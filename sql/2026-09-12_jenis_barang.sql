-- =========================================================================
-- Master Data: Jenis Barang
-- Tabel lookup sederhana berisi jenis pelanggan/segmen: Horeka & SPPG.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `jenis_barang` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `jenis_barang` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jenis_barang` (`jenis_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `jenis_barang` (`jenis_barang`) VALUES
  ('Horeka (Hotel, Resto, Kafe)'),
  ('SPPG')
ON DUPLICATE KEY UPDATE `jenis_barang` = VALUES(`jenis_barang`);
