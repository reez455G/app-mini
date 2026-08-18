-- App-mini — data dummy TAMBAHAN untuk testing (bukan pengganti schema.sql).
-- Aman dijalankan di atas database app_mini yang sudah ada (cuma INSERT, tidak
-- menghapus/mengubah apa pun) — semua lookup FK pakai nama, bukan id, jadi tidak
-- peduli id berapa yang sudah dipakai. Import lewat phpMyAdmin (Import > pilih
-- file ini) atau `mysql -u root app_mini < backend/dummy_data.sql`.
USE app_mini;

-- ── Suplier tambahan ──
INSERT INTO suplier (kode, nama, alamat, no_hp) SELECT CONCAT('SUP-', LPAD((SELECT COUNT(*)+1 FROM suplier s2), 4, '0')), 'Sinar Motor Parts', 'Jl. Industri No. 12', '081234500001' WHERE NOT EXISTS (SELECT 1 FROM suplier WHERE nama = 'Sinar Motor Parts');
INSERT INTO suplier (kode, nama, alamat, no_hp) SELECT CONCAT('SUP-', LPAD((SELECT COUNT(*)+1 FROM suplier s2), 4, '0')), 'UD Jaya Ban', 'Jl. Raya Timur No. 45', '081234500002' WHERE NOT EXISTS (SELECT 1 FROM suplier WHERE nama = 'UD Jaya Ban');
INSERT INTO suplier (kode, nama, alamat, no_hp) SELECT CONCAT('SUP-', LPAD((SELECT COUNT(*)+1 FROM suplier s2), 4, '0')), 'CV Anugerah Oli', 'Jl. Pasar Baru No. 7', '081234500003' WHERE NOT EXISTS (SELECT 1 FROM suplier WHERE nama = 'CV Anugerah Oli');
INSERT INTO suplier (kode, nama, alamat, no_hp) SELECT CONCAT('SUP-', LPAD((SELECT COUNT(*)+1 FROM suplier s2), 4, '0')), 'Toko Aki Makmur Jaya', 'Jl. Gatot Subroto No. 88', '081234500004' WHERE NOT EXISTS (SELECT 1 FROM suplier WHERE nama = 'Toko Aki Makmur Jaya');

-- ── Pelanggan tambahan ──
INSERT INTO pelanggan (kode, nama, alamat, no_hp) SELECT CONCAT('CUST-', LPAD((SELECT COUNT(*)+1 FROM pelanggan p2), 4, '0')), 'Budi Santoso', '-', '081298700001' WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama = 'Budi Santoso');
INSERT INTO pelanggan (kode, nama, alamat, no_hp) SELECT CONCAT('CUST-', LPAD((SELECT COUNT(*)+1 FROM pelanggan p2), 4, '0')), 'Rina Wulandari', '-', '081298700002' WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama = 'Rina Wulandari');
INSERT INTO pelanggan (kode, nama, alamat, no_hp) SELECT CONCAT('CUST-', LPAD((SELECT COUNT(*)+1 FROM pelanggan p2), 4, '0')), 'Agus Setiawan', '-', '081298700003' WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama = 'Agus Setiawan');
INSERT INTO pelanggan (kode, nama, alamat, no_hp) SELECT CONCAT('CUST-', LPAD((SELECT COUNT(*)+1 FROM pelanggan p2), 4, '0')), 'Dewi Lestari', '-', '081298700004' WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama = 'Dewi Lestari');
INSERT INTO pelanggan (kode, nama, alamat, no_hp) SELECT CONCAT('CUST-', LPAD((SELECT COUNT(*)+1 FROM pelanggan p2), 4, '0')), 'Fajar Nugroho', '-', '081298700005' WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama = 'Fajar Nugroho');

