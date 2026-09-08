<?php
// =========================================================
// FILE: assets/cetak_po.php (VERSI PERBAIKAN ROBUST)
// =========================================================

// 1. AKTIFKAN ERROR REPORTING (Agar ketahuan jika ada error, bukan cuma layar putih 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CEK DAN PANGGIL KONEKSI DENGAN PINTAR
// Kita cek dua kemungkinan lokasi file koneksi
$path_internal = 'config/koneksi.php';       // Jika folder config ada di dalam assets
$path_external = '../config/koneksi.php';    // Jika folder config ada di luar assets (root)

if (file_exists($path_internal)) {
    require $path_internal;
} elseif (file_exists($path_external)) {
    require $path_external;
} else {
    // Jika file tidak ketemu, stop dan beri pesan jelas
    die("<h3>Error Fatal: File koneksi.php tidak ditemukan!</h3><p>Sistem mencari di: <b>$path_internal</b> dan <b>$path_external</b> tapi tidak ada.</p>");
}

// 3. VALIDASI INPUT
if (!isset($_GET['no_faktur'])) {
    die("<h3>Error: Nomor Faktur tidak ditemukan di URL.</h3>");
}

$faktur = mysqli_real_escape_string($conn, $_GET['no_faktur']);

// 4. AMBIL DATA HEADER PO
// Menggunakan s.no_telp (sesuai database Anda)
$query_po = mysqli_query($conn, "
    SELECT t.*, s.nama_supplier, s.alamat, s.no_telp 
    FROM transaksi t 
    JOIN supplier s ON t.supplier_id = s.id 
    WHERE t.no_faktur = '$faktur'
");

if (!$query_po) {
    die("Query Error: " . mysqli_error($conn));
}

if (mysqli_num_rows($query_po) == 0) {
    die("<h3>Data PO dengan nomor <b>$faktur</b> tidak ditemukan di database.</h3>");
}

$po = mysqli_fetch_assoc($query_po);

// Cek apakah kolom status_bayar ada (menghindari error jika kolom belum dibuat)
$status_bayar = isset($po['status_bayar']) ? strtoupper($po['status_bayar']) : '-';

// 5. AMBIL DETAIL BARANG
$query_detail = mysqli_query($conn, "
    SELECT td.*, b.nama_barang, b.kode_barang, b.satuan 
    FROM transaksi_detail td 
    JOIN barang b ON td.barang_id = b.id 
    WHERE td.no_faktur = '$faktur'
");

if (!$query_detail) {
    die("Query Detail Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PO - <?= $faktur ?></title>
    <style>
        /* RESET & BASIC */
        body { font-family: Arial, sans-serif; font-size: 13px; margin: 0; padding: 20px; color: #000; }
        
        /* HEADER */
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; }
        .header p { margin: 5px 0 0; font-size: 14px; font-weight: bold; }

        /* INFO BOXES */
        .info-container { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .box-info { 
            border: 1px solid #000; 
            padding: 10px; 
            width: 45%; 
            min-height: 90px;
        }
        .label { font-weight: bold; font-size: 10px; color: #555; text-transform: uppercase; display: block; margin-bottom: 5px; border-bottom: 1px dashed #ccc; padding-bottom: 3px;}
        
        /* TABLE DATA */
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.data-table th { border: 1px solid #000; padding: 8px; background-color: #eee; font-weight: bold; text-align: center; }
        table.data-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        
        /* UTILS */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        /* SIGNATURE */
        .ttd-area { margin-top: 40px; display: flex; justify-content: space-between; padding: 0 30px; }
        .ttd-box { width: 200px; text-align: center; }
        .ttd-line { border-bottom: 1px solid #000; margin-top: 70px; font-weight: bold; }

        /* PRINT SETTINGS */
        @media print {
            @page { size: A4; margin: 1cm; }
            body { padding: 0; }
            .no-print { display: none; }
            table.data-table th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
        }
        .btn-back { background: #333; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px;">
        <a href="javascript:window.history.back()" class="btn-back">
            &laquo; Kembali
        </a>
    </div>

    <div class="header">
        <h1>Purchase Order (PO)</h1>
        <p>KOPERASI DELAPAN MENARA PERSADA</p> 
    </div>

    <div class="info-container">
        <div class="box-info">
            <span class="label">Kepada Supplier</span>
            <div style="font-size: 14px; font-weight: bold; margin-bottom: 3px;"><?= $po['nama_supplier'] ?></div>
            <div><?= $po['alamat'] ?></div>
            <div>Telp: <?= $po['no_telp'] ?></div>
        </div>

        <div class="box-info">
            <span class="label">Detail Dokumen</span>
            <table style="width: 100%; font-size: 12px;">
                <tr>
                    <td style="width: 80px;">No. PO</td>
                    <td>: <strong><?= $po['no_faktur'] ?></strong></td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>: <?= date('d/m/Y', strtotime($po['tanggal'])) ?></td>
                </tr>
                <tr>
                    <td>Status Bayar</td>
                    <td>: <?= $status_bayar ?></td>
                </tr>
            </table>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Deskripsi Barang</th>
                <th width="15%">Qty</th>
                <th width="20%">Harga Satuan</th>
                <th width="20%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            while($d = mysqli_fetch_assoc($query_detail)): 
                $qty = (float)$d['qty'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <div class="bold"><?= $d['nama_barang'] ?></div>
                    <div style="font-size: 11px; color: #555;">Kode: <?= $d['kode_barang'] ?></div>
                </td>
                <td class="text-center"><?= $qty ?> <?= $d['satuan'] ?></td>
                <td class="text-right">Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right" style="padding-right: 15px;"><strong>TOTAL ORDER</strong></td>
                <td class="text-right" style="background-color: #eee;"><strong>Rp <?= number_format($po['total_transaksi'], 0, ',', '.') ?></strong></td>
            </tr>
        </tfoot>
    </table>

    <div style="font-size: 12px; margin-top: 10px; border: 1px dashed #aaa; padding: 10px; background-color: #fafafa;">
        <strong>Catatan:</strong><br>
        1. Mohon kirimkan barang sesuai spesifikasi dan jumlah di atas.<br>
        2. Sertakan nomor PO ini pada Surat Jalan dan Invoice tagihan Anda.<br>
        3. Hubungi kami segera jika ada perubahan harga atau stok.
    </div>

    <div class="ttd-area">
        <div class="ttd-box">
            <div>Dibuat Oleh,</div>
            <div class="ttd-line">( Admin Gudang )</div>
        </div>
        <div class="ttd-box">
            <div>Disetujui Oleh,</div>
            <div class="ttd-line">( Manajer Operasional )</div>
        </div>
    </div>

</body>
</html>