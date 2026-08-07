# App-mini — Sistem Stok & Kasir Toko

Prototipe web interaktif untuk sistem stok dan kasir toko (login, dashboard,
transaksi penjualan, data barang, input pembelian, laporan, retur, dan master
data). Dibuat dari Claude Design (`Dashboard.dc.html`) dan sudah bisa langsung
dijalankan sebagai halaman web statis — tidak perlu proses build.

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
├── backend/                  # PHP + MySQL — API, belum tersambung ke frontend di atas
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

1. Copy seluruh folder `app-mini` ke `htdocs`, misalnya:
   `C:\xampp\htdocs\app-mini` (Windows) atau `/opt/lampp/htdocs/app-mini` (Linux).
2. Jalankan Apache dari XAMPP Control Panel (tidak perlu MySQL/PHP — aplikasi ini
   full client-side, tidak ada backend).
3. Buka browser ke `http://localhost/app-mini/`.

Tidak perlu langkah install/build apa pun (tidak ada `npm install`, tidak ada
database) — Apache di XAMPP cukup untuk menyajikan file statis ini. Selalu
akses lewat `http://localhost/...`, jangan dobel klik file-nya langsung
(`file:///...`): `support.js` melakukan `fetch()` ke halaman itu sendiri saat
boot, dan sebagian browser memblokir `fetch()` untuk skema `file://` sehingga
perilakunya jadi tidak konsisten.

## Backend + database (PHP + MySQL)

Ada backend PHP + MySQL di folder `backend/` — schema database dan REST API
untuk auth, master data, pembelian, penjualan, stok, retur, dan semua laporan
di `docs/Sistem Stok - Complete Business Flow.md`. **Belum tersambung ke
`Dashboard.dc.html`** (lihat batasan di bawah) — jalan sendiri, sudah dites
lewat `curl`. Setup dan referensi API lengkap ada di **`docs/BACKEND.md`**.

Ringkas: import `backend/schema.sql` lewat phpMyAdmin, copy folder `backend/`
bersama file-file di atas ke `htdocs` yang sama, akun demo `Budi`/`owner123`
(Owner) dan `Karyawan1`/`karyawan123` (Karyawan) — ganti passwordnya sebelum
dipakai sungguhan.

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

## ⚠️ Batasan penting sebelum dipakai sungguhan di toko

`Dashboard.dc.html` (halaman yang jalan sekarang) dan `backend/` (API yang
sudah dibangun) **belum saling terhubung** — dua hal terpisah:

- **Frontend masih 100% in-memory.** Semua data di `Dashboard.dc.html` (produk,
  transaksi, stok, suplier, dst) cuma tersimpan di memori browser (React
  state). Refresh halaman → balik ke data contoh (`INITIAL_PRODUCTS`, dst).
  Login di layar ini juga tidak memvalidasi kata sandi apa pun.
- **Backend sudah punya penyimpanan data + autentikasi asli** (MySQL, password
  di-hash, role Owner/Karyawan ditegakkan di server) — tapi frontend belum
  memanggilnya. Lihat `docs/BACKEND.md`.

Supaya bisa dipakai transaksi nyata: `Dashboard.dc.html` perlu diubah supaya
tiap aksi (login, tambah barang, transaksi, dst) benar-benar `fetch()` ke API
di `backend/api/`, bukan lagi `setState` in-memory. Itu pekerjaan terpisah
yang belum dikerjakan.

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