-- ── Barang tambahan ──
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'BAN-021', 'Ban Michelin Pilot Sport 195/55 R16', (SELECT id FROM kategori WHERE nama='BAN'), (SELECT id FROM suplier WHERE nama='Sinar Motor Parts'), 950000, 920000, 'NETTO', 1150000, 1100000, 1020000, 12, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'BAN-021');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'BAN-022', 'Ban Dunlop D115 80/90-17', (SELECT id FROM kategori WHERE nama='BAN'), (SELECT id FROM suplier WHERE nama='UD Jaya Ban'), 210000, 200000, 'NETTO', 260000, 245000, 225000, 18, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'BAN-022');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'BAN-023', 'Ban IRC NR73 70/90-17', (SELECT id FROM kategori WHERE nama='BAN'), (SELECT id FROM suplier WHERE nama='UD Jaya Ban'), 150000, 145000, 'NETTO', 190000, 178000, 165000, 4, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'BAN-023');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'OLI-030', 'Oli Castrol Power1 4T 1L', (SELECT id FROM kategori WHERE nama='OLI'), (SELECT id FROM suplier WHERE nama='CV Anugerah Oli'), 62000, 58000, 'NETTO', 78000, 72000, 65000, 34, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'OLI-030');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'OLI-031', 'Oli Motul 3000 2L', (SELECT id FROM kategori WHERE nama='OLI'), (SELECT id FROM suplier WHERE nama='CV Anugerah Oli'), 88000, 84000, 'NETTO', 110000, 102000, 92000, 20, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'OLI-031');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'OLI-032', 'Oli Gardan Federal 250ml', (SELECT id FROM kategori WHERE nama='OLI'), (SELECT id FROM suplier WHERE nama='Toko Oli Makmur'), 18000, 16000, 'NETTO', 24000, 22000, 19000, 3, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'OLI-032');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'AKI-010', 'Aki Yuasa YTZ7S', (SELECT id FROM kategori WHERE nama='AKI'), (SELECT id FROM suplier WHERE nama='Toko Aki Makmur Jaya'), 480000, 460000, 'NETTO', 560000, 535000, 500000, 9, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'AKI-010');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'AKI-011', 'Aki GS Astra GTZ5S', (SELECT id FROM kategori WHERE nama='AKI'), (SELECT id FROM suplier WHERE nama='UD Aki Sejahtera'), 390000, 375000, 'NETTO', 470000, 445000, 415000, 2, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'AKI-011');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-201', 'Kampas Kopling Honda Vario', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='PT Astra Otoparts'), 65000, 60000, 'NETTO', 85000, 78000, 70000, 22, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-201');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-202', 'Lampu LED Depan Universal', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='Sinar Motor Parts'), 45000, 40000, 'NETTO', 60000, 55000, 48000, 5, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-202');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-203', 'Rantai Motor DID 428H', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='PT Astra Otoparts'), 175000, 165000, 'NETTO', 220000, 205000, 185000, 15, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-203');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-204', 'Filter Udara Yamaha NMAX', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='Sinar Motor Parts'), 38000, 35000, 'NETTO', 50000, 46000, 40000, 27, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-204');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-205', 'Kabel Gas Universal', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='PT Astra Otoparts'), 22000, 20000, 'NETTO', 32000, 29000, 25000, 30, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-205');
INSERT INTO barang (kode, nama, kategori_id, suplier_id, harga_faktur, harga_netto, price_list_basis, harga_ecer, harga_bengkel, harga_grosir, stok, min_stok, pending_setup) SELECT 'SP-206', 'Bohlam Sein LED', (SELECT id FROM kategori WHERE nama='SPAREPART'), (SELECT id FROM suplier WHERE nama='Sinar Motor Parts'), 12000, 10000, 'NETTO', 18000, 16000, 13000, 6, 10, 0 WHERE NOT EXISTS (SELECT 1 FROM barang WHERE kode = 'SP-206');

