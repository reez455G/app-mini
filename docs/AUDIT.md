# Laporan Audit — App-mini

**PUTRA JAYA MOTOR**
Jl. Jati Raya Blok J No. 11, Banyumanik, Semarang
0815-5608-055

Tanggal audit: 15 Agustus 2026

---

# Bagian A — Ringkasan untuk Owner

## Apa yang diperiksa

Audit ini menjawab tiga pertanyaan yang Anda ajukan:

1. **Apakah ada isian di layar yang tidak nyambung ke mana-mana?** — setiap
   kotak isian ditelusuri sampai ke tempat penyimpanannya, dan sebaliknya
   setiap data yang dipakai sistem dicari tempat isiannya.
2. **Apakah datanya sinkron?** — stok, batch barang, nota, dan retur diadu
   satu sama lain, dijalankan langsung ke database toko yang sekarang.
3. **Apakah laporannya benar dan gampang dipahami?** — kedelapan laporan
   dicek angkanya dan istilahnya.

Tidak ada satu baris kode pun yang diubah dalam audit ini, dan tidak ada
data yang ditambah/diubah/dihapus. Semua pemeriksaan ke database hanya
membaca.

## Hasil singkat

**Kabar baiknya: inti perhitungan stok dan uang Anda sehat.** Sepuluh
pemeriksaan konsistensi data dijalankan ke database toko, dan **semuanya
lolos** — tidak ada satu pun stok yang meleset dari rincian batch-nya, tidak
ada nota yang jumlahnya berbeda dari rincian barangnya, tidak ada penjualan
yang kehilangan catatan modalnya. Alur pembelian → penjualan → retur ditulis
dengan pengaman yang rapat (semua perubahan stok terjadi dalam satu transaksi
database, dan penghapusan nota ditolak kalau barangnya sudah terlanjur
terjual).

**Yang perlu perhatian: 11 temuan**, dengan pembagian:

| Berat | Jumlah | Ringkasnya |
|---|---|---|
| 🔴 Berat | 3 | Bisa membuat Anda salah baca uang, atau barang tidak bisa dijual tanpa penjelasan |
| 🟡 Sedang | 4 | Informasi yang ditampilkan bisa menyesatkan, atau ada yang tidak bisa diatur |
| 🔵 Ringan | 4 | Kerapian: data tersimpan tapi tak terpakai, atau catatan teknis yang basi |

## Tiga yang paling berdampak

**1. Retur penjualan tidak mengurangi omzet dan laba di laporan.**
Kalau pelanggan mengembalikan barang, barangnya kembali ke stok dengan benar
— tapi omzet dan laba di Laporan tetap dihitung penuh seolah barang itu
tetap terjual. Artinya laba di layar lebih besar dari yang sebenarnya Anda
terima. Belum terasa sekarang karena belum ada retur sama sekali, tapi begitu
retur pertama terjadi, angkanya langsung meleset.

**2. Barang bisa hilang diam-diam dari layar kasir.**
Sejak harga jual dibuat per suplier, barang yang stoknya datang dari suplier
yang harga jualnya belum Anda isi **tidak muncul sama sekali** di daftar
Transaksi Penjualan. Tidak ada peringatan, tidak ada tanda. Kasir hanya
melihat barangnya tidak ada, padahal barangnya ada di rak. Saat ini kebetulan
semua suplier sudah terisi harganya, tapi ini akan terjadi setiap kali ada
suplier baru mengirim barang.

**3. "Lupa Kata Sandi" tidak melakukan apa pun.**
Layarnya menampilkan pesan *"Permintaan reset kata sandi telah dikirim"*,
padahal tidak ada apa pun yang dikirim atau dicatat. Karyawan yang lupa
sandinya akan menunggu balasan yang tidak akan pernah datang.

## Yang sudah terbukti aman

- Stok total selalu sama persis dengan penjumlahan batch-nya (aturan paling
  penting di sistem ini).
- Setiap penjualan tercatat modalnya per batch, jadi laba per transaksi
  memakai harga beli yang benar-benar terjadi — bukan harga terbaru.
- Retur tidak bisa melebihi yang pernah terjual/dibeli, dan tidak bisa
  memakai nomor nota karangan.
- Nota tidak bisa dihapus kalau barangnya sudah terlanjur bergerak.
- Pembatasan Owner vs Karyawan konsisten: dijaga di layar **dan** di server,
  bukan cuma disembunyikan tampilannya.

---

# Bagian B — Daftar Temuan

## 🔴 T-01 · Retur penjualan tidak mengurangi omzet & laba

**Apa yang terjadi.** Saat pelanggan mengembalikan barang, sistem menambah
stok kembali dengan benar dan mencatat returnya. Tapi nota penjualan aslinya
tidak disentuh sama sekali, dan tidak ada satu pun laporan yang mengurangkan
retur dari omzet. Akibatnya barang yang sama dihitung dua kali: sebagai
penjualan (omzet + laba) **dan** sebagai stok yang kembali ada di rak.

**Contoh nyatanya di toko.** Pelanggan beli 5 aki seharga Rp 125.000, lalu
mengembalikan 3 karena salah tipe. Laporan Laba tetap menampilkan omzet
Rp 125.000 dan laba dari 5 aki, padahal uang yang benar-benar Anda pegang
hanya dari 2 aki. Semakin sering ada retur, semakin jauh melesetnya.

**Seberapa berat.** 🔴 Berat — langsung memengaruhi angka uang yang Anda
pakai untuk mengambil keputusan.

