# FAQ — App-mini (Sistem Stok & Kasir Toko)

**PUTRA JAYA MOTOR** · Jl. Jati Raya Blok J No. 11, Banyumanik, Semarang · 0815-5608-055

---

## FAQ ini untuk siapa?

Dokumen ini menjawab pertanyaan yang paling sering muncul saat memakai
App-mini sehari-hari di toko, sekaligus hal-hal teknis yang perlu diketahui
Owner selaku pemasang & perawat aplikasinya.

- **Bagian A — Pemakaian Harian** ditujukan untuk **kasir dan Owner**. Tidak
  perlu paham komputer; cukup ikuti langkahnya.
- **Bagian B — Teknis** ditujukan untuk **Owner / orang yang memasang
  aplikasi** di komputer toko.

Kalau ada pesan aneh yang muncul di layar, langsung lompat ke **A8 — Arti
Pesan Error**; pesannya ditulis persis seperti yang tampil supaya gampang
dicari.

---

## Daftar Isi

**Bagian A — Pemakaian Harian**

- A1 · Login & Peran
- A2 · Transaksi Penjualan
- A3 · Stok & Data Barang
- A4 · Input Pembelian
- A5 · Retur
- A6 · Laporan
- A7 · Barcode (Scan & Cetak)
- A8 · Arti Pesan Error

**Bagian B — Teknis (Owner / Admin)**

- B1 · Memasang di XAMPP
- B2 · Dipakai Beberapa Kasir Sekaligus
- B3 · Offline — Tanpa Internet
- B4 · Backup & Restore Data
- B5 · Pengguna & Kata Sandi
- B6 · Data Berubah Sendiri Tanpa Refresh
- B7 · Kalau Ada Masalah
- B8 · Batasan yang Perlu Diketahui

---

# Bagian A — Pemakaian Harian

## A1 · Login & Peran

### Bagaimana cara masuk ke aplikasi?

Buka browser, ketik alamat aplikasi (`http://localhost/app-mini/` di komputer
server, atau `http://<alamat-ip-server>/app-mini/` dari komputer/HP kasir
lain). Isi **Nama Pengguna** dan **Kata Sandi**, klik **Masuk**.

Peran Anda (Owner atau Karyawan) otomatis mengikuti akun — tidak perlu dipilih
saat login. Peran yang sedang aktif terlihat sebagai badge di pojok kanan atas.

### Apa bedanya Owner dan Karyawan?

| Menu | Owner | Karyawan |
|---|:---:|:---:|
| Dashboard | ✅ | ✅ |
| Transaksi Penjualan | ✅ | ✅ |
| Data Barang | ✅ | 🔒 |
| Input Pembelian | ✅ | 🔒 |
| Laporan Stok | ✅ | ✅ |
| Laporan (lengkap) | ✅ | 🔒 |
| Retur | ✅ | ✅ (hanya Retur Penjualan) |
| Master Data | ✅ | 🔒 |

Selain menu yang terkunci, ada juga hal-hal yang **disembunyikan dari
Karyawan** walau menunya terbuka:

- **Semua angka harga beli / modal / laba / margin.** Karyawan tidak pernah
  melihat harga beli barang, baik di Data Barang, Laporan, maupun Riwayat
  Suplier.
- Kartu **Pembelian Bulan Ini**, **Laba Kotor Bulan Ini**, dan grafik **Tren
  Penjualan** di Dashboard.
- Tombol **hapus transaksi** dan **hapus retur**.
- Tab **Retur Pembelian**.

Penyembunyian ini dilakukan di server, bukan cuma disembunyikan di tampilan —
jadi tetap aman meskipun ada yang mencoba mengakalinya.

### Kenapa beberapa menu ada gambar gemboknya?

Artinya menu itu **khusus akses Owner**. Kalau diklik tidak akan terjadi
apa-apa. Kalau Anda memang perlu mengaksesnya, minta Owner login dengan
akunnya, atau minta Owner mengubah peran akun Anda lewat Master Data →
Pengguna.

### Saya lupa kata sandi, bagaimana?

⚠️ **Tombol "Lupa kata sandi?" di layar login belum berfungsi sungguhan.**
Tombol itu hanya menampilkan pesan konfirmasi di layar — tidak benar-benar
mengirim apa pun dan tidak mereset kata sandi apa pun.

Cara reset yang sebenarnya: **Owner login**, buka **Master Data → Pengguna**,
klik **Ubah** pada akun yang bersangkutan, isi kolom *Password Baru*, lalu
Simpan.

### Kenapa saat login muncul "Username atau password salah."?

Pesan ini sengaja tidak memberi tahu mana yang salah (demi keamanan). Periksa
huruf besar/kecil pada nama pengguna — `Budi` tidak sama dengan `budi`. Kalau
tetap gagal, minta Owner mereset kata sandi Anda lewat Master Data → Pengguna.

---

## A2 · Transaksi Penjualan

### Bagaimana urutan membuat transaksi penjualan?

1. Buka menu **Transaksi Penjualan**.
2. Di kartu **Tambah Barang**, klik kolom **Cari Barang**, ketik kode atau nama
   barang, lalu pilih dari daftar yang muncul.
3. Isi **Jumlah**, lalu klik **+ Tambah ke Keranjang**. Ulangi untuk barang
   berikutnya.
4. Isi **Nama Pelanggan** di kartu Data Transaksi (boleh dikosongkan).
5. Pilih **Metode Bayar**, isi **Jumlah Bayar**, lalu klik **Selesaikan
   Transaksi**.
6. Struk muncul — bisa langsung diklik **Cetak**.

### Kenapa harga per pcs berubah sendiri saat saya ubah jumlahnya?

