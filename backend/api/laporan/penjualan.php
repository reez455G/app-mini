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

// Rincian barang per invoice -- dipakai export Laporan Penjualan (1 baris per
// barang, bukan cuma 1 baris ringkasan per invoice), pola sama dengan
// laporan/pembelian.php. Aman untuk Karyawan juga: isinya harga JUAL, bukan
// harga beli/suplier yang memang disembunyikan dari mereka.
$itemStmt = $pdo->prepare(
    'SELECT pi.penjualan_id, b.kode, pi.nama_snapshot AS nama, pi.tier, pi.qty, pi.unit_price, pi.subtotal
     FROM penjualan_item pi
     JOIN penjualan p ON p.id = pi.penjualan_id
     JOIN barang b ON b.id = pi.barang_id
     WHERE p.tanggal BETWEEN ? AND ? ORDER BY pi.id'
);
$itemStmt->execute([$from, $to]);
$itemsByPenjualan = [];
foreach ($itemStmt->fetchAll() as $it) {
    $itemsByPenjualan[(int)$it['penjualan_id']][] = [
        'kode' => $it['kode'], 'nama' => $it['nama'], 'tier' => $it['tier'],
        'qty' => (int)$it['qty'], 'unit_price' => (float)$it['unit_price'], 'subtotal' => (float)$it['subtotal'],
    ];
}
$data = array_map(function ($r) use ($itemsByPenjualan) {
    $r['items'] = $itemsByPenjualan[(int)$r['id']] ?? [];
    return $r;
}, $data);

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