**Usulan perbaikan.** Kurangkan nilai retur dari omzet & laba di Laporan
Laba, Laporan Global, dan Laporan Penjualan. Cara paling jujur: tampilkan
tiga baris terpisah — *Omzet Kotor*, *Dikurangi Retur*, dan *Omzet Bersih* —
supaya Anda tetap bisa melihat berapa banyak yang diretur, bukan sekadar
angka akhir yang sudah dipotong diam-diam.

**Letak teknisnya.** `backend/api/laporan/laba.php`,
`backend/api/laporan/global.php`, `backend/api/laporan/penjualan.php`,
`backend/api/laporan/top_produk.php` — semuanya menghitung dari
`penjualan.grand_total` tanpa menyentuh tabel `retur_penjualan`.

---

## 🔴 T-02 · Barang hilang dari layar kasir tanpa peringatan

**Apa yang terjadi.** Daftar barang di Transaksi Penjualan hanya menampilkan
kombinasi barang+suplier yang **sudah ada harga jualnya** di *Ubah Barang →
Harga Jual per Suplier*. Yang belum terisi disaring keluar tanpa pesan apa
pun. Kalau semua suplier suatu barang belum terisi harganya, barang itu lenyap
sepenuhnya dari layar kasir.

**Contoh nyatanya di toko.** Suplier baru mengirim 20 pcs busi. Stok masuk,
Laporan Stok menunjukkan 20 pcs, barangnya ada di rak. Tapi kasir mengetik
"busi" dan tidak menemukan apa-apa. Tidak ada yang memberi tahu bahwa
penyebabnya adalah harga jual untuk suplier baru itu belum diisi.

**Seberapa berat.** 🔴 Berat — barang ada, tapi tidak bisa dijual, dan tidak
ada satu pun petunjuk kenapa.

**Usulan perbaikan.** Dua lapis:
1. Di **Data Barang**, beri tanda pada barang yang punya stok dari suplier
   yang harganya belum diisi — mirip tanda "Perlu Dilengkapi" yang sudah ada
   untuk barang baru.
2. Di **Transaksi Penjualan**, tetap tampilkan barangnya di daftar tapi
   dalam keadaan tidak bisa dipilih, dengan keterangan *"harga jual dari
   suplier X belum diisi"* — jadi kasir tahu harus lapor apa ke Anda.

**Letak teknisnya.** `Dashboard.dc.html:2524` (penyaringan
`sup.harga_ecer != null`), dan `backend/api/penjualan.php:134-139` (penolakan
di sisi server, yang pesannya sudah bagus tapi tidak pernah terlihat karena
barangnya sudah disaring lebih dulu di layar).

---

## 🔴 T-03 · "Lupa Kata Sandi" hanya berpura-pura mengirim

**Apa yang terjadi.** Form "Lupa Kata Sandi" di halaman Masuk menerima email
atau nomor HP, lalu menampilkan *"Permintaan reset kata sandi telah dikirim
ke ... Owner akan menghubungi Anda dengan kata sandi baru."* Kenyataannya
tombol itu hanya mengganti tampilan layar. Tidak ada email terkirim, tidak
ada pesan tersimpan, tidak ada catatan yang bisa Anda lihat.

**Contoh nyatanya di toko.** Karyawan lupa sandi hari Minggu, mengisi form
itu, membaca "sudah dikirim", lalu menunggu. Anda tidak pernah tahu ada yang
minta. Senin pagi kasir tidak bisa buka aplikasi.

**Seberapa berat.** 🔴 Berat — bukan karena rusaknya besar, tapi karena
aplikasi mengatakan sesuatu yang tidak benar kepada pemakainya.

**Usulan perbaikan.** Paling murah dan paling jujur: ganti isi layarnya
menjadi petunjuk langsung — *"Hubungi Owner di 0815-5608-055 untuk minta
kata sandi baru."* Hilangkan kotak isian dan tombol kirimnya sekalian. Kalau
nanti benar-benar ingin ada permintaan yang tercatat, itu pekerjaan
tersendiri (perlu tabel baru + tampilan daftar permintaan untuk Owner).

**Letak teknisnya.** `Dashboard.dc.html:2638-2640` (fungsi `submitForgot`
hanya menjalankan `setState({ forgotSent: true })`), teks palsunya di
`Dashboard.dc.html:285`.

---

## 🟡 T-04 · Stok minimum tidak bisa diubah sama sekali

**Apa yang terjadi.** Ambang peringatan stok — yang menentukan sebuah barang
berstatus **Kritis**, **Menipis**, atau **Aman** — disimpan per barang dengan
nama *stok minimum*. Nilainya ikut dikirim setiap kali Anda menyimpan form
Ubah Barang, tapi **tidak ada kotak isian untuk mengubahnya** di layar mana
pun. Nilainya terkunci di angka bawaan 10 untuk setiap barang, selamanya.

**Contoh nyatanya di toko.** Oli yang laku 30 pcs sebulan dan aki yang laku
2 pcs sebulan sama-sama baru dianggap "Menipis" di angka 10. Untuk oli itu
sudah terlambat; untuk aki itu peringatan palsu yang muncul terus-menerus
sampai Anda berhenti memperhatikannya.

**Seberapa berat.** 🟡 Sedang — tidak merusak data, tapi membuat fitur
peringatan stok kehilangan gunanya.

**Usulan perbaikan.** Tambahkan satu kotak isian **"Stok Minimum"** di form
Ubah Barang, bersebelahan dengan kotak "Stok" yang sudah ada. Seluruh jalur
penyimpanannya sudah siap — yang hilang benar-benar hanya kotak isiannya.