Karena aplikasi memilih **tingkat harga secara otomatis berdasarkan jumlah**
yang dibeli. Kasir tidak memilih harga secara manual:

| Jumlah dalam satu baris | Harga yang dipakai |
|---|---|
| 1 – 5 pcs | **ECER** |
| 6 – 10 pcs | **BENGKEL** |
| 11 pcs atau lebih | **GROSIR** |

Tingkat harga yang sedang berlaku selalu terlihat sebagai badge (misalnya
`BENGKEL · Rp 58.000/pcs`) sebelum barang ditambahkan, dan tercantum di kolom
**Tier** di keranjang serta di struk.

> ⚠️ **Hati-hati:** perhitungan ini dilakukan **per baris keranjang**, bukan
> dari total belanja. Menambahkan **10 pcs sekaligus** akan mendapat harga
> BENGKEL, tetapi menambahkan **5 pcs lalu 5 pcs lagi secara terpisah** akan
> mendapat harga ECER dua kali. Kalau pelanggan membeli 10 pcs barang yang
> sama, masukkan sebagai **satu baris berisi 10**, bukan dua baris berisi 5.

### Metode bayar apa saja yang tersedia?

Hanya dua: **Tunai** dan **Transfer**. Belum ada kartu debit/kredit, QRIS,
maupun pembayaran sebagian (bon/kasbon) untuk penjualan.

### Apakah kembalian dihitung otomatis?

Ya, tapi **hanya untuk pembayaran Tunai**. Isi kolom *Jumlah Bayar*, kembalian
langsung terhitung. Untuk Transfer, kembalian selalu dianggap nol.

> ⚠️ Aplikasi **tidak menolak** jika *Jumlah Bayar* diisi kurang dari total.
> Transaksinya tetap tersimpan dengan kembalian minus. Pastikan kasir mengisi
> jumlah bayar dengan benar.

### Nomor invoice dari mana? Bisa diketik manual?

Dibuat **otomatis** oleh sistem saat transaksi disimpan, dengan format
`INV-TTBBHH-NNNN` — contoh: `INV-260810-0003` artinya transaksi ke-3 pada
10 Agustus 2026. Nomor urut mulai lagi dari `0001` setiap ganti hari. Nomor
invoice **tidak bisa diketik manual**, dan tidak akan pernah kembar.

Sebelum disimpan, kolom No. Invoice memang tertulis *"Dibuat otomatis saat
disimpan"* — itu normal.

### Apakah tanggal transaksi bisa dimundurkan?

Tidak. Tanggal penjualan selalu **hari ini** mengikuti tanggal komputer server.
Kalau tanggal di transaksi salah, berarti jam/tanggal di komputer server yang
perlu dibetulkan.

### Apakah nama pelanggan baru otomatis tersimpan?

Ya. Kalau Anda mengetik nama pelanggan yang belum terdaftar, sistem otomatis
membuatkan datanya (kode `CUST-0001`, `CUST-0002`, dst.) dengan alamat dan
nomor HP masih kosong (`-`). Owner bisa melengkapinya belakangan lewat
**Master Data → Pelanggan**.

Kalau nama pelanggan dikosongkan, transaksinya dicatat atas nama **"Umum"**.

### Apakah nama & alamat toko muncul di struk?

Ya. Nama toko, alamat, dan nomor telepon/WA tercetak di bagian atas struk,
lengkap dengan logo di pojok kanan atas. Datanya diambil dari **Master Data →
Toko** — kalau ada yang perlu diubah (misal pindah alamat atau ganti nomor
WA), ubah di sana dan struk berikutnya langsung ikut berubah.

### Ukuran kertas apa yang cocok untuk cetak struk?

Tombol **Cetak** di layar "Transaksi Berhasil" hanya mencetak kartu struknya
saja (kop toko, rincian barang, total) — sidebar, header aplikasi, dan
tombol-tombol di layar itu tidak ikut tercetak.

- **Printer biasa (A4/Letter)**: langsung pakai, tidak perlu setelan apa pun.
- **Printer struk kecil (thermal 80mm)**: juga didukung otomatis — tata
  letak struk menyesuaikan ke lebar 80mm begitu tombol Cetak diklik. Di
  sisi Windows, pastikan printer struknya di-set sebagai kertas
  roll/continuous di driver-nya supaya kertas terpotong pas sesuai
  panjang struk, bukan ikut ukuran kertas standar.

### Bagaimana melihat detail transaksi yang sudah lewat?

Di tabel **Riwayat Penjualan** (di bawah layar Transaksi Penjualan), **klik
nomor invoice-nya** — akan muncul jendela Detail Penjualan berisi rincian
barang, jumlah, harga satuan, dan subtotal.

Ini bisa dilakukan **oleh Karyawan juga**, jadi kasir tidak perlu memanggil
Owner hanya untuk mengecek riwayat belanja pelanggan.

### Transaksinya salah input, bisa dihapus?

Bisa, tapi **hanya oleh Owner**. Klik ikon tempat sampah di baris transaksi
tersebut pada tabel Riwayat Penjualan, lalu konfirmasi.

Saat transaksi dihapus, stok barangnya **otomatis dikembalikan** persis ke
batch asalnya. Kalau transaksi itu sudah pernah diretur, penghapusan akan
ditolak — hapus dulu retur-nya, baru transaksinya.

### Bisakah scan barcode saat melayani penjualan?

**Belum.** Saat ini alat scanner hanya bisa dipakai di **Data Barang** dan
**Input Pembelian**. Di layar Transaksi Penjualan, barang dipilih dengan
mengetik kode/nama di kolom **Cari Barang**.

