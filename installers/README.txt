Installer QZ Tray (untuk cetak struk lewat ESC/POS)
=====================================================

File: qz-tray-2.2.6-x86_64.exe
Sumber resmi: https://github.com/qzind/tray/releases/tag/v2.2.6
Lisensi: LGPL-2.1 (lihat LICENSE.txt di sumber resmi di atas)
SHA-256: aeb93a601c27f5fa6bb464f63471e7acd43052ba384fef49dceec8290d4f7587

Apa ini?
--------
Aplikasi desktop Windows yang WAJIB diinstal & dijalankan di tiap PC kasir
yang mau pakai mode cetak "QZ Tray / ESC-POS" (Master Data > Printer di
app-mini). Tanpa ini, app-mini tetap jalan normal lewat mode "Browser
Print" (window.print(), seperti sebelumnya) -- QZ Tray cuma dibutuhkan
kalau mau cetak ESC/POS langsung tanpa dialog print.

File ini SENGAJA tidak ikut di-commit ke Git (lihat .gitignore) karena
ukurannya ~99MB, mepet batas ukuran file GitHub. Tetap ikut ter-copy ke
PC client lewat deploy-windows.bat (yang menyalin seluruh folder proyek
apa adanya) selama foldernya dipindahkan ke PC client lewat USB/zip/
copy-paste -- BUKAN "git clone" langsung dari GitHub (git clone tidak
akan membawa file yang di-gitignore ini).

Cara instal di PC kasir
------------------------
1. Jalankan qz-tray-2.2.6-x86_64.exe, ikuti wizard instalasinya (Next/Next/
   Install seperti instalasi Windows pada umumnya).
2. Sesudah terinstal, QZ Tray otomatis jalan di background -- cek ikon
   kecilnya muncul di system tray Windows (dekat jam, biasanya perlu klik
   panah "^" untuk melihat ikon tersembunyi).
3. Biarkan QZ Tray selalu jalan (biasanya otomatis start bareng Windows).
   Kalau ikonnya tidak ada, buka lagi lewat Start Menu > QZ Tray.
4. Di app-mini: Master Data > Printer > pilih mode "QZ Tray / ESC-POS" >
   klik "Cari Printer" untuk pastikan printernya terdeteksi > "Uji Cetak"
   untuk tes.
5. Kali pertama mencetak, QZ Tray akan menampilkan dialog "izinkan situs
   ini mencetak?" -- centang "remember this decision" supaya tidak muncul
   lagi tiap kali.

Cara update ke versi lebih baru
---------------------------------
Cek rilis terbaru di https://github.com/qzind/tray/releases, unduh file
"qz-tray-X.X.X-x86_64.exe" (Windows 64-bit), ganti file di folder ini.