**Letak teknisnya.** `Dashboard.dc.html:2372` (nilai awal `minStok: ''`),
`:3157` & `:3161` (dibaca & divalidasi), `:3168` (dikirim ke server) — tapi
tidak ada `<input>` untuk `stokForm.minStok` di seluruh berkas. Aturan
statusnya di `backend/api/barang.php:9-13`.

---

## 🟡 T-05 · Kolom "Suplier" menampilkan suplier yang salah

**Apa yang terjadi.** Dua masalah yang menumpuk pada kolom yang sama:

1. Kolom Suplier di Data Barang dan Laporan Stok menampilkan **suplier
   pembelian terakhir**, bukan suplier dari stok yang sekarang ada di rak.
2. Ada dropdown "Suplier" di form Ubah Barang yang bisa Anda pilih — tapi
   pilihan Anda **ditimpa otomatis** oleh pembelian berikutnya.

**Contoh nyatanya di toko — ini terjadi pada data Anda sekarang.**
Barang **AKI-010 (AKI ClubOne)** tercatat suplier **"CV Anugrah service"**.
Kenyataannya stok yang tersisa 100% berasal dari **"PT Eka Jaya"** — batch
milik CV Anugrah service sudah habis terjual. Kalau Anda menyaring Laporan
Stok berdasarkan suplier, hasilnya akan salah.

**Seberapa berat.** 🟡 Sedang — tidak merusak angka stok, tapi salah menuntun
saat Anda mau memutuskan mesti pesan ulang ke siapa.

**Usulan perbaikan.** Ganti isi kolom itu dengan suplier dari **stok yang
benar-benar masih ada**. Kalau lebih dari satu, tampilkan seperti
*"PT Eka Jaya +1"* — pola yang sudah dipakai di tempat lain di aplikasi ini.
Lalu hapus dropdown "Suplier" dari form Ubah Barang, karena isinya memang
tidak bisa dipertahankan.

**Letak teknisnya.** `backend/api/pembelian.php:146` (`suplier_id = ?`
menimpa setiap kali ada pembelian), `backend/api/barang.php:63` (kolom yang
ditampilkan diambil dari situ), dropdown-nya di `Dashboard.dc.html:840`.

---

## 🟡 T-06 · Tanggal pembelian selalu dianggap hari ini

**Apa yang terjadi.** Input Pembelian tidak punya kotak isian tanggal. Sistem
selalu memakai tanggal hari ini sebagai tanggal faktur. Padahal Retur
Penjualan dan Retur Pembelian dua-duanya punya kotak isian tanggal — jadi
aplikasi ini tidak konsisten dengan dirinya sendiri.

**Contoh nyatanya di toko.** Faktur suplier tertanggal 10 Agustus baru
sempat Anda input tanggal 15. Di Laporan Pembelian, belanja itu tercatat
tanggal 15. Kalau Anda menutup laporan bulanan atau membandingkan dengan
faktur fisik, angkanya tidak akan ketemu.

Ada efek sampingan yang lebih halus: tanggal ini juga dipakai untuk
mengurutkan batch mana yang terjual duluan. Faktur lama yang diinput
belakangan akan dianggap batch paling baru, jadi stok lama bisa mengendap
lebih lama dari seharusnya.

**Seberapa berat.** 🟡 Sedang — bisa dihindari kalau selalu input di hari
yang sama, tapi jangan diandalkan.

**Usulan perbaikan.** Tambahkan kotak isian **"Tanggal Faktur"** di Input
Pembelian, isinya otomatis hari ini tapi bisa diubah — persis seperti yang
sudah ada di kedua form Retur.

**Letak teknisnya.** `backend/api/pembelian.php:95` (`$tanggal =
date('Y-m-d')`) dan `:183` (`VALUES (?, CURDATE(), ...)`). Di layar, "Tanggal
Faktur" hanya tulisan, bukan isian.

---

## 🟡 T-07 · "Total Nilai Jual" memakai harga default, bukan harga per suplier

**Apa yang terjadi.** Ringkasan **Total Nilai Jual** di Laporan Stok dihitung
dengan rumus *seluruh stok × harga ecer default barang*. Sejak harga jual
dibuat per suplier, harga default itu bukan lagi harga yang benar-benar
ditagih ke pelanggan — harga aslinya bisa berbeda per suplier. Kolom Harga
Ecer/Bengkel/Grosir di tabelnya juga masih menampilkan harga default yang
sama.

**Contoh nyatanya di toko — dihitung dari data Anda sekarang.**

| Cara hitung | Hasil |
|---|---|
| Rumus yang dipakai sekarang (stok × harga default) | Rp 274.945 |
| Harga per suplier yang sebenarnya berlaku | Rp 274.955 |

Selisihnya baru Rp 10 karena toko baru punya satu barang. Tapi rumusnya
memang tidak lagi mencerminkan harga sebenarnya, jadi selisihnya akan tumbuh
seiring bertambahnya barang dan suplier.

**Seberapa berat.** 🟡 Sedang — angkanya salah, tapi belum banyak.

**Usulan perbaikan.** Hitung Total Nilai Jual dari sisa stok per batch dikali
harga jual suplier batch itu (untuk stok hasil koreksi manual yang tidak
punya suplier, harga default memang jawaban yang benar). Untuk kolom harga di
tabel layar & PDF, tampilkan rentangnya (mis. *"Rp 24.000 – 25.000"*) kalau
suplier-suplier barang itu memang beda harga.

