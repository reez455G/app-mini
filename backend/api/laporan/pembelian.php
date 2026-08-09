<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Pembelian: Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT p.id, p.no_faktur, p.tanggal, s.nama AS suplier, p.total_qty, p.total_biaya
     FROM pembelian p JOIN suplier s ON s.id = p.suplier_id
     WHERE p.tanggal BETWEEN ? AND ? ORDER BY p.tanggal DESC'
);
$stmt->execute([$from, $to]);
$data = $stmt->fetchAll();

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
