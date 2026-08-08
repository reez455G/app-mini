# App-mini — Sistem Stok & Kasir Toko

Aplikasi web untuk sistem stok dan kasir toko (login, dashboard, transaksi
penjualan, data barang, input pembelian, laporan, retur, dan master data).
Frontend (`Dashboard.dc.html`, awalnya dari Claude Design) sudah tersambung
ke backend PHP + MySQL (`backend/`) — data beneran tersimpan, login
memvalidasi password sungguhan, role Owner/Karyawan ditegakkan di server.

## Struktur file

```
app-mini/
├── index.html              # redirect ke Dashboard.dc.html (biar bisa akses via folder root)
├── Dashboard.dc.html        # halaman utama: markup + logika (React, di dalam <script>)
├── support.js               # runtime yang membaca Dashboard.dc.html dan merender React-nya
├── vendor/                   # React/ReactDOM, font, & library barcode — di-download lokal, lihat § Offline
│   ├── react/
│   ├── fonts/
│   └── barcode/
├── _ds/broadsheet-.../
│   ├── styles.css            # design tokens & style komponen (warna, font, spacing)
│   ├── _ds_bundle.js          # efek visual tambahan (filter cetak/plate)
│   ├── _ds_manifest.json      # metadata design system (tidak dipakai saat runtime)
│   └── readme.md              # dokumentasi design system aslinya
├── backend/                  # PHP + MySQL — API yang dipanggil Dashboard.dc.html
│   └── ...                    # lihat docs/BACKEND.md
└── docs/
    ├── BACKEND.md              # dokumentasi schema database + referensi API lengkap
    ├── Sistem Stok - Complete Business Flow.md
    └── Sistem Stok - Main Process Flow (Excalidraw Guide).md
```

Semua logic aplikasi (state, validasi, kalkulasi) ada di dalam tag
`<script data-dc-script>` di bagian bawah `Dashboard.dc.html`. `support.js`
tidak boleh diedit manual (di-generate dari tool lain) — kalau perlu ubah
tampilan/perilaku, edit `Dashboard.dc.html`.

## Cara jalankan lokal dengan XAMPP

Aplikasi ini butuh Apache **dan** MySQL — bukan cuma file statis lagi, karena
sekarang datanya beneran tersimpan di database.

1. Import `backend/schema.sql` lewat phpMyAdmin (`http://localhost/phpmyadmin`
   → Import), atau `mysql -u root -p < backend/schema.sql`. Ini membuat
   database `app_mini` + data contoh.
2. Copy seluruh folder `app-mini` (termasuk `backend/`) ke `htdocs`, misalnya:
   `C:\xampp\htdocs\app-mini` (Windows) atau `/opt/lampp/htdocs/app-mini` (Linux).
3. Jalankan **Apache dan MySQL** dari XAMPP Control Panel.
4. Buka browser ke `http://localhost/app-mini/`, login dengan akun demo:

   | Username | Password | Role |
   |---|---|---|
   | `Budi` | `owner123` | Owner |
   | `Karyawan1` | `karyawan123` | Karyawan |

   ⚠️ Ganti password akun-akun ini (lewat Master Data → Pengguna) sebelum
   dipakai sungguhan.

Tidak perlu langkah install/build tambahan (tidak ada `npm install`) — cukup
Apache + MySQL bawaan XAMPP. Selalu akses lewat `http://localhost/...`,
jangan dobel klik file-nya langsung (`file:///...`): `support.js` melakukan
`fetch()` ke halaman itu sendiri saat boot (browser memblokir itu untuk
`file://`), dan semua panggilan ke `backend/api/` juga butuh HTTP, bukan
`file://`.

Detail schema database + referensi API lengkap ada di **`docs/BACKEND.md`**.

### Mau data yang lebih banyak buat testing?

`backend/schema.sql` cuma isi 8 barang secukupnya. Kalau mau coba fitur
laporan/dashboard/tren dengan data yang lebih ramai (22 barang, riwayat
pembelian & penjualan sebulan terakhir, beberapa retur), import juga
`backend/dummy_data.sql` (lewat phpMyAdmin, atau `mysql -u root app_mini <
backend/dummy_data.sql`) — aman dijalankan kapan saja di atas database yang
sudah ada, cuma nambah data, tidak menghapus apa pun.

## Offline — tidak butuh internet

Semua yang tadinya dimuat dari CDN sudah di-download dan disimpan lokal di
folder `vendor/`:
- React & ReactDOM (tadinya dari `unpkg.com`) → `vendor/react/`, di-load lewat
  `window.__resources` (hook resmi `support.js` sendiri untuk override sumber
  CDN — bukan hasil edit file `support.js`, lihat `<head>` di `Dashboard.dc.html`).
- Font "Source Serif 4" (tadinya dari `fonts.googleapis.com`) → `vendor/fonts/`,
  di-load lewat `@font-face` di `styles.css`. Cuma subset "latin" yang
  disimpan (huruf Latin standar) — cukup untuk UI berbahasa Indonesia, bukan
  full 6-subset Google Fonts (cyrillic/greek/vietnamese/dst tidak terpakai).
- Library pembuat QR code (`qrcode-generator`) & barcode (`JsBarcode`, buat
  fitur Cetak Barcode) → `vendor/barcode/`. Membaca QR/barcode pakai
  `BarcodeDetector` bawaan browser (bukan library — lihat § Scan di bawah).

