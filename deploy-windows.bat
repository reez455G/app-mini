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
REM  cuma diimport kalau database app_mini belum ada. Kalau database sudah
REM  ada, semua file backend\migration_*.sql dijalankan otomatis (aman
REM  dijalankan berkali-kali - lihat isi file migrasinya) supaya perubahan
REM  struktur tabel dari update aplikasi ikut diterapkan. Update aplikasi
REM  berikutnya cukup jalankan ulang file ini.
REM
REM  Kalau database sudah berisi data, script ini BACKUP DULU (mysqldump ke
REM  %XAMPP%\app-mini-backups\, di luar folder htdocs supaya tidak pernah
REM  ikut ke-robocopy/ke-serve) sebelum migrasi dijalankan, lalu verifikasi
REM  jumlah baris sebelum/sesudah. Backup gagal atau jumlah baris berkurang
REM  = deployment dihentikan, backup TIDAK dihapus.
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
echo [1/5] Menyalin file aplikasi...
robocopy "%SRC%." "%TARGET%" /E /NFL /NDL /NJH /NJS /NP /XD ".git" ".claude" >nul
REM Robocopy: kode 0-7 sukses, 8 ke atas beneran error.
if %ERRORLEVEL% GEQ 8 (
  echo [GAGAL] Penyalinan file gagal ^(kode %ERRORLEVEL%^).
  echo         Coba jalankan file ini sebagai Administrator.
  goto :fail
)
echo       OK.

REM --- 2. Database ------------------------------------------------------
echo [2/5] Memeriksa database...
"%MYSQL%" -u root %PWARG% -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
  echo [GAGAL] Tidak bisa terhubung ke MySQL.
  echo         Nyalakan MySQL di XAMPP Control Panel, lalu jalankan lagi.
  echo         Kalau root-nya pakai password, isi DBPASS di baris atas file ini.
  goto :fail
)

REM Cek isi, bukan cuma ada-tidaknya database: kalau tabel users sudah ada
REM berarti toko ini sudah punya data sungguhan - schema.sql JANGAN diimport
REM lagi (isinya CREATE TABLE + akun demo, bukan migrasi). DBADA dipakai
REM langkah 3 & 4 di bawah supaya backup/verifikasi cuma jalan kalau memang
REM ada data sungguhan yang perlu dilindungi.
set "DBADA="
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
  set "DBADA=1"
)

REM --- 3. Backup database (cuma kalau sudah ada data) --------------------
REM Ditulis ke %XAMPP%\app-mini-backups\, DI LUAR htdocs -- kalau ditulis ke
REM %SRC%/%TARGET%, robocopy /E di langkah 1 (dijalankan lagi tiap deploy
REM ulang) bisa ikut menyalinnya jadi ke-serve lewat web. Backup gagal atau
REM kosong = migrasi TIDAK dijalankan sama sekali, deployment dihentikan.
REM
REM Ditulis pakai goto/label, BUKAN blok if(...)else(...) besar -- variabel
REM yang di-set lalu langsung dibaca di dalam blok tanda-kurung yang SAMA
REM tidak akan terlihat nilai barunya tanpa "setlocal EnableDelayedExpansion"
REM (yang tidak dipakai script ini). Tiap "set" di bawah dan pembacaan
REM %VAR%-nya sengaja jadi baris top-level terpisah supaya aman tanpa itu.
echo [3/5] Backup database sebelum migrasi...
if not defined DBADA (
  echo       Database baru, tidak ada data yang perlu di-backup.
  goto :afterbackup
)
set "MYSQLDUMP=%XAMPP%\mysql\bin\mysqldump.exe"
if not exist "%MYSQLDUMP%" (
  echo [GAGAL] Tidak menemukan "%MYSQLDUMP%".
  goto :fail
)
if not exist "%XAMPP%\app-mini-backups" mkdir "%XAMPP%\app-mini-backups"
for /f %%t in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%t"
set "BACKUPFILE=%XAMPP%\app-mini-backups\app_mini_%STAMP%.sql"
"%MYSQLDUMP%" -u root %PWARG% app_mini > "%BACKUPFILE%"
if errorlevel 1 (
  echo [GAGAL] Backup database gagal. Migrasi DIBATALKAN demi keamanan data.
  goto :fail
)
set "BACKUPSIZE=0"
for %%s in ("%BACKUPFILE%") do set "BACKUPSIZE=%%~zs"
if "%BACKUPSIZE%"=="0" (
  echo [GAGAL] File backup kosong ^(0 byte^). Migrasi DIBATALKAN demi keamanan data.
  goto :fail
)
echo       OK - backup: %BACKUPFILE%

