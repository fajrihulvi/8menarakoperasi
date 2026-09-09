-- =========================================================================
-- MIGRASI: Master Data Kategori Barang (dropdown, bukan teks bebas)
-- Jalankan file ini SEKALI di database hosting (phpMyAdmin / Adminer / mysql CLI).
-- =========================================================================

-- 1. Buat tabel master kategori
CREATE TABLE `kategori` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_usaha` INT(11) DEFAULT 1,
  `nama_kategori` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nama_kategori` (`id_usaha`, `nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Migrasikan nilai kategori (teks) yang sudah ada di tabel barang
INSERT INTO `kategori` (`id_usaha`, `nama_kategori`)
SELECT DISTINCT `id_usaha`, TRIM(`kategori`) FROM `barang`
WHERE `kategori` IS NOT NULL AND TRIM(`kategori`) != '';

-- 3. Tambahkan kategori default (jaga-jaga bila belum pernah dipakai di data)
INSERT IGNORE INTO `kategori` (`id_usaha`, `nama_kategori`) VALUES
 (1,'Umum'), (1,'Makanan'), (1,'Minuman'), (1,'Bakery'), (1,'Elektronik'), (1,'Operasional');

-- 4. Tambah kolom kategori_id di tabel barang (relasi ke tabel kategori)
--    Kolom teks `kategori` TETAP dipertahankan untuk kompatibilitas kode lama.
ALTER TABLE `barang` ADD COLUMN `kategori_id` INT(11) NULL AFTER `kategori`;
ALTER TABLE `barang` ADD INDEX `idx_kategori_id` (`kategori_id`);

-- 5. Isi kategori_id berdasarkan pencocokan nama kategori (teks) yang sudah ada
UPDATE `barang` b
JOIN `kategori` k ON k.`id_usaha` = b.`id_usaha` AND k.`nama_kategori` = TRIM(b.`kategori`)
SET b.`kategori_id` = k.`id`
WHERE b.`kategori` IS NOT NULL AND TRIM(b.`kategori`) != '';
