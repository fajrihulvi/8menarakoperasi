<?php
// ajax_get_driver_location.php
header('Content-Type: application/json');
require 'config/koneksi.php'; // Sesuaikan path koneksi

// PROTEKSI: hanya user login dengan role terkait yang boleh melihat posisi driver
wajib_login_ajax(['admin','po','accounting','invoice','pelanggan','driver']);

// Ambil Driver yang rolenya 'driver'
// Pastikan tabel `users` memiliki kolom `latitude`, `longitude`, `last_login`, dan `nopol` (Nomor Polisi)
// Jika nopol ada di tabel profil terpisah, sesuaikan query JOIN-nya.

$query = mysqli_query($conn, "SELECT id, nama_lengkap as nama, latitude, longitude, last_login FROM users WHERE role = 'driver'");

$data = [];
while($row = mysqli_fetch_assoc($query)) {
    // Cek status online (jika last_login < 5 menit yang lalu dianggap online)
    $last_active = strtotime($row['last_login']);
    $diff = time() - $last_active;
    $status = ($diff < 300) ? 'online' : 'offline'; // 300 detik = 5 menit

    $data[] = [
        'id'   => $row['id'],
        'nama' => $row['nama'],
        'nopol' => 'B 1234 CD', // Ganti dengan kolom nopol dari DB jika ada
        'lat'  => $row['latitude'],
        'lng'  => $row['longitude'],
        'status' => $status,
        'last_update' => date('H:i:s', $last_active)
    ];
}

echo json_encode($data);
?>