Catatan: Export **Excel** sudah benar — sudah dipecah satu baris per suplier
dengan harga masing-masing. Yang perlu menyusul adalah tampilan layar, PDF,
dan angka ringkasannya.

**Letak teknisnya.** `Dashboard.dc.html:4180`
(`filtered.reduce((a, p) => a + p.stok * p.ecer, 0)`) dan `:3501`
(baris tabel untuk PDF).

---

## 🔵 T-08 · Jumlah Bayar & Kembalian tersimpan tapi tak pernah bisa dilihat lagi

**Apa yang terjadi.** Saat transaksi tunai, kasir mengisi "Jumlah Bayar" dan
sistem menghitung kembalian. Keduanya tersimpan rapi di database. Tapi setelah
struk tercetak, tidak ada satu layar pun yang bisa menampilkannya lagi —
tidak di Riwayat Penjualan, tidak di Laporan Penjualan, tidak di dialog rincian
nota (yang hanya menampilkan pelanggan, metode bayar, dan total).

**Contoh nyatanya di toko.** Pelanggan kembali sore hari mengaku kembaliannya
kurang. Datanya ada di dalam sistem, tapi tidak ada cara melihatnya dari
aplikasi.

**Seberapa berat.** 🔵 Ringan — datanya aman, cuma tidak terlihat.

**Usulan perbaikan.** Tambahkan dua baris "Jumlah Bayar" dan "Kembalian" di
dialog rincian nota penjualan. Datanya sudah ikut terkirim dari server —
tinggal ditampilkan.

**Letak teknisnya.** `Dashboard.dc.html:4231-4234` (`laporanPenjualanDetailView`
mengabaikan `amount_paid` dan `kembalian` yang sudah ada di data).

---

## 🔵 T-09 · Ada satu berkas laporan di server yang tidak pernah dipakai

**Apa yang terjadi.** Berkas `backend/api/laporan/stok.php` berisi Laporan
Stok lengkap dengan penyaringan dan perhitungan totalnya, tapi **tidak pernah
dipanggil sama sekali** oleh aplikasi. Laporan Stok yang Anda lihat sebenarnya
diambil dari daftar barang biasa lalu disaring di dalam browser.

**Kenapa ini perlu diberi tahu.** Berkas itu punya salinan aturan Kritis /
Menipis / Aman sendiri. Kalau suatu hari aturannya diubah di satu tempat dan
lupa diubah di satunya, akan muncul dua versi kebenaran yang tidak pernah
ketahuan mana yang dipakai.

**Seberapa berat.** 🔵 Ringan — tidak ada efek ke Anda hari ini.

**Usulan perbaikan.** Hapus berkas itu, atau justru pakai (lebih baik dipakai:
menyaring di server lebih ringan kalau nanti barangnya ribuan). Yang penting
jangan dibiarkan menganggur dan mendua.

**Letak teknisnya.** `backend/api/laporan/stok.php` (tidak ada satu pun
pemanggil di `Dashboard.dc.html`); aturan yang kembar ada di
`backend/api/barang.php:9-13`.

---

## 🔵 T-10 · "Pricelist" tersimpan tapi tidak dipakai hitungan apa pun

**Apa yang terjadi.** Kolom Pricelist di Input Pembelian tersimpan per baris
barang dan bisa dilihat lagi di rincian nota pembelian. Tapi tidak ada satu
pun perhitungan, laporan, atau harga jual yang memakainya.

**Seberapa berat.** 🔵 Ringan — bukan kesalahan kalau memang dimaksudkan
sebagai catatan saja.

**Usulan perbaikan.** Kalau memang cuma catatan, biarkan — tapi ubah labelnya
jadi *"Pricelist (catatan)"* supaya jelas tidak ikut hitungan. Kalau
sebenarnya Anda ingin harga jual dihitung dari pricelist, itu fitur baru yang
perlu dibicarakan tersendiri.

**Letak teknisnya.** `backend/api/pembelian.php:119` (disimpan),
`Dashboard.dc.html:4225` (ditampilkan) — tidak muncul di berkas laporan mana
pun.

---

## 🔵 T-11 · Catatan teknis tentang tingkat harga sudah basi

**Apa yang terjadi.** Catatan di berkas rancangan database menulis batas
tingkat harga sebagai *ecer 1–5 pcs, bengkel 6–10 pcs, grosir 11–100 pcs*.
Aturan yang benar-benar berjalan adalah **ecer 1 pcs, bengkel 2–5 pcs, grosir
6 pcs ke atas**.

**Kabar baiknya:** yang salah hanya catatan untuk pemrogram. Buku FAQ yang
Anda pakai sudah menulis aturan yang benar, dan aplikasinya sendiri berjalan
sesuai aturan yang benar. Jadi tidak ada pengaruhnya ke transaksi.

**Seberapa berat.** 🔵 Ringan.

**Usulan perbaikan.** Perbaiki tiga baris catatan itu supaya orang yang
mengerjakan aplikasi ini di kemudian hari tidak salah paham.

**Letak teknisnya.** `backend/schema.sql:69-71` versus
`backend/api/penjualan.php:10-14`.

---

## Catatan: satu perbedaan yang memang disengaja

Export **Excel** Laporan Stok memecah barang multi-suplier jadi satu baris per
suplier, sedangkan export **Cetak/PDF** tetap satu baris per barang. Ini bukan
kesalahan — PDF sengaja dibuat ringkas supaya muat dicetak di kertas, Excel
sengaja dibuat rinci supaya bisa diolah lagi. Dicatat di sini supaya tidak
dikira temuan saat dibandingkan.

