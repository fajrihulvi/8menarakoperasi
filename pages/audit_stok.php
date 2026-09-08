<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Hanya izinkan Admin & Accounting yang bisa melihat audit
wajib_akses('audit_stok');

$id_usaha = $_SESSION['id_usaha'] ?? 1;

// =========================================================================
// 0. PENGATURAN FILTER WAKTU (MENGABAIKAN ERROR MASA LALU)
// AI hanya akan mengawasi transaksi mulai tanggal 20 Mei 2026 ke depan
// =========================================================================
$cutoff_date = '2026-05-20 00:00:00';

// =========================================================================
// FITUR MAGIS AI: 1A. KEMBALIKAN STOK SAJA (INVOICE AMAN / TIDAK DIHAPUS)
// =========================================================================
if(isset($_POST['magic_fix_bocor_restore'])) {
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    
    // Tarik dan kembalikan stok fisik ke gudang
    $q_td = mysqli_query($conn, "SELECT barang_id, qty FROM transaksi_detail WHERE no_faktur='$no_pesanan'");
    while($td = mysqli_fetch_assoc($q_td)) {
        $b_id = $td['barang_id'];
        $qty = (float)$td['qty'];
        mysqli_query($conn, "UPDATE barang SET stok = stok + $qty WHERE id='$b_id'");
    }
    
    // PERMINTAAN USER: JANGAN HAPUS INVOICE.
    // Solusi: Kita ubah status transaksinya menjadi 'batal' agar tidak memotong stok lagi
    // namun data riwayat invoice tersebut TETAP ADA di database.
    mysqli_query($conn, "UPDATE transaksi SET status = 'batal' WHERE no_faktur='$no_pesanan'");
    
    echo "<script>alert('✨ MAGIC FIX: Stok barang dikembalikan ke gudang. Data invoice tidak dihapus (hanya diset menjadi Batal).'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// FITUR MAGIS AI: 1B. NETRALKAN / SINKRONKAN (INVOICE AMAN / TIDAK DIHAPUS)
// =========================================================================
if(isset($_POST['magic_fix_bocor_netral'])) {
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    
    // PERMINTAAN USER: JANGAN HAPUS INVOICE.
    // Solusi: Kita sinkronkan saja status Pesanan dan Transaksinya menjadi 'Selesai'
    // Sehingga anomali hilang dari daftar audit tanpa mengubah stok saat ini.
    mysqli_query($conn, "UPDATE pesanan SET status = 'Selesai' WHERE no_pesanan='$no_pesanan' AND id_usaha='$id_usaha'");
    mysqli_query($conn, "UPDATE transaksi SET status = 'selesai' WHERE no_faktur='$no_pesanan'");
    
    echo "<script>alert('✨ MAGIC FIX: Anomali disinkronkan menjadi [Selesai]. Invoice aman tidak dihapus dan stok berjalan dipertahankan.'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// FITUR MAGIS AI: 2. PERBAIKI STOK MENGGANTUNG (ORDER)
// =========================================================================
if(isset($_POST['magic_fix_gantung'])) {
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    
    // Fitur ini aman, tidak pernah menghapus invoice, hanya memundurkan status pesanan.
    mysqli_query($conn, "UPDATE pesanan SET status='Pengiriman' WHERE no_pesanan='$no_pesanan' AND id_usaha='$id_usaha'");
    
    echo "<script>alert('✨ MAGIC FIX BERHASIL!\\n\\nStatus pesanan dimundurkan ke [Pengiriman]. Silakan proses ulang ke [Selesai] di menu Pesanan. (Data invoice aman).'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// FITUR MAGIS AI: 3. PERBAIKI BARANG MASUK FIKTIF
// =========================================================================
if(isset($_POST['magic_fix_bm_ghost'])) {
    $no_faktur = mysqli_real_escape_string($conn, $_POST['no_faktur']);
    mysqli_query($conn, "DELETE FROM transaksi WHERE no_faktur='$no_faktur' AND id_usaha='$id_usaha'");
    echo "<script>alert('✨ MAGIC FIX BERHASIL!\\n\\nRiwayat Barang Masuk fiktif dibersihkan dari sistem.'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// FITUR MAGIS AI: 4A. PERBAIKI STOK MINUS (JADIKAN NOL)
// =========================================================================
if(isset($_POST['magic_fix_minus'])) {
    $id_brg = mysqli_real_escape_string($conn, $_POST['id_barang']);
    mysqli_query($conn, "UPDATE barang SET stok = 0 WHERE id='$id_brg'");
    echo "<script>alert('✨ MAGIC FIX BERHASIL!\\n\\nStok dinetralkan menjadi 0.'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// FITUR MAGIS AI: 4B. KALKULASI ULANG STOK DARI NOL (SMART SYNC)
// =========================================================================
if(isset($_POST['magic_fix_recalc'])) {
    $id_brg = mysqli_real_escape_string($conn, $_POST['id_barang']);
    
    // Hitung Keluar (Penjualan Selesai)
    $q_out = mysqli_query($conn, "SELECT SUM(td.qty) as t_out FROM transaksi_detail td JOIN transaksi t ON td.no_faktur = t.no_faktur WHERE td.barang_id='$id_brg' AND t.status='selesai' AND (t.jenis_transaksi IS NULL OR t.jenis_transaksi != 'masuk')");
    $out = (float)(mysqli_fetch_assoc($q_out)['t_out'] ?? 0);

    // Hitung Masuk (Pembelian Selesai)
    $q_in = mysqli_query($conn, "SELECT SUM(td.qty) as t_in FROM transaksi_detail td JOIN transaksi t ON td.no_faktur = t.no_faktur WHERE td.barang_id='$id_brg' AND t.status='selesai' AND t.jenis_transaksi = 'masuk'");
    $in = (float)(mysqli_fetch_assoc($q_in)['t_in'] ?? 0);
    
    // Kalkulasi
    $stok_real = $in - $out;

    mysqli_query($conn, "UPDATE barang SET stok = '$stok_real' WHERE id='$id_brg'");
    echo "<script>alert('✨ AI SMART AUDIT:\\n\\nTotal Barang Masuk (Valid): $in\\nTotal Barang Keluar (Valid): $out\\n\\nStok master telah di-update menjadi: $stok_real'); window.location='index.php?page=audit_stok';</script>";
}

// =========================================================================
// QUERY ANOMALI AI
// =========================================================================
// 1. Order Bocor (Ditambahkan filter 'batal' agar jika status diubah batal, hilang dari list)
$q_bocor = mysqli_query($conn, "SELECT p.no_pesanan, p.tanggal, p.nama_pelanggan, p.status FROM pesanan p JOIN transaksi t ON p.no_pesanan = t.no_faktur WHERE p.id_usaha = '$id_usaha' AND p.tanggal >= '$cutoff_date' AND p.status IN ('Pending', 'Persiapan', 'Pengiriman', 'Batal') AND t.status != 'batal'");
$jml_bocor = mysqli_num_rows($q_bocor);

// 2. Order Menggantung
$q_gantung = mysqli_query($conn, "SELECT p.no_pesanan, p.tanggal, p.nama_pelanggan, p.status FROM pesanan p LEFT JOIN transaksi t ON p.no_pesanan = t.no_faktur WHERE p.id_usaha = '$id_usaha' AND p.tanggal >= '$cutoff_date' AND p.status = 'Selesai' AND t.no_faktur IS NULL");
$jml_gantung = mysqli_num_rows($q_gantung);

// 3. Barang Masuk Fiktif
$q_bm_ghost = mysqli_query($conn, "
    SELECT t.no_faktur, t.tanggal, s.nama_supplier 
    FROM transaksi t 
    LEFT JOIN transaksi_detail td ON t.no_faktur = td.no_faktur 
    LEFT JOIN supplier s ON t.supplier_id = s.id 
    WHERE t.id_usaha = '$id_usaha' AND t.jenis_transaksi = 'masuk' AND t.tanggal >= '$cutoff_date' AND td.id IS NULL
");
$jml_bm_ghost = mysqli_num_rows($q_bm_ghost);

// 4. Stok Minus
$q_minus = mysqli_query($conn, "SELECT id, kode_barang, nama_barang, stok, satuan FROM barang WHERE id_usaha = '$id_usaha' AND stok < 0");
$jml_minus = mysqli_num_rows($q_minus);
?>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-lg shadow-sm border-b-4 border-indigo-600">
        <div class="flex flex-col md:flex-row justify-between items-start gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-robot mr-2 text-indigo-600"></i> AI Audit & Anomali Gudang</h2>
                <p class="text-sm text-gray-500 mt-1">Sistem cerdas pendeteksi ketidaksesuaian status order, sinkronisasi stok, hingga histori transaksi yang janggal.</p>
            </div>
            <div class="text-xs bg-indigo-50 text-indigo-700 font-bold px-3 py-1.5 rounded border border-indigo-200 shadow-sm flex items-center">
                <i class="fa-solid fa-filter mr-2"></i> Filter AI: Memeriksa data mulai 20 Mei 2026
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-red-50 p-5 rounded-lg shadow-sm border border-red-200 relative overflow-hidden transition hover:shadow-md">
            <h3 class="text-red-800 font-bold mb-1 z-10 relative">Order Bocor</h3>
            <p class="text-[10px] text-red-600 mb-3 z-10 relative leading-tight">Order belum Selesai, stok sudah terpotong.</p>
            <div class="text-3xl font-extrabold text-red-600 z-10 relative"><?= $jml_bocor ?></div>
            <i class="fa-solid fa-box-open absolute -bottom-4 -right-4 text-6xl text-red-200 opacity-50 z-0"></i>
        </div>

        <div class="bg-yellow-50 p-5 rounded-lg shadow-sm border border-yellow-200 relative overflow-hidden transition hover:shadow-md">
            <h3 class="text-yellow-800 font-bold mb-1 z-10 relative">Order Menggantung</h3>
            <p class="text-[10px] text-yellow-600 mb-3 z-10 relative leading-tight">Order Selesai, stok belum terpotong.</p>
            <div class="text-3xl font-extrabold text-yellow-600 z-10 relative"><?= $jml_gantung ?></div>
            <i class="fa-solid fa-truck-ramp-box absolute -bottom-4 -right-4 text-6xl text-yellow-200 opacity-50 z-0"></i>
        </div>

        <div class="bg-orange-50 p-5 rounded-lg shadow-sm border border-orange-200 relative overflow-hidden transition hover:shadow-md">
            <h3 class="text-orange-800 font-bold mb-1 z-10 relative">Barang Masuk Fiktif</h3>
            <p class="text-[10px] text-orange-600 mb-3 z-10 relative leading-tight">Faktur ada, rincian barang kosong.</p>
            <div class="text-3xl font-extrabold text-orange-600 z-10 relative"><?= $jml_bm_ghost ?></div>
            <i class="fa-solid fa-file-circle-question absolute -bottom-4 -right-4 text-6xl text-orange-200 opacity-50 z-0"></i>
        </div>

        <div class="bg-purple-50 p-5 rounded-lg shadow-sm border border-purple-200 relative overflow-hidden transition hover:shadow-md">
            <h3 class="text-purple-800 font-bold mb-1 z-10 relative">Stok Minus (Error)</h3>
            <p class="text-[10px] text-purple-600 mb-3 z-10 relative leading-tight">Barang di gudang bernilai minus.</p>
            <div class="text-3xl font-extrabold text-purple-600 z-10 relative"><?= $jml_minus ?></div>
            <i class="fa-solid fa-arrow-trend-down absolute -bottom-4 -right-4 text-6xl text-purple-200 opacity-50 z-0"></i>
        </div>
    </div>

    <?php if($jml_minus > 0): ?>
    <div class="bg-white rounded-lg shadow-sm border border-purple-200 overflow-hidden animate-fade-in-up">
        <div class="bg-purple-600 p-4 text-white font-bold flex items-center gap-2">
            <i class="fa-solid fa-arrow-trend-down"></i> Daftar Barang Stok Minus (Perlu Sinkronisasi)
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left border">
                <thead class="bg-purple-50 text-purple-800">
                    <tr>
                        <th class="p-3 border">Kode</th>
                        <th class="p-3 border">Nama Barang</th>
                        <th class="p-3 border text-center">Stok Fisik Sistem</th>
                        <th class="p-3 border text-center">Aksi / Solusi Cerdas AI</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while($row = mysqli_fetch_assoc($q_minus)): ?>
                    <tr class="hover:bg-purple-50">
                        <td class="p-3 border font-mono text-gray-500"><?= $row['kode_barang'] ?></td>
                        <td class="p-3 border font-bold text-gray-800"><?= $row['nama_barang'] ?></td>
                        <td class="p-3 border text-center text-red-600 font-extrabold text-lg">
                            <?= (float)$row['stok'] ?> <span class="text-xs font-normal"><?= $row['satuan'] ?></span>
                        </td>
                        <td class="p-3 border text-center w-[350px]">
                            <form method="POST" class="flex gap-2 justify-center">
                                <input type="hidden" name="id_barang" value="<?= $row['id'] ?>">
                                <button type="submit" name="magic_fix_minus" onclick="return confirm('Ubah paksa stok menjadi 0?')" class="bg-purple-600 text-white px-3 py-2 rounded font-bold hover:shadow transition text-xs w-full">
                                    <i class="fa-solid fa-eraser"></i> Paksa 0
                                </button>
                                <button type="submit" name="magic_fix_recalc" onclick="return confirm('Biarkan AI menghitung ulang total In/Out transaksi selesai untuk mencari stok aktual?')" class="bg-indigo-600 text-white px-3 py-2 rounded font-bold hover:shadow transition text-xs w-full">
                                    <i class="fa-solid fa-calculator"></i> Kalkulasi In/Out
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($jml_bocor > 0): ?>
    <div class="bg-white rounded-lg shadow-sm border border-red-200 overflow-hidden animate-fade-in-up">
        <div class="bg-red-600 p-4 text-white font-bold flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i> Daftar Order Bocor (Kelolosan Sistem)
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left border">
                <thead class="bg-red-50 text-red-800">
                    <tr>
                        <th class="p-3 border">No Pesanan</th>
                        <th class="p-3 border">Tanggal</th>
                        <th class="p-3 border text-center">Status</th>
                        <th class="p-3 border text-center">Aksi / Solusi AI (Aman)</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while($row = mysqli_fetch_assoc($q_bocor)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 border font-mono font-bold text-indigo-700"><?= $row['no_pesanan'] ?></td>
                        <td class="p-3 border"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                        <td class="p-3 border text-center">
                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= $row['status'] ?></span>
                        </td>
                        <td class="p-3 border text-center w-[350px]">
                            <form method="POST" class="flex gap-2 justify-center">
                                <input type="hidden" name="no_pesanan" value="<?= $row['no_pesanan'] ?>">
                                <button type="submit" name="magic_fix_bocor_restore" onclick="return confirm('Kembalikan stok ke gudang? (Invoice tetap aman, tidak dihapus)')" class="bg-red-600 text-white px-3 py-2 rounded font-bold hover:shadow transition text-xs w-full" title="Kembalikan stok yang terlanjur terpotong">
                                    <i class="fa-solid fa-rotate-left"></i> Restore Stok
                                </button>
                                <button type="submit" name="magic_fix_bocor_netral" onclick="return confirm('Paksa sinkron menjadi Selesai? (Invoice tetap aman, tidak dihapus)')" class="bg-green-600 text-white px-3 py-2 rounded font-bold hover:shadow transition text-xs w-full" title="Ubah status jadi selesai agar klop">
                                    <i class="fa-solid fa-check-double"></i> Jadikan Selesai
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($jml_gantung > 0): ?>
    <div class="bg-white rounded-lg shadow-sm border border-yellow-200 overflow-hidden animate-fade-in-up">
        <div class="bg-yellow-500 p-4 text-white font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> Daftar Order Menggantung (Perlu Ditarik)
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left border">
                <thead class="bg-yellow-50 text-yellow-800">
                    <tr>
                        <th class="p-3 border">No Pesanan</th>
                        <th class="p-3 border">Tanggal</th>
                        <th class="p-3 border text-center">Status</th>
                        <th class="p-3 border text-center">Aksi / Solusi AI</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while($row = mysqli_fetch_assoc($q_gantung)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 border font-mono font-bold text-indigo-700"><?= $row['no_pesanan'] ?></td>
                        <td class="p-3 border"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                        <td class="p-3 border text-center">
                            <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold uppercase"><?= $row['status'] ?></span>
                        </td>
                        <td class="p-3 border text-center w-64">
                            <form method="POST" onsubmit="return confirm('Biarkan AI memulihkan status orderan ini ke Pengiriman agar bisa diproses Selesai?')">
                                <input type="hidden" name="no_pesanan" value="<?= $row['no_pesanan'] ?>">
                                <button type="submit" name="magic_fix_gantung" class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-4 py-2 rounded font-bold hover:shadow-lg transition flex items-center justify-center w-full gap-2 text-xs">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Fix: Reset ke Pengiriman
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($jml_bm_ghost > 0): ?>
    <div class="bg-white rounded-lg shadow-sm border border-orange-200 overflow-hidden animate-fade-in-up">
        <div class="bg-orange-500 p-4 text-white font-bold flex items-center gap-2">
            <i class="fa-solid fa-file-circle-question"></i> Daftar Barang Masuk Fiktif (Ghost Transaction)
        </div>
        <div class="p-4 overflow-x-auto">
            <table class="w-full text-sm text-left border">
                <thead class="bg-orange-50 text-orange-800">
                    <tr>
                        <th class="p-3 border">No Faktur</th>
                        <th class="p-3 border">Tanggal Masuk</th>
                        <th class="p-3 border">Supplier</th>
                        <th class="p-3 border text-center">Aksi / Solusi AI</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while($row = mysqli_fetch_assoc($q_bm_ghost)): ?>
                    <tr class="hover:bg-orange-50">
                        <td class="p-3 border font-mono font-bold text-indigo-700"><?= $row['no_faktur'] ?></td>
                        <td class="p-3 border"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                        <td class="p-3 border font-bold text-gray-700"><?= $row['nama_supplier'] ?? 'Tanpa Supplier' ?></td>
                        <td class="p-3 border text-center w-64">
                            <form method="POST" onsubmit="return confirm('Hapus histori Barang Masuk yang kosong/fiktif ini?')">
                                <input type="hidden" name="no_faktur" value="<?= $row['no_faktur'] ?>">
                                <button type="submit" name="magic_fix_bm_ghost" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-4 py-2 rounded font-bold hover:shadow-lg transition flex items-center justify-center w-full gap-2 text-xs">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Fix: Hapus Data Fiktif
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($jml_bocor == 0 && $jml_gantung == 0 && $jml_bm_ghost == 0 && $jml_minus == 0): ?>
    <div class="bg-green-50 border border-green-200 p-10 rounded-xl text-center mt-6 shadow-sm animate-fade-in-up">
        <div class="inline-block bg-green-100 p-4 rounded-full mb-4 ring-8 ring-green-50">
            <i class="fa-solid fa-shield-check text-5xl text-green-500"></i>
        </div>
        <h3 class="text-2xl font-bold text-green-800">Sistem 100% Sehat & Tersinkronisasi!</h3>
        <p class="text-green-600 mt-2 font-medium">AI tidak menemukan adanya stok minus, barang masuk fiktif, maupun order bocor.</p>
    </div>
    <?php endif; ?>
</div>