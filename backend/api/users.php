<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$me = require_owner(); // kelola akun (termasuk lihat daftar username) khusus Owner
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

if ($method === 'GET') {
    json_ok($pdo->query('SELECT id, username, role FROM users ORDER BY username')->fetchAll());
}

if ($method === 'POST') {
    $in = body();
    $username = trim($in['username'] ?? '');
    $password = (string)($in['password'] ?? '');
    $role = ($in['role'] ?? '') === 'owner' ? 'owner' : 'karyawan';
    if ($username === '' || $password === '') json_error('Username dan password wajib diisi.');
    if (strlen($password) < 6) json_error('Password minimal 6 karakter.');
    try {
        $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
    } catch (PDOException $e) {
        json_error('Username sudah digunakan.', 409);
    }
    json_ok(['id' => (int)$pdo->lastInsertId(), 'username' => $username, 'role' => $role], 201);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) json_error('id wajib diisi.');

// Owner terakhir tidak boleh dihapus atau diturunkan: tanpa satu pun owner,
// Master Data (termasuk kelola akun) jadi tidak bisa dibuka siapa pun lagi.
function is_last_owner(PDO $pdo, int $id): bool {
    $s = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $s->execute([$id]);
    if ($s->fetchColumn() !== 'owner') return false;
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn() <= 1;
}

if ($method === 'PUT') {
    $in = body();
    if (!empty($in['role']) && $in['role'] !== 'owner' && is_last_owner($pdo, $id)) json_error('Owner terakhir tidak bisa diubah jadi Karyawan.');
    $fields = []; $params = [];
    if (!empty($in['username'])) { $fields[] = 'username = ?'; $params[] = trim($in['username']); }
    if (!empty($in['role'])) { $fields[] = 'role = ?'; $params[] = $in['role'] === 'owner' ? 'owner' : 'karyawan'; }
    if (!empty($in['password'])) {
        if (strlen($in['password']) < 6) json_error('Password minimal 6 karakter.');
        $fields[] = 'password_hash = ?'; $params[] = password_hash($in['password'], PASSWORD_DEFAULT);
    }
    if (!$fields) json_error('Tidak ada perubahan.');
    $params[] = $id;
    try {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    } catch (PDOException $e) {
        json_error('Username sudah digunakan.', 409);
    }
    json_ok(['updated' => $id]);
}

if ($method === 'DELETE') {
    if ($id === $me['id']) json_error('Tidak bisa menghapus akun sendiri.');
    if (is_last_owner($pdo, $id)) json_error('Owner terakhir tidak bisa dihapus.');
    delete_row($pdo, 'users', $id, 'Pengguna');
    json_ok(['deleted' => $id]);
}

json_error('Method not allowed', 405);