-- ── Riwayat pembelian tambahan ──
SET @sup_id = (SELECT id FROM suplier WHERE nama = 'Toko Oli Makmur');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-07-12','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-07-12','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-12', @sup_id, 'TOP', '2026-08-11', 4, 35, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 20, b.harga_netto * 20 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'OLI-030';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'OLI-020';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-102';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-023';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'UD Aki Sejahtera');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-07-15','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-07-15','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-15', @sup_id, 'CASH', NULL, 2, 18, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 8, b.harga_netto * 8 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'AKI-007';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 10, b.harga_netto * 10 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-018';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'CV Anugerah Oli');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-07-27','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-07-27','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-27', @sup_id, 'CASH', NULL, 2, 15, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-001';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 10, b.harga_netto * 10 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'OLI-002';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'CV Anugerah Oli');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-07-28','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-07-28','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-28', @sup_id, 'CASH', NULL, 3, 30, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-201';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'OLI-031';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 20, b.harga_netto * 20 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'AKI-010';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'PT Astra Otoparts');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-07-28','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-07-28','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-28', @sup_id, 'CASH', NULL, 3, 40, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 12, b.harga_netto * 12 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-204';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 20, b.harga_netto * 20 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-018';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 8, b.harga_netto * 8 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-201';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'PT Astra Otoparts');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-08-01','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-08-01','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-01', @sup_id, 'TOP', '2026-08-31', 3, 31, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 8, b.harga_netto * 8 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-023';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 15, b.harga_netto * 15 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-102';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 8, b.harga_netto * 8 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'SP-202';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'UD Aki Sejahtera');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_faktur,11) AS UNSIGNED)),0)+1 FROM pembelian WHERE no_faktur LIKE CONCAT('PB-', DATE_FORMAT('2026-08-04','%y%m%d'), '-%'));
INSERT INTO pembelian (no_faktur, tanggal, suplier_id, payment_type, jatuh_tempo, total_items, total_qty, total_biaya, created_by) VALUES (CONCAT('PB-', DATE_FORMAT('2026-08-04','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-04', @sup_id, 'CASH', NULL, 4, 40, 0, 1);
SET @pb_id = LAST_INSERT_ID();
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-023';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 10, b.harga_netto * 10 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'BAN-022';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 5, b.harga_netto * 5 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'OLI-020';
INSERT INTO pembelian_item (pembelian_id, barang_id, kode_snapshot, nama_snapshot, kategori_snapshot, harga_faktur, harga_netto, qty, subtotal) SELECT @pb_id, b.id, b.kode, b.nama, k.nama, b.harga_faktur, b.harga_netto, 20, b.harga_netto * 20 FROM barang b JOIN kategori k ON k.id = b.kategori_id WHERE b.kode = 'AKI-011';
UPDATE pembelian SET total_biaya = (SELECT COALESCE(SUM(subtotal),0) FROM pembelian_item WHERE pembelian_id = @pb_id) WHERE id = @pb_id;

-- ── Riwayat penjualan tambahan (termasuk beberapa hari terakhir, biar dashboard & tren keisi) ──
SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Dewi Lestari');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-18','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-18','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-18', @cust_id, 'Dewi Lestari', 'TUNAI', 0, 8, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 8, b.harga_bengkel, b.harga_bengkel * 8 FROM barang b WHERE b.kode = 'AKI-010';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Fajar Nugroho');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-18','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-18','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-18', @cust_id, 'Fajar Nugroho', 'TUNAI', 0, 14, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 3, b.harga_ecer, b.harga_ecer * 3 FROM barang b WHERE b.kode = 'BAN-001';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 4, b.harga_ecer, b.harga_ecer * 4 FROM barang b WHERE b.kode = 'OLI-031';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'SP-201';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Dewi Lestari');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-19', @cust_id, 'Dewi Lestari', 'TUNAI', 0, 15, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'OLI-031';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hendra');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-19', @cust_id, 'Hendra', 'TUNAI', 0, 3, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 3, b.harga_ecer, b.harga_ecer * 3 FROM barang b WHERE b.kode = 'SP-045';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Siti');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-19','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-19', @cust_id, 'Siti', 'TUNAI', 0, 8, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'BAN-018';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'BAN-001';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Rina Wulandari');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-23', @cust_id, 'Rina Wulandari', 'TRANSFER', 0, 15, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'OLI-030';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 3, b.harga_ecer, b.harga_ecer * 3 FROM barang b WHERE b.kode = 'SP-202';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hendra');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-23', @cust_id, 'Hendra', 'TRANSFER', 0, 13, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'SP-201';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'AKI-007';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 4, b.harga_ecer, b.harga_ecer * 4 FROM barang b WHERE b.kode = 'OLI-032';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Agus Setiawan');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-23','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-23', @cust_id, 'Agus Setiawan', 'TUNAI', 0, 24, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 8, b.harga_bengkel, b.harga_bengkel * 8 FROM barang b WHERE b.kode = 'AKI-007';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'BAN-021';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'BAN-018';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Rina Wulandari');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-24','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-24','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-24', @cust_id, 'Rina Wulandari', 'TUNAI', 0, 2, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'AKI-011';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Fajar Nugroho');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-24','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-24','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-24', @cust_id, 'Fajar Nugroho', 'TUNAI', 0, 22, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 3, b.harga_ecer, b.harga_ecer * 3 FROM barang b WHERE b.kode = 'OLI-031';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'OLI-020';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'OLI-014';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Budi Santoso');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-26', @cust_id, 'Budi Santoso', 'TRANSFER', 0, 23, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 8, b.harga_bengkel, b.harga_bengkel * 8 FROM barang b WHERE b.kode = 'SP-205';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'OLI-020';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Siti');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-26', @cust_id, 'Siti', 'TUNAI', 0, 8, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 8, b.harga_bengkel, b.harga_bengkel * 8 FROM barang b WHERE b.kode = 'BAN-023';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Budi Santoso');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-26','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-26', @cust_id, 'Budi Santoso', 'TRANSFER', 0, 2, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'BAN-021';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Siti');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-27','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-27','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-27', @cust_id, 'Siti', 'TUNAI', 0, 11, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 4, b.harga_ecer, b.harga_ecer * 4 FROM barang b WHERE b.kode = 'BAN-023';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'AKI-007';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hadi');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-29','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-29','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-29', @cust_id, 'Hadi', 'TRANSFER', 0, 31, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'OLI-002';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'OLI-014';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 4, b.harga_ecer, b.harga_ecer * 4 FROM barang b WHERE b.kode = 'AKI-011';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Dewi Lestari');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-30','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-30','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-30', @cust_id, 'Dewi Lestari', 'TUNAI', 0, 3, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'SP-206';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'BAN-001';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Agus Setiawan');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-31','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-31','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-31', @cust_id, 'Agus Setiawan', 'TUNAI', 0, 4, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 4, b.harga_ecer, b.harga_ecer * 4 FROM barang b WHERE b.kode = 'AKI-010';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hadi');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-07-31','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-07-31','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-07-31', @cust_id, 'Hadi', 'TUNAI', 0, 15, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'SP-201';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Fajar Nugroho');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-02','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-02','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-02', @cust_id, 'Fajar Nugroho', 'TRANSFER', 0, 1, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'BAN-022';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hadi');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-03','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-03','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-03', @cust_id, 'Hadi', 'TUNAI', 0, 13, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'OLI-014';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'AKI-010';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hendra');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-04','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-04','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-04', @cust_id, 'Hendra', 'TUNAI', 0, 27, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'BAN-001';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 15, b.harga_grosir, b.harga_grosir * 15 FROM barang b WHERE b.kode = 'SP-202';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Budi Santoso');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-04','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-04','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-04', @cust_id, 'Budi Santoso', 'TUNAI', 0, 1, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'BAN-022';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 20000, kembalian = 20000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hendra');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-05','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-05','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-05', @cust_id, 'Hendra', 'TRANSFER', 0, 3, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 1, b.harga_ecer, b.harga_ecer * 1 FROM barang b WHERE b.kode = 'AKI-007';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'OLI-032';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Siti');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-06','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-06','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-06', @cust_id, 'Siti', 'TRANSFER', 0, 2, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'SP-205';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 5000, kembalian = 5000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Hendra');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-07', @cust_id, 'Hendra', 'TRANSFER', 0, 8, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 8, b.harga_bengkel, b.harga_bengkel * 8 FROM barang b WHERE b.kode = 'SP-206';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 20000, kembalian = 20000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Budi Santoso');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-07', @cust_id, 'Budi Santoso', 'TRANSFER', 0, 22, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'grosir', 12, b.harga_grosir, b.harga_grosir * 12 FROM barang b WHERE b.kode = 'SP-203';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 3, b.harga_ecer, b.harga_ecer * 3 FROM barang b WHERE b.kode = 'AKI-011';
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'bengkel', 7, b.harga_bengkel, b.harga_bengkel * 7 FROM barang b WHERE b.kode = 'SP-201';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 50000, kembalian = 50000 WHERE id = @pj_id;

SET @cust_id = (SELECT id FROM pelanggan WHERE nama = 'Agus Setiawan');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no,12) AS UNSIGNED)),0)+1 FROM penjualan WHERE invoice_no LIKE CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-%'));
INSERT INTO penjualan (invoice_no, tanggal, pelanggan_id, cust_name, payment_method, amount_paid, total_qty, grand_total, kembalian, created_by) VALUES (CONCAT('INV-', DATE_FORMAT('2026-08-07','%y%m%d'), '-', LPAD(@seq,4,'0')), '2026-08-07', @cust_id, 'Agus Setiawan', 'TUNAI', 0, 2, 0, 0, 1);
SET @pj_id = LAST_INSERT_ID();
INSERT INTO penjualan_item (penjualan_id, barang_id, nama_snapshot, tier, qty, unit_price, subtotal) SELECT @pj_id, b.id, b.nama, 'ecer', 2, b.harga_ecer, b.harga_ecer * 2 FROM barang b WHERE b.kode = 'BAN-001';
UPDATE penjualan SET grand_total = (SELECT COALESCE(SUM(subtotal),0) FROM penjualan_item WHERE penjualan_id = @pj_id) WHERE id = @pj_id;
UPDATE penjualan SET amount_paid = grand_total + 0, kembalian = 0 WHERE id = @pj_id;

