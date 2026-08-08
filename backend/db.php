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
