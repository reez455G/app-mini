// Self-check escpos-encoder.js -- jalankan: node test_escpos_encoder.js
// Bukan test suite besar, cuma jaring pengaman minimal untuk logika yang
// tidak bisa dilihat langsung dari hasil cetak fisik (word-wrap, flag
// autoCut/cashDrawer, larangan cash-drawer di TRANSFER, dan skenario
// transaksi yang diminta Owner termasuk baris CUST/nama pelanggan).
'use strict';
global.window = {};
require('./escpos-encoder.js');
const { build, wrapText, formatRibuan, truncate } = global.window.EscPosEncoder;
const assert = require('assert');

// Lucuti command ESC/POS yang dikenal sebelum diubah jadi teks -- byte
// parameter command (mis. 0x45 'E' di "ESC E 1") kebetulan berada di
// rentang ASCII tercetak, jadi konversi naif per-byte salah mengira itu
// teks struk sungguhan.
function bytesToStr(bytes) {
  const a = Array.from(bytes);
  const CMDS = [[0x1B, 0x40, 2], [0x1B, 0x61, 3], [0x1B, 0x45, 3], [0x1D, 0x21, 3], [0x1D, 0x56, 3], [0x1B, 0x70, 5]];
  let out = '';
  for (let i = 0; i < a.length; i++) {
    const cmd = CMDS.find((c) => c[0] === a[i] && c[1] === a[i + 1]);
    if (cmd) { i += cmd[2] - 1; continue; }
    const b = a[i];
    out += b === 0x0A ? '\n' : (b >= 0x20 && b <= 0x7E ? String.fromCharCode(b) : '');
  }
  return out;
}
function containsCmd(bytes, seq) {
  const arr = Array.from(bytes);
  outer: for (let i = 0; i <= arr.length - seq.length; i++) {
    for (let j = 0; j < seq.length; j++) if (arr[i + j] !== seq[j]) continue outer;
    return true;
  }
  return false;
}
function renderLines(receiptData, opts) {
  return bytesToStr(build(receiptData, opts)).split('\n');
}

const toko = { nama: 'PUTRA JAYA MOTOR', alamat: 'Jl. Jati Raya Blok J No. 11, Banyumanik, Semarang', noHp: '08155608055' };
function tx(overrides) {
  return Object.assign({
    invoiceNo: 'INV-260820-0006', waktuLabel: '20/08/2026 21:35:43', custName: 'Reno',
    kasirUsername: 'Dimas', paymentMethod: 'TUNAI', totalQty: 1, grandTotal: 56000,
    amountPaid: 60000, kembalian: 4000, toko,
    items: [{ nama: 'Oli SHELL ADV AX3 20W-50 0,8 L', qty: 1, unitPrice: 56000, subtotal: 56000 }],
  }, overrides);
}

let n = 0;
function check(desc, fn) { fn(); n++; }

// 1. wrapText: tidak ada baris > width, nama sangat panjang tetap terpecah rapi.
check('wrapText batas kolom', () => {
  const lines = wrapText('Aki GS Astra NS40 12V 35Ah MF Kering Sekali Banget Panjang', 48);
  assert.ok(lines.every((l) => l.length <= 48));
  assert.ok(lines.length > 1);
});

// 2. Kata tunggal lebih panjang dari width dipotong paksa, bukan meluber.
check('wrapText kata tunggal super panjang', () => {
  const lines = wrapText('X'.repeat(120), 48);
  assert.ok(lines.every((l) => l.length <= 48));
});

// 3. formatRibuan
check('formatRibuan', () => {
  assert.strictEqual(formatRibuan(13000), '13.000');
  assert.strictEqual(formatRibuan(0), '0');
});

// 4. Struktur header wajib: urutan persis INV / TGL / KSR / CUST, satu field per baris.
check('urutan header INV/TGL/KSR/CUST', () => {
  const lines = renderLines(tx());
  const invIdx = lines.findIndex((l) => l.startsWith('INV : '));
  assert.ok(invIdx > -1, 'baris INV tidak ditemukan');
  assert.ok(lines[invIdx].startsWith('INV : INV-260820-0006'));
  assert.ok(lines[invIdx + 1].startsWith('TGL : 20/08/2026 21:35:43'));
  assert.ok(lines[invIdx + 2].startsWith('KSR : Dimas'));
  assert.ok(lines[invIdx + 3].startsWith('CUST : Reno'));
});