-- ── Retur contoh ──
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_retur,11) AS UNSIGNED)),0)+1 FROM retur_penjualan WHERE no_retur LIKE CONCAT('RJ-', DATE_FORMAT('2026-07-29','%y%m%d'), '-%'));
INSERT INTO retur_penjualan (no_retur, original_invoice_no, customer_name, tanggal, total_qty, created_by) VALUES (CONCAT('RJ-', DATE_FORMAT('2026-07-29','%y%m%d'), '-', LPAD(@seq,4,'0')), 'INV-DUMMY-0001', 'Siti', '2026-07-29', 2, 1);
SET @rj_id = LAST_INSERT_ID();
INSERT INTO retur_penjualan_item (retur_id, barang_id, nama_snapshot, qty, reason) SELECT @rj_id, b.id, b.nama, 2, 'Rusak' FROM barang b WHERE b.kode = 'OLI-002';
UPDATE barang SET stok = stok + 2 WHERE kode = 'OLI-002';

SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_retur,11) AS UNSIGNED)),0)+1 FROM retur_penjualan WHERE no_retur LIKE CONCAT('RJ-', DATE_FORMAT('2026-08-03','%y%m%d'), '-%'));
INSERT INTO retur_penjualan (no_retur, original_invoice_no, customer_name, tanggal, total_qty, created_by) VALUES (CONCAT('RJ-', DATE_FORMAT('2026-08-03','%y%m%d'), '-', LPAD(@seq,4,'0')), 'INV-DUMMY-0002', 'Hendra', '2026-08-03', 1, 1);
SET @rj_id = LAST_INSERT_ID();
INSERT INTO retur_penjualan_item (retur_id, barang_id, nama_snapshot, qty, reason) SELECT @rj_id, b.id, b.nama, 1, 'Salah Kirim' FROM barang b WHERE b.kode = 'SP-045';
UPDATE barang SET stok = stok + 1 WHERE kode = 'SP-045';

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'CV Sumber Ban Jaya');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_retur,11) AS UNSIGNED)),0)+1 FROM retur_pembelian WHERE no_retur LIKE CONCAT('RB-', DATE_FORMAT('2026-07-29','%y%m%d'), '-%'));
INSERT INTO retur_pembelian (no_retur, original_invoice_no, suplier_id, tanggal, total_qty, created_by) VALUES (CONCAT('RB-', DATE_FORMAT('2026-07-29','%y%m%d'), '-', LPAD(@seq,4,'0')), 'PB-DUMMY-0001', @sup_id, '2026-07-29', 2, 1);
SET @rb_id = LAST_INSERT_ID();
INSERT INTO retur_pembelian_item (retur_id, barang_id, nama_snapshot, qty, reason) SELECT @rb_id, b.id, b.nama, 2, 'Cacat' FROM barang b WHERE b.kode = 'BAN-018';
UPDATE barang SET stok = GREATEST(0, stok - 2) WHERE kode = 'BAN-018';

