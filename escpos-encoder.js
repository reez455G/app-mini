// EscPosEncoder — murni fungsi, tanpa QZ Tray, tanpa DOM. Ambil satu bentuk
// data struk (dari buildReceiptData() di Dashboard.dc.html) + opsi cetak,
// keluarkan Uint8Array command ESC/POS siap kirim lewat PrinterManager.print().
//
// Lebar 48 kolom: printer thermal 80mm mencetak di lebar kepala 72,1mm
// (576 dot @203dpi -- angka yang sama yang sudah dipakai & diverifikasi di
// CSS cetak browser-print, lihat komentar #penj-invoice-print di
// Dashboard.dc.html). Font A ESC/POS standar = 12 dot/karakter di printer
// 203dpi generic manapun (bukan merek tertentu), 576 / 12 = 48 kolom.
(function () {
  'use strict';

  var ESC = 0x1B, GS = 0x1D, LF = 0x0A;

  function bytesOf(str) {
    // ponytail: ASCII saja. Karakter beraksen diganti '?' alih-alih peta
    // codepage CP437/1252 penuh -- nama barang bengkel praktis selalu ASCII.
    // Upgrade ke peta codepage kalau nanti ada nama yang benar-benar butuh.
    var out = [];
    for (var i = 0; i < str.length; i++) {
      var c = str.charCodeAt(i);
      out.push(c >= 0x20 && c <= 0x7E ? c : (c === 0x0A ? LF : 0x3F));
    }
    return out;
  }

  function padLeft(str, width) {
    str = String(str);
    return str.length >= width ? str : new Array(width - str.length + 1).join(' ') + str;
  }

  function padRight(str, width) {
    str = String(str);
    return str.length >= width ? str : str + new Array(width - str.length + 1).join(' ');
  }

  function centerText(str, width) {
    str = String(str);
    if (str.length >= width) return str;
    var left = Math.floor((width - str.length) / 2);
    return new Array(left + 1).join(' ') + str;
  }

  // Baris dua-kolom: kiri rata kiri, kanan rata kanan, dipisah spasi
  // secukupnya. Kalau tidak muat dalam satu baris, kanan turun ke baris
  // sendiri (rata kanan) alih-alih memotong salah satu sisi.
  function twoColumn(left, right, width) {
    left = String(left); right = String(right);
    if (left.length + right.length + 1 > width) {
      return [left, padLeft(right, width)];
    }
    return [left + new Array(width - left.length - right.length + 1).join(' ') + right];
  }

  // Word-wrap murni: pecah per kata, kata yang sendirian lebih panjang dari
  // width dipotong paksa (bukan meluber) -- ini yang menjaga kolom qty/
  // harga/subtotal di baris berikutnya tidak pernah terdorong keluar kertas.
  function wrapText(str, width) {
    var words = String(str).split(/\s+/).filter(Boolean);
    var lines = []; var cur = '';
    words.forEach(function (w) {
      while (w.length > width) {
        if (cur) { lines.push(cur); cur = ''; }
        lines.push(w.slice(0, width));
        w = w.slice(width);
      }
      var candidate = cur ? cur + ' ' + w : w;
      if (candidate.length > width) { lines.push(cur); cur = w; }
      else cur = candidate;
    });
    if (cur) lines.push(cur);
    return lines.length ? lines : [''];
  }

  function formatRibuan(n) {
    n = Math.round(Number(n) || 0);
    var s = String(Math.abs(n));
    var out = '';
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 === 0) out += '.';
      out += s[i];
    }
    return (n < 0 ? '-' : '') + out;
  }

  function paymentLabel(m) { return m === 'TRANSFER' ? 'Transfer' : 'Tunai'; }

  function build(receiptData, opts) {
    opts = opts || {};
    var width = opts.width || 48;
    var autoCut = opts.autoCut !== false;
    var cashDrawer = !!opts.cashDrawer && receiptData.paymentMethod === 'TUNAI';

    var out = [];
    function push(bytesArr) { out.push.apply(out, bytesArr); }
    function line(str) { push(bytesOf(str)); out.push(LF); }
    function lines(arr) { arr.forEach(line); }
    function sep(ch) { line(new Array(width + 1).join(ch)); }
    function align(n) { push([ESC, 0x61, n]); } // 0 kiri, 1 tengah, 2 kanan
    function bold(on) { push([ESC, 0x45, on ? 1 : 0]); }
    function doubleSize(on) { push([GS, 0x21, on ? 0x11 : 0x00]); }

    push([ESC, 0x40]); // init

    var toko = receiptData.toko || {};
    align(1); bold(true);
    if (toko.nama) line(toko.nama);
    bold(false);
    if (toko.alamat) wrapText(toko.alamat, width).forEach(line);
    if (toko.noHp) line(toko.noHp);
    sep('=');

    align(0);
    line('INV : ' + receiptData.invoiceNo);
    line('TGL : ' + receiptData.waktuLabel);
    line('KSR : ' + (receiptData.kasirUsername || '-'));
    sep('-');

    (receiptData.items || []).forEach(function (it) {
      wrapText(it.nama || '', width).forEach(line);
      var kiri = '  ' + it.qty + ' x ' + formatRibuan(it.unitPrice);
      twoColumn(kiri, formatRibuan(it.subtotal), width).forEach(line);
    });
    sep('-');

    twoColumn('Subtotal', formatRibuan(receiptData.grandTotal), width).forEach(line);
    bold(true); doubleSize(true);
    // Baris TOTAL double-size makan lebar dot dua kali lipat -- lebar
    // kolomnya dibagi dua supaya tetap muat 48 dot-lebar di kertas 80mm.
    twoColumn('TOTAL', formatRibuan(receiptData.grandTotal), Math.floor(width / 2)).forEach(line);
    doubleSize(false); bold(false);
    sep('-');

    twoColumn('Metode', paymentLabel(receiptData.paymentMethod), width).forEach(line);
    twoColumn('Bayar', formatRibuan(receiptData.amountPaid), width).forEach(line);
    twoColumn('Kembali', formatRibuan(receiptData.kembalian), width).forEach(line);
    sep('=');

    align(1); bold(true);
    line('TERIMA KASIH');
    bold(false);
    line('SELAMAT BERBELANJA');
    sep('=');

    push([LF, LF, LF]);
    if (autoCut) push([GS, 0x56, 0x01]); // potong sebagian -- printer tanpa cutter cukup mengabaikan command ini
    if (cashDrawer) push([ESC, 0x70, 0x00, 0x19, 0xFA]); // kick drawer pin 2

    return new Uint8Array(out);
  }

  window.EscPosEncoder = { build: build, wrapText: wrapText, formatRibuan: formatRibuan };
})();
