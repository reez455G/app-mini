<?php
header('Content-Type: application/json; charset=utf-8');

function json_ok($data = null, int $status = 200): never {
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_error(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function body(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