---

# Bagian C — Tabel Keterhubungan Input

Setiap kotak isian di aplikasi, ditelusuri sampai tempat penyimpanannya.
Empat kemungkinan vonis:

- ✅ **Tersambung penuh** — tersimpan, terpakai, dan bisa dilihat lagi.
- ⚠️ **Tersimpan tapi tidak terpakai** — masuk database, tapi tidak
  memengaruhi perhitungan/laporan apa pun.
- ⚠️ **Terpakai tapi tidak bisa diisi** — sistem memakainya, tapi Anda tidak
  punya cara mengubahnya.
- ❌ **Menggantung** — tidak ke mana-mana.

## Masuk / Login

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Nama Pengguna | — (dicocokkan) | Masuk aplikasi | ✅ |
| Kata Sandi | — (dicocokkan) | Masuk aplikasi | ✅ |
| Email / No. HP (Lupa Sandi) | **tidak ke mana-mana** | — | ❌ **T-03** |

## Data Barang → Ubah Barang (Owner)

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Kode | — | dikunci saat ubah (benar) | ✅ |
| Nama Barang | `barang.nama` | Semua tampilan & struk | ✅ |
| Kategori | `barang.kategori_id` | Penyaringan & label | ✅ |
| Suplier | `barang.suplier_id` | Kolom Suplier — **ditimpa pembelian berikutnya** | ⚠️ **T-05** |
| Stok | `barang.stok` + batch | Koreksi stok opname | ✅ |
| *Stok Minimum* | `barang.min_stok` | Status Kritis/Menipis/Aman | ⚠️ **tidak ada kotak isiannya — T-04** |
| Harga Faktur / Netto | `barang.harga_faktur` / `harga_netto` | Modal & laporan | ✅ |
| Basis Price List | `barang.price_list_basis` | Menentukan basis harga beli | ✅ |
| Harga Ecer / Bengkel / Grosir (default) | `barang.harga_*` | Harga stok tanpa suplier + bahan tombol "Salin" | ✅ |
| Harga per Suplier (Ecer/Bengkel/Grosir) | `barang_suplier_harga` | **Harga jual sebenarnya di kasir** | ✅ |

## Input Pembelian (Owner)

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Suplier | `pembelian.suplier_id` | Nota, batch, laporan | ✅ |
| No. Faktur | `pembelian.no_faktur` | Identitas nota, acuan retur | ✅ |
| *Tanggal Faktur* | `pembelian.tanggal` | Laporan & urutan batch | ⚠️ **tidak ada kotak isiannya, selalu hari ini — T-06** |
| Metode Bayar (Cash/TOP) | `pembelian.payment_type` | Label di riwayat | ✅ |
| Jatuh Tempo | `pembelian.jatuh_tempo` | Label di riwayat | ✅ |
| Barang / Kode baru / Nama / Kategori | `pembelian_item` + `barang` | Buat/tambah stok | ✅ |
| Qty | `pembelian_item.qty` + batch | Stok & modal | ✅ |
| Harga Beli (Faktur / Netto) | `pembelian_item` + batch | Modal di Laporan Laba | ✅ |
| Pricelist | `pembelian_item.pricelist` | hanya ditampilkan di rincian | ⚠️ **T-10** |

## Transaksi Penjualan (Owner & Karyawan)

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Nama Pelanggan | `penjualan.cust_name` + master pelanggan | Struk & laporan | ✅ |
| Cari Barang (barang + suplier) | `penjualan_item` + rincian batch | Harga jual & pengurangan stok | ✅ |
| Jumlah | `penjualan_item.qty` | Tingkat harga & stok | ✅ |
| Metode Bayar | `penjualan.payment_method` | Struk & laporan | ✅ |
| Jumlah Bayar | `penjualan.amount_paid` | Hitung kembalian, lalu tidak pernah ditampilkan lagi | ⚠️ **T-08** |
| *(Kembalian)* | `penjualan.kembalian` | Struk saat itu saja | ⚠️ **T-08** |

## Retur Penjualan & Retur Pembelian

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| No. Invoice / Faktur Asal | `retur_*.original_invoice_no` | Diverifikasi ke nota asli | ✅ |
| Pelanggan / Suplier | `retur_*` | Nota retur & laporan | ✅ |
| Tanggal | `retur_*.tanggal` | Laporan Retur | ✅ |
| Qty per barang | `retur_*_item.qty` | Stok & batch | ✅ (tapi tidak mengurangi omzet — **T-01**) |
| Alasan | `retur_*_item.reason` | Laporan Retur | ✅ |

## Master Data & Profil Toko (Owner)

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Suplier: Nama / Alamat / No. HP | `suplier` | Daftar & nota | ✅ |
| Pelanggan: Nama / Alamat / No. HP | `pelanggan` | Daftar & nota | ✅ |
| Kategori: Nama | `kategori` | Penyaringan & label | ✅ |
| Pengguna: Username / Role / Password | `users` | Masuk & hak akses | ✅ |
| Profil Toko: Nama / Alamat / No. Telp | `toko_profil` | Kop struk & laporan | ✅ |

## Penyaringan Laporan

| Isian | Disimpan di | Dipakai untuk | Vonis |
|---|---|---|---|
| Cari / Kategori / Suplier / Status | — (sementara) | Menyaring tampilan & export | ✅ |
| Tanggal Dari / Sampai | — (sementara) | Rentang tanggal semua laporan | ✅ |

