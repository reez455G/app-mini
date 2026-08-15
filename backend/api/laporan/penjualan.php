<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_login(); // Laporan Penjualan: Owner & Karyawan
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT id, invoice_no, tanggal, created_at, cust_name, payment_method, total_qty, grand_total
     FROM penjualan WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, created_at DESC'
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

// Retur penjualan membatalkan omzet — dihitung pada tanggal retur, sama
// seperti laporan/laba.php & global.php supaya ketiganya tidak saling
// bertentangan.
$returStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(nilai_omzet), 0) FROM (' . sql_retur_penjualan_nilai() . ') r WHERE r.tanggal BETWEEN ? AND ?'
);
$returStmt->execute([$from, $to]);
$totalRetur = (float)$returStmt->fetchColumn();

$grossRevenue = array_sum(array_column($data, 'grand_total'));
json_ok([
    'rows' => $data,
    'per_customer' => $perCustomer,
    'gross_revenue' => $grossRevenue,
    'total_retur' => $totalRetur,
    'total_revenue' => $grossRevenue - $totalRetur,
    'total_items' => array_sum(array_column($data, 'total_qty')),
    'avg_sale' => count($data) ? ($grossRevenue - $totalRetur) / count($data) : 0,
    'tx_count' => count($data),
]);
