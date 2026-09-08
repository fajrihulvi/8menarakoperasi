<?php
// fix_database.php - Script Otomatis Perbaikan Database
require 'config/koneksi.php';

echo "<h1>🛠️ Sedang Memperbaiki Database...</h1>";

// 1. Buat Tabel PESANAN jika belum ada
$sql_pesanan = "CREATE TABLE IF NOT EXISTS `pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usaha` int(11) NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `no_pesanan` varchar(50) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `lokasi_kirim` varchar(150) DEFAULT NULL,
  `nama_driver` varchar(100) DEFAULT NULL,
  `nopol` varchar(50) DEFAULT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `status` enum('Pending','Persiapan','Pengiriman','Selesai','Batal') DEFAULT 'Pending',
  `catatan` text NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if(mysqli_query($conn, $sql_pesanan)) {
    echo "✅ Tabel 'pesanan' siap.<br>";
} else {
    echo "❌ Gagal buat tabel pesanan: " . mysqli_error($conn) . "<br>";
}

// 2. Buat Tabel PESANAN_DETAIL jika belum ada
$sql_detail = "CREATE TABLE IF NOT EXISTS `pesanan_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pesanan` int(11) NOT NULL,
  `id_barang` int(11) NOT NULL,
  `qty` decimal(10,2) NOT NULL, 
  `harga_satuan` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if(mysqli_query($conn, $sql_detail)) {
    echo "✅ Tabel 'pesanan_detail' siap.<br>";
} else {
    echo "❌ Gagal buat tabel pesanan_detail: " . mysqli_error($conn) . "<br>";
}

// 3. Tambahkan Kolom yang Hilang di Tabel TRANSAKSI
$kolom_transaksi = [
    "ADD COLUMN nama_driver varchar(100) DEFAULT NULL",
    "ADD COLUMN nopol varchar(50) DEFAULT NULL",
    "ADD COLUMN lokasi_kirim text DEFAULT NULL",
    "ADD COLUMN pelanggan_id int(11) DEFAULT 0"
];

foreach($kolom_transaksi as $cmd) {
    // Gunakan IGNORE agar tidak error jika kolom sudah ada
    @mysqli_query($conn, "ALTER TABLE `transaksi` $cmd");
}
echo "✅ Struktur Tabel 'transaksi' diperbarui.<br>";

// 4. Tambahkan Kolom yang Hilang di Tabel PESANAN (Jika tabel lama sudah ada)
$kolom_pesanan = [
    "ADD COLUMN user_id int(11) DEFAULT 0",
    "ADD COLUMN nama_driver varchar(100) DEFAULT NULL",
    "ADD COLUMN nopol varchar(50) DEFAULT NULL",
    "ADD COLUMN lokasi_kirim text DEFAULT NULL"
];

foreach($kolom_pesanan as $cmd) {
    @mysqli_query($conn, "ALTER TABLE `pesanan` $cmd");
}
echo "✅ Struktur Tabel 'pesanan' diperbarui.<br>";

echo "<hr><h3>🎉 SELESAI! Database Sudah Sinkron.</h3>";
echo "<p>Silahkan coba cetak Invoice/SJ lagi. Hapus file ini jika sudah berhasil.</p>";
echo "<a href='index.php'>Kembali ke Dashboard</a>";
?>