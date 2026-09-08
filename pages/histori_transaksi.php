<?php
wajib_akses('histori_transaksi');

// Pastikan sesi dan koneksi sudah berjalan dari index.php
require_once __DIR__ . '/../layout/tabel_helper.php';
if (!isset($_SESSION['login'])) {
    exit;
}

// 1. Ambil parameter filter jenis dari URL (default: 'semua')
$jenis_filter = isset($_GET['jenis']) ? strtolower($_GET['jenis']) : 'semua';

// 2. Ambil parameter filter tanggal & nama barang (DEFAULT: KOSONG)
$tgl_awal    = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : '';
$tgl_akhir   = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : '';
$nama_barang = isset($_GET['nama_barang']) ? $_GET['nama_barang'] : '';

// 3. Buat kondisi SQL (WHERE) yang dinamis
$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1;
// PERBAIKAN: Selalu batasi histori pada ID usaha yang login saat ini
$where_clause = "WHERE t.id_usaha = '$id_usaha_aktif'"; 
$judul_laporan = "LAPORAN HISTORI TRANSAKSI BARANG (SEMUA)";

// Kondisi Jenis Transaksi
if ($jenis_filter == 'masuk') {
    $where_clause .= " AND t.jenis_transaksi = 'masuk'";
    $judul_laporan = "LAPORAN HISTORI BARANG MASUK";
} elseif ($jenis_filter == 'keluar') {
    $where_clause .= " AND (t.jenis_transaksi = 'keluar' OR t.jenis_transaksi IS NULL)";
    $judul_laporan = "LAPORAN HISTORI BARANG KELUAR";
}

// Kondisi Tanggal
if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $where_clause .= " AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
    $judul_laporan .= " <br><span style='font-size:14px; font-weight:normal;'>Periode: " . date('d/m/Y', strtotime($tgl_awal)) . " s/d " . date('d/m/Y', strtotime($tgl_akhir)) . "</span>";
} else {
    $judul_laporan .= " <br><span style='font-size:14px; font-weight:normal;'>Periode: Semua Waktu</span>";
}

// Kondisi Nama Barang (Pencarian Kata Kunci)
if (!empty($nama_barang)) {
    $kata_kunci = mysqli_real_escape_string($conn, $nama_barang);
    $where_clause .= " AND b.nama_barang LIKE '%$kata_kunci%'";
    $judul_laporan .= " <br><span style='font-size:14px; font-weight:normal;'>Filter Barang: " . htmlspecialchars($nama_barang) . "</span>";
}

// Variabel bantuan untuk URL agar semua filter terbawa saat ganti tab
$query_filters = "&tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir&nama_barang=" . urlencode($nama_barang);
?>

