-- App-mini — Sistem Stok & Kasir Toko
-- Schema + seed data. Import lewat phpMyAdmin (XAMPP) atau:
--   mysql -u root -p < schema.sql
--
-- Desain schema ini dijelaskan di docs/BACKEND.md. Ringkas:
-- - `barang` menyimpan harga_faktur/harga_netto TERBARU (snapshot) untuk
--   tampilan cepat (Data Barang, dst) — tapi utang budi ke `barang.stok` SAJA
--   tidak cukup buat modal per transaksi yang akurat, makanya ada `barang_lot`.
-- - `barang_lot`: SATU baris per batch pembelian (barang_id + suplier + harga
--   beli batch itu + sisa qty yang belum terjual/diretur). `barang.stok`
--   TETAP jadi angka agregat cepat yang dipakai di semua query lama yang
--   sudah ada — barang_lot cuma lapisan tambahan, disinkronkan bersama
--   `stok` di transaksi yang sama setiap kali stok berubah (lihat komentar
--   di pembelian.php/penjualan.php/retur_*.php).
-- - Penjualan menarik stok dari barang_lot secara FIFO (batch terlama duluan)
--   lewat `penjualan_item_lot`, jadi Laporan Laba bisa pakai harga beli BATCH
--   yang benar-benar terjual (bukan cuma harga_netto/harga_faktur TERKINI di
--   `barang`, yang sudah tidak akurat kalau harga berubah setelah transaksi
--   lama terjadi).
-- - Perbandingan harga antar-suplier (riwayat, ditampilkan di Data Barang)
--   tetap diambil dari `pembelian_item` + `pembelian` seperti sebelumnya —
--   `barang_lot` menambahkan kolom "sisa" ke riwayat itu, bukan mengganti
--   sumbernya.

CREATE DATABASE IF NOT EXISTS app_mini CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE app_mini;

-- ── Auth ──────────────────────────────────────────────────────────────
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','karyawan') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Master data ───────────────────────────────────────────────────────
CREATE TABLE kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE suplier (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  alamat VARCHAR(255) NOT NULL DEFAULT '-',
  no_hp VARCHAR(30) NOT NULL DEFAULT '-'
) ENGINE=InnoDB;

CREATE TABLE pelanggan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  alamat VARCHAR(255) NOT NULL DEFAULT '-',
  no_hp VARCHAR(30) NOT NULL DEFAULT '-'
) ENGINE=InnoDB;

-- ── Produk ────────────────────────────────────────────────────────────
CREATE TABLE barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(200) NOT NULL,
  kategori_id INT NOT NULL,
  suplier_id INT DEFAULT NULL,          -- suplier utama (ditentukan manual di Data Barang)
  harga_faktur DECIMAL(14,2) NOT NULL DEFAULT 0,   -- harga beli terakhir (snapshot)
  harga_netto DECIMAL(14,2) NOT NULL DEFAULT 0,
  price_list_basis ENUM('FAKTUR','NETTO') NOT NULL DEFAULT 'NETTO',
  harga_ecer DECIMAL(14,2) NOT NULL DEFAULT 0,     -- 1-5 pcs
  harga_bengkel DECIMAL(14,2) NOT NULL DEFAULT 0,  -- 6-10 pcs
  harga_grosir DECIMAL(14,2) NOT NULL DEFAULT 0,   -- 11-100 pcs
  stok INT NOT NULL DEFAULT 0,
  min_stok INT NOT NULL DEFAULT 10,
  pending_setup TINYINT(1) NOT NULL DEFAULT 0,     -- true: baru dibuat otomatis dari pembelian, harga jual belum diisi
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id),
  FOREIGN KEY (suplier_id) REFERENCES suplier(id)
) ENGINE=InnoDB;

