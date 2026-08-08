# Backend + Database — App-mini

Backend PHP + MySQL untuk App-mini, dibangun dari alur bisnis yang didokumentasikan
di `docs/Sistem Stok - Complete Business Flow.md` dan `docs/Sistem Stok - Main
Process Flow (Excalidraw Guide).md`. Sudah ditest end-to-end (login, pembelian,
penjualan, retur, semua laporan) sebelum dokumen ini ditulis — lihat § Cara test
di bawah kalau mau mengulang.

**Status: sudah tersambung ke `Dashboard.dc.html`.** Setiap aksi yang mengubah
data (login, CRUD master data/barang, transaksi penjualan/pembelian, retur)
dan semua laporan memanggil endpoint di dokumen ini lewat `fetch()` — lihat
helper `api()` di bagian atas `<script data-dc-script>` di `Dashboard.dc.html`.
Cart/keranjang (Penjualan, Pembelian, Retur) tetap murni di sisi client
selagi barang ditambah satu-satu; API dipanggil sekali saat transaksi
benar-benar disimpan.

---

## 1. Setup di XAMPP

1. **Import database.** Buka phpMyAdmin (`http://localhost/phpmyadmin`) →
   Import → pilih `backend/schema.sql`. File ini membuat database `app_mini`,
   semua tabel, dan mengisi data contoh (sama seperti data contoh yang sudah
   ada di `Dashboard.dc.html`).
   Atau lewat terminal: `mysql -u root -p < backend/schema.sql`.
2. **Cek kredensial.** `backend/config.php` sudah diset ke default XAMPP
   (`root`, tanpa password, host `localhost`). Kalau instalasi MySQL Anda beda,
   ubah 4 baris di file itu.
3. **Copy folder** `app-mini` (termasuk `backend/`) ke `htdocs`.
4. **Test.** Buka `http://localhost/app-mini/backend/api/kategori.php` — harus
   muncul `{"ok":false,"error":"Belum login."}` (401). Itu tandanya PHP dan
   koneksi database jalan; endpoint memang menolak karena belum login.

### Akun demo (dari `schema.sql`)

| Username | Password | Role |
|---|---|---|
| `Budi` | `owner123` | Owner |
| `Karyawan1` | `karyawan123` | Karyawan |

⚠️ **Ganti password ini** (lewat `PUT /api/users.php?id=`) sebelum dipakai
sungguhan — ini password demo yang ditulis apa adanya di dokumen ini.

---

## 2. Struktur

```
backend/
├── schema.sql       # semua CREATE TABLE + data contoh
├── config.php        # kredensial DB — satu-satunya file yang perlu disesuaikan
├── db.php            # koneksi PDO + helper nomor dokumen (next_kode, next_doc_no)
├── auth.php           # session + require_login()/require_owner()
├── response.php        # json_ok()/json_error()/body() — bentuk respons seragam
└── api/
    ├── auth/login.php, logout.php, me.php
    ├── kategori.php, suplier.php, pelanggan.php, users.php    # master data
    ├── barang.php                                              # produk
    ├── pembelian.php, penjualan.php                            # transaksi
    ├── retur_penjualan.php, retur_pembelian.php
    └── laporan/
        stok.php, pembelian.php, penjualan.php, laba.php, retur.php, top_produk.php, global.php
```

Tidak ada framework, tidak ada `composer install`, tidak ada build step — plain
PHP (PDO + session), setiap file `api/*.php` diakses langsung sebagai satu
endpoint. Ini sengaja: aplikasi satu toko, satu server lokal, tidak butuh
router/ORM/dependency manager.

---

## 3. Desain skema

12 tabel: `users`, `kategori`, `suplier`, `pelanggan`, `barang`, `pembelian` +
`pembelian_item`, `penjualan` + `penjualan_item`, `retur_penjualan` +
`retur_penjualan_item`, `retur_pembelian` + `retur_pembelian_item`.

