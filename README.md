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
└── _ds/broadsheet-.../
    ├── styles.css            # design tokens & style komponen (warna, font, spacing)
    ├── _ds_bundle.js          # efek visual tambahan (filter cetak/plate)
    ├── _ds_manifest.json      # metadata design system (tidak dipakai saat runtime)
    └── readme.md              # dokumentasi design system aslinya
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

## Kebutuhan koneksi internet

Halaman ini memuat beberapa file dari CDN saat dibuka:
- React & ReactDOM dari `unpkg.com`
- Font "Source Serif 4" dari `fonts.googleapis.com`

Jadi komputer/toko yang menjalankannya **butuh koneksi internet aktif**
walau server web-nya sendiri jalan lokal. Kalau nanti perlu jalan tanpa
internet sama sekali, file-file CDN itu perlu di-download dan disimpan
lokal (belum dilakukan di versi ini).

## ⚠️ Batasan penting sebelum dipakai sungguhan di toko

- **Tidak ada penyimpanan data.** Semua data (produk, transaksi, stok,
  suplier, dst) hanya tersimpan di memori browser (React state). Refresh
  halaman atau tutup tab → semua data kembali ke data contoh awal yang
  ditulis di dalam `Dashboard.dc.html` (`INITIAL_PRODUCTS`, dst). Ini masih
  prototipe tampilan/alur, belum tersambung ke database.
- **Tidak ada autentikasi asli.** Login hanya mengecek nama pengguna diisi
  atau tidak; kata sandi tidak divalidasi dan peran (Owner/Karyawan) dipilih
  bebas dari layar login. Siapa pun yang membuka halaman bisa memilih jadi
  Owner.
- **Single-user.** Tidak ada sinkronisasi antar perangkat/kasir — dua orang
  yang membuka halaman ini di dua komputer punya data masing-masing yang
  tidak nyambung.

Kalau tujuannya sudah mau dipakai untuk transaksi nyata di toko, tiga hal di
atas (penyimpanan data, login sungguhan, multi-user) perlu dibangun dulu —
bukan sekadar "deploy", tapi menambah backend + database.

## Rencana upload ke repo Git

Semua file di folder ini aman untuk di-commit (tidak ada secret/kredensial).
Yang perlu diperhatikan:
- Folder `.claude/` (kalau ada) adalah folder kerja Claude Code, bukan bagian
  aplikasi — boleh ditambahkan ke `.gitignore` kalau tidak mau ikut ter-commit.
- Tidak ada `.env` atau kredensial apa pun untuk disembunyikan — aplikasi ini
  tidak punya backend.
