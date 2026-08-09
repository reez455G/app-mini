<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_owner(); // Input Pembelian: Owner only
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $h = $pdo->prepare('SELECT p.*, s.nama AS suplier FROM pembelian p JOIN suplier s ON s.id = p.suplier_id WHERE p.id = ?');
        $h->execute([$id]);
        $header = $h->fetch();
        if (!$header) json_error('Pembelian tidak ditemukan.', 404);
        $it = $pdo->prepare('SELECT kode_snapshot AS kode, nama_snapshot AS nama, kategori_snapshot AS kategori, harga_faktur, harga_netto, pricelist, qty, subtotal FROM pembelian_item WHERE pembelian_id = ?');
        $it->execute([$id]);
        $header['items'] = $it->fetchAll();
        json_ok($header);
    }
    $rows = $pdo->query(
        'SELECT p.id, p.no_faktur, p.tanggal, s.nama AS suplier, p.payment_type, p.jatuh_tempo, p.total_items, p.total_qty, p.total_biaya
         FROM pembelian p JOIN suplier s ON s.id = p.suplier_id ORDER BY p.tanggal DESC, p.id DESC'
    )->fetchAll();
    json_ok($rows);
}

if ($method !== 'POST') json_error('Method not allowed', 405);

$in = body();
$supplierName = trim($in['suplier'] ?? '');
$items = $in['items'] ?? [];
if ($supplierName === '' || !$items) json_error('Suplier dan minimal 1 barang wajib diisi.');
$paymentType = ($in['payment_type'] ?? 'CASH') === 'TOP' ? 'TOP' : 'CASH';
$jatuhTempo = $paymentType === 'TOP' ? ($in['jatuh_tempo'] ?? null) : null;

$pdo->beginTransaction();
try {
    // Suplier baru: sama seperti barang baru di bawah, dibuat otomatis (kode
    // urut, alamat/no_hp default '-') supaya pembelian tetap bisa disimpan
    // tanpa mampir ke Master Data dulu — kelengkapan alamat/no HP bisa
    // dilengkapi belakangan lewat Master Data.
    $sup = $pdo->prepare('SELECT id FROM suplier WHERE nama = ?');
    $sup->execute([$supplierName]);
    $suplierId = $sup->fetchColumn();
    if (!$suplierId) {
        $supKode = next_kode($pdo, 'suplier', 'SUP');
        $pdo->prepare('INSERT INTO suplier (kode, nama, alamat, no_hp) VALUES (?, ?, ?, ?)')
            ->execute([$supKode, $supplierName, '-', '-']);
        $suplierId = (int)$pdo->lastInsertId();
    }

    $totalQty = 0; $totalBiaya = 0; $rows = [];
    foreach ($items as $line) {
        $kode = trim($line['kode'] ?? '');
        $qty = (int)($line['qty'] ?? 0);
        $hargaFaktur = (float)($line['harga_faktur'] ?? 0);
        $hargaNetto = (float)($line['harga_netto'] ?? 0);
        $pricelist = (float)($line['pricelist'] ?? 0);
        if ($kode === '' || $qty < 1 || $hargaFaktur <= 0 || $hargaNetto <= 0 || $pricelist <= 0) {
            throw new RuntimeException('Setiap item butuh kode, qty >= 1, dan harga faktur/netto/pricelist > 0.');
        }

        $b = $pdo->prepare('SELECT id, nama, kategori_id FROM barang WHERE kode = ? FOR UPDATE');
        $b->execute([$kode]);
        $barang = $b->fetch();

        if ($barang) {
            // Barang sudah ada: tambah stok, timpa harga beli terakhir (sama seperti
            // finalizePembelian() di Dashboard.dc.html).
            $pdo->prepare('UPDATE barang SET stok = stok + ?, harga_faktur = ?, harga_netto = ? WHERE id = ?')
                ->execute([$qty, $hargaFaktur, $hargaNetto, $barang['id']]);
            $barangId = $barang['id'];
            $nama = $barang['nama'];
            $kn = $pdo->prepare('SELECT nama FROM kategori WHERE id = ?');
            $kn->execute([$barang['kategori_id']]);
            $kategoriNama = $kn->fetchColumn();
        } else {
            // Kode belum ada: buat barang baru, pending_setup=1 (harga jual belum diisi,
            // owner harus lengkapi lewat Data Barang) — sama seperti perilaku frontend.
            $nama = trim($line['nama'] ?? '');
            $kategoriNama = trim($line['kategori'] ?? '');
            if ($nama === '' || $kategoriNama === '') throw new RuntimeException("Barang baru \"$kode\" butuh nama dan kategori.");
            $kat = $pdo->prepare('SELECT id FROM kategori WHERE nama = ?');
            $kat->execute([$kategoriNama]);
            $katId = $kat->fetchColumn();
            if (!$katId) throw new RuntimeException("Kategori \"$kategoriNama\" tidak ditemukan.");
            $pdo->prepare(
                'INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, stok, min_stok, pending_setup)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 10, 1)'
            )->execute([$kode, $nama, $katId, $suplierId, $hargaFaktur, $hargaNetto, $qty]);
            $barangId = (int)$pdo->lastInsertId();
        }

        $subtotal = $qty * $hargaNetto;
        $totalQty += $qty;
        $totalBiaya += $subtotal;
        $rows[] = [$barangId, $kode, $nama, $kategoriNama, $hargaFaktur, $hargaNetto, $pricelist, $qty, $subtotal];
    }

    $noFaktur = next_doc_no($pdo, 'pembelian', 'no_faktur', 'PB');
    $pdo->prepare(
        'INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by)
         VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$noFaktur, $suplierId, $paymentType, $jatuhTempo, count($items), $totalQty, $totalBiaya, $me['id']]);
    $pembelianId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, pricelist, qty, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) $itemStmt->execute([$pembelianId, ...$r]);

    $pdo->commit();
    json_ok(['id' => $pembelianId, 'no_faktur' => $noFaktur, 'total_qty' => $totalQty, 'total_biaya' => $totalBiaya], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
