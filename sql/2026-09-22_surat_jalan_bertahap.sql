-- =========================================================================
-- Surat Jalan bertahap (partial delivery)
-- Satu pesanan bisa dikirim beberapa kali; tiap pengiriman mencatat
-- item apa saja dan berapa qty yang dibawa.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `surat_jalan` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usaha` INT(11) NOT NULL DEFAULT 1,
  `no_surat_jalan` VARCHAR(50) NOT NULL,
  `id_pesanan` INT(11) NOT NULL,
  `no_pesanan` VARCHAR(50) NOT NULL,
  `nama_driver` VARCHAR(100) NULL,
  `nopol` VARCHAR(20) NULL,
  `user_id` INT(11) NULL COMMENT 'Admin yang membuat',
  `tanggal` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no_surat_jalan` (`no_surat_jalan`),
  KEY `idx_id_pesanan` (`id_pesanan`),
  KEY `idx_no_pesanan` (`no_pesanan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `surat_jalan_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `surat_jalan_id` INT(11) NOT NULL,
  `pesanan_detail_id` INT(11) NOT NULL COMMENT 'Baris item pada pesanan',
  `id_barang` INT(11) NOT NULL,
  `qty_kirim` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_surat_jalan_id` (`surat_jalan_id`),
  KEY `idx_pesanan_detail_id` (`pesanan_detail_id`),
  CONSTRAINT `fk_sjd_surat_jalan` FOREIGN KEY (`surat_jalan_id`) REFERENCES `surat_jalan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
