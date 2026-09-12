-- =========================================================================
-- Fitur: Stock Opname
-- Gudang mengajukan hasil hitung fisik stok (dengan alasan selisih per item),
-- lalu Admin menyetujui sebelum stok sistem benar-benar disesuaikan.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `stock_opname` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usaha` INT(11) NOT NULL DEFAULT 1,
  `no_opname` VARCHAR(50) NOT NULL,
  `user_id` INT(11) NOT NULL COMMENT 'Pengaju (role gudang)',
  `catatan` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `tgl_pengajuan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responden_id` INT(11) NULL COMMENT 'Admin yang approve/reject',
  `tgl_respon` DATETIME NULL,
  `alasan_tolak` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no_opname` (`no_opname`),
  KEY `idx_status` (`status`),
  KEY `idx_id_usaha` (`id_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_opname_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `stock_opname_id` INT(11) NOT NULL,
  `barang_id` INT(11) NOT NULL,
  `stok_sistem` DECIMAL(10,2) NOT NULL COMMENT 'Snapshot stok sistem saat opname diajukan',
  `stok_fisik` DECIMAL(10,2) NOT NULL COMMENT 'Hasil hitung fisik gudang',
  `selisih` DECIMAL(10,2) NOT NULL COMMENT 'stok_fisik - stok_sistem',
  `alasan` VARCHAR(255) NOT NULL COMMENT 'Alasan selisih, wajib diisi',
  PRIMARY KEY (`id`),
  KEY `idx_stock_opname_id` (`stock_opname_id`),
  KEY `idx_barang_id` (`barang_id`),
  CONSTRAINT `fk_sod_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opname` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
