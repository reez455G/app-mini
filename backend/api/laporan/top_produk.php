<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Dashboard "Produk Terlaris" dipakai Owner & Karyawan — tapi profit (data
// finansial) disembunyikan untuk Karyawan, sama seperti barang.php.
$me = require_login();
$isOwner = $me['role'] === 'owner';
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');
$limit = min(50, max(1, (int)($_GET['limit'] ?? 5)));

$stmt = $pdo->prepare(
    "SELECT b.kode, b.nama, SUM(pi.qty) AS qty, SUM(pi.subtotal) AS revenue,
            SUM(pi.subtotal) - SUM(pi.qty * IF(b.harga_netto > 0, b.harga_netto, b.harga_faktur)) AS profit
     FROM penjualan_item pi
     JOIN penjualan pj ON pj.id = pi.penjualan_id
     JOIN barang b ON b.id = pi.barang_id
     WHERE pj.tanggal BETWEEN ? AND ?
     GROUP BY b.id, b.kode, b.nama"
);
$stmt->execute([$from, $to]);
$agg = $stmt->fetchAll();
if (!$isOwner) {
    $agg = array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama'], 'qty' => $r['qty'], 'revenue' => $r['revenue']], $agg);
}

$byQty = $agg; usort($byQty, fn($a, $b) => $b['qty'] <=> $a['qty']);
$byRevenue = $agg; usort($byRevenue, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

$out = ['by_qty' => array_slice($byQty, 0, $limit), 'by_revenue' => array_slice($byRevenue, 0, $limit)];
if ($isOwner) {
    $byProfit = $agg; usort($byProfit, fn($a, $b) => $b['profit'] <=> $a['profit']);
    $out['by_profit'] = array_slice($byProfit, 0, $limit);
}
json_ok($out);
