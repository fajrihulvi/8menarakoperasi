<?php
// cetak_retur_pembelian.php

// --- PERBAIKAN ERROR 500 (PATH DETECTION) ---
// Cek file koneksi ada di mana (root atau folder config)
if (file_exists('config/koneksi.php')) {
    require 'config/koneksi.php';
} elseif (file_exists('../config/koneksi.php')) {
    require '../config/koneksi.php';
} else {
    // Jika masih gagal, coba cari manual atau tampilkan error jelas
    die("<h3>Error Sistem: File koneksi database tidak ditemukan.</h3><p>Pastikan file config/koneksi.php ada.</p>");
}
// ---------------------------------------------

$id = $_GET['id'] ?? 0;
$q = mysqli_query($conn, "SELECT * FROM retur_pembelian WHERE id='$id'");
$h = mysqli_fetch_assoc($q);

if(!$h) die("Data Retur tidak ditemukan di database.");

$id_usaha = $h['id_usaha'];
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha='$id_usaha'"));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nota Retur - <?= $h['no_retur'] ?></title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 13px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 22px; text-transform: uppercase; letter-spacing: 2px; }
        .header p { margin: 5px 0; font-size: 14px; }
        
        .info-container { width: 100%; margin-bottom: 20px; display: table; }
        .info-left { display: table-cell; width: 50%; }
        .info-right { display: table-cell; width: 50%; text-align: right; }
        
        .table-box { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table-box th, .table-box td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .table-box th { background-color: #f5f5f5; font-weight: bold; text-transform: uppercase; font-size: 11px; }
        
        .footer { width: 100%; margin-top: 50px; }
        .ttd-box { width: 30%; float: left; text-align: center; }
        .ttd-box-right { width: 30%; float: right; text-align: center; }
        .line { margin-top: 60px; border-bottom: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto; }
        
        @media print {
            @page { margin: 10mm; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h1>NOTA RETUR PEMBELIAN</h1>
        <p><strong><?= $profil['nama_usaha'] ?? 'NAMA TOKO' ?></strong></p>
        <p><?= $profil['alamat'] ?? '' ?></p>
    </div>

    <div class="info-container">
        <div class="info-left">
            <table>
                <tr><td><strong>No. Retur</strong></td><td>: <?= $h['no_retur'] ?></td></tr>
                <tr><td><strong>Tanggal</strong></td><td>: <?= date('d F Y', strtotime($h['tanggal'])) ?></td></tr>
            </table>
        </div>
        <div class="info-right">
            <table>
                <tr><td align="right"><strong>Kepada Supplier</strong></td></tr>
                <tr><td align="right" style="font-size:16px; font-weight:bold;"><?= $h['nama_supplier'] ?></td></tr>
            </table>
        </div>
    </div>

    <div style="margin-bottom: 10px; font-style: italic; background: #f9f9f9; padding: 10px; border: 1px dashed #ccc;">
        <strong>Alasan Retur:</strong> "<?= $h['alasan'] ?>"
    </div>

    <table class="table-box">
        <thead>
            <tr>
                <th width="5%" style="text-align:center">No</th>
                <th>Nama Barang</th>
                <th width="15%" style="text-align:center">Qty Retur</th>
                <th width="15%" style="text-align:center">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $det = mysqli_query($conn, "SELECT d.*, b.nama_barang, b.satuan FROM retur_pembelian_detail d JOIN barang b ON d.barang_id=b.id WHERE d.retur_id='$id'");
            while($d = mysqli_fetch_assoc($det)):
            ?>
            <tr>
                <td style="text-align:center"><?= $no++ ?></td>
                <td><?= $d['nama_barang'] ?></td>
                <td style="text-align:center; font-weight:bold;"><?= (float)$d['qty'] ?></td>
                <td style="text-align:center"><?= $d['satuan'] ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="ttd-box">
            <p>Dibuat Oleh,</p>
            <div class="line"></div>
            <p>( Admin Gudang )</p>
        </div>
        <div class="ttd-box-right">
            <p>Diterima Oleh,</p>
            <div class="line"></div>
            <p>( <?= $h['nama_supplier'] ?> )</p>
        </div>
    </div>

</body>
</html>