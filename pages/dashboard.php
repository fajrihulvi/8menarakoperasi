<?php
// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Catatan: blok "popup pengumuman" dihapus karena variabelnya tidak pernah
// dipakai untuk menampilkan apa pun (fitur popup-nya sudah tidak ada).

// --- AMBIL DATA USER ---
$role     = $_SESSION['role'] ?? '';
$id_usaha = $_SESSION['id_usaha'] ?? 1;
$user_id  = $_SESSION['user_id'] ?? 0;

// ==============================================================================
// LOGIKA EKSEKUSI RESET STOK
// ==============================================================================
if (isset($_POST['reset_stok_semua'])) {

    // GERBANG AKSES: tindakan ini menolkan SELURUH stok dan tidak bisa dibatalkan,
    // jadi hanya admin yang boleh. Pengecekan ada di sisi server (bukan sekadar
    // menyembunyikan tombol), supaya kiriman POST langsung pun tetap ditolak.
    if (strtolower($role) !== 'admin') {
        if (function_exists('catat_log')) {
            catat_log($conn, "Akses Ditolak", "Percobaan reset stok oleh role '" . $role . "' (ditolak sistem).");
        }
        echo "<script>alert('Akses Ditolak! Hanya Administrator yang boleh mereset stok.'); window.location.href='index.php?page=dashboard';</script>";
        exit;
    }

    // Simpan stok lama ke tabel cadangan agar masih bisa dipulihkan bila salah klik
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS backup_stok_reset (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_usaha INT(11) NOT NULL,
        barang_id INT(11) NOT NULL,
        nama_barang VARCHAR(255) NULL,
        stok_lama DOUBLE NOT NULL DEFAULT 0,
        user_id INT(11) NULL,
        nama_user VARCHAR(255) NULL,
        waktu DATETIME NOT NULL,
        KEY idx_usaha_waktu (id_usaha, waktu)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $nama_pelaku = $_SESSION['nama'] ?? 'System';
    $stmt_bk = mysqli_prepare($conn,
        "INSERT INTO backup_stok_reset (id_usaha, barang_id, nama_barang, stok_lama, user_id, nama_user, waktu)
         SELECT id_usaha, id, nama_barang, stok, ?, ?, NOW() FROM barang WHERE id_usaha = ?");
    if ($stmt_bk) {
        mysqli_stmt_bind_param($stmt_bk, 'isi', $user_id, $nama_pelaku, $id_usaha);
        mysqli_stmt_execute($stmt_bk);
        mysqli_stmt_close($stmt_bk);
    }

    $jumlah_direset = 0;
    $stmt_reset = mysqli_prepare($conn, "UPDATE barang SET stok = 0 WHERE id_usaha = ?");
    $reset_query = false;
    if ($stmt_reset) {
        mysqli_stmt_bind_param($stmt_reset, 'i', $id_usaha);
        $reset_query = mysqli_stmt_execute($stmt_reset);
        $jumlah_direset = mysqli_stmt_affected_rows($stmt_reset);
        mysqli_stmt_close($stmt_reset);
    }

    if ($reset_query) {
        if(function_exists('catat_log')) {
            catat_log($conn, "Reset Stok", "Reset SEMUA stok barang ($jumlah_direset item) oleh $nama_pelaku. Stok lama tersimpan di backup_stok_reset.");
        }
        echo "<script>alert('Berhasil! $jumlah_direset stok barang telah di-reset menjadi 0.\\nStok lama tersimpan sebagai cadangan.'); window.location.href='index.php?page=dashboard';</script>";
    } else {
        // Jangan tampilkan pesan error database mentah ke layar pengguna
        error_log('[8MP] Gagal reset stok: ' . mysqli_error($conn));
        echo "<script>alert('Gagal mereset stok. Silakan hubungi administrator sistem.'); window.history.back();</script>";
    }
    exit;
}

// ==============================================================================
// PENGALIHAN KHUSUS (DRIVER, CHEF, AHLI GIZI)
// ==============================================================================
if ($role == 'driver') { echo "<script>window.location.href = 'index.php?page=driver_panel';</script>"; exit; }
if ($role == 'chef') { echo "<script>window.location.href = 'index.php?page=panel_chef';</script>"; exit; }
if ($role == 'ahli_gizi') { echo "<script>window.location.href = 'index.php?page=panel_gizi';</script>"; exit; }

if (!function_exists('format_rupiah')) {
    function format_rupiah($angka){ return "Rp " . number_format($angka,0,',','.'); }
}

?>

<style>
    /* Latar belakang animasi bergerak (LEBIH BERWARNA & HIDUP) */
    .bg-animated-mesh {
        background: linear-gradient(-45deg, #a1c4fd, #c2e9fb, #e0c3fc, #ffafbd);
        background-size: 400% 400%;
        animation: gradientMove 15s ease infinite;
    }
    @keyframes gradientMove {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Blob melayang di background */
    .blob {
        position: absolute; filter: blur(80px); z-index: -1; opacity: 0.85;
        animation: floatBlob 10s infinite alternate ease-in-out;
    }
    .blob-1 { top: -10%; left: -10%; width: 400px; height: 400px; background: rgba(99, 102, 241, 0.6); }
    .blob-2 { bottom: -10%; right: -10%; width: 500px; height: 500px; background: rgba(236, 72, 153, 0.5); animation-delay: 2s; }
    .blob-3 { top: 40%; left: 50%; width: 300px; height: 300px; background: rgba(56, 189, 248, 0.5); animation-delay: 4s; }
    
    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(30px, -50px) scale(1.1); }
    }

    .glass-panel {
        background: rgba(255, 255, 255, 0.55);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
    }

    .card-3d { transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); transform-style: preserve-3d; }
    .card-3d:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 30px 60px -12px rgba(79, 70, 229, 0.25), 0 18px 36px -18px rgba(0, 0, 0, 0.1);
        border-color: rgba(255, 255, 255, 1);
    }
    .card-3d:hover .icon-float { animation: floatIcon 2s ease-in-out infinite; }
    @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    .glow-effect { position: relative; overflow: hidden; }
    .glow-effect::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, transparent 60%);
        opacity: 0; transition: opacity 0.5s; pointer-events: none; mix-blend-mode: overlay;
    }
    .glow-effect:hover::before { opacity: 1; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    
    .animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; } 
    @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="min-h-screen bg-animated-mesh relative overflow-hidden transition-all duration-300">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="p-4 md:p-8 max-w-7xl mx-auto relative z-10">

        <?php
        // ==============================================================================
        // TAMPILAN 1: DASHBOARD KHUSUS PELANGGAN
        // ==============================================================================
        if ($role == 'pelanggan' || $role == 'invoice') {
        ?>
            <div class="mb-8 mt-14 md:mt-0 animate-fade-in" style="animation-delay: 0.1s;"> 
                <h2 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-700 to-purple-800 drop-shadow-sm">
                    Halo, <?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8') ?>! 👋
                </h2>
                <p class="text-slate-700 mt-2 font-medium text-lg">Selamat datang di panel masa depan. Pantau pesanan Anda di sini.</p>
            </div>

            <div class="grid grid-cols-1 mb-8 animate-fade-in" style="animation-delay: 0.2s;">
                <a href="index.php?page=monitoring_armada" class="group">
                    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-[2px] rounded-3xl shadow-2xl transition-transform duration-500 hover:scale-[1.02] hover:shadow-[0_0_40px_rgba(79,70,229,0.4)]">
                        <div class="glass-panel p-8 rounded-[22px] flex justify-between items-center text-white relative overflow-hidden" style="background: rgba(0,0,0,0.2) !important;">
                            <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            
                            <div class="relative z-10">
                                <h3 class="text-2xl font-bold mb-2 flex items-center gap-3">
                                    <span class="p-2 bg-white/20 rounded-lg"><i class="fa-solid fa-location-crosshairs"></i></span> Lacak Lokasi Driver
                                </h3>
                                <p class="text-blue-50 text-sm font-medium">Sistem radar canggih: Lihat posisi armada yang mengirim pesanan Anda secara real-time.</p>
                            </div>
                            <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-3xl shadow-lg relative z-10 border border-white/30 group-hover:-translate-y-2 transition-transform duration-500">
                                <i class="fa-solid fa-truck-fast icon-float"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="bg-gradient-to-r from-indigo-600 to-fuchsia-600 rounded-3xl shadow-2xl p-[2px] mb-10 animate-fade-in card-3d glow-effect" style="animation-delay: 0.3s;">
                <div class="bg-[#0f172a]/80 backdrop-blur-xl rounded-[22px] p-8 relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-fuchsia-500/30 rounded-full blur-3xl"></div>
                    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="text-white">
                            <h3 class="text-2xl font-bold flex items-center mb-2">
                                <span class="bg-yellow-400/20 text-yellow-300 p-2 rounded-lg mr-3 border border-yellow-400/30"><i class="fa-solid fa-robot"></i></span> 
                                Asisten Virtual AI
                            </h3>
                            <p class="text-indigo-200 text-sm font-medium">Bingung mau pesan apa? Putar musik? Atau tanya tips? Kecerdasan Buatan kami siap membantu!</p>
                        </div>
                        <button onclick="openAIChat()" class="bg-white text-indigo-700 px-8 py-4 rounded-xl font-extrabold shadow-[0_0_20px_rgba(255,255,255,0.3)] hover:shadow-[0_0_30px_rgba(255,255,255,0.6)] hover:scale-105 transition-all duration-300 flex items-center whitespace-nowrap text-sm group">
                            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-indigo-500 group-hover:rotate-12 transition-transform"></i> Mulai Percakapan
                        </button>
                    </div>
                </div>
            </div>

            <div class="glass-panel rounded-3xl overflow-hidden mb-24 animate-fade-in card-3d" style="animation-delay: 0.4s;">
                <div class="p-6 border-b border-white/50 flex justify-between items-center bg-white/40">
                    <h3 class="font-bold text-slate-800 text-lg flex items-center gap-3">
                        <div class="bg-indigo-100 text-indigo-600 p-2 rounded-lg"><i class="fa-solid fa-clock-rotate-left"></i></div> 
                        Pesanan Terakhir Anda
                    </h3>
                    <a href="index.php?page=riwayat_pesanan" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-lg hover:shadow-md transition-all">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto p-2">
                    <table class="w-full text-sm text-left">
                        <thead class="text-slate-500 uppercase text-xs font-bold border-b border-slate-200/50">
                            <tr>
                                <th class="p-4">No Pesanan</th>
                                <th class="p-4">Tanggal</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/50">
                            <?php
                            $q_his = mysqli_query($conn, "SELECT * FROM pesanan WHERE user_id='$user_id' ORDER BY id DESC LIMIT 5");
                            if(mysqli_num_rows($q_his) > 0):
                                while($r = mysqli_fetch_assoc($q_his)):
                                    $color = match($r['status']) { 'Pending'=>'bg-slate-200 text-slate-700','Persiapan'=>'bg-amber-200 text-amber-800','Pengiriman'=>'bg-blue-200 text-blue-800','Diterima'=>'bg-teal-200 text-teal-800','Selesai'=>'bg-emerald-200 text-emerald-800', default=>'bg-rose-200 text-rose-800' };
                            ?>
                            <tr class="hover:bg-white/60 transition-colors duration-300">
                                <td class="p-4 font-bold text-indigo-700 text-base"><?= htmlspecialchars($r['no_pesanan'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-4 text-slate-600 font-medium"><?= date('d M Y, H:i', strtotime($r['tanggal'])) ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-4 py-1.5 rounded-full text-xs uppercase font-extrabold shadow-sm <?= $color ?>"><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="p-4 text-center">
                                    <?php /* Diarahkan ke halaman Riwayat Pesanan yang sudah memiliki modal
                                             detail lengkap. Sebelumnya memanggil lihatDetail() yang tidak
                                             pernah didefinisikan di halaman ini, sehingga tombol tidak berfungsi. */ ?>
                                    <a href="index.php?page=riwayat_pesanan&lihat=<?= (int) $r['id'] ?>"
                                       title="Lihat detail pesanan <?= htmlspecialchars($r['no_pesanan'], ENT_QUOTES, 'UTF-8') ?>"
                                       class="w-10 h-10 rounded-full bg-white shadow-sm border border-slate-200 text-slate-400 hover:text-white hover:bg-indigo-500 hover:shadow-lg hover:-translate-y-1 transition-all flex items-center justify-center mx-auto">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="p-12 text-center text-slate-400 font-medium text-lg">Belum ada riwayat pesanan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php
        // ==============================================================================
        // TAMPILAN 2: DASHBOARD ADMIN / STAFF
        // ==============================================================================
        } else {
            $tgl = date('Y-m-d');
            
            $q_omzet = mysqli_query($conn, "
                SELECT SUM(td.subtotal) as total 
                FROM transaksi t
                JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
                WHERE DATE(t.tanggal) = '$tgl' 
                AND t.jenis_transaksi = 'keluar' 
                AND t.status = 'selesai' 
                AND t.status_bayar = 'lunas'
                AND t.id_usaha = '$id_usaha'
            ");
            $d_omzet = mysqli_fetch_assoc($q_omzet); 
            $omzet = $d_omzet['total'] ?? 0;

            $q_trx = mysqli_query($conn, "
                SELECT COUNT(DISTINCT t.no_faktur) as jlh 
                FROM transaksi t 
                WHERE DATE(t.tanggal) = '$tgl' 
                AND t.jenis_transaksi = 'keluar' 
                AND t.status = 'selesai' 
                AND t.status_bayar = 'lunas'
                AND t.id_usaha = '$id_usaha'
            ");
            $d_trx = mysqli_fetch_assoc($q_trx); 
            $trx = $d_trx['jlh'] ?? 0;

            $q_prod = mysqli_query($conn, "SELECT COUNT(*) as jlh FROM barang WHERE id_usaha='$id_usaha'");
            $d_prod = mysqli_fetch_assoc($q_prod); $produk = $d_prod['jlh'] ?? 0;

            $q_tipis = mysqli_query($conn, "SELECT COUNT(*) as jlh FROM barang WHERE stok <= 5 AND id_usaha='$id_usaha'");
            $d_tipis = mysqli_fetch_assoc($q_tipis); $tipis = $d_tipis['jlh'] ?? 0;

            // ==================================================================
            // KPI PROFITABILITAS (SELURUH PERIODE)
            // ------------------------------------------------------------------
            // Basis data sama dengan halaman Laporan Laba Rugi: hanya transaksi
            // penjualan yang sudah SELESAI dan LUNAS.
            //
            // Catatan HPP: sebagian baris transaksi_detail punya kolom `hpp`
            // kosong/0 (data lama). Bila dibiarkan 0, laba kotor jadi terlihat
            // jauh lebih besar dari kenyataan. Karena itu dipakai urutan:
            //   1) td.hpp bila terisi,
            //   2) harga_beli dari master barang sebagai perkiraan,
            //   3) 0 bila keduanya tidak ada.
            // ==================================================================
            $kpi_omzet = 0.0; $kpi_hpp = 0.0; $kpi_baris = 0; $kpi_baris_estimasi = 0;

            $sql_kpi = "
                SELECT
                    COALESCE(SUM(td.subtotal), 0) AS omzet,
                    COALESCE(SUM(COALESCE(NULLIF(td.hpp, 0), b.harga_beli, 0) * td.qty), 0) AS hpp,
                    COUNT(*) AS baris,
                    COALESCE(SUM(CASE WHEN td.hpp IS NULL OR td.hpp = 0 THEN 1 ELSE 0 END), 0) AS baris_estimasi
                FROM transaksi t
                JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
                LEFT JOIN barang b ON td.barang_id = b.id
                WHERE t.id_usaha = ?
                  AND t.jenis_transaksi = 'keluar'
                  AND t.status = 'selesai'
                  AND t.status_bayar = 'lunas'";

            $stmt_kpi = mysqli_prepare($conn, $sql_kpi);
            if ($stmt_kpi) {
                mysqli_stmt_bind_param($stmt_kpi, 'i', $id_usaha);
                mysqli_stmt_execute($stmt_kpi);
                $res_kpi = mysqli_stmt_get_result($stmt_kpi);
                if ($res_kpi && ($row_kpi = mysqli_fetch_assoc($res_kpi))) {
                    $kpi_omzet          = (float) $row_kpi['omzet'];
                    $kpi_hpp            = (float) $row_kpi['hpp'];
                    $kpi_baris          = (int)   $row_kpi['baris'];
                    $kpi_baris_estimasi = (int)   $row_kpi['baris_estimasi'];
                }
                mysqli_stmt_close($stmt_kpi);
            }

            $kpi_laba   = $kpi_omzet - $kpi_hpp;
            // Hindari pembagian nol bila belum ada penjualan sama sekali
            $kpi_margin = ($kpi_omzet > 0) ? ($kpi_laba / $kpi_omzet) * 100 : 0.0;

            // Ambang peringatan margin laba kotor (ubah di sini bila kebijakan berubah;
            // teks & indikator di kartu ikut menyesuaikan otomatis).
            $kpi_ambang = 20;
            $kpi_sehat  = ($kpi_margin >= $kpi_ambang);
            $kpi_persen_estimasi = ($kpi_baris > 0) ? ($kpi_baris_estimasi / $kpi_baris) * 100 : 0.0;

            // ==================================================================
            // DATA GRAFIK 1: TREN BULANAN (12 BULAN TERAKHIR)
            // Basis & rumus HPP sama persis dengan KPI di atas agar angkanya
            // konsisten: hpp transaksi bila ada, jika tidak pakai harga_beli.
            // ==================================================================
            $g_bulan = []; $g_omzet = []; $g_hpp = []; $g_laba = []; $g_margin = [];

            $sql_tren = "
                SELECT DATE_FORMAT(t.tanggal, '%Y-%m') AS bln,
                       COALESCE(SUM(td.subtotal), 0) AS omzet,
                       COALESCE(SUM(COALESCE(NULLIF(td.hpp, 0), b.harga_beli, 0) * td.qty), 0) AS hpp
                FROM transaksi t
                JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
                LEFT JOIN barang b ON td.barang_id = b.id
                WHERE t.id_usaha = ?
                  AND t.jenis_transaksi = 'keluar'
                  AND t.status = 'selesai'
                  AND t.status_bayar = 'lunas'
                  AND t.tanggal >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                GROUP BY bln
                ORDER BY bln ASC";

            $stmt_tren = mysqli_prepare($conn, $sql_tren);
            if ($stmt_tren) {
                mysqli_stmt_bind_param($stmt_tren, 'i', $id_usaha);
                mysqli_stmt_execute($stmt_tren);
                $res_tren = mysqli_stmt_get_result($stmt_tren);
                $nama_bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                while ($res_tren && ($rt = mysqli_fetch_assoc($res_tren))) {
                    $om = (float) $rt['omzet'];
                    $hp = (float) $rt['hpp'];
                    $lb = $om - $hp;
                    $pecah = explode('-', $rt['bln']);
                    $g_bulan[]  = ($nama_bulan[(int) $pecah[1]] ?? $pecah[1]) . ' ' . substr($pecah[0], 2);
                    // Dibulatkan agar JSON di halaman ringkas (rupiah tidak perlu desimal)
                    $g_omzet[]  = (int) round($om);
                    $g_hpp[]    = (int) round($hp);
                    $g_laba[]   = (int) round($lb);
                    $g_margin[] = $om > 0 ? number_format(($lb / $om) * 100, 2, '.', '') : '0';
                }
                mysqli_stmt_close($stmt_tren);
            }

            // ==================================================================
            // DATA GRAFIK 2: MARGIN PER KATEGORI (10 kategori omzet terbesar)
            // ==================================================================
            $k_nama = []; $k_margin = []; $k_omzet = []; $k_laba = [];

            $sql_kat = "
                SELECT COALESCE(NULLIF(TRIM(b.kategori), ''), '(Tanpa Kategori)') AS kategori,
                       COALESCE(SUM(td.subtotal), 0) AS omzet,
                       COALESCE(SUM(COALESCE(NULLIF(td.hpp, 0), b.harga_beli, 0) * td.qty), 0) AS hpp
                FROM transaksi t
                JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
                JOIN barang b ON td.barang_id = b.id
                WHERE t.id_usaha = ?
                  AND t.jenis_transaksi = 'keluar'
                  AND t.status = 'selesai'
                  AND t.status_bayar = 'lunas'
                GROUP BY kategori
                HAVING omzet > 0
                ORDER BY omzet DESC
                LIMIT 10";

            $stmt_kat = mysqli_prepare($conn, $sql_kat);
            if ($stmt_kat) {
                mysqli_stmt_bind_param($stmt_kat, 'i', $id_usaha);
                mysqli_stmt_execute($stmt_kat);
                $res_kat = mysqli_stmt_get_result($stmt_kat);
                while ($res_kat && ($rk = mysqli_fetch_assoc($res_kat))) {
                    $om = (float) $rk['omzet'];
                    $lb = $om - (float) $rk['hpp'];
                    $k_nama[]   = $rk['kategori'];
                    $k_omzet[]  = (int) round($om);
                    $k_laba[]   = (int) round($lb);
                    $k_margin[] = $om > 0 ? number_format(($lb / $om) * 100, 2, '.', '') : '0';
                }
                mysqli_stmt_close($stmt_kat);
            }

            // ==================================================================
            // DATA GRAFIK 3: KONTRIBUSI OMZET PER PELANGGAN
            // Menampilkan 6 pelanggan terbesar, sisanya digabung "Lainnya"
            // agar terlihat seberapa terkonsentrasi omzet pada sedikit pembeli.
            // ==================================================================
            $p_nama = []; $p_omzet = []; $p_persen = [];

            $sql_pel = "
                SELECT COALESCE(NULLIF(TRIM(p.nama_pelanggan), ''), '(Tanpa Nama)') AS nama,
                       COALESCE(SUM(td.subtotal), 0) AS omzet
                FROM transaksi t
                JOIN transaksi_detail td ON t.no_faktur = td.no_faktur
                LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
                WHERE t.id_usaha = ?
                  AND t.jenis_transaksi = 'keluar'
                  AND t.status = 'selesai'
                  AND t.status_bayar = 'lunas'
                GROUP BY t.pelanggan_id
                HAVING omzet > 0
                ORDER BY omzet DESC";

            $stmt_pel = mysqli_prepare($conn, $sql_pel);
            if ($stmt_pel) {
                mysqli_stmt_bind_param($stmt_pel, 'i', $id_usaha);
                mysqli_stmt_execute($stmt_pel);
                $res_pel = mysqli_stmt_get_result($stmt_pel);
                $semua_pel = [];
                while ($res_pel && ($rp = mysqli_fetch_assoc($res_pel))) { $semua_pel[] = $rp; }
                mysqli_stmt_close($stmt_pel);

                $total_pel = 0.0;
                foreach ($semua_pel as $rp) { $total_pel += (float) $rp['omzet']; }

                $batas_tampil = 6;
                foreach (array_slice($semua_pel, 0, $batas_tampil) as $rp) {
                    $nilai = (float) $rp['omzet'];
                    $p_nama[]   = $rp['nama'];
                    $p_omzet[]  = (int) round($nilai);
                    $p_persen[] = $total_pel > 0 ? number_format($nilai / $total_pel * 100, 1, '.', '') : '0';
                }
                // Gabungkan sisanya jadi satu irisan
                $sisa_pel = array_slice($semua_pel, $batas_tampil);
                if ($sisa_pel) {
                    $nilai_sisa = 0.0;
                    foreach ($sisa_pel as $rp) { $nilai_sisa += (float) $rp['omzet']; }
                    $p_nama[]   = 'Lainnya (' . count($sisa_pel) . ')';
                    $p_omzet[]  = (int) round($nilai_sisa);
                    $p_persen[] = $total_pel > 0 ? number_format($nilai_sisa / $total_pel * 100, 1, '.', '') : '0';
                }
            }

            // Konsentrasi 2 pelanggan teratas (indikator risiko ketergantungan)
            $p_konsentrasi = 0.0;
            if (count($p_persen) >= 2) {
                $p_konsentrasi = (float) $p_persen[0] + (float) $p_persen[1];
            } elseif (count($p_persen) === 1) {
                $p_konsentrasi = (float) $p_persen[0];
            }

            // ==================================================================
            // DATA GRAFIK 4: PIUTANG vs TERTAGIH PER BULAN
            // Berbasis nilai nota (total_transaksi), bukan detail barang,
            // karena yang dinilai adalah tagihan yang sudah/belum dibayar.
            // ==================================================================
            $r_bulan = []; $r_lunas = []; $r_belum = []; $r_nota = [];
            $r_total_piutang = 0.0; $r_total_nota = 0;

            $sql_piutang = "
                SELECT DATE_FORMAT(t.tanggal, '%Y-%m') AS bln,
                       COALESCE(SUM(CASE WHEN LOWER(COALESCE(t.status_bayar,'')) = 'lunas'
                                         THEN t.total_transaksi ELSE 0 END), 0) AS lunas,
                       COALESCE(SUM(CASE WHEN LOWER(COALESCE(t.status_bayar,'')) <> 'lunas'
                                         THEN t.total_transaksi ELSE 0 END), 0) AS belum,
                       COALESCE(SUM(CASE WHEN LOWER(COALESCE(t.status_bayar,'')) <> 'lunas'
                                         THEN 1 ELSE 0 END), 0) AS n_belum
                FROM transaksi t
                WHERE t.id_usaha = ?
                  AND t.jenis_transaksi = 'keluar'
                  AND t.tanggal >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                GROUP BY bln
                ORDER BY bln ASC";

            $stmt_piutang = mysqli_prepare($conn, $sql_piutang);
            if ($stmt_piutang) {
                mysqli_stmt_bind_param($stmt_piutang, 'i', $id_usaha);
                mysqli_stmt_execute($stmt_piutang);
                $res_piutang = mysqli_stmt_get_result($stmt_piutang);
                $nama_bulan2 = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                while ($res_piutang && ($rr = mysqli_fetch_assoc($res_piutang))) {
                    $pecah2 = explode('-', $rr['bln']);
                    $r_bulan[] = ($nama_bulan2[(int) $pecah2[1]] ?? $pecah2[1]) . ' ' . substr($pecah2[0], 2);
                    $r_lunas[] = (int) round((float) $rr['lunas']);
                    $r_belum[] = (int) round((float) $rr['belum']);
                    $r_nota[]  = (int) $rr['n_belum'];
                    $r_total_piutang += (float) $rr['belum'];
                    $r_total_nota    += (int) $rr['n_belum'];
                }
                mysqli_stmt_close($stmt_piutang);
            }

            $ada_grafik = (count($g_bulan) > 0 || count($k_nama) > 0
                           || count($p_nama) > 0 || count($r_bulan) > 0);
        ?>

            <div class="mb-4 mt-14 md:mt-0 animate-fade-in">
                <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-800 to-purple-600">Dashboard Utama</h2>
                <p class="text-slate-700 font-medium">Ringkasan performa dan data real-time hari ini.</p>
            </div>

            <!-- ================= KPI PROFITABILITAS (KESELURUHAN) ================= -->
            <div class="mb-4 animate-fade-in" style="animation-delay: 0.05s;">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                    <span class="bg-violet-100 text-violet-600 p-2 rounded-lg"><i class="fa-solid fa-chart-pie"></i></span>
                    Kinerja Profitabilitas
                    <span class="text-[11px] font-bold text-slate-500 bg-white/70 border border-white px-3 py-1 rounded-full">Seluruh Periode</span>
                </h3>
                <p class="text-slate-600 text-sm font-medium mt-1 ml-1">
                    Dihitung dari transaksi penjualan berstatus <b>Selesai</b> &amp; <b>Lunas</b>.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">

                <!-- 1. TOTAL MODAL BELANJA KELUAR / HPP -->
                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in relative overflow-hidden" style="animation-delay: 0.1s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/20 rounded-full blur-2xl group-hover:bg-amber-500/40 transition-colors duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl text-white shadow-lg shadow-amber-500/30 group-hover:scale-110 transition-transform duration-500">
                                <i class="fa-solid fa-cart-flatbed text-xl icon-float"></i>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full">Modal</span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Total Modal Belanja (HPP)</p>
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight break-words"><?= format_rupiah($kpi_hpp) ?></h3>
                        <p class="text-[11px] font-semibold text-slate-500 mt-2">Harga pokok barang yang terjual</p>
                    </div>
                </div>

                <!-- 2. TOTAL OMZET KESELURUHAN -->
                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in relative overflow-hidden" style="animation-delay: 0.15s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-500/20 rounded-full blur-2xl group-hover:bg-blue-500/40 transition-colors duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl text-white shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform duration-500">
                                <i class="fa-solid fa-sack-dollar text-xl icon-float"></i>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 bg-blue-100 px-2.5 py-1 rounded-full">Omzet</span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Total Omzet Keseluruhan</p>
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight break-words"><?= format_rupiah($kpi_omzet) ?></h3>
                        <p class="text-[11px] font-semibold text-slate-500 mt-2">Dari <?= number_format($kpi_baris) ?> baris penjualan</p>
                    </div>
                </div>

                <!-- 3. LABA KOTOR -->
                <?php $laba_positif = ($kpi_laba >= 0); ?>
                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in relative overflow-hidden" style="animation-delay: 0.2s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 <?= $laba_positif ? 'bg-emerald-500/20 group-hover:bg-emerald-500/40' : 'bg-rose-500/20 group-hover:bg-rose-500/40' ?> rounded-full blur-2xl transition-colors duration-500"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-gradient-to-br <?= $laba_positif ? 'from-emerald-400 to-teal-500 shadow-emerald-500/30' : 'from-rose-500 to-red-600 shadow-rose-500/30' ?> rounded-2xl text-white shadow-lg group-hover:scale-110 transition-transform duration-500">
                                <i class="fa-solid <?= $laba_positif ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?> text-xl icon-float"></i>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-wider <?= $laba_positif ? 'text-emerald-700 bg-emerald-100' : 'text-rose-700 bg-rose-100' ?> px-2.5 py-1 rounded-full">
                                <?= $laba_positif ? 'Laba' : 'Rugi' ?>
                            </span>
                        </div>
                        <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Laba Kotor</p>
                        <h3 class="text-2xl font-black tracking-tight break-words <?= $laba_positif ? 'text-emerald-700' : 'text-rose-700' ?>"><?= format_rupiah($kpi_laba) ?></h3>
                        <p class="text-[11px] font-semibold text-slate-500 mt-2">Omzet dikurangi modal belanja</p>
                    </div>
                </div>

                <!-- 4. PERSENTASE LABA KOTOR (ALERT < 20% MERAH) -->
                <div class="p-[2px] rounded-3xl animate-fade-in card-3d <?= $kpi_sehat ? 'bg-gradient-to-br from-emerald-400 to-teal-500 shadow-lg shadow-emerald-500/25' : 'bg-gradient-to-br from-rose-500 to-red-600 shadow-lg shadow-rose-500/30' ?>" style="animation-delay: 0.25s;">
                    <div class="glass-panel h-full p-6 rounded-[22px] glow-effect group relative overflow-hidden" style="background: rgba(255,255,255,0.85) !important;">
                        <div class="absolute -right-6 -top-6 w-24 h-24 <?= $kpi_sehat ? 'bg-emerald-500/25' : 'bg-rose-500/25' ?> rounded-full blur-2xl"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-gradient-to-br <?= $kpi_sehat ? 'from-emerald-400 to-teal-500 shadow-emerald-500/30' : 'from-rose-500 to-red-600 shadow-rose-500/30' ?> rounded-2xl text-white shadow-lg group-hover:scale-110 transition-transform duration-500">
                                    <i class="fa-solid <?= $kpi_sehat ? 'fa-shield-heart' : 'fa-triangle-exclamation' ?> text-xl icon-float"></i>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-wider <?= $kpi_sehat ? 'text-emerald-700 bg-emerald-100' : 'text-white bg-rose-600 animate-pulse' ?> px-2.5 py-1 rounded-full">
                                    <?= $kpi_sehat ? 'Sehat' : 'Waspada' ?>
                                </span>
                            </div>
                            <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Persentase Laba Kotor</p>
                            <h3 class="text-3xl font-black tracking-tight <?= $kpi_sehat ? 'text-emerald-700' : 'text-rose-700' ?>">
                                <?= number_format($kpi_margin, 2, ',', '.') ?><span class="text-xl">%</span>
                            </h3>

                            <!-- Bar indikator terhadap ambang 20% -->
                            <div class="mt-3 h-2 w-full bg-slate-200/80 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?= $kpi_sehat ? 'bg-gradient-to-r from-emerald-400 to-teal-500' : 'bg-gradient-to-r from-rose-500 to-red-600' ?>"
                                     style="width: <?= max(0, min(100, $kpi_margin)) ?>%"></div>
                            </div>
                            <p class="text-[11px] font-bold mt-2 <?= $kpi_sehat ? 'text-emerald-700' : 'text-rose-700' ?>">
                                <?= $kpi_sehat
                                    ? 'Di atas ambang minimal ' . $kpi_ambang . '%'
                                    : 'Di bawah ambang minimal ' . $kpi_ambang . '% — perlu ditinjau' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($kpi_baris_estimasi > 0): ?>
            <!-- Catatan transparansi: sebagian HPP diperkirakan dari master barang -->
            <div class="flex items-start gap-3 text-xs font-medium text-slate-600 bg-white/60 border border-white rounded-2xl px-4 py-3 mb-10 animate-fade-in" style="animation-delay: 0.3s;">
                <i class="fa-solid fa-circle-info text-slate-400 mt-0.5"></i>
                <span>
                    <b><?= number_format($kpi_persen_estimasi, 1, ',', '.') ?>%</b>
                    (<?= number_format($kpi_baris_estimasi) ?> dari <?= number_format($kpi_baris) ?> baris)
                    data penjualan tidak menyimpan harga pokok saat transaksi, sehingga HPP-nya diperkirakan
                    dari <b>harga beli</b> pada Master Barang. Angka laba kotor di atas bersifat perkiraan.
                </span>
            </div>
            <?php endif; ?>

            <!-- ================= GRAFIK EVALUASI BULANAN ================= -->
            <div class="mb-4 animate-fade-in" style="animation-delay: 0.32s;">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                    <span class="bg-indigo-100 text-indigo-600 p-2 rounded-lg"><i class="fa-solid fa-chart-column"></i></span>
                    Analisa Strategis
                </h3>
                <p class="text-slate-600 text-sm font-medium mt-1 ml-1">
                    Bahan evaluasi bulanan: arah tren laba dan kategori mana yang menekan margin.
                </p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 mb-10">

                <!-- GRAFIK 1: TREN BULANAN -->
                <div class="xl:col-span-3 glass-panel rounded-3xl p-6 card-3d animate-fade-in" style="animation-delay: 0.35s;">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-arrow-trend-up text-indigo-500"></i> Tren Omzet, Modal &amp; Laba
                        </h4>
                        <span class="text-[10px] font-bold text-slate-500 bg-white/70 border border-white px-2.5 py-1 rounded-full">12 Bulan Terakhir</span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-500 mb-4">Batang = nilai rupiah &nbsp;•&nbsp; Garis = margin laba kotor (%)</p>
                    <?php if (count($g_bulan) > 0): ?>
                        <div class="relative h-[320px]"><canvas id="grafikTren"></canvas></div>
                    <?php else: ?>
                        <div class="h-[320px] flex flex-col items-center justify-center text-slate-400">
                            <i class="fa-regular fa-chart-bar text-4xl mb-3 opacity-40"></i>
                            <span class="font-bold text-sm">Belum ada transaksi lunas untuk ditampilkan</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- GRAFIK 2: MARGIN PER KATEGORI -->
                <div class="xl:col-span-2 glass-panel rounded-3xl p-6 card-3d animate-fade-in" style="animation-delay: 0.4s;">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-layer-group text-violet-500"></i> Margin per Kategori
                        </h4>
                        <span class="text-[10px] font-bold text-slate-500 bg-white/70 border border-white px-2.5 py-1 rounded-full">Top 10 Omzet</span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-500 mb-4">
                        Merah = di bawah ambang <?= (int) $kpi_ambang ?>% &nbsp;•&nbsp; Hijau = sehat
                    </p>
                    <?php if (count($k_nama) > 0): ?>
                        <div class="relative h-[320px]"><canvas id="grafikKategori"></canvas></div>
                    <?php else: ?>
                        <div class="h-[320px] flex flex-col items-center justify-center text-slate-400">
                            <i class="fa-regular fa-chart-bar text-4xl mb-3 opacity-40"></i>
                            <span class="font-bold text-sm">Belum ada data kategori</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 mb-10">

                <!-- GRAFIK 3: KONTRIBUSI OMZET PER PELANGGAN -->
                <div class="xl:col-span-2 glass-panel rounded-3xl p-6 card-3d animate-fade-in" style="animation-delay: 0.45s;">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-users text-pink-500"></i> Kontribusi Pelanggan
                        </h4>
                        <span class="text-[10px] font-bold text-slate-500 bg-white/70 border border-white px-2.5 py-1 rounded-full">Top 6</span>
                    </div>
                    <?php if (count($p_nama) > 0): ?>
                        <p class="text-[11px] font-medium mb-3 <?= $p_konsentrasi >= 60 ? 'text-rose-600 font-bold' : 'text-slate-500' ?>">
                            <?php if ($p_konsentrasi >= 60): ?>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                2 pelanggan teratas menguasai <b><?= number_format($p_konsentrasi, 1, ',', '.') ?>%</b> omzet — risiko ketergantungan tinggi.
                            <?php else: ?>
                                2 pelanggan teratas menyumbang <?= number_format($p_konsentrasi, 1, ',', '.') ?>% omzet.
                            <?php endif; ?>
                        </p>
                        <div class="relative h-[300px]"><canvas id="grafikPelanggan"></canvas></div>
                    <?php else: ?>
                        <div class="h-[320px] flex flex-col items-center justify-center text-slate-400">
                            <i class="fa-regular fa-chart-pie text-4xl mb-3 opacity-40"></i>
                            <span class="font-bold text-sm">Belum ada data pelanggan</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- GRAFIK 4: PIUTANG vs TERTAGIH -->
                <div class="xl:col-span-3 glass-panel rounded-3xl p-6 card-3d animate-fade-in" style="animation-delay: 0.5s;">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-file-invoice-dollar text-cyan-600"></i> Tertagih vs Piutang
                        </h4>
                        <span class="text-[10px] font-bold text-slate-500 bg-white/70 border border-white px-2.5 py-1 rounded-full">12 Bulan Terakhir</span>
                    </div>
                    <?php if (count($r_bulan) > 0): ?>
                        <p class="text-[11px] font-medium mb-3 <?= $r_total_piutang > 0 ? 'text-rose-600 font-bold' : 'text-slate-500' ?>">
                            <?php if ($r_total_piutang > 0): ?>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Belum tertagih: <b><?= format_rupiah($r_total_piutang) ?></b> dari <?= number_format($r_total_nota) ?> nota.
                            <?php else: ?>
                                Seluruh tagihan sudah lunas.
                            <?php endif; ?>
                        </p>
                        <div class="relative h-[300px]"><canvas id="grafikPiutang"></canvas></div>
                    <?php else: ?>
                        <div class="h-[320px] flex flex-col items-center justify-center text-slate-400">
                            <i class="fa-regular fa-chart-bar text-4xl mb-3 opacity-40"></i>
                            <span class="font-bold text-sm">Belum ada data tagihan</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($ada_grafik): // Chart.js hanya dimuat bila memang ada data ?>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
            <script>
            (function () {
                if (typeof Chart === 'undefined') { return; } // CDN gagal dimuat: lewati saja

                const rupiahSingkat = function (v) {
                    const a = Math.abs(v);
                    if (a >= 1e9) return (v / 1e9).toFixed(1).replace('.', ',') + ' M';
                    if (a >= 1e6) return (v / 1e6).toFixed(0) + ' jt';
                    if (a >= 1e3) return (v / 1e3).toFixed(0) + ' rb';
                    return v;
                };
                const rupiahPenuh = function (v) {
                    return 'Rp ' + Number(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                };

                Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
                Chart.defaults.color = '#475569';

                // ---------- GRAFIK 1: TREN BULANAN ----------
                const elTren = document.getElementById('grafikTren');
                if (elTren) {
                    new Chart(elTren, {
                        data: {
                            labels: <?= json_encode($g_bulan, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [
                                {
                                    type: 'bar', label: 'Omzet',
                                    data: <?= json_encode($g_omzet) ?>,
                                    backgroundColor: 'rgba(59,130,246,0.75)',
                                    borderRadius: 6, yAxisID: 'y', order: 2
                                },
                                {
                                    type: 'bar', label: 'Modal (HPP)',
                                    data: <?= json_encode($g_hpp) ?>,
                                    backgroundColor: 'rgba(245,158,11,0.75)',
                                    borderRadius: 6, yAxisID: 'y', order: 2
                                },
                                {
                                    type: 'line', label: 'Margin (%)',
                                    data: (<?= json_encode($g_margin) ?>).map(Number),
                                    borderColor: '#059669', backgroundColor: '#059669',
                                    borderWidth: 3, tension: 0.35, pointRadius: 4,
                                    pointBackgroundColor: '#fff', pointBorderWidth: 2,
                                    yAxisID: 'y1', order: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { labels: { usePointStyle: true, boxWidth: 8, font: { weight: '700', size: 11 } } },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            if (ctx.dataset.yAxisID === 'y1') {
                                                return ' Margin: ' + Number(ctx.parsed.y).toFixed(2).replace('.', ',') + '%';
                                            }
                                            return ' ' + ctx.dataset.label + ': ' + rupiahPenuh(ctx.parsed.y);
                                        },
                                        afterBody: function (items) {
                                            const i = items[0].dataIndex;
                                            const laba = <?= json_encode($g_laba) ?>[i];
                                            return 'Laba Kotor: ' + rupiahPenuh(laba);
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    position: 'left', beginAtZero: true,
                                    grid: { color: 'rgba(148,163,184,0.18)' },
                                    ticks: { callback: rupiahSingkat, font: { size: 10 } }
                                },
                                y1: {
                                    position: 'right', grid: { drawOnChartArea: false },
                                    ticks: { callback: v => v + '%', font: { size: 10 } }
                                },
                                x: { grid: { display: false }, ticks: { font: { weight: '700', size: 10 } } }
                            }
                        }
                    });
                }

                // ---------- GRAFIK 2: MARGIN PER KATEGORI ----------
                const elKat = document.getElementById('grafikKategori');
                if (elKat) {
                    const marginKat = (<?= json_encode($k_margin) ?>).map(Number);
                    const omzetKat  = <?= json_encode($k_omzet) ?>;
                    const labaKat   = <?= json_encode($k_laba) ?>;
                    const ambang    = <?= (int) $kpi_ambang ?>;

                    new Chart(elKat, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($k_nama, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [{
                                label: 'Margin (%)',
                                data: marginKat,
                                backgroundColor: marginKat.map(m => m < ambang ? 'rgba(225,29,72,0.8)' : 'rgba(16,185,129,0.8)'),
                                borderRadius: 6, borderSkipped: false
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            return ' Margin: ' + Number(ctx.parsed.x).toFixed(2).replace('.', ',') + '%';
                                        },
                                        afterBody: function (items) {
                                            const i = items[0].dataIndex;
                                            return ['Omzet: ' + rupiahPenuh(omzetKat[i]),
                                                    'Laba : ' + rupiahPenuh(labaKat[i])];
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: 'rgba(148,163,184,0.18)' },
                                    ticks: { callback: v => v + '%', font: { size: 10 } }
                                },
                                y: { grid: { display: false }, ticks: { font: { weight: '700', size: 10 } } }
                            }
                        }
                    });
                }

                // ---------- GRAFIK 3: KONTRIBUSI PELANGGAN ----------
                const elPel = document.getElementById('grafikPelanggan');
                if (elPel) {
                    const persenPel = (<?= json_encode($p_persen) ?>).map(Number);
                    const warnaPel = ['#4f46e5','#ec4899','#06b6d4','#f59e0b','#10b981','#8b5cf6','#94a3b8'];

                    new Chart(elPel, {
                        type: 'doughnut',
                        data: {
                            labels: <?= json_encode($p_nama, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [{
                                data: <?= json_encode($p_omzet) ?>,
                                backgroundColor: warnaPel,
                                borderColor: '#fff', borderWidth: 2, hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '58%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true, boxWidth: 8, padding: 10,
                                        font: { size: 10, weight: '600' },
                                        generateLabels: function (chart) {
                                            const d = chart.data;
                                            return d.labels.map(function (l, i) {
                                                return {
                                                    text: l + ' — ' + persenPel[i].toFixed(1).replace('.', ',') + '%',
                                                    fillStyle: d.datasets[0].backgroundColor[i],
                                                    strokeStyle: d.datasets[0].backgroundColor[i],
                                                    index: i
                                                };
                                            });
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            return ' ' + rupiahPenuh(ctx.parsed) +
                                                   ' (' + persenPel[ctx.dataIndex].toFixed(1).replace('.', ',') + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // ---------- GRAFIK 4: PIUTANG vs TERTAGIH ----------
                const elPiutang = document.getElementById('grafikPiutang');
                if (elPiutang) {
                    const notaBelum = <?= json_encode($r_nota) ?>;

                    new Chart(elPiutang, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($r_bulan, JSON_UNESCAPED_UNICODE) ?>,
                            datasets: [
                                {
                                    label: 'Tertagih (Lunas)',
                                    data: <?= json_encode($r_lunas) ?>,
                                    backgroundColor: 'rgba(16,185,129,0.8)', borderRadius: 6
                                },
                                {
                                    label: 'Belum Tertagih',
                                    data: <?= json_encode($r_belum) ?>,
                                    backgroundColor: 'rgba(225,29,72,0.8)', borderRadius: 6
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { labels: { usePointStyle: true, boxWidth: 8, font: { weight: '700', size: 11 } } },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            return ' ' + ctx.dataset.label + ': ' + rupiahPenuh(ctx.parsed.y);
                                        },
                                        afterBody: function (items) {
                                            const n = notaBelum[items[0].dataIndex];
                                            return n > 0 ? n + ' nota belum lunas' : 'Semua nota lunas';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { stacked: true, grid: { display: false }, ticks: { font: { weight: '700', size: 10 } } },
                                y: {
                                    stacked: true, beginAtZero: true,
                                    grid: { color: 'rgba(148,163,184,0.18)' },
                                    ticks: { callback: rupiahSingkat, font: { size: 10 } }
                                }
                            }
                        }
                    });
                }
            })();
            </script>
            <?php endif; // $ada_grafik: skrip hanya dimuat bila ada data ?>

            <div class="mb-4 animate-fade-in" style="animation-delay: 0.3s;">
                <h3 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                    <span class="bg-sky-100 text-sky-600 p-2 rounded-lg"><i class="fa-solid fa-calendar-day"></i></span>
                    Ringkasan Hari Ini
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                
                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in" style="animation-delay: 0.1s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-indigo-500/20 rounded-full blur-2xl group-hover:bg-indigo-500/40 transition-colors duration-500"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="p-4 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl text-white shadow-lg shadow-indigo-500/30 group-hover:scale-110 transition-transform duration-500">
                            <i class="fa-solid fa-wallet text-2xl icon-float"></i>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Omset Hari Ini</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight"><?= format_rupiah($omzet) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in" style="animation-delay: 0.2s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/20 rounded-full blur-2xl group-hover:bg-emerald-500/40 transition-colors duration-500"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="p-4 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl text-white shadow-lg shadow-emerald-500/30 group-hover:scale-110 transition-transform duration-500">
                            <i class="fa-solid fa-receipt text-2xl icon-float"></i>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Trx Selesai</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight"><?= $trx ?> <span class="text-sm font-bold text-slate-500">Nota</span></h3>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in" style="animation-delay: 0.3s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-cyan-500/20 rounded-full blur-2xl group-hover:bg-cyan-500/40 transition-colors duration-500"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="p-4 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-2xl text-white shadow-lg shadow-cyan-500/30 group-hover:scale-110 transition-transform duration-500">
                            <i class="fa-solid fa-boxes-stacked text-2xl icon-float"></i>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Total Produk</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight"><?= $produk ?> <span class="text-sm font-bold text-slate-500">Item</span></h3>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6 rounded-3xl card-3d glow-effect group animate-fade-in" style="animation-delay: 0.4s;">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-rose-500/20 rounded-full blur-2xl group-hover:bg-rose-500/40 transition-colors duration-500"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="p-4 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl text-white shadow-lg shadow-rose-500/30 group-hover:scale-110 transition-transform duration-500">
                            <i class="fa-solid fa-triangle-exclamation text-2xl icon-float"></i>
                        </div>
                        <div>
                            <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-1">Stok Kritis</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight"><?= $tipis ?> <span class="text-sm font-bold text-slate-500">Item</span></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-10 animate-fade-in" style="animation-delay: 0.5s;">
                <h3 class="text-lg font-extrabold text-slate-800 mb-5 flex items-center gap-3">
                    <span class="bg-amber-100 text-amber-500 p-2 rounded-lg"><i class="fa-solid fa-bolt"></i></span> Aksi Cepat
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                    <a href="index.php?page=histori_transaksi" class="glass-panel p-5 rounded-2xl card-3d group flex items-center gap-4 border-l-4 border-l-indigo-500">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-100 to-indigo-200 text-indigo-600 flex items-center justify-center text-xl shadow-inner group-hover:-rotate-12 transition-transform duration-300">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">Histori Trx</h4>
                            <p class="text-[11px] font-semibold text-slate-600">In & Out Stok</p>
                        </div>
                    </a>

                    <?php if (strtolower($role) === 'admin'): // Reset stok khusus admin ?>
                    <form method="POST" onsubmit="return confirm('⚠️ PERINGATAN BAHAYA!\n\nApakah Anda YAKIN ingin MERESET SEMUA STOK BARANG menjadi 0?\n\nStok lama akan dicadangkan, tetapi seluruh stok berjalan akan hilang!');" class="glass-panel p-5 rounded-2xl card-3d group border-l-4 border-l-red-500 cursor-pointer hover:bg-red-50/50 m-0">
                        <button type="submit" name="reset_stok_semua" class="w-full flex items-center gap-4 text-left bg-transparent border-0 p-0 cursor-pointer outline-none">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-100 to-red-200 text-red-600 flex items-center justify-center text-xl shadow-inner group-hover:-rotate-12 transition-transform duration-300">
                                <i class="fa-solid fa-trash-arrow-up"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 group-hover:text-red-600 transition-colors">Reset Stok</h4>
                                <p class="text-[11px] font-semibold text-slate-600">Nol-kan Semua Stok</p>
                            </div>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gradient-to-r from-violet-700 via-indigo-600 to-blue-600 rounded-3xl shadow-2xl p-[2px] mb-10 animate-fade-in card-3d glow-effect" style="animation-delay: 0.6s;">
                <div class="bg-[#0f172a]/40 backdrop-blur-2xl rounded-[22px] p-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-full h-full bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4xKSIvPjwvc3ZnPg==')] opacity-50"></div>
                    <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="text-white">
                            <h3 class="text-2xl font-bold flex items-center mb-2">
                                <span class="bg-yellow-400/20 text-yellow-300 p-2 rounded-lg mr-3 shadow-[0_0_15px_rgba(250,204,21,0.3)]"><i class="fa-solid fa-microchip"></i></span> 
                                AI Business Assistant
                            </h3>
                            <p class="text-indigo-100 text-sm font-medium">Kecerdasan Buatan Terintegrasi. Minta analisa data, cek stok, atau cari insight bisnis.</p>
                        </div>
                        <button onclick="openAIChat()" class="bg-white text-indigo-700 px-8 py-4 rounded-xl font-extrabold shadow-[0_0_20px_rgba(255,255,255,0.3)] hover:shadow-[0_0_30px_rgba(255,255,255,0.6)] hover:-translate-y-1 transition-all duration-300 flex items-center whitespace-nowrap text-sm group">
                            <i class="fa-solid fa-wand-magic-sparkles mr-2 text-indigo-500 group-hover:scale-125 transition-transform"></i> Buka Chat AI
                        </button>
                    </div>
                </div>
            </div>

            <?php
            // ==============================================================================
            // Smart Auditor
            // ==============================================================================
            $q_audit = mysqli_query($conn, "SELECT nama_barang, stok FROM barang WHERE id_usaha='$id_usaha' AND stok < 0");
            $jumlah_anomali = mysqli_num_rows($q_audit);

            if($jumlah_anomali > 0):
            ?>
            <div class="bg-red-50 border border-red-200 p-5 mb-10 rounded-2xl shadow-sm animate-fade-in relative overflow-hidden group">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-red-500/10 rounded-full blur-2xl"></div>
                <div class="flex items-start relative z-10">
                    <div class="flex-shrink-0 bg-red-100 p-3 rounded-xl shadow-sm border border-red-200">
                        <i class="fa-solid fa-robot text-red-600 text-2xl group-hover:rotate-12 transition-transform"></i>
                    </div>
                    <div class="ml-4 w-full">
                        <h3 class="text-sm font-black text-red-800 uppercase tracking-wide">Peringatan Sistem Auditor</h3>
                        <div class="mt-1 text-sm text-red-700">
                            <p>Sistem menemukan <b class="bg-red-200 px-1 rounded"><?= $jumlah_anomali ?> barang</b> dengan stok minus (di bawah 0). Ini adalah anomali yang harus segera diperiksa karena stok fisik tidak mungkin bernilai minus.</p>
                            <ul class="list-disc list-inside mt-3 font-mono text-xs bg-white/60 p-3 rounded-lg border border-red-100 w-full md:w-1/2">
                                <?php while($anomali = mysqli_fetch_assoc($q_audit)): ?>
                                    <li><?= htmlspecialchars($anomali['nama_barang'], ENT_QUOTES, 'UTF-8') ?> (Stok saat ini: <span class="font-bold text-red-600"><?= (float)$anomali['stok'] ?></span>)</li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <a href="index.php?page=barang" class="inline-flex items-center text-xs font-bold bg-red-600 hover:bg-red-700 text-white shadow-md hover:shadow-lg hover:-translate-y-0.5 px-4 py-2 rounded-lg transition-all duration-300">
                                <i class="fa-solid fa-boxes-stacked mr-2"></i> Perbaiki Stok di Master Barang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-24 animate-fade-in" style="animation-delay: 0.7s;"> 
                
                <div class="lg:col-span-1 glass-panel rounded-3xl flex flex-col h-[500px] overflow-hidden card-3d">
                    <div class="p-6 border-b border-white/50 flex justify-between items-center bg-white/40">
                        <h3 class="font-bold text-slate-800 flex items-center gap-3 text-lg">
                            <div class="bg-orange-100 text-orange-600 p-2 rounded-lg shadow-sm"><i class="fa-solid fa-box-open"></i></div> Stok Menipis
                        </h3>
                    </div>
                    <div class="overflow-y-auto flex-1 custom-scrollbar p-3">
                        <table class="w-full text-sm text-left">
                            <tbody>
                                <?php
                                $q_stok_tabel = mysqli_query($conn, "SELECT nama_barang, stok, satuan FROM barang WHERE stok <= 5 AND id_usaha='$id_usaha' ORDER BY stok ASC");
                                if(mysqli_num_rows($q_stok_tabel) > 0):
                                    while($r = mysqli_fetch_assoc($q_stok_tabel)):
                                ?>
                                <tr class="group border-b border-slate-200/50 last:border-0 hover:bg-white/60 transition-colors">
                                    <td class="py-4 px-4"><div class="font-bold text-slate-700"><?= htmlspecialchars($r['nama_barang'], ENT_QUOTES, 'UTF-8') ?></div></td>
                                    <td class="py-4 px-4 text-right">
                                        <span class="bg-red-100 text-red-600 px-3 py-1.5 rounded-lg font-bold shadow-sm inline-block transform group-hover:scale-110 transition-transform">
                                            <?= (float)$r['stok'] ?> <?= htmlspecialchars($r['satuan'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="2" class="py-16 text-center text-emerald-500"><div class="bg-emerald-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm"><i class="fa-solid fa-check text-3xl"></i></div><span class="font-bold text-lg">Stok Aman Semua!</span></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-2 glass-panel rounded-3xl flex flex-col h-[500px] overflow-hidden card-3d">
                    <div class="p-6 border-b border-white/50 bg-white/40">
                        <h3 class="font-bold text-slate-800 flex items-center gap-3 text-lg">
                            <div class="bg-blue-100 text-blue-600 p-2 rounded-lg shadow-sm"><i class="fa-solid fa-list-check"></i></div> Log Aktivitas Sistem
                        </h3>
                    </div>
                    <div class="overflow-y-auto flex-1 custom-scrollbar p-5 space-y-4">
                        <?php
                        $query_log = mysqli_query($conn, "SELECT * FROM log_aktivitas WHERE user_id IN (SELECT id FROM users WHERE id_usaha = '$id_usaha') ORDER BY tanggal DESC LIMIT 50");
                        if ($query_log && mysqli_num_rows($query_log) > 0):
                            while($log = mysqli_fetch_assoc($query_log)): 
                                $icon = 'fa-circle-info'; $bg_badge = 'bg-slate-100 text-slate-600';
                                if(strpos($log['aksi'], 'Login') !== false) { $icon='fa-right-to-bracket'; $bg_badge='bg-emerald-100 text-emerald-700 shadow-sm'; }
                                elseif(strpos($log['aksi'], 'Penjualan') !== false) { $icon='fa-cart-shopping'; $bg_badge='bg-blue-100 text-blue-700 shadow-sm'; }
                                elseif(strpos($log['aksi'], 'Tambah') !== false) { $icon='fa-plus'; $bg_badge='bg-indigo-100 text-indigo-700 shadow-sm'; }
                                elseif(strpos($log['aksi'], 'Edit') !== false) { $icon='fa-pen-to-square'; $bg_badge='bg-amber-100 text-amber-700 shadow-sm'; }
                                elseif(strpos($log['aksi'], 'Hapus') !== false) { $icon='fa-trash'; $bg_badge='bg-rose-100 text-rose-700 shadow-sm'; }
                        ?>
                            <div class="flex items-start gap-5 p-4 rounded-2xl bg-white/60 hover:bg-white shadow-[0_2px_10px_rgba(0,0,0,0.02)] hover:shadow-[0_5px_15px_rgba(0,0,0,0.05)] transition-all duration-300 group border border-white">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-lg font-bold flex-shrink-0 <?= $bg_badge ?> group-hover:scale-110 transition-transform duration-300">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <span class="text-sm font-extrabold text-slate-800 group-hover:text-indigo-600 transition-colors"><?= htmlspecialchars($log['nama_user'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-[10px] font-bold text-slate-500 bg-white shadow-sm border border-slate-100 px-2.5 py-1 rounded-full"><?= date('H:i', strtotime($log['tanggal'])) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span class="text-[10px] font-black uppercase tracking-wider <?= $bg_badge ?> px-2 py-0.5 rounded-md"><?= htmlspecialchars($log['aksi'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="text-xs font-medium text-slate-500 leading-relaxed truncate group-hover:whitespace-normal transition-all duration-300"><?= htmlspecialchars($log['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400"><p class="text-sm font-bold">Belum ada aktivitas tercatat</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        
        <div class="mt-8 pt-6 border-t border-slate-300/50 flex flex-col items-center justify-center text-center pb-6 relative z-10 animate-fade-in" style="animation-delay: 0.8s;">
            <p class="text-sm font-bold text-slate-500 mb-1">
                &copy; <?= date('Y') ?> <span class="text-indigo-600 font-black tracking-wide">IT-SOLUTION</span>. All rights reserved.
            </p>
            <p class="text-xs font-medium text-slate-400">Developed with <i class="fa-solid fa-heart text-rose-500 animate-pulse"></i> for Better Business.</p>
        </div>

    </div>
</div>



<div id="aiChatModal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#0f172a]/60 backdrop-blur-md" onclick="closeAIChat()"></div>
    
    <div class="bg-white/90 backdrop-blur-2xl w-full max-w-lg h-[600px] rounded-[30px] shadow-[0_0_50px_rgba(0,0,0,0.3)] flex flex-col overflow-hidden ring-1 ring-white/50 relative z-10 transform scale-95 opacity-0 transition-all duration-300" id="aiModalContent">
        
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-5 flex justify-between items-center text-white shadow-lg shrink-0 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4xKSIvPjwvc3ZnPg==')] opacity-30"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 shadow-inner"><i class="fa-solid fa-robot text-2xl"></i></div>
                <div>
                    <h3 class="font-extrabold text-lg">Asisten AI</h3>
                    <p class="text-xs text-indigo-200 font-medium flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span> Online & Siap</p>
                </div>
            </div>
            <button onclick="closeAIChat()" class="w-10 h-10 rounded-full hover:bg-white/20 flex items-center justify-center transition-colors relative z-10"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        
        <div id="aiChatBox" class="flex-1 p-5 overflow-y-auto bg-slate-50/50 space-y-5 custom-scrollbar">
            <div class="flex gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 shadow-sm border border-indigo-200"><i class="fa-solid fa-robot"></i></div>
                <div class="bg-white border border-slate-200 p-4 rounded-2xl rounded-tl-none text-sm font-medium text-slate-700 shadow-[0_4px_15px_rgba(0,0,0,0.03)]">
                    Halo! 👋 Saya bisa bantu cek stok, omset, atau putar musik. <br><br>Coba ketik: <br><span class="inline-block mt-2 bg-slate-100 px-2 py-1 rounded text-indigo-600 font-bold">"Putar lagu Santai"</span> atau <span class="inline-block mt-2 bg-slate-100 px-2 py-1 rounded text-indigo-600 font-bold">"Tips jualan"</span>.
                </div>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-200 shrink-0">
            <form onsubmit="kirimPesanAI(event)" class="flex gap-3 relative">
                <input type="text" id="inputPesanAI" class="flex-1 bg-slate-100 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium focus:ring-4 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-400 outline-none transition-all shadow-inner" placeholder="Ketik pesan untuk AI..." autocomplete="off">
                <button type="submit" id="btnKirimAI" class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-purple-600 text-white rounded-2xl flex items-center justify-center hover:shadow-[0_0_20px_rgba(79,70,229,0.4)] hover:scale-105 transition-all duration-300">
                    <i class="fa-solid fa-paper-plane text-lg"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openAIChat() {
    const modal = document.getElementById('aiChatModal');
    const content = document.getElementById('aiModalContent');
    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
        document.getElementById('inputPesanAI').focus();
    }, 10);
}

function closeAIChat() {
    const modal = document.getElementById('aiChatModal');
    const content = document.getElementById('aiModalContent');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}

function kirimPesanAI(e) {
    e.preventDefault();
    let input = document.getElementById('inputPesanAI');
    let msg = input.value.trim();
    if(!msg) return;

    let chatBox = document.getElementById('aiChatBox');
    
    chatBox.innerHTML += `
        <div class="flex gap-3 justify-end animate-fade-in">
            <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white p-4 rounded-2xl rounded-tr-none text-sm font-medium shadow-[0_4px_15px_rgba(79,70,229,0.3)] max-w-[80%]">${msg}</div>
        </div>`;
    
    input.value = '';
    chatBox.scrollTop = chatBox.scrollHeight;

    let loadingId = 'loading-' + Date.now();
    chatBox.innerHTML += `
        <div id="${loadingId}" class="flex gap-3 animate-fade-in">
            <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 shadow-sm border border-indigo-200"><i class="fa-solid fa-robot"></i></div>
            <div class="bg-white border border-slate-200 p-4 rounded-2xl rounded-tl-none text-sm font-bold text-indigo-500 shadow-[0_4px_15px_rgba(0,0,0,0.03)] flex items-center gap-3">
                <i class="fa-solid fa-circle-notch fa-spin text-lg"></i> AI sedang menganalisa...
            </div>
        </div>`;
    chatBox.scrollTop = chatBox.scrollHeight;

    let fd = new FormData();
    fd.append('message', msg);

    fetch('ajax_chat_ai.php', { method: 'POST', body: fd })
    .then(res => res.text())
    .then(response => {
        document.getElementById(loadingId).remove();
        chatBox.innerHTML += `
            <div class="flex gap-3 animate-fade-in">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0 shadow-sm border border-indigo-200"><i class="fa-solid fa-robot"></i></div>
                <div class="bg-white border border-slate-200 p-4 rounded-2xl rounded-tl-none text-sm font-medium text-slate-700 shadow-[0_4px_15px_rgba(0,0,0,0.03)] max-w-[90%] leading-relaxed">
                    ${response}
                </div>
            </div>`;
        chatBox.scrollTop = chatBox.scrollHeight;
    })
    .catch(err => {
        document.getElementById(loadingId).remove();
        alert('Gagal terhubung ke AI');
    });
}
</script>