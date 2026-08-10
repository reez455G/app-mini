<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Laba: Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

// Biaya per baris pakai harga_beli batch (lot) yang BENAR-BENAR terjual saat
// itu (penjualan_item_lot, diisi FIFO oleh penjualan.php), bukan harga barang
// TERKINI yang bisa saja sudah berubah sejak transaksi lama terjadi.
// LEFT JOIN: transaksi lama sebelum migrasi lot belum punya baris ini —
// cost-nya jadi 0 sampai skrip backfill dijalankan.
$stmt = $pdo->prepare(
    "SELECT pj.id, pj.invoice_no, pj.cust_name, pj.grand_total, pj.created_at,
            COALESCE(SUM(pil.qty * pil.harga_beli), 0) AS cost
     FROM penjualan pj
     JOIN penjualan_item pi ON pi.penjualan_id = pj.id
     LEFT JOIN penjualan_item_lot pil ON pil.penjualan_item_id = pi.id
     WHERE pj.tanggal BETWEEN ? AND ?
     GROUP BY pj.id, pj.invoice_no, pj.cust_name, pj.grand_total, pj.created_at
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
    return ['invoice_no' => $r['invoice_no'], 'cust_name' => $r['cust_name'], 'created_at' => $r['created_at'], 'profit' => $profit, 'margin_pct' => $margin];
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