SET @sup_id = (SELECT id FROM suplier WHERE nama = 'Toko Oli Makmur');
SET @seq = (SELECT COALESCE(MAX(CAST(SUBSTRING(no_retur,11) AS UNSIGNED)),0)+1 FROM retur_pembelian WHERE no_retur LIKE CONCAT('RB-', DATE_FORMAT('2026-08-02','%y%m%d'), '-%'));
INSERT INTO retur_pembelian (no_retur, original_invoice_no, suplier_id, tanggal, total_qty, created_by) VALUES (CONCAT('RB-', DATE_FORMAT('2026-08-02','%y%m%d'), '-', LPAD(@seq,4,'0')), 'PB-DUMMY-0002', @sup_id, '2026-08-02', 3, 1);
SET @rb_id = LAST_INSERT_ID();
INSERT INTO retur_pembelian_item (retur_id, barang_id, nama_snapshot, qty, reason) SELECT @rb_id, b.id, b.nama, 3, 'Rusak' FROM barang b WHERE b.kode = 'OLI-014';
UPDATE barang SET stok = GREATEST(0, stok - 3) WHERE kode = 'OLI-014';


-- ── Batch stok + harga jual per suplier untuk data seed ───────────────
-- Setiap pcs di barang.stok WAJIB punya batch pendukung di barang_lot
-- (SUM(qty_sisa) == stok). Tanpa ini Transaksi Penjualan tampil kosong dan
-- penjualan ditolak "Data batch tidak sinkron" (lihat penjualan.php).

