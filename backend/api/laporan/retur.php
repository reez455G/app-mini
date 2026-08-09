<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Retur (gabungan penjualan+pembelian): Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

$pj = $pdo->prepare(
    'SELECT rp.no_retur, rp.customer_name, rp.original_invoice_no, rp.tanggal, rp.created_at, ri.nama_snapshot AS nama, ri.qty, ri.reason
     FROM retur_penjualan rp JOIN retur_penjualan_item ri ON ri.retur_id = rp.id
     WHERE rp.tanggal BETWEEN ? AND ? ORDER BY rp.tanggal DESC, rp.created_at DESC'
);
$pj->execute([$from, $to]);

$pb = $pdo->prepare(
    'SELECT rb.no_retur, s.nama AS suplier, rb.original_invoice_no, rb.tanggal, rb.created_at, ri.nama_snapshot AS nama, ri.qty, ri.reason
     FROM retur_pembelian rb JOIN suplier s ON s.id = rb.suplier_id JOIN retur_pembelian_item ri ON ri.retur_id = rb.id
     WHERE rb.tanggal BETWEEN ? AND ? ORDER BY rb.tanggal DESC, rb.created_at DESC'
);
$pb->execute([$from, $to]);

json_ok(['retur_penjualan' => $pj->fetchAll(), 'retur_pembelian' => $pb->fetchAll()]);
