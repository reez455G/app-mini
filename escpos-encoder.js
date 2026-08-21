// EscPosEncoder — murni fungsi, tanpa QZ Tray, tanpa DOM. Ambil satu bentuk
// data struk (dari buildReceiptData() di Dashboard.dc.html) + opsi cetak,
// keluarkan Uint8Array command ESC/POS siap kirim lewat PrinterManager.print().
//
// Lebar 48 kolom: printer thermal 80mm mencetak di lebar kepala 72,1mm
// (576 dot @203dpi -- angka yang sama yang sudah dipakai & diverifikasi di
// CSS cetak browser-print, lihat komentar #penj-invoice-print di
// Dashboard.dc.html). Font A ESC/POS standar = 12 dot/karakter di printer
// 203dpi generic manapun (bukan merek tertentu), 576 / 12 = 48 kolom.
//
// Layout ditentukan Owner: kop toko, blok INV/TGL/KSR/PEL (satu field per
// baris, bukan digabung), nama barang di baris sendiri lalu "qty x harga"
// vs subtotal di baris berikutnya, Subtotal+TOTAL, Metode/Bayar/Kembali,
// footer TERIMA KASIH+SELAMAT BERBELANJA -- dipisah border "====" (blok
// besar) dan "----" (blok kecil). Sama persis dipakai browser-print
// (Dashboard.dc.html, #penj-invoice-print) supaya kedua jalur cetak
// terlihat identik strukturnya.
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

  // Potong + "..." kalau melebihi maxLen -- dipakai baris PEL supaya nama
  // pelanggan yang panjang tidak pernah bikin baris turun/lebih lebar dari
  // kolom yang sudah ada (beda dari nama barang, yang boleh wrap/pindah
  // baris; ini cuma metadata satu baris, bukan bagian tabel item).
  function truncate(str, maxLen) {
    str = String(str);
    if (str.length <= maxLen) return str;
    return str.slice(0, Math.max(0, maxLen - 3)) + '...';
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
  // width dipotong paksa (bukan meluber) -- ini yang menjaga baris qty/
  // harga/subtotal di bawahnya tidak pernah terdorong keluar kertas.
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

  // Satu item = 1-2 baris: nama barang (wrap ke lebar PENUH kalau panjang,
  // bukan dipotong paksa jadi kolom sempit), lalu "qty x harga" di kiri vs
  // subtotal di kanan pada baris berikutnya.
  function itemLines(item, width) {
    var out = wrapText(item.nama || '', width);
    var kiri = '  ' + item.qty + ' x ' + formatRibuan(item.unitPrice);
    twoColumn(kiri, formatRibuan(item.subtotal), width).forEach(function (l) { out.push(l); });
    return out;
  }

  function build(receiptData, opts) {
    opts = opts || {};
    var width = opts.width || 48;
    var autoCut = opts.autoCut !== false;
    var cashDrawer = !!opts.cashDrawer && receiptData.paymentMethod === 'TUNAI';
    var items = receiptData.items || [];

    var out = [];
    function push(bytesArr) { out.push.apply(out, bytesArr); }
    function line(str) { push(bytesOf(str)); out.push(LF); }
    function sep(ch) { line(new Array(width + 1).join(ch)); }
    function align(n) { push([ESC, 0x61, n]); } // 0 kiri, 1 tengah, 2 kanan
    function bold(on) { push([ESC, 0x45, on ? 1 : 0]); }

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
    // CUST: nama pelanggan dari receiptData.custName -- SATU sumber data yang
    // sama dipakai QZ Tray/ESC-POS, browser-print, dan reprint (buildReceiptData()
    // di Dashboard.dc.html), sudah snapshot histori transaksi, bukan state
    // login saat ini. "Umum" kalau transaksi walk-in tanpa nama pelanggan.
    var custLabel = 'CUST : ';
    line(custLabel + truncate(receiptData.custName || 'Umum', width - custLabel.length));
    sep('-');

    items.forEach(function (it) {
      itemLines(it, width).forEach(line);
    });
    sep('-');

    // Subtotal & TOTAL sengaja nilainya sama (grandTotal) -- aplikasi ini
    // tidak punya konsep diskon/pajak terpisah, jadi tidak ada subtotal
    // "sebelum" apa pun buat dikurangi/ditambah. Dua baris tetap ditampilkan
    // karena format struk yang diminta Owner.
    twoColumn('Subtotal', formatRibuan(receiptData.grandTotal), width).forEach(line);
    bold(true);
    twoColumn('TOTAL', formatRibuan(receiptData.grandTotal), width).forEach(line);
    bold(false);
    sep('-');

    twoColumn('Metode', paymentLabel(receiptData.paymentMethod), width).forEach(line);
    twoColumn('Bayar', formatRibuan(receiptData.amountPaid), width).forEach(line);
    // Kembali cuma relevan untuk Tunai -- Transfer selalu 0, baris ini
    // dibuang khusus Transfer supaya tidak menampilkan info kosong.
    if (receiptData.paymentMethod !== 'TRANSFER') {
      twoColumn('Kembali', formatRibuan(receiptData.kembalian), width).forEach(line);
    }
    sep('=');

    align(1); bold(true);
    line('TERIMA KASIH');
    bold(false);
    line('SELAMAT BERBELANJA');
    sep('=');

    push([LF, LF]);
    if (autoCut) push([GS, 0x56, 0x01]); // potong sebagian -- printer tanpa cutter cukup mengabaikan command ini
    if (cashDrawer) push([ESC, 0x70, 0x00, 0x19, 0xFA]); // kick drawer pin 2

    return new Uint8Array(out);
  }

  window.EscPosEncoder = { build: build, wrapText: wrapText, formatRibuan: formatRibuan, truncate: truncate };
})();
