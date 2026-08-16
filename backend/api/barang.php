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
    // Riwayat suplier untuk 1 produk, diambil dari riwayat pembelian asli
    // (bukan tabel terpisah yang perlu disinkronkan). Dipakai Data Barang
    // (Owner) DAN Laporan Stok (Owner + Karyawan), jadi harga belinya
    // dibuang untuk Karyawan — sama seperti daftar barang di bawah, dan
    // BUKAN cuma disembunyikan di frontend: kalau cuma disembunyikan,
    // angkanya tetap ada di response dan bisa dilihat siapa pun yang
    // membuka Network tab.
    if (!empty($_GET['id']) && !empty($_GET['history'])) {
        // sisa: qty_sisa batch (lot) yang dibuat baris pembelian ini — berapa
        // dari batch itu yang masih belum terjual/diretur. LEFT JOIN karena
        // data lama sebelum fitur lot ada belum punya baris barang_lot.
        $rows = $pdo->prepare(
            'SELECT p.no_faktur, p.tanggal, s.nama AS suplier, s.kode AS suplier_kode, pi.harga_faktur, pi.harga_netto, pi.qty, bl.qty_sisa AS sisa
             FROM pembelian_item pi
             JOIN pembelian p ON p.id = pi.pembelian_id
             JOIN suplier s ON s.id = p.suplier_id
             LEFT JOIN barang_lot bl ON bl.pembelian_item_id = pi.id
             WHERE pi.barang_id = ? ORDER BY p.tanggal DESC, p.id DESC'
        );
        $rows->execute([(int)$_GET['id']]);
        $history = $rows->fetchAll();
        if ($me['role'] !== 'owner') {
            // Karyawan tidak boleh tahu nama suplier (identitas bisnis private) —
            // dibuang total dari response, bukan cuma disembunyikan di frontend.
            $history = array_map(fn($r) => [
                'no_faktur' => $r['no_faktur'], 'tanggal' => $r['tanggal'],
                'suplier_kode' => $r['suplier_kode'], 'qty' => $r['qty'], 'sisa' => $r['sisa'],
            ], $history);
        }
        json_ok($history);
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
    $sql = 'SELECT b.*, k.nama AS kategori_nama, s.nama AS suplier_nama, s.kode AS suplier_kode,
              (SELECT COUNT(DISTINCT p.suplier_id) FROM pembelian_item pi
               JOIN pembelian p ON p.id = pi.pembelian_id WHERE pi.barang_id = b.id) AS suplier_count
            FROM barang b
            JOIN kategori k ON k.id = b.kategori_id
            LEFT JOIN suplier s ON s.id = b.suplier_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY b.nama';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Ringkasan stok yang MASIH ADA, dikelompokkan per (barang, suplier),
    // lengkap dengan harga jual suplier itu. Satu query untuk semua barang —
    // sengaja dihitung di PHP di bawah alih-alih delapan subquery berkorelasi
    // di query utama. Dipakai tiga hal sekaligus:
    //   - kolom Suplier: sebelumnya b.suplier_id (= suplier pembelian TERAKHIR),
    //     yang bisa menampilkan suplier yang batch-nya sudah habis terjual.
    //   - nilai_jual: stok × harga jual suplier batch itu, bukan stok total ×
    //     harga default barang (yang sejak harga-per-suplier bukan lagi harga
    //     yang benar-benar ditagih ke pelanggan).
    //   - suplier_tanpa_harga: stok yang belum bisa dijual karena harga jual
    //     suplier itu belum diisi — supaya bisa ditandai di layar alih-alih
    //     barangnya diam-diam hilang dari daftar kasir.
    $lotStmt = $pdo->query(
        'SELECT bl.barang_id, bl.suplier_id, s.nama AS suplier, s.kode AS suplier_kode_lot, SUM(bl.qty_sisa) AS sisa,
                h.harga_ecer, h.harga_bengkel, h.harga_grosir
         FROM barang_lot bl
         LEFT JOIN suplier s ON s.id = bl.suplier_id
         LEFT JOIN barang_suplier_harga h ON h.barang_id = bl.barang_id AND h.suplier_id = bl.suplier_id
         WHERE bl.qty_sisa > 0
         GROUP BY bl.barang_id, bl.suplier_id, s.nama, s.kode, h.harga_ecer, h.harga_bengkel, h.harga_grosir
         ORDER BY s.nama'
    );
    $stokPerSuplier = [];
    foreach ($lotStmt->fetchAll() as $r) $stokPerSuplier[(int)$r['barang_id']][] = $r;

    // Karyawan tidak boleh lihat harga beli (data finansial) — tapi nama
    // suplier bukan data finansial (dipakai filter di Laporan Stok, yang
    // memang bisa diakses Karyawan) jadi tetap dikirim ke semua role. Lihat
    // docs/BACKEND.md § Access control.
    $isOwner = $me['role'] === 'owner';
    $out = array_map(function ($r) use ($isOwner, $stokPerSuplier) {
        $lots = $stokPerSuplier[(int)$r['id']] ?? [];
        $namaSuplier = []; $kodeSuplier = []; $tanpaHarga = []; $kodeTanpaHarga = []; $nilaiJual = 0.0; $adaStokTanpaSuplier = false;
        $rentang = ['ecer' => [], 'bengkel' => [], 'grosir' => []];
        foreach ($lots as $l) {
            $sisa = (int)$l['sisa'];
            // Batch tanpa suplier = hasil koreksi stok manual; harga default
            // barang memang jawaban yang benar untuk batch itu.
            $punyaHarga = $l['harga_ecer'] !== null;
            foreach ($rentang as $tier => $_) {
                $rentang[$tier][] = (float)($punyaHarga ? $l['harga_' . $tier] : $r['harga_' . $tier]);
            }
            $nilaiJual += $sisa * (float)($punyaHarga ? $l['harga_ecer'] : $r['harga_ecer']);
            if ($l['suplier'] !== null) {
                $namaSuplier[] = $l['suplier'];
                $kodeSuplier[] = $l['suplier_kode_lot'];
                if (!$punyaHarga) { $tanpaHarga[] = $l['suplier']; $kodeTanpaHarga[] = $l['suplier_kode_lot']; }
            } else {
                $adaStokTanpaSuplier = true;
            }
        }
        // Urutan fallback: suplier batch yang masih ada → "tanpa suplier" kalau
        // sisanya cuma stok hasil koreksi manual → suplier pembelian terakhir
        // kalau stoknya habis sama sekali (biar kolomnya tidak kosong).
        $suplierLabel = $namaSuplier
            ? implode(', ', $namaSuplier)
            : ($adaStokTanpaSuplier ? 'Tanpa Suplier (koreksi manual)' : $r['suplier_nama']);
        // Versi kode, sepadan dengan $suplierLabel di atas — dipakai Karyawan
        // supaya tidak pernah lihat nama suplier (identitas bisnis private).
        $kodeSuplierLabel = $kodeSuplier
            ? implode(', ', $kodeSuplier)
            : ($adaStokTanpaSuplier ? 'Tanpa Suplier (koreksi manual)' : $r['suplier_kode']);
        $base = [
            'id' => (int)$r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'],
            'kategori' => $r['kategori_nama'],
            'suplier' => $suplierLabel,
            'suplier_kode_label' => $kodeSuplierLabel,
            'suplier_kode' => $r['suplier_kode'], 'suplier_count' => (int)$r['suplier_count'],
            'suplier_stok_count' => count($namaSuplier),
            'suplier_tanpa_harga' => $tanpaHarga,
            'suplier_kode_tanpa_harga' => $kodeTanpaHarga,
            'stok' => (int)$r['stok'],
            'harga_ecer' => (float)$r['harga_ecer'], 'harga_bengkel' => (float)$r['harga_bengkel'], 'harga_grosir' => (float)$r['harga_grosir'],
            'nilai_jual' => $nilaiJual,
            'ecer_min' => $rentang['ecer'] ? min($rentang['ecer']) : (float)$r['harga_ecer'],
            'ecer_max' => $rentang['ecer'] ? max($rentang['ecer']) : (float)$r['harga_ecer'],
            'bengkel_min' => $rentang['bengkel'] ? min($rentang['bengkel']) : (float)$r['harga_bengkel'],
            'bengkel_max' => $rentang['bengkel'] ? max($rentang['bengkel']) : (float)$r['harga_bengkel'],
            'grosir_min' => $rentang['grosir'] ? min($rentang['grosir']) : (float)$r['harga_grosir'],
            'grosir_max' => $rentang['grosir'] ? max($rentang['grosir']) : (float)$r['harga_grosir'],
            'status' => stok_status((int)$r['stok'], (int)$r['min_stok']),
        ];
        if (!$isOwner) return $base;
        return $base + [
            'harga_faktur' => (float)$r['harga_faktur'], 'harga_netto' => (float)$r['harga_netto'],
            'price_list_basis' => $r['price_list_basis'], 'min_stok' => (int)$r['min_stok'], 'pending_setup' => (bool)$r['pending_setup'],
        ];
    }, $rows);
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

    $hargaFaktur = (float)($in['harga_faktur'] ?? 0);
    $hargaNetto = (float)($in['harga_netto'] ?? 0);

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $kode, $nama, $katId, $supId, $hargaFaktur, $hargaNetto,
            ($in['price_list_basis'] ?? 'NETTO') === 'FAKTUR' ? 'FAKTUR' : 'NETTO',
            $ecer, $bengkel, $grosir, $stok, $minStok,
        ]);
        $newId = (int)$pdo->lastInsertId();
        // Stok awal harus punya batch juga, kalau tidak barang ini langsung
        // lahir dalam kondisi desinkron dan penjualan pertamanya akan ditolak.
        lot_sync_stok($pdo, $newId, 0, $stok, $hargaNetto > 0 ? $hargaNetto : $hargaFaktur);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        json_error('Kode barang sudah digunakan.', 409);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_error($e->getMessage(), 400);
    }
    json_ok(['id' => $newId], 201);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id wajib diisi.');