-- ── Pembelian (Owner only) ────────────────────────────────────────────
CREATE TABLE pembelian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_faktur VARCHAR(30) NOT NULL,        -- diketik manual dari faktur fisik suplier, bukan dibuat sistem
  tanggal DATE NOT NULL,
  suplier_id INT NOT NULL,
  payment_type ENUM('CASH','TOP') NOT NULL DEFAULT 'CASH',
  jatuh_tempo DATE DEFAULT NULL,
  total_items INT NOT NULL,
  total_qty INT NOT NULL,
  total_biaya DECIMAL(14,2) NOT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Unique per suplier, BUKAN global: dua suplier berbeda bisa saja
  -- kebetulan pakai penomoran faktur yang sama (mis. sama-sama mulai dari
  -- "001"), jadi no_faktur cuma perlu unik dalam lingkup satu suplier.
  UNIQUE KEY no_faktur_per_suplier (suplier_id, no_faktur),
  FOREIGN KEY (suplier_id) REFERENCES suplier(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE pembelian_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pembelian_id INT NOT NULL,
  barang_id INT NOT NULL,
  kode_snapshot VARCHAR(30) NOT NULL,
  nama_snapshot VARCHAR(200) NOT NULL,
  kategori_snapshot VARCHAR(50) NOT NULL,
  harga_faktur DECIMAL(14,2) NOT NULL,
  harga_netto DECIMAL(14,2) NOT NULL,
  pricelist DECIMAL(14,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (pembelian_id) REFERENCES pembelian(id) ON DELETE CASCADE,
  FOREIGN KEY (barang_id) REFERENCES barang(id)
) ENGINE=InnoDB;

-- Satu batch = satu baris di sini — dibuat otomatis tiap kali pembelian_item
-- disimpan (pembelian_item_id terisi), tiap kali retur penjualan dibuat
-- (pembelian_item_id NULL — retur menciptakan batch baru, lihat komentar di
-- retur_penjualan_item), ATAU tiap kali stok dinaikkan manual lewat Data
-- Barang (batch penyesuaian: pembelian_item_id DAN suplier_id dua-duanya
-- NULL, karena koreksi stok opname memang tidak berasal dari suplier
-- manapun — lihat lot_sync_stok() di db.php). qty_sisa berkurang tiap kali
-- FIFO menarik dari batch ini (penjualan) atau batch ini diretur ke suplier;
-- qty_awal tetap jadi acuan "berapa banyak batch ini sebenarnya".
CREATE TABLE barang_lot (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  pembelian_item_id INT DEFAULT NULL,
  suplier_id INT DEFAULT NULL,
  harga_beli DECIMAL(14,2) NOT NULL,
  qty_awal INT NOT NULL,
  qty_sisa INT NOT NULL,
  tanggal DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (barang_id) REFERENCES barang(id),
  FOREIGN KEY (pembelian_item_id) REFERENCES pembelian_item(id) ON DELETE CASCADE,
  FOREIGN KEY (suplier_id) REFERENCES suplier(id)
) ENGINE=InnoDB;

-- ── Penjualan (Owner & Karyawan) ──────────────────────────────────────
CREATE TABLE penjualan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  tanggal DATE NOT NULL,
  pelanggan_id INT DEFAULT NULL,        -- NULL kalau pelanggan walk-in (bukan dari master)
  cust_name VARCHAR(150) NOT NULL,
  payment_method ENUM('TUNAI','TRANSFER') NOT NULL,
  amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_qty INT NOT NULL,
  grand_total DECIMAL(14,2) NOT NULL,
  kembalian DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE penjualan_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  penjualan_id INT NOT NULL,
  barang_id INT NOT NULL,
  nama_snapshot VARCHAR(200) NOT NULL,
  tier ENUM('ecer','bengkel','grosir') NOT NULL,
  qty INT NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (penjualan_id) REFERENCES penjualan(id) ON DELETE CASCADE,
  FOREIGN KEY (barang_id) REFERENCES barang(id)
) ENGINE=InnoDB;

-- Rincian FIFO: satu penjualan_item bisa "pecah" ke lebih dari satu batch
-- kalau qty-nya melewati sisa satu barang_lot (mis. jual 6, batch terlama
-- cuma sisa 5, jadi 5 dari batch lama + 1 dari batch berikutnya = 2 baris di
-- sini). harga_beli di sini SNAPSHOT biaya batch itu saat terjual, dipakai
-- Laporan Laba supaya modalnya akurat ke transaksi itu sendiri.
CREATE TABLE penjualan_item_lot (
  id INT AUTO_INCREMENT PRIMARY KEY,
  penjualan_item_id INT NOT NULL,
  barang_lot_id INT NOT NULL,
  qty INT NOT NULL,
  harga_beli DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (penjualan_item_id) REFERENCES penjualan_item(id) ON DELETE CASCADE,
  FOREIGN KEY (barang_lot_id) REFERENCES barang_lot(id)
) ENGINE=InnoDB;