Beberapa keputusan desain yang menyimpang dari draf awal di dokumen bisnis, dan
alasannya:

- **`barang` menyimpan SATU harga beli terkini** (`harga_faktur`/`harga_netto`),
  bukan satu baris per suplier. Dokumen bisnis menyebut "multi-supplier
  pricing" sebagai fitur, tapi prototipe frontend (`Dashboard.dc.html`) hanya
  menyimpan satu harga beli per produk — ditimpa tiap kali ada pembelian baru
  (lihat `finalizePembelian()`). Skema ini mengikuti perilaku frontend supaya
  data konsisten kalau nanti disambungkan.
  **Perbandingan harga antar-suplier** (fitur yang sama, caranya beda) tetap
  ada: `GET /api/barang.php?id=X&history=1` mengambil riwayat harga per
  suplier langsung dari `pembelian_item` + `pembelian` — bukan tabel terpisah
  yang harus disinkronkan manual, sumber datanya cuma satu (transaksi
  pembelian asli).
- **Tidak ada tabel `laporan_*`.** Semua laporan adalah query `SELECT`/`JOIN`/
  `GROUP BY` langsung ke `pembelian`/`penjualan`/`retur_*` saat diminta — tidak
  ada data teragregasi yang bisa basi.
- **Laba dihitung dari harga beli TERKINI**, bukan harga beli saat transaksi
  terjadi. Ini juga mengikuti frontend (`costOfLine()` di `Dashboard.dc.html`
  pakai `stokProducts` saat ini, bukan snapshot). Konsekuensinya: angka laba di
  laporan bisa berubah kalau harga beli produk itu di-update setelah
  transaksi lama terjadi. Kalau nanti butuh laba historis yang akurat (harga
  beli SAAT itu), field `harga_faktur`/`harga_netto` di `pembelian_item` sudah
  ada — tinggal ganti query laporan laba untuk pakai pembelian_item terakhir
  sebelum tanggal transaksi, bukan `barang.harga_netto` saat ini.
- **Password sungguhan ditambahkan** meski frontend saat ini tidak
  memvalidasi password sama sekali (`doLogin()` cuma cek username diisi).
  Backend menambahkan `password_hash`/`password_verify` (bcrypt) — bukan
  penyimpangan dari dokumen bisnis, tapi kebutuhan dasar yang tidak bisa
  dilewati untuk sistem yang benar-benar dipakai.

---

## 4. Autentikasi & kontrol akses

Session-based (`$_SESSION`, cookie PHP native) — bukan token/JWT, karena
frontend dan backend akan berjalan di origin yang sama (satu server Apache).
Login lewat `POST /api/auth/login.php`, lalu setiap request berikutnya otomatis
terautentikasi lewat cookie sesi (`credentials: 'include'` di frontend nanti).

Matriks akses (persis dari `docs/Sistem Stok - Complete Business Flow.md` §
ACCESS CONTROL MATRIX):

| Endpoint | Owner | Karyawan |
|---|:---:|:---:|
| `auth/*` | ✅ | ✅ |
| `kategori.php`, `suplier.php`, `pelanggan.php` — **GET** | ✅ | ✅ |
| `kategori.php`, `suplier.php`, `pelanggan.php`, `users.php` — tulis | ✅ | ❌ |
| `barang.php` — **GET** | ✅ (semua field) | ✅ (tanpa harga beli) |
| `barang.php` — tulis, `?history=1` | ✅ | ❌ |
| `pembelian.php` (semua method) | ✅ | ❌ |
| `penjualan.php` (semua method) | ✅ | ✅ |
| `retur_penjualan.php` | ✅ | ✅ |
| `retur_pembelian.php` | ✅ | ❌ |
| `laporan/stok.php`, `laporan/penjualan.php` | ✅ | ✅ |
| `laporan/top_produk.php` | ✅ (+ `profit`) | ✅ (tanpa `profit`) |
| `laporan/pembelian.php`, `laporan/laba.php`, `laporan/retur.php`, `laporan/global.php`, `laporan/tren_penjualan.php` | ✅ | ❌ |

