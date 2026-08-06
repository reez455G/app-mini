<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$in = body();
$username = trim($in['username'] ?? '');
$password = (string)($in['password'] ?? '');
if ($username === '' || $password === '') json_error('Username dan password wajib diisi.');

$stmt = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Username atau password salah.', 401);
}

$_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']];
json_ok($_SESSION['user']);