-- ── Retur ─────────────────────────────────────────────────────────────
CREATE TABLE retur_penjualan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_retur VARCHAR(30) NOT NULL UNIQUE,
  original_invoice_no VARCHAR(30) NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  tanggal DATE NOT NULL,
  total_qty INT NOT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE retur_penjualan_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  retur_id INT NOT NULL,
  barang_id INT NOT NULL,
  nama_snapshot VARCHAR(200) NOT NULL,
  qty INT NOT NULL,
  reason VARCHAR(100) NOT NULL,
  -- Barang fisik masuk lagi lewat retur dianggap batch BARU (bukan
  -- ditelusur balik ke batch asal penjualannya — rumit kalau ada retur
  -- parsial berulang atas invoice yang sama), harga_beli-nya rata-rata
  -- tertimbang dari batch yang terjual di transaksi itu. Lihat barang_lot.
  barang_lot_id INT DEFAULT NULL,
  FOREIGN KEY (retur_id) REFERENCES retur_penjualan(id) ON DELETE CASCADE,
  FOREIGN KEY (barang_id) REFERENCES barang(id),
  FOREIGN KEY (barang_lot_id) REFERENCES barang_lot(id)
) ENGINE=InnoDB;

CREATE TABLE retur_pembelian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_retur VARCHAR(30) NOT NULL UNIQUE,
  original_invoice_no VARCHAR(30) NOT NULL,
  suplier_id INT NOT NULL,
  tanggal DATE NOT NULL,
  total_qty INT NOT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (suplier_id) REFERENCES suplier(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE retur_pembelian_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  retur_id INT NOT NULL,
  barang_id INT NOT NULL,
  nama_snapshot VARCHAR(200) NOT NULL,
  qty INT NOT NULL,
  reason VARCHAR(100) NOT NULL,
  -- Barang fisik keluar lagi ke suplier, jadi mengurangi qty_sisa pada
  -- batch ASLI yang dibuat pembelian ini (bukan batch baru).
  barang_lot_id INT DEFAULT NULL,
  FOREIGN KEY (retur_id) REFERENCES retur_pembelian(id) ON DELETE CASCADE,
  FOREIGN KEY (barang_id) REFERENCES barang(id),
  FOREIGN KEY (barang_lot_id) REFERENCES barang_lot(id)
) ENGINE=InnoDB;

-- ── Profil toko ───────────────────────────────────────────────────────
-- Selalu tepat SATU baris (id=1) — bukan daftar seperti suplier/pelanggan,
-- jadi tidak ada kode/AUTO_INCREMENT. Ditampilkan & diedit di Master Data.
CREATE TABLE toko_profil (
  id INT PRIMARY KEY DEFAULT 1,
  nama VARCHAR(150) NOT NULL,
  alamat VARCHAR(255) NOT NULL DEFAULT '-',
  no_hp VARCHAR(30) NOT NULL DEFAULT '-'
) ENGINE=InnoDB;

INSERT INTO toko_profil (id, nama, alamat, no_hp) VALUES
  (1, 'PUTRA JAYA MOTOR', 'Jl. Jati Raya Blok J No. 11, Banyumanik, Semarang', '08155608055');

-- ════════════════════════════════════════════════════════════════════
-- Seed data — sama dengan data contoh yang sudah ada di Dashboard.dc.html,
-- supaya demo/testing konsisten dengan prototipe frontend.
-- ════════════════════════════════════════════════════════════════════

-- Users. GANTI PASSWORD INI SEBELUM DIPAKAI SUNGGUHAN.
-- username=Budi password=owner123 (role owner)
-- username=Karyawan1 password=karyawan123 (role karyawan)
INSERT INTO users (username, password_hash, role) VALUES
  ('Budi', '$2y$10$bFCWiIgY7i032XyJFsbfGewMnw6dD5pGqzoT/CyGz.RjJe3GsZ71.', 'owner'),
  ('Karyawan1', '$2y$10$hsguSjrqX5UpQ37KfB6H/.lYqESwQj1y6EyrufsTJSieoBpDDUQP.', 'karyawan');

INSERT INTO kategori (nama) VALUES ('BAN'), ('OLI'), ('AKI'), ('SPAREPART');

