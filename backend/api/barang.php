<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_login();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

function stok_status(int $stok, int $minStok): string {
    if ($stok <= intdiv($minStok, 2)) return 'Kritis';
    if ($stok <= $minStok) return 'Menipis';
    return 'Aman';
}

if ($method === 'GET') {
    // Riwayat harga per suplier untuk 1 produk (perbandingan biaya) — Owner only,
    // diambil dari riwayat pembelian asli, bukan tabel terpisah yang perlu disinkronkan.
    if (!empty($_GET['id']) && !empty($_GET['history'])) {
        require_owner();
        // sisa: qty_sisa batch (lot) yang dibuat baris pembelian ini — berapa
        // dari batch itu yang masih belum terjual/diretur. LEFT JOIN karena
        // data lama sebelum fitur lot ada belum punya baris barang_lot.
        $rows = $pdo->prepare(
            'SELECT p.no_faktur, p.tanggal, s.nama AS suplier, pi.harga_faktur, pi.harga_netto, pi.qty, bl.qty_sisa AS sisa
             FROM pembelian_item pi
             JOIN pembelian p ON p.id = pi.pembelian_id
             JOIN suplier s ON s.id = p.suplier_id
             LEFT JOIN barang_lot bl ON bl.pembelian_item_id = pi.id
             WHERE pi.barang_id = ? ORDER BY p.tanggal DESC'
        );
        $rows->execute([(int)$_GET['id']]);
        json_ok($rows->fetchAll());
    }

    $where = []; $params = [];
    if (!empty($_GET['kategori']) && $_GET['kategori'] !== 'ALL') { $where[] = 'k.nama = ?'; $params[] = $_GET['kategori']; }
    if (!empty($_GET['search'])) {
        $where[] = '(b.nama LIKE ? OR b.kode LIKE ?)';
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
    }
    // suplier_count: berapa suplier BERBEDA yang pernah dipakai buat beli
    // barang ini (dari riwayat pembelian asli, sama seperti endpoint
    // ?history=1 di atas) — b.suplier_id sendiri cuma nyimpen suplier
    // TERAKHIR, jadi tabel Data Barang/Laporan Stok butuh angka ini buat
    // tahu kapan perlu nampilin badge "+N lainnya".
    $sql = 'SELECT b.*, k.nama AS kategori_nama, s.nama AS suplier_nama,
              (SELECT COUNT(DISTINCT p.suplier_id) FROM pembelian_item pi
               JOIN pembelian p ON p.id = pi.pembelian_id WHERE pi.barang_id = b.id) AS suplier_count
            FROM barang b
            JOIN kategori k ON k.id = b.kategori_id
            LEFT JOIN suplier s ON s.id = b.suplier_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY b.nama';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Karyawan tidak boleh lihat harga beli (data finansial) — tapi nama
    // suplier bukan data finansial (dipakai filter di Laporan Stok, yang
    // memang bisa diakses Karyawan) jadi tetap dikirim ke semua role. Lihat
    // docs/BACKEND.md § Access control.
    $isOwner = $me['role'] === 'owner';
    $out = array_map(function ($r) use ($isOwner) {
        $base = [
            'id' => (int)$r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'],
            'kategori' => $r['kategori_nama'], 'suplier' => $r['suplier_nama'], 'suplier_count' => (int)$r['suplier_count'], 'stok' => (int)$r['stok'],
            'harga_ecer' => (float)$r['harga_ecer'], 'harga_bengkel' => (float)$r['harga_bengkel'], 'harga_grosir' => (float)$r['harga_grosir'],
            'status' => stok_status((int)$r['stok'], (int)$r['min_stok']),
        ];
        if (!$isOwner) return $base;
        return $base + [
            'harga_faktur' => (float)$r['harga_faktur'], 'harga_netto' => (float)$r['harga_netto'],
            'price_list_basis' => $r['price_list_basis'], 'min_stok' => (int)$r['min_stok'], 'pending_setup' => (bool)$r['pending_setup'],
        ];
    }, $stmt->fetchAll());
    json_ok($out);
}

require_owner(); // menambah/ubah/hapus barang khusus Owner (lihat "Data Barang" di access matrix)

