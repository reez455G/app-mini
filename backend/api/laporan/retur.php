<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_owner(); // Laporan Retur (gabungan penjualan+pembelian): Owner only
$pdo = db();
$from = $_GET['from'] ?? '1970-01-01';
$to = $_GET['to'] ?? date('Y-m-d');

// Dulu laporan ini cuma menampilkan JUMLAH barang, tanpa satu pun angka
// rupiah — jadi tidak bisa menjawab "retur bulan ini merugikan berapa".
// nilai per baris memakai fragmen yang sama dengan laporan laba/global,
// supaya angka di sini pasti cocok dengan pengurangan omzet di sana.
$pj = $pdo->prepare(
    'SELECT rp.no_retur, rp.customer_name, rp.original_invoice_no, rp.tanggal, rp.created_at,
            ri.nama_snapshot AS nama, ri.qty, ri.reason,
            COALESCE(rn.nilai_omzet, 0) AS nilai
     FROM retur_penjualan rp
     JOIN retur_penjualan_item ri ON ri.retur_id = rp.id
     LEFT JOIN (' . sql_retur_penjualan_nilai() . ') rn
       ON rn.original_invoice_no = rp.original_invoice_no AND rn.barang_id = ri.barang_id AND rn.tanggal = rp.tanggal
     WHERE rp.tanggal BETWEEN ? AND ? ORDER BY rp.tanggal DESC, rp.created_at DESC'
);
$pj->execute([$from, $to]);
$rowsPj = $pj->fetchAll();

$pb = $pdo->prepare(
    'SELECT rb.no_retur, s.nama AS suplier, rb.original_invoice_no, rb.tanggal, rb.created_at,
            ri.nama_snapshot AS nama, ri.qty, ri.reason,
            ri.qty * COALESCE(bl.harga_beli, 0) AS nilai
     FROM retur_pembelian rb
     JOIN suplier s ON s.id = rb.suplier_id
     JOIN retur_pembelian_item ri ON ri.retur_id = rb.id
     LEFT JOIN barang_lot bl ON bl.id = ri.barang_lot_id
     WHERE rb.tanggal BETWEEN ? AND ? ORDER BY rb.tanggal DESC, rb.created_at DESC'
);
$pb->execute([$from, $to]);
$rowsPb = $pb->fetchAll();

json_ok([
    'retur_penjualan' => $rowsPj,
    'retur_pembelian' => $rowsPb,
    'total_nilai_penjualan' => array_sum(array_map(fn($r) => (float)$r['nilai'], $rowsPj)),
    'total_nilai_pembelian' => array_sum(array_map(fn($r) => (float)$r['nilai'], $rowsPb)),
    'total_qty_penjualan' => array_sum(array_map(fn($r) => (int)$r['qty'], $rowsPj)),
    'total_qty_pembelian' => array_sum(array_map(fn($r) => (int)$r['qty'], $rowsPb)),
]);
