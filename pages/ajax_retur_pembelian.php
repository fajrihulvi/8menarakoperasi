<?php
// pages/ajax_retur_pembelian.php
session_start();
require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
wajib_login_ajax(['admin', 'po']);
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if($action == 'simpan_retur_beli') {
    $id_usaha    = $_SESSION['id_usaha'];
    $user_id     = $_SESSION['user_id'];
    $supplier_id = $_POST['supplier_id'];
    $tanggal     = $_POST['tanggal'] . ' ' . date('H:i:s');
    $alasan      = mysqli_real_escape_string($conn, $_POST['alasan']);
    
    $d_sup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_supplier FROM supplier WHERE id='$supplier_id'"));
    $nama_supplier = mysqli_real_escape_string($conn, $d_sup['nama_supplier']);

    $no_retur = "RB-" . date('ymdHis'); // Kode Retur Beli

    $q_header = "INSERT INTO retur_pembelian (id_usaha, no_retur, tanggal, supplier_id, nama_supplier, alasan, user_id) 
                 VALUES ('$id_usaha', '$no_retur', '$tanggal', '$supplier_id', '$nama_supplier', '$alasan', '$user_id')";
    
    if(mysqli_query($conn, $q_header)) {
        $retur_id = mysqli_insert_id($conn);
        $barang_ids = $_POST['id_barang'];
        $qtys       = $_POST['qty'];
        
        foreach($barang_ids as $k => $id_brg) {
            $qty = (float)$qtys[$k];
            if($qty > 0 && !empty($id_brg)) {
                mysqli_query($conn, "INSERT INTO retur_pembelian_detail (retur_id, barang_id, qty) VALUES ('$retur_id', '$id_brg', '$qty')");
                // KURANGI STOK GUDANG
                mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id='$id_brg'");
            }
        }
        echo json_encode(['status' => 'success', 'msg' => 'Retur Berhasil Disimpan & Stok Dikurangi', 'id' => $retur_id]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => mysqli_error($conn)]);
    }
}
?>