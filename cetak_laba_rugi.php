<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'config/koneksi.php';

if (!isset($_SESSION['login'])) { exit; }

// Ambil ID Usaha (Cabang/Koperasi) yang sedang login
$id_usaha = $_SESSION['id_usaha'] ?? 1;

$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// Ambil Data Perusahaan untuk KOP SESUAI CABANG
$q_toko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha='$id_usaha' LIMIT 1");
$toko = mysqli_fetch_assoc($q_toko);

// Fallback jika data pengaturan cabang kosong
if(!$toko) {
    $toko = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan LIMIT 1"));
}

// 1. OMZET (FILTER CABANG)
$q_omzet = mysqli_query($conn, "SELECT SUM(total_transaksi) as total, COUNT(*) as jlh_trx 
    FROM transaksi 
    WHERE id_usaha='$id_usaha' 
    AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' 
    AND status='selesai'");
$d_omzet = mysqli_fetch_assoc($q_omzet);
$omzet = $d_omzet['total'] ?? 0;
$jlh_trx = $d_omzet['jlh_trx'] ?? 0;

// 2. HPP (FILTER CABANG)
$q_hpp = mysqli_query($conn, "SELECT SUM(b.harga_beli * td.qty) as total_hpp 
    FROM transaksi_detail td 
    JOIN transaksi t ON td.no_faktur = t.no_faktur 
    JOIN barang b ON td.barang_id = b.id 
    WHERE t.id_usaha='$id_usaha' 
    AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' 
    AND t.status='selesai'");
$d_hpp = mysqli_fetch_assoc($q_hpp);
$hpp = $d_hpp['total_hpp'] ?? 0;

$laba_bersih = $omzet - $hpp;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi - <?= htmlspecialchars($toko['nama_perusahaan']) ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .row { display: flex; justify-content: space-between; border-bottom: 1px dotted #ccc; padding: 5px 0; }
        .bold { font-weight: bold; }
        .total { border-top: 2px solid #000; border-bottom: none; margin-top: 10px; padding-top: 10px; font-size: 16px; }
        @media print { .no-print { display: none; } }
        .btn-print { background: #333; color: #fff; padding: 10px; text-decoration: none; border-radius: 5px; }
        .btn-close { background: #dc2626; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-left: 10px;}
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <a href="javascript:window.print()" class="btn-print">Cetak Laporan</a>
        <button onclick="window.close()" class="btn-close">Tutup</button>
    </div>

    <div class="header">
        <h1><?= htmlspecialchars($toko['nama_perusahaan']) ?></h1>
        <p><?= htmlspecialchars($toko['alamat']) ?> | Telp: <?= htmlspecialchars($toko['no_telp']) ?></p>
    </div>

    <h3 style="text-align: center; text-decoration: underline;">LAPORAN LABA RUGI</h3>
    <p style="text-align: center;">Periode: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>

    <div style="max-width: 700px; margin: 0 auto; border: 1px solid #000; padding: 20px;">
        <div class="row bold"><div class="label">PENDAPATAN KOTOR</div></div>
        <div class="row">
            <div style="padding-left: 20px;">Penjualan Barang (Total: <?= $jlh_trx ?> Trx)</div>
            <div>Rp <?= number_format($omzet, 0, ',', '.') ?></div>
        </div>
        
        <br>
        
        <div class="row bold"><div class="label">BEBAN POKOK PENJUALAN</div></div>
        <div class="row">
            <div style="padding-left: 20px;">HPP (Harga Modal Barang Terjual)</div>
            <div style="color: red;">(Rp <?= number_format($hpp, 0, ',', '.') ?>)</div>
        </div>

        <div class="row bold total" style="background-color: #f0f0f0; padding: 10px;">
            <div>LABA BERSIH (Gross Profit)</div>
            <div style="color: #00695c;">Rp <?= number_format($laba_bersih, 0, ',', '.') ?></div>
        </div>
    </div>
</body>
</html>