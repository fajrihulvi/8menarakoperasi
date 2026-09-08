<?php
session_start();

// 1. Hapus Semua Data Session
$_SESSION = [];
session_unset();
session_destroy();

// 2. Hapus Cookie di Browser (Set waktu ke masa lalu agar expired)
setcookie('id_user', '', time() - 3600, '/');
setcookie('key', '', time() - 3600, '/');

// 3. Kembali ke Halaman Login
header("Location: login.php");
exit;
?>