<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Dashboard "Tren Penjualan" chart: Owner only
$pdo = db();
$days = min(90, max(1, (int)($_GET['days'] ?? 14)));

$stmt = $pdo->prepare(
    'SELECT tanggal, COALESCE(SUM(grand_total), 0) AS total
     FROM penjualan WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY tanggal'
);
$stmt->execute([$days - 1]);
$byDate = [];
foreach ($stmt->fetchAll() as $r) $byDate[$r['tanggal']] = (float)$r['total'];

// Zero-fill setiap hari (termasuk yang tidak ada penjualan sama sekali), supaya
// chart selalu punya $days titik berurutan — bukan cuma hari yang ada transaksi.
$rows = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $rows[] = ['tanggal' => $d, 'total' => $byDate[$d] ?? 0.0];
}

json_ok($rows);
