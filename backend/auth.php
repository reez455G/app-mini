<?php
require_once __DIR__ . '/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) json_error('Belum login.', 401);
    return $u;
}

function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        json_error('Tidak punya akses untuk aksi ini.', 403);
    }
    return $u;
}

function require_owner(): array {
    return require_role('owner');
}
