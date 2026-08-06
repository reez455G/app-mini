<?php
require_once __DIR__ . '/config.php';

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
// ponytail: COUNT(*)+1, bukan tabel sequence tersendiri — cukup untuk satu toko,
// satu admin. Naikkan ke sequence/lock kalau nanti ada input paralel yang nyata.
function next_kode(PDO $pdo, string $table, string $prefix): string {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn() + 1;
    return $prefix . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

// Nomor dokumen harian (INV-260806-0001, dst) — sequence per hari dihitung dari
// baris yang sudah ada hari itu.
function next_doc_no(PDO $pdo, string $table, string $col, string $prefix): string {
    $datePrefix = $prefix . '-' . date('ymd') . '-';
    $n = (int)$pdo->query("SELECT COUNT(*) FROM $table WHERE $col LIKE " . $pdo->quote($datePrefix . '%'))->fetchColumn() + 1;
    return $datePrefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