Yang ditolak dapat `401` (belum login) atau `403` (login tapi role tidak
cukup), body `{"ok":false,"error":"..."}`.

---

## 5. Format respons

Semua endpoint balas JSON dengan bentuk yang sama:

```json
// sukses
{ "ok": true, "data": { ... } }
// gagal
{ "ok": false, "error": "pesan dalam Bahasa Indonesia, siap ditampilkan ke user" }
```

Kode HTTP: `200` sukses baca, `201` sukses buat data baru, `400` input tidak
valid, `401` belum login, `403` role tidak cukup, `404` tidak ditemukan, `405`
method salah, `409` konflik (kode/username sudah dipakai).

Body request dikirim sebagai JSON (`Content-Type` apa pun — endpoint baca raw
`php://input`, bukan `$_POST`).

---

## 6. Referensi API

### Auth

```
POST /api/auth/login.php   {username, password} → {id, username, role}
POST /api/auth/logout.php  → {loggedOut: true}
GET  /api/auth/me.php      → {id, username, role} atau null
```

### Master data — pola sama untuk kategori/suplier/pelanggan

```
GET    /api/suplier.php                 → [{id, kode, nama, alamat, no_hp}, ...]
POST   /api/suplier.php   {nama, alamat?, noHp?}     → 201, kode auto (SUP-0001, ...)
PUT    /api/suplier.php?id=1   {nama, alamat?, noHp?}
DELETE /api/suplier.php?id=1
```
`pelanggan.php` sama persis (kode `CUST-0001`, ...). `kategori.php` cuma
punya field `nama` (dipakai sebagai nama sekaligus identitas, huruf besar
otomatis — `BAN`, `OLI`, dst).

```
GET    /api/users.php                          → [{id, username, role}, ...]   (Owner only)
POST   /api/users.php   {username, password, role}   → 201
PUT    /api/users.php?id=1  {username?, password?, role?}
DELETE /api/users.php?id=1   — tidak bisa hapus akun sendiri
```

### Barang (produk)

```
GET /api/barang.php?kategori=BAN&search=ban
```
Karyawan dapat: `id, kode, nama, kategori, suplier, stok, harga_ecer,
harga_bengkel, harga_grosir, status` — suplier bukan data finansial (dipakai
filter di Laporan Stok, yang bisa diakses Karyawan). Owner dapat tambahan:
`harga_faktur, harga_netto, price_list_basis, min_stok, pending_setup`.
`status` dihitung otomatis: `stok <= min_stok/2` → `Kritis`, `<= min_stok` →
`Menipis`, selain itu `Aman`.

```
GET /api/barang.php?id=3&history=1   (Owner) → riwayat harga per suplier dari pembelian_item
POST   /api/barang.php   {kode, nama, kategori, suplier?, harga_faktur?, harga_netto?, price_list_basis?, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok}
PUT    /api/barang.php?id=3   (field sama, kode tidak bisa diubah)
DELETE /api/barang.php?id=3
```
`POST` masih ada & jalan (endpoint-nya tidak dihapus), tapi **frontend sudah
tidak memanggilnya** — Data Barang di `Dashboard.dc.html` sekarang edit-only
(`saveStokForm` selalu `PUT`). Barang baru cuma masuk lewat `POST
/api/pembelian.php` (§ Pembelian di bawah), yang membuat baris `barang` baru
sendiri kalau `kode`-nya belum ada.

### Pembelian (Owner only)

