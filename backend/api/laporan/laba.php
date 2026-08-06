<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Laba: Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

// Biaya per baris pakai harga_netto/harga_faktur TERKINI barang (bukan harga saat
// transaksi dulu) — sama seperti costOfLine() di Dashboard.dc.html.
$stmt = $pdo->prepare(
    "SELECT pj.id, pj.invoice_no, pj.cust_name, pj.grand_total,
            COALESCE(SUM(pi.qty * IF(b.harga_netto > 0, b.harga_netto, b.harga_faktur)), 0) AS cost
     FROM penjualan pj
     JOIN penjualan_item pi ON pi.penjualan_id = pj.id
     JOIN barang b ON b.id = pi.barang_id
     WHERE pj.tanggal BETWEEN ? AND ?
     GROUP BY pj.id, pj.invoice_no, pj.cust_name, pj.grand_total
     ORDER BY pj.tanggal DESC"
);
$stmt->execute([$from, $to]);
$data = $stmt->fetchAll();

$totalRevenue = 0.0; $totalCost = 0.0;
$perTx = array_map(function ($r) use (&$totalRevenue, &$totalCost) {
    $profit = (float)$r['grand_total'] - (float)$r['cost'];
    $margin = $r['grand_total'] > 0 ? $profit / $r['grand_total'] * 100 : 0;
    $totalRevenue += (float)$r['grand_total'];
    $totalCost += (float)$r['cost'];
    return ['invoice_no' => $r['invoice_no'], 'cust_name' => $r['cust_name'], 'profit' => $profit, 'margin_pct' => $margin];
}, $data);

$grossProfit = $totalRevenue - $totalCost;
json_ok([
    'rows' => $perTx,
    'total_revenue' => $totalRevenue,
    'total_cost' => $totalCost,
    'gross_profit' => $grossProfit,
    'margin_pct' => $totalRevenue ? $grossProfit / $totalRevenue * 100 : 0,
    'roi_pct' => $totalCost ? $grossProfit / $totalCost * 100 : 0,
]);