-- 1. Satu batch per baris pembelian, sama seperti yang dibuat pembelian.php.
INSERT INTO barang_lot (barang_id, pembelian_item_id, suplier_id, harga_beli, qty_awal, qty_sisa, tanggal)
SELECT pi.barang_id, pi.id, p.suplier_id,
       IF(pi.harga_netto > 0, pi.harga_netto, pi.harga_faktur), pi.qty, pi.qty, p.tanggal
FROM pembelian_item pi
JOIN pembelian p ON p.id = pi.pembelian_id
WHERE NOT EXISTS (SELECT 1 FROM barang_lot bl WHERE bl.pembelian_item_id = pi.id);

-- 2. Barang yang stok seed-nya tidak berasal dari pembelian di atas tetap
--    butuh batch, atas nama suplier terakhirnya — kasir wajib memilih
--    suplier saat menjual (penjualan.php), jadi batch tanpa suplier tidak
--    akan pernah bisa dijual lewat layar kasir.
INSERT INTO barang_lot (barang_id, pembelian_item_id, suplier_id, harga_beli, qty_awal, qty_sisa, tanggal)
SELECT b.id, NULL, b.suplier_id,
       IF(b.harga_netto > 0, b.harga_netto, b.harga_faktur), b.stok, b.stok, CURDATE()
FROM barang b
WHERE b.stok > 0 AND b.suplier_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM barang_lot bl WHERE bl.barang_id = b.id);

-- 3. Batch adalah sumber kebenaran: stok agregat diselaraskan ke batch.
UPDATE barang b
SET b.stok = COALESCE((SELECT SUM(bl.qty_sisa) FROM barang_lot bl WHERE bl.barang_id = b.id), 0);

-- 4. Harga jual per (barang, suplier) — tanpa baris ini barangnya tetap
--    tidak muncul di kasir walau stoknya ada (ditandai "harga belum diisi").
INSERT INTO barang_suplier_harga (barang_id, suplier_id, harga_faktur, harga_netto, harga_ecer, harga_bengkel, harga_grosir)
SELECT DISTINCT bl.barang_id, bl.suplier_id, b.harga_faktur, b.harga_netto,
       b.harga_ecer, b.harga_bengkel, b.harga_grosir
FROM barang_lot bl
JOIN barang b ON b.id = bl.barang_id
WHERE bl.suplier_id IS NOT NULL
  AND b.harga_ecer > 0 AND b.harga_bengkel > 0 AND b.harga_grosir > 0
  AND NOT EXISTS (SELECT 1 FROM barang_suplier_harga h
                  WHERE h.barang_id = bl.barang_id AND h.suplier_id = bl.suplier_id);