// 5. Item: nama di baris sendiri, "qty x harga" vs subtotal di baris berikutnya.
check('1 item nama pendek -> nama baris sendiri, qty x harga vs subtotal baris berikutnya', () => {
  const lines = renderLines(tx({ items: [{ nama: 'Aki GS', qty: 1, unitPrice: 56000, subtotal: 56000 }] }));
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom');
  const namaIdx = lines.indexOf('Aki GS');
  assert.ok(namaIdx > -1, 'baris nama barang tidak ditemukan persis');
  assert.ok(lines[namaIdx + 1].includes('1 x 56.000') && lines[namaIdx + 1].trim().endsWith('56.000'));
});

// 6. 2 item (kasus foto struk) -- semua field tampil, tidak ada baris kepanjangan.
check('2 item (kasus foto struk)', () => {
  const data = tx({
    grandTotal: 114000, amountPaid: 120000, kembalian: 6000,
    items: [
      { nama: 'Oli SHELL ADV AX3 20W-50 0,8 L', qty: 1, unitPrice: 56000, subtotal: 56000 },
      { nama: 'Oli FDRL Utec 20W-50 0,8 L', qty: 1, unitPrice: 58000, subtotal: 58000 },
    ],
  });
  const lines = renderLines(data);
  assert.ok(lines.every((l) => l.length <= 48));
  assert.ok(lines.some((l) => l.trim() === 'TOTAL' + ' '.repeat(0) || l.includes('TOTAL')), 'TOTAL tidak muncul');
  assert.ok(lines.filter((l) => l.trim().endsWith('114.000')).length >= 2, 'Subtotal & TOTAL (sama-sama grandTotal) tidak muncul dua kali');
});

// 7. 10 item -- semua nama & angka tetap muncul, tidak ada baris > 48 kolom.
check('10 item', () => {
  const items = [];
  for (let i = 1; i <= 10; i++) items.push({ nama: 'Barang Uji ' + i, qty: i, unitPrice: 10000 * i, subtotal: 10000 * i * i });
  const data = tx({ items, grandTotal: items.reduce((a, it) => a + it.subtotal, 0) });
  const lines = renderLines(data);
  assert.ok(lines.every((l) => l.length <= 48));
  assert.ok(lines.includes('Barang Uji 10'), 'nama item ke-10 tidak ditemukan persis');
});

// 8. Nama barang panjang tapi masih muat dalam lebar penuh -> tetap 1 baris.
check('nama barang panjang tapi masih muat', () => {
  const lines = renderLines(tx({ items: [{ nama: 'Kampas Rem Depan Beat FI', qty: 1, unitPrice: 75000, subtotal: 75000 }] }));
  assert.ok(lines.every((l) => l.length <= 48));
  assert.ok(lines.includes('Kampas Rem Depan Beat FI'));
});

// 9. Nama SANGAT panjang -> boleh wrap ke lebih dari 1 baris nama, tapi baris
//    qty/harga vs subtotal TIDAK BOLEH bertabrakan dengan teks nama.
check('nama sangat panjang -> wrap rapi, angka tidak bertabrakan', () => {
  const longName = 'Ban Luar IRC NR73 80/90-14 Tubeless Ekstra Panjang Sekali Banget Tidak Mungkin Muat Satu Baris';
  const lines = renderLines(tx({ items: [{ nama: longName, qty: 3, unitPrice: 385000, subtotal: 1155000 }] }));
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom saat nama sangat panjang');
  const numLine = lines.find((l) => l.trim().endsWith('1.155.000'));
  assert.ok(numLine, 'baris angka (qty x harga vs subtotal) tidak ditemukan');
  assert.ok(!numLine.includes('Ban Luar'), 'baris angka bertabrakan dengan teks nama barang');
});

