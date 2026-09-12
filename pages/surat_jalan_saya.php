<?php
// pages/surat_jalan_saya.php
wajib_akses('surat_jalan_saya');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';
$user_id = $_SESSION['user_id'];

// =========================================================
// EXPORT EXCEL (mengikuti filter yang sedang aktif)
// =========================================================
if (isset($_POST['export_excel'])) {
    while (ob_get_level()) { ob_end_clean(); }

    $ex_cari  = mysqli_real_escape_string($conn, $_POST['filter_cari'] ?? '');
    $ex_awal  = mysqli_real_escape_string($conn, $_POST['filter_tgl_awal'] ?? '');
    $ex_akhir = mysqli_real_escape_string($conn, $_POST['filter_tgl_akhir'] ?? '');

    $ex_where = "ps.user_id = '$user_id'";
    if ($ex_awal !== '')  { $ex_where .= " AND DATE(t.tanggal) >= '$ex_awal'"; }
    if ($ex_akhir !== '') { $ex_where .= " AND DATE(t.tanggal) <= '$ex_akhir'"; }
    if ($ex_cari !== '') {
        $ex_where .= " AND (t.no_faktur LIKE '%$ex_cari%' OR t.nama_driver LIKE '%$ex_cari%')";
    }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Surat_Jalan_Saya_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr style="background-color: #ea580c; color: white;">
            <th>No</th>
            <th>No. Surat Jalan</th>
            <th>Ref Faktur</th>
            <th>Tanggal</th>
            <th>Tujuan</th>
            <th>Driver</th>
            <th>Nopol</th>
          </tr>';

    $q_ex = mysqli_query($conn, "
        SELECT t.*, p.nama_pelanggan, p.alamat as alamat_pelanggan
        FROM transaksi t
        JOIN pesanan ps ON t.no_faktur = ps.no_pesanan
        LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
        WHERE t.jenis_transaksi = 'keluar' AND $ex_where
        ORDER BY t.tanggal DESC");

    $no = 1;
    while ($row = mysqli_fetch_assoc($q_ex)) {
        $no_sj = str_replace('TRX', 'SJ', $row['no_faktur']);
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $no_sj . '</td>';
        echo '<td>' . $row['no_faktur'] . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . ($row['nama_pelanggan'] ?: 'Pelanggan Umum') . '</td>';
        echo '<td>' . ($row['nama_driver'] ?: '-') . '</td>';
        echo '<td>' . ($row['nopol'] ?: '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}
?>
<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Surat Jalan Saya</h3>
            <p class="text-sm text-gray-500">Daftar surat jalan pengiriman untuk pesanan Anda</p>
        </div>
    </div>

    <?php
    $f_tgl_awal  = htmlspecialchars((string)($_GET['tgl_awal'] ?? ''), ENT_QUOTES, 'UTF-8');
    $f_tgl_akhir = htmlspecialchars((string)($_GET['tgl_akhir'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extra_tgl   = '<input type="date" name="tgl_awal" value="' . $f_tgl_awal . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Mulai">'
                 . '<input type="date" name="tgl_akhir" value="' . $f_tgl_akhir . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Akhir">';

    echo render_filter('Cari no surat jalan / driver...', $extra_tgl);
    ?>

    <div class="flex justify-end mb-4">
        <form method="POST">
            <input type="hidden" name="filter_cari" value="<?= htmlspecialchars(ambil_kata_kunci(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_tgl_awal" value="<?= $f_tgl_awal ?>">
            <input type="hidden" name="filter_tgl_akhir" value="<?= $f_tgl_akhir ?>">
            <button type="submit" name="export_excel" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 shadow">
                <i class="fa-solid fa-file-excel mr-2"></i>Rekap / Download Excel
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-orange-50 text-orange-800 uppercase font-bold text-xs">
                <tr>
                    <th class="p-3">No Surat Jalan</th>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3">Tujuan</th>
                    <th class="p-3">Info Pengiriman</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $sj_cari  = ambil_kata_kunci();
                $sj_awal  = trim((string) ($_GET['tgl_awal'] ?? ''));
                $sj_akhir = trim((string) ($_GET['tgl_akhir'] ?? ''));
                $sj_hal   = ambil_halaman();
                $sj_limit = ambil_per_halaman();

                $sj_wt = ["t.jenis_transaksi = 'keluar'", 'ps.user_id = ?'];
                $sj_pt = [$user_id];
                $sj_tt = 'i';

                if ($sj_awal !== '') {
                    $sj_wt[] = 'DATE(t.tanggal) >= ?';
                    $sj_pt[] = $sj_awal;
                    $sj_tt  .= 's';
                }
                if ($sj_akhir !== '') {
                    $sj_wt[] = 'DATE(t.tanggal) <= ?';
                    $sj_pt[] = $sj_akhir;
                    $sj_tt  .= 's';
                }

                [$sj_where, $sj_params, $sj_tipe] = bangun_filter(
                    $sj_cari, ['t.no_faktur', 't.nama_driver'],
                    $sj_wt, $sj_pt, $sj_tt
                );

                $sj_from = 'transaksi t JOIN pesanan ps ON t.no_faktur = ps.no_pesanan';

                $sj_total  = hitung_total($conn, $sj_from, $sj_where, $sj_params, $sj_tipe);
                $sj_hal    = batasi_halaman($sj_hal, $sj_total, $sj_limit);
                $sj_offset = ($sj_hal - 1) * $sj_limit;

                $sj_rows = ambil_data($conn,
                    "SELECT t.*, p.nama_pelanggan, p.alamat as alamat_pelanggan FROM $sj_from LEFT JOIN pelanggan p ON t.pelanggan_id = p.id",
                    $sj_where, $sj_params, $sj_tipe, 'ORDER BY t.tanggal DESC', $sj_limit, $sj_offset);

                if (!$sj_rows) { echo render_kosong(5, 'Tidak ada surat jalan yang cocok.'); }

                foreach ($sj_rows as $row):
                    $no_sj  = str_replace('TRX', 'SJ', $row['no_faktur']);
                    $driver = !empty($row['nama_driver']) ? $row['nama_driver'] : null;
                    $nopol  = !empty($row['nopol']) ? $row['nopol'] : null;
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3">
                        <div class="font-bold text-gray-800"><?= $no_sj ?></div>
                        <div class="text-[10px] text-gray-400">Ref: <?= $row['no_faktur'] ?></div>
                    </td>
                    <td class="p-3">
                        <div class="font-bold text-gray-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($row['tanggal'])) ?> WIB</div>
                    </td>
                    <td class="p-3">
                        <div class="font-bold text-gray-800"><?= $row['nama_pelanggan'] ?: 'Pelanggan Umum' ?></div>
                        <div class="text-xs text-gray-500 truncate w-48" title="<?= $row['alamat_pelanggan'] ?>">
                            <i class="fa-solid fa-location-dot mr-1"></i> <?= $row['alamat_pelanggan'] ?: '-' ?>
                        </div>
                    </td>
                    <td class="p-3">
                        <?php if ($driver): ?>
                            <div class="flex items-center gap-2">
                                <div class="bg-blue-100 text-blue-600 p-2 rounded-full h-8 w-8 flex items-center justify-center">
                                    <i class="fa-solid fa-truck"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-700 text-xs"><?= strtoupper($driver) ?></div>
                                    <div class="text-[10px] bg-gray-200 px-1 rounded inline-block text-gray-600 mt-0.5">
                                        <?= strtoupper($nopol) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-red-500 bg-red-50 px-2 py-1 rounded border border-red-100 flex items-center gap-1 w-fit">
                                <i class="fa-solid fa-triangle-exclamation"></i> Belum ada driver
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-center">
                        <a href="cetak_surat_jalan.php?no_faktur=<?= $row['no_faktur'] ?>" target="_blank"
                           class="bg-orange-500 text-white px-3 py-2 rounded text-xs font-bold hover:bg-orange-600 shadow-sm inline-flex items-center justify-center gap-2 transition">
                            <i class="fa-solid fa-print"></i> Cetak
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($sj_hal, $sj_total, $sj_limit) ?>
</div>
