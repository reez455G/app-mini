<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login();
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// Profil toko: selalu tepat satu baris (id=1), bukan daftar — tidak ada
// GET/id=, POST, atau DELETE seperti suplier/pelanggan.
if ($method === 'GET') {
    json_ok($pdo->query('SELECT nama, alamat, no_hp FROM toko_profil WHERE id = 1')->fetch());
}

require_owner();

if ($method === 'PUT') {
    $in = body();
    $nama = trim($in['nama'] ?? '');
    if ($nama === '') json_error('Nama toko wajib diisi.');
    $alamat = trim($in['alamat'] ?? '') ?: '-';
    $noHp = trim($in['noHp'] ?? '') ?: '-';
    $pdo->prepare('UPDATE toko_profil SET nama = ?, alamat = ?, no_hp = ? WHERE id = 1')
        ->execute([$nama, $alamat, $noHp]);
    json_ok(['nama' => $nama, 'alamat' => $alamat, 'noHp' => $noHp]);
}

json_error('Method not allowed', 405);
