// PrinterManager — satu-satunya tempat yang boleh memanggil qz.* langsung.
// Bungkus QZ Tray API resmi (vendor/printer/qz-tray.js) jadi antarmuka
// sederhana buat Dashboard.dc.html: connect/disconnect/findPrinters/print.
//
// Signed connection: sertifikat & tanda tangan diambil dari backend
// (backend/api/qz_certificate.php, backend/api/qz_sign.php) -- private key
// TIDAK PERNAH ada di file ini atau di browser sama sekali, cuma hasil
// tanda tangannya (base64) yang lewat sini. Supaya QZ Tray benar-benar
// tidak pernah nanya izin lagi (bukan cuma sekali klik "remember"),
// sertifikat ini juga harus di-provisioning ke QZ Tray lewat
// provision-qz-signing.bat (override.crt + allowed.dat) -- itu langkah
// TERPISAH di luar kode ini, lihat installers/README.txt.
//
// fetchCertificate()/signData() memanggil api() (didefinisikan di
// Dashboard.dc.html lewat support.js) -- SENGAJA tidak dipanggil di level
// atas modul ini (baru dieksekusi belakangan, di dalam callback), karena
// printer-manager.js dimuat SEBELUM support.js selesai memproses halaman
// (urutan <script> di <head>), jadi api() belum tentu ada di titik file
// ini pertama kali dijalankan -- cuma boleh dipanggil lewat referensi
// lambat (di dalam fungsi), bukan langsung saat modul dimuat.
(function () {
  'use strict';

  function isAvailable() {
    return typeof qz !== 'undefined';
  }

  // dataToSign: string mentah dari QZ Tray, dikirim apa adanya ke backend
  // (backend/api/qz_sign.php) yang menandatanganinya pakai private key
  // server-side, TIDAK PERNAH sebaliknya (browser tidak pernah punya akses
  // ke key). Endpoint itu wajib login (require_login()) -- proteksi
  // aplikasi kita sendiri, terpisah dari mekanisme trust QZ Tray.
  function signData(dataToSign) {
    return api('POST', 'qz_sign.php', { data: dataToSign });
  }

  function fetchCertificate() {
    return api('GET', 'qz_certificate.php');
  }

  if (isAvailable()) {
    qz.security.setCertificatePromise(function (resolve, reject) {
      fetchCertificate().then(resolve).catch(reject);
    });
    qz.security.setSignatureAlgorithm('SHA512');
    qz.security.setSignaturePromise(function (toSign) {
      return function (resolve, reject) {
        signData(toSign).then(resolve).catch(reject);
      };
    });
  }

  function isConnected() {
    return isAvailable() && qz.websocket.isActive();
  }

  // qz.websocket.connect() bawaan mencoba 8 kombinasi host x port (4 port di
  // localhost, lalu 4 port lagi di localhost.qz.io) sebelum menyerah -- kalau
  // QZ Tray memang belum jalan, ini bisa makan waktu lama (apalagi kalau
  // resolusi DNS localhost.qz.io lambat/diblok jaringan toko) TANPA ada
  // tanda apa pun di layar, terasa seperti aplikasi diam/macet. Dibatasi 5
  // detik di sisi kita sendiri supaya kasir cepat dapat kabar jelas,
  // bukan menunggu lama untuk pesan yang sama persis.
  var CONNECT_TIMEOUT_MS = 5000;
  function withTimeout(promise, ms, message) {
    return new Promise(function (resolve, reject) {
      var timer = setTimeout(function () { reject(new Error(message)); }, ms);
      promise.then(
        function (v) { clearTimeout(timer); resolve(v); },
        function (e) { clearTimeout(timer); reject(e); }
      );
    });
  }

  function connect() {
    // Cek qz belum dimuat DIKEMBALIKAN sebagai Promise.reject, BUKAN throw
    // langsung -- connect() dipanggil dari banyak tempat yang semuanya
    // mengandalkan .then()/.catch(), kalau ini melempar synchronous
    // exception sebelum sempat mengembalikan Promise, .catch() pemanggilnya
    // tidak akan pernah kena, errornya jadi tidak kelihatan sama sekali di
    // UI (persis gejala "tidak muncul apa-apa" yang pernah terjadi).
    if (!isAvailable()) {
      return Promise.reject(new Error('QZ Tray belum bisa dimuat (vendor/printer/qz-tray.js tidak ada/gagal load).'));
    }
    if (qz.websocket.isActive()) return Promise.resolve();
    return withTimeout(
      qz.websocket.connect(),
      CONNECT_TIMEOUT_MS,
      'QZ Tray tidak merespons dalam ' + (CONNECT_TIMEOUT_MS / 1000) + ' detik -- pastikan aplikasi QZ Tray sudah terpasang dan sedang berjalan (cek ikon di system tray Windows).'
    );
  }

  function disconnect() {
    if (!isAvailable() || !qz.websocket.isActive()) return Promise.resolve();
    return qz.websocket.disconnect();
  }

  function findPrinters() {
    return connect().then(function () { return qz.printers.find(); });
  }

  function getDefaultPrinter() {
    return connect().then(function () { return qz.printers.getDefault(); }).catch(function () { return null; });
  }

  // bytes: Uint8Array hasil EscPosEncoder.build(). Dikirim sebagai hex
  // string (format:'hex'), BUKAN 'plain' -- byte kontrol ESC/POS (0x1B dkk)
  // rusak kalau lewat encoding string biasa, hex aman untuk data biner apa
  // pun tanpa perlu peduli encoding karakternya.
  function bytesToHex(bytes) {
    var out = '';
    for (var i = 0; i < bytes.length; i++) {
      var h = bytes[i].toString(16);
      out += h.length < 2 ? '0' + h : h;
    }
    return out;
  }

  function print(bytes, printerName, copies) {
    return connect()
      .then(function () {
        return printerName ? Promise.resolve(printerName) : getDefaultPrinter();
      })
      .then(function (name) {
        if (!name) throw new Error('Tidak ada printer yang dipilih/ditemukan.');
        var config = qz.configs.create(name, { copies: Math.max(1, parseInt(copies, 10) || 1) });
        return qz.print(config, [{ type: 'raw', format: 'hex', data: bytesToHex(bytes) }]);
      });
  }

  window.PrinterManager = {
    isAvailable: isAvailable,
    isConnected: isConnected,
    connect: connect,
    disconnect: disconnect,
    findPrinters: findPrinters,
    getDefaultPrinter: getDefaultPrinter,
    print: print,
    testPrint: print,
  };
})();
