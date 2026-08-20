// PrinterManager — satu-satunya tempat yang boleh memanggil qz.* langsung.
// Bungkus QZ Tray API resmi (vendor/printer/qz-tray.js) jadi antarmuka
// sederhana buat Dashboard.dc.html: connect/disconnect/findPrinters/print.
//
// TIDAK pakai sertifikat/signing (qz.security.setCertificatePromise/
// setSignaturePromise) -- artinya tiap koneksi baru dari browser yang belum
// pernah "Allow" akan memicu dialog trust SEKALI dari aplikasi QZ Tray
// sendiri (bukan dialog Windows/browser yang mau dihindari). Pemilik toko
// bisa centang "remember this decision" di dialog itu supaya tidak muncul
// lagi. Upgrade ke signed request adalah langkah terpisah (butuh sertifikat
// sendiri per instalasi), sengaja tidak dikerjakan di sini.
(function () {
  'use strict';

  function isAvailable() {
    return typeof qz !== 'undefined';
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
