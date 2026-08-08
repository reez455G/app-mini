<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_login(); // Retur Penjualan: Owner & Karyawan
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $rows = $pdo->query('SELECT id, no_retur, original_invoice_no, customer_name, tanggal, total_qty FROM retur_penjualan ORDER BY tanggal DESC, id DESC')->fetchAll();
    json_ok($rows);
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
