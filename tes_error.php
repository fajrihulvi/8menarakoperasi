<?php
// File: tes_error.php
// MENYALAKAN SEMUA PESAN ERROR
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Mulai Tes Koneksi...</h1>";

// 1. Cek Apakah File Koneksi Ada?
if (file_exists('koneksi.php')) {
    echo "✅ File koneksi.php DITEMUKAN.<br>";
    include 'koneksi.php';
} elseif (file_exists('../koneksi.php')) {
    echo "✅ File koneksi.php DITEMUKAN (di folder luar).<br>";
    include '../koneksi.php';
} elseif (file_exists('config/koneksi.php')) {
    echo "✅ File koneksi.php DITEMUKAN (di folder config).<br>";
    include 'config/koneksi.php';
} else {
    die("❌ GAGAL TOTAL: File koneksi.php tidak ditemukan di mana-mana! Cek folder hosting Anda.");
}

// 2. Cek Variabel Koneksi
if (isset($conn)) {
    echo "✅ Variabel \$conn ditemukan.<br>";
    if ($conn) {
        echo "✅ KONEKSI DATABASE SUKSES!";
    } else {
        echo "❌ Koneksi Gagal: " . mysqli_connect_error();
    }
} elseif (isset($koneksi)) {
    echo "⚠️ Variabel \$conn TIDAK ADA, tapi \$koneksi ADA. (Anda harus ubah \$conn jadi \$koneksi di script api_gps.php)<br>";
} else {
    echo "❌ Variabel koneksi (\$conn) tidak ditemukan. Cek isi file koneksi.php Anda.";
}
?>