```
GET  /api/pembelian.php                 → daftar riwayat
GET  /api/pembelian.php?id=1            → detail + items
POST /api/pembelian.php
{
  "suplier": "CV Sumber Ban Jaya",
  "payment_type": "CASH",           // atau "TOP"
  "jatuh_tempo": "2026-09-01",      // wajib kalau TOP
  "items": [
    {"kode":"BAN-001","harga_faktur":710000,"harga_netto":690000,"qty":5},
    {"kode":"BAN-099","nama":"Ban Baru","kategori":"BAN","harga_faktur":100000,"harga_netto":90000,"qty":12}
  ]
}
```
Kalau `kode` sudah ada di `barang`: stok ditambah, `harga_faktur`/
`harga_netto` produk ditimpa dengan nilai pembelian ini. Kalau belum ada:
barang baru dibuat dengan `pending_setup=true` (harga jual ECER/BENGKEL/GROSIR
= 0, harus dilengkapi lewat `PUT /api/barang.php`) — `nama` dan `kategori`
wajib diisi untuk baris ini. `no_faktur` dibuat otomatis (`PB-YYMMDD-0001`).

### Penjualan (Owner & Karyawan)

```
GET  /api/penjualan.php                 → daftar riwayat
GET  /api/penjualan.php?id=1            → detail + items
POST /api/penjualan.php
{
  "cust_name": "Hendra",
  "payment_method": "TUNAI",       // atau "TRANSFER"
  "amount_paid": 400000,
  "items": [{"kode":"OLI-002","qty":8}]
}
```
Tier harga (ECER 1-5 / BENGKEL 6-10 / GROSIR 11-100) dipilih otomatis dari
`qty`. Stok divalidasi (request ditolak dengan `400` kalau stok kurang) lalu
dikurangi dalam satu transaksi database dengan row lock (`SELECT ... FOR
UPDATE`) — dua kasir menjual barang yang sama di saat bersamaan tidak akan
sama-sama lolos validasi stok. `invoice_no` dibuat otomatis
(`INV-YYMMDD-0001`).

### Retur

```
POST /api/retur_penjualan.php   (Owner & Karyawan) — restock (+stok)
{
  "customer_name": "Hendra", "original_invoice_no": "INV-260806-0001",
  "tanggal": "2026-08-06",
  "items": [{"kode":"OLI-002","qty":2,"reason":"Rusak"}]
}

POST /api/retur_pembelian.php   (Owner only) — kirim balik ke suplier (-stok, clamp ke 0)
{
  "suplier": "CV Sumber Ban Jaya", "original_invoice_no": "PB-260806-0001",
  "tanggal": "2026-08-06",
  "items": [{"kode":"BAN-001","qty":1,"reason":"Cacat"}]
}
```
`GET` di kedua endpoint mengembalikan daftar riwayat. `no_retur` otomatis
(`RJ-YYMMDD-0001` / `RB-YYMMDD-0001`).

### Laporan

Semua laporan pakai query string `from`/`to` (format `YYYY-MM-DD`, opsional —
default dari awal waktu s/d hari ini).

```
GET /api/laporan/stok.php?kategori=&suplier=&status=&search=      (Owner & Karyawan)
GET /api/laporan/penjualan.php?from=&to=                          (Owner & Karyawan)
GET /api/laporan/top_produk.php?from=&to=&limit=5                  (Owner & Karyawan — profit disembunyikan utk Karyawan)
GET /api/laporan/pembelian.php?from=&to=                          (Owner only)
GET /api/laporan/laba.php?from=&to=                                (Owner only)
GET /api/laporan/retur.php?from=&to=                                (Owner only)
GET /api/laporan/global.php?from=&to=                               (Owner only)
GET /api/laporan/tren_penjualan.php?days=14                        (Owner only) → [{tanggal, total}, ...] per hari, zero-filled
```
Bentuk respons masing-masing ada di kode sumbernya (`api/laporan/*.php`) —
tiap file pendek (30-60 baris), lebih cepat dibaca langsung daripada
didokumentasikan ulang di sini field-per-field.

---

## 7. Cara test cepat

