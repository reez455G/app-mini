<?php
// Menandatangani permintaan koneksi/print QZ Tray dengan private key
// server-side -- private key TIDAK PERNAH keluar dari fungsi ini, cuma
// hasil tanda tangannya (base64) yang dikembalikan. Dipanggil otomatis oleh
// QZ Tray JS API lewat qz.security.setSignaturePromise() di printer-manager.js
// tiap kali ada koneksi/print baru -- bukan endpoint yang QZ Tray sendiri
// panggil langsung, jadi require_login() di sini adalah proteksi APLIKASI
// KITA (cuma user yang sudah login yang boleh minta tanda tangan), terpisah
// dari mekanisme trust QZ Tray sendiri.
//
// Endpoint ini SENGAJA cuma bisa "menandatangani satu string", TIDAK PERNAH
// menerima command ESC/POS atau bicara ke printer sama sekali -- structurally
// tidak bisa disalahgunakan jadi "print apa saja", karena dia tidak
// menyentuh printer-manager.js/PrinterManager.print() sama sekali.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../qz_signing_key.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method tidak diizinkan, harus POST.', 405);
}

// Batas ukuran payload SEBELUM json_decode -- data yang ditandatangani QZ
// Tray biasanya cuma beberapa ratus byte, 64KB sudah sangat longgar tapi
// tetap mencegah body raksasa dipaksa masuk ke json_decode.
$raw = file_get_contents('php://input');
if (strlen($raw) > 65536) {
    json_error('Payload terlalu besar.', 413);
}

$in = json_decode($raw, true);
$data = is_array($in) ? ($in['data'] ?? null) : null;
if (!is_string($data) || $data === '') {
    json_error('Payload tidak valid -- field "data" wajib diisi.', 400);
}

if (!is_readable(QZ_SIGNING_KEY_PATH)) {
    json_error('Kunci penandatanganan QZ Tray belum tersedia. Jalankan provision-qz-signing.bat dulu.', 500);
}

$privateKey = openssl_pkey_get_private('file://' . QZ_SIGNING_KEY_PATH);
if ($privateKey === false) {
    json_error('Kunci penandatanganan QZ Tray tidak valid.', 500);
}

$signature = '';
$ok = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA512);
if (!$ok) {
    json_error('Gagal menandatangani permintaan cetak.', 500);
}

json_ok(base64_encode($signature));