### Kenapa barang yang saya cari tidak muncul di daftar?

Kolom Cari Barang **hanya menampilkan barang yang sudah terdaftar**. Kalau
barangnya benar-benar belum pernah ada di toko, barang tersebut harus
didaftarkan dulu lewat **Input Pembelian** (lihat A3 & A4).

---

## A3 · Stok & Data Barang

### Bagaimana cara menambah barang baru?

**Barang baru hanya bisa didaftarkan lewat menu Input Pembelian**, bukan lewat
Data Barang. Di Input Pembelian, pada pemilih barang, pilih opsi **"+ Barang
Baru (belum terdaftar)"**, lalu isi kode dan namanya.

Ini disengaja: supaya setiap barang di toko selalu punya jejak asal-usulnya —
dibeli dari suplier mana, tanggal berapa, dengan harga berapa. Menu Data Barang
memang hanya untuk **mengubah** barang yang sudah ada.

### Kenapa barang saya statusnya "Perlu Dilengkapi"?

Karena barang itu baru saja dibuat lewat Input Pembelian, dan **harga jualnya
masih nol**. Sistem tahu harga belinya (dari faktur), tapi belum tahu Anda mau
menjualnya berapa.

Cara melengkapi: **Data Barang → cari barangnya → Ubah →** isi ketiga harga
jual (**Harga Ecer**, **Harga Bengkel**, **Harga Grosir**) → **Simpan**.
Ketiganya wajib diisi lebih dari 0, kalau tidak akan muncul pesan
*"Isi semua harga jual dengan angka lebih dari 0."*

Selama masih "Perlu Dilengkapi", barang itu tidak ikut dihitung di kartu
peringatan **Stok Menipis** di Dashboard.

### Apa arti status Aman, Menipis, dan Kritis?

Dihitung dari **Stok Minimum** barang tersebut (bawaannya **10**):

| Status | Kapan muncul (kalau stok minimum = 10) |
|---|---|
| **Kritis** | stok **0 – 5** (setengah dari stok minimum atau kurang) |
| **Menipis** | stok **6 – 10** (sama dengan stok minimum atau kurang) |
| **Aman** | stok **11 atau lebih** |

Catatan: stok 0 tetap dibaca sebagai **Kritis** — tidak ada status khusus
"Habis".

### Boleh mengubah stok langsung di Data Barang (stok opname)?

Boleh. Kolom **Stok** di form Ubah Barang memang bisa diisi manual, dan itu
memang cara yang benar untuk menyesuaikan hasil hitung fisik di rak.

Yang perlu diketahui: aplikasi menyimpan stok dalam bentuk **batch per
pembelian** (supaya modal per barang akurat). Kalau Anda menaikkan stok manual,
sistem otomatis membuat satu "batch penyesuaian" tanpa suplier. Kalau Anda
menurunkan stok manual, sistem otomatis mengurangi batch yang paling lama
dulu. Jadi Anda tidak perlu memikirkan batch sama sekali — cukup isi angka
stok yang benar.

Kalau muncul pesan *"Batch barang ini tidak cukup untuk menurunkan stok
sebanyak itu..."*, artinya data batch dan stoknya tidak cocok — hubungi yang
merawat aplikasi.

### Apakah stok bisa jadi minus?

Tidak. Setiap jalur yang mengurangi stok (penjualan, retur ke suplier,
penyesuaian manual) diperiksa dulu, dan akan ditolak dengan pesan yang jelas
kalau stoknya tidak cukup.

Kalau dua kasir menjual barang terakhir yang sama secara bersamaan, hanya satu
yang berhasil — yang satunya akan mendapat pesan stok tidak mencukupi.

### Boleh ada dua barang dengan kode yang sama?

Tidak. **Kode barang bersifat unik** — satu kode selamanya mewakili satu
barang, dan kode tidak bisa diubah setelah barang terdaftar.

### Kalau saya beli barang dengan kode sama dari suplier berbeda, jadi barang baru?

**Tidak — stoknya digabung** ke barang yang sudah ada. Ini memang perilaku yang
diinginkan: 1 kode = 1 barang di rak, berapa pun jumlah supliernya.

Meski begitu, **harga beli dari tiap suplier tetap dicatat terpisah** di
belakang layar. Jadi:

- Laba per transaksi tetap akurat, memakai harga beli batch yang benar-benar
  keluar dari rak (yang paling lama keluar duluan / FIFO).
- Di Data Barang, badge **+N** di kolom Suplier bisa diklik untuk melihat
  **Riwayat Suplier**: daftar semua pembelian barang itu, dari suplier mana,
  tanggal berapa, harga berapa, dan **sisa**-nya berapa.

Kolom Suplier di tabel utama menampilkan **suplier terakhir** yang memasok
barang tersebut.

### Apa arti kolom "Sisa"?

Tergantung di layar mana:

- Di **Riwayat Suplier** (Data Barang): sisa = **berapa pcs dari pembelian itu
  yang masih ada di rak**, belum terjual dan belum diretur ke suplier.
- Di **layar Retur**: sisa = **berapa pcs yang masih boleh diretur** dari
  invoice/faktur itu (jumlah aslinya dikurangi yang sudah pernah diretur).

### Bisakah menghapus barang?

Bisa, selama barang itu **belum pernah dipakai di transaksi apa pun**. Kalau
sudah pernah masuk pembelian/penjualan, penghapusan ditolak dengan pesan
*"Barang ini masih dipakai di data transaksi yang tersimpan, jadi tidak bisa
dihapus."* Ini demi menjaga laporan lama tetap utuh.

Untuk menghapus banyak barang sekaligus, centang beberapa baris lalu klik
**Hapus (N)** — barang yang terkunci karena sudah dipakai transaksi akan
otomatis dilewati.

