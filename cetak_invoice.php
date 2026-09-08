<?php
// Pastikan path koneksi benar
if(file_exists('config/koneksi.php')) {
    include 'config/koneksi.php';
} elseif(file_exists('../config/koneksi.php')) {
    include '../config/koneksi.php';
} else {
    if(file_exists('assets/config/koneksi.php')) include 'assets/config/koneksi.php';
    else die("Error: File koneksi.php tidak ditemukan!");
}

$faktur = $_GET['no_faktur'] ?? '';

if(empty($faktur)) {
    die("No Faktur tidak ditemukan");
}

// ==========================================
// QUERY UTAMA
// ==========================================
$sql = "
    SELECT 
        t.*, 
        p.nama_pelanggan as nama_master,
        p.alamat as alamat_master,
        p.no_telp as telp_master,
        ps.nama_driver as driver_pesanan,
        ps.nopol as nopol_pesanan,
        ps.nama_pelanggan as nama_manual_pesanan,
        ps.alamat as alamat_manual_pesanan,
        ps.no_hp as telp_manual_pesanan,
        t.lokasi_kirim,
        t.status
    FROM transaksi t 
    LEFT JOIN pelanggan p ON t.pelanggan_id = p.id 
    LEFT JOIN pesanan ps ON t.no_faktur = ps.no_pesanan
    WHERE t.no_faktur='$faktur'
";

$query_run = mysqli_query($conn, $sql);

if (!$query_run) {
    die("<b>Error Database:</b> " . mysqli_error($conn));
}

$trx = mysqli_fetch_assoc($query_run);

if(!$trx) {
    die("Data transaksi tidak ditemukan. Pastikan No Faktur benar.");
}

