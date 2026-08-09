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

        $items = $pdo->prepare('SELECT barang_id, qty, nama_snapshot FROM retur_penjualan_item WHERE retur_id = ?');
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Balikkan efek stok retur penjualan (POST menambah stok — barang
        // masuk lagi dari pelanggan — jadi di sini dikurangi kembali).
        // Validasi dulu semua item supaya tidak ada yang kepotong negatif
        // (mis. barang yang baru masuk lagi itu sudah keburu terjual ulang).
        foreach ($itemRows as $it) {
            $b = $pdo->prepare('SELECT stok FROM barang WHERE id = ? FOR UPDATE');
            $b->execute([$it['barang_id']]);
            $stok = $b->fetchColumn();
            if ($stok === false) continue; // barangnya sendiri sudah dihapus terpisah
            if ((int)$stok < (int)$it['qty']) {
                throw new RuntimeException("Stok {$it['nama_snapshot']} tidak cukup untuk dibalik saat menghapus retur ini (tersisa $stok pcs, butuh {$it['qty']} pcs).");
            }
        }
        foreach ($itemRows as $it) {
            $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$it['qty'], $it['barang_id']]);
        }

        // retur_penjualan_item ikut terhapus lewat ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM retur_penjualan WHERE id = ?')->execute([$id]);

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
$tanggal = $in['tanggal'] ?? date('Y-m-d');
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
        $totalQty += $qty;
        $rows[] = [$barang['id'], $barang['nama'], $qty, $reason];
    }

    $noRetur = next_doc_no($pdo, 'retur_penjualan', 'no_retur', 'RJ');
    $pdo->prepare('INSERT INTO retur_penjualan (no_retur, original_invoice_no, customer_name, tanggal, total_qty, created_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$noRetur, $invoice, $customer, $tanggal, $totalQty, $me['id']]);
    $returId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO retur_penjualan_item (retur_id, barang_id, nama_snapshot, qty, reason) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as $r) $itemStmt->execute([$returId, ...$r]);

    $pdo->commit();
    json_ok(['id' => $returId, 'no_retur' => $noRetur, 'total_qty' => $totalQty], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
