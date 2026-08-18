<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_login(); // Retur Penjualan: Owner & Karyawan
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// Cek invoice sebelum retur diproses: kembalikan pelanggan + daftar barang
// yang MASIH bisa diretur dari invoice itu (qty terjual dikurangi yang sudah
// pernah diretur), supaya frontend bisa isi tabel "Barang yang Diretur"
// otomatis alih-alih user pilih barang manual dari seluruh stok (yang gampang
// salah pilih barang yang tidak ada di invoice itu sama sekali).
if ($method === 'GET' && !empty($_GET['invoice_no'])) {
    $invoice = trim($_GET['invoice_no']);
    $pj = $pdo->prepare('SELECT id, cust_name, tanggal FROM penjualan WHERE invoice_no = ?');
    $pj->execute([$invoice]);
    $header = $pj->fetch();
    if (!$header) json_error("Invoice \"$invoice\" tidak ditemukan.", 404);

    $items = $pdo->prepare(
        'SELECT b.kode, pi.nama_snapshot AS nama, SUM(pi.qty) AS sold_qty,
            (SELECT COALESCE(SUM(ri.qty), 0) FROM retur_penjualan_item ri
             JOIN retur_penjualan r ON r.id = ri.retur_id
             WHERE r.original_invoice_no = ? AND ri.barang_id = pi.barang_id) AS returned_qty
         FROM penjualan_item pi JOIN barang b ON b.id = pi.barang_id
         WHERE pi.penjualan_id = ?
         GROUP BY pi.barang_id, b.kode, pi.nama_snapshot'
    );
    $items->execute([$invoice, $header['id']]);
    $itemRows = array_values(array_filter(array_map(function ($r) {
        return ['kode' => $r['kode'], 'nama' => $r['nama'], 'sisa' => (int)$r['sold_qty'] - (int)$r['returned_qty']];
    }, $items->fetchAll()), fn($r) => $r['sisa'] > 0));

    json_ok(['customer_name' => $header['cust_name'], 'tanggal' => $header['tanggal'], 'items' => $itemRows]);
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, no_retur, original_invoice_no, customer_name, tanggal, total_qty FROM retur_penjualan ORDER BY tanggal DESC, id DESC')->fetchAll();
    json_ok($rows);
}

if ($method === 'DELETE') {
    require_owner(); // hapus transaksi: Owner only, beda dari GET/POST yang boleh Karyawan
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id wajib diisi.', 400);

    $pdo->beginTransaction();
    try {
        $h = $pdo->prepare('SELECT id FROM retur_penjualan WHERE id = ? FOR UPDATE');
        $h->execute([$id]);
        if (!$h->fetchColumn()) throw new RuntimeException('Retur penjualan tidak ditemukan.');

        $items = $pdo->prepare('SELECT barang_id, qty, nama_snapshot, barang_lot_id FROM retur_penjualan_item WHERE retur_id = ?');
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Retur ini menciptakan batch baru (lihat POST) — kalau batch itu
        // sudah kejual sebagian sejak retur ini dibuat, tolak (guard lebih
        // presisi dari cek stok agregat). Item lama tanpa barang_lot_id
        // (data sebelum fitur ini ada) tetap pakai cek stok agregat seperti
        // sebelumnya.
        foreach ($itemRows as $it) {
            if ($it['barang_lot_id']) {
                $lot = $pdo->prepare('SELECT qty_awal, qty_sisa FROM barang_lot WHERE id = ? FOR UPDATE');
                $lot->execute([$it['barang_lot_id']]);
                $lotRow = $lot->fetch();
                if ($lotRow && (int)$lotRow['qty_sisa'] !== (int)$lotRow['qty_awal']) {
                    throw new RuntimeException("Batch {$it['nama_snapshot']} dari retur ini sudah terjual lagi sebagian, tidak bisa dihapus.");
                }
            } else {
                $b = $pdo->prepare('SELECT stok FROM barang WHERE id = ? FOR UPDATE');
                $b->execute([$it['barang_id']]);
                $stok = $b->fetchColumn();
                if ($stok !== false && (int)$stok < (int)$it['qty']) {
                    throw new RuntimeException("Stok {$it['nama_snapshot']} tidak cukup untuk dibalik saat menghapus retur ini (tersisa $stok pcs, butuh {$it['qty']} pcs).");
                }
            }
        }
        foreach ($itemRows as $it) {
            $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$it['qty'], $it['barang_id']]);
        }

        // retur_penjualan_item ikut terhapus lewat ON DELETE CASCADE — baru
        // setelah itu barang_lot yang dibuat retur ini boleh dihapus (FK
        // retur_penjualan_item.barang_lot_id masih RESTRICT selama baris
        // itu ada).
        $pdo->prepare('DELETE FROM retur_penjualan WHERE id = ?')->execute([$id]);
        foreach ($itemRows as $it) {
            if ($it['barang_lot_id']) {
                $pdo->prepare('DELETE FROM barang_lot WHERE id = ?')->execute([$it['barang_lot_id']]);
            }
        }

        $pdo->commit();
        json_ok(['deleted' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
}

if ($method !== 'POST') json_error('Method not allowed', 405);

$in = body();
$customer = trim($in['customer_name'] ?? '');
$invoice = trim($in['original_invoice_no'] ?? '');
// ?: bukan ??  — field tanggal yang dikosongkan user mengirim string kosong,
// dan ?? cuma menangkap null. Tanpa ini '' masuk ke kolom DATE dan ditolak
// MySQL (sql_mode STRICT) sebagai error mentah, bukan jatuh ke hari ini.
$tanggal = ($in['tanggal'] ?? '') ?: date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) json_error('Tanggal retur tidak valid.');
if ($tanggal > date('Y-m-d')) json_error('Tanggal retur tidak boleh di masa depan.');
$items = $in['items'] ?? [];
if ($customer === '' || $invoice === '' || !$items) json_error('Pelanggan, no. invoice asal, dan minimal 1 barang wajib diisi.');

