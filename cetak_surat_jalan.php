<?php
// 1. Pastikan path koneksi benar
if(file_exists('config/koneksi.php')) {
    include 'config/koneksi.php';
} elseif(file_exists('../config/koneksi.php')) {
    include '../config/koneksi.php';
} else {
    if(file_exists('assets/config/koneksi.php')) include 'assets/config/koneksi.php';
    else die("Error: File koneksi.php tidak ditemukan!");
}

// 2. Ambil Parameter Faktur (Support GET 'faktur' atau 'no_faktur')
$faktur = $_GET['faktur'] ?? $_GET['no_faktur'] ?? '';

if(empty($faktur)) die("Faktur tidak ditemukan");

// 3. Ambil data dari transaksi (t) dan relasi pesanan (ps) untuk sinkronisasi driver & nopol
$sql = "
    SELECT t.*, 
           p.nama_pelanggan as nama_master,
           p.alamat as alamat_master,
           ps.nama_driver as driver_pesanan,
           ps.nopol as nopol_pesanan,
           ps.nama_pelanggan as nama_manual_pesanan,
           ps.alamat as alamat_manual_pesanan,
           ps.lokasi_kirim as lokasi_pesanan
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

// Pesanan yang baru tahap Pengiriman belum punya baris transaksi.
// Ambil identitasnya langsung dari tabel pesanan agar surat jalan tetap bisa dicetak.
// Nomor surat jalan bertahap berbentuk "<no_pesanan>/SJn" -> ambil pesanan induknya.
if (!$trx) {
    $no_induk = preg_replace('#/SJ\d+$#', '', $faktur);
    $faktur_safe = mysqli_real_escape_string($conn, $no_induk);
    $trx = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT ps.no_pesanan AS no_faktur, ps.tanggal, ps.total_bayar AS total_transaksi,
               ps.pelanggan_id, ps.nama_driver, ps.nopol, ps.lokasi_kirim,
               p.nama_pelanggan AS nama_master, p.alamat AS alamat_master,
               ps.nama_driver AS driver_pesanan, ps.nopol AS nopol_pesanan,
               ps.nama_pelanggan AS nama_manual_pesanan, ps.alamat AS alamat_manual_pesanan,
               ps.lokasi_kirim AS lokasi_pesanan
        FROM pesanan ps
        LEFT JOIN pelanggan p ON ps.pelanggan_id = p.id
        WHERE ps.no_pesanan = '$faktur_safe'"));

    if (!$trx) { die("Data pesanan tidak ditemukan"); }
}

// --- LOGIKA PRIORITAS DATA (SINKRONISASI) ---
// 1. Driver & Nopol (Ambil dari transaksi dulu, jika kosong ambil dari pesanan)
$nama_driver = !empty($trx['nama_driver']) ? $trx['nama_driver'] : (!empty($trx['driver_pesanan']) ? $trx['driver_pesanan'] : '-');
$nopol       = !empty($trx['nopol']) ? $trx['nopol'] : (!empty($trx['nopol_pesanan']) ? $trx['nopol_pesanan'] : '-');

// 2. Nama Pelanggan
$nama_pelanggan = !empty($trx['nama_master']) ? $trx['nama_master'] : 
                  (!empty($trx['nama_manual_pesanan']) ? $trx['nama_manual_pesanan'] : 
                  (!empty($trx['nama_pelanggan']) ? $trx['nama_pelanggan'] : 'Pelanggan Umum'));

// 3. Alamat
$alamat_pelanggan = !empty($trx['lokasi_kirim']) ? $trx['lokasi_kirim'] :
                    (!empty($trx['lokasi_pesanan']) ? $trx['lokasi_pesanan'] :
                    (!empty($trx['alamat_master']) ? $trx['alamat_master'] : 
                    (!empty($trx['alamat_manual_pesanan']) ? $trx['alamat_manual_pesanan'] : '-')));

// 4. Ambil Data Pengaturan Perusahaan
$id_usaha = $trx['id_usaha'] ?? 1;
$info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha='$id_usaha' LIMIT 1"));

// 5. Ubah Format Nomor: INV/... menjadi SJ/...
// Surat jalan bertahap punya nomor sendiri; selain itu turunkan dari nomor faktur.
// Nomor surat jalan bertahap ("ORD-xxx/SJ1") dipakai apa adanya.
$no_surat = preg_match('#/SJ\d+$#', $faktur)
    ? $faktur
    : str_replace(["INV", "TRX", "ORD"], "SJ", $faktur);

