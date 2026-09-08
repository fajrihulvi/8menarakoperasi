<?php
// ==========================================
// FILE: excel_laba_rugi.php (METODE HTML TABLE - ANTI GAGAL)
// ==========================================

// 1. BERSIHKAN BUFFER SEBERSIH-BERSIHNYA
error_reporting(0);
ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean(); // Hapus semua buffer level
ob_start(); 

require 'config/koneksi.php';

// 2. FUNGSI FORMAT
function format_uang($angka) {
    // Format angka standar komputer (tanpa Rp, tanpa titik) untuk Excel
    return (float)$angka; 
}

// 3. AMBIL DATA
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filename  = "Laporan_Laba_Rugi_" . date('Ymd', strtotime($tgl_awal)) . ".xls";

// Query Data (Sama seperti sebelumnya)
$q_omzet = mysqli_query($conn, "SELECT SUM(total_transaksi) as total FROM transaksi WHERE tanggal BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59' AND status='selesai'");
$omzet = mysqli_fetch_assoc($q_omzet)['total'] ?? 0;

$q_hpp = mysqli_query($conn, "SELECT SUM(b.harga_beli * td.qty) as total_hpp FROM transaksi_detail td JOIN transaksi t ON td.no_faktur = t.no_faktur JOIN barang b ON td.barang_id = b.id WHERE t.tanggal BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59' AND t.status='selesai'");
$hpp = mysqli_fetch_assoc($q_hpp)['total_hpp'] ?? 0;

$list_masuk = [];
$q_masuk = mysqli_query($conn, "SELECT * FROM arus_kas WHERE jenis='pemasukan' AND tanggal BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59'");
while($r = mysqli_fetch_assoc($q_masuk)) { $list_masuk[] = $r; }

$list_keluar = [];
$q_keluar = mysqli_query($conn, "SELECT * FROM arus_kas WHERE jenis='pengeluaran' AND tanggal BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59'");
while($r = mysqli_fetch_assoc($q_keluar)) { $list_keluar[] = $r; }

// Bersihkan Buffer Terakhir Kali
if (ob_get_length()) ob_end_clean();

// 4. HEADER DOWNLOAD EXCEL
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 5. ISI FILE (HTML TABLE BIASA)
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background-color: #4a69bd; color: white; font-weight: bold; text-align: center; }
        .header-orange { background-color: #e58e26; color: white; font-weight: bold; text-align: center; }
        .header-red { background-color: #e55039; color: white; font-weight: bold; text-align: center; }
        .subtotal { background-color: #dfe4ea; font-weight: bold; }
        .grandtotal { background-color: #27ae60; color: white; font-weight: bold; font-size: 14px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; padding: 5px; }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="2" style="font-size: 16px; font-weight: bold; text-align: center; border: none;">LAPORAN LABA RUGI DETAIL</td>
    </tr>
    <tr>
        <td colspan="2" style="text-align: center; border: none;">Periode: <?= $tgl_awal ?> s/d <?= $tgl_akhir ?></td>
    </tr>
    <tr><td colspan="2" style="border: none;"></td></tr>

    <tr class="header">
        <td colspan="2">A. PENDAPATAN USAHA</td>
    </tr>
    <tr>
        <td>1. Penjualan Toko (Omzet)</td>
        <td class="text-right" x:num><?= format_uang($omzet) ?></td>
    </tr>

    <?php 
    $total_A = $omzet;
    $start_row_A = 6; // Baris Excel dimulai (Estimasi)
    if(!empty($list_masuk)):
        foreach($list_masuk as $m): 
            $total_A += $m['jumlah'];
    ?>
    <tr>
        <td> &nbsp; • <?= htmlspecialchars($m['keterangan']) ?> (<?= date('d/m', strtotime($m['tanggal'])) ?>)</td>
        <td class="text-right" x:num><?= format_uang($m['jumlah']) ?></td>
    </tr>
    <?php endforeach; endif; ?>

    <tr class="subtotal">
        <td>TOTAL PENDAPATAN (A)</td>
        <td class="text-right" x:num><?= format_uang($total_A) ?></td>
    </tr>

    <tr><td colspan="2" style="border: none;"></td></tr>

    <tr class="header-orange">
        <td colspan="2">B. HARGA POKOK PENJUALAN</td>
    </tr>
    <tr>
        <td>1. Modal Barang Terjual</td>
        <td class="text-right" x:num><?= format_uang($hpp) ?></td>
    </tr>
    <tr class="subtotal">
        <td>TOTAL HPP (B)</td>
        <td class="text-right" x:num><?= format_uang($hpp) ?></td>
    </tr>

    <tr><td colspan="2" style="border: none;"></td></tr>

    <tr class="header-red">
        <td colspan="2">C. BIAYA OPERASIONAL</td>
    </tr>
    
    <?php 
    $total_C = 0;
    if(!empty($list_keluar)):
        foreach($list_keluar as $k): 
            $total_C += $k['jumlah'];
    ?>
    <tr>
        <td> &nbsp; • <?= htmlspecialchars($k['keterangan']) ?> (<?= date('d/m', strtotime($k['tanggal'])) ?>)</td>
        <td class="text-right" x:num><?= format_uang($k['jumlah']) ?></td>
    </tr>
    <?php endforeach; else: ?>
    <tr>
        <td> - Tidak ada pengeluaran - </td>
        <td class="text-right" x:num>0</td>
    </tr>
    <?php endif; ?>

    <tr class="subtotal">
        <td>TOTAL BIAYA (C)</td>
        <td class="text-right" x:num><?= format_uang($total_C) ?></td>
    </tr>

    <tr><td colspan="2" style="border: none;"></td></tr>

    <?php $laba_bersih = $total_A - $hpp - $total_C; ?>
    <tr class="grandtotal">
        <td>LABA BERSIH (A - B - C)</td>
        <td class="text-right" x:num><?= format_uang($laba_bersih) ?></td>
    </tr>

</table>
</body>
</html>
<?php exit; ?>