-- =========================================================================
-- Tambah relasi Jenis Barang (Horeka / SPPG) ke tabel barang
-- =========================================================================

ALTER TABLE `barang`
  ADD COLUMN `jenis_barang_id` INT(11) NULL DEFAULT NULL AFTER `kategori_id`,
  ADD KEY `idx_jenis_barang_id` (`jenis_barang_id`);
