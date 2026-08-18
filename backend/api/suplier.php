<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_owner(); // sudah memanggil require_login() -- suplier adalah data private Owner (nama, alamat, no HP)
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    json_ok($pdo->query('SELECT id, kode, nama, alamat, no_hp FROM suplier ORDER BY nama')->fetchAll());
}


if ($method === 'POST') {
    $in = body();
    $nama = trim($in['nama'] ?? '');
    if ($nama === '') json_error('Nama suplier wajib diisi.');
    $kode = next_kode($pdo, 'suplier', 'SUP');
    $alamat = trim($in['alamat'] ?? '') ?: '-';
    $noHp = trim($in['noHp'] ?? '') ?: '-';
    $pdo->prepare('INSERT INTO suplier (kode, nama, alamat, no_hp) VALUES (?, ?, ?, ?)')
        ->execute([$kode, $nama, $alamat, $noHp]);
    json_ok(['id' => (int)$pdo->lastInsertId(), 'kode' => $kode, 'nama' => $nama, 'alamat' => $alamat, 'noHp' => $noHp], 201);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id wajib diisi.');

if ($method === 'PUT') {
    $in = body();
    $nama = trim($in['nama'] ?? '');
    if ($nama === '') json_error('Nama suplier wajib diisi.');
    $alamat = trim($in['alamat'] ?? '') ?: '-';
    $noHp = trim($in['noHp'] ?? '') ?: '-';
    $pdo->prepare('UPDATE suplier SET nama = ?, alamat = ?, no_hp = ? WHERE id = ?')
        ->execute([$nama, $alamat, $noHp, $id]);
    json_ok(['id' => $id, 'nama' => $nama, 'alamat' => $alamat, 'noHp' => $noHp]);
}

if ($method === 'DELETE') {
    delete_row($pdo, 'suplier', $id, 'Suplier');
    json_ok(['deleted' => $id]);
}

json_error('Method not allowed', 405);
