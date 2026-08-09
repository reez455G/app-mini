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

// Modal diambil dari harga beli batch yang benar-benar terjual
// (penjualan_item_lot), sama seperti laporan/laba.php — bukan harga barang
// TERKINI yang bisa sudah berubah sejak transaksi lama.
// Sengaja SUBQUERY BERKORELASI, bukan LEFT JOIN: query ini juga menjumlahkan
// pi.subtotal, sedangkan satu penjualan_item bisa pecah ke >1 batch — join
// akan menggandakan barisnya dan ikut menggandakan revenue-nya.
$stmt = $pdo->prepare(
    "SELECT b.kode, b.nama, SUM(pi.qty) AS qty, SUM(pi.subtotal) AS revenue,
            SUM(pi.subtotal) - COALESCE(SUM(
                (SELECT SUM(pil.qty * pil.harga_beli) FROM penjualan_item_lot pil
                 WHERE pil.penjualan_item_id = pi.id)
            ), 0) AS profit
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