$pdo->beginTransaction();
try {
    // Retur menambah stok, jadi harus diikat ke penjualan yang benar-benar ada:
    // tanpa ini nomor invoice karangan + qty berapa pun akan diterima dan stok
    // bisa digelembungkan sesuka hati.
    $pj = $pdo->prepare('SELECT id FROM penjualan WHERE invoice_no = ?');
    $pj->execute([$invoice]);
    $penjualanId = $pj->fetchColumn();
    if (!$penjualanId) throw new RuntimeException("Invoice \"$invoice\" tidak ditemukan.");

    $totalQty = 0; $rows = [];
    foreach ($items as $line) {
        $kode = trim($line['kode'] ?? '');
        $qty = (int)($line['qty'] ?? 0);
        $reason = trim($line['reason'] ?? '') ?: 'Lainnya';
        if ($kode === '' || $qty < 1) throw new RuntimeException('Setiap item butuh kode dan qty >= 1.');
        $b = $pdo->prepare('SELECT id, nama FROM barang WHERE kode = ?');
        $b->execute([$kode]);
        $barang = $b->fetch();
        if (!$barang) throw new RuntimeException("Barang \"$kode\" tidak ditemukan.");

        $sold = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM penjualan_item WHERE penjualan_id = ? AND barang_id = ?');
        $sold->execute([$penjualanId, $barang['id']]);
        $soldQty = (int)$sold->fetchColumn();
        if ($soldQty === 0) throw new RuntimeException("{$barang['nama']} tidak ada di invoice $invoice.");

        // Kurangi yang sudah pernah diretur atas invoice yang sama, supaya satu
        // barang tidak bisa diretur berkali-kali sampai melebihi yang terjual.
        $ret = $pdo->prepare(
            'SELECT COALESCE(SUM(ri.qty), 0) FROM retur_penjualan_item ri
             JOIN retur_penjualan r ON r.id = ri.retur_id
             WHERE r.original_invoice_no = ? AND ri.barang_id = ?'
        );
        $ret->execute([$invoice, $barang['id']]);
        $sisa = $soldQty - (int)$ret->fetchColumn();
        if ($qty > $sisa) throw new RuntimeException("Retur {$barang['nama']} melebihi yang bisa diretur dari invoice $invoice (sisa $sisa pcs).");

        $pdo->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')->execute([$qty, $barang['id']]);

        // ponytail: barang yang kembali fisik dianggap batch BARU (bukan
        // dilacak balik ke batch asal penjualannya secara proporsional —
        // rumit kalau ada retur parsial berulang atas invoice yang sama).
        // Biayanya rata-rata tertimbang dari batch yang benar-benar terjual
        // di transaksi ini (penjualan_item_lot), suplier-nya ikut yang
        // menyumbang qty terbanyak. Upgrade path kalau nanti perlu presisi
        // lebih: lacak balik proporsional per baris penjualan_item_lot.
        $costRows = $pdo->prepare(
            'SELECT pil.harga_beli, pil.qty, bl.suplier_id
             FROM penjualan_item_lot pil
             JOIN penjualan_item pi ON pi.id = pil.penjualan_item_id
             JOIN barang_lot bl ON bl.id = pil.barang_lot_id
             WHERE pi.penjualan_id = ? AND pi.barang_id = ?'
        );
        $costRows->execute([$penjualanId, $barang['id']]);
        $totalCostQty = 0; $totalCostValue = 0.0; $supplierQty = [];
        foreach ($costRows->fetchAll() as $c) {
            $totalCostQty += (int)$c['qty'];
            $totalCostValue += (int)$c['qty'] * (float)$c['harga_beli'];
            $supplierQty[$c['suplier_id']] = ($supplierQty[$c['suplier_id']] ?? 0) + (int)$c['qty'];
        }
        $newLotId = null;
        if ($totalCostQty > 0) {
            arsort($supplierQty);
            $dominantSupplierId = array_key_first($supplierQty);
            $avgCost = $totalCostValue / $totalCostQty;
            $pdo->prepare(
                'INSERT INTO barang_lot (barang_id, pembelian_item_id, suplier_id, harga_beli, qty_awal, qty_sisa, tanggal)
                 VALUES (?, NULL, ?, ?, ?, ?, ?)'
            )->execute([$barang['id'], $dominantSupplierId, $avgCost, $qty, $qty, $tanggal]);
            $newLotId = (int)$pdo->lastInsertId();
        }
        // $newLotId tetap NULL kalau penjualan aslinya tidak punya rincian
        // lot sama sekali (data lama sebelum fitur ini ada) — stok tetap
        // benar, cuma retur itu tidak ikut tercatat granular per-batch.

        $totalQty += $qty;
        $rows[] = [$barang['id'], $barang['nama'], $qty, $reason, $newLotId];
    }

    $noRetur = next_doc_no($pdo, 'retur_penjualan', 'no_retur', 'RJ');
    $pdo->prepare('INSERT INTO retur_penjualan (no_retur, original_invoice_no, customer_name, tanggal, total_qty, created_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$noRetur, $invoice, $customer, $tanggal, $totalQty, $me['id']]);
    $returId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO retur_penjualan_item (retur_id, barang_id, nama_snapshot, qty, reason, barang_lot_id) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $r) $itemStmt->execute([$returId, ...$r]);

    $pdo->commit();
    json_ok(['id' => $returId, 'no_retur' => $noRetur, 'total_qty' => $totalQty], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