---

## A4 · Input Pembelian

### Bagaimana urutan input pembelian?

1. Buka **Input Pembelian**.
2. Di kartu **Data Pembelian**: pilih **Nama Suplier** (boleh mengetik suplier
   baru), isi **No. Faktur** sesuai faktur fisik, pilih **Payment**
   (Cash / TOP), dan isi **Tgl Jatuh Tempo** kalau TOP.
3. Di kartu **Tambah Barang**: scan atau ketik kode barang, pilih kategori,
   pilih barangnya (atau **+ Barang Baru** kalau belum terdaftar), pilih
   **Basis Harga**, isi **Harga Beli**, **Jumlah**, dan **Pricelist**, lalu
   **+ Tambah ke Daftar**.
4. Ulangi untuk barang lain, lalu klik **Simpan Pembelian** — data langsung
   tersimpan ke Riwayat Pembelian dengan notifikasi sukses, **tidak ada layar
   pratinjau/invoice lagi** (No. Faktur di langkah 2 memang sudah jadi bukti
   fisik dari suplier, jadi tidak perlu invoice tambahan dari sistem).

Kalau input pembelian terpotong (browser tertutup, listrik mati), isian yang
belum disimpan otomatis dipulihkan saat Anda membuka menu itu lagi.

### Apakah No. Faktur dibuat otomatis?

**Tidak.** Berbeda dengan nomor invoice penjualan, **No. Faktur pembelian harus
diketik manual** sesuai nomor yang tertulis di faktur fisik dari suplier.
Tujuannya supaya nomor di sistem sama persis dengan nomor di kertas, sehingga
mudah dicocokkan saat audit atau saat mau retur.

### Bolehkah dua faktur punya nomor yang sama?

- **Beda suplier: boleh.** Wajar kalau dua suplier sama-sama menomori faktur
  mereka "001".
- **Suplier yang sama: tidak boleh.** Akan ditolak dengan pesan
  *"No. Faktur "..." sudah pernah diinput untuk suplier "..."."* Pesan ini
  biasanya berarti faktur itu sudah pernah Anda input sebelumnya — cek dulu di
  Riwayat Pembelian sebelum menginput ulang.

Karena nomor faktur hanya unik per suplier, **setiap kali mencari faktur
pembelian (misalnya saat retur), nama supliernya wajib ikut diisi.**

### Apa bedanya Basis Harga "Faktur" dan "Netto"?

- **Faktur** — harga sebelum diskon, sesuai yang tertera di faktur suplier.
- **Netto** — harga bersih yang benar-benar Anda bayar setelah diskon.

Pilih yang sesuai dengan angka yang Anda ketik di kolom **Harga Beli**. Minimal
salah satunya harus terisi; kalau keduanya nol, pembelian ditolak.

Untuk perhitungan modal & laba, sistem memakai **harga netto kalau ada**, dan
harga faktur kalau netto-nya kosong.

Terpisah dari itu, di form Ubah Barang ada pilihan **Price List (acuan
tampilan)** — ini hanya menentukan angka mana (faktur atau netto) yang
ditampilkan sebagai acuan di tabel Data Barang. Tidak memengaruhi perhitungan
laba.

### Apa itu Payment "Cash" dan "TOP"?

- **Cash** — dibayar langsung saat barang datang.
- **TOP** (*Term of Payment*) — dibayar tempo, jatuh temponya diisi di kolom
  **Tgl Jatuh Tempo**.

Tanggal jatuh tempo hanya disimpan untuk pembelian TOP. Perlu diketahui:
aplikasi **belum punya pengingat/alarm jatuh tempo** — tanggalnya tersimpan
sebagai catatan saja.

### Suplier dan kategori baru otomatis dibuat?

- **Suplier: ya.** Mengetik nama suplier yang belum terdaftar akan otomatis
  membuatkan datanya (kode `SUP-0001`, dst.), alamat & nomor HP bisa dilengkapi
  belakangan di Master Data.
- **Kategori: tidak.** Kategori harus sudah ada lebih dulu. Kalau belum,
  tambahkan di **Master Data → Kategori Barang** (ada tombol pintasnya di form
  Input Pembelian).

### Barang baru sudah masuk, bagaimana barcodenya?

Setelah pembelian disimpan, akan muncul kartu **"Barang Baru Belum Ada
Barcode"** berisi daftar barang yang baru saja dibuat, lengkap dengan tombol
**Cetak Barcode** untuk masing-masing — supaya bisa langsung ditempel di
rak/produk.

### Pembelian salah input, bisa dihapus?

Bisa, **tapi hanya selama barangnya belum bergerak sama sekali** — belum ada
yang terjual dan belum ada yang diretur. Kalau sudah ada yang terjual,
penghapusan ditolak dengan pesan *"Batch ... dari pembelian ini sudah ada yang
terjual/diretur, tidak bisa dihapus."*

Kalau pembelian itu sudah pernah diretur ke suplier, hapus dulu retur-nya.

---

## A5 · Retur

### Apa bedanya Retur Penjualan dan Retur Pembelian?

- **Retur Penjualan** — pelanggan mengembalikan barang ke toko. Stok
  **bertambah**. Bisa dilakukan Owner maupun Karyawan.
- **Retur Pembelian** — toko mengembalikan barang ke suplier. Stok
  **berkurang**. **Hanya Owner.**

### Bagaimana cara memproses retur?

1. Buka menu **Retur**, pilih tab yang sesuai.
2. Isi nama pelanggan (atau suplier) dan **No. Invoice/Faktur Asal**, lalu klik
   tombol **Cek**.
