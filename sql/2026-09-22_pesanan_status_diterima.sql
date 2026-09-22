-- =========================================================================
-- Tambah status "Diterima" pada pesanan
-- Alur baru:
--   Diterima = barang sampai ke pelanggan, stok dipotong, invoice BELUM lunas
--              (pelanggan sudah boleh mengajukan retur di tahap ini)
--   Selesai  = barang sampai DAN invoice sudah LUNAS
-- =========================================================================

ALTER TABLE `pesanan`
  MODIFY COLUMN `status`
  ENUM('Pending','Persiapan','Pengiriman','Diterima','Selesai','Batal')
  NULL DEFAULT 'Pending';
