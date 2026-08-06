<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Stok Laris: Owner only
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

$byQty = $agg; usort($byQty, fn($a, $b) => $b['qty'] <=> $a['qty']);
$byRevenue = $agg; usort($byRevenue, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
$byProfit = $agg; usort($byProfit, fn($a, $b) => $b['profit'] <=> $a['profit']);

json_ok([
    'by_qty' => array_slice($byQty, 0, $limit),
    'by_revenue' => array_slice($byRevenue, 0, $limit),
    'by_profit' => array_slice($byProfit, 0, $limit),
]);