3. Daftar barang yang boleh diretur akan muncul. Isi **Qty Diretur** dan pilih
   **Alasan** (Rusak / Cacat / Salah Kirim / Lainnya).
4. Klik **Proses Retur**.

Nomor retur dibuat otomatis: `RJ-...` untuk retur penjualan, `RB-...` untuk
retur pembelian.

### Kenapa barangnya tidak muncul setelah saya klik "Cek"?

Kemungkinan besar barang itu **sudah pernah diretur sepenuhnya**. Barang dengan
sisa 0 sengaja tidak ditampilkan. Kalau semua barang di dokumen itu sudah
diretur habis, akan muncul pesan *"Semua barang di invoice ini sudah pernah
diretur sepenuhnya."*

Untuk retur pembelian, pastikan juga **nama supliernya sudah benar** — nomor
faktur saja tidak cukup, karena nomor faktur hanya unik per suplier.

### Kenapa retur saya ditolak padahal stoknya masih ada?

Untuk **Retur Pembelian**, ada satu aturan yang sering mengagetkan:

> *"Batch ... dari faktur ... cuma sisa N pcs yang belum terjual, tidak bisa
> retur M pcs."*

Artinya: barang yang Anda kembalikan ke suplier **harus berasal dari faktur
suplier itu dan belum terjual**. Stok total yang mencukupi tidak otomatis
berarti boleh diretur — bisa jadi stok yang tersisa di rak berasal dari
pembelian yang lain.

Contoh: Anda beli 10 pcs dari Suplier A dan 10 pcs dari Suplier B, lalu terjual
10 pcs (diambil dari batch A karena lebih dulu masuk). Stok tinggal 10, tapi
yang bisa diretur ke **Suplier A** hanya 0 pcs — sisanya milik batch B.

### Kalau barang retur dari pelanggan, masuk ke stok mana?

Masuk sebagai **stok baru** dengan modal dihitung dari rata-rata harga beli
barang yang benar-benar terjual di invoice itu. Barangnya tidak dilacak balik
ke batch aslinya, karena begitu keluar toko riwayat batch-nya sudah putus.

### Retur salah input, bisa dihapus?

Bisa, oleh Owner. Untuk retur penjualan, penghapusan akan ditolak kalau barang
hasil retur itu **sudah terjual lagi** — logis, karena barangnya sudah tidak di
rak lagi untuk dibatalkan.

---

## A6 · Laporan

### Apa bedanya menu "Laporan Stok" dan "Laporan"?

| | **Laporan Stok** | **Laporan** |
|---|---|---|
| Siapa yang bisa buka | Owner & Karyawan | Owner saja |
| Isi | Daftar stok barang saat ini | Laporan transaksi per periode (bertab) |
| Ada angka modal/laba? | ❌ tidak | ✅ ya |

**Laporan Stok** cocok untuk kasir saat mengecek ketersediaan barang: bisa
disaring per kategori, per suplier, atau per status stok, dan bisa
di-**Export ke Excel** atau **Cetak/PDF**.

### Ada laporan apa saja di menu "Laporan"?

Semuanya mengikuti rentang tanggal yang diisi di **Periode Dari / Sampai** di
bagian atas (bawaannya 30 hari terakhir):

| Tab | Isi |
|---|---|
| **Global** | Ringkasan: total pendapatan, modal, laba bersih, jumlah transaksi |
| **Pembelian** | Rincian faktur per suplier |
| **Penjualan** | Rincian tiap transaksi penjualan |
| **Laba** | Laba & margin per transaksi |
| **Stok Laris** | Ranking barang berdasarkan qty terjual dan pendapatan |
| **Retur** | Daftar retur penjualan & retur pembelian |

Kolom **Waktu** di tabel bisa diklik untuk mengurutkan dari terbaru/terlama.

### Seberapa bisa dipercaya angka labanya?

Modal dihitung dari **harga beli batch yang benar-benar keluar dari rak**,
bukan harga beli terkini. Konsekuensinya, dan ini bagus:

- **Menaikkan harga beli hari ini tidak mengubah laba transaksi bulan lalu.**
  Angka laporan lama tetap stabil.
- Kalau satu barang dibeli dari dua suplier dengan harga berbeda, laba per
  transaksi mengikuti harga batch yang betul-betul terjual.

Yang perlu diketahui sebagai catatan:

- **Retur penjualan tidak dikurangkan dari laba.** Barang yang diretur kembali
  ke stok, dan modalnya baru dihitung lagi kalau barang itu terjual lagi.
- Stok yang disesuaikan manual (stok opname) dinilai dengan **harga rata-rata**
  batch yang tersisa — jadi sifatnya perkiraan, bukan harga faktur asli.
- Kalau ada transaksi yang tercatat **sebelum fitur pelacakan batch aktif**,
  modalnya bisa terbaca 0 sehingga labanya terlihat terlalu besar. Data
  transaksi contoh bawaan aplikasi termasuk kategori ini.

---

## A7 · Barcode (Scan & Cetak)

### Alat scanner seperti apa yang cocok?

Alat **scanner barcode USB atau Bluetooth** biasa (bukan kamera HP). Alat
seperti ini bekerja sebagai "keyboard palsu": begitu dipicu, dia mengetik kode
ke kolom yang sedang aktif lalu menekan Enter sendiri.

Karena dianggap keyboard biasa oleh komputer, alat ini:

- **tidak butuh instalasi driver khusus** — colok dan langsung jalan,
- **tidak butuh izin kamera atau HTTPS**,
- jalan di browser apa pun.

Alat yang mendukung QR / barcode 2D lebih disarankan, karena fitur Cetak
Barcode di aplikasi ini bisa menghasilkan keduanya.

