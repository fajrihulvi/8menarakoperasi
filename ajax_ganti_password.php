<?php
// ajax_ganti_password.php
ob_start();
session_start();
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

require 'config/koneksi.php';

function send_json($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['login']) || empty($_SESSION['user_id'])) {
    send_json(['status' => 'error', 'message' => 'Sesi Anda telah habis. Silakan login ulang.']);
}

$user_id           = $_SESSION['user_id'];
$password_lama     = $_POST['password_lama'] ?? '';
$password_baru     = $_POST['password_baru'] ?? '';
$konfirmasi        = $_POST['konfirmasi_password'] ?? '';

if ($password_lama === '' || $password_baru === '' || $konfirmasi === '') {
    send_json(['status' => 'error', 'message' => 'Semua kolom wajib diisi.']);
}

if (strlen($password_baru) < 6) {
    send_json(['status' => 'error', 'message' => 'Password baru minimal 6 karakter.']);
}

if ($password_baru !== $konfirmasi) {
    send_json(['status' => 'error', 'message' => 'Konfirmasi password baru tidak cocok.']);
}

$q = mysqli_query($conn, "SELECT password FROM users WHERE id='" . (int)$user_id . "'");
$d = $q ? mysqli_fetch_assoc($q) : null;

if (!$d) {
    send_json(['status' => 'error', 'message' => 'Akun tidak ditemukan.']);
}

$hash_tersimpan = $d['password'] ?? '';
$password_valid = false;

if (password_verify($password_lama, $hash_tersimpan)) {
    $password_valid = true;
} elseif (strlen($hash_tersimpan) === 32 && md5($password_lama) === $hash_tersimpan) {
    // Dukungan legacy MD5, sama seperti alur login
    $password_valid = true;
}

if (!$password_valid) {
    send_json(['status' => 'error', 'message' => 'Password lama tidak sesuai.']);
}

$hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $hash_baru, $user_id);
$sukses = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($sukses) {
    if (function_exists('catat_log')) {
        catat_log($conn, 'Ganti Password', 'User mengganti password akun sendiri.');
    }
    send_json(['status' => 'success']);
} else {
    send_json(['status' => 'error', 'message' => 'Gagal menyimpan password baru.']);
}
