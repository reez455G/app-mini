// Self-check escpos-encoder.js -- jalankan: node test_escpos_encoder.js
// Bukan test suite besar, cuma jaring pengaman minimal untuk logika yang
// tidak bisa dilihat langsung dari hasil cetak fisik (kolom adaptif,
// word-wrap, flag autoCut/cashDrawer, larangan cash-drawer di TRANSFER,
// dan 10 skenario transaksi yang diminta Owner sesudah optimasi layout).
'use strict';
global.window = {};
require('./escpos-encoder.js');
const { build, wrapText, formatRibuan } = global.window.EscPosEncoder;
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

// 4. TEST CASE WAJIB #1: 1 item nama pendek -- harus 1 baris (prioritas utama).
check('1 item nama pendek -> 1 baris, tidak wrap', () => {
  const lines = renderLines(tx({ items: [{ nama: 'Aki GS', qty: 1, unitPrice: 56000, subtotal: 56000 }] }));
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom');
  const row = lines.find((l) => l.trimStart().startsWith('1 Aki GS'));
  assert.ok(row, 'baris item nama pendek tidak ditemukan / ikut wrap');
  assert.ok(row.includes('56.000'), 'qty/harga/subtotal tidak ikut di baris yang sama');
});

// 5. TEST CASE WAJIB #2: 2 item seperti di foto struk asli.
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
  assert.ok(lines.some((l) => l.includes('114.000')), 'TOTAL tidak muncul');
});

// 6. TEST CASE WAJIB #3: 10 item -- kolom No. ikut melebar (2 digit), tetap rapi.
check('10 item', () => {
  const items = [];
  for (let i = 1; i <= 10; i++) items.push({ nama: 'Barang Uji ' + i, qty: i, unitPrice: 10000 * i, subtotal: 10000 * i * i });
  const data = tx({ items, grandTotal: items.reduce((a, it) => a + it.subtotal, 0) });
  const lines = renderLines(data);
  assert.ok(lines.every((l) => l.length <= 48));
  assert.ok(lines.some((l) => l.trimStart().startsWith('10 Barang Uji 10')), 'nomor urut 2 digit (item ke-10) tidak rapi');
});

// 7. TEST CASE WAJIB #4: nama barang panjang -- diusahakan tetap 1 baris kalau muat.
check('nama barang panjang tapi masih muat', () => {
  const lines = renderLines(tx({ items: [{ nama: 'Kampas Rem Depan Beat FI', qty: 1, unitPrice: 75000, subtotal: 75000 }] }));
  assert.ok(lines.every((l) => l.length <= 48));
});

// 8. TEST CASE WAJIB #5: nama SANGAT panjang -- boleh wrap, tapi qty/harga/subtotal
//    TIDAK BOLEH bertabrakan (harus ada baris angka terpisah, rata kanan).
check('nama sangat panjang -> wrap, angka di baris terpisah', () => {
  const longName = 'Ban Luar IRC NR73 80/90-14 Tubeless Ekstra Panjang Sekali Banget Tidak Mungkin Muat Satu Baris';
  const lines = renderLines(tx({ items: [{ nama: longName, qty: 3, unitPrice: 385000, subtotal: 1155000 }] }));
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom saat nama sangat panjang');
  const numLine = lines.find((l) => l.trim().endsWith('1.155.000'));
  assert.ok(numLine, 'baris angka (qty/harga/subtotal) tidak ditemukan');
  assert.ok(!numLine.includes('Ban Luar'), 'baris angka bertabrakan dengan nama barang');
});

// 9. TEST CASE WAJIB #6+#7: harga besar & total besar -- kolom melebar sendiri, tidak kepotong.
check('harga & total besar (jutaan)', () => {
  const data = tx({
    grandTotal: 12500000, amountPaid: 15000000, kembalian: 2500000,
    items: [{ nama: 'Mesin Motor Rebuild', qty: 1, unitPrice: 12500000, subtotal: 12500000 }],
  });
  const lines = renderLines(data);
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris > 48 kolom saat harga jutaan');
  assert.ok(lines.some((l) => l.includes('12.500.000')));
});

// 10. TEST CASE WAJIB #8: TUNAI -- baris KEMBALI harus ada.
check('TUNAI -> baris KEMBALI ada', () => {
  const lines = renderLines(tx({ paymentMethod: 'TUNAI' }));
  assert.ok(lines.some((l) => l.includes('KEMBALI')), 'baris KEMBALI hilang di transaksi TUNAI');
  assert.ok(lines.some((l) => l.includes('DIBAYAR') && l.includes('TUNAI')));
});

// 11. TEST CASE WAJIB #9: TRANSFER -- baris KEMBALI harus DIBUANG (selalu 0, tidak relevan).
check('TRANSFER -> baris KEMBALI hilang', () => {
  const lines = renderLines(tx({ paymentMethod: 'TRANSFER', kembalian: 0 }));
  assert.ok(!lines.some((l) => l.includes('KEMBALI')), 'baris KEMBALI seharusnya tidak ada di TRANSFER');
  assert.ok(lines.some((l) => l.includes('DIBAYAR') && l.includes('TRANSFER')));
});

// 12. TEST CASE WAJIB #10 (reprint): buildReceiptData('server', ...) menghasilkan
//     bentuk yang sama, jadi build() dari data reprint harus lolos uji yang sama --
//     disimulasikan di sini lewat kasirUsername dari sumber "server" (JOIN users).
check('bentuk data reprint (kasirUsername dari server) tetap valid', () => {
  const lines = renderLines(tx({ kasirUsername: 'Budi' }));
  assert.ok(lines.some((l) => l.includes('Budi')), 'kasirUsername reprint tidak muncul');
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

// 15. Data toko/invoice/kasir/pelanggan muncul di teks hasil (sanity end-to-end).
check('sanity end-to-end', () => {
  const lines = renderLines(tx());
  const text = lines.join('\n');
  ['PUTRA JAYA MOTOR', 'INV-260820-0006', 'Dimas', 'Reno', '56.000'].forEach((needle) => {
    assert.ok(text.includes(needle), `teks struk tidak memuat "${needle}"`);
  });
});

console.log(`OK: ${n} skenario escpos-encoder.js lolos`);
