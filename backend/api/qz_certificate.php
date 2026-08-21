<?php
// Sertifikat publik QZ Tray -- SENGAJA tanpa require_login(), ini memang
// isinya public key material, tidak ada rahasia sama sekali di dalamnya
// (bandingkan dengan qz_sign.php yang wajib login karena itu jalur ke
// private key). Dipanggil dari printer-manager.js lewat
// qz.security.setCertificatePromise().
require_once __DIR__ . '/../qz_signing_key.php';
require_once __DIR__ . '/../response.php';

// no-store: kalau sertifikat pernah dirotasi (lihat komentar qz_certgen.php),
// browser tidak boleh terus pakai salinan lama dari cache selamanya.
header('Cache-Control: no-store');

if (!is_readable(QZ_PUBLIC_CERT_PATH)) {
    json_error('Sertifikat QZ Tray belum tersedia. Jalankan provision-qz-signing.bat dulu.', 404);
}

json_ok(file_get_contents(QZ_PUBLIC_CERT_PATH));
