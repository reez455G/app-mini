@echo off
setlocal EnableExtensions
REM ====================================================================
REM  Deploy App-mini ke XAMPP di Windows.
REM
REM  Cara pakai: taruh folder sumber ini di mana saja (hasil unzip / git
REM  clone), lalu klik dua kali file ini. Kalau XAMPP tidak di C:\xampp,
REM  jalankan lewat cmd dengan foldernya sebagai argumen:
REM      deploy-windows.bat D:\xampp
REM
REM  Script ini TIDAK pernah menyentuh data yang sudah ada: schema.sql
REM  cuma diimport kalau database app_mini belum ada. Update aplikasi
REM  berikutnya cukup jalankan ulang file ini.
REM ====================================================================

REM Isi kalau MySQL root di XAMPP Anda pakai password (default XAMPP kosong).
set "DBPASS="

set "XAMPP=C:\xampp"
if not "%~1"=="" set "XAMPP=%~1"
set "TARGET=%XAMPP%\htdocs\app-mini"
set "MYSQL=%XAMPP%\mysql\bin\mysql.exe"
set "SRC=%~dp0"

if defined DBPASS (set "PWARG=-p%DBPASS%") else (set "PWARG=")

echo.
echo === Deploy App-mini ===
echo   Sumber : %SRC%
echo   Tujuan : %TARGET%
echo.

if not exist "%MYSQL%" (
  echo [GAGAL] Tidak menemukan "%MYSQL%".
  echo         XAMPP-nya ada di folder lain? Jalankan lagi seperti ini:
  echo             deploy-windows.bat D:\xampp
  goto :fail
)

REM --- 1. Salin file aplikasi -----------------------------------------
REM /E salin semua subfolder, tanpa /MIR supaya tidak ada yang terhapus.
REM .git dan .claude bukan bagian aplikasi, tidak perlu ikut ke htdocs.
echo [1/3] Menyalin file aplikasi...
robocopy "%SRC%." "%TARGET%" /E /NFL /NDL /NJH /NJS /NP /XD ".git" ".claude" >nul
REM Robocopy: kode 0-7 sukses, 8 ke atas beneran error.
if %ERRORLEVEL% GEQ 8 (
  echo [GAGAL] Penyalinan file gagal ^(kode %ERRORLEVEL%^).
  echo         Coba jalankan file ini sebagai Administrator.
  goto :fail
)
echo       OK.

REM --- 2. Database ------------------------------------------------------
echo [2/3] Memeriksa database...
"%MYSQL%" -u root %PWARG% -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
  echo [GAGAL] Tidak bisa terhubung ke MySQL.
  echo         Nyalakan MySQL di XAMPP Control Panel, lalu jalankan lagi.
  echo         Kalau root-nya pakai password, isi DBPASS di baris atas file ini.
  goto :fail
)

REM Cek isi, bukan cuma ada-tidaknya database: kalau tabel users sudah ada
REM berarti toko ini sudah punya data sungguhan - schema.sql JANGAN diimport
REM lagi (isinya CREATE TABLE + akun demo, bukan migrasi).
"%MYSQL%" -u root %PWARG% -e "SELECT 1 FROM app_mini.users LIMIT 1" >nul 2>&1
if errorlevel 1 (
  echo       Database belum ada, mengimport backend\schema.sql...
  "%MYSQL%" -u root %PWARG% < "%TARGET%\backend\schema.sql"
  if errorlevel 1 (
    echo [GAGAL] Import schema gagal.
    goto :fail
  )
  echo       OK - database app_mini dibuat beserta akun demo.
  set "BARU=1"
) else (
  echo       Database app_mini sudah ada dan berisi data - tidak disentuh.
)

REM --- 3. Selesai -------------------------------------------------------
echo [3/3] Selesai.
echo.
echo   Buka: http://localhost/app-mini/
echo   Dari komputer kasir lain: http://^<ip-komputer-ini^>/app-mini/
echo.
if defined BARU (
  echo   Akun demo: Budi / owner123      ^(Owner^)
  echo              Karyawan1 / karyawan123  ^(Karyawan^)
  echo   ^>^> GANTI password kedua akun ini lewat Master Data - Pengguna
  echo      sebelum dipakai sungguhan.
  echo.
)
echo   Pastikan Apache dan MySQL menyala di XAMPP Control Panel.
echo   Panduan pemakaian ada di docs\FAQ.pdf.
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
