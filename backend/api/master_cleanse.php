<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

// Cleansing Data (Master Data) -- satu-satunya endpoint yang bisa menghapus
// data secara MASSAL di aplikasi ini. Semua aksi Owner-only. Lihat komentar
// panjang di masing-masing fungsi untuk alasan urutan hapusnya -- intinya:
// barang_lot.pembelian_item_id ON DELETE CASCADE bisa diam-diam menghapus
// batch stok yang qty_sisa-nya masih > 0 kalau pembelian_item dihapus
// langsung, jadi SELALU dilepas (SET NULL) dulu sebelum pembelian_item-nya
// dihapus -- supaya barang.stok tidak pernah kehilangan batch pendukungnya.

$me = require_owner();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// Tabel yang tercakup fitur ini (export & import), urutan FK-safe untuk INSERT.
const CLEANSE_TABLES_ORDER = [
    'kategori', 'suplier', 'pelanggan',
    'barang', 'barang_lot', 'barang_suplier_harga',
    'pembelian', 'pembelian_item',
    'penjualan', 'penjualan_item', 'penjualan_item_lot',
    'retur_penjualan', 'retur_penjualan_item',
    'retur_pembelian', 'retur_pembelian_item',
];
// Tabel yang benar-benar bisa DIHAPUS lewat cleansing (kategori cuma ikut
// terbawa export sebagai referensi, users & toko_profil tidak pernah
// disentuh sama sekali oleh file ini).
const CLEANSE_DELETABLE_TABLES = [
    'penjualan', 'pembelian', 'retur_penjualan', 'retur_pembelian', 'barang', 'suplier', 'pelanggan',
];

// Kumpulan filter dari querystring (preview, GET) atau body (cleanse, POST) --
// dipakai bareng oleh find_affected_ids() supaya preview & cleanse PASTI
// menghitung baris yang sama persis.
function cleanse_filters(array $in): array {
    return [
        'dari' => trim((string)($in['dari'] ?? '')) ?: null,
        'sampai' => trim((string)($in['sampai'] ?? '')) ?: null,
        'modules' => array_values(array_intersect((array)($in['modules'] ?? []), CLEANSE_DELETABLE_TABLES)),
        'suplier_id' => !empty($in['suplier_id']) ? (int)$in['suplier_id'] : null,
        'pelanggan_id' => !empty($in['pelanggan_id']) ? (int)$in['pelanggan_id'] : null,
    ];
}

