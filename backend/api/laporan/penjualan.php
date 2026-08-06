<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_login(); // Laporan Penjualan: Owner & Karyawan
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT invoice_no, tanggal, cust_name, payment_method, total_qty, grand_total
     FROM penjualan WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC'
);
$stmt->execute([$from, $to]);
$data = $stmt->fetchAll();

$perCustomer = [];
foreach ($data as $r) {
    $c = $r['cust_name'];
    $perCustomer[$c] ??= ['cust_name' => $c, 'invoice_count' => 0, 'total_qty' => 0, 'total_amount' => 0.0];
    $perCustomer[$c]['invoice_count']++;
    $perCustomer[$c]['total_qty'] += (int)$r['total_qty'];
    $perCustomer[$c]['total_amount'] += (float)$r['grand_total'];
}
$perCustomer = array_values($perCustomer);
usort($perCustomer, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

json_ok([
    'rows' => $data,
    'per_customer' => $perCustomer,
    'total_revenue' => array_sum(array_column($data, 'grand_total')),
    'total_items' => array_sum(array_column($data, 'total_qty')),
    'avg_sale' => count($data) ? array_sum(array_column($data, 'grand_total')) / count($data) : 0,
    'tx_count' => count($data),
]);
