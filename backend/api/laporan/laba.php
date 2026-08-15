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

// Retur penjualan membatalkan omzet DAN modal barang yang kembali. Dihitung
// pada tanggal RETUR-nya (bukan tanggal nota aslinya), supaya ringkasan di
// bawah selalu sama dengan penjumlahan baris-barisnya. Nota lama yang diretur
// di periode ini karena itu tetap ikut terkoreksi di periode ini.
$returStmt = $pdo->prepare(
    'SELECT penjualan_id, SUM(nilai_omzet) AS omzet, SUM(nilai_modal) AS modal
     FROM (' . sql_retur_penjualan_nilai() . ') r
     WHERE r.tanggal BETWEEN ? AND ? GROUP BY penjualan_id'
);
$returStmt->execute([$from, $to]);
$returPerNota = [];
foreach ($returStmt->fetchAll() as $r) {
    $returPerNota[(int)$r['penjualan_id']] = ['omzet' => (float)$r['omzet'], 'modal' => (float)$r['modal']];
}

$totalRevenue = 0.0; $totalCost = 0.0; $totalRetur = 0.0;
$perTx = array_map(function ($r) use (&$totalRevenue, &$totalCost, &$totalRetur, $returPerNota) {
    $retur = $returPerNota[(int)$r['id']] ?? ['omzet' => 0.0, 'modal' => 0.0];
    $revenue = (float)$r['grand_total'] - $retur['omzet'];
    $cost = (float)$r['cost'] - $retur['modal'];
    $profit = $revenue - $cost;
    $margin = $revenue > 0 ? $profit / $revenue * 100 : 0;
    $totalRevenue += (float)$r['grand_total'];
    $totalCost += $cost;
    $totalRetur += $retur['omzet'];
    return [
        'invoice_no' => $r['invoice_no'], 'cust_name' => $r['cust_name'], 'created_at' => $r['created_at'],
        'retur' => $retur['omzet'], 'profit' => $profit, 'margin_pct' => $margin,
    ];
}, $data);

// Retur atas nota yang notanya sendiri di LUAR rentang laporan tidak punya
// baris di $perTx — nilainya tetap harus ikut dikurangkan dari ringkasan,
// kalau tidak omzet bersihnya kelihatan lebih besar dari yang sebenarnya.
$idDitampilkan = array_column($data, 'id');
foreach ($returPerNota as $penjualanId => $r) {
    if (!in_array($penjualanId, $idDitampilkan)) {
        $totalRetur += $r['omzet'];
        $totalCost -= $r['modal'];
    }
}

$netRevenue = $totalRevenue - $totalRetur;
$grossProfit = $netRevenue - $totalCost;
json_ok([
    'rows' => $perTx,
    'total_revenue' => $totalRevenue,   // omzet kotor, sebelum retur
    'total_retur' => $totalRetur,
    'net_revenue' => $netRevenue,
    'total_cost' => $totalCost,
    'gross_profit' => $grossProfit,
    'margin_pct' => $netRevenue ? $grossProfit / $netRevenue * 100 : 0,
    'roi_pct' => $totalCost ? $grossProfit / $totalCost * 100 : 0,
]);
