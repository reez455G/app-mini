<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';

// Master data yang sudah dipakai transaksi lama dilindungi FOREIGN KEY (RESTRICT),
// jadi DELETE-nya melempar PDOException. Tanpa penangan ini exception-nya lolos
// keluar jadi HTTP 500 dengan body kosong — frontend gagal mem-parse JSON dan cuma
// bisa menampilkan pesan yang tidak berguna. Semua endpoint DELETE lewat sini.
function delete_row(PDO $pdo, string $table, int $id, string $label): void {
    try {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            json_error("$label ini masih dipakai di data transaksi yang tersimpan, jadi tidak bisa dihapus.", 409);
        }
        throw $e;
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── Sinkronisasi stok ↔ barang_lot ────────────────────────────────────
// barang.stok adalah angka agregat cepat; barang_lot adalah rinciannya per
// batch. Keduanya WAJIB selalu sama (SUM(qty_sisa) == stok) — kalau meleset,
// penjualan.php akan menolak transaksi dengan "Data batch tidak sinkron".
// Dua fungsi di bawah dipakai jalur yang mengubah stok TANPA lewat pembelian
// (yaitu koreksi manual di Data Barang).

// Rata-rata tertimbang harga beli dari batch yang masih bersisa — dipakai
// sebagai harga batch penyesuaian, supaya modal di Laporan Laba tidak
// melonjak/anjlok cuma gara-gara stok dikoreksi.
function lot_avg_cost(PDO $pdo, int $barangId): float {
    $s = $pdo->prepare(
        'SELECT SUM(qty_sisa * harga_beli) / NULLIF(SUM(qty_sisa), 0)
         FROM barang_lot WHERE barang_id = ? AND qty_sisa > 0'
    );
    $s->execute([$barangId]);
    return (float)($s->fetchColumn() ?: 0);
}

// Selaraskan barang_lot dengan perubahan stok manual.
// - stok naik  → satu batch penyesuaian baru (tanpa pembelian & tanpa suplier)
// - stok turun → potong FIFO dari batch terlama, sama seperti penjualan
// Harus dipanggil DI DALAM transaksi yang sama dengan UPDATE barang.stok-nya.
function lot_sync_stok(PDO $pdo, int $barangId, int $stokLama, int $stokBaru, float $fallbackCost = 0): void {
    $delta = $stokBaru - $stokLama;
    if ($delta === 0) return;

    if ($delta > 0) {
        $harga = lot_avg_cost($pdo, $barangId) ?: $fallbackCost;
        $pdo->prepare(
            'INSERT INTO barang_lot (barang_id, pembelian_item_id, suplier_id, harga_beli, qty_awal, qty_sisa, tanggal)
             VALUES (?, NULL, NULL, ?, ?, ?, CURDATE())'
        )->execute([$barangId, $harga, $delta, $delta]);
        return;
    }

    // Stok dikurangi: tarik FIFO dari batch terlama, pola sama dengan
    // penjualan.php supaya batch yang "hilang" duluan konsisten di mana pun.
    $sisa = -$delta;
    $lots = $pdo->prepare('SELECT id, qty_sisa FROM barang_lot WHERE barang_id = ? AND qty_sisa > 0 ORDER BY tanggal ASC, id ASC FOR UPDATE');
    $lots->execute([$barangId]);
    foreach ($lots->fetchAll() as $lot) {
        if ($sisa <= 0) break;
        $take = min($sisa, (int)$lot['qty_sisa']);
        $pdo->prepare('UPDATE barang_lot SET qty_sisa = qty_sisa - ? WHERE id = ?')->execute([$take, $lot['id']]);
        $sisa -= $take;
    }
    if ($sisa > 0) {
        // Hanya terjadi kalau data batch sudah tidak konsisten SEBELUM ini —
        // lebih baik gagal jelas daripada memperlebar selisihnya diam-diam.
        throw new RuntimeException("Batch barang ini tidak cukup untuk menurunkan stok sebanyak itu (kurang $sisa pcs) — data batch perlu diperiksa admin.");
    }
}

// ── Nilai retur ───────────────────────────────────────────────────────
// Retur penjualan mengembalikan barang ke stok, tapi dulu omzet & laba di
// semua laporan tetap dihitung penuh — barang yang sama terhitung dua kali
// (sebagai penjualan DAN sebagai stok yang kembali ada di rak). Dua fragmen
// SQL di bawah dipakai bersama oleh laporan/laba, global, penjualan,
// top_produk, tren_penjualan & retur supaya aturannya cuma ditulis sekali.
//
// unit_price diambil dari rata-rata tertimbang per (penjualan, barang), BUKAN
// join langsung ke penjualan_item: satu barang bisa muncul di lebih dari satu
// baris nota (mis. 1 pcs harga ecer + 6 pcs harga grosir), dan join biasa akan
// menggandakan barisnya.
//
// nilai_modal diambil dari batch yang DIBUAT oleh retur itu (barang fisik
// kembali jadi batch baru, lihat retur_penjualan.php) — modal itu harus ikut
// dibatalkan dari HPP, kalau tidak labanya jadi terlalu kecil. Retur lama
// sebelum fitur batch ada tidak punya barang_lot_id, modalnya dianggap 0.
function sql_retur_penjualan_nilai(): string {
    return 'SELECT rp.tanggal, rp.original_invoice_no, pj.id AS penjualan_id, ri.barang_id, ri.qty,
                   ri.qty * pia.unit_price AS nilai_omzet,
                   ri.qty * COALESCE(bl.harga_beli, 0) AS nilai_modal
            FROM retur_penjualan rp
            JOIN retur_penjualan_item ri ON ri.retur_id = rp.id
            JOIN penjualan pj ON pj.invoice_no = rp.original_invoice_no
            JOIN (SELECT penjualan_id, barang_id, SUM(subtotal) / NULLIF(SUM(qty), 0) AS unit_price
                  FROM penjualan_item GROUP BY penjualan_id, barang_id) pia
              ON pia.penjualan_id = pj.id AND pia.barang_id = ri.barang_id
            LEFT JOIN barang_lot bl ON bl.id = ri.barang_lot_id';
}

// Retur pembelian: barang keluar lagi ke suplier, jadi belanja yang benar-benar
// terjadi berkurang. Nilainya dari batch ASAL yang dipotong retur itu.
function sql_retur_pembelian_nilai(): string {
    return 'SELECT rb.tanggal, rb.suplier_id, ri.barang_id, ri.qty,
                   ri.qty * COALESCE(bl.harga_beli, 0) AS nilai_biaya
            FROM retur_pembelian rb
            JOIN retur_pembelian_item ri ON ri.retur_id = rb.id
            LEFT JOIN barang_lot bl ON bl.id = ri.barang_lot_id';
}

// Kode berurutan sederhana untuk master data (SUP-0001, CUST-0001, dst).
// ponytail: MAX(angka di belakang)+1, bukan tabel sequence tersendiri — cukup
// untuk satu toko, satu admin. Naikkan ke sequence/lock kalau nanti ada input
// paralel yang nyata.
// MAX+1, BUKAN COUNT(*)+1: kode/nomor ini UNIQUE di schema, jadi begitu ada satu
// baris dihapus COUNT(*)+1 akan mengulang kode yang masih terpakai → INSERT gagal
// duplicate key dan penambahan data jadi mustahil sampai jumlah barisnya pas lagi.
function next_kode(PDO $pdo, string $table, string $prefix): string {
    $lead = strlen($prefix) + 2; // "SUP-" → angka mulai posisi 5 (SUBSTRING 1-based)
    $n = (int)$pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(kode, $lead) AS UNSIGNED)), 0) FROM $table"
    )->fetchColumn() + 1;
    return $prefix . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// Nomor dokumen harian (INV-260806-0001, dst) — sequence per hari, diambil dari
// nomor tertinggi yang sudah ada hari itu (alasan MAX vs COUNT sama seperti di atas).
function next_doc_no(PDO $pdo, string $table, string $col, string $prefix): string {
    $datePrefix = $prefix . '-' . date('ymd') . '-';
    $lead = strlen($datePrefix) + 1; // lewati "PB-260806-" → angka urut mulai di sini
    $n = (int)$pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING($col, $lead) AS UNSIGNED)), 0) FROM $table
         WHERE $col LIKE " . $pdo->quote($datePrefix . '%')
    )->fetchColumn() + 1;
    return $datePrefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
