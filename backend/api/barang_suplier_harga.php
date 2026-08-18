<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_login(); // harga jual bukan data finansial rahasia, Karyawan juga perlu baca ini buat Transaksi Penjualan
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

    // Harga yang sudah pernah diisi Owner (kalau ada) -- termasuk harga
    // faktur/netto REFERENSI per suplier (beda dari barang_lot.harga_beli
    // yang aktual per batch; ini cuma catatan Owner, boleh 0).
    $hargaRows = $pdo->prepare(
        'SELECT suplier_id, harga_faktur, harga_netto, harga_ecer, harga_bengkel, harga_grosir FROM barang_suplier_harga WHERE barang_id = ?'
    );
    $hargaRows->execute([$barangId]);
    $hargaBySuplier = [];
    foreach ($hargaRows->fetchAll() as $r) $hargaBySuplier[(int)$r['suplier_id']] = $r;

    // Harga beli (modal) TERBARU per suplier -- dari batch paling baru yang
    // benar-benar tertaut suplier itu. Cuma dikirim ke Owner (data finansial,
    // sama seperti harga_beli di barang.php?history=1).
    $isOwner = $me['role'] === 'owner';
    $hargaBeliBySuplier = [];
    if ($isOwner) {
        $lotRows = $pdo->prepare(
            'SELECT suplier_id, harga_beli FROM barang_lot WHERE barang_id = ? AND suplier_id IS NOT NULL ORDER BY tanggal DESC, id DESC'
        );
        $lotRows->execute([$barangId]);
        foreach ($lotRows->fetchAll() as $r) {
            $sid = (int)$r['suplier_id'];
            if (!isset($hargaBeliBySuplier[$sid])) $hargaBeliBySuplier[$sid] = (float)$r['harga_beli']; // baris pertama = paling baru (ORDER BY DESC)
        }
    }

    // Faktur/Netto dari FAKTUR PEMBELIAN ASLI paling baru per suplier --
    // dipakai isi awal kalau Owner belum pernah simpan harga referensi
    // sendiri untuk suplier itu di barang_suplier_harga, supaya angka yang
    // sebenarnya sudah diketahui (dari Input Pembelian) tidak perlu diketik
    // ulang. Cuma salah satu (faktur ATAU netto) yang > 0, sama seperti
    // basis yang dipilih saat Input Pembelian dulu -- lihat pembelian.php.
    // Hanya diperlukan Owner: harga faktur/netto adalah data finansial.
    $latestBySuplier = [];
    if ($isOwner) {
        $latestPurchase = $pdo->prepare(
            'SELECT p.suplier_id, pi.harga_faktur, pi.harga_netto
             FROM pembelian_item pi JOIN pembelian p ON p.id = pi.pembelian_id
             WHERE pi.barang_id = ? ORDER BY p.tanggal DESC, p.id DESC, pi.id DESC'
        );
        $latestPurchase->execute([$barangId]);
        foreach ($latestPurchase->fetchAll() as $r) {
            $sid = (int)$r['suplier_id'];
            if (!isset($latestBySuplier[$sid])) $latestBySuplier[$sid] = $r; // baris pertama = paling baru
        }
    }

    $out = array_map(function ($s) use ($stokBySuplier, $hargaBySuplier, $hargaBeliBySuplier, $isOwner, $latestBySuplier) {
        $sid = (int)$s['id'];
        $h = $hargaBySuplier[$sid] ?? null;
        // Karyawan tidak pernah menerima NAMA suplier -- cuma kodenya. Field
        // 'suplier' tetap ada supaya pemakainya di frontend tidak perlu
        // bercabang; isinya kode untuk non-owner.
        $row = [
            'suplier_id' => $sid,
            'suplier' => $isOwner ? $s['nama'] : $s['kode'],
            'suplier_kode' => $s['kode'],
            'stok_sisa' => $stokBySuplier[$sid] ?? 0,
            'harga_ecer' => $h ? (float)$h['harga_ecer'] : null,
            'harga_bengkel' => $h ? (float)$h['harga_bengkel'] : null,
            'harga_grosir' => $h ? (float)$h['harga_grosir'] : null,
        ];
        if (!$isOwner) return $row;
        // Harga beli (faktur/netto referensi + modal batch terakhir) data
        // finansial: dibuang total dari response non-owner, bukan cuma
        // disembunyikan di layar.
        $sudahDisimpan = $h && ((float)$h['harga_faktur'] > 0 || (float)$h['harga_netto'] > 0);
        $lp = $latestBySuplier[$sid] ?? null;
        return $row + [
            'harga_faktur' => $sudahDisimpan ? (float)$h['harga_faktur'] : ($lp ? (float)$lp['harga_faktur'] : null),
            'harga_netto' => $sudahDisimpan ? (float)$h['harga_netto'] : ($lp ? (float)$lp['harga_netto'] : null),
            'harga_beli' => $hargaBeliBySuplier[$sid] ?? null,
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
    $hargaFaktur = (float)($in['harga_faktur'] ?? 0);
    $hargaNetto = (float)($in['harga_netto'] ?? 0);
    $ecer = (float)($in['harga_ecer'] ?? 0);
    $bengkel = (float)($in['harga_bengkel'] ?? 0);
    $grosir = (float)($in['harga_grosir'] ?? 0);
    if (!$barangId || !$suplierId) json_error('barang_id dan suplier_id wajib diisi.');
    if ($ecer <= 0 || $bengkel <= 0 || $grosir <= 0) json_error('Isi semua harga jual dengan angka lebih dari 0.');
    // stok: opsional -- kalau dikirim, ini TARGET jumlah pcs suplier ini
    // (bukan delta). Ubah Barang sekarang cuma bisa mengoreksi Pcs lewat
    // baris suplier di sini, tidak ada lagi jalur global tanpa suplier.
    $adaStok = array_key_exists('stok', $in);
    $stokBaru = $adaStok ? (int)$in['stok'] : null;
    if ($adaStok && $stokBaru < 0) json_error('Stok tidak boleh negatif.');

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO barang_suplier_harga (barang_id, suplier_id, harga_faktur, harga_netto, harga_ecer, harga_bengkel, harga_grosir)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE harga_faktur = VALUES(harga_faktur), harga_netto = VALUES(harga_netto),
                 harga_ecer = VALUES(harga_ecer), harga_bengkel = VALUES(harga_bengkel), harga_grosir = VALUES(harga_grosir)'
        )->execute([$barangId, $suplierId, $hargaFaktur, $hargaNetto, $ecer, $bengkel, $grosir]);

        if ($adaStok) {
            // Kunci baris barang dulu (pola sama seperti barang.php PUT) supaya
            // dua penyimpanan bersamaan tidak sama-sama baca stok lama yang sama.
            $cur = $pdo->prepare('SELECT stok FROM barang WHERE id = ? FOR UPDATE');
            $cur->execute([$barangId]);
            if ($cur->fetchColumn() === false) throw new RuntimeException('Barang tidak ditemukan.');

            $sisaLama = $pdo->prepare('SELECT COALESCE(SUM(qty_sisa), 0) FROM barang_lot WHERE barang_id = ? AND suplier_id = ? FOR UPDATE');
            $sisaLama->execute([$barangId, $suplierId]);
            $stokLama = (int)$sisaLama->fetchColumn();

            $delta = $stokBaru - $stokLama;
            if ($delta !== 0) {
                $pdo->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')->execute([$delta, $barangId]);
                lot_sync_stok_suplier($pdo, $barangId, $suplierId, $stokLama, $stokBaru, $hargaNetto > 0 ? $hargaNetto : $hargaFaktur);
            }
        }

        // Momen ini yang sekarang menandai "harga jual barang sudah diisi" --
        // dipindah dari barang.php PUT (yang sudah tidak lagi mengurus harga).
        $pdo->prepare('UPDATE barang SET pending_setup = 0 WHERE id = ?')->execute([$barangId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
    json_ok([
        'barang_id' => $barangId, 'suplier_id' => $suplierId,
        'harga_faktur' => $hargaFaktur, 'harga_netto' => $hargaNetto,
        'harga_ecer' => $ecer, 'harga_bengkel' => $bengkel, 'harga_grosir' => $grosir,
    ]);
}

json_error('Method not allowed', 405);
