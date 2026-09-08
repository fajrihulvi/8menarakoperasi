<?php
// ==========================================
// 1. CEK KEAMANAN (IZINKAN ADMIN, ACCOUNTING, PO, DAN INVOICE)
// ==========================================
// Perbaikan: Menambahkan 'invoice' ke dalam array izin akses
wajib_akses('keuangan');

// --- LOGIKA DATA TOKO (Agar Keuangan Terpisah) ---
$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1; 
// -------------------------------------------------

// ==========================================
// 2. HANDLE TAMBAH PENGELUARAN
// ==========================================
if(isset($_POST['tambah_pengeluaran'])) {
    $tgl  = $_POST['tanggal'] . ' ' . date('H:i:s');
    $ket  = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $jml  = str_replace('.', '', $_POST['jumlah']); // Hapus titik format ribuan
    $user = $_SESSION['user_id'];

    // Perbaikan: Menambahkan id_usaha saat insert agar data masuk ke toko yang benar
    $simpan = mysqli_query($conn, "INSERT INTO pengeluaran (tanggal, keterangan, jumlah, user_id, id_usaha) VALUES ('$tgl', '$ket', '$jml', '$user', '$id_usaha_aktif')");
    
    if($simpan) {
        echo "<script>alert('Pengeluaran berhasil disimpan!'); window.location='index.php?page=keuangan';</script>";
    } else {
        echo "<script>alert('Gagal simpan: ".mysqli_error($conn)."');</script>";
    }
}

// ==========================================
// 3. HANDLE HAPUS (HANYA ADMIN & ACCOUNTING)
// ==========================================
if(isset($_GET['hapus_id'])) {
    // Perbaikan: Role Invoice juga tidak boleh hapus data (hanya view/add)
    if(in_array($_SESSION['role'], ['po', 'invoice'])) {
        echo "<script>alert('Role Anda hanya bisa melihat dan input, tidak bisa menghapus data keuangan.'); window.location='index.php?page=keuangan';</script>";
    } else {
        // Perbaikan: Pastikan hanya menghapus data milik toko sendiri
        $id = $_GET['hapus_id'];
        mysqli_query($conn, "DELETE FROM pengeluaran WHERE id='$id' AND id_usaha='$id_usaha_aktif'");
        echo "<script>window.location='index.php?page=keuangan';</script>";
    }
}

// Filter Tanggal
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// HITUNG RINGKASAN
// 1. Total Penjualan (Pemasukan) - Perbaikan: Filter by id_usaha dan hanya yang Lunas
$q_masuk = mysqli_query($conn, "SELECT SUM(total_transaksi) as total FROM transaksi WHERE jenis_transaksi='keluar' AND status='selesai' AND status_bayar='lunas' AND id_usaha='$id_usaha_aktif' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'");
$d_masuk = mysqli_fetch_assoc($q_masuk);
$total_masuk = $d_masuk['total'] ?? 0;

// 2. Total Pengeluaran - Perbaikan: Filter by id_usaha
$q_keluar = mysqli_query($conn, "SELECT SUM(jumlah) as total FROM pengeluaran WHERE id_usaha='$id_usaha_aktif' AND DATE(tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'");
$d_keluar = mysqli_fetch_assoc($q_keluar);
$total_keluar = $d_keluar['total'] ?? 0;

// 3. Saldo
$saldo = $total_masuk - $total_keluar;
?>

