<?php
session_start();
require 'config/koneksi.php';

header('Content-Type: application/json');

// Cek Login
if (!isset($_SESSION['login'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Akses Ditolak']);
    exit;
}

$id_usaha = $_SESSION['id_usaha'] ?? 1;
$user_id  = $_SESSION['user_id'] ?? 0;
$role     = $_SESSION['role'] ?? ''; // 'admin' (Manager), 'po' (Admin PO)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang  = mysqli_real_escape_string($conn, $_POST['id']);
    $stok_baru  = (float)$_POST['stok'];
    $harga_baru = (float)$_POST['harga'];

    // Ambil Info Barang Lama (Untuk Keterangan)
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_barang, stok, harga_jual FROM barang WHERE id='$id_barang'"));
    $nama_brg = $cek['nama_barang'] ?? 'Unknown';

    // --- LOGIKA UTAMA --- //
    
    // JIKA ROLE ADALAH 'PO' (Butuh Approval)
    if ($role == 'po') {
        // 1. Siapkan Data dalam format JSON
        $data_simpan = [
            'id_barang' => $id_barang,
            'stok_baru' => $stok_baru,
            'harga_baru'=> $harga_baru
        ];
        $json_data = json_encode($data_simpan);
        
        // 2. Buat Keterangan
        $ket = "Update Barang: $nama_brg. Stok ($cek[stok] -> $stok_baru), Harga ($cek[harga_jual] -> $harga_baru)";

        // 3. Masukkan ke Tabel Approval (Status Pending)
        $q = "INSERT INTO approval_request (id_usaha, user_id, tipe_aksi, keterangan, data_json, status) 
              VALUES ('$id_usaha', '$user_id', 'update_stok', '$ket', '$json_data', 'pending')";
        
        if(mysqli_query($conn, $q)) {
            echo json_encode(['status' => 'success', 'msg' => 'Data dikirim ke Manager untuk disetujui.']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal request approval.']);
        }
    } 
    
    // JIKA ROLE ADALAH 'ADMIN' (Manager / Super Admin) - Langsung Eksekusi
    else if ($role == 'admin') {
        // ... (Kode update langsung seperti sebelumnya) ...
        $update = mysqli_query($conn, "UPDATE barang SET stok='$stok_baru', harga_jual='$harga_baru' WHERE id='$id_barang' AND id_usaha='$id_usaha'");
        
        if ($update) {
            // Catat History Harga
            if((float)$cek['harga_jual'] != $harga_baru) {
                mysqli_query($conn, "INSERT INTO riwayat_harga (barang_id, harga_jual_lama, harga_jual_baru, tgl_perubahan, user_id) VALUES ('$id_barang', '{$cek['harga_jual']}', '$harga_baru', NOW(), '$user_id')");
            }
            echo json_encode(['status' => 'success', 'msg' => 'Data Berhasil Disimpan (Direct)']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal update DB']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Role tidak diizinkan']);
    }

} else {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid Request']);
}
?>