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
        $newStok = max(0, (int)$barang['stok'] - $qty); // barang fisik keluar ke suplier, tidak boleh negatif
        $pdo->prepare('UPDATE barang SET stok = ? WHERE id = ?')->execute([$newStok, $barang['id']]);
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
