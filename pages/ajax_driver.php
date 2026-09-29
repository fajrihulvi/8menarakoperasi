<?php
// pages/ajax_driver.php
session_start();
require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
wajib_login_ajax(['admin', 'po', 'gudang', 'viewer', 'pelanggan', 'invoice', 'driver']);
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =========================================================================
// SIMPAN POSISI DRIVER (dikirim berkala oleh pages/driver_panel.php)
// Tanpa handler ini koordinat tidak pernah tersimpan, sehingga pin di peta
// tidak bergerak dan status driver selalu terbaca Offline.
// =========================================================================
if ($action == 'update_location') {
    $uid = (int) ($_SESSION['user_id'] ?? 0);

    // Hanya driver yang boleh menulis posisinya sendiri.
    if ($uid <= 0 || strtolower($_SESSION['role'] ?? '') !== 'driver') {
        echo json_encode(['status' => 'error', 'msg' => 'Hanya driver yang dapat mengirim lokasi.']);
        exit;
    }

    // is_numeric() penting: (float)"abc" menghasilkan 0, dan koordinat 0,0
    // adalah titik di Samudra Atlantik — pin akan melompat ke sana.
    $lat_in = $_POST['lat'] ?? '';
    $lng_in = $_POST['lng'] ?? '';

    if (!is_numeric($lat_in) || !is_numeric($lng_in)) {
        echo json_encode(['status' => 'error', 'msg' => 'Koordinat tidak valid.']);
        exit;
    }

    $lat     = (float) $lat_in;
    $lng     = (float) $lng_in;
    $heading = is_numeric($_POST['heading'] ?? null) ? (float) $_POST['heading'] : 0;
    $speed   = is_numeric($_POST['speed']   ?? null) ? (float) $_POST['speed']   : 0;

    // Tolak koordinat di luar rentang wajar, termasuk 0,0 yang hampir pasti
    // berarti pembacaan GPS gagal, bukan posisi sebenarnya.
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0 && $lng == 0)) {
        echo json_encode(['status' => 'error', 'msg' => 'Koordinat tidak valid.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE users
        SET latitude = ?, longitude = ?, heading = ?, speed = ?,
            is_active = 1, last_location_update = NOW()
        WHERE id = ? AND role = 'driver'");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ddddi', $lat, $lng, $heading, $speed, $uid);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            // Rekam jejak rute, tapi jangan menumpuk titik yang praktis sama
            // (driver berhenti/lampu merah) agar tabel history tidak membengkak.
            $simpan_jejak = true;
            $q_last = mysqli_query($conn, "SELECT latitude, longitude, waktu FROM history_perjalanan WHERE user_id='$uid' ORDER BY id DESC LIMIT 1");
            if ($q_last && ($last = mysqli_fetch_assoc($q_last))) {
                $sama_posisi = (abs((float)$last['latitude'] - $lat) < 0.00005)
                            && (abs((float)$last['longitude'] - $lng) < 0.00005);
                $baru_saja   = (time() - strtotime($last['waktu'])) < 10;
                if ($sama_posisi || $baru_saja) { $simpan_jejak = false; }
            }

            if ($simpan_jejak) {
                $stmt_h = mysqli_prepare($conn, "INSERT INTO history_perjalanan (user_id, latitude, longitude, heading) VALUES (?, ?, ?, ?)");
                if ($stmt_h) {
                    mysqli_stmt_bind_param($stmt_h, 'iddd', $uid, $lat, $lng, $heading);
                    mysqli_stmt_execute($stmt_h);
                    mysqli_stmt_close($stmt_h);
                }
            }

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan lokasi.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal menyiapkan query.']);
    }
    exit;
}

// =========================================================================
// DRIVER MENGHENTIKAN PELACAKAN (tombol STOP DRIVE)
// =========================================================================
if ($action == 'set_offline') {
    $uid = (int) ($_SESSION['user_id'] ?? 0);

    if ($uid > 0 && strtolower($_SESSION['role'] ?? '') === 'driver') {
        // Mundurkan waktu update agar langsung terbaca Offline oleh pemantau.
        mysqli_query($conn, "UPDATE users SET is_active = 0, speed = 0,
                             last_location_update = (NOW() - INTERVAL 2 HOUR)
                             WHERE id = '$uid' AND role = 'driver'");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// =========================================================================
// GPS DIMATIKAN PAKSA OLEH DRIVER (speed = -1 sebagai penanda)
// =========================================================================
if ($action == 'gps_off') {
    $uid = (int) ($_SESSION['user_id'] ?? 0);

    if ($uid > 0 && strtolower($_SESSION['role'] ?? '') === 'driver') {
        mysqli_query($conn, "UPDATE users SET speed = -1, is_active = 1,
                             last_location_update = NOW()
                             WHERE id = '$uid' AND role = 'driver'");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

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