// 10. Harga & total besar (jutaan) -- tidak ada baris kepotong.
check('harga & total besar (jutaan)', () => {
  const data = tx({
    grandTotal: 12500000, amountPaid: 15000000, kembalian: 2500000,
    items: [{ nama: 'Mesin Motor Rebuild', qty: 1, unitPrice: 12500000, subtotal: 12500000 }],
  });
  const lines = renderLines(data);
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom saat harga jutaan');
  assert.ok(lines.some((l) => l.includes('12.500.000')));
});

// 11. TUNAI -> baris "Kembali" harus ada.
check('TUNAI -> baris Kembali ada', () => {
  const lines = renderLines(tx({ paymentMethod: 'TUNAI' }));
  assert.ok(lines.some((l) => l.startsWith('Kembali')), 'baris Kembali hilang di transaksi TUNAI');
  assert.ok(lines.some((l) => l.startsWith('Metode') && l.includes('Tunai')));
});

// 12. TRANSFER -> baris "Kembali" harus DIBUANG (selalu 0, tidak relevan).
check('TRANSFER -> baris Kembali hilang', () => {
  const lines = renderLines(tx({ paymentMethod: 'TRANSFER', kembalian: 0 }));
  assert.ok(!lines.some((l) => l.startsWith('Kembali')), 'baris Kembali seharusnya tidak ada di TRANSFER');
  assert.ok(lines.some((l) => l.startsWith('Metode') && l.includes('Transfer')));
});

// 13. autoCut: ON -> command GS V ada; OFF -> tidak ada.
check('autoCut on/off', () => {
  const withCut = build(tx(), { autoCut: true });
  const noCut = build(tx(), { autoCut: false });
  assert.ok(containsCmd(withCut, [0x1D, 0x56]));
  assert.ok(!containsCmd(noCut, [0x1D, 0x56]));
});

// 14. cashDrawer: ON + TUNAI -> ada; TIDAK PERNAH nyala di TRANSFER walau diminta ON.
check('cashDrawer cuma nyala di TUNAI', () => {
  const withDrawer = build(tx({ paymentMethod: 'TUNAI' }), { cashDrawer: true });
  const transferDrawer = build(tx({ paymentMethod: 'TRANSFER' }), { cashDrawer: true });
  assert.ok(containsCmd(withDrawer, [0x1B, 0x70]));
  assert.ok(!containsCmd(transferDrawer, [0x1B, 0x70]), 'cashDrawer TIDAK BOLEH nyala di TRANSFER walau opsi ON');
});

// 15. Data toko/invoice/kasir/pelanggan muncul di teks hasil (sanity end-to-end),
//     termasuk footer TERIMA KASIH + SELAMAT BERBELANJA.
check('sanity end-to-end + footer lengkap', () => {
  const lines = renderLines(tx());
  const text = lines.join('\n');
  ['PUTRA JAYA MOTOR', 'INV-260820-0006', 'Dimas', 'Reno', '56.000', 'TERIMA KASIH', 'SELAMAT BERBELANJA'].forEach((needle) => {
    assert.ok(text.includes(needle), `teks struk tidak memuat "${needle}"`);
  });
});

// 16. TEST CASE WAJIB: pelanggan "Reno" -> baris "CUST : Reno" muncul persis
//     setelah baris "KSR : ...".
check('baris CUST muncul setelah KSR (pelanggan Reno)', () => {
  const lines = renderLines(tx({ custName: 'Reno', kasirUsername: 'Budi' }));
  const ksrIdx = lines.findIndex((l) => l.startsWith('KSR : Budi'));
  assert.ok(ksrIdx > -1, 'baris KSR tidak ditemukan');
  assert.strictEqual(lines[ksrIdx + 1], 'CUST : Reno', 'baris CUST tidak persis setelah KSR');
});

// 17. TEST CASE WAJIB: pelanggan lain (nama beda) -> ikut berubah, bukan hardcode.
check('pelanggan lain (bukan hardcode)', () => {
  const lines = renderLines(tx({ custName: 'Haryo Susilo' }));
  assert.ok(lines.includes('CUST : Haryo Susilo'));
});