<div class="container-fluid px-4 py-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">Histori Transaksi Gudang</h2>
        
        <div class="flex gap-2">
            <button onclick="downloadPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow font-bold flex items-center gap-2 transition">
                <i class="fa-solid fa-file-pdf"></i> Download PDF
            </button>
            <button onclick="cetakLaporan()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded shadow font-bold flex items-center gap-2 transition">
                <i class="fa-solid fa-print"></i> Cetak Laporan
            </button>
        </div>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
        <form method="GET" action="index.php" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="page" value="histori_transaksi">
            <input type="hidden" name="jenis" value="<?= $jenis_filter ?>">
            
            <div class="w-full md:w-64">
                <label class="block text-xs font-bold text-gray-500 mb-1">Cari Nama Barang</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="nama_barang" value="<?= htmlspecialchars($nama_barang) ?>" placeholder="Ketik nama barang..." class="w-full border border-gray-300 rounded pl-10 pr-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="border border-gray-300 rounded px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="border border-gray-300 rounded px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </div>
            
            <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0">
                <button type="submit" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow font-bold text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <a href="index.php?page=histori_transaksi&jenis=<?= $jenis_filter ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded shadow font-bold text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-rotate-right"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="flex flex-wrap gap-3 mb-6 border-b border-gray-200 pb-4">
        <a href="index.php?page=histori_transaksi&jenis=semua<?= $query_filters ?>" 
           class="<?= $jenis_filter == 'semua' ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?> px-5 py-2 rounded-lg font-bold shadow-sm transition flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Semua Transaksi
        </a>
        <a href="index.php?page=histori_transaksi&jenis=masuk<?= $query_filters ?>" 
           class="<?= $jenis_filter == 'masuk' ? 'bg-green-600 text-white ring-2 ring-green-300' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?> px-5 py-2 rounded-lg font-bold shadow-sm transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-down"></i> Barang Masuk
        </a>
        <a href="index.php?page=histori_transaksi&jenis=keluar<?= $query_filters ?>" 
           class="<?= $jenis_filter == 'keluar' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' ?> px-5 py-2 rounded-lg font-bold shadow-sm transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-up"></i> Barang Keluar
        </a>
    </div>

    <div id="area-cetak" class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-xl font-bold text-center mb-6 hidden" id="judul-cetak">
            <?= $judul_laporan ?>
        </h3>

        <div id="tabel-container" class="overflow-x-auto">
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-300 p-3 text-center">Tanggal</th>
                        <th class="border border-gray-300 p-3 text-left">Nama Barang</th>
                        <th class="border border-gray-300 p-3 text-center">Qty</th>
                        <th class="border border-gray-300 p-3 text-center">Satuan</th>
                        <th class="border border-gray-300 p-3 text-center">No Faktur</th>
                        <th class="border border-gray-300 p-3 text-center">Jenis</th>
                        <th class="border border-gray-300 p-3 text-left">Keterangan</th>
                        <th class="border border-gray-300 p-3 text-right text-indigo-800">Nominal</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // PERBAIKAN: Menambahkan JOIN tabel supplier agar nama supplier bisa tertampil pada keterangan
                    $sql = "
                        SELECT 
                            t.tanggal, 
                            b.nama_barang, 
                            td.qty, 
                            td.harga_satuan,
                            td.subtotal,
                            b.harga_beli,
                            b.satuan, 
                            t.no_faktur, 
                            UPPER(IFNULL(t.jenis_transaksi, 'KELUAR')) AS jenis,
                            IF(t.jenis_transaksi = 'masuk', IFNULL(s.nama_supplier, 'Supplier Umum'), IFNULL(p.nama_pelanggan, 'Umum')) AS keterangan 
                        FROM transaksi_detail td
                        JOIN transaksi t ON td.no_faktur = t.no_faktur
                        JOIN barang b ON td.barang_id = b.id
                        LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                        LEFT JOIN supplier s ON t.supplier_id = s.id
                        $where_clause
                        ORDER BY t.tanggal DESC
                    ";
                    
                    // ======================================================
                    // PAGINASI: halaman ini sudah punya filter sendiri
                    // ($where_clause), yang ditambahkan hanya pembatas baris
                    // agar tidak memuat belasan ribu baris sekaligus.
                    // ======================================================
                    $h_hal   = ambil_halaman();
                    $h_limit = ambil_per_halaman();

                    $sql_hitung = "
                        SELECT COUNT(*) AS n
                        FROM transaksi_detail td
                        JOIN transaksi t ON td.no_faktur = t.no_faktur
                        JOIN barang b ON td.barang_id = b.id
                        LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                        LEFT JOIN supplier s ON t.supplier_id = s.id
                        $where_clause";
                    $q_hitung = mysqli_query($conn, $sql_hitung);
                    $h_total  = $q_hitung ? (int) mysqli_fetch_assoc($q_hitung)['n'] : 0;

                    $h_hal    = batasi_halaman($h_hal, $h_total, $h_limit);
                    $h_offset = ($h_hal - 1) * $h_limit;

                    $sql .= " LIMIT " . (int) $h_limit . " OFFSET " . (int) $h_offset;

                    $query = mysqli_query($conn, $sql);

                    if($query && mysqli_num_rows($query) > 0) {
                        while($row = mysqli_fetch_assoc($query)) {
                            $badge_color = ($row['jenis'] == 'MASUK') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            
                            // Hitung Nominal
                            $nominal = 0;
                            if ($row['jenis'] == 'MASUK') {
                                $nominal = (float)$row['qty'] * (float)$row['harga_beli'];
                            } else {
                                $nominal = $row['subtotal'] > 0 ? (float)$row['subtotal'] : ((float)$row['qty'] * (float)$row['harga_satuan']);
                            }
                            $format_nominal = "Rp " . number_format($nominal, 0, ',', '.');

                            echo "<tr class='hover:bg-gray-50 transition-colors'>";
                            echo "<td class='border border-gray-300 p-2 text-center whitespace-nowrap'>" . date('d/m/Y H:i', strtotime($row['tanggal'])) . "</td>";
                            echo "<td class='border border-gray-300 p-2 font-semibold text-indigo-700'>" . $row['nama_barang'] . "</td>";
                            echo "<td class='border border-gray-300 p-2 text-center font-bold'>" . (float)$row['qty'] . "</td>";
                            echo "<td class='border border-gray-300 p-2 text-center'>" . $row['satuan'] . "</td>";
                            echo "<td class='border border-gray-300 p-2 text-center text-gray-500 text-xs font-mono'>" . $row['no_faktur'] . "</td>";
                            echo "<td class='border border-gray-300 p-2 text-center'><span class='px-2 py-1 rounded text-[10px] font-bold tracking-wider $badge_color'>" . $row['jenis'] . "</span></td>";
                            echo "<td class='border border-gray-300 p-2 text-xs text-gray-600'>" . $row['keterangan'] . "</td>";
                            echo "<td class='border border-gray-300 p-2 text-right font-mono text-slate-800 font-bold whitespace-nowrap'>" . $format_nominal . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center p-8 border border-gray-300 text-gray-500 font-bold'>Tidak ada data transaksi yang sesuai dengan filter Anda.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <?= render_paginasi($h_hal, $h_total, $h_limit) ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    // ==========================================
    // 1. FUNGSI DOWNLOAD PDF (DIOPTIMASI)
    // ==========================================
    function downloadPDF() {
        const wrapper = document.getElementById('tabel-container');
        const element = document.getElementById('area-cetak');
        
        // Munculkan Judul
        document.getElementById('judul-cetak').style.display = 'block'; 
        
        // SANGAT PENTING: Hapus class overflow agar tabel PDF ditarik full (tidak blank/berat)
        wrapper.classList.remove('overflow-x-auto');
        
        // Tentukan nama file
        let fileName = 'Histori_Transaksi.pdf';
        <?php if (!empty($nama_barang)): ?>
            fileName = 'Histori_Transaksi_<?= htmlspecialchars($nama_barang) ?>.pdf';
        <?php endif; ?>

        const options = {
            margin:       0.4,
            filename:     fileName,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 1.5, useCORS: true }, // Skala diturunkan ke 1.5 agar lebih ringan & tidak error
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' },
            pagebreak:    { mode: 'css' }
        };
        
        // Eksekusi Convert & Download
        html2pdf().set(options).from(element).save().then(() => {
            // Kembalikan Tampilan ke Semula setelah selesai
            document.getElementById('judul-cetak').style.display = 'none'; 
            wrapper.classList.add('overflow-x-auto');
        });
    }

    // ==========================================
    // 2. FUNGSI CETAK LAPORAN (SANGAT RAPI - POPUP)
    // ==========================================
    function cetakLaporan() {
        // Ambil elemen tabel dan judul HTML
        const tableHTML = document.getElementById('tabel-container').innerHTML;
        const judulHTML = document.getElementById('judul-cetak').innerHTML;
        
        // Buka Window Baru Khusus Print (Supaya CSS dashboard tidak bocor ke hasil print)
        const printWindow = window.open('', '_blank', 'width=1200,height=800');
        
        printWindow.document.write(`
            <html>
            <head>
                <title>Cetak Histori Transaksi</title>
                <style>
                    /* Set Kertas Landscape */
                    @page { size: landscape; margin: 10mm; }
                    
                    /* Styling Khusus Kertas agar Rapi */
                    body { font-family: Arial, sans-serif; padding: 20px; color: #000; }
                    h3 { text-align: center; margin-bottom: 20px; font-size: 18px; font-weight: bold; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #333; padding: 8px; text-align: left; }
                    th { background-color: #f3f4f6 !important; text-align: center !important; font-weight: bold; }
                    
                    /* Utility Class Converter dari Tailwind */
                    .text-center { text-align: center !important; }
                    .text-right { text-align: right !important; }
                    .text-left { text-align: left !important; }
                    .whitespace-nowrap { white-space: nowrap !important; }
                    
                    /* Warna Badge Jenis */
                    .bg-green-100 { background-color: #dcfce7 !important; color: #166534 !important; }
                    .bg-red-100 { background-color: #fee2e2 !important; color: #991b1b !important; }
                    span.px-2 { padding: 3px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #ccc; font-size: 10px; }
                </style>
            </head>
            <body>
                <h3>${judulHTML}</h3>
                ${tableHTML}
                
                <script>
                    // Otomatis Muncul Jendela Print Saat Teks Selesai Dimuat
                    window.onload = function() {
                        window.print();
                        // Tutup otomatis tabnya jika sudah selesai print
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    }
</script>