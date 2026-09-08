<?php
// Simpan di: pages/ajax_video_signal.php
session_start();
require '../config/koneksi.php'; // Pastikan path config benar
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$room_id = $_POST['room_id'] ?? '';
// Tentukan peran: Admin atau Driver
$my_role = ($_SESSION['role'] == 'driver') ? 'driver' : 'admin';

if (!$room_id) exit(json_encode(['status'=>'error', 'msg'=>'No Room ID']));

// 1. KIRIM SINYAL (OFFER / ANSWER / CANDIDATE)
if ($action == 'send_signal') {
    $type = $_POST['type'];
    $data = $_POST['data'];
    
    // Jika Admin memulai panggilan baru (OFFER), 
    // hapus semua sinyal lama di room ini agar bersih
    if ($type == 'offer') {
        mysqli_query($conn, "DELETE FROM video_call_signaling WHERE room_id='$room_id'");
    }

    // Simpan sinyal ke database
    $stmt = $conn->prepare("INSERT INTO video_call_signaling (room_id, type, data, sender) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $room_id, $type, $data, $my_role);
    
    if($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
}

// 2. TERIMA SINYAL (POLLING)
if ($action == 'get_signal') {
    // Ambil sinyal dari LAWAN BICARA (sender != saya)
    // Hanya ambil sinyal yang dibuat 2 menit terakhir (agar tidak ambil sampah lama)
    $q = mysqli_query($conn, "SELECT * FROM video_call_signaling 
                              WHERE room_id='$room_id' 
                              AND sender != '$my_role' 
                              AND created_at > (NOW() - INTERVAL 2 MINUTE)
                              ORDER BY id ASC");
    
    $signals = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $signals[] = $row;
    }
    echo json_encode($signals);
}
?>