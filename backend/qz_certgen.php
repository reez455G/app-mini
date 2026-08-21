<?php
// CLI SEKALI-JALAN: generate sertifikat self-signed + private key untuk
// signed connection QZ Tray. Dipanggil oleh provision-qz-signing.bat,
// TIDAK PERNAH dipanggil lewat browser -- sengaja ditaruh di backend/
// (bukan backend/api/) supaya jelas ini skrip administratif, bukan
// endpoint HTTP, dan ada penjagaan CLI-only di bawah sebagai lapis kedua.
//
// SENGAJA MENOLAK JALAN kalau kunci sudah ada -- satu sertifikat berlaku
// seumur instalasi toko ini (bukan di-generate ulang tiap update aplikasi
// atau tiap provisioning dijalankan lagi). Kalau memang perlu rotasi
// sertifikat (kompromise, dsb), itu harus jadi keputusan sadar: hapus
// folder qz-keys\ manual dulu, baru jalankan skrip ini lagi -- BUKAN
// sesuatu yang boleh terjadi diam-diam.
require_once __DIR__ . '/qz_signing_key.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini cuma untuk dijalankan lewat command line (provision-qz-signing.bat), bukan lewat browser.');
}

if (is_file(QZ_SIGNING_KEY_PATH)) {
    fwrite(STDOUT, "Kunci sudah ada di " . QZ_SIGNING_KEY_PATH . " -- TIDAK dibuat ulang.\n");
    fwrite(STDOUT, "(Mau rotasi sertifikat? Hapus folder " . QZ_KEYS_DIR . " manual dulu, baru jalankan skrip ini lagi.)\n");
    exit(0);
}

if (!is_dir(QZ_KEYS_DIR) && !mkdir(QZ_KEYS_DIR, 0700, true) && !is_dir(QZ_KEYS_DIR)) {
    fwrite(STDERR, "Gagal membuat folder " . QZ_KEYS_DIR . "\n");
    exit(1);
}

// PHP openssl_pkey_new() dkk BUTUH file openssl.cnf yang valid untuk jalan
// sama sekali -- kalau php.ini di komputer itu tidak mengarah ke openssl.cnf
// yang benar (banyak terjadi di XAMPP Windows, tergantung versi/instalasi),
// gagalnya dengan pesan cryptic "system library:fopen:No such process" yang
// tidak menyebut openssl.cnf sama sekali. Solusinya: BUKAN menebak lokasi
// openssl.cnf bawaan XAMPP (beda-beda tiap versi, tidak bisa diandalkan) --
// bikin openssl.cnf minimal SENDIRI di sini, jadi tidak bergantung pada
// konfigurasi PHP/XAMPP apa pun di komputer target sama sekali.
$opensslCnf = tempnam(sys_get_temp_dir(), 'qzcnf');
file_put_contents($opensslCnf, "[req]\ndistinguished_name = req_distinguished_name\nx509_extensions = v3_ca\nprompt = no\n\n[req_distinguished_name]\n\n[v3_ca]\nbasicConstraints = critical,CA:true\nkeyUsage = critical, digitalSignature, keyCertSign, cRLSign\n");
$opensslConfig = ['config' => $opensslCnf];

$privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + $opensslConfig);
if ($privateKey === false) {
    fwrite(STDERR, "Gagal generate private key: " . openssl_error_string() . "\n");
    @unlink($opensslCnf);
    exit(1);
}

// Semua field DN diisi eksplisit -- kalau dibiarkan kosong, PHP mengambil
// default dari openssl.cnf bawaan mesin (di Windows/XAMPP kadang berisi
// placeholder "AU"/"Some-State" yang tidak relevan) tergantung konfigurasi
// PHP di komputer target. Isinya cuma kosmetik (tidak memengaruhi trust
// QZ Tray sama sekali), tapi lebih rapi diisi benar daripada bergantung ke
// default yang tidak terprediksi.
$dn = [
    'commonName' => 'app-mini QZ Signing', 'organizationName' => 'PUTRA JAYA MOTOR',
    'countryName' => 'ID', 'stateOrProvinceName' => 'Jawa Tengah', 'localityName' => 'Semarang',
];
$csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha512'] + $opensslConfig);
if ($csr === false) {
    fwrite(STDERR, "Gagal generate CSR: " . openssl_error_string() . "\n");
    @unlink($opensslCnf);
    exit(1);
}

// 3650 hari (~10 tahun) -- sengaja panjang supaya rotasi praktis tidak
// pernah jadi masalah operasional selama umur toko ini pakai aplikasi ini.
$cert = openssl_csr_sign($csr, null, $privateKey, 3650, ['digest_alg' => 'sha512'] + $opensslConfig);
if ($cert === false) {
    fwrite(STDERR, "Gagal generate sertifikat: " . openssl_error_string() . "\n");
    @unlink($opensslCnf);
    exit(1);
}

// openssl_pkey_export() JUGA butuh config yang sama (bukan cuma _new/_csr_new/
// _csr_sign) -- kalau tidak dikasih, dia gagal DIAM-DIAM (return false, tidak
// exception) dan $privateKeyPem tetap kosong. Sebelumnya return value-nya
// tidak dicek sama sekali, jadi file_put_contents() menulis file kosong
// tanpa ada tanda kesalahan apa pun -- bug nyata yang baru ketemu waktu tes
// di Windows/XAMPP sungguhan, sekarang dicek eksplisit.
$exportedKey = openssl_pkey_export($privateKey, $privateKeyPem, null, $opensslConfig);
$exportedCert = openssl_x509_export($cert, $certPem);
@unlink($opensslCnf);
if (!$exportedKey || !$exportedCert || $privateKeyPem === '' || $certPem === '') {
    fwrite(STDERR, "Gagal mengekspor key/sertifikat ke format PEM: " . openssl_error_string() . "\n");
    exit(1);
}

file_put_contents(QZ_SIGNING_KEY_PATH, $privateKeyPem);
@chmod(QZ_SIGNING_KEY_PATH, 0600); // no-op di Windows, tapi aman kalau suatu saat dijalankan di Linux/Mac
file_put_contents(QZ_PUBLIC_CERT_PATH, $certPem);

fwrite(STDOUT, "OK - sertifikat & kunci dibuat:\n");
fwrite(STDOUT, "  Private key : " . QZ_SIGNING_KEY_PATH . "\n");
fwrite(STDOUT, "  Sertifikat  : " . QZ_PUBLIC_CERT_PATH . "\n");