// ID baris yang kena filter, per kategori -- dipakai preview (cuma dihitung)
// dan cleanse (dihapus beneran). Satu fungsi untuk keduanya supaya angkanya
// tidak pernah beda antara yang ditampilkan Owner dan yang benar-benar terjadi.
function cleanse_find_ids(PDO $pdo, string $table, array $f): array {
    $where = []; $params = [];
    $dateCol = null;
    if ($table === 'penjualan') $dateCol = 'tanggal';
    if ($table === 'pembelian') $dateCol = 'tanggal';
    if ($table === 'retur_penjualan') $dateCol = 'tanggal';
    if ($table === 'retur_pembelian') $dateCol = 'tanggal';
    if ($table === 'barang') $dateCol = 'DATE(created_at)';
    if ($dateCol) {
        if ($f['dari']) { $where[] = "$dateCol >= ?"; $params[] = $f['dari']; }
        if ($f['sampai']) { $where[] = "$dateCol <= ?"; $params[] = $f['sampai']; }
    }
    if ($table === 'penjualan' && $f['pelanggan_id']) { $where[] = 'pelanggan_id = ?'; $params[] = $f['pelanggan_id']; }
    if ($table === 'pembelian' && $f['suplier_id']) { $where[] = 'suplier_id = ?'; $params[] = $f['suplier_id']; }
    if ($table === 'retur_pembelian' && $f['suplier_id']) { $where[] = 'suplier_id = ?'; $params[] = $f['suplier_id']; }
    if ($table === 'retur_penjualan' && $f['pelanggan_id']) {
        $nama = $pdo->prepare('SELECT nama FROM pelanggan WHERE id = ?');
        $nama->execute([$f['pelanggan_id']]);
        $where[] = 'customer_name = ?'; $params[] = $nama->fetchColumn() ?: '';
    }
    if ($table === 'barang' && $f['suplier_id']) { $where[] = 'suplier_id = ?'; $params[] = $f['suplier_id']; }
    if ($table === 'suplier' && $f['suplier_id']) { $where[] = 'id = ?'; $params[] = $f['suplier_id']; }
    if ($table === 'pelanggan' && $f['pelanggan_id']) { $where[] = 'id = ?'; $params[] = $f['pelanggan_id']; }

    $sql = "SELECT id FROM $table" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

if ($method === 'GET' && ($_GET['action'] ?? '') === 'preview') {
    $f = cleanse_filters($_GET);
    $counts = [];
    $total = 0;
    foreach ($f['modules'] as $table) {
        $ids = cleanse_find_ids($pdo, $table, $f);
        $counts[$table] = count($ids);
        $total += count($ids);
    }
    json_ok(['counts' => $counts, 'total' => $total]);
}

if ($method === 'GET' && ($_GET['action'] ?? '') === 'export') {
    $tables = [];
    foreach (CLEANSE_TABLES_ORDER as $t) {
        $tables[$t] = $pdo->query("SELECT * FROM $t")->fetchAll();
    }
    json_ok(['app' => 'app-mini-backup', 'version' => 1, 'generated_at' => date('c'), 'tables' => $tables]);
}

if ($method === 'POST') {
    $in = body();
    $action = $in['action'] ?? '';

    if ($action === 'cleanse') {
        $tokoNama = $pdo->query('SELECT nama FROM toko_profil WHERE id = 1')->fetchColumn();
        $confirm = trim((string)($in['confirm_phrase'] ?? ''));
        if ($confirm === '' || $confirm !== $tokoNama) {
            json_error('Kata konfirmasi tidak cocok dengan nama toko. Ketik ulang persis seperti yang tertera.');
        }
        $f = cleanse_filters($in);
        if (!$f['modules']) json_error('Pilih minimal satu kategori data yang mau dihapus.');

        $deleted = []; $skipped = [];
        $pdo->beginTransaction();
        try {
            // Fase 1: item Penjualan (cascade penjualan_item_lot otomatis,
            // TIDAK menyentuh barang_lot -- aman untuk barang.stok).
            if (in_array('penjualan', $f['modules'], true)) {
                $ids = cleanse_find_ids($pdo, 'penjualan', $f);
                if ($ids) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM penjualan_item WHERE penjualan_id IN ($ph)")->execute($ids);
                }
                $deleted['penjualan'] = count($ids);
            }
            // Fase 2: Retur (item+header, cascade otomatis lewat header).
            foreach (['retur_penjualan', 'retur_pembelian'] as $table) {
                if (in_array($table, $f['modules'], true)) {
                    $ids = cleanse_find_ids($pdo, $table, $f);
                    if ($ids) {
                        $ph = implode(',', array_fill(0, count($ids), '?'));
                        $pdo->prepare("DELETE FROM $table WHERE id IN ($ph)")->execute($ids);
                    }
                    $deleted[$table] = count($ids);
                }
            }
            // Fase 3: header Penjualan (item-nya sudah kosong dari fase 1).
            if (in_array('penjualan', $f['modules'], true)) {
                $ids = cleanse_find_ids($pdo, 'penjualan', $f);
                if ($ids) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("DELETE FROM penjualan WHERE id IN ($ph)")->execute($ids);
                }
            }
            // Fase 4: Pembelian -- lepas dulu barang_lot.pembelian_item_id
            // (SET NULL) SEBELUM hapus pembelian_item, supaya batch stoknya
            // (qty_sisa yang mungkin masih > 0) tidak ikut ter-cascade-hapus.
            // barang.stok TIDAK disentuh sama sekali di fase ini.
            if (in_array('pembelian', $f['modules'], true)) {
                $ids = cleanse_find_ids($pdo, 'pembelian', $f);
                if ($ids) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare(
                        "UPDATE barang_lot SET pembelian_item_id = NULL
                         WHERE pembelian_item_id IN (SELECT id FROM pembelian_item WHERE pembelian_id IN ($ph))"
                    )->execute($ids);
                    $pdo->prepare("DELETE FROM pembelian_item WHERE pembelian_id IN ($ph)")->execute($ids);
                    $pdo->prepare("DELETE FROM pembelian WHERE id IN ($ph)")->execute($ids);
                }
                $deleted['pembelian'] = count($ids);
            }
            // Fase 5: Barang & Stok -- semua baris yang tadinya merujuk
            // barang_lot (fase 1-4) sudah beres, jadi aman dihapus langsung.
            if (in_array('barang', $f['modules'], true)) {
                $ids = cleanse_find_ids($pdo, 'barang', $f);
                $ok = 0; $blocked = 0;
                foreach ($ids as $id) {
                    try {
                        $pdo->prepare('DELETE FROM barang_lot WHERE barang_id = ?')->execute([$id]);
                        $pdo->prepare('DELETE FROM barang WHERE id = ?')->execute([$id]);
                        $ok++;
                    } catch (PDOException $e) {
                        if ($e->getCode() !== '23000') throw $e;
                        $blocked++; // masih dirujuk transaksi yang TIDAK ikut kena filter
                    }
                }
                $deleted['barang'] = $ok;
                if ($blocked) $skipped['barang'] = $blocked;
            }
            // Fase 6: Suplier/Pelanggan -- baris yang masih dipakai (RESTRICT)
            // dilewati, bukan membatalkan seluruh operasi.
            foreach (['suplier', 'pelanggan'] as $table) {
                if (in_array($table, $f['modules'], true)) {
                    $ids = cleanse_find_ids($pdo, $table, $f);
                    $ok = 0; $blocked = 0;
                    foreach ($ids as $id) {
                        try {
                            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
                            $ok++;
                        } catch (PDOException $e) {
                            if ($e->getCode() !== '23000') throw $e;
                            $blocked++;
                        }
                    }
                    $deleted[$table] = $ok;
                    if ($blocked) $skipped[$table] = $blocked;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_error('Gagal menghapus: ' . $e->getMessage(), 400);
        }
        json_ok(['deleted' => $deleted, 'skipped' => $skipped]);
    }

    if ($action === 'import') {
        if (($in['app'] ?? '') !== 'app-mini-backup' || (int)($in['version'] ?? 0) !== 1) {
            json_error('File ini bukan file backup App-mini yang valid.');
        }
        $tables = $in['tables'] ?? [];
        $restored = []; $skipped = [];
        // Transaksi TERPISAH per tabel (bukan satu transaksi besar untuk
        // semuanya) -- tabel yang datanya merujuk tabel LAIN yang ternyata
        // TIDAK ikut kosong (mis. retur_penjualan_item merujuk barang_id
        // yang barang-nya tidak dipulihkan karena tabel barang masih ada
        // isi asli) akan gagal FK, tapi kegagalan itu HARUS cuma
        // membatalkan tabel itu sendiri, bukan seluruh proses pemulihan.
        foreach (CLEANSE_TABLES_ORDER as $table) {
            $rows = $tables[$table] ?? [];
            if (!$rows) continue;
            // kategori sengaja tidak pernah dihapus tool ini -- jangan
            // dicoba-insert ulang, biar tidak bentrok primary key.
            if ($table === 'kategori') continue;
            $count = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            if ($count > 0) { $skipped[$table] = 'tabel sudah ada isinya, dilewati'; continue; }

            $cols = array_keys($rows[0]);
            $colList = implode(',', $cols);
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO $table ($colList) VALUES ($ph)");
                foreach ($rows as $row) $stmt->execute(array_values($row));
                $pdo->commit();
                $restored[$table] = count($rows);
            } catch (Throwable $e) {
                $pdo->rollBack();
                $skipped[$table] = 'gagal dipulihkan (' . $e->getMessage() . ')';
            }
        }
        json_ok(['restored' => $restored, 'skipped' => $skipped]);
    }
}

json_error('Method not allowed', 405);