REM Total baris across semua tabel transaksi/master -- dibandingkan lagi
REM sesudah migrasi di langkah 4. Query yang sama dipakai dua kali supaya
REM angkanya benar-benar sebanding (bukan query yang "mirip").
set "ROWCOUNT_SQL=SELECT (SELECT COUNT(*) FROM app_mini.users)+(SELECT COUNT(*) FROM app_mini.barang)+(SELECT COUNT(*) FROM app_mini.barang_lot)+(SELECT COUNT(*) FROM app_mini.penjualan)+(SELECT COUNT(*) FROM app_mini.penjualan_item)+(SELECT COUNT(*) FROM app_mini.penjualan_item_lot)+(SELECT COUNT(*) FROM app_mini.pembelian)+(SELECT COUNT(*) FROM app_mini.pembelian_item)+(SELECT COUNT(*) FROM app_mini.retur_penjualan)+(SELECT COUNT(*) FROM app_mini.retur_penjualan_item)+(SELECT COUNT(*) FROM app_mini.retur_pembelian)+(SELECT COUNT(*) FROM app_mini.retur_pembelian_item)+(SELECT COUNT(*) FROM app_mini.pelanggan)+(SELECT COUNT(*) FROM app_mini.suplier)+(SELECT COUNT(*) FROM app_mini.kategori)+(SELECT COUNT(*) FROM app_mini.toko_profil)"
for /f %%c in ('"%MYSQL%" -N -u root %PWARG% -e "%ROWCOUNT_SQL%"') do set "ROWCOUNT_BEFORE=%%c"
if not defined ROWCOUNT_BEFORE (
  echo [GAGAL] Tidak bisa menghitung jumlah baris sebelum migrasi. Migrasi DIBATALKAN.
  goto :fail
)
echo       Jumlah baris sebelum migrasi: %ROWCOUNT_BEFORE%
:afterbackup

REM --- 4. Migrasi database ------------------------------------------------
REM Database baru dari schema.sql di atas sudah paling baru, jalankan juga
REM migrasinya tidak masalah (isinya ADD COLUMN IF NOT EXISTS, no-op kalau
REM sudah ada) -- lebih sederhana daripada mengecualikan kasus BARU.
echo [4/5] Menerapkan migrasi database...
set "ADAMIGRASI="
for /f "delims=" %%f in ('dir /b /on "%TARGET%\backend\migration_*.sql" 2^>nul') do (
  set "ADAMIGRASI=1"
  echo       - %%f
  "%MYSQL%" -u root %PWARG% < "%TARGET%\backend\%%f"
  if errorlevel 1 (
    echo [GAGAL] Migrasi %%f gagal.
    goto :fail
  )
)
if not defined ADAMIGRASI echo       Tidak ada file migrasi.
echo       OK.

REM Verifikasi jumlah baris cuma kalau langkah 3 sempat menghitungnya
REM (DBADA) -- migrasi cuma boleh MENAMBAH struktur, kalau baris berkurang
REM berarti ada yang tidak beres, deployment dihentikan dan backup dari
REM langkah 3 tetap tersimpan apa adanya (jalur :fail tidak menghapus apa pun).
if not defined DBADA goto :afterverify
for /f %%c in ('"%MYSQL%" -N -u root %PWARG% -e "%ROWCOUNT_SQL%"') do set "ROWCOUNT_AFTER=%%c"
if not defined ROWCOUNT_AFTER (
  echo [GAGAL] DEPLOYMENT FAILED: tidak bisa menghitung jumlah baris sesudah migrasi.
  echo         Backup masih tersimpan di: %BACKUPFILE%
  goto :fail
)
if %ROWCOUNT_AFTER% LSS %ROWCOUNT_BEFORE% (
  echo [GAGAL] DEPLOYMENT FAILED: jumlah baris berkurang setelah migrasi ^(%ROWCOUNT_BEFORE% -^> %ROWCOUNT_AFTER%^).
  echo         Backup masih tersimpan di: %BACKUPFILE%
  goto :fail
)
echo       Verifikasi jumlah baris OK ^(%ROWCOUNT_BEFORE% -^> %ROWCOUNT_AFTER%^).
:afterverify

REM --- 5. Selesai -------------------------------------------------------
echo [5/5] Selesai.
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
echo   Mau cetak struk langsung tanpa dialog print (mode QZ Tray / ESC-POS)?
echo   Instal installers\qz-tray-2.2.6-x86_64.exe sekali di PC ini, lihat
echo   installers\README.txt untuk langkahnya. Tidak wajib -- tanpa itu
echo   aplikasi tetap cetak seperti biasa lewat Browser Print.
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1