// =========================================================================
// PROTEKSI STATUS INVOICE: HANYA BISA DICETAK JIKA STATUS 'SELESAI'
// =========================================================================
if(strtolower(trim($trx['status'])) !== 'selesai') {
    die("<div style='font-family: Arial; padding: 20px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 5px; text-align: center; font-size: 16px; margin: 50px auto; max-width: 600px;'>
        <b>Akses Ditolak!</b><br><br>
        Invoice <b>$faktur</b> belum bisa diterbitkan/dicetak karena status transaksinya masih <b>" . strtoupper($trx['status']) . "</b>.<br><br>
        Silakan selesaikan pesanan terlebih dahulu di sistem agar laporan keuangan sinkron.
    </div>");
}
// =========================================================================

// LOGIKA SINKRONISASI DATA KE TAMPILAN
// 1. Nama Pelanggan
$nama_pelanggan = !empty($trx['nama_master']) ? $trx['nama_master'] : 
                  (!empty($trx['nama_manual_pesanan']) ? $trx['nama_manual_pesanan'] : 
                  ($trx['nama_pelanggan'] ?? 'Pelanggan Umum'));

// 2. Alamat
$alamat_pelanggan = !empty($trx['lokasi_kirim']) ? $trx['lokasi_kirim'] :
                    (!empty($trx['alamat_master']) ? $trx['alamat_master'] : 
                    (!empty($trx['alamat_manual_pesanan']) ? $trx['alamat_manual_pesanan'] : '-'));

// 3. Telepon
$telp_pelanggan = !empty($trx['telp_master']) ? $trx['telp_master'] : 
                  (!empty($trx['telp_manual_pesanan']) ? $trx['telp_manual_pesanan'] : '-');

// --- DETEKSI KATEGORI OTOMATIS DARI SINKRONISASI DATABASE (TIDAK TEBAK NAMA LAGI) ---
$kategori_pelanggan = 'HARGA UMUM';
$pelanggan_id = (int)($trx['pelanggan_id'] ?? 0);

if ($pelanggan_id > 0) {
    $q_kat = mysqli_query($conn, "SELECT jenis_harga FROM users WHERE pelanggan_id='$pelanggan_id' AND jenis_harga != 'harga_jual' LIMIT 1");
    if($kat = mysqli_fetch_assoc($q_kat)) {
        if($kat['jenis_harga'] == 'harga_gabek') $kategori_pelanggan = 'AREA PANGKALPINANG';
        elseif($kat['jenis_harga'] == 'harga_kereta') $kategori_pelanggan = 'AREA BANGKA TENGAH';
        elseif($kat['jenis_harga'] == 'harga_jebus') $kategori_pelanggan = 'AREA BANGKA BARAT';
    }
}
// ---------------------------------------------------------------------------------

// 4. Driver & Nopol
$nama_driver = !empty($trx['nama_driver']) ? $trx['nama_driver'] : ($trx['driver_pesanan'] ?? '');
$nopol       = !empty($trx['nopol']) ? $trx['nopol'] : ($trx['nopol_pesanan'] ?? '');

// Data Perusahaan
$id_usaha = $trx['id_usaha'] ?? 1;
$info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha='$id_usaha' LIMIT 1"));

// Fungsi Tanggal Indo
function tgl_indo($tanggal){
    $bulan = array (1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>INVOICE <?= $faktur ?></title>
    <style>
        /* RESET & BASIC */
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; color: #000; -webkit-print-color-adjust: exact; background: #fff; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        
        /* TITLE */
        .invoice-title { text-align: center; font-size: 28px; font-weight: 900; color: #333399; letter-spacing: 5px; margin-bottom: 30px; text-transform: uppercase; }

        /* HEADER BOXES */
        .header-boxes { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .box-frame { border: 1px solid #000; width: 48%; text-align: center; }
        .box-title { border-bottom: 1px solid #000; padding: 5px; font-weight: bold; font-size: 10px; background-color: #fff; }
        .box-content { padding: 8px; font-weight: bold; font-size: 12px; }

        /* ADDRESS SECTION */
        .address-section { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .address-box { width: 48%; }
        .addr-title { font-weight: bold; margin-bottom: 5px; font-size: 10px; text-decoration: underline; }
        .addr-row { display: flex; margin-bottom: 3px; }
        .addr-label { width: 100px; font-weight: normal; }
        .addr-val { flex: 1; font-weight: bold; }

        /* TABLE STYLE */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000; }
        th { border: 1px solid #000; padding: 5px; text-align: center; background-color: #fff; font-weight: bold; font-size: 10px; }
        td { border: 1px solid #000; padding: 5px; vertical-align: middle; font-size: 11px; }
        .col-no { width: 30px; text-align: center; }
        .col-qty { width: 50px; text-align: center; }
        .col-unit { width: 50px; text-align: center; }
        .col-price { width: 100px; text-align: right; }
        .col-subtotal { width: 100px; text-align: right; }
        .curr-flex { display: flex; justify-content: space-between; }

        /* TOTAL ROW */
        .total-row td { background-color: #FFF2CC !important; font-weight: bold; border-top: 2px solid #000; }

        /* FOOTER */
        .footer-section { display: flex; justify-content: space-between; margin-top: 20px; }
        .bank-info { width: 45%; font-size: 11px; border: 1px solid #ccc; padding: 10px; }
        .bank-title { font-weight: bold; margin-bottom: 5px; text-decoration: underline; }

        /* TANDA TANGAN */
        .signatures { width: 50%; display: flex; justify-content: space-between; text-align: center; }
        .sign-box { width: 48%; display: flex; flex-direction: column; justify-content: space-between; min-height: 120px; position: relative; }
        .sign-title { font-weight: bold; font-size: 11px; margin-top: 0; z-index: 2; }
        .sign-name { font-weight: bold; font-size: 11px; text-decoration: underline; margin-bottom: 0; z-index: 2; }
        
        .stamp-img { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100px; opacity: 0.6; z-index: 1; }

        .no-print { text-align: center; margin-top: 30px; margin-bottom: 50px; border-top: 1px dashed #ccc; padding-top: 20px; }
        .btn { background: #333399; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; cursor: pointer; border: none; font-size: 12px; }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: #555; margin-left: 10px; }
        
        @media print { 
            .no-print { display: none; } 
            body { margin: 0; padding: 0; }
            .container { max-width: 100%; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="container">
        
        <div class="invoice-title">INVOICE</div>

        <div class="header-boxes">
            <div class="box-frame">
                <div class="box-title">NOMOR INVOICE</div>
                <div class="box-content"><?= $faktur ?></div>
            </div>
            <div class="box-frame">
                <div class="box-title">TANGGAL PENAGIHAN</div>
                <div class="box-content"><?= tgl_indo(date('Y-m-d', strtotime($trx['tanggal']))) ?></div>
            </div>
        </div>

        <div class="address-section">
            <div class="address-box">
                <div class="addr-title">DITAGIHKAN KEPADA</div>
                <div class="addr-row" style="margin-bottom: 8px;">
                    <span class="addr-label">Kategori / Area</span>
                    <span class="addr-val">: 
                        <span style="background: #333399; color: white; padding: 3px 6px; border-radius: 3px; font-size: 10px;">
                            <?= $kategori_pelanggan ?>
                        </span>
                    </span>
                </div>
                <div class="addr-row">
                    <span class="addr-label">Nama Lengkap</span>
                    <span class="addr-val">: <?= $nama_pelanggan ?></span>
                </div>
                <div class="addr-row">
                    <span class="addr-label">Alamat Lengkap</span>
                    <span class="addr-val">: <?= $alamat_pelanggan ?></span>
                </div>
                <div class="addr-row">
                    <span class="addr-label">No. Telp/HP</span>
                    <span class="addr-val">: <?= $telp_pelanggan ?></span> 
                </div>
                
                <?php if(!empty($nama_driver) && $nama_driver != '-'): ?>
                <div class="addr-row">
                    <span class="addr-label">Driver / Nopol</span>
                    <span class="addr-val">: <?= $nama_driver ?> (<?= $nopol ?>)</span> 
                </div>
                <?php endif; ?>
            </div>

            <div class="address-box">
                <div class="addr-title">DITAGIHKAN OLEH</div>
                <div class="addr-row">
                    <span class="addr-label">Nama Perusahaan</span>
                    <span class="addr-val">: <?= $info['nama_perusahaan'] ?></span>
                </div>
                <div class="addr-row">
                    <span class="addr-label">Alamat Lengkap</span>
                    <span class="addr-val">: <?= $info['alamat'] ?></span>
                </div>
                <div class="addr-row">
                    <span class="addr-label">No. Telp</span>
                    <span class="addr-val">: <?= $info['no_telp'] ?></span> 
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th style="text-align: left;">ITEM / DESKRIPSI BARANG</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-unit">UNIT</th>
                    <th class="col-price">HARGA SATUAN</th>
                    <th class="col-subtotal">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $grand_total_dinamis = 0; 
                
                // MENGGUNAKAN DATA HARGA DARI DATABASE TRANSAKSI, BUKAN MENGHITUNG ULANG (100% AMAN)
                $query_detail = mysqli_query($conn, "
                    SELECT td.*, b.nama_barang, b.satuan, b.kode_barang 
                    FROM transaksi_detail td 
                    JOIN barang b ON td.barang_id = b.id 
                    WHERE td.no_faktur='$faktur' AND td.qty > 0 
                    ORDER BY b.nama_barang ASC
                ");
                
                while($d = mysqli_fetch_assoc($query_detail)):
                    $qty_show = (float)$d['qty'];
                    $grand_total_dinamis += $d['subtotal'];
                ?>
                <tr>
                    <td class="col-no"><?= $no++ ?></td>
                    <td><?= $d['nama_barang'] ?> <br><small style="color:#666;"><?= $d['kode_barang'] ?? '' ?></small></td>
                    <td class="col-qty"><?= $qty_show ?></td>
                    <td class="col-unit"><?= $d['satuan'] ?: 'pcs' ?></td>
                    <td>
                        <div class="curr-flex"><span>Rp</span><span><?= number_format($d['harga_satuan'], 0, ',', '.') ?></span></div>
                    </td>
                    <td>
                        <div class="curr-flex"><span>Rp</span><span><?= number_format($d['subtotal'], 0, ',', '.') ?></span></div>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <tr class="total-row">
                    <td colspan="4" style="border-right: 1px solid #000; background: #fff !important;"></td> 
                    <td style="text-align: center; border-left: 1px solid #000;">TOTAL</td>
                    <td>
                        <div class="curr-flex"><span>Rp</span><span><?= number_format($grand_total_dinamis, 0, ',', '.') ?></span></div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="footer-section">
            <div class="bank-info">
                <div class="bank-title">Pembayaran ditujukan kepada :</div>
                <div style="font-weight: bold; margin-bottom: 3px;"><?= $info['nama_bank'] ?></div>
                <div style="margin-bottom: 3px;">A/N: <?= $info['atas_nama_rek'] ?></div>
                <div>No. Rek: <strong><?= $info['no_rek'] ?></strong></div>
            </div>

            <div class="signatures">
                <div class="sign-box">
                    <div class="sign-title">Penerima / Pembeli,</div>
                    <div style="margin-top:auto;" class="sign-name"><?= $nama_pelanggan ?></div>
                </div>

                <div class="sign-box">
                    <div class="sign-title">Hormat Kami,</div>
                    <?php if(!empty($info['logo'])): ?>
                        <img src="assets/img/<?= $info['logo'] ?>" class="stamp-img" alt="Stempel">
                    <?php endif; ?>
                    <div class="sign-name"><?= $info['nama_perusahaan'] ?></div>
                </div>
            </div>
        </div>

        <div class="no-print">
            <button onclick="window.history.back()" class="btn">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fa-solid fa-print"></i> Cetak Lagi
            </button>
        </div>

    </div>

</body>
</html>