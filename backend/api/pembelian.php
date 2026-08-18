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
        // b.pricelist: Pricelist/HET SAAT INI di Data Barang (bukan snapshot
        // waktu pembelian -- barang bisa saja sudah diedit Pricelist/HET-nya
        // sesudah faktur ini dibuat).
        $it = $pdo->prepare(
            'SELECT pi.id AS pembelian_item_id, pi.barang_id, pi.kode_snapshot AS kode, pi.nama_snapshot AS nama, pi.kategori_snapshot AS kategori,
                    pi.harga_faktur, pi.harga_netto, pi.qty, pi.subtotal, b.pricelist AS pricelist_het
             FROM pembelian_item pi JOIN barang b ON b.id = pi.barang_id WHERE pi.pembelian_id = ?'
        );
        $it->execute([$id]);
        $header['items'] = $it->fetchAll();
        json_ok($header);
    }
    // can_edit: SEMUA batch dari faktur ini masih utuh (belum ada yang
    // terjual/diretur) -- aturan yang sama dengan guard "Hapus" di bawah,
    // dipakai frontend buat munculkan/sembunyikan tombol Ubah.
    $rows = $pdo->query(
        'SELECT p.id, p.no_faktur, p.tanggal, s.nama AS suplier, p.payment_type, p.jatuh_tempo, p.total_items, p.total_qty, p.total_biaya,
                NOT EXISTS (
                    SELECT 1 FROM pembelian_item pi JOIN barang_lot bl ON bl.pembelian_item_id = pi.id
                    WHERE pi.pembelian_id = p.id AND bl.qty_sisa <> bl.qty_awal
                ) AS can_edit
         FROM pembelian p JOIN suplier s ON s.id = p.suplier_id ORDER BY p.tanggal DESC, p.id DESC'
    )->fetchAll();
    json_ok($rows);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id wajib diisi.', 400);

    $pdo->beginTransaction();
    try {
        $h = $pdo->prepare('SELECT no_faktur, suplier_id FROM pembelian WHERE id = ? FOR UPDATE');
        $h->execute([$id]);
        $header = $h->fetch();
        if (!$header) throw new RuntimeException('Pembelian tidak ditemukan.');
        $noFaktur = $header['no_faktur'];

        // Kalau sudah pernah diretur, batalkan hapusnya — retur_pembelian
        // masih mengacu ke no_faktur ini (bukan lewat FK, string biasa) dan
        // kalkulasi "sisa yang bisa diretur"-nya bergantung pembelian_item
        // masih ada. Hapus di sini bikin retur itu jadi tidak konsisten.
        // Dicocokkan dengan suplier_id juga: no_faktur cuma unik per-suplier,
        // jadi tanpa ini retur milik suplier LAIN yang kebetulan pakai nomor
        // faktur sama ikut memblokir penghapusan ini.
        $retCount = $pdo->prepare('SELECT COUNT(*) FROM retur_pembelian WHERE original_invoice_no = ? AND suplier_id = ?');
        $retCount->execute([$noFaktur, $header['suplier_id']]);
        if ((int)$retCount->fetchColumn() > 0) {
            throw new RuntimeException("Pembelian $noFaktur sudah punya retur pembelian terkait, hapus retur-nya dulu.");
        }

        $items = $pdo->prepare('SELECT pi.id AS pembelian_item_id, pi.barang_id, pi.qty, pi.nama_snapshot FROM pembelian_item pi WHERE pi.pembelian_id = ?');
        $items->execute([$id]);
        $itemRows = $items->fetchAll();

        // Guard per-lot (lebih presisi dari cek stok agregat) — validasi
        // SEMUA item dulu sebelum menyentuh satu pun. Setiap baris pembelian
        // ini punya SATU barang_lot; kalau qty_sisa lot itu sudah beda dari
        // qty_awal-nya, berarti batch ini sudah pernah ditarik (terjual lewat
        // FIFO, atau diretur ke suplier) — hapus pembelian-nya jadi tidak
        // konsisten lagi dengan riwayat itu, jadi ditolak.
        foreach ($itemRows as $it) {
            $lot = $pdo->prepare('SELECT qty_awal, qty_sisa FROM barang_lot WHERE pembelian_item_id = ? FOR UPDATE');
            $lot->execute([$it['pembelian_item_id']]);
            $lotRow = $lot->fetch();
            if ($lotRow && (int)$lotRow['qty_sisa'] !== (int)$lotRow['qty_awal']) {
                throw new RuntimeException("Batch {$it['nama_snapshot']} dari pembelian ini sudah ada yang terjual/diretur, tidak bisa dihapus.");
            }
        }
        foreach ($itemRows as $it) {
            $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$it['qty'], $it['barang_id']]);
        }

        // pembelian_item DAN barang_lot-nya ikut terhapus lewat ON DELETE CASCADE.
        $pdo->prepare('DELETE FROM pembelian WHERE id = ?')->execute([$id]);

        $pdo->commit();
        json_ok(['deleted' => $id]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
}

