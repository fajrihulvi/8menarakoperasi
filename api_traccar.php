<?php
// assets/pages/api_traccar.php (Support Nopol)

// 1. CARI KONEKSI OTOMATIS
if (file_exists('config/koneksi.php')) {
    include 'config/koneksi.php';
} elseif (file_exists('../config/koneksi.php')) {
    include '../config/koneksi.php';
} elseif (file_exists('../../config/koneksi.php')) {
    include '../../config/koneksi.php';
} else {
    http_response_code(500);
    die("CRITICAL ERROR: File koneksi database tidak ditemukan.");
}

// =============================================================
// PROTEKSI TOKEN PERANGKAT (OPSIONAL, AKTIF SAAT DIISI)
// Endpoint ini dipanggil aplikasi Traccar, jadi tidak bisa memakai sesi login.
// Isi token di config/gps_token.php untuk mewajibkannya.
// =============================================================
$__token_file = __DIR__ . '/config/gps_token.php';
if (file_exists($__token_file)) {
    $__token_sah = @include $__token_file;
    if (is_string($__token_sah) && $__token_sah !== '') {
        $__token_kirim = $_REQUEST['token'] ?? '';
        if (!hash_equals($__token_sah, (string) $__token_kirim)) {
            http_response_code(403);
            die("Token perangkat tidak valid");
        }
    }
}

// 2. TANGKAP DATA (Input dari Aplikasi Traccar berisi NOPOL di parameter 'id')
$nopol_input = trim($_REQUEST['id'] ?? ''); // Ini isinya Plat Nomor (misal: B1234XYZ)
$lat         = $_REQUEST['lat'] ?? '';
$lon         = $_REQUEST['lon'] ?? '';
$speed       = $_REQUEST['speed'] ?? 0;
$heading     = $_REQUEST['bearing'] ?? 0;

// Validasi koordinat sebelum dipakai
if ($lat !== '' && $lon !== '') {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        http_response_code(400);
        die("Koordinat tidak valid");
    }
    $lat = (float) $lat;
    $lon = (float) $lon;
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        http_response_code(400);
        die("Koordinat di luar jangkauan");
    }
}

$heading = (float) $heading;

// Konversi Speed (Knots ke KM/H)
$speed_kmh = ((float) $speed) * 1.852;

// 3. PROSES DATA
if (!empty($nopol_input) && !empty($lat) && !empty($lon)) {

    // --- PERUBAHAN UTAMA DISINI ---
    // Cari ID User berdasarkan NOPOL
    // Kita hilangkan spasi dulu jaga-jaga beda format (misal B 1234 AA vs B1234AA)
    
    // OPSI 1: Pencarian Persis (Exact Match) — hanya user berperan driver
    $data_user = null;
    $stmt_cari = mysqli_prepare($conn,
        "SELECT id, nama FROM users WHERE nopol = ? AND LOWER(role) = 'driver' LIMIT 1");
    if ($stmt_cari) {
        mysqli_stmt_bind_param($stmt_cari, 's', $nopol_input);
        mysqli_stmt_execute($stmt_cari);
        $res_cari = mysqli_stmt_get_result($stmt_cari);
        $data_user = $res_cari ? mysqli_fetch_assoc($res_cari) : null;
        mysqli_stmt_close($stmt_cari);
    }

    // OPSI 2 (JIKA OPSI 1 GAGAL): Cari Username (Jaga-jaga driver isi username)
    if (!$data_user) {
        $stmt_cari2 = mysqli_prepare($conn,
            "SELECT id, nama FROM users WHERE username = ? AND LOWER(role) = 'driver' LIMIT 1");
        if ($stmt_cari2) {
            mysqli_stmt_bind_param($stmt_cari2, 's', $nopol_input);
            mysqli_stmt_execute($stmt_cari2);
            $res_cari2 = mysqli_stmt_get_result($stmt_cari2);
            $data_user = $res_cari2 ? mysqli_fetch_assoc($res_cari2) : null;
            mysqli_stmt_close($stmt_cari2);
        }
    }

    if($data_user) {
        $real_user_id = (int) $data_user['id']; // Ini ID asli (angka) dari database

        // UPDATE POSISI
        $ok_update = false;
        $stmt_pos = mysqli_prepare($conn,
            "UPDATE users SET latitude = ?, longitude = ?, heading = ?, is_active = 1,
                              last_location_update = NOW()
             WHERE id = ?");
        if ($stmt_pos) {
            mysqli_stmt_bind_param($stmt_pos, 'dddi', $lat, $lon, $heading, $real_user_id);
            $ok_update = mysqli_stmt_execute($stmt_pos);
            mysqli_stmt_close($stmt_pos);
        }

        if($ok_update) {
            echo "OK";
        } else {
            // Jangan bocorkan detail error database ke pemanggil
            echo "DB Error";
        }

        // SIMPAN HISTORY
        // Kita simpan ID User (Angka) agar relasi database tetap benar
        $stmt_his = mysqli_prepare($conn,
            "INSERT INTO history_perjalanan (user_id, latitude, longitude, heading, speed)
             VALUES (?, ?, ?, ?, ?)");
        if ($stmt_his) {
            mysqli_stmt_bind_param($stmt_his, 'idddd', $real_user_id, $lat, $lon, $heading, $speed_kmh);
            mysqli_stmt_execute($stmt_his);
            mysqli_stmt_close($stmt_his);
        }

    } else {
        // Jika Nopol tidak ditemukan di database (escape agar tidak jadi XSS)
        echo "Nopol/User '" . htmlspecialchars($nopol_input, ENT_QUOTES, 'UTF-8') . "' Tidak Ditemukan";
    }

} else {
    echo "API Ready. Menunggu Data...";
}
?>