### Di mana saya bisa scan?

- **Data Barang** → kolom *"Cari / Scan Barang"*. Barang yang sudah terdaftar
  langsung membuka form Ubah.
- **Input Pembelian** → kolom *"Scan / Kode Barang"*. Barang yang sudah ada
  langsung terpilih; kalau belum ada, form otomatis pindah ke mode "Barang
  Baru" dengan kodenya sudah terisi.

Di **Transaksi Penjualan belum ada fitur scan.**

Cara pakainya: klik dulu kolomnya (supaya kursor berkedip di situ), baru
arahkan scanner ke barcode.

### Bagaimana mencetak barcode?

Klik tombol **Cetak Barcode** — tersedia di baris tabel Data Barang, di dalam
form Ubah Barang, di daftar barang pembelian, dan di kartu "Barang Baru Belum
Ada Barcode". Untuk mencetak banyak sekaligus, centang beberapa barang di Data
Barang lalu klik **Cetak Barcode (N)**.

Pilihan yang tersedia:

- **Tipe:** QR Code atau Barcode garis (Code128).
- **Layout:** hanya kode, atau kode + nama barang.
- **Ukuran label:** 40 × 30 mm.

Isi barcode-nya adalah **kode barang** itu sendiri (misal `BAN-001`) — tidak
ada nomor barcode terpisah yang perlu dicatat.

---

## A8 · Arti Pesan Error

Pesan ditulis persis seperti yang tampil di layar supaya mudah dicari.

### Saat menjual

| Pesan | Artinya & solusinya |
|---|---|
| *Stok tidak mencukupi (tersisa N pcs).* | Jumlah yang diminta melebihi stok. Kurangi jumlahnya, atau input pembelian dulu. |
| *Keranjang masih kosong.* | Belum ada barang yang ditambahkan ke keranjang. |
| *Data batch ... tidak sinkron dengan stok ... — hubungi admin.* | Angka stok dan rincian batch tidak cocok. Transaksi dibatalkan otomatis (data aman). **Perlu diperiksa oleh yang merawat aplikasi.** |

### Saat input pembelian

| Pesan | Artinya & solusinya |
|---|---|
| *No. Faktur "..." sudah pernah diinput untuk suplier "..."* | Faktur ini sudah pernah dimasukkan. Cek Riwayat Pembelian dulu. |
| *Setiap item butuh kode, qty >= 1, dan salah satu harga faktur/netto.* | Ada baris barang yang harga belinya masih kosong/nol. |
| *Barang baru "..." butuh nama dan kategori.* | Barang baru wajib diberi nama dan kategori. |
| *Kategori "..." tidak ditemukan.* | Kategori belum terdaftar — tambahkan dulu di Master Data → Kategori Barang. |

### Saat mengubah data barang

| Pesan | Artinya & solusinya |
|---|---|
| *Isi semua harga jual dengan angka lebih dari 0.* | Ketiga harga jual (ecer, bengkel, grosir) wajib diisi. Biasanya muncul pada barang baru berstatus "Perlu Dilengkapi". |
| *Batch barang ini tidak cukup untuk menurunkan stok sebanyak itu ...* | Rincian batch tidak mencukupi penurunan stok. **Hubungi yang merawat aplikasi.** |
| *... masih dipakai di data transaksi yang tersimpan, jadi tidak bisa dihapus.* | Data ini sudah dipakai transaksi lama. Sengaja dilindungi supaya laporan lama tidak rusak. |

### Saat retur atau menghapus transaksi

| Pesan | Artinya & solusinya |
|---|---|
| *Invoice "..." tidak ditemukan.* | Nomor invoice salah ketik, atau transaksinya sudah dihapus. |
| *No. faktur "..." untuk suplier "..." tidak ditemukan.* | Nomor faktur benar tapi **supliernya salah** (atau sebaliknya). Keduanya harus cocok. |
| *Retur ... melebihi yang bisa diretur ... (sisa N pcs).* | Sebagian sudah pernah diretur sebelumnya. |
| *Batch ... cuma sisa N pcs yang belum terjual, tidak bisa retur ... pcs.* | Barang dari faktur itu sudah keburu terjual — lihat penjelasan di A5. |
| *... sudah punya retur ... terkait, hapus retur-nya dulu.* | Hapus retur-nya lebih dulu, baru transaksinya. |
| *Batch ... dari pembelian ini sudah ada yang terjual/diretur, tidak bisa dihapus.* | Pembelian hanya bisa dihapus selama barangnya belum bergerak sama sekali. |

### Pesan umum

| Pesan | Artinya & solusinya |
|---|---|
| *Belum login.* | Sesi berakhir (browser lama tidak dipakai, atau server sempat restart). Login ulang. |
| *Tidak punya akses untuk aksi ini.* | Aksi ini khusus Owner. |
| *Server bermasalah (HTTP xxx). Coba lagi, kalau tetap gagal hubungi admin.* | Masalah di sisi server — lihat **B7 · Kalau Ada Masalah**. |
| *Tidak bisa menghapus akun sendiri.* | Akun yang sedang login tidak bisa menghapus dirinya sendiri. |

---

# Bagian B — Teknis (Owner / Admin)

## B1 · Memasang di XAMPP

### Apa yang dibutuhkan?

XAMPP dengan **Apache** dan **MySQL**. Tidak ada langkah build, tidak perlu
`npm install`, tidak perlu internet.

### Cara cepat: `deploy-windows.bat`