if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('id wajib diisi.', 400);
    $in = body();
    $noFaktur = trim($in['no_faktur'] ?? '');
    $itemsIn = $in['items'] ?? [];
    if ($noFaktur === '' || !$itemsIn) json_error('No. Faktur dan minimal 1 barang wajib diisi.');
    $paymentType = ($in['payment_type'] ?? 'CASH') === 'TOP' ? 'TOP' : 'CASH';
    $tanggal = ($in['tanggal'] ?? '') ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) json_error('Tanggal faktur tidak valid.');
    if ($tanggal > date('Y-m-d')) json_error('Tanggal faktur tidak boleh di masa depan.');
    $jatuhTempo = $paymentType === 'TOP' ? ($in['jatuh_tempo'] ?? null) : $tanggal;
    if ($paymentType === 'TOP') {
        if (!$jatuhTempo || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jatuhTempo)) {
            json_error('Tgl Jatuh Tempo wajib diisi untuk pembayaran TOP.');
        }
    }

    $pdo->beginTransaction();
    try {
        $h = $pdo->prepare('SELECT suplier_id FROM pembelian WHERE id = ? FOR UPDATE');
        $h->execute([$id]);
        $header = $h->fetch();
        if (!$header) throw new RuntimeException('Pembelian tidak ditemukan.');
        $suplierId = (int)$header['suplier_id'];

        // Suplier & jumlah baris barang TIDAK bisa diubah lewat Ubah Pembelian
        // -- ganti suplier/barang berarti transaksi berbeda, bukan koreksi.
        // Kalau itu yang salah, tetap harus hapus lalu input ulang.
        $origItems = $pdo->prepare(
            'SELECT pi.id AS pembelian_item_id, pi.barang_id, pi.qty AS qty_lama, pi.nama_snapshot FROM pembelian_item pi WHERE pi.pembelian_id = ?'
        );
        $origItems->execute([$id]);
        $origRows = $origItems->fetchAll();
        if (count($itemsIn) !== count($origRows)) {
            throw new RuntimeException('Jumlah baris barang tidak bisa diubah lewat Ubah Pembelian -- hapus dan input ulang kalau perlu tambah/kurangi barang.');
        }

        // Guard per-lot (sama persis dengan guard "Hapus" di atas): faktur
        // ini cuma boleh diubah selama BELUM ada satu pcs pun yang tertarik
        // (terjual/diretur) dari batch-batchnya -- kalau sudah, koreksi bisa
        // bikin laporan laba yang sudah terlanjur dihitung jadi tidak
        // konsisten dengan modal barunya.
        $lotByItemId = [];
        foreach ($origRows as $it) {
            $lot = $pdo->prepare('SELECT id, qty_awal, qty_sisa FROM barang_lot WHERE pembelian_item_id = ? FOR UPDATE');
            $lot->execute([$it['pembelian_item_id']]);
            $lotRow = $lot->fetch();
            if ($lotRow && (int)$lotRow['qty_sisa'] !== (int)$lotRow['qty_awal']) {
                throw new RuntimeException("Batch {$it['nama_snapshot']} dari pembelian ini sudah ada yang terjual/diretur, tidak bisa diubah.");
            }
            $lotByItemId[(int)$it['pembelian_item_id']] = $lotRow;
        }

        // Cocokkan tiap baris kiriman ke pembelian_item aslinya lewat id --
        // bukan posisi array, supaya urutan dari frontend tidak berpengaruh.
        $itemsInById = [];
        foreach ($itemsIn as $line) {
            $pid = (int)($line['pembelian_item_id'] ?? 0);
            if (!$pid) throw new RuntimeException('pembelian_item_id wajib diisi tiap baris.');
            $itemsInById[$pid] = $line;
        }

        $totalQty = 0; $totalBiaya = 0;
        foreach ($origRows as $orig) {
            $pid = (int)$orig['pembelian_item_id'];
            $line = $itemsInById[$pid] ?? null;
            if (!$line) throw new RuntimeException('Baris barang tidak cocok dengan faktur ini -- hapus dan input ulang kalau perlu tambah/kurangi barang.');

            $qty = (int)($line['qty'] ?? 0);
            $hargaFaktur = (float)($line['harga_faktur'] ?? 0);
            $hargaNetto = (float)($line['harga_netto'] ?? 0);
            $pricelist = (float)($line['pricelist'] ?? 0);
            if ($qty < 1 || ($hargaFaktur <= 0 && $hargaNetto <= 0)) {
                throw new RuntimeException("Baris \"{$orig['nama_snapshot']}\" butuh qty >= 1 dan salah satu harga faktur/netto.");
            }
            $hargaBeli = $hargaNetto > 0 ? $hargaNetto : $hargaFaktur;
            $subtotal = $qty * $hargaBeli;
            $totalQty += $qty; $totalBiaya += $subtotal;

            $pdo->prepare('UPDATE pembelian_item SET harga_faktur = ?, harga_netto = ?, pricelist = ?, qty = ?, subtotal = ? WHERE id = ?')
                ->execute([$hargaFaktur, $hargaNetto, $pricelist, $qty, $subtotal, $pid]);

            $lotRow = $lotByItemId[$pid];
            if ($lotRow) {
                $pdo->prepare('UPDATE barang_lot SET qty_awal = ?, qty_sisa = ?, harga_beli = ?, tanggal = ? WHERE id = ?')
                    ->execute([$qty, $qty, $hargaBeli, $tanggal, $lotRow['id']]);
            }
            $deltaQty = $qty - (int)$orig['qty_lama'];
            if ($deltaQty !== 0) {
                $pdo->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')->execute([$deltaQty, $orig['barang_id']]);
            }
            // Snapshot harga_faktur/harga_netto di level barang ikut disamakan
            // pola merge yang sama seperti pembelian baru (POST di bawah) --
            // supaya "Harga default barang" di Ubah Barang tidak nyangkut di
            // basis lama yang baru saja dikoreksi di sini.
            $pdo->prepare(
                'UPDATE barang SET harga_faktur = IF(? > 0, ?, harga_faktur), harga_netto = IF(? > 0, ?, harga_netto),
                    pricelist = IF(? > 0, ?, pricelist) WHERE id = ?'
            )->execute([$hargaFaktur, $hargaFaktur, $hargaNetto, $hargaNetto, $pricelist, $pricelist, $orig['barang_id']]);
        }

        try {
            $pdo->prepare(
                'UPDATE pembelian SET no_faktur = ?, tanggal = ?, payment_type = ?, jatuh_tempo = ?, total_qty = ?, total_biaya = ? WHERE id = ?'
            )->execute([$noFaktur, $tanggal, $paymentType, $jatuhTempo, $totalQty, $totalBiaya, $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new RuntimeException("No. Faktur \"$noFaktur\" sudah dipakai faktur lain untuk suplier ini.");
            }
            throw $e;
        }

        $pdo->commit();
        json_ok(['updated' => $id]);
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
// Tanggal faktur fisik dari suplier, bukan tanggal input — faktur tanggal 10
// yang baru sempat diinput tanggal 15 harus tetap tercatat tanggal 10, kalau
// tidak Laporan Pembelian tidak bisa dicocokkan dengan faktur fisik. Dipakai
// juga sebagai urutan FIFO barang_lot, jadi satu nilai ini untuk keduanya.
// ?: bukan ?? — kolom tanggal yang dikosongkan mengirim string kosong, dan
// ?? cuma menangkap null (pola sama dengan retur_*.php).
$tanggal = ($in['tanggal'] ?? '') ?: date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) json_error('Tanggal faktur tidak valid.');
if ($tanggal > date('Y-m-d')) json_error('Tanggal faktur tidak boleh di masa depan.');

// Cash = lunas saat itu juga, jadi "jatuh tempo"-nya ya tanggal faktur itu
// sendiri (bukan NULL) -- riwayat/laporan tetap punya tanggal acuan yang
// masuk akal, dan konsisten dengan field ini di-disable untuk Cash di UI.
$jatuhTempo = $paymentType === 'TOP' ? ($in['jatuh_tempo'] ?? null) : $tanggal;
// TOP tanpa tanggal jatuh tempo = utang tanpa tenggat: ditolak, bukan
// disimpan NULL diam-diam.
if ($paymentType === 'TOP') {
    if (!$jatuhTempo || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jatuhTempo)) {
        json_error('Tgl Jatuh Tempo wajib diisi untuk pembayaran TOP.');
    }
}

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
        // Pricelist/HET: SATU angka yang sama dengan barang.pricelist (bukan
        // catatan terpisah per baris pembelian seperti dulu) -- diisi di sini
        // supaya barang baru langsung punya patokan harga sejak awal, dan
        // ikut disimpan ke pembelian_item.pricelist sebagai snapshot histori.
        // Boleh 0/kosong: artinya "tidak diubah" untuk barang yang sudah ada.
        $pricelist = (float)($line['pricelist'] ?? 0);
        // Input Pembelian sekarang cuma minta SATU basis harga (Faktur ATAU
        // Netto, bukan dua-duanya) — jadi salah satu boleh 0, tapi keduanya
        // 0 sekaligus tetap ditolak.
        if ($kode === '' || $qty < 1 || ($hargaFaktur <= 0 && $hargaNetto <= 0)) {
            throw new RuntimeException('Setiap item butuh kode, qty >= 1, dan salah satu harga faktur/netto.');
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
            // pricelist ikut pola yang sama: cuma ditimpa kalau kali ini diisi,
            // jadi mengosongkannya tidak menghapus Pricelist/HET yang sudah ada.
            $pdo->prepare(
                'UPDATE barang SET stok = stok + ?,
                    harga_faktur = IF(? > 0, ?, harga_faktur),
                    harga_netto = IF(? > 0, ?, harga_netto),
                    pricelist = IF(? > 0, ?, pricelist),
                    suplier_id = ?
                 WHERE id = ?'
            )->execute([$qty, $hargaFaktur, $hargaFaktur, $hargaNetto, $hargaNetto, $pricelist, $pricelist, $suplierId, $barang['id']]);
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
                'INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, pricelist, stok, min_stok, pending_setup)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 10, 1)'
            )->execute([$kode, $nama, $katId, $suplierId, $hargaFaktur, $hargaNetto, $pricelist, $qty]);
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
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$noFaktur, $tanggal, $suplierId, $paymentType, $jatuhTempo, count($items), $totalQty, $totalBiaya, $me['id']]);
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
    // Satu barang_lot per baris pembelian_item — batch ini yang nanti ditarik
    // FIFO oleh penjualan.php dan dipakai Laporan Laba buat hitung modal
    // aktual (bukan cuma harga_faktur/harga_netto TERKINI di barang).
    $lotStmt = $pdo->prepare(
        'INSERT INTO barang_lot (barang_id, pembelian_item_id, suplier_id, harga_beli, qty_awal, qty_sisa, tanggal)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) {
        $itemStmt->execute([$pembelianId, ...$r]);
        $pembelianItemId = (int)$pdo->lastInsertId();
        $barangId = $r[0]; $hargaFakturRow = $r[4]; $hargaNettoRow = $r[5]; $qtyRow = $r[7];
        $hargaBeliLot = $hargaNettoRow > 0 ? $hargaNettoRow : $hargaFakturRow;
        $lotStmt->execute([$barangId, $pembelianItemId, $suplierId, $hargaBeliLot, $qtyRow, $qtyRow, $tanggal]);
    }

    $pdo->commit();
    json_ok(['id' => $pembelianId, 'no_faktur' => $noFaktur, 'total_qty' => $totalQty, 'total_biaya' => $totalBiaya], 201);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 400);
}
