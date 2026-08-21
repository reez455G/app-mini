@echo off
setlocal EnableExtensions
REM ====================================================================
REM  Provisioning QZ Tray untuk app-mini -- signed connection SEKALI
REM  setup, supaya QZ Tray tidak pernah nanya izin lagi (bukan cuma
REM  sekali klik "Remember this decision" per browser/PC).
REM
REM  JALANKAN SEKALI per PC yang mau pakai mode cetak "QZ Tray / ESC-POS",
REM  SESUDAH: (1) app-mini sudah di-deploy (deploy-windows.bat), dan
REM  (2) QZ Tray sendiri sudah diinstal (installers\qz-tray-2.2.6-x86_64.exe).
REM
REM  Aman dijalankan berkali-kali: sertifikat CUMA dibuat kalau belum ada
REM  (satu sertifikat berlaku seumur instalasi toko ini -- lihat komentar
REM  di backend\qz_certgen.php). Mau rotasi sertifikat? Hapus folder
REM  qz-keys\ manual dulu, baru jalankan file ini lagi -- itu satu-satunya
REM  cara, tidak ada rotasi otomatis.
REM
REM  Script ini SENGAJA terpisah dari deploy-windows.bat (yang jalan tiap
REM  update) -- supaya update aplikasi biasa TIDAK PERNAH menyentuh
REM  sertifikat/trust yang sudah terpasang.
REM ====================================================================

set "XAMPP=C:\xampp"
if not "%~1"=="" set "XAMPP=%~1"
set "PHP=%XAMPP%\php\php.exe"
set "TARGET=%XAMPP%\htdocs\app-mini"
set "QZKEYS=%XAMPP%\qz-keys"
set "QZDIR=%ProgramFiles%\QZ Tray"

echo.
echo === Provisioning QZ Tray (signed connection, app-mini) ===
echo.

if not exist "%PHP%" (
  echo [GAGAL] Tidak menemukan "%PHP%".
  echo         Jalankan lagi seperti ini kalau XAMPP di folder lain:
  echo             provision-qz-signing.bat D:\xampp
  goto :fail
)
if not exist "%TARGET%\backend\qz_certgen.php" (
  echo [GAGAL] app-mini belum ter-deploy di "%TARGET%".
  echo         Jalankan deploy-windows.bat dulu, baru file ini.
  goto :fail
)

echo [1/3] Generate sertifikat ^& kunci penandatanganan...
"%PHP%" "%TARGET%\backend\qz_certgen.php"
if errorlevel 1 (
  echo [GAGAL] Generate sertifikat gagal -- lihat pesan di atas.
  goto :fail
)
if not exist "%QZKEYS%\digital-certificate.txt" (
  echo [GAGAL] Sertifikat tidak ditemukan di "%QZKEYS%" sesudah generate.
  goto :fail
)
echo       OK.

echo [2/3] Memasang sertifikat ke QZ Tray...
if not exist "%QZDIR%" (
  echo [GAGAL] QZ Tray belum terinstal di "%QZDIR%".
  echo         Instal dulu lewat installers\qz-tray-2.2.6-x86_64.exe,
  echo         lalu jalankan provision-qz-signing.bat ini lagi -- aman
  echo         diulang, sertifikat yang baru dibuat di atas tidak hilang.
  goto :fail
)
copy /y "%QZKEYS%\digital-certificate.txt" "%QZDIR%\override.crt" >nul
if errorlevel 1 (
  echo [GAGAL] Tidak bisa menyalin sertifikat ke folder QZ Tray.
  echo         Coba jalankan file ini sebagai Administrator.
  goto :fail
)
echo       OK.

echo [3/3] Mendaftarkan sertifikat ke daftar dipercaya QZ Tray...
if exist "%QZDIR%\qz-tray-console.exe" (
  "%QZDIR%\qz-tray-console.exe" --whitelist "%QZKEYS%\digital-certificate.txt"
  echo       OK.
) else (
  echo       [PERINGATAN] qz-tray-console.exe tidak ditemukan di "%QZDIR%".
  echo                    Sertifikat sudah terpasang ^(langkah 2^), tapi
  echo                    kasir kemungkinan masih akan diminta izin SEKALI
  echo                    di QZ Tray untuk koneksi pertama -- tidak fatal,
  echo                    centang "Remember this decision" saat itu terjadi.
)

echo.
echo === Selesai ===
echo   Restart QZ Tray supaya override.crt terbaca ^(klik kanan ikon di
echo   system tray Windows ^> Exit, lalu buka lagi lewat Start Menu^).
echo   Sesudah itu Master Data ^> Printer ^> mode "QZ Tray / ESC-POS" di
echo   PC ini seharusnya tidak minta izin lagi.
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
