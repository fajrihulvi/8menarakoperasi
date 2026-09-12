-- =========================================================================
-- Migration: Alur lanjutan status Retur (Mengganti Barang / Potong Jumlah Invoice)
-- Jalankan manual sekali lewat phpMyAdmin / mysql client.
-- =========================================================================

-- 1. Kolom baru untuk melacak surat jalan pengganti (kasus "Mengganti Barang")
ALTER TABLE `retur`
    ADD COLUMN `no_faktur_pengganti` VARCHAR(50) NULL DEFAULT NULL AFTER `respon_admin`;

-- 1b. Kolom `status` sebelumnya ENUM('Pending','Disetujui','Ditolak') sehingga menolak
--     nilai baru ('Menunggu', 'Mengganti Barang', 'Potong Jumlah Invoice').
--     Ubah jadi VARCHAR agar bebas menampung status apa pun tanpa migration ulang.
ALTER TABLE `retur`
    MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'Menunggu';

-- 2. Ganti nilai status lama 'Pending' menjadi 'Menunggu' agar konsisten dengan alur baru
UPDATE `retur` SET `status` = 'Menunggu' WHERE `status` = 'Pending';

-- 3. Tabel alokasi qty retur per item, dipakai saat status "Potong Jumlah Invoice"
--    (dan diisi otomatis 100% ke qty_warehouse saat status "Mengganti Barang")
--    CATATAN: tanpa FOREIGN KEY (hanya index biasa) supaya tidak bergantung pada
--    tipe kolom / engine persis dari tabel retur_detail yang bisa berbeda antar environment.
CREATE TABLE IF NOT EXISTS `retur_alokasi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `retur_detail_id` INT NOT NULL,
    `qty_warehouse` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `qty_stok_gudang` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `qty_waste` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `dibuat_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_retur_detail` (`retur_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
