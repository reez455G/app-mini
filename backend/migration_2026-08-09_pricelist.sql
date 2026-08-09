-- Migrasi: tambah kolom pricelist ke pembelian_item.
-- Jalankan SEKALI di database yang SUDAH ADA (yang di-setup sebelum tanggal
-- di nama file ini) — instalasi baru dari schema.sql sudah otomatis punya
-- kolom ini, tidak perlu jalankan file ini lagi.
--
-- Aman dijalankan berkali-kali (IF NOT EXISTS) kalau ragu sudah pernah atau
-- belum. Baris pembelian LAMA (sebelum migrasi ini) akan tampil pricelist-nya
-- "—" di halaman Detail Laporan Pembelian — datanya memang belum pernah
-- direkam sebelum ini, bukan bug.
--
--   mysql -u root -p app_mini < backend/migration_2026-08-09_pricelist.sql

USE app_mini;

ALTER TABLE pembelian_item
  ADD COLUMN IF NOT EXISTS pricelist DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER harga_netto;
