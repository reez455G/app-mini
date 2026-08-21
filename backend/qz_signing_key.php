<?php
// Lokasi file kunci & sertifikat penandatanganan QZ Tray -- BUKAN berisi
// key-nya langsung, cuma path config (pola sama seperti config.php untuk
// kredensial DB). Key sungguhannya SENGAJA di luar document root (bukan di
// dalam htdocs/app-mini), supaya walau Apache/PHP suatu saat salah
// konfigurasi dan mulai men-serve file statis dari backend/, private key
// tetap tidak terjangkau URL apa pun -- dia memang tidak ada di folder yang
// di-serve web sama sekali.
//
// Dibuat oleh backend/qz_certgen.php (dipanggil dari provision-qz-signing.bat)
// sekali saat setup awal. Satu sertifikat berlaku seumur instalasi toko ini
// -- lihat komentar di qz_certgen.php soal kenapa tidak pernah di-generate
// ulang otomatis.
//
// Asumsi struktur instalasi XAMPP standar:
//   C:\xampp\htdocs\app-mini\backend\qz_signing_key.php   (__DIR__ di sini)
//   C:\xampp\qz-keys\                                      (folder kunci)
// Ganti QZ_KEYS_DIR kalau struktur folder server Anda beda dari itu.
define('QZ_KEYS_DIR', dirname(__DIR__, 3) . '/qz-keys');
define('QZ_SIGNING_KEY_PATH', QZ_KEYS_DIR . '/qz_signing_key.pem');
define('QZ_PUBLIC_CERT_PATH', QZ_KEYS_DIR . '/digital-certificate.txt');
