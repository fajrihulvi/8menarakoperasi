<?php
// ajax_chat_team.php (VERSI SUPER RINGAN - HANYA STATUS ONLINE)
ob_start();
session_start();
error_reporting(0); // Matikan error warning agar JSON tidak rusak

header('Content-Type: application/json; charset=utf-8');

// --- KONEKSI DATABASE ---
require 'config/koneksi.php';

// Helper respon JSON
function send_json($data) {
    ob_clean();
    echo json_encode($data);
    exit;
}

// Cek Login
if (!isset($_SESSION['login'])) {
    send_json(['status' => 'error', 'message' => 'Sesi habis']);
}

$my_id = $_SESSION['user_id'];
$my_name = $_SESSION['nama'] ?? 'User';

// FIX KEMARIN: BEBASKAN SESSION LOCK AGAR WEBSITE TIDAK HANG!
session_write_close();

// 1. UPDATE STATUS ONLINE (Ke tabel users)
// Menggunakan @ agar error query tidak merusak format JSON
@mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = '$my_id'");

// 2. HANDLE REQUEST
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$response = [];

if ($action == 'get_all') {
    
    // A. LIST USER ONLINE (Aktif 5 menit terakhir)
    $users = [];
    $q_user = mysqli_query($conn, "SELECT id, nama, username, role FROM users WHERE last_activity > (NOW() - INTERVAL 5 MINUTE) ORDER BY last_activity DESC");
    
    if($q_user) {
        while($u = mysqli_fetch_assoc($q_user)) {
            $is_me = ($u['id'] == $my_id);
            $display_name = !empty($u['nama']) ? $u['nama'] : $u['username'];
            $nama_panggil = explode(' ', $display_name)[0]; 

            $users[] = [
                'id' => $u['id'],
                'nama' => $is_me ? "Anda" : $nama_panggil,
                'role' => $u['role'] ?? 'User',
                'inisial' => strtoupper(substr($display_name, 0, 1)),
                'is_me' => $is_me
            ];
        }
    }
    
    // Kembalikan daftar user yang online
    $response['users'] = $users;
    
    // B. KOSONGKAN LIST CHAT AGAR SERVER RINGAN & FRONTEND TIDAK ERROR
    $response['chats'] = []; 

} else {
    // Jika ada aksi lain (seperti send_chat atau video_call dari kode lama), matikan.
    $response['status'] = 'disabled';
    $response['message'] = 'Fitur chat dinonaktifkan demi performa server.';
}

send_json($response);
?>