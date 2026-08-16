-- Migrasi: tambah kolom pricelist (Pricelist/HET) ke barang.
-- Jalankan SEKALI di database yang SUDAH ADA (yang di-setup sebelum tanggal
-- di nama file ini) — instalasi baru dari schema.sql sudah otomatis punya
-- kolom ini, tidak perlu jalankan file ini lagi.
--
-- Aman dijalankan berkali-kali (IF NOT EXISTS) kalau ragu sudah pernah atau
-- belum. Barang lama otomatis dapat Pricelist/HET = 0 (artinya "belum
-- diisi"), Owner bisa mengisinya lewat Data Barang → Ubah Barang.
--
--   mysql -u root -p app_mini < backend/migration_2026-08-16_pricelist_het.sql

USE app_mini;

ALTER TABLE barang
  ADD COLUMN IF NOT EXISTS pricelist DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER price_list_basis;