Di Windows, semua langkah di bawah sudah dibungkus jadi satu file. Taruh
folder aplikasi ini di mana saja (hasil unzip/`git clone`), nyalakan MySQL di
XAMPP Control Panel, lalu klik dua kali **`deploy-windows.bat`** di folder
paling atas. Kalau XAMPP bukan di `C:\xampp`, jalankan lewat cmd:
`deploy-windows.bat D:\xampp`.

Script-nya menyalin aplikasi ke `htdocs\app-mini` dan mengimport
`backend/schema.sql` **hanya kalau database `app_mini` belum ada** — jadi aman
dijalankan ulang tiap kali ada update aplikasi, data toko yang sudah ada tidak
akan tertimpa.

### Langkah pemasangan (manual)

1. **Copy** seluruh folder `app-mini` (termasuk folder `backend/`) ke folder
   `htdocs`:

    - Windows: `C:\xampp\htdocs\app-mini`
    - Linux: `/opt/lampp/htdocs/app-mini`

2. **Nyalakan Apache dan MySQL** dari XAMPP Control Panel.
3. **Import database**: buka `http://localhost/phpmyadmin` → tab **Import** →
   pilih file `backend/schema.sql` → jalankan. Ini membuat database `app_mini`
   beserta data contoh.
4. Buka **`http://localhost/app-mini/`**.
5. Login dengan akun bawaan:

   | Nama Pengguna | Kata Sandi | Peran |
   |---|---|---|
   | `Budi` | `owner123` | Owner |
   | `Karyawan1` | `karyawan123` | Karyawan |

6. ⚠️ **Segera ganti kata sandi kedua akun itu** lewat Master Data → Pengguna
   sebelum dipakai sungguhan.
7. Isi identitas toko di **Master Data → Toko** supaya struk mencetak nama dan
   alamat yang benar.

### Perlu mengubah setelan database?

Biasanya tidak. Bawaan aplikasi (`backend/config.php`) sudah cocok dengan
XAMPP standar: pengguna `root`, tanpa kata sandi, database `app_mini`. Kalau
MySQL Anda memakai kata sandi, ubah `DB_PASS` di file tersebut.

---

## B2 · Dipakai Beberapa Kasir Sekaligus

### Bagaimana caranya kasir lain ikut memakai aplikasi ini?

Cukup **satu komputer** yang menjalankan XAMPP (sebut saja komputer server).
Komputer/HP/tablet lain tidak perlu menginstal apa pun — cukup buka browser
dan mengakses:

```
http://<alamat-ip-komputer-server>/app-mini/
```

Contoh: `http://192.168.1.10/app-mini/`

Semua perangkat memakai **satu database yang sama**, jadi stok dan transaksi
otomatis sinkron. Cara mengetahui alamat IP komputer server: jalankan
`ipconfig` (Windows) atau `ip a` (Linux) di komputer tersebut.

Syaratnya semua perangkat berada di **jaringan Wi-Fi/LAN yang sama**, dan
Firewall Windows mengizinkan Apache (biasanya ada konfirmasi saat Apache
pertama kali dinyalakan — pilih *Allow*).

### Apakah setiap kasir perlu akun sendiri?

Sangat disarankan, supaya jelas siapa melakukan apa. Buat akunnya lewat
**Master Data → Pengguna**, beri peran **Karyawan** untuk kasir.

---

## B3 · Offline — Tanpa Internet

### Apakah aplikasi ini butuh koneksi internet?

**Tidak sama sekali.** Semua yang tadinya diambil dari internet sudah
di-download dan disimpan di dalam folder aplikasi:

- React & ReactDOM → `vendor/react/`
- Font "Source Serif 4" → `vendor/fonts/`
- Pembuat QR Code & Barcode → `vendor/barcode/`
- Logo toko → `assets/`

Backend PHP juga tidak pernah menghubungi layanan luar mana pun. Aplikasi jalan
100% dari komputer toko, cocok untuk lokasi yang sinyalnya tidak stabil.

Yang tetap dibutuhkan adalah **jaringan lokal** (Wi-Fi/kabel di dalam toko)
kalau ada lebih dari satu perangkat kasir — tapi jaringan lokal ini tidak perlu
tersambung ke internet.

---

## B4 · Backup & Restore Data

### Bagaimana cara mem-backup data?

Yang wajib di-backup adalah **databasenya** (semua transaksi, stok, master data
ada di situ).

**Lewat phpMyAdmin (paling mudah):**
`http://localhost/phpmyadmin` → pilih database `app_mini` → tab **Export** →
pilih *Quick* → **Go**. Hasilnya satu file `.sql` — simpan ke flashdisk atau
cloud.

**Lewat command line:**

```
mysqldump -u root app_mini > backup-app_mini-2026-08-10.sql
```

### Seberapa sering sebaiknya backup?

Untuk toko yang transaksinya jalan tiap hari, **backup harian saat tutup toko**
sudah memadai. Simpan minimal beberapa versi terakhir (jangan menimpa file yang
sama terus), dan taruh salinannya di tempat terpisah dari komputer server —
supaya kalau komputernya rusak/hilang, datanya masih ada.

### Bagaimana cara mengembalikan (restore) backup?

phpMyAdmin → pilih database `app_mini` → tab **Import** → pilih file `.sql`
hasil backup → jalankan. Atau lewat command line:

```
mysql -u root app_mini < backup-app_mini-2026-08-10.sql
```

⚠️ Restore akan **menimpa** data yang ada sekarang. Backup dulu kondisi
terkini sebelum me-restore, kalau ragu.

---

## B5 · Pengguna & Kata Sandi

### Bagaimana menambah kasir baru?

**Master Data → Pengguna → + Tambah Pengguna.** Isi nama pengguna, kata sandi
(minimal 6 karakter), dan pilih peran **Karyawan**.

