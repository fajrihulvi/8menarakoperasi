-- =========================================================================
-- Tambah relasi Warehouse (Gudang) ke tabel barang
-- =========================================================================

ALTER TABLE `barang`
  ADD COLUMN `warehouse_id` INT(11) NULL DEFAULT NULL AFTER `supplier_id`,
  ADD KEY `idx_warehouse_id` (`warehouse_id`);
