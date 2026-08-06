<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

require_login(); // Laporan Stok: Owner & Karyawan
$pdo = db();

function stok_status(int $stok, int $minStok): string {
    if ($stok <= intdiv($minStok, 2)) return 'Kritis';
    if ($stok <= $minStok) return 'Menipis';
    return 'Aman';
}

$where = []; $params = [];
if (!empty($_GET['kategori']) && $_GET['kategori'] !== 'ALL') { $where[] = 'k.nama = ?'; $params[] = $_GET['kategori']; }
if (!empty($_GET['suplier']) && $_GET['suplier'] !== 'ALL') { $where[] = 's.nama = ?'; $params[] = $_GET['suplier']; }
if (!empty($_GET['search'])) {
    $where[] = '(b.nama LIKE ? OR b.kode LIKE ?)';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

$sql = 'SELECT b.kode, b.nama, k.nama AS kategori, s.nama AS suplier, b.stok, b.min_stok, b.harga_ecer, b.harga_bengkel, b.harga_grosir
        FROM barang b JOIN kategori k ON k.id = b.kategori_id LEFT JOIN suplier s ON s.id = b.suplier_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.nama';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = array_map(function ($r) {
    $r['status'] = stok_status((int)$r['stok'], (int)$r['min_stok']);
    return $r;
}, $stmt->fetchAll());

if (!empty($_GET['status']) && $_GET['status'] !== 'ALL') {
    $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $_GET['status']));
}

json_ok([
    'rows' => $rows,
    'total_stok' => array_sum(array_column($rows, 'stok')),
    'total_nilai_jual' => array_sum(array_map(fn($r) => $r['stok'] * $r['harga_ecer'], $rows)),
]);