// 6. Fungsi Format Tanggal Indonesia
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - <?= $no_surat ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 20px 40px; }
        .header { margin-bottom: 30px; }
        .company-name { font-weight: bold; font-size: 14px; text-transform: uppercase; margin-bottom: 5px; }
        .company-address { font-size: 11px; max-width: 400px; }
        .info-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .info-table { border-collapse: collapse; }
        .info-table td { padding: 2px 0; vertical-align: top; font-size: 11px; }
        .label-col { width: 90px; }
        .sep-col { width: 15px; text-align: center; }
        .page-title { text-align: center; font-weight: bold; font-size: 16px; margin: 20px 0; text-transform: uppercase; letter-spacing: 2px; text-decoration: underline; }
        .table-items { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #000; }
        .table-items th { border: 1px solid #000; padding: 5px; font-weight: bold; text-align: center; background-color: #fff; }
        .table-items td { border: 1px solid #000; padding: 4px 8px; }
        .text-center { text-align: center; }
        .footer-note { margin-top: 10px; margin-bottom: 20px; font-size: 11px; }
        .signatures { display: flex; justify-content: space-between; margin-bottom: 10px; padding-right: 20px; }
        .sign-box { text-align: center; width: 150px; }
        .sign-role { margin-bottom: 60px; }
        .sign-name { font-weight: bold; text-decoration: underline; }
        .notes { font-size: 10px; line-height: 1.4; margin-top: 20px; }
        .notes strong { text-decoration: underline; }

        @media print {
            @page { margin: 1cm; }
            .no-print { display: none !important; }
        }

        /* Navigasi Layar */
        .no-print { position: fixed; top: 20px; right: 20px; background: rgba(255,255,255,0.9); padding: 10px; border-radius: 10px; border: 1px solid #ddd; z-index: 9999; }
        .btn-nav { padding: 8px 15px; cursor: pointer; border-radius: 6px; font-weight: bold; text-decoration: none; display: inline-block; margin: 0 5px; font-size: 12px; border: none; }
        .btn-back { background: #64748b; color: white; }
        .btn-print { background: #4f46e5; color: white; }
        .btn-pdf { background: #dc2626; color: white; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.location.href='index.php?page=list_surat_jalan'" class="btn-nav btn-back">Kembali</button>
        <button onclick="downloadPDF()" class="btn-nav btn-pdf">Download PDF</button>
        <button onclick="window.print()" class="btn-nav btn-print">Cetak Surat</button>
    </div>

    <div id="nota-layar" style="background: white; width: 100%; max-width: 800px; margin: auto;">
        <div class="header">
            <div class="company-name"><?= $info['nama_perusahaan'] ?></div>
            <div class="company-address"><?= $info['alamat'] ?></div>
        </div>

        <div class="info-container">
            <div class="info-left">
                <table class="info-table">
                    <tr>
                        <td class="label-col">Tanggal</td>
                        <td class="sep-col">:</td>
                        <td><?= tgl_indo(date('Y-m-d', strtotime($trx['tanggal']))) ?></td>
                    </tr>
                    <tr>
                        <td>No Surat</td>
                        <td>:</td>
                        <td><?= $no_surat ?></td>
                    </tr>
                    <tr>
                        <td>No Kendaraan</td>
                        <td>:</td>
                        <td><?= strtoupper($nopol) ?></td>
                    </tr>
                    <tr>
                        <td>Driver</td>
                        <td>:</td>
                        <td><?= strtoupper($nama_driver) ?></td>
                    </tr>
                </table>
            </div>

            <div class="info-right">
                <table class="info-table">
                    <tr>
                        <td class="label-col">Kepada</td>
                        <td class="sep-col">:</td>
                        <td><?= strtoupper($nama_pelanggan) ?></td>
                    </tr>
                    <tr>
                        <td>Alamat</td>
                        <td>:</td>
                        <td><?= strtoupper($alamat_pelanggan) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="page-title">SURAT JALAN</div>

        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 5%">No</th>
                    <th>Nama Barang</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 10%">Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $q_detail = mysqli_query($conn, "
                    SELECT td.qty, b.nama_barang, b.satuan
                    FROM transaksi_detail td
                    JOIN barang b ON td.barang_id = b.id
                    WHERE td.no_faktur='$faktur'
                ");

                while($d = mysqli_fetch_assoc($q_detail)):
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $d['nama_barang'] ?></td>
                    <td class="text-center"><?= (float)$d['qty'] ?></td>
                    <td class="text-center"><?= $d['satuan'] ?></td>
                </tr>
                <?php endwhile; ?>
                
                <?php for($i=0; $i<(8-$no); $i++): ?>
                <tr><td style="height: 20px;"></td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="footer-note">Barang Yang Diterima Dalam Keadaan Baik Dan Cukup Oleh :</div>

        <div class="signatures">
            <div class="sign-box">
                <div class="sign-role">Penerima</div>
                <div class="sign-name">( <?= strtoupper($nama_pelanggan) ?> )</div>
            </div>
            <div class="sign-box">
                <div class="sign-role">Pengirim</div>
                <div class="sign-name">( <?= strtoupper($nama_driver) ?> )</div>
            </div>
            <div class="sign-box">
                <div class="sign-role">Hormat Kami</div>
                <div class="sign-name">( ADMIN GUDANG )</div>
            </div>
        </div>

        <div class="notes">
            <strong>PERHATIAN:</strong><br>
            1. Surat Jalan Ini Sebagai Bukti Penerimaan Barang<br>
            2. Surat Jalan Ini Bukan Bukti Pembayaran<br>
            3. Surat Jalan Ini Akan Dilengkapi Dengan Invoice Sebagai Bukti Penjualan
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('nota-layar');
            const options = {
                margin:       [0.3, 0.3, 0.3, 0.3], // Mengurangi margin agar pas di ponsel
                filename:     'Surat_Jalan_<?= $no_surat ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    logging: false,
                    letterRendering: true 
                },
                jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' } // Menggunakan A4 agar universal
            };

            html2pdf().set(options).from(element).save();
        }
    </script>

</body>
</html>