Halaman ini sekarang jalan 100% tanpa koneksi internet, dari awal buka
sampai dipakai — cocok untuk toko yang sinyalnya tidak stabil.

## Scan QR/Barcode & Cetak Barcode

Kode barang (`kode`, mis. `BAN-001`) dobel fungsi sebagai isi QR/barcode-nya
— tidak ada field kode-batang terpisah. Ada dua cara scan, dua-duanya
didukung sekaligus:

**1. Kamera** (HP/tablet/webcam) — tombol "Scan QR"/"Scan" yang buka dialog
kamera, baca pakai `BarcodeDetector` bawaan browser.
**2. Alat scanner USB/Bluetooth** — alat ini kerja sebagai "keyboard palsu":
begitu di-scan, dia mengetik kode ke field yang lagi fokus lalu tekan Enter
sendiri. Tidak butuh kamera/izin apa pun — cukup sorot ke field yang
disediakan, scan, otomatis lanjut.

Di kedua modul, hasil dari cara mana pun diproses sama:

- **Data Barang** — tombol kamera "Scan QR" di toolbar, atau sorot ke field
  "Cari Barang" lalu scan dengan alat + Enter. Barang yang sudah terdaftar →
  langsung buka form Ubah. Belum terdaftar → muncul pesan "tambahkan lewat
  Input Pembelian" (Data Barang tidak bisa bikin barang baru — lihat batasan
  di bawah). Ada juga tombol **Cetak Barcode** per baris dan di dalam form
  Ubah, bisa pilih QR atau Barcode 1D (Code128).
- **Input Pembelian** — tombol kamera "Scan" di card Tambah Barang, atau
  sorot ke field **"Scan / Kode Barang"** lalu scan dengan alat + Enter
  (dropdown pemilih barang di situ tidak bisa "ditembak" langsung sama alat
  scanner, makanya ada field teks terpisah). Barang yang sudah ada → langsung
  terpilih. Belum ada → pindah ke mode "Barang Baru" dengan kode sudah
  terisi.
- **Belum ada** — scan di Transaksi Penjualan (buat masukin ke keranjang) dan
  pelacakan history "siapa scan apa kapan".

⚠️ Kamera pakai `BarcodeDetector` bawaan browser — cuma jalan di
**Chrome/Edge Android** (Safari/Firefox belum dukung), dan butuh **HTTPS**
buat diakses dari HP/tablet terpisah (`http://` biasa ditolak browser kecuali
dari `localhost` sendiri). Cara cepat test dari HP tanpa setup SSL permanen
di XAMPP: jalankan `ngrok http 80` (port Apache), buka URL
`https://...ngrok-free.app` yang muncul dari HP. **Alat scanner USB/Bluetooth
tidak punya batasan ini** — jalan di browser mana pun, HTTP biasa, karena
cuma dianggap keyboard oleh browser.

## Layout — tablet & Android

Sidebar berubah jadi drawer geser (tombol hamburger) di bawah ~900px lebar
layar, tabel lebar bisa di-scroll ke samping di dalam card-nya sendiri, dan
form/dialog menyesuaikan lebar layar.

⚠️ Perubahan ini dites secara struktural (kode & binding template cocok),
bukan dicek visual langsung di browser sungguhan — kalau ada yang masih
janggal di HP/tablet Anda, kabari.

## ⚠️ Yang perlu diketahui sebelum dipakai sungguhan di toko

- **"Lupa kata sandi" di layar login masih pura-pura.** Tombol itu cuma
  menunjukkan pesan konfirmasi di layar, tidak benar-benar mengirim apa pun
  atau mereset password di database. Reset password sungguhan: Owner login,
  buka Master Data → Pengguna, edit user itu, isi password baru.
- **Barang baru cuma bisa didaftarkan lewat Input Pembelian**, bukan dari
  Data Barang (yang sekarang edit-only) — sengaja, supaya setiap barang baru
  selalu punya jejak pembelian (suplier, harga beli).
- **Single server, satu database.** Semua kasir/perangkat yang mengakses
  `http://<ip-komputer-server>/app-mini/` dari jaringan yang sama akan
  berbagi data yang sama (ini yang diinginkan) — tapi kalau server (komputer
  yang menjalankan XAMPP) mati, seluruh toko tidak bisa transaksi sampai
  server hidup lagi. Tidak ada mode offline-lalu-sync.
- **Tidak ada rate limiting pada percobaan login.** Cukup untuk pemakaian
  normal satu toko; bukan sesuatu yang perlu dikhawatirkan selama server ini
  tidak diekspos ke internet terbuka.

Selebihnya — data barang, transaksi, laporan, retur, master data — sudah
tersambung penuh ke database lewat `backend/api/`. Detail tiap endpoint ada
di `docs/BACKEND.md`.

## Rencana upload ke repo Git

Semua file di folder ini aman untuk di-commit — tidak ada kredensial database
sungguhan (`backend/config.php` cuma berisi default XAMPP: `root` tanpa
password). Yang perlu diperhatikan:
- Folder `.claude/` (kalau ada) adalah folder kerja Claude Code, bukan bagian
  aplikasi — boleh ditambahkan ke `.gitignore` kalau tidak mau ikut ter-commit.
- `backend/schema.sql` berisi **hash password akun demo** (`owner123` /
  `karyawan123`, sudah di-bcrypt, bukan plaintext) — aman di-commit sebagai
  data contoh, tapi ganti password akun-akun itu sebelum server ini benar-benar
  diakses orang lain.
