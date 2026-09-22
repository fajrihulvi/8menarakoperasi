-- =========================================================================
-- Catatan orderan per item pesanan
-- Sebelumnya catatan hanya satu untuk seluruh pesanan; kini tiap baris
-- barang bisa punya catatannya sendiri (mis. "tomat jangan terlalu matang").
-- =========================================================================

ALTER TABLE `pesanan_detail`
  ADD COLUMN `catatan` VARCHAR(255) NULL DEFAULT NULL AFTER `subtotal`;