if ($method === 'PUT') {
    // Ubah Barang cuma info dasar sekarang -- harga (faktur/netto/ecer/
    // bengkel/grosir) dan stok (Pcs) dipindah jadi per-suplier lewat
    // barang_suplier_harga.php, supaya setiap koreksi stok/harga selalu
    // tertaut ke suplier yang jelas (tidak ada lagi batch "tanpa suplier"
    // baru yang bisa tercipta dari sini). min_stok tetap di sini karena itu
    // ambang peringatan, bukan angka stok/harga yang perlu suplier.
    $in = body();
    $nama = trim($in['nama'] ?? '');
    if ($nama === '') json_error('Nama barang wajib diisi.');
    $minStok = (int)($in['min_stok'] ?? 10);
    if ($minStok < 0) json_error('Isi stok minimum dengan angka valid.');

    $katId = resolve_kategori($pdo, $in['kategori'] ?? '');
    // suplier_id cuma ditimpa kalau field "suplier" memang DIKIRIM: form Ubah
    // Barang sudah tidak punya dropdown suplier lagi (kolom itu ditentukan
    // Input Pembelian), dan tanpa penjagaan ini setiap simpan akan mengosongkan
    // suplier_id jadi NULL karena resolve_suplier(null) mengembalikan null.
    $setSuplier = array_key_exists('suplier', $in) ? 'suplier_id=?, ' : '';
    $params = [$nama, $katId];
    if ($setSuplier) $params[] = resolve_suplier($pdo, $in['suplier'] ?? null);
    $params[] = $minStok;
    $params[] = $id;

    // kode tidak bisa diubah lewat endpoint ini (sama seperti form "Ubah Barang" di frontend).
    $pdo->prepare("UPDATE barang SET nama=?, kategori_id=?, {$setSuplier}min_stok=? WHERE id=?")->execute($params);
    json_ok(['updated' => $id]);
}

if ($method === 'DELETE') {
    // barang_lot dihapus duluan: batch hasil koreksi stok manual punya
    // pembelian_item_id NULL, jadi tidak ikut ON DELETE CASCADE waktu
    // pembeliannya dihapus. Sisa batch itu bikin barang tidak bisa dihapus
    // padahal semua transaksinya sudah dihapus. Batch yang masih tercatat
    // terjual tetap ditahan FK penjualan_item_lot → rollback, pesan "masih
    // dipakai" seperti biasa.
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM barang_lot WHERE barang_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM barang WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            json_error('Barang ini masih dipakai di data transaksi yang tersimpan, jadi tidak bisa dihapus.', 409);
        }
        throw $e;
    }
    json_ok(['deleted' => $id]);
}

json_error('Method not allowed', 405);
