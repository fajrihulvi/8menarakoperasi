<?php
// pages/ajax_retur.php
session_start();
// Matikan error reporting agar warning tidak merusak format JSON
error_reporting(0);
ini_set('display_errors', 0);

require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
wajib_login_ajax(['admin', 'po', 'gudang', 'invoice']);

header('Content-Type: application/json'); 

$action = $_POST['action'] ?? '';

// [BARU] AMBIL ITEM DARI PESANAN UNTUK DITAMPILKAN DI MODAL RETUR
if($action == 'get_items_pesanan') {
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    
    // Ambil ID pesanan berdasarkan no_pesanan
    $cek_pesanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM pesanan WHERE no_pesanan='$no_pesanan'"));
    
    if($cek_pesanan) {
        $id_pesanan = $cek_pesanan['id'];
        $items = [];
        
        $q_item = mysqli_query($conn, "SELECT d.id_barang, d.qty, b.nama_barang, b.satuan 
                                       FROM pesanan_detail d 
                                       JOIN barang b ON d.id_barang = b.id 
                                       WHERE d.id_pesanan = '$id_pesanan'");
        
        while($d = mysqli_fetch_assoc($q_item)) {
            $items[] = [
                'id_barang' => $d['id_barang'],
                'nama_barang' => $d['nama_barang'],
                'qty_beli' => (float)$d['qty'],
                'satuan' => $d['satuan']
            ];
        }
        echo json_encode(['status' => 'success', 'items' => $items]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Pesanan tidak ditemukan']);
    }
    exit;
}

// 1. PROSES PENGAJUAN RETUR (PELANGGAN)
if($action == 'ajukan_retur') {
    $id_usaha   = $_SESSION['id_usaha'];
    $user_id    = $_SESSION['user_id'];
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    $alasan     = mysqli_real_escape_string($conn, $_POST['alasan']);
    $no_retur   = "RET-" . date('ymdHis');
    
    // Validasi Item
    $barang_ids = $_POST['barang_id'] ?? [];
    $qtys       = $_POST['qty_retur'] ?? [];
    $has_item   = false;

    // Cek apakah ada item yang diretur > 0
    foreach($qtys as $q) { if((float)$q > 0) $has_item = true; }

    if(!$has_item) {
        echo json_encode(['status' => 'error', 'msg' => 'Harap isi jumlah barang yang ingin diretur (minimal 1)']);
        exit;
    }

    // Upload Foto Bukti
    $foto_nama = null;
    if(!empty($_FILES['foto']['name'])){
        $target_dir = "../assets/img/bukti_retur/";
        
        // Buat folder jika belum ada (Mencegah warning error)
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nama = "retur_" . time() . "." . $ext;
        
        // Pindahkan file
        move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_nama);
    }

    // Insert Header Retur
    $q = mysqli_query($conn, "INSERT INTO retur (id_usaha, no_retur, no_pesanan, user_id, alasan, foto_bukti, status) 
                              VALUES ('$id_usaha', '$no_retur', '$no_pesanan', '$user_id', '$alasan', '$foto_nama', 'Pending')");
    
    if($q) {
        $retur_id = mysqli_insert_id($conn);
        
        // Insert Detail Barang yang diretur
        foreach($barang_ids as $k => $id_brg) {
            $qty = (float)$qtys[$k];
            if($qty > 0) {
                mysqli_query($conn, "INSERT INTO retur_detail (retur_id, barang_id, qty) VALUES ('$retur_id', '$id_brg', '$qty')");
            }
        }
        echo json_encode(['status' => 'success', 'msg' => 'Pengajuan Retur Berhasil! Menunggu persetujuan Admin.']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan data DB: ' . mysqli_error($conn)]);
    }
    exit;
}

// 2. PROSES PERSETUJUAN RETUR (ADMIN/PO)
if($action == 'proses_retur') {
    $id_retur = $_POST['id_retur'];
    $keputusan = $_POST['keputusan']; // Disetujui / Ditolak
    $catatan   = mysqli_real_escape_string($conn, $_POST['catatan']);

    $update = mysqli_query($conn, "UPDATE retur SET status='$keputusan', respon_admin='$catatan' WHERE id='$id_retur'");

    if($update && $keputusan == 'Disetujui') {
        // Jika disetujui, kembalikan stok barang
        $items = mysqli_query($conn, "SELECT * FROM retur_detail WHERE retur_id='$id_retur'");
        while($item = mysqli_fetch_assoc($items)) {
            mysqli_query($conn, "UPDATE barang SET stok = stok + {$item['qty']} WHERE id='{$item['barang_id']}'");
        }
    }

    echo json_encode(['status' => 'success', 'msg' => 'Status Retur Diperbarui']);
    exit;
}
?>