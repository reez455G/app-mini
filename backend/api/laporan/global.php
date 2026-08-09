<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Global (dashboard KPI): Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$penjualan = $pdo->prepare('SELECT COUNT(*) tx_count, COALESCE(SUM(grand_total),0) revenue, COALESCE(SUM(total_qty),0) items, COUNT(DISTINCT cust_name) customers FROM penjualan WHERE tanggal BETWEEN ? AND ?');
$penjualan->execute([$from, $to]);
$pj = $penjualan->fetch();

$pembelian = $pdo->prepare('SELECT COUNT(*) tx_count, COALESCE(SUM(total_biaya),0) cost, COUNT(DISTINCT suplier_id) suppliers FROM pembelian WHERE tanggal BETWEEN ? AND ?');
$pembelian->execute([$from, $to]);
$pb = $pembelian->fetch();

// Cost dari harga_beli batch (lot) yang benar-benar terjual, lihat komentar
// serupa di laporan/laba.php.
$costStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(pil.qty * pil.harga_beli),0) AS cost
     FROM penjualan_item pi
     JOIN penjualan pj ON pj.id = pi.penjualan_id
     LEFT JOIN penjualan_item_lot pil ON pil.penjualan_item_id = pi.id
     WHERE pj.tanggal BETWEEN ? AND ?"
);
$costStmt->execute([$from, $to]);
$hpp = (float)$costStmt->fetchColumn();

$revenue = (float)$pj['revenue'];
$netProfit = $revenue - $hpp;

json_ok([
    'total_revenue' => $revenue,
    'total_cost' => $hpp,
    'net_profit' => $netProfit,
    'margin_pct' => $revenue ? $netProfit / $revenue * 100 : 0,
    'roi_pct' => $hpp ? $netProfit / $hpp * 100 : 0,
    'tx_count' => (int)$pj['tx_count'],
    'avg_sale' => $pj['tx_count'] ? $revenue / $pj['tx_count'] : 0,
    'customers_served' => (int)$pj['customers'],
    'items_sold' => (int)$pj['items'],
    'purchase_tx' => (int)$pb['tx_count'],
    'suppliers_used' => (int)$pb['suppliers'],
    'avg_purchase' => $pb['tx_count'] ? (float)$pb['cost'] / $pb['tx_count'] : 0,
]);
