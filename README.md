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
├── vendor/                   # React/ReactDOM + font, di-download lokal — lihat § Offline
│   ├── react/
│   └── fonts/
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

Halaman ini sekarang jalan 100% tanpa koneksi internet, dari awal buka
sampai dipakai — cocok untuk toko yang sinyalnya tidak stabil.

## ⚠️ Yang perlu diketahui sebelum dipakai sungguhan di toko

- **"Lupa kata sandi" di layar login masih pura-pura.** Tombol itu cuma
  menunjukkan pesan konfirmasi di layar, tidak benar-benar mengirim apa pun
  atau mereset password di database. Reset password sungguhan: Owner login,
  buka Master Data → Pengguna, edit user itu, isi password baru.
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
