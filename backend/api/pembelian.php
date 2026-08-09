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

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id wajib diisi.', 400);

    $pdo->beginTransaction();
    try {
        $h = $pdo->prepare('SELECT no_faktur FROM pembelian WHERE id = ? FOR UPDATE');
        $h->execute([$id]);
        $noFaktur = $h->fetchColumn();
        if (!$noFaktur) throw new RuntimeException('Pembelian tidak ditemukan.');

        // Kalau sudah pernah diretur, batalkan hapusnya — retur_pembelian
        // masih mengacu ke no_faktur ini (bukan lewat FK, string biasa) dan
        // kalkulasi "sisa yang bisa diretur"-nya bergantung pembelian_item
        // masih ada. Hapus di sini bikin retur itu jadi tidak konsisten.
        $retCount = $pdo->prepare('SELECT COUNT(*) FROM retur_pembelian WHERE original_invoice_no = ?');
        $retCount->execute([$noFaktur]);
        if ((int)$retCount->fetchColumn() > 0) {
            throw new RuntimeException("Pembelian $noFaktur sudah punya retur pembelian terkait, hapus retur-nya dulu.");
        }

        $items = $pdo->prepare('SELECT barang_id, qty, nama_snapshot FROM pembelian_item WHERE pembelian_id = ?');
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Balikkan efek stok pembelian (POST menambah stok, jadi di sini
        // dikurangi) — validasi SEMUA item dulu sebelum menyentuh satu pun,
        // supaya kalau satu barang gagal (mis. sebagian sudah kejual/kepakai
        // di pembelian lain sejak itu) tidak ada perubahan stok yang setengah
        // jalan.
        foreach ($itemRows as $it) {
            $b = $pdo->prepare('SELECT stok FROM barang WHERE id = ? FOR UPDATE');
            $b->execute([$it['barang_id']]);
            $stok = $b->fetchColumn();
            if ($stok === false) continue; // barangnya sendiri sudah dihapus terpisah, tidak ada yang perlu dibalik
            if ((int)$stok < (int)$it['qty']) {
                throw new RuntimeException("Stok {$it['nama_snapshot']} tidak cukup untuk dibalik saat menghapus pembelian ini (tersisa $stok pcs, butuh {$it['qty']} pcs).");
            }
        }
        foreach ($itemRows as $it) {
            $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$it['qty'], $it['barang_id']]);
        }

        // pembelian_item ikut terhapus lewat ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM pembelian WHERE id = ?')->execute([$id]);

        $pdo->commit();
        json_ok(['deleted' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
}

if ($method !== 'POST') json_error('Method not allowed', 405);

$in = body();
$supplierName = trim($in['suplier'] ?? '');
$noFaktur = trim($in['no_faktur'] ?? '');
$items = $in['items'] ?? [];
if ($supplierName === '' || $noFaktur === '' || !$items) json_error('Suplier, No. Faktur, dan minimal 1 barang wajib diisi.');
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
        // Input Pembelian sekarang cuma minta SATU basis harga (Faktur ATAU
        // Netto, bukan dua-duanya) — jadi salah satu boleh 0, tapi keduanya
        // 0 sekaligus tetap ditolak.
        if ($kode === '' || $qty < 1 || ($hargaFaktur <= 0 && $hargaNetto <= 0) || $pricelist <= 0) {
            throw new RuntimeException('Setiap item butuh kode, qty >= 1, salah satu harga faktur/netto, dan pricelist > 0.');
        }
        // Netto lebih diutamakan sebagai basis biaya (harga bersih yang
        // beneran dibayar) — sama seperti fallback IF(harga_netto>0,...) di
        // laporan/laba.php & laporan/global.php.
        $hargaBeli = $hargaNetto > 0 ? $hargaNetto : $hargaFaktur;

        $b = $pdo->prepare('SELECT id, nama, kategori_id FROM barang WHERE kode = ? FOR UPDATE');
        $b->execute([$kode]);
        $barang = $b->fetch();

        if ($barang) {
            // Barang sudah ada: tambah stok, timpa suplier, dan timpa HANYA
            // basis harga yang diisi kali ini — basis yang tidak diisi
            // dipertahankan dari pembelian sebelumnya (bukan ditimpa jadi 0),
            // supaya kalau bulan ini beli pakai Netto lalu bulan depan pakai
            // Faktur, dua-duanya tetap punya nilai terakhir yang wajar.
            $pdo->prepare(
                'UPDATE barang SET stok = stok + ?,
                    harga_faktur = IF(? > 0, ?, harga_faktur),
                    harga_netto = IF(? > 0, ?, harga_netto),
                    suplier_id = ?
                 WHERE id = ?'
            )->execute([$qty, $hargaFaktur, $hargaFaktur, $hargaNetto, $hargaNetto, $suplierId, $barang['id']]);
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

        $subtotal = $qty * $hargaBeli;
        $totalQty += $qty;
        $totalBiaya += $subtotal;
        $rows[] = [$barangId, $kode, $nama, $kategoriNama, $hargaFaktur, $hargaNetto, $pricelist, $qty, $subtotal];
    }

    // no_faktur diketik manual (faktur fisik dari suplier, bukan nomor buatan
    // sistem) — unique per suplier (lihat schema.sql), jadi tangkap duplicate
    // key di sini biar pesannya jelas alih-alih HTTP 500 mentah.
    try {
        $pdo->prepare(
            'INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by)
             VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$noFaktur, $suplierId, $paymentType, $jatuhTempo, count($items), $totalQty, $totalBiaya, $me['id']]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new RuntimeException("No. Faktur \"$noFaktur\" sudah pernah diinput untuk suplier \"$supplierName\".");
        }
        throw $e;
    }
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
