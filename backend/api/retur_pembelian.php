<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_owner(); // Retur Pembelian: Owner only
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $rows = $pdo->query(
        'SELECT rp.id, rp.no_retur, rp.original_invoice_no, s.nama AS suplier, rp.tanggal, rp.total_qty
         FROM retur_pembelian rp JOIN suplier s ON s.id = rp.suplier_id ORDER BY rp.tanggal DESC, rp.id DESC'
    )->fetchAll();
    json_ok($rows);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id wajib diisi.', 400);

    $pdo->beginTransaction();
    try {
        $h = $pdo->prepare('SELECT id FROM retur_pembelian WHERE id = ? FOR UPDATE');
        $h->execute([$id]);
        if (!$h->fetchColumn()) throw new RuntimeException('Retur pembelian tidak ditemukan.');

        $items = $pdo->prepare('SELECT barang_id, qty FROM retur_pembelian_item WHERE retur_id = ?');
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Balikkan efek stok retur pembelian (POST mengurangi stok — barang
        // keluar ke suplier — jadi di sini ditambah kembali). Menambah
        // selalu aman, tidak perlu validasi negatif.
        foreach ($itemRows as $it) {
            $pdo->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')->execute([$it['qty'], $it['barang_id']]);
        }

        // retur_pembelian_item ikut terhapus lewat ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM retur_pembelian WHERE id = ?')->execute([$id]);

        $pdo->commit();
        json_ok(['deleted' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
}

if ($method !== 'POST') json_error('Method not allowed', 405);

$in = body();
$supplierName = trim($in['suplier'] ?? '');
$invoice = trim($in['original_invoice_no'] ?? '');
$tanggal = $in['tanggal'] ?? date('Y-m-d');
$items = $in['items'] ?? [];
if ($supplierName === '' || $invoice === '' || !$items) json_error('Suplier, no. faktur asal, dan minimal 1 barang wajib diisi.');

$sup = $pdo->prepare('SELECT id FROM suplier WHERE nama = ?');
$sup->execute([$supplierName]);
$suplierId = $sup->fetchColumn();
if (!$suplierId) json_error('Suplier tidak ditemukan.');

$pdo->beginTransaction();
try {
    // Sama seperti retur penjualan: retur harus mengacu ke faktur pembelian yang
    // benar-benar ada, supaya qty retur tidak bisa melebihi yang pernah dibeli.
    $pb = $pdo->prepare('SELECT id FROM pembelian WHERE no_faktur = ?');
    $pb->execute([$invoice]);
    $pembelianId = $pb->fetchColumn();
    if (!$pembelianId) throw new RuntimeException("No. faktur \"$invoice\" tidak ditemukan.");

    $totalQty = 0; $rows = [];
    foreach ($items as $line) {
        $kode = trim($line['kode'] ?? '');
        $qty = (int)($line['qty'] ?? 0);
        $reason = trim($line['reason'] ?? '') ?: 'Lainnya';
        if ($kode === '' || $qty < 1) throw new RuntimeException('Setiap item butuh kode dan qty >= 1.');
        $b = $pdo->prepare('SELECT id, nama, stok FROM barang WHERE kode = ? FOR UPDATE');
        $b->execute([$kode]);
        $barang = $b->fetch();
        if (!$barang) throw new RuntimeException("Barang \"$kode\" tidak ditemukan.");

        $bought = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM pembelian_item WHERE pembelian_id = ? AND barang_id = ?');
        $bought->execute([$pembelianId, $barang['id']]);
        $boughtQty = (int)$bought->fetchColumn();
        if ($boughtQty === 0) throw new RuntimeException("{$barang['nama']} tidak ada di faktur $invoice.");

        $ret = $pdo->prepare(
            'SELECT COALESCE(SUM(ri.qty), 0) FROM retur_pembelian_item ri
             JOIN retur_pembelian r ON r.id = ri.retur_id
             WHERE r.original_invoice_no = ? AND ri.barang_id = ?'
        );
        $ret->execute([$invoice, $barang['id']]);
        $sisa = $boughtQty - (int)$ret->fetchColumn();
        if ($qty > $sisa) throw new RuntimeException("Retur {$barang['nama']} melebihi yang bisa diretur dari faktur $invoice (sisa $sisa pcs).");

        // Barang fisik keluar ke suplier. Stok harus benar-benar cukup — dulu
        // dipotong dengan max(0, ...) yang diam-diam mencatat retur lebih besar
        // daripada stok yang sebenarnya berkurang.
        if ($qty > (int)$barang['stok']) throw new RuntimeException("Stok {$barang['nama']} tidak mencukupi untuk diretur (tersisa {$barang['stok']} pcs).");
        $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$qty, $barang['id']]);
        $totalQty += $qty;
        $rows[] = [$barang['id'], $barang['nama'], $qty, $reason];
    }

    $noRetur = next_doc_no($pdo, 'retur_pembelian', 'no_retur', 'RB');
    $pdo->prepare('INSERT INTO retur_pembelian (no_retur, original_invoice_no, suplier_id, tanggal, total_qty, created_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$noRetur, $invoice, $suplierId, $tanggal, $totalQty, $me['id']]);
    $returId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO retur_pembelian_item (retur_id, barang_id, nama_snapshot, qty, reason) VALUES (?, ?, ?, ?, ?)');
    foreach ($rows as $r) $itemStmt->execute([$returId, ...$r]);

    $pdo->commit();
    json_ok(['id' => $returId, 'no_retur' => $noRetur, 'total_qty' => $totalQty], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