### Bagaimana mereset kata sandi staf yang lupa?

**Master Data → Pengguna → Ubah** pada akun tersebut → isi kolom *Password
Baru* → **Simpan**. Kosongkan kolom itu kalau tidak ingin mengubah kata sandi.

### Apakah kata sandi tersimpan aman?

Ya. Kata sandi disimpan dalam bentuk teracak (bcrypt), bukan teks biasa —
bahkan yang punya akses ke database pun tidak bisa membacanya. Itu juga
sebabnya kata sandi yang lupa hanya bisa **diganti**, tidak bisa "dilihat".

### Kenapa akun saya sendiri tidak bisa dihapus?

Sengaja dicegah, supaya tidak ada kondisi toko kehilangan semua akun Owner.
Kalau memang perlu, buat dulu akun Owner yang lain, login dengan akun itu, baru
hapus yang lama.

---

## B6 · Data Berubah Sendiri Tanpa Refresh

### Kenapa angka di layar berubah sendiri padahal saya tidak menekan apa-apa?

Itu memang fiturnya. Setiap **10 detik**, aplikasi diam-diam mengambil data
terbaru dari server dan memperbarui tampilan. Jadi kalau kasir lain menjual
barang, stok di layar Anda ikut turun tanpa perlu menekan tombol refresh.

Yang perlu diketahui:

- Ini **bukan reload halaman** — tampilan tidak berkedip dan posisi scroll
  tidak melompat.
- **Isian yang sedang Anda ketik tidak terganggu.** Keranjang penjualan dan
  form pembelian yang sedang diisi tidak ikut di-refresh.
- Pembaruan berhenti sementara kalau tab browser sedang tidak dilihat, supaya
  tidak membebani server.

---

## B7 · Kalau Ada Masalah

### Halaman putih/kosong, atau tabel & dropdown tidak muncul isinya

Pastikan aplikasi dibuka lewat **`http://...`**, bukan dengan **klik dua kali
file `Dashboard.dc.html`** (yang menghasilkan alamat `file:///...`). Aplikasi
ini wajib dijalankan lewat Apache; lewat `file://` browser memblokir
mekanisme yang dibutuhkan aplikasi dan tampilan akan kosong.

### Muncul "Server bermasalah (HTTP xxx)"

Berarti Apache/PHP bermasalah. Periksa berurutan:

1. Apakah **Apache** dan **MySQL** menyala di XAMPP Control Panel? (harus dua-duanya)
2. Kalau MySQL mati dan tidak mau nyala, biasanya ada aplikasi lain yang
   memakai port 3306 — lihat log di XAMPP Control Panel.
3. Coba buka `http://localhost/phpmyadmin` — kalau ini juga gagal, masalahnya
   di XAMPP, bukan di aplikasi.

### Tiba-tiba muncul "Belum login." padahal tadi sudah masuk

Sesi login berakhir — biasanya karena Apache sempat direstart, atau browser
lama tidak dipakai. Login ulang saja; tidak ada data yang hilang.

### Kasir lain tidak bisa membuka aplikasi

1. Pastikan komputer server menyala dan Apache jalan.
2. Pastikan alamat IP-nya benar dan belum berubah. Alamat IP bisa berganti
   sendiri kalau router memberikannya secara otomatis — **minta teknisi
   men-setting IP statis** untuk komputer server supaya alamatnya tetap.
3. Pastikan semua perangkat di jaringan Wi-Fi yang sama.
4. Cek Firewall Windows di komputer server — Apache harus diizinkan.

### Stok di layar terasa tidak cocok dengan fisik

Lakukan stok opname: **Data Barang → Ubah → perbaiki kolom Stok → Simpan**.
Rincian batch akan menyesuaikan otomatis (lihat A3).

Kalau yang muncul justru pesan *"Data batch ... tidak sinkron dengan stok"*
atau *"Batch barang ini tidak cukup ..."*, jangan dipaksa — itu tanda ada data
yang perlu diperiksa oleh yang merawat aplikasi.

---

## B8 · Batasan yang Perlu Diketahui

Hal-hal berikut **memang belum ada** di versi sekarang. Bukan kerusakan, tapi
perlu diketahui sebelum aplikasi diandalkan penuh:

- **Satu server, satu database.** Kalau komputer server mati atau XAMPP-nya
  berhenti, **seluruh toko tidak bisa bertransaksi** sampai server hidup lagi.
  Tidak ada mode "kerja offline dulu, sinkron belakangan" di sisi kasir.
- **Tombol "Lupa kata sandi" belum berfungsi.** Reset dilakukan Owner lewat
  Master Data → Pengguna.
- **Barang baru hanya bisa didaftarkan lewat Input Pembelian**, tidak bisa dari
  Data Barang.
- **Belum ada scan barcode di layar Transaksi Penjualan.**
- **Belum ada pengingat jatuh tempo** untuk pembelian TOP — tanggalnya
  tersimpan sebagai catatan saja.
- **Belum ada pembayaran sebagian / kasbon** untuk penjualan, dan tidak ada
  pemeriksaan kalau jumlah bayar diisi kurang dari total.
- **Retur belum dikurangkan dari laporan laba** (lihat A6).
- **Tidak ada pembatasan percobaan login.** Aman untuk pemakaian satu toko di
  jaringan sendiri, tapi **jangan mengekspos aplikasi ini ke internet
  terbuka** tanpa pengamanan tambahan.

---

*Dokumen ini dibuat berdasarkan kondisi aplikasi saat ini. Kalau ada fitur yang
berubah, perbarui `docs/FAQ.md` lalu jalankan `python3 docs/build-faq-pdf.py`
untuk membuat ulang versi PDF-nya.*
