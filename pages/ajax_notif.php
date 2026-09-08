<?php
// pages/ajax_notif.php
session_start();
// --- PERBAIKAN KONEKSI DATABASE ---
if (file_exists('../config/koneksi.php')) {
    require '../config/koneksi.php';
} elseif (file_exists('../../config/koneksi.php')) {
    require '../../config/koneksi.php';
} else {
    exit; // Silent exit jika koneksi gagal
}

// Penjaga endpoint: wajib login (config/hak_akses.php)
wajib_login_ajax([]);

header('Content-Type: application/json');

if (!isset($_SESSION['login'])) exit;

$id_usaha = $_SESSION['id_usaha'] ?? 0;
$role     = $_SESSION['role'] ?? '';
$user_id  = $_SESSION['user_id'] ?? 0;

// FIX: BEBASKAN SESSION LOCK AGAR REQUEST AJAX LAIN BISA BERJALAN (Mencegah PHP Nyangkut)
session_write_close();

// --- LOGIKA NOTIFIKASI BERDASARKAN ROLE ---

if ($role == 'pelanggan') {
    // ============================================================
    // LOGIKA PELANGGAN:
    // Deteksi status 'Persiapan' atau 'Pengiriman'
    // ============================================================
    
    $q = mysqli_query($conn, "SELECT status, no_pesanan FROM pesanan 
                              WHERE id_usaha = '$id_usaha' 
                              AND pelanggan_id = '$user_id' 
                              AND status IN ('Persiapan', 'Pengiriman') 
                              ORDER BY id DESC LIMIT 1");
                              
    $data = mysqli_fetch_assoc($q);
    
    if ($data) {
        echo json_encode([
            'status'       => 'bunyi_pelanggan',
            'no_pesanan'   => $data['no_pesanan'],
            'status_order' => $data['status'],
            'pesan'        => "Status Order " . $data['no_pesanan'] . " berubah menjadi: " . $data['status']
        ]);
    } else {
        echo json_encode(['status' => 'bersih']);
    }

} elseif (in_array($role, ['admin', 'po'])) {
    // ============================================================
    // LOGIKA KHUSUS ADMIN & PO (Role Lain TIDAK AKAN MUNCUL)
    // Bunyi TERUS MENERUS saat ada orderan 'Pending'
    // ============================================================
    
    $q_pending = mysqli_query($conn, "SELECT COUNT(*) as jumlah FROM pesanan WHERE id_usaha = '$id_usaha' AND status = 'Pending'");
    $d_pending = mysqli_fetch_assoc($q_pending);
    $jumlah_pending = $d_pending['jumlah'];

    if ($jumlah_pending > 0) {
        echo json_encode([
            'status' => 'bunyi_admin',
            'jumlah' => $jumlah_pending,
            'pesan'  => "Ada $jumlah_pending Orderan Baru Menunggu!"
        ]);
    } else {
        echo json_encode(['status' => 'bersih']);
    }

} else {
    // ============================================================
    // ROLE LAIN (Invoice, Chef, Driver, Gizi, dll)
    // Tidak menampilkan notifikasi apapun
    // ============================================================
    echo json_encode(['status' => 'bersih']);
}
?>