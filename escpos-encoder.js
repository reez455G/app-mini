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
// Layout dibuat compact (hemat kertas) atas permintaan Owner sesudah tes
// cetak fisik: field yang berkaitan digabung satu baris (invoice+waktu,
// kasir+pelanggan), nama barang tabel dgn lebar kolom ADAPTIF (dihitung
// dari data transaksi ini, bukan angka tetap) supaya barang muat 1 baris
// selama masih memungkinkan, dan border/footer yang tidak esensial dibuang.
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

  // Lebar kolom No./Qty/Harga/Total dihitung dari data TRANSAKSI INI (bukan
  // angka tetap) -- supaya kolom angka selalu pas untuk transaksi kecil
  // (hemat, sisa lebar jatuh ke nama barang) MAUPUN transaksi dengan harga
  // besar (kolom melebar sendiri, tidak pernah kepotong). Minimum per kolom
  // dijaga supaya header "Qty/Harga/Total" sendiri tidak pernah terpotong.
  function computeColumns(items, width) {
    var noWidth = Math.max(1, String(items.length).length);
    var qtyWidth = 3, hargaWidth = 5, subWidth = 5; // minimum: muat header "Qty"/"Harga"/"Total"
    items.forEach(function (it) {
      qtyWidth = Math.max(qtyWidth, String(it.qty).length);
      hargaWidth = Math.max(hargaWidth, formatRibuan(it.unitPrice).length);
      subWidth = Math.max(subWidth, formatRibuan(it.subtotal).length);
    });
    var spacers = 4; // spasi pemisah: No|Nama, Nama|Qty, Qty|Harga, Harga|Total
    var nameWidth = width - noWidth - qtyWidth - hargaWidth - subWidth - spacers;
    if (nameWidth < 8) {
      // Kolom angka kebetulan sangat lebar (harga jutaan+) -- prioritaskan
      // nama tetap punya ruang wajar, kolom angka yang mengalah dulu.
      var deficit = 8 - nameWidth;
      var shrink = Math.min(deficit, Math.max(0, hargaWidth - 5));
      hargaWidth -= shrink; deficit -= shrink;
      shrink = Math.min(deficit, Math.max(0, subWidth - 5));
      subWidth -= shrink;
      nameWidth = width - noWidth - qtyWidth - hargaWidth - subWidth - spacers;
    }
    return { noWidth: noWidth, nameWidth: Math.max(1, nameWidth), qtyWidth: qtyWidth, hargaWidth: hargaWidth, subWidth: subWidth };
  }

  function itemRow(no, name, qty, harga, sub, cols) {
    return padLeft(no, cols.noWidth) + ' ' + padRight(name, cols.nameWidth) + ' ' +
      padLeft(qty, cols.qtyWidth) + ' ' + padLeft(harga, cols.hargaWidth) + ' ' + padLeft(sub, cols.subWidth);
  }

  // Satu baris kalau nama muat di nameWidth (prioritas utama -- diminta
  // Owner supaya barang TIDAK wrap kalau sebenarnya masih muat). Kalau
  // benar-benar tidak muat, nama dipecah lewat wrapText mengisi lebar penuh
  // (bukan cuma nameWidth -- baris nama sendirian, jadi boleh pinjam lebar
  // kolom angka), lalu qty/harga/subtotal turun ke baris sendiri rata
  // kanan -- supaya TIDAK PERNAH bertabrakan dengan nama.
  function itemLines(no, item, cols, width) {
    var name = item.nama || '';
    var qtyStr = String(item.qty), hargaStr = formatRibuan(item.unitPrice), subStr = formatRibuan(item.subtotal);
    if (name.length <= cols.nameWidth) {
      return [itemRow(no, name, qtyStr, hargaStr, subStr, cols)];
    }
    var out = [];
    var nameLines = wrapText(name, width - cols.noWidth - 1);
    nameLines.forEach(function (l, i) {
      out.push((i === 0 ? padLeft(no, cols.noWidth) : padLeft('', cols.noWidth)) + ' ' + l);
    });
    var numLine = padLeft(qtyStr, cols.qtyWidth) + ' ' + padLeft(hargaStr, cols.hargaWidth) + ' ' + padLeft(subStr, cols.subWidth);
    out.push(padLeft(numLine, width));
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
    line('');

    align(0); bold(true);
    line('TRANSAKSI BERHASIL');
    bold(false);
    twoColumn(receiptData.invoiceNo, receiptData.waktuLabel, width).forEach(line);
    twoColumn('Kasir: ' + (receiptData.kasirUsername || '-'), 'Pelanggan: ' + (receiptData.custName || 'Umum'), width).forEach(line);
    line('');

    var cols = computeColumns(items, width);
    sep('-');
    bold(true);
    line(itemRow('', 'Barang', 'Qty', 'Harga', 'Total', cols));
    bold(false);
    items.forEach(function (it, idx) {
      itemLines(String(idx + 1), it, cols, width).forEach(line);
    });
    sep('-');

    bold(true);
    twoColumn('TOTAL', 'Rp ' + formatRibuan(receiptData.grandTotal), width).forEach(line);
    bold(false);
    twoColumn('DIBAYAR (' + paymentLabel(receiptData.paymentMethod).toUpperCase() + ')', 'Rp ' + formatRibuan(receiptData.amountPaid), width).forEach(line);
    // KEMBALI cuma relevan untuk Tunai -- Transfer selalu 0, baris ini
    // dibuang khusus Transfer supaya tidak menampilkan info kosong.
    if (receiptData.paymentMethod !== 'TRANSFER') {
      twoColumn('KEMBALI', 'Rp ' + formatRibuan(receiptData.kembalian), width).forEach(line);
    }
    line('');

    align(1); bold(true);
    line('TERIMA KASIH');
    bold(false);

    push([LF, LF]);
    if (autoCut) push([GS, 0x56, 0x01]); // potong sebagian -- printer tanpa cutter cukup mengabaikan command ini
    if (cashDrawer) push([ESC, 0x70, 0x00, 0x19, 0xFA]); // kick drawer pin 2

    return new Uint8Array(out);
  }

  window.EscPosEncoder = { build: build, wrapText: wrapText, formatRibuan: formatRibuan };
})();
