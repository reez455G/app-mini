<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Pembelian: Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT p.id, p.no_faktur, p.tanggal, p.created_at, s.nama AS suplier, p.total_qty, p.total_biaya
     FROM pembelian p JOIN suplier s ON s.id = p.suplier_id
     WHERE p.tanggal BETWEEN ? AND ? ORDER BY p.tanggal DESC'
);
$stmt->execute([$from, $to]);
$data = $stmt->fetchAll();

// Rincian barang per faktur -- dipakai export Laporan Pembelian (1 baris
// per barang, bukan cuma 1 baris ringkasan per faktur) supaya kelihatan
// barang apa saja yang dibeli dan harga faktur/netto masing-masing.
// b.pricelist: Pricelist/HET SAAT INI di Data Barang (bukan snapshot waktu
// pembelian) -- barang bisa saja sudah diedit Pricelist/HET-nya sesudah
// faktur ini dibuat.
$itemStmt = $pdo->prepare(
    'SELECT pi.pembelian_id, pi.kode_snapshot AS kode, pi.nama_snapshot AS nama,
            pi.harga_faktur, pi.harga_netto, pi.qty, pi.subtotal, b.pricelist AS pricelist_het
     FROM pembelian_item pi JOIN pembelian p ON p.id = pi.pembelian_id JOIN barang b ON b.id = pi.barang_id
     WHERE p.tanggal BETWEEN ? AND ? ORDER BY pi.id'
);
$itemStmt->execute([$from, $to]);
$itemsByPembelian = [];
foreach ($itemStmt->fetchAll() as $it) {
    $itemsByPembelian[(int)$it['pembelian_id']][] = [
        'kode' => $it['kode'], 'nama' => $it['nama'],
        'harga_faktur' => (float)$it['harga_faktur'], 'harga_netto' => (float)$it['harga_netto'],
        'qty' => (int)$it['qty'], 'subtotal' => (float)$it['subtotal'], 'pricelist_het' => (float)$it['pricelist_het'],
    ];
}
$data = array_map(function ($r) use ($itemsByPembelian) {
    $r['items'] = $itemsByPembelian[(int)$r['id']] ?? [];
    return $r;
}, $data);

$perSupplier = [];
foreach ($data as $r) {
    $s = $r['suplier'];
    $perSupplier[$s] ??= ['suplier' => $s, 'invoice_count' => 0, 'total_biaya' => 0.0];
    $perSupplier[$s]['invoice_count']++;
    $perSupplier[$s]['total_biaya'] += (float)$r['total_biaya'];
}

json_ok([
    'rows' => $data,
    'per_suplier' => array_values($perSupplier),
    'total_biaya' => array_sum(array_column($data, 'total_biaya')),
    'total_items' => array_sum(array_column($data, 'total_qty')),
    'avg_invoice' => count($data) ? array_sum(array_column($data, 'total_biaya')) / count($data) : 0,
]);
