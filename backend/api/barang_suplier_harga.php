<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login(); // harga jual bukan data finansial rahasia, Karyawan juga perlu baca ini buat Transaksi Penjualan
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    $barangId = (int)($_GET['barang_id'] ?? 0);
    if (!$barangId) json_error('barang_id wajib diisi.');

    // Suplier yang PERNAH beli barang ini, dari riwayat pembelian asli --
    // pola sama dengan query suplier_count di barang.php.
    $suppliers = $pdo->prepare(
        'SELECT DISTINCT s.id, s.nama, s.kode
         FROM pembelian_item pi
         JOIN pembelian p ON p.id = pi.pembelian_id
         JOIN suplier s ON s.id = p.suplier_id
         WHERE pi.barang_id = ?
         ORDER BY s.nama'
    );
    $suppliers->execute([$barangId]);
    $suppliers = $suppliers->fetchAll();

    // Sisa stok per suplier (dari batch yang benar-benar tertaut suplier itu).
    $stokRows = $pdo->prepare(
        'SELECT suplier_id, SUM(qty_sisa) AS sisa FROM barang_lot
         WHERE barang_id = ? AND suplier_id IS NOT NULL GROUP BY suplier_id'
    );
    $stokRows->execute([$barangId]);
    $stokBySuplier = [];
    foreach ($stokRows->fetchAll() as $r) $stokBySuplier[(int)$r['suplier_id']] = (int)$r['sisa'];

    // Harga yang sudah pernah diisi Owner (kalau ada).
    $hargaRows = $pdo->prepare(
        'SELECT suplier_id, harga_ecer, harga_bengkel, harga_grosir FROM barang_suplier_harga WHERE barang_id = ?'
    );
    $hargaRows->execute([$barangId]);
    $hargaBySuplier = [];
    foreach ($hargaRows->fetchAll() as $r) $hargaBySuplier[(int)$r['suplier_id']] = $r;

    $out = array_map(function ($s) use ($stokBySuplier, $hargaBySuplier) {
        $h = $hargaBySuplier[(int)$s['id']] ?? null;
        return [
            'suplier_id' => (int)$s['id'], 'suplier' => $s['nama'], 'suplier_kode' => $s['kode'],
            'stok_sisa' => $stokBySuplier[(int)$s['id']] ?? 0,
            'harga_ecer' => $h ? (float)$h['harga_ecer'] : null,
            'harga_bengkel' => $h ? (float)$h['harga_bengkel'] : null,
            'harga_grosir' => $h ? (float)$h['harga_grosir'] : null,
        ];
    }, $suppliers);

    // Stok hasil koreksi manual (tidak tertaut suplier mana pun) -- dipakai
    // munculkan opsi "Tanpa Suplier" di Transaksi Penjualan kalau > 0.
    $tanpaSuplier = $pdo->prepare('SELECT COALESCE(SUM(qty_sisa), 0) FROM barang_lot WHERE barang_id = ? AND suplier_id IS NULL');
    $tanpaSuplier->execute([$barangId]);

    json_ok(['suppliers' => $out, 'stok_tanpa_suplier' => (int)$tanpaSuplier->fetchColumn()]);
}

require_owner(); // isi harga jual per suplier khusus Owner (sama seperti semua harga jual lain)

if ($method === 'POST') {
    $in = body();
    $barangId = (int)($in['barang_id'] ?? 0);
    $suplierId = (int)($in['suplier_id'] ?? 0);
    $ecer = (float)($in['harga_ecer'] ?? 0);
    $bengkel = (float)($in['harga_bengkel'] ?? 0);
    $grosir = (float)($in['harga_grosir'] ?? 0);
    if (!$barangId || !$suplierId) json_error('barang_id dan suplier_id wajib diisi.');
    if ($ecer <= 0 || $bengkel <= 0 || $grosir <= 0) json_error('Isi semua harga jual dengan angka lebih dari 0.');

    $pdo->prepare(
        'INSERT INTO barang_suplier_harga (barang_id, suplier_id, harga_ecer, harga_bengkel, harga_grosir)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE harga_ecer = VALUES(harga_ecer), harga_bengkel = VALUES(harga_bengkel), harga_grosir = VALUES(harga_grosir)'
    )->execute([$barangId, $suplierId, $ecer, $bengkel, $grosir]);
    json_ok(['barang_id' => $barangId, 'suplier_id' => $suplierId, 'harga_ecer' => $ecer, 'harga_bengkel' => $bengkel, 'harga_grosir' => $grosir]);
}

json_error('Method not allowed', 405);