INSERT INTO suplier (kode, nama, alamat, no_hp) VALUES
  ('SUP-0001', 'CV Sumber Ban Jaya', '-', '-'),
  ('SUP-0002', 'PT Astra Otoparts', '-', '-'),
  ('SUP-0003', 'Toko Oli Makmur', '-', '-'),
  ('SUP-0004', 'UD Aki Sejahtera', '-', '-');

INSERT INTO pelanggan (kode, nama, alamat, no_hp) VALUES
  ('CUST-0001', 'Hendra', '-', '-'),
  ('CUST-0002', 'Siti', '-', '-'),
  ('CUST-0003', 'Hadi', '-', '-');

-- kategori_id: BAN=1 OLI=2 AKI=3 SPAREPART=4 — suplier_id: 1=Sumber Ban 2=Astra Otoparts 3=Oli Makmur 4=Aki Sejahtera
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) VALUES
  ('BAN-001', 'Ban Bridgestone Turanza 185/65 R15', 1, 1, 700000, 680000, 'NETTO', 850000, 800000, 750000, 3, 10, 0),
  ('OLI-014', 'Oli Shell Helix HX7 4L',             2, 3, 165000, 160000, 'NETTO', 210000, 195000, 180000, 5, 10, 0),
  ('AKI-007', 'Aki GS Astra NS40',                  3, 4, 640000, 620000, 'NETTO', 750000, 720000, 690000, 2, 10, 0),
  ('SP-102',  'Kanvas Rem Depan Honda Beat',        4, 2, 78000,  75000,  'NETTO', 95000,  90000,  85000,  4, 10, 0),
  ('OLI-002', 'Oli Federal Matic 0.8L',             2, 3, 34000,  32000,  'NETTO', 45000,  42000,  38000,  30, 10, 0),
  ('BAN-018', 'Ban Motor FDR Sportivo 90/80-17',    1, 1, 260000, 250000, 'NETTO', 320000, 300000, 280000, 6, 10, 0),
  ('SP-045',  'Busi NGK CPR8EA-9',                  4, 2, 25000,  24000,  'NETTO', 35000,  32000,  28000,  40, 10, 0),
  ('OLI-020', 'Oli Yamalube Sport 1L',              2, 3, 50000,  48000,  'NETTO', 65000,  60000,  55000,  25, 10, 0);

-- Riwayat pembelian contoh (dibuat oleh Budi/owner, id=1)
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES
  ('PB-260728-0001', '2026-07-28', 1, 'CASH', NULL, 2, 20, 6720000, 1),
  ('PB-260802-0002', '2026-08-02', 3, 'TOP', '2026-09-01', 3, 60, 3520000, 1);

INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) VALUES
  (1, 1, 'BAN-001', 'Ban Bridgestone Turanza 185/65 R15', 'BAN', 700000, 680000, 4,  2720000),
  (1, 6, 'BAN-018', 'Ban Motor FDR Sportivo 90/80-17',    'BAN', 260000, 250000, 16, 4000000),
  (2, 5, 'OLI-002', 'Oli Federal Matic 0.8L',             'OLI', 34000,  32000,  30, 960000),
  (2, 2, 'OLI-014', 'Oli Shell Helix HX7 4L',             'OLI', 165000, 160000, 10, 1600000),
  (2, 8, 'OLI-020', 'Oli Yamalube Sport 1L',              'OLI', 50000,  48000,  20, 960000);

-- Riwayat penjualan contoh
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES
  ('INV-260803-0001', '2026-08-03', 1, 'Hendra', 'TUNAI',    336000,  8,  336000,  0, 1),
  ('INV-260804-0002', '2026-08-04', 2, 'Siti',   'TRANSFER', 1200000, 22, 1200000, 0, 2),
  ('INV-260805-0003', '2026-08-05', 3, 'Hadi',   'TUNAI',    325000,  5,  325000,  0, 1);

INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) VALUES
  (1, 5, 'Oli Federal Matic 0.8L',             'bengkel', 8,  42000,  336000),
  (2, 7, 'Busi NGK CPR8EA-9',                  'grosir',  20, 28000,  560000),
  (2, 6, 'Ban Motor FDR Sportivo 90/80-17',    'ecer',    2,  320000, 640000),
  (3, 8, 'Oli Yamalube Sport 1L',              'ecer',    5,  65000,  325000);