function resolve_kategori(PDO $pdo, string $nama): int {
    $s = $pdo->prepare('SELECT id FROM kategori WHERE nama = ?');
    $s->execute([$nama]);
    $id = $s->fetchColumn();
    if (!$id) json_error('Kategori tidak ditemukan.');
    return (int)$id;
}

function resolve_suplier(PDO $pdo, ?string $nama): ?int {
    if (!$nama) return null;
    $s = $pdo->prepare('SELECT id FROM suplier WHERE nama = ?');
    $s->execute([$nama]);
    return $s->fetchColumn() ?: null;
}

if ($method === 'POST') {
    $in = body();
    $kode = trim($in['kode'] ?? '');
    $nama = trim($in['nama'] ?? '');
    if ($kode === '' || $nama === '') json_error('Kode dan nama barang wajib diisi.');
    $ecer = (float)($in['harga_ecer'] ?? 0);
    $bengkel = (float)($in['harga_bengkel'] ?? 0);
    $grosir = (float)($in['harga_grosir'] ?? 0);
    if ($ecer <= 0 || $bengkel <= 0 || $grosir <= 0) json_error('Isi semua harga jual dengan angka lebih dari 0.');
    $stok = (int)($in['stok'] ?? 0);
    $minStok = (int)($in['min_stok'] ?? 10);
    if ($stok < 0 || $minStok < 0) json_error('Isi stok dan stok minimum dengan angka valid.');

    $katId = resolve_kategori($pdo, $in['kategori'] ?? '');
    $supId = resolve_suplier($pdo, $in['suplier'] ?? null);

    try {
        $pdo->prepare(
            'INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $kode, $nama, $katId, $supId,
            (float)($in['harga_faktur'] ?? 0), (float)($in['harga_netto'] ?? 0),
            ($in['price_list_basis'] ?? 'NETTO') === 'FAKTUR' ? 'FAKTUR' : 'NETTO',
            $ecer, $bengkel, $grosir, $stok, $minStok,
        ]);
    } catch (PDOException $e) {
        json_error('Kode barang sudah digunakan.', 409);
    }
    json_ok(['id' => (int)$pdo->lastInsertId()], 201);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id wajib diisi.');

if ($method === 'PUT') {
    $in = body();
    $nama = trim($in['nama'] ?? '');
    if ($nama === '') json_error('Nama barang wajib diisi.');
    $ecer = (float)($in['harga_ecer'] ?? 0);
    $bengkel = (float)($in['harga_bengkel'] ?? 0);
    $grosir = (float)($in['harga_grosir'] ?? 0);
    if ($ecer <= 0 || $bengkel <= 0 || $grosir <= 0) json_error('Isi semua harga jual dengan angka lebih dari 0.');
    $stok = (int)($in['stok'] ?? 0);
    $minStok = (int)($in['min_stok'] ?? 10);
    if ($stok < 0 || $minStok < 0) json_error('Isi stok dan stok minimum dengan angka valid.');

    $katId = resolve_kategori($pdo, $in['kategori'] ?? '');
    $supId = resolve_suplier($pdo, $in['suplier'] ?? null);

    // kode tidak bisa diubah lewat endpoint ini (sama seperti form "Ubah Barang" di frontend)
    $pdo->prepare(
        'UPDATE barang SET nama=?, kategori_id=?, suplier_id=?, harga_faktur=?, harga_netto=?, price_list_basis=?, harga_ecer=?, harga_bengkel=?, harga_grosir=?, stok=?, min_stok=?, pending_setup=0
         WHERE id=?'
    )->execute([
        $nama, $katId, $supId, (float)($in['harga_faktur'] ?? 0), (float)($in['harga_netto'] ?? 0),
        ($in['price_list_basis'] ?? 'NETTO') === 'FAKTUR' ? 'FAKTUR' : 'NETTO',
        $ecer, $bengkel, $grosir, $stok, $minStok, $id,
    ]);
    json_ok(['updated' => $id]);
}

if ($method === 'DELETE') {
    delete_row($pdo, 'barang', $id, 'Barang');
    json_ok(['deleted' => $id]);
}

json_error('Method not allowed', 405);
