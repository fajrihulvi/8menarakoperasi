<?php
// fix_collation.php - Script Perbaikan Format Database
require 'config/koneksi.php';

echo "<h1>🛠️ Sedang Menyamakan Format Database...</h1>";

// Daftar tabel yang terlibat dalam transaksi
$tables = [
    'transaksi', 
    'transaksi_detail', 
    'pesanan', 
    'pesanan_detail', 
    'pelanggan', 
    'barang', 
    'users',
    'pengaturan'
];

foreach ($tables as $table) {
    // Ubah format tabel ke utf8mb4_general_ci (Format Standar)
    $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✅ Tabel <b>$table</b> berhasil diperbarui ke utf8mb4_general_ci.<br>";
    } else {
        echo "❌ Gagal update $table: " . mysqli_error($conn) . "<br>";
    }
}

echo "<hr>";
echo "<h3>🎉 SELESAI! Semua tabel sudah sinkron.</h3>";
echo "<p>Silahkan coba <a href='index.php'>Kembali ke Dashboard</a> dan cetak Invoice lagi.</p>";
echo "<p><i>Anda boleh menghapus file ini setelah berhasil.</i></p>";
?>