**Kesimpulan Bagian C:** dari 38 isian yang ditelusuri, **1 menggantung
total** (T-03), **4 tersimpan tapi tidak sepenuhnya terpakai** (T-05, T-08 ×2,
T-10), **2 dipakai sistem tapi tidak bisa Anda isi** (T-04, T-06), dan
**31 sisanya tersambung penuh**.

---

# Bagian D — Hasil Pemeriksaan Konsistensi Data

Sepuluh aturan dijalankan langsung ke database toko pada 15 Agustus 2026.
Isi database saat pemeriksaan: 1 barang, 3 nota pembelian, 1 nota penjualan,
4 batch stok, 2 baris harga per suplier, 0 retur.

| # | Aturan yang diuji | Hasil | Kalau gagal artinya |
|---|---|---|---|
| 1 | Stok setiap barang = penjumlahan sisa semua batch-nya | ✅ **Lolos** | Stok dan rinciannya berbeda; penjualan barang itu akan ditolak sistem |
| 2 | Total nota penjualan = penjumlahan rincian barangnya | ✅ **Lolos** | Struk dan laporan menampilkan uang yang berbeda |
| 3 | Total nota pembelian = penjumlahan rincian barangnya | ✅ **Lolos** | Belanja tercatat lebih besar/kecil dari yang dibayar |
| 4 | Qty terjual = penjumlahan qty yang ditarik dari batch | ✅ **Lolos** | Modal di Laporan Laba kurang dari seharusnya |
| 5 | Setiap barang terjual punya catatan batch asalnya | ✅ **Lolos** | Modal dihitung nol; laba terlihat jauh lebih besar dari kenyataan |
| 6 | Tidak ada batch bersisa negatif atau melebihi jumlah awalnya | ✅ **Lolos** | Angka stok tidak masuk akal |
| 7 | Semua stok bersuplier sudah ada harga jualnya | ✅ **Lolos** | Stok itu tidak bisa dijual dari kasir (lihat T-02) |
| 8 | Harga per suplier tidak menggantung tanpa stok | ⚠️ **1 baris** | Bukan kesalahan: harga CV Anugrah service tersimpan sementara stoknya sudah habis. Justru bagus — kalau suplier itu kirim lagi, harganya sudah siap |
| 9 | Kolom Suplier cocok dengan asal stok yang sebenarnya | ❌ **1 barang meleset** | AKI-010 tertulis "CV Anugrah service", stok nyatanya dari "PT Eka Jaya" — lihat **T-05** |
| 10 | Total Nilai Jual sesuai harga yang benar-benar berlaku | ❌ **Selisih Rp 10** | Rp 274.945 (dilaporkan) vs Rp 274.955 (sebenarnya) — lihat **T-07** |

**Kesimpulannya:** delapan pemeriksaan pertama — yang semuanya menyangkut
keutuhan stok dan uang — **lolos bersih**. Dua yang gagal bukan kerusakan
data, melainkan akibat dari cara menampilkan yang sudah tertinggal dari
perubahan fitur harga-per-suplier (T-05 dan T-07).

Query untuk sepuluh pemeriksaan ini ada di **Bagian F**, supaya bisa Anda
jalankan sendiri kapan saja setelah datanya bertambah banyak.

---

# Bagian E — Catatan per Laporan

## 1. Laporan Global (Owner)

- **Sumber angkanya:** omzet dari total semua nota penjualan; modal dari harga
  beli batch yang benar-benar terjual; belanja dari total nota pembelian.
- **Yang perlu diwaspadai:** retur belum dikurangkan (**T-01**). "Pelanggan
  Dilayani" dihitung dari nama pelanggan yang berbeda, jadi dua pelanggan
  bernama sama terhitung satu orang, dan pelanggan tanpa nama semuanya
  terhitung sebagai satu "Umum".
- **Usulan agar lebih jelas:** beri keterangan kecil di bawah "Laba Bersih"
  bahwa angka itu sudah dikurangi modal barang tapi belum dikurangi biaya
  operasional (listrik, gaji, sewa) — supaya tidak dikira uang bersih yang
  masuk kantong.

## 2. Laporan Laba (Owner)

- **Sumber angkanya:** per nota — omzet nota dikurangi harga beli batch yang
  terjual di nota itu. Ini cara yang benar: modalnya memakai harga beli saat
  barang itu benar-benar masuk, bukan harga terbaru.
- **Yang perlu diwaspadai:** retur belum dikurangkan (**T-01**).
- **Usulan agar lebih jelas:** ganti judul "Laba" jadi **"Laba Kotor"**, karena
  yang dipotong baru modal barang.

## 3. Laporan Stok (Owner & Karyawan)

- **Sumber angkanya:** daftar barang, disaring di dalam browser.
- **Yang perlu diwaspadai:** kolom Suplier bisa salah (**T-05**); Total Nilai
  Jual memakai harga default (**T-07**); ada berkas laporan di server yang
  menganggur (**T-09**).
- **Usulan agar lebih jelas:** ganti "Total Nilai Jual" jadi **"Perkiraan
  Nilai Jual Stok"** — kata *perkiraan* penting, karena harga akhir tergantung
  jumlah yang dibeli pelanggan (ecer/bengkel/grosir), sedangkan angka ini
  selalu memakai harga ecer.

## 4. Laporan Penjualan (Owner)

