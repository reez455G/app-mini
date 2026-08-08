<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    json_ok($pdo->query('SELECT id, nama FROM kategori ORDER BY nama')->fetchAll());
}

require_owner(); // sisanya (tambah/ubah/hapus kategori) khusus Owner

if ($method === 'POST') {
    $in = body();
    $nama = trim(strtoupper($in['nama'] ?? ''));
    if ($nama === '') json_error('Nama kategori wajib diisi.');
    try {
        $pdo->prepare('INSERT INTO kategori (nama) VALUES (?)')->execute([$nama]);
    } catch (PDOException $e) {
        json_error('Kategori sudah ada.', 409);
    }
    json_ok(['id' => (int)$pdo->lastInsertId(), 'nama' => $nama], 201);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id wajib diisi.');

if ($method === 'PUT') {
    $in = body();
    $nama = trim(strtoupper($in['nama'] ?? ''));
    if ($nama === '') json_error('Nama kategori wajib diisi.');
    $pdo->prepare('UPDATE kategori SET nama = ? WHERE id = ?')->execute([$nama, $id]);
    json_ok(['id' => $id, 'nama' => $nama]);
}

if ($method === 'DELETE') {
    delete_row($pdo, 'kategori', $id, 'Kategori');
    json_ok(['deleted' => $id]);
}

json_error('Method not allowed', 405);
