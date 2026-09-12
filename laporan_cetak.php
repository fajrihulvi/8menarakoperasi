<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'config/koneksi.php';

// Ambil ID Usaha (Cabang/Koperasi) yang sedang login
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// Ambil Data Perusahaan untuk KOP SESUAI CABANG
$q_info = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha='$id_usaha' LIMIT 1");
$info = mysqli_fetch_assoc($q_info);

// Fallback jika data pengaturan cabang kosong
if(!$info) {
    $info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan LIMIT 1"));
}

// Filter Tanggal & Pelanggan
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$jenis = $_GET['jenis'] ?? 'penjualan';
$pelanggan_id = trim((string)($_GET['pelanggan_id'] ?? ''));
$mode_cetak = isset($_GET['cetak']); // Tampilan preview (tabel) dulu, cetak PDF hanya saat tombol diklik

// Judul Laporan
$judul = "LAPORAN PENJUALAN PER PERIODE";
if($jenis == 'pembelian') $judul = "LAPORAN PEMBELIAN PER PERIODE";

// Daftar pelanggan untuk filter dropdown
$daftar_pelanggan = mysqli_query($conn, "SELECT id, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan ASC");

function tgl_indo($t){
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $p = explode('-', $t);
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan - <?= htmlspecialchars($info['nama_perusahaan']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Times New Roman', Times, serif; padding: 20px; }
        
        /* KOP SURAT */
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 3px double #000; 
            padding-bottom: 10px; 
            position: relative;
        }
        .logo {
            position: absolute;
            left: 0;
            top: 0;
            height: 70px;
        }
        .nama-pt { font-size: 24px; font-weight: bold; text-transform: uppercase; }
        .alamat { font-size: 14px; }
        
        /* JUDUL LAPORAN */
        .judul-laporan { text-align: center; font-weight: bold; margin: 20px 0; font-size: 16px; text-decoration: underline; }
        .periode { text-align: center; font-size: 14px; margin-top: -15px; margin-bottom: 20px; }

        /* TABEL DATA */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #000; padding: 6px 8px; }
        th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* FOOTER TTD */
        .footer { margin-top: 40px; display: flex; justify-content: space-between; padding: 0 50px; }
        .ttd-box { text-align: center; width: 200px; }
        .ttd-space { height: 70px; }
        .ttd-name { font-weight: bold; text-decoration: underline; }

        @media print { .no-print { display: none; } }

        /* FORM FILTER (hanya tampil di mode preview, tidak ikut tercetak) */
        .filter-bar {
            display: flex; flex-wrap: wrap; align-items: end; gap: 10px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px 16px; margin-bottom: 20px; font-family: Arial, sans-serif;
        }
        .filter-bar label { display: block; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4px; text-transform: uppercase; }
        .filter-bar input, .filter-bar select {
            padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;
        }
        .btn-filter, .btn-cetak {
            padding: 9px 18px; border: none; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer;
        }
        .btn-filter { background: #4f46e5; color: #fff; }
        .btn-cetak { background: #16a34a; color: #fff; display: flex; align-items: center; gap: 6px; }
    </style>
</head>
<body<?= $mode_cetak ? ' onload="window.print()"' : '' ?>>

    <?php if(!$mode_cetak): ?>
    <form method="GET" action="laporan_cetak.php" target="_self" class="filter-bar no-print">
        <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label>Tanggal Mulai</label>
            <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div>
            <label>Tanggal Akhir</label>
            <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div>
            <label>Pelanggan</label>
            <select name="pelanggan_id">
                <option value="">-- Semua Pelanggan --</option>
                <?php while($p = mysqli_fetch_assoc($daftar_pelanggan)): ?>
                    <option value="<?= $p['id'] ?>" <?= ($pelanggan_id === (string)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nama_pelanggan'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Terapkan Filter</button>
        <button type="submit" name="cetak" value="1" class="btn-cetak" formtarget="_blank">Cetak PDF</button>
    </form>
    <?php endif; ?>

    <div class="header">
        <?php if(!empty($info['logo']) && file_exists('assets/img/'.$info['logo'])): ?>
            <img src="assets/img/<?= $info['logo'] ?>" class="logo">
        <?php endif; ?>
        
        <div class="nama-pt"><?= htmlspecialchars($info['nama_perusahaan']) ?></div>
        <div class="alamat"><?= htmlspecialchars($info['alamat']) ?></div>
        <div class="alamat">Telp: <?= htmlspecialchars($info['no_telp']) ?></div>
    </div>

    <div class="judul-laporan"><?= $judul ?></div>
    <div class="periode">
        Tanggal: <?= tgl_indo($tgl_awal) ?> s/d <?= tgl_indo($tgl_akhir) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Tanggal</th>
                <th width="20%">No Faktur</th>
                <th>Pelanggan</th>
                <th width="20%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $grand_total = 0;
            
            // Query Data (DITAMBAH FILTER id_usaha & pelanggan)
            $tgl_awal_sql  = mysqli_real_escape_string($conn, $tgl_awal);
            $tgl_akhir_sql = mysqli_real_escape_string($conn, $tgl_akhir);
            $filter_pelanggan_sql = '';
            if ($pelanggan_id !== '') {
                $filter_pelanggan_sql = " AND t.pelanggan_id = '" . mysqli_real_escape_string($conn, $pelanggan_id) . "'";
            }

            $q = mysqli_query($conn, "
                SELECT t.*, p.nama_pelanggan
                FROM transaksi t
                LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                WHERE t.jenis_transaksi='keluar'
                AND t.status='selesai'
                AND t.id_usaha='$id_usaha'
                AND DATE(t.tanggal) BETWEEN '$tgl_awal_sql' AND '$tgl_akhir_sql'
                $filter_pelanggan_sql
                ORDER BY t.tanggal DESC
            ");
            
            while($r = mysqli_fetch_assoc($q)):
                $grand_total += $r['total_transaksi'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center"><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
                <td class="text-center font-bold"><?= $r['no_faktur'] ?></td>
                <td><?= $r['nama_pelanggan'] ?? 'Umum (Walk-in)' ?></td>
                <td class="text-right">Rp <?= number_format($r['total_transaksi'], 0, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
            
            <?php if(mysqli_num_rows($q) == 0): ?>
            <tr><td colspan="5" class="text-center" style="padding:20px;">Tidak ada data transaksi pada periode tanggal ini.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right" style="font-weight:bold; background-color: #f0f0f0;">GRAND TOTAL PENDAPATAN</td>
                <td class="text-right" style="font-weight:bold; background-color: #e0f2f1; color: #00695c;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div class="ttd-box">
            <div>Mengetahui,</div>
            <div>Pimpinan</div>
            <div class="ttd-space"></div>
            <div class="ttd-name">( ........................... )</div>
        </div>
        
        <div class="ttd-box">
            <div>Pangkalpinang, <?= date('d F Y') ?></div>
            <div>Dibuat Oleh,</div>
            <div class="ttd-space"></div>
            <div class="ttd-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <?php if($mode_cetak): ?>
            <button onclick="window.close()" style="padding: 10px 25px; cursor: pointer; background: #dc2626; color: white; border: none; border-radius: 5px; font-weight: bold;">Tutup Jendela Ini</button>
        <?php else: ?>
            <button onclick="window.print()" style="padding: 10px 25px; cursor: pointer; background: #16a34a; color: white; border: none; border-radius: 5px; font-weight: bold;">
                <i class="fa-solid fa-print"></i> Cetak PDF
            </button>
        <?php endif; ?>
    </div>

</body>
</html>