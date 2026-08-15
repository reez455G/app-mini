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

// Barang yang diretur pelanggan kembali ke rak, jadi omzet DAN modalnya harus
// dibatalkan — tanpa ini barang yang sama terhitung dua kali (sebagai
// penjualan sekaligus sebagai stok). Sama untuk retur ke suplier terhadap
// belanja. Keduanya dihitung pada tanggal retur.
$returPj = $pdo->prepare(
    'SELECT COALESCE(SUM(nilai_omzet), 0) AS omzet, COALESCE(SUM(nilai_modal), 0) AS modal
     FROM (' . sql_retur_penjualan_nilai() . ') r WHERE r.tanggal BETWEEN ? AND ?'
);
$returPj->execute([$from, $to]);
$rj = $returPj->fetch();

$returPb = $pdo->prepare(
    'SELECT COALESCE(SUM(nilai_biaya), 0) FROM (' . sql_retur_pembelian_nilai() . ') r WHERE r.tanggal BETWEEN ? AND ?'
);
$returPb->execute([$from, $to]);
$returBelanja = (float)$returPb->fetchColumn();

$grossRevenue = (float)$pj['revenue'];
$returOmzet = (float)$rj['omzet'];
$revenue = $grossRevenue - $returOmzet;
$hpp -= (float)$rj['modal'];
$belanja = (float)$pb['cost'] - $returBelanja;
$netProfit = $revenue - $hpp;

json_ok([
    'gross_revenue' => $grossRevenue,
    'total_retur' => $returOmzet,
    'total_revenue' => $revenue,   // omzet BERSIH (sesudah retur)
    'total_cost' => $hpp,
    'net_profit' => $netProfit,
    'margin_pct' => $revenue ? $netProfit / $revenue * 100 : 0,
    'roi_pct' => $hpp ? $netProfit / $hpp * 100 : 0,
    'tx_count' => (int)$pj['tx_count'],
    'avg_sale' => $pj['tx_count'] ? $revenue / $pj['tx_count'] : 0,
    'customers_served' => (int)$pj['customers'],
    'items_sold' => (int)$pj['items'],
    'purchase_tx' => (int)$pb['tx_count'],
    'total_belanja' => $belanja,
    'retur_belanja' => $returBelanja,
    'suppliers_used' => (int)$pb['suppliers'],
    'avg_purchase' => $pb['tx_count'] ? $belanja / $pb['tx_count'] : 0,
]);
