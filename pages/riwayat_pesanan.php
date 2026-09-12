<?php
wajib_akses('riwayat_pesanan');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';
$user_id = $_SESSION['user_id'];

// =========================================================
// 1. LOGIKA HAPUS SATUAN ITEM (MANUAL)
// =========================================================
if (isset($_GET['hapus_item_id'])) {
    $id_det = mysqli_real_escape_string($conn, $_GET['hapus_item_id']);
    
    // Ambil info item (ID Pesanan dan Subtotal) sebelum dihapus
    $q_info = mysqli_query($conn, "SELECT id_pesanan, subtotal FROM pesanan_detail WHERE id='$id_det'");
    $d_info = mysqli_fetch_assoc($q_info);
    
    if ($d_info) {
        $id_p = $d_info['id_pesanan'];
        $sub  = $d_info['subtotal'];
        
        // Keamanan: Cek apakah pesanan masih Pending dan milik user ini
        $cek_psn = mysqli_query($conn, "SELECT status FROM pesanan WHERE id='$id_p' AND user_id='$user_id'");
        $d_psn = mysqli_fetch_assoc($cek_psn);
        
        if ($d_psn && $d_psn['status'] == 'Pending') {
            // A. Hapus item dari detail
            mysqli_query($conn, "DELETE FROM pesanan_detail WHERE id='$id_det'");
            
            // B. Kurangi Total Bayar di Header Pesanan
            mysqli_query($conn, "UPDATE pesanan SET total_bayar = total_bayar - $sub WHERE id='$id_p'");
            
            // C. Jika item sudah habis total, hapus Header Pesanannya juga
            $cek_sisa = mysqli_query($conn, "SELECT id FROM pesanan_detail WHERE id_pesanan='$id_p'");
            if (mysqli_num_rows($cek_sisa) == 0) {
                mysqli_query($conn, "DELETE FROM pesanan WHERE id='$id_p'");
                echo "<script>alert('Semua item dihapus, pesanan dibatalkan otomatis.'); window.location='index.php?page=riwayat_pesanan';</script>";
            } else {
                echo "<script>alert('Item berhasil dihapus!'); window.location='index.php?page=riwayat_pesanan';</script>";
            }
        }
    }
}

// =========================================================
// 2. LOGIKA HAPUS SELURUH PESANAN
// =========================================================
if (isset($_GET['hapus_pesanan'])) {
    $id_pesanan = mysqli_real_escape_string($conn, $_GET['hapus_pesanan']);
    $cek = mysqli_query($conn, "SELECT id FROM pesanan WHERE id='$id_pesanan' AND user_id='$user_id' AND status='Pending'");
    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($conn, "DELETE FROM pesanan_detail WHERE id_pesanan='$id_pesanan'");
        mysqli_query($conn, "DELETE FROM pesanan WHERE id='$id_pesanan'");
        echo "<script>alert('Pesanan berhasil dibatalkan.'); window.location='index.php?page=riwayat_pesanan';</script>";
    }
}

