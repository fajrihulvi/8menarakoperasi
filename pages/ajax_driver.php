<?php
// pages/ajax_driver.php
session_start();
require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
wajib_login_ajax(['admin', 'po', 'gudang', 'viewer', 'pelanggan', 'invoice', 'driver']);
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'get_active_drivers') {
    $query = "SELECT id, nama, nopol, latitude, longitude, heading, speed, last_location_update 
              FROM users WHERE role='driver' ORDER BY nama ASC";
    $q = mysqli_query($conn, $query);
    
    $driver_online = [];
    $driver_offline = [];
    $waktu_sekarang = time();

    if($q){
        while($row = mysqli_fetch_assoc($q)) {
            
            // --- FITUR BARU: FORMAT WAKTU "LAST SEEN" ---
            $waktu_terakhir_format = "";
            if (empty($row['last_location_update'])) {
                $last_update = 0; 
            } else {
                $last_update = strtotime($row['last_location_update']);
                // Mengubah format waktu menjadi tanggal & jam (Contoh: 26/02/2026 14:30)
                $waktu_terakhir_format = date('d/m/Y H:i', $last_update);
            }

            $selisih_detik = $waktu_sekarang - $last_update;
            $speed = isset($row['speed']) ? $row['speed'] : 0;
            
            $is_online = false;

            // LOGIKA STATUS TAMPILAN
            if ($speed == -1 && $selisih_detik <= 60) {
                $row['status_text'] = "⚠ GPS Sengaja Dimatikan!";
                $row['status_color'] = "text-red-600 font-extrabold animate-pulse";
                $is_online = true; 
            } elseif ($selisih_detik > 90) { 
                // JIKA OFFLINE, TAMPILKAN WAKTU TERAKHIR IA MENGGUNAKAN APLIKASI
                if ($last_update == 0) {
                    $row['status_text'] = "⚪ Belum Pernah Login";
                    $row['status_color'] = "text-gray-400";
                } else {
                    $row['status_text'] = "🔴 Offline (Terakhir: " . $waktu_terakhir_format . ")";
                    $row['status_color'] = "text-red-400";
                }
                $is_online = false; 
            } elseif ($selisih_detik > 45) {
                $row['status_text'] = "🟡 Delay Jaringan";
                $row['status_color'] = "text-yellow-600";
                $is_online = true; 
            } else {
                $row['status_text'] = "🟢 Terhubung Kuat";
                $row['status_color'] = "text-green-600 font-bold";
                $is_online = true; 
            }

            if(empty($row['nopol'])) $row['nopol'] = "Mobil 8MP";
            
            // Ambil Rute History
            $id_drv = $row['id'];
            $q_hist = mysqli_query($conn, "SELECT latitude, longitude FROM history_perjalanan WHERE user_id='$id_drv' ORDER BY id DESC LIMIT 20");
            $path = [];
            if($q_hist) {
                while($h = mysqli_fetch_assoc($q_hist)) {
                    $path[] = [(float)$h['latitude'], (float)$h['longitude']];
                }
            }
            $row['rute'] = $path; 
            
            // PEMISAHAN POSISI ATAS BAWAH
            if ($is_online) {
                $driver_online[] = $row;
            } else {
                $driver_offline[] = $row;
            }
        }
    }
    
    // GABUNGKAN
    $drivers = array_merge($driver_online, $driver_offline);
    
    echo json_encode($drivers);
    exit;
}

// FITUR TOMBOL STOP ADMIN
if ($action == 'force_stop') {
    $driver_id = mysqli_real_escape_string($conn, $_POST['driver_id']);
    mysqli_query($conn, "UPDATE users SET is_active = 0, speed = 0, last_location_update = (NOW() - INTERVAL 2 HOUR) WHERE id = '$driver_id'");
    echo json_encode(['status' => 'success']);
    exit;
}
?>