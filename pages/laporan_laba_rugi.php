<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!function_exists('format_rupiah')) {
    function format_rupiah($angka){
        return "Rp " . number_format($angka,0,',','.');
    }
}

// CEK AKSES
wajib_akses('laporan_laba_rugi');

$id_usaha = $_SESSION['id_usaha'] ?? 1;
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// --- PENTING: FILTER DATA VALID ---
$tgl_valid_start = '2026-02-09 00:00:00'; 

// AMBIL NAMA TOKO UNTUK KOP SURAT
$q_info = mysqli_query($conn, "SELECT nama_perusahaan FROM pengaturan WHERE id_usaha='$id_usaha' LIMIT 1");
$info = mysqli_fetch_assoc($q_info);
$nama_perusahaan = $info ? $info['nama_perusahaan'] : 'Koperasi Delapan Menara Persada';
?>

<div id="area-print" class="bg-white rounded-xl shadow-sm p-8 mb-6 max-w-5xl mx-auto">
    
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8 border-b pb-6 no-print">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Laporan Laba Rugi Detail</h2>
            <p class="text-gray-500 text-sm">Periode: <span class="font-bold text-indigo-600"><?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></span></p>
        </div>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2 items-center bg-gray-100 p-2 rounded-lg">
                <input type="hidden" name="page" value="laporan_laba_rugi">
                <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="border rounded px-2 py-1 text-sm">
                <span class="text-gray-400">-</span>
                <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="border rounded px-2 py-1 text-sm">
                <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm font-bold hover:bg-indigo-700">Filter</button>
            </form>
            <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-gray-900"><i class="fa-solid fa-print mr-2"></i> Cetak</button>
            <a href="excel_laba_rugi.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>" target="_blank" class="bg-green-600 text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-green-700"><i class="fa-solid fa-file-excel mr-2"></i> Excel</a>
        </div>
    </div>

    <div class="hidden print:block text-center mb-8 border-b-2 border-black pb-4">
        <h1 class="text-3xl font-bold uppercase tracking-wider mb-1"><?= htmlspecialchars($nama_perusahaan) ?></h1>
        <p class="text-sm text-gray-600 mb-4">Laporan Laba Rugi (Income Statement)</p>
        <p class="font-bold">Periode: <?= date('d F Y', strtotime($tgl_awal)) ?> - <?= date('d F Y', strtotime($tgl_akhir)) ?></p>
    </div>

    <?php
    // ==================================================================================
    // 1. PENDAPATAN (PENJUALAN) - SYARAT: STATUS SELESAI, TANGGAL VALID & LUNAS
    // ==================================================================================
    $q_jual = mysqli_query($conn, "
        SELECT SUM(td.subtotal) as total 
        FROM transaksi t
        JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
        WHERE t.id_usaha='$id_usaha' 
        AND t.jenis_transaksi='keluar' 
        AND t.status='selesai' 
        AND t.status_bayar='lunas'
        AND t.tanggal >= '$tgl_valid_start' 
        AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ");
    $d_jual = mysqli_fetch_assoc($q_jual);
    $pendapatan_jual = $d_jual['total'] ?? 0;

    $q_rev_lain = mysqli_query($conn, "
        SELECT SUM(j.kredit - j.debit) as total 
        FROM jurnal_umum j 
        JOIN akun_perkiraan a ON j.akun_id = a.id 
        WHERE j.id_usaha='$id_usaha' 
        AND a.kategori='Pendapatan' 
        AND j.tanggal >= '$tgl_valid_start' 
        AND DATE(j.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ");
    $d_rev_lain = mysqli_fetch_assoc($q_rev_lain);
    $pendapatan_lain = $d_rev_lain['total'] ?? 0;

    $total_pendapatan = $pendapatan_jual + $pendapatan_lain;

    // ==================================================================================
    // 2. BEBAN POKOK PENJUALAN (HPP) - SYARAT: STATUS SELESAI & LUNAS
    // ==================================================================================
    $q_hpp = mysqli_query($conn, "
        SELECT SUM(td.hpp * td.qty) as total_hpp 
        FROM transaksi t 
        JOIN transaksi_detail td ON t.no_faktur = td.no_faktur 
        WHERE t.id_usaha = '$id_usaha' 
        AND t.jenis_transaksi = 'keluar' 
        AND t.status = 'selesai'
        AND t.status_bayar = 'lunas'
        AND t.tanggal >= '$tgl_valid_start'
        AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ");
    
    $d_hpp = mysqli_fetch_assoc($q_hpp);
    $total_hpp = $d_hpp['total_hpp'] ?? 0;

    $laba_kotor = $total_pendapatan - $total_hpp;

    // ==================================================================================
    // 3. BEBAN OPERASIONAL
    // ==================================================================================
    $q_beban_ops = mysqli_query($conn, "
        SELECT SUM(jumlah) as total 
        FROM pengeluaran 
        WHERE id_usaha='$id_usaha' 
        AND tanggal >= '$tgl_valid_start'
        AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ");
    $d_beban_ops = mysqli_fetch_assoc($q_beban_ops);
    $beban_ops_langsung = $d_beban_ops['total'] ?? 0;

    $q_beban_jurnal = mysqli_query($conn, "
        SELECT SUM(j.debit - j.kredit) as total 
        FROM jurnal_umum j 
        JOIN akun_perkiraan a ON j.akun_id = a.id 
        WHERE j.id_usaha='$id_usaha' 
        AND a.kategori='Beban' 
        AND j.tanggal >= '$tgl_valid_start'
        AND DATE(j.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ");
    $d_beban_jurnal = mysqli_fetch_assoc($q_beban_jurnal);
    $beban_jurnal = $d_beban_jurnal['total'] ?? 0;

    $total_biaya_ops = $beban_ops_langsung + $beban_jurnal;

    $laba_bersih = $laba_kotor - $total_biaya_ops;
    ?>

    <div class="overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100"><th colspan="2" class="py-2 px-4 text-left font-bold text-gray-700 uppercase">1. Pendapatan (Revenue)</th></tr>
            </thead>
            <tbody class="text-gray-600">
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-2 px-6">Penjualan Bersih (Sales - Selesai & Lunas)</td>
                    <td class="py-2 px-6 text-right font-medium"><?= format_rupiah($pendapatan_jual) ?></td>
                </tr>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-2 px-6">Pendapatan Lain-lain</td>
                    <td class="py-2 px-6 text-right font-medium"><?= format_rupiah($pendapatan_lain) ?></td>
                </tr>
                <tr class="bg-indigo-50 font-bold text-indigo-900">
                    <td class="py-2 px-6 text-right">Total Pendapatan</td>
                    <td class="py-2 px-6 text-right"><?= format_rupiah($total_pendapatan) ?></td>
                </tr>
            </tbody>

            <tbody><tr><td colspan="2" class="py-2"></td></tr></tbody>

            <thead>
                <tr class="bg-gray-100"><th colspan="2" class="py-2 px-4 text-left font-bold text-gray-700 uppercase">2. Harga Pokok Penjualan (HPP)</th></tr>
            </thead>
            <tbody class="text-gray-600">
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-2 px-6">Total HPP (Modal Barang Terjual)</td>
                    <td class="py-2 px-6 text-right font-medium text-red-500">( <?= format_rupiah($total_hpp) ?> )</td>
                </tr>
                <tr class="bg-gray-200 font-bold text-gray-800">
                    <td class="py-2 px-6 text-right uppercase">Laba Kotor (Gross Profit)</td>
                    <td class="py-2 px-6 text-right"><?= format_rupiah($laba_kotor) ?></td>
                </tr>
            </tbody>

            <tbody><tr><td colspan="2" class="py-2"></td></tr></tbody>

            <thead>
                <tr class="bg-gray-100"><th colspan="2" class="py-2 px-4 text-left font-bold text-gray-700 uppercase">3. Biaya Operasional</th></tr>
            </thead>
            <tbody class="text-gray-600">
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-2 px-6">Biaya Operasional (Kas Keluar)</td>
                    <td class="py-2 px-6 text-right font-medium"><?= format_rupiah($beban_ops_langsung) ?></td>
                </tr>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-2 px-6">Biaya Lainnya (Jurnal)</td>
                    <td class="py-2 px-6 text-right font-medium"><?= format_rupiah($beban_jurnal) ?></td>
                </tr>
                <tr class="bg-red-50 font-bold text-red-900">
                    <td class="py-2 px-6 text-right">Total Biaya</td>
                    <td class="py-2 px-6 text-right text-red-600">( <?= format_rupiah($total_biaya_ops) ?> )</td>
                </tr>
            </tbody>

            <tbody><tr><td colspan="2" class="py-4"></td></tr></tbody>

            <tfoot>
                <tr class="bg-green-100 border-t-4 border-green-500 print:bg-gray-300 print:border-black">
                    <td class="py-4 px-6 text-xl font-bold text-green-800 print:text-black uppercase">Laba Bersih</td>
                    <td class="py-4 px-6 text-2xl font-extrabold text-right text-green-700 print:text-black">
                        <?= format_rupiah($laba_bersih) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="only-print hidden mt-16 flex justify-end px-8">
        <div class="text-center w-64">
            <p class="mb-2">Diketahui Oleh,</p>
            <br><br><br><br>
            <p class="font-bold border-b border-black pb-1"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs mt-1">Pimpinan / Manager Keuangan</p>
        </div>
    </div>
</div>

<style>
@media print {
    nav, aside, .no-print { display: none !important; }
    body { background: white; }
    #area-print { box-shadow: none; margin: 0; max-width: 100%; }
    .only-print { display: flex !important; }
}
</style>