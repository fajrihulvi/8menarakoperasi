<?php
// File: api_gps.php
if (file_exists('config/koneksi.php')) { require 'config/koneksi.php'; } 
elseif (file_exists('koneksi.php')) { require 'koneksi.php'; } 
else { die("Koneksi Database Tidak Ditemukan"); }

// =============================================================
// PROTEKSI TOKEN PERANGKAT (OPSIONAL, AKTIF SAAT DIISI)
// Endpoint ini dipanggil aplikasi Android, jadi tidak bisa memakai sesi login.
// Isi GPS_DEVICE_TOKEN di config/gps_token.php untuk mewajibkan token.
// Selama file itu belum dibuat, endpoint tetap berjalan seperti sebelumnya.
// =============================================================
$__token_file = __DIR__ . '/config/gps_token.php';
if (file_exists($__token_file)) {
    $__token_sah = @include $__token_file;
    if (is_string($__token_sah) && $__token_sah !== '') {
        $__token_kirim = $_POST['token'] ?? ($_GET['token'] ?? '');
        if (!hash_equals($__token_sah, (string) $__token_kirim)) {
            http_response_code(403);
            die("Token perangkat tidak valid");
        }
    }
}

if (isset($_POST['lat']) && isset($_POST['lng']) && isset($_POST['driver_id'])) {

    // Validasi tipe data: id harus angka, koordinat harus numerik yang masuk akal
    $driver_id = (int) $_POST['driver_id'];
    $lat_raw   = $_POST['lat'];
    $lng_raw   = $_POST['lng'];

    if ($driver_id <= 0 || !is_numeric($lat_raw) || !is_numeric($lng_raw)) {
        http_response_code(400);
        die("Data tidak valid");
    }

    $lat = (float) $lat_raw;
    $lng = (float) $lng_raw;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        die("Koordinat di luar jangkauan");
    }

    $status_gps = (isset($_POST['status_gps']) && strtoupper($_POST['status_gps']) === 'OFF') ? 'OFF' : 'ON';

    // MENANGKAP KECEPATAN DARI ANDROID (Jika ada)
    $speed_post = isset($_POST['speed']) ? (float)$_POST['speed'] : 0;

    // Jika GPS dimatikan, speed = -1 (Anti-Cheat). Jika hidup, gunakan speed asli (dibulatkan)
    $speed = ($status_gps == 'OFF') ? -1 : round($speed_post);

    // Hanya update user yang benar-benar berperan driver
    $update_sukses = false;
    $stmt_up = mysqli_prepare($conn,
        "UPDATE users SET latitude = ?, longitude = ?, speed = ?, last_location_update = NOW()
         WHERE id = ? AND LOWER(role) = 'driver'");
    if ($stmt_up) {
        mysqli_stmt_bind_param($stmt_up, 'dddi', $lat, $lng, $speed, $driver_id);
        $update_sukses = mysqli_stmt_execute($stmt_up);
        mysqli_stmt_close($stmt_up);
    }

    // Fallback bila kolom `speed` belum ada di tabel users
    if (!$update_sukses) {
        $stmt_up2 = mysqli_prepare($conn,
            "UPDATE users SET latitude = ?, longitude = ?, last_location_update = NOW()
             WHERE id = ? AND LOWER(role) = 'driver'");
        if ($stmt_up2) {
            mysqli_stmt_bind_param($stmt_up2, 'ddi', $lat, $lng, $driver_id);
            $update_sukses = mysqli_stmt_execute($stmt_up2);
            mysqli_stmt_close($stmt_up2);
        }
    }

    if ($update_sukses && $status_gps == 'ON') {
        $last = null;
        $stmt_last = mysqli_prepare($conn,
            "SELECT latitude, longitude FROM history_perjalanan WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        if ($stmt_last) {
            mysqli_stmt_bind_param($stmt_last, 'i', $driver_id);
            mysqli_stmt_execute($stmt_last);
            $res_last = mysqli_stmt_get_result($stmt_last);
            $last = $res_last ? mysqli_fetch_assoc($res_last) : null;
            mysqli_stmt_close($stmt_last);
        }

        $simpan = true;
        if($last) {
            $diff = abs($last['latitude'] - $lat) + abs($last['longitude'] - $lng);
            if($diff < 0.00005) { $simpan = false; }
        }

        if($simpan) {
            $stmt_ins = mysqli_prepare($conn,
                "INSERT INTO history_perjalanan (user_id, latitude, longitude) VALUES (?, ?, ?)");
            if ($stmt_ins) {
                mysqli_stmt_bind_param($stmt_ins, 'idd', $driver_id, $lat, $lng);
                mysqli_stmt_execute($stmt_ins);
                mysqli_stmt_close($stmt_ins);
            }
        }
        echo "OK";
    } else {
        echo "Gagal Update DB";
    }
} else {
    echo "Menunggu Data dari Aplikasi Android...";
}
?>