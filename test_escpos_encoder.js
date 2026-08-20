// Self-check escpos-encoder.js -- jalankan: node test_escpos_encoder.js
// Bukan test suite besar, cuma jaring pengaman minimal untuk logika yang
// tidak bisa dilihat langsung dari hasil cetak fisik (word-wrap, flag
// autoCut/cashDrawer, dan larangan cash-drawer nyala di TRANSFER).
'use strict';
global.window = {};
require('./escpos-encoder.js');
const { build, wrapText, formatRibuan } = global.window.EscPosEncoder;
const assert = require('assert');

// Lucuti command ESC/POS yang dikenal (ESC @, ESC a n, ESC E n, GS ! n,
// GS V m, ESC p m t1 t2) sebelum diubah jadi teks -- byte parameter command
// (mis. 0x45 'E' di "ESC E 1") kebetulan berada di rentang ASCII tercetak,
// jadi konversi naif per-byte akan salah mengira itu teks struk sungguhan.
function bytesToStr(bytes) {
  const a = Array.from(bytes);
  // [byte1, byte2, totalLength] untuk tiap command yang dipakai encoder.
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

const sample = {
  invoiceNo: 'INV-20260820-0001', waktuLabel: '20-08-2026 18:42', custName: 'Umum',
  kasirUsername: 'MIFTAH', paymentMethod: 'TUNAI', totalQty: 3, grandTotal: 13000,
  amountPaid: 20000, kembalian: 7000, toko: { nama: 'PUTRA JAYA MOTOR', alamat: '-', noHp: '-' },
  items: [
    { nama: 'Indomie Goreng', qty: 2, unitPrice: 3500, subtotal: 7000 },
    { nama: 'Aqua 600ml', qty: 2, unitPrice: 3000, subtotal: 6000 },
  ],
};

// 1. wrapText: tidak ada baris > width, nama sangat panjang tetap terpecah rapi.
{
  const lines = wrapText('Aki GS Astra NS40 12V 35Ah MF Kering Sekali Banget Panjang', 48);
  assert.ok(lines.every((l) => l.length <= 48), 'ada baris wrap > 48 kolom');
  assert.ok(lines.length > 1, 'nama panjang harusnya terpecah lebih dari 1 baris');
}

// 2. Kata tunggal lebih panjang dari width dipotong paksa, bukan meluber.
{
  const lines = wrapText('X'.repeat(120), 48);
  assert.ok(lines.every((l) => l.length <= 48), 'kata tunggal super panjang meluber dari 48 kolom');
}

// 3. formatRibuan
{
  assert.strictEqual(formatRibuan(13000), '13.000');
  assert.strictEqual(formatRibuan(0), '0');
}

// 4. Nama barang sangat panjang di build() tidak merusak baris qty/harga:
//    baris qty/harga tetap ada persis setelah baris-baris nama, dan setiap
//    baris hasil <= 48 karakter.
{
  const longItem = { ...sample, items: [{ nama: 'Ban Luar IRC NR73 80/90-14 Tubeless Ekstra Panjang Sekali', qty: 1, unitPrice: 385000, subtotal: 385000 }] };
  const text = bytesToStr(build(longItem, { width: 48 }));
  const bodyLines = text.split('\n');
  assert.ok(bodyLines.every((l) => l.length <= 48), 'ada baris struk > 48 kolom saat nama barang panjang');
  const qtyLineIdx = bodyLines.findIndex((l) => l.trim().startsWith('1 x'));
  assert.ok(qtyLineIdx > -1, 'baris qty x harga tidak ditemukan');
  assert.ok(bodyLines[qtyLineIdx].includes('385.000'), 'subtotal tidak ikut di baris qty');
}

// 5. autoCut: ON -> command GS V ada; OFF -> tidak ada, dan build() tidak error.
{
  const withCut = build(sample, { autoCut: true });
  const noCut = build(sample, { autoCut: false });
  assert.ok(containsCmd(withCut, [0x1D, 0x56]), 'autoCut:true harusnya kirim GS V');
  assert.ok(!containsCmd(noCut, [0x1D, 0x56]), 'autoCut:false tidak boleh kirim command cut');
}

// 6. cashDrawer: ON + TUNAI -> command ESC p ada.
{
  const withDrawer = build(sample, { cashDrawer: true });
  assert.ok(containsCmd(withDrawer, [0x1B, 0x70]), 'cashDrawer:true + TUNAI harusnya kirim ESC p');
}

// 7. cashDrawer TIDAK PERNAH nyala di TRANSFER walau opsi diminta ON.
{
  const transferTx = { ...sample, paymentMethod: 'TRANSFER' };
  const withDrawer = build(transferTx, { cashDrawer: true });
  assert.ok(!containsCmd(withDrawer, [0x1B, 0x70]), 'cashDrawer TIDAK BOLEH nyala di TRANSFER walau opsi ON');
}

// 8. Data toko/invoice/kasir benar-benar muncul di teks hasil (sanity end-to-end).
{
  const text = bytesToStr(build(sample, {}));
  ['PUTRA JAYA MOTOR', 'INV-20260820-0001', 'MIFTAH', '13.000', '7.000'].forEach((needle) => {
    assert.ok(text.includes(needle), `teks struk tidak memuat "${needle}"`);
  });
}

console.log('OK: 8 skenario escpos-encoder.js lolos');