<div class="space-y-6">
    
    <div class="bg-white p-6 rounded-lg shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-wallet mr-2 text-indigo-600"></i>Laporan Keuangan</h2>
            <p class="text-sm text-gray-500">Ringkasan Arus Kas (Penjualan Lunas vs Pengeluaran)</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page" value="keuangan">
            <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="border p-2 rounded text-sm">
            <span class="text-gray-400">-</span>
            <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="border p-2 rounded text-sm">
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-indigo-700">Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border-b-4 border-green-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Total Penjualan Lunas</p>
                    <h3 class="text-2xl font-bold text-green-600 mt-1">Rp <?= number_format($total_masuk, 0, ',', '.') ?></h3>
                </div>
                <div class="p-2 bg-green-50 rounded text-green-600"><i class="fa-solid fa-arrow-trend-up"></i></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border-b-4 border-red-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Total Pengeluaran</p>
                    <h3 class="text-2xl font-bold text-red-600 mt-1">Rp <?= number_format($total_keluar, 0, ',', '.') ?></h3>
                </div>
                <div class="p-2 bg-red-50 rounded text-red-600"><i class="fa-solid fa-arrow-trend-down"></i></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border-b-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Saldo Bersih</p>
                    <h3 class="text-2xl font-bold text-blue-600 mt-1">Rp <?= number_format($saldo, 0, ',', '.') ?></h3>
                </div>
                <div class="p-2 bg-blue-50 rounded text-blue-600"><i class="fa-solid fa-coins"></i></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-gray-700">Rincian Pengeluaran Operasional</h3>
            <button onclick="document.getElementById('modalPengeluaran').classList.remove('hidden')" class="bg-red-500 text-white px-3 py-2 rounded text-xs font-bold hover:bg-red-600">
                <i class="fa-solid fa-plus mr-1"></i> Catat Pengeluaran
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase font-bold text-xs">
                    <tr>
                        <th class="p-3 text-center w-10">No</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Keterangan</th>
                        <th class="p-3 text-right">Jumlah</th>
                        <th class="p-3 text-center">User</th>
                        <th class="p-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    $no = 1;
                    // Perbaikan: Menambahkan Filter id_usaha agar data tidak tercampur antar toko
                    $query = mysqli_query($conn, "
                        SELECT p.*, u.nama_lengkap 
                        FROM pengeluaran p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.id_usaha='$id_usaha_aktif' AND DATE(p.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir' 
                        ORDER BY p.tanggal DESC
                    ");

                    if(!$query) {
                        echo "<tr><td colspan='6' class='p-4 text-center text-red-500'>Tabel 'pengeluaran' belum dibuat di database!</td></tr>";
                    } else {
                        while($row = mysqli_fetch_assoc($query)):
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center"><?= $no++ ?></td>
                        <td class="p-3"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                        <td class="p-3 font-medium"><?= $row['keterangan'] ?></td>
                        <td class="p-3 text-right font-bold text-red-600">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                        <td class="p-3 text-center text-xs text-gray-500"><?= $row['nama_lengkap'] ?: 'Admin' ?></td>
                        <td class="p-3 text-center">
                            <?php if(!in_array($_SESSION['role'], ['po', 'invoice'])): ?>
                            <a href="index.php?page=keuangan&hapus_id=<?= $row['id'] ?>" onclick="return confirm('Hapus pengeluaran ini?')" class="text-red-500 hover:text-red-700">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-gray-300"><i class="fa-solid fa-lock"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; } ?>
                    
                    <?php if($query && mysqli_num_rows($query) == 0): ?>
                    <tr>
                        <td colspan="6" class="p-6 text-center text-gray-400">Belum ada data pengeluaran pada periode ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalPengeluaran" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative">
        <button onclick="document.getElementById('modalPengeluaran').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
        
        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Catat Pengeluaran Baru</h3>
        
        <form method="POST">
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-600 mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded focus:outline-indigo-500" required>
            </div>
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-600 mb-1">Keterangan Biaya</label>
                <input type="text" name="keterangan" class="w-full border p-2 rounded focus:outline-indigo-500" placeholder="Contoh: Beli Bensin, Listrik, Makan Siang" required>
            </div>
            
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-600 mb-1">Jumlah (Rp)</label>
                <input type="number" name="jumlah" class="w-full border p-2 rounded focus:outline-indigo-500 font-bold text-lg text-red-600" placeholder="0" required>
            </div>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalPengeluaran').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 font-bold text-sm">Batal</button>
                <button type="submit" name="tambah_pengeluaran" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 font-bold text-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>