- **Sumber angkanya:** daftar nota penjualan + rekap per pelanggan.
- **Yang perlu diwaspadai:** retur belum dikurangkan (**T-01**); Jumlah Bayar
  & Kembalian tidak ditampilkan (**T-08**).
- **Usulan agar lebih jelas:** sudah cukup jelas selain dua hal di atas.

## 5. Laporan Pembelian (Owner)

- **Sumber angkanya:** daftar nota pembelian + rekap per suplier.
- **Yang perlu diwaspadai:** tanggalnya adalah tanggal input, bukan tanggal
  faktur fisik (**T-06**).
- **Usulan agar lebih jelas:** setelah T-06 diperbaiki, laporan ini bisa
  dipercaya untuk pencocokan bulanan dengan faktur fisik.

## 6. Laporan Retur (Owner)

- **Sumber angkanya:** retur penjualan dan retur pembelian, digabung.
- **Yang perlu diwaspadai:** hanya menampilkan jumlah barang, **tidak ada nilai
  rupiahnya sama sekali**. Jadi tidak bisa dipakai menjawab "retur bulan ini
  merugikan berapa rupiah".
- **Usulan agar lebih jelas:** tambahkan kolom nilai rupiah per baris dan
  totalnya. Ini juga jadi bahan yang dibutuhkan untuk memperbaiki **T-01**.

## 7. Stok Laris / Produk Terlaris (Owner & Karyawan)

- **Sumber angkanya:** penjumlahan qty & omzet per barang; laba hanya
  ditampilkan untuk Owner.
- **Yang perlu diwaspadai:** retur belum dikurangkan (**T-01**) — barang yang
  banyak diretur bisa terlihat sebagai barang terlaris.
- **Usulan agar lebih jelas:** cara hitungnya sudah ditulis rapi (sengaja
  menghindari penggandaan baris saat satu penjualan diambil dari beberapa
  batch) — tidak ada masalah teknis di sini.

## 8. Tren Penjualan (Owner)

- **Sumber angkanya:** total penjualan per hari, hari kosong diisi nol supaya
  grafiknya tidak bolong.
- **Yang perlu diwaspadai:** retur belum dikurangkan (**T-01**).
- **Usulan agar lebih jelas:** tidak ada — laporan ini paling sederhana dan
  paling aman.

## Pembatasan akses — sudah benar

Diperiksa dan konsisten di semua lapisan: Laporan Global, Laba, Pembelian,
Retur, dan Tren hanya untuk Owner; Laporan Stok dan Produk Terlaris boleh
dilihat Karyawan tapi **angka laba dan harga belinya dibuang di server**,
bukan sekadar disembunyikan di layar. Ini penting: kalau hanya disembunyikan
di layar, angkanya masih bisa dilihat siapa pun yang tahu caranya.

---

# Bagian F — Query Pemeriksaan Mandiri

Sepuluh query di bawah bisa Anda jalankan sendiri kapan saja lewat
**phpMyAdmin** (XAMPP → Admin → pilih database `app_mini` → tab **SQL** →
tempel → **Go**). Semuanya hanya membaca, tidak mengubah apa pun.

> **Cara membacanya:** untuk query 1–7, **hasil kosong berarti aman.** Kalau
> ada baris yang muncul, baris itulah yang bermasalah.

**1. Stok meleset dari rincian batch-nya** — pemeriksaan terpenting.

```sql
SELECT b.kode, b.nama, b.stok, COALESCE(l.s, 0) AS jumlah_batch
FROM barang b
LEFT JOIN (SELECT barang_id, SUM(qty_sisa) s FROM barang_lot GROUP BY barang_id) l
  ON l.barang_id = b.id
WHERE b.stok <> COALESCE(l.s, 0);
```

**2. Total nota penjualan tidak cocok dengan rinciannya**

```sql
SELECT p.invoice_no, p.grand_total, SUM(i.subtotal) AS jumlah_rincian
FROM penjualan p JOIN penjualan_item i ON i.penjualan_id = p.id
GROUP BY p.id
HAVING p.grand_total <> SUM(i.subtotal) OR p.total_qty <> SUM(i.qty);
```

**3. Total nota pembelian tidak cocok dengan rinciannya**

```sql
SELECT p.no_faktur, p.total_biaya, SUM(i.subtotal) AS jumlah_rincian
FROM pembelian p JOIN pembelian_item i ON i.pembelian_id = p.id
GROUP BY p.id
HAVING p.total_biaya <> SUM(i.subtotal) OR p.total_qty <> SUM(i.qty);
```

**4. Qty terjual tidak sama dengan qty yang ditarik dari batch**

```sql
SELECT pi.id, pi.nama_snapshot, pi.qty, COALESCE(SUM(pil.qty), 0) AS qty_batch
FROM penjualan_item pi
LEFT JOIN penjualan_item_lot pil ON pil.penjualan_item_id = pi.id
GROUP BY pi.id
HAVING pi.qty <> COALESCE(SUM(pil.qty), 0);
```

**5. Barang terjual yang kehilangan catatan modalnya** — kalau ada isinya,
laba di laporan terlihat lebih besar dari kenyataan.

```sql
SELECT pj.invoice_no, pi.nama_snapshot, pi.qty
FROM penjualan_item pi JOIN penjualan pj ON pj.id = pi.penjualan_id
WHERE NOT EXISTS (
  SELECT 1 FROM penjualan_item_lot l WHERE l.penjualan_item_id = pi.id
);
```

**6. Batch dengan angka tidak masuk akal**

```sql
SELECT id, barang_id, qty_awal, qty_sisa FROM barang_lot
WHERE qty_sisa < 0 OR qty_sisa > qty_awal;
```

**7. Stok yang tidak bisa dijual karena harga suplier belum diisi** — inilah
daftar barang yang "hilang" dari layar kasir (T-02).

```sql
SELECT b.kode, b.nama, s.nama AS suplier, SUM(l.qty_sisa) AS stok_terkunci
FROM barang_lot l
JOIN barang b ON b.id = l.barang_id
JOIN suplier s ON s.id = l.suplier_id
LEFT JOIN barang_suplier_harga h
  ON h.barang_id = l.barang_id AND h.suplier_id = l.suplier_id
WHERE l.suplier_id IS NOT NULL AND l.qty_sisa > 0 AND h.id IS NULL
GROUP BY b.id, s.id;
```

**8. Kolom Suplier vs asal stok yang sebenarnya** (T-05) — bandingkan dua
kolom terakhir; kalau berbeda, kolom Suplier di layar sedang menyesatkan.

```sql
SELECT b.kode, b.nama, su.nama AS tertulis_di_layar,
  (SELECT GROUP_CONCAT(DISTINCT s2.nama) FROM barang_lot l2
   JOIN suplier s2 ON s2.id = l2.suplier_id
   WHERE l2.barang_id = b.id AND l2.qty_sisa > 0) AS asal_stok_sebenarnya
FROM barang b LEFT JOIN suplier su ON su.id = b.suplier_id;
```

**9. Total Nilai Jual: rumus sekarang vs harga sebenarnya** (T-07)

```sql
SELECT
  (SELECT SUM(stok * harga_ecer) FROM barang) AS rumus_sekarang,
  (SELECT SUM(l.qty_sisa * COALESCE(h.harga_ecer, b.harga_ecer))
   FROM barang_lot l
   JOIN barang b ON b.id = l.barang_id
   LEFT JOIN barang_suplier_harga h
     ON h.barang_id = l.barang_id AND h.suplier_id = l.suplier_id
   WHERE l.qty_sisa > 0) AS harga_sebenarnya;
```

**10. Berapa omzet yang sebenarnya sudah diretur** (T-01) — angka inilah yang
seharusnya dikurangkan dari Laporan Laba.

```sql
SELECT rp.no_retur, rp.tanggal, rp.original_invoice_no, ri.nama_snapshot,
       ri.qty, pi.unit_price, ri.qty * pi.unit_price AS nilai_retur
FROM retur_penjualan rp
JOIN retur_penjualan_item ri ON ri.retur_id = rp.id
JOIN penjualan pj ON pj.invoice_no = rp.original_invoice_no
JOIN penjualan_item pi ON pi.penjualan_id = pj.id AND pi.barang_id = ri.barang_id;
```

---

# Bagian G — Daftar Tindak Lanjut

Diurutkan dari yang paling layak dikerjakan duluan. Anda tinggal menunjuk
nomornya.

| Urutan | Temuan | Kenapa didahulukan | Perkiraan besarnya pekerjaan |
|---|---|---|---|
| 1 | **T-04** Kotak isian Stok Minimum | Perbaikan paling murah di daftar ini — seluruh jalurnya sudah ada, tinggal satu kotak isian. Langsung menghidupkan kembali peringatan stok | Kecil |
| 2 | **T-03** Ganti "Lupa Kata Sandi" jadi petunjuk hubungi Owner | Menghapus kebohongan di layar. Cukup mengganti isi satu dialog | Kecil |
| 3 | **T-08** Tampilkan Jumlah Bayar & Kembalian di rincian nota | Datanya sudah lengkap terkirim, tinggal ditampilkan | Kecil |
| 4 | **T-02** Peringatan barang yang harga suplier-nya belum diisi | Mencegah barang hilang dari kasir tanpa penjelasan. Perlu tanda di dua layar | Sedang |
| 5 | **T-06** Kotak isian Tanggal Faktur di Input Pembelian | Membuat Laporan Pembelian bisa dicocokkan dengan faktur fisik. Polanya sudah ada di form Retur | Sedang |
| 6 | **T-05** Kolom Suplier ikut asal stok sebenarnya | Menghilangkan informasi yang menyesatkan saat memutuskan pesan ulang ke siapa | Sedang |
| 7 | **T-07** Total Nilai Jual & kolom harga ikut harga per suplier | Menyelaraskan Laporan Stok dengan harga yang benar-benar ditagih | Sedang |
| 8 | **T-01** Retur dikurangkan dari omzet & laba | Paling berdampak ke angka uang, tapi paling besar pekerjaannya — menyentuh 4 laporan sekaligus dan sebaiknya dikerjakan bersama penambahan nilai rupiah di Laporan Retur | Besar |
| 9 | **T-10** Perjelas label Pricelist | Kerapian | Kecil |
| 10 | **T-09** Rapikan berkas laporan stok yang menganggur | Kerapian, mencegah salah paham di kemudian hari | Kecil |
| 11 | **T-11** Perbaiki catatan teknis tingkat harga | Kerapian, tidak berpengaruh ke toko | Kecil |

**Saran urutan praktis:** kerjakan nomor 1–3 sekaligus dalam satu kali jalan
(ketiganya kecil dan tidak saling bergantung), lalu putuskan apakah nomor 4–7
dikerjakan satu per satu. Nomor 8 sebaiknya dijadwalkan tersendiri karena
mengubah cara angka laba dihitung — dan itu perlu Anda periksa hasilnya
sebelum dipakai untuk mengambil keputusan.