// 18. TEST CASE WAJIB: transaksi tanpa pelanggan -> "CUST : Umum".
check('tanpa pelanggan -> CUST : Umum', () => {
  const linesEmpty = renderLines(tx({ custName: '' }));
  const linesUndef = renderLines(tx({ custName: undefined }));
  assert.ok(linesEmpty.includes('CUST : Umum'), 'custName kosong string harusnya jadi Umum');
  assert.ok(linesUndef.includes('CUST : Umum'), 'custName undefined harusnya jadi Umum');
});

// 19. TEST CASE WAJIB: nama pelanggan sangat panjang -> dipotong + "...",
//     TIDAK PERNAH bikin baris > 48 kolom, TIDAK wrap ke baris kedua.
check('nama pelanggan sangat panjang -> truncate, tetap 1 baris, <=48 kolom', () => {
  const longName = 'Miftahudin Santoso Wijayakusuma Nugroho Pratama';
  const lines = renderLines(tx({ custName: longName }));
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom akibat nama pelanggan panjang');
  const pelLine = lines.find((l) => l.startsWith('CUST : '));
  assert.ok(pelLine, 'baris CUST tidak ditemukan');
  assert.ok(pelLine.endsWith('...'), 'nama panjang seharusnya dipotong dengan "..."');
  const pelLinesCount = lines.filter((l) => l.startsWith('CUST')).length;
  assert.strictEqual(pelLinesCount, 1, 'CUST tidak boleh wrap jadi lebih dari 1 baris');
});

// 20. TEST CASE WAJIB (reprint): custName dari data SERVER (histori transaksi),
//     bukan dari kasir yang lagi login sekarang -- disimulasikan lewat
//     kombinasi kasirUsername beda dengan custName, keduanya harus tetap
//     apa adanya dari receiptData yang dikirim (bukan di-override encoder).
check('reprint: CUST tetap nama pelanggan SAAT transaksi dibuat', () => {
  // Skenario dari spec: transaksi dibuat kasir Budi utk pelanggan Reno,
  // lalu user yang login SEKARANG berganti jadi Dimas -- tapi reprint
  // (buildReceiptData('server', ...) di Dashboard.dc.html) selalu mengirim
  // kasirUsername & custName dari kolom historis (created_by JOIN users,
  // cust_name), TIDAK PERNAH dari state login. Di level encoder ini
  // cukup dibuktikan: apa pun yang dikirim di receiptData itulah yang
  // tercetak apa adanya, tidak diam-diam diganti nama lain.
  const lines = renderLines(tx({ kasirUsername: 'Budi', custName: 'Reno' }));
  assert.ok(lines.includes('KSR : Budi'));
  assert.ok(lines.includes('CUST : Reno'));
  assert.ok(!lines.some((l) => l.includes('Dimas')), 'nama kasir yang login sekarang TIDAK BOLEH ikut tercetak');
});

// 21. truncate(): sanity langsung ke fungsinya.
check('truncate() sanity', () => {
  assert.strictEqual(truncate('Reno', 42), 'Reno');
  assert.strictEqual(truncate('X'.repeat(50), 42).length, 42);
  assert.ok(truncate('X'.repeat(50), 42).endsWith('...'));
});

// 22. Border "====" (3x: bawah kop, bawah blok Metode/Bayar/Kembali, bawah
//     footer) dan "----" (3x: bawah CUST, bawah item, bawah TOTAL) -- sesuai
//     format persis yang diminta Owner.
check('border ==== dan ---- ada di posisi yang benar', () => {
  const lines = renderLines(tx());
  const eqCount = lines.filter((l) => l.length > 0 && [...l].every((c) => c === '=')).length;
  const dashCount = lines.filter((l) => l.length > 0 && [...l].every((c) => c === '-')).length;
  assert.strictEqual(eqCount, 3, 'harus ada persis 3 baris "===="');
  assert.strictEqual(dashCount, 3, 'harus ada persis 3 baris "----"');
});

console.log(`OK: ${n} skenario escpos-encoder.js lolos`);