// =========================================================
// 3. LOGIKA TERIMA PESANAN (UBAH STATUS JADI SELESAI)
// =========================================================
if (isset($_GET['terima_pesanan'])) {
    $id_pesanan = mysqli_real_escape_string($conn, $_GET['terima_pesanan']);
    
    // Cek Hak Akses: Pemilik Pesanan ATAU Driver Spesial (Gilang/Dzifki)
    $is_authorized = false;
    
    // 1. Cek Pemilik
    $cek_own = mysqli_query($conn, "SELECT id FROM pesanan WHERE id='$id_pesanan' AND user_id='$user_id'");
    if(mysqli_num_rows($cek_own) > 0) $is_authorized = true;

    // 2. Cek Driver Spesial
    $nama_user = strtolower($_SESSION['nama'] ?? '');
    $is_special_driver = ($_SESSION['role'] == 'driver' && in_array($nama_user, ['gilang', 'dzifki']));
    if($is_special_driver) $is_authorized = true;

    if ($is_authorized) {
        $update = mysqli_query($conn, "UPDATE pesanan SET status='Selesai' WHERE id='$id_pesanan'");
        if($update) {
            echo "<script>
                alert('Terima kasih! Pesanan telah diselesaikan.'); 
                window.location='index.php?page=riwayat_pesanan';
            </script>";
        } else {
            echo "<script>alert('Gagal mengupdate status pesanan.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Anda tidak memiliki akses untuk menyelesaikan pesanan ini.'); window.history.back();</script>";
    }
}

// =========================================================
// 4. EXPORT EXCEL (mengikuti filter yang sedang aktif)
// =========================================================
if (isset($_POST['export_excel'])) {
    while (ob_get_level()) { ob_end_clean(); }

    $ex_awal  = mysqli_real_escape_string($conn, $_POST['filter_tgl_awal'] ?? '');
    $ex_akhir = mysqli_real_escape_string($conn, $_POST['filter_tgl_akhir'] ?? '');

    $ex_where = "user_id = '$user_id'";
    if ($ex_awal !== '')  { $ex_where .= " AND DATE(tanggal) >= '$ex_awal'"; }
    if ($ex_akhir !== '') { $ex_where .= " AND DATE(tanggal) <= '$ex_akhir'"; }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Riwayat_Pesanan_Saya_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr style="background-color: #4F46E5; color: white;">
            <th>No</th>
            <th>No. Pesanan</th>
            <th>Tanggal</th>
            <th>Periode Order</th>
            <th>Status</th>
            <th>Total</th>
          </tr>';

    $q_ex = mysqli_query($conn, "SELECT * FROM pesanan WHERE $ex_where ORDER BY id DESC");

    $no = 1;
    while ($row = mysqli_fetch_assoc($q_ex)) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $row['no_pesanan'] . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . ($row['keterangan'] ?? '-') . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '<td>' . $row['total_bayar'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}
?>

<div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
    <h2 class="text-2xl font-bold text-slate-800 mb-6"><i class="fa-solid fa-clock-rotate-left mr-2 text-indigo-600"></i> Riwayat Pesanan Saya</h2>

    <?php
    $f_tgl_awal  = htmlspecialchars((string)($_GET['tgl_awal'] ?? ''), ENT_QUOTES, 'UTF-8');
    $f_tgl_akhir = htmlspecialchars((string)($_GET['tgl_akhir'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extra_tgl   = '<input type="date" name="tgl_awal" value="' . $f_tgl_awal . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Mulai">'
                 . '<input type="date" name="tgl_akhir" value="' . $f_tgl_akhir . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Akhir">';

    echo render_filter('Cari no pesanan / catatan...', $extra_tgl);
    ?>

    <div class="flex justify-end mb-4">
        <form method="POST">
            <input type="hidden" name="filter_tgl_awal" value="<?= $f_tgl_awal ?>">
            <input type="hidden" name="filter_tgl_akhir" value="<?= $f_tgl_akhir ?>">
            <button type="submit" name="export_excel" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 shadow">
                <i class="fa-solid fa-file-excel mr-2"></i>Rekap / Download Excel
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-slate-600 uppercase text-xs font-bold border-b">
                <tr>
                    <th class="p-4">No Pesanan</th>
                    <th class="p-4">Tanggal</th>
                    <th class="p-4">Periode Order</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-right">Total</th>
                    <th class="p-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                // Jika user adalah Driver Gilang/Dzifki, mungkin mereka perlu melihat semua orderan (opsional),
                // tapi sesuai kode asli, ini riwayat per user_id.
                // Fitur ini akan bekerja jika Driver login dan membuka halaman ini untuk pesanan mereka sendiri,
                // ATAU jika Anda mengubah query ini untuk menampilkan semua pesanan bagi driver.
                // Disini saya biarkan default (user_id) agar aman untuk pelanggan.

                $rp_cari  = ambil_kata_kunci();
                $rp_awal  = trim((string) ($_GET['tgl_awal'] ?? ''));
                $rp_akhir = trim((string) ($_GET['tgl_akhir'] ?? ''));
                $rp_hal   = ambil_halaman();
                $rp_limit = ambil_per_halaman();

                $rp_wt = ['user_id = ?'];
                $rp_pt = [$user_id];
                $rp_tt = 'i';

                if ($rp_awal !== '') {
                    $rp_wt[] = 'DATE(tanggal) >= ?';
                    $rp_pt[] = $rp_awal;
                    $rp_tt  .= 's';
                }
                if ($rp_akhir !== '') {
                    $rp_wt[] = 'DATE(tanggal) <= ?';
                    $rp_pt[] = $rp_akhir;
                    $rp_tt  .= 's';
                }

                [$rp_where, $rp_params, $rp_tipe] = bangun_filter(
                    $rp_cari, ['no_pesanan', 'keterangan'],
                    $rp_wt, $rp_pt, $rp_tt
                );

                $rp_total  = hitung_total($conn, 'pesanan', $rp_where, $rp_params, $rp_tipe);
                $rp_hal    = batasi_halaman($rp_hal, $rp_total, $rp_limit);
                $rp_offset = ($rp_hal - 1) * $rp_limit;

                $rp_rows = ambil_data($conn, 'SELECT * FROM pesanan',
                    $rp_where, $rp_params, $rp_tipe, 'ORDER BY id DESC', $rp_limit, $rp_offset);

                if (!$rp_rows) { echo render_kosong(6, 'Tidak ada pesanan yang cocok.'); }

                foreach ($rp_rows as $r):
                     $status_color = match($r['status']) {
                        'Pending' => 'bg-gray-100 text-gray-600',
                        'Persiapan' => 'bg-yellow-100 text-yellow-700',
                        'Pengiriman' => 'bg-blue-100 text-blue-700',
                        'Selesai' => 'bg-green-100 text-green-700',
                        default => 'bg-red-100 text-red-700'
                    };
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-4 font-bold text-indigo-700"><?= $r['no_pesanan'] ?></td>
                    <td class="p-4 text-slate-500"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></td>
                    <td class="p-4 italic text-blue-600 font-medium"><?= $r['keterangan'] ?? '-' ?></td>
                    <td class="p-4 text-center"><span class="px-3 py-1 rounded-full text-xs font-bold <?= $status_color ?>"><?= $r['status'] ?></span></td>
                    <td class="p-4 text-right font-bold text-slate-700">Rp <?= number_format($r['total_bayar']) ?></td>
                    <td class="p-4 text-center flex justify-center items-center gap-2">
                        <button onclick="lihatDetail(<?= $r['id'] ?>, '<?= $r['no_pesanan'] ?>')" class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded hover:bg-indigo-100 font-bold text-xs border border-indigo-100">Detail</button>

                        <?php if($r['status'] == 'Pending'): ?>
                            <a href="index.php?page=riwayat_pesanan&hapus_pesanan=<?= $r['id'] ?>" 
                               onclick="return confirm('Batalkan seluruh pesanan ini?')" 
                               class="bg-red-50 text-red-600 px-3 py-1 rounded hover:bg-red-100 font-bold text-xs border border-red-100">
                               Hapus
                            </a>
                        <?php endif; ?>

                        <?php if($r['status'] == 'Pengiriman'): ?>
                            <a href="index.php?page=riwayat_pesanan&terima_pesanan=<?= $r['id'] ?>" 
                               onclick="return confirm('Apakah pesanan sudah diterima dengan baik? Status akan berubah menjadi Selesai.')" 
                               class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 font-bold text-xs shadow-sm flex items-center gap-1">
                               <i class="fa-solid fa-check"></i> Diterima
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($rp_hal, $rp_total, $rp_limit) ?>
</div>

<div id="modalDetail" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800" id="titleNoPesanan">Detail Pesanan</h3>
            <button onclick="document.getElementById('modalDetail').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="p-4 overflow-y-auto max-h-[70vh]" id="kontenDetail"></div>
    </div>
</div>

<script>
function lihatDetail(id, no) {
    const modal = document.getElementById('modalDetail');
    const container = document.getElementById('kontenDetail');
    document.getElementById('titleNoPesanan').innerText = "Detail: " + no;

    modal.classList.remove('hidden');
    container.innerHTML = '<div class="p-10 text-center text-slate-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i><br>Memuat...</div>';

    let fd = new FormData();
    fd.append('get_detail_pesanan', true);
    fd.append('id', id);

    fetch('pages/ajax_pesanan.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(html => { container.innerHTML = html; })
    .catch(e => { container.innerHTML = 'Gagal memuat data.'; });
}
</script>