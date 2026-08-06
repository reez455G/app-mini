<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_login(); // Transaksi Penjualan: Owner & Karyawan
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// Auto-deteksi tier harga dari qty — sama seperti addToCart() di Dashboard.dc.html.
function tier_for_qty(int $qty): string {
    if ($qty >= 11) return 'grosir';
    if ($qty >= 6) return 'bengkel';
    return 'ecer';
}

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $h = $pdo->prepare('SELECT * FROM penjualan WHERE id = ?');
        $h->execute([$id]);
        $header = $h->fetch();
        if (!$header) json_error('Penjualan tidak ditemukan.', 404);
        $it = $pdo->prepare('SELECT nama_snapshot AS nama, tier, qty, unit_price, subtotal FROM penjualan_item WHERE penjualan_id = ?');
        $it->execute([$id]);
        $header['items'] = $it->fetchAll();
        json_ok($header);
    }
    $rows = $pdo->query('SELECT id, invoice_no, tanggal, cust_name, payment_method, total_qty, grand_total FROM penjualan ORDER BY tanggal DESC, id DESC')->fetchAll();
    json_ok($rows);
}

if ($method !== 'POST') json_error('Method not allowed', 405);

$in = body();
$custName = trim($in['cust_name'] ?? '');
$items = $in['items'] ?? [];
if (!$items) json_error('Keranjang masih kosong.');
$paymentMethod = ($in['payment_method'] ?? 'TUNAI') === 'TRANSFER' ? 'TRANSFER' : 'TUNAI';
$amountPaid = (float)($in['amount_paid'] ?? 0);

$pdo->beginTransaction();
try {
    $totalQty = 0; $grandTotal = 0; $rows = [];
    foreach ($items as $line) {
        $kode = trim($line['kode'] ?? '');
        $qty = (int)($line['qty'] ?? 0);
        if ($kode === '' || $qty < 1) throw new RuntimeException('Setiap item butuh kode dan qty >= 1.');

        // FOR UPDATE: kunci baris stok selama transaksi supaya dua kasir yang jual
        // barang yang sama bersamaan tidak sama-sama lolos validasi stok (race condition).
        $b = $pdo->prepare('SELECT id, nama, stok, harga_ecer, harga_bengkel, harga_grosir FROM barang WHERE kode = ? FOR UPDATE');
        $b->execute([$kode]);
        $barang = $b->fetch();
        if (!$barang) throw new RuntimeException("Barang \"$kode\" tidak ditemukan.");
        if ($qty > $barang['stok']) throw new RuntimeException("Stok {$barang['nama']} tidak mencukupi (tersisa {$barang['stok']} pcs).");

        $tier = tier_for_qty($qty);
        $unitPrice = (float)$barang['harga_' . $tier];
        $subtotal = $unitPrice * $qty;
        $totalQty += $qty;
        $grandTotal += $subtotal;

        $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$qty, $barang['id']]);
        $rows[] = [$barang['id'], $barang['nama'], $tier, $qty, $unitPrice, $subtotal];
    }

    $pelangganId = null;
    if ($custName !== '') {
        $p = $pdo->prepare('SELECT id FROM pelanggan WHERE nama = ?');
        $p->execute([$custName]);
        $pelangganId = $p->fetchColumn() ?: null;
    }

    $invoiceNo = next_doc_no($pdo, 'penjualan', 'invoice_no', 'INV');
    $kembalian = $paymentMethod === 'TUNAI' ? $amountPaid - $grandTotal : 0;

    $pdo->prepare(
        'INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by)
         VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$invoiceNo, $pelangganId, $custName ?: 'Umum', $paymentMethod, $amountPaid, $totalQty, $grandTotal, $kembalian, $me['id']]);
    $penjualanId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) $itemStmt->execute([$penjualanId, ...$r]);

    $pdo->commit();
    json_ok(['id' => $penjualanId, 'invoice_no' => $invoiceNo, 'total_qty' => $totalQty, 'grand_total' => $grandTotal, 'kembalian' => $kembalian], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
