-- =========================================================================
-- MIGRASI: Tambahan kolom Bank/Rekening di Supplier + Tabel baru Warehouse
-- Jalankan file ini di database hosting (phpMyAdmin / Adminer / mysql CLI).
-- =========================================================================

-- 1. Tambah kolom bank/rekening ke tabel supplier
ALTER TABLE `supplier`
  ADD COLUMN `nama_bank` VARCHAR(100) NULL AFTER `no_telp`,
  ADD COLUMN `no_rekening` VARCHAR(50) NULL AFTER `nama_bank`,
  ADD COLUMN `nama_akun_rekening` VARCHAR(100) NULL AFTER `no_rekening`;

-- 2. Buat tabel warehouse
CREATE TABLE `warehouse` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usaha` INT(11) DEFAULT 1,
  `nama_warehouse` VARCHAR(100) DEFAULT NULL,
  `alamat` TEXT DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `nama_bank` VARCHAR(100) DEFAULT NULL,
  `no_rekening` VARCHAR(50) DEFAULT NULL,
  `nama_akun_rekening` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nama_warehouse` (`nama_warehouse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