```bash
BASE=http://localhost/app-mini/backend
COOKIE=/tmp/cookies.txt

curl -s -c $COOKIE -X POST $BASE/api/auth/login.php \
  -d '{"username":"Budi","password":"owner123"}'

curl -s -b $COOKIE $BASE/api/barang.php | python3 -m json.tool

curl -s -b $COOKIE -X POST $BASE/api/penjualan.php -d '{
  "cust_name":"Test","payment_method":"TUNAI","amount_paid":400000,
  "items":[{"kode":"OLI-002","qty":8}]
}'
```

Semua endpoint di atas sudah dites dengan alur ini (login gagal/berhasil,
pembelian barang lama & baru, penjualan dengan tier otomatis, penjualan
ditolak karena stok kurang, retur penjualan & pembelian, akses karyawan
ditolak di endpoint Owner-only, semua 8 laporan) — baik langsung lewat curl
maupun ditelusuri satu-satu terhadap setiap pemanggilan `api()` di
`Dashboard.dc.html` sebelum frontend dan backend dianggap tersambung.

---

## 8. Pola integrasi di sisi frontend

Untuk siapa pun yang nanti mengubah `Dashboard.dc.html`, polanya konsisten di
seluruh file — cari bagian ini di dalam `<script data-dc-script>`:

- **`api(method, path, body)`** — satu helper `fetch()` terpusat (di dekat
  atas file). Semua panggilan ke `backend/api/` lewat sini; melempar `Error`
  kalau `{ok:false}`, jadi cukup `.catch((e) => ...e.message)`.
- **`normalizeBarang()`/`normalizeSuplier()`/dst** — API balas field
  snake_case (`harga_ecer`, `min_stok`), tapi seluruh state/template file ini
  sudah lama pakai camelCase (`p.ecer`, `p.minStok`). Fungsi-fungsi ini
  menjembatani sekali di titik masuk data, supaya kode konsumsinya (semua
  `*Vals()`) tidak perlu diubah.
- **`refreshBarang()`/`refreshPembelianHistory()`/dst** — dipanggil ulang
  setelah setiap POST/PUT/DELETE yang mengubah data itu (bukan optimistic
  update client-side) — sumber kebenaran selalu balik ke server.
- **`loadLaporanTab()`/`loadDashboardWidgets()`** — laporan tidak dihitung di
  client sama sekali lagi; ini cuma fetch + simpan hasil server ke
  `state.remoteLaporan*` / `state.dashboard*`, dipicu saat tab/modul dibuka
  atau filter tanggal berubah.
- **`resolveStokScan()`/`resolvePembelianScan()`** — logic "cocokkan kode →
  aksi" per modul (bukan backend, murni frontend), dipanggil dari
  `onKeyDown` (Enter) di field teks yang menampung ketikan alat scanner
  barcode USB/Bluetooth (alat itu cuma keyboard emulator — tidak butuh API
  khusus, cukup field yang lagi fokus, tidak ada mode kamera). Kalau nanti
  nambah scan di modul lain, ikuti pola ini: satu `resolveXScan()` dipanggil
  dari field Enter-nya sendiri.

---

## 9. Yang belum dikerjakan

- **"Lupa kata sandi" di frontend masih fake** — layar login itu tidak
  memanggil endpoint apa pun, cuma tampilan. Reset password sungguhan lewat
  Master Data → Pengguna (Owner), yang memang sudah tersambung ke
  `PUT /api/users.php?id=`.
- **Tidak ada rate limiting / lockout** pada percobaan login yang gagal.
  Cukup untuk satu toko dengan beberapa user; tambahkan kalau nanti diakses
  dari internet terbuka.
- **CORS belum diatur** — asumsinya frontend dan backend satu origin (server
  Apache yang sama, yang memang begitu sesuai `docs/BACKEND.md` § Setup).
  Kalau nanti frontend dihosting terpisah dari backend, tambahkan header
  CORS di `response.php`.
