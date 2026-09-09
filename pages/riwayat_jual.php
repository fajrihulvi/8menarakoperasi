<?php
wajib_akses('riwayat_jual');

// Pastikan session aktif
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';

$role = $_SESSION['role'] ?? '';
$id_usaha = $_SESSION['id_usaha'] ?? 0;

// =================================================================================
// [BARU] LOGIKA UBAH STATUS ORDER & SINKRONISASI STOK
// =================================================================================
if(isset($_POST['ubah_status_order'])) {
    if(in_array($role, ['admin', 'invoice', 'accounting', 'po'])) {
        $id_transaksi = mysqli_real_escape_string($conn, $_POST['id_transaksi_order']);
        $no_faktur    = mysqli_real_escape_string($conn, $_POST['no_faktur_order']);
        $status_baru  = strtolower(trim($_POST['status_order_baru']));

        // 1. Cek status lama
        $q_cek = mysqli_query($conn, "SELECT status FROM transaksi WHERE no_faktur='$no_faktur' AND id_usaha='$id_usaha'");
        if($q_cek && mysqli_num_rows($q_cek) > 0) {
            $trx = mysqli_fetch_assoc($q_cek);
            $status_lama = strtolower(trim($trx['status']));

            // Proses hanya jika statusnya benar-benar diubah
            if($status_lama !== $status_baru) {
                // 2. Update Status Transaksi Utama
                mysqli_query($conn, "UPDATE transaksi SET status='$status_baru' WHERE no_faktur='$no_faktur' AND id_usaha='$id_usaha'");

                // 3. SINKRONISASI STOK GUDANG
                $q_detail = mysqli_query($conn, "SELECT barang_id, qty FROM transaksi_detail WHERE no_faktur='$no_faktur'");
                while($d = mysqli_fetch_assoc($q_detail)) {
                    $id_brg = $d['barang_id'];
                    $qty = (float)$d['qty'];

                    // A. Jika diubah menjadi SELESAI -> POTONG STOK
                    if ($status_baru === 'selesai' && $status_lama !== 'selesai') {
                        mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id='$id_brg'");
                    }
                    // B. Jika DARI SELESAI menjadi yang lain (Batal/Pending) -> KEMBALIKAN STOK
                    elseif ($status_lama === 'selesai' && $status_baru !== 'selesai') {
                        mysqli_query($conn, "UPDATE barang SET stok = stok + $qty WHERE id='$id_brg'");
                    }
                }

                // 4. Sinkronisasi ganda ke tabel pesanan (Jika order berasal dari pelanggan)
                mysqli_query($conn, "UPDATE pesanan SET status='$status_baru' WHERE no_pesanan='$no_faktur'");

                if(function_exists('catat_log')) {
                    catat_log($conn, 'Update Status Order', "Ubah status faktur $no_faktur dari ".strtoupper($status_lama)." menjadi ".strtoupper($status_baru));
                }

                echo "<script>alert('Status Order berhasil diubah menjadi ".strtoupper($status_baru)." dan stok telah disinkronkan!'); window.location='index.php?page=riwayat_jual';</script>";
            }
        }
    } else {
        echo "<script>alert('Akses ditolak!');</script>";
    }
}

// =================================================================================
// LOGIKA UBAH STATUS PEMBAYARAN (LUNAS / BELUM LUNAS)
// =================================================================================
if(isset($_POST['ubah_status_bayar'])) {
    if(in_array($role, ['admin', 'invoice', 'accounting'])) {
        $id_transaksi = mysqli_real_escape_string($conn, $_POST['id_transaksi']);
        $status_baru  = mysqli_real_escape_string($conn, $_POST['status_bayar_baru']);
        $no_faktur    = mysqli_real_escape_string($conn, $_POST['no_faktur_bayar']);
        
        $update = mysqli_query($conn, "UPDATE transaksi SET status_bayar = '$status_baru' WHERE id = '$id_transaksi' AND id_usaha = '$id_usaha'");
        
        if($update) {
            if(function_exists('catat_log')) {
                catat_log($conn, 'Update Lunas', "Ubah status bayar invoice $no_faktur menjadi $status_baru");
            }
            echo "<script>alert('Status pembayaran berhasil diubah!'); window.location='index.php?page=riwayat_jual';</script>";
        } else {
            echo "<script>alert('Gagal mengubah status pembayaran.');</script>";
        }
    } else {
        echo "<script>alert('Akses ditolak!');</script>";
    }
}

// =================================================================================
// LOGIKA HAPUS TRANSAKSI (DENGAN PENCATATAN KE DASHBOARD)
// =================================================================================
if(isset($_POST['hapus_transaksi'])) {
    if($role == 'admin') {
        $id_hapus = $_POST['id_hapus'];
        
        // 1. Ambil No Faktur dulu sebelum dihapus (untuk dicatat di log)
        $cek = mysqli_query($conn, "SELECT no_faktur, total_transaksi FROM transaksi WHERE id='$id_hapus' AND id_usaha='$id_usaha'");
        
        if(mysqli_num_rows($cek) > 0) {
            $data = mysqli_fetch_assoc($cek);
            $faktur_hapus = $data['no_faktur'];
            $total_hapus  = number_format($data['total_transaksi']);

            // 2. Hapus Detail Barang & Transaksi Utama
            mysqli_query($conn, "DELETE FROM transaksi_detail WHERE no_faktur='$faktur_hapus'");
            $hapus = mysqli_query($conn, "DELETE FROM transaksi WHERE id='$id_hapus'");

            if($hapus) {
                // --- [PENTING] CATAT AKTIVITAS KE DASHBOARD ---
                if(function_exists('catat_log')){
                    $detail_log = "Menghapus Riwayat Penjualan No: $faktur_hapus (Rp $total_hapus)";
                    catat_log($conn, 'Hapus Data', $detail_log);
                }

                echo "<script>
                        alert('Data Berhasil Dihapus dan Tercatat di Log Dashboard!'); 
                        window.location='index.php?page=riwayat_jual';
                      </script>";
            } else {
                echo "<script>alert('Gagal menghapus data.');</script>";
            }
        }
    } else {
        echo "<script>alert('AKSES DITOLAK: Hanya Admin yang boleh menghapus.');</script>";
    }
}

// =================================================================================
// EXPORT EXCEL (mengikuti filter yang sedang aktif)
// =================================================================================
if(isset($_POST['export_excel'])) {
    while (ob_get_level()) { ob_end_clean(); }

    $ex_cari  = mysqli_real_escape_string($conn, $_POST['filter_cari'] ?? '');
    $ex_stat  = mysqli_real_escape_string($conn, $_POST['filter_status_bayar'] ?? '');
    $ex_awal  = mysqli_real_escape_string($conn, $_POST['filter_tgl_awal'] ?? '');
    $ex_akhir = mysqli_real_escape_string($conn, $_POST['filter_tgl_akhir'] ?? '');

    $ex_where = "t.jenis_transaksi = 'keluar' AND t.id_usaha = '$id_usaha'";
    if ($ex_stat === 'lunas')  { $ex_where .= " AND LOWER(TRIM(COALESCE(t.status_bayar,'belum'))) = 'lunas'"; }
    elseif ($ex_stat === 'belum') { $ex_where .= " AND LOWER(TRIM(COALESCE(t.status_bayar,'belum'))) <> 'lunas'"; }
    if ($ex_awal !== '')  { $ex_where .= " AND DATE(t.tanggal) >= '$ex_awal'"; }
    if ($ex_akhir !== '') { $ex_where .= " AND DATE(t.tanggal) <= '$ex_akhir'"; }
    if ($ex_cari !== '') {
        $ex_where .= " AND (t.no_faktur LIKE '%$ex_cari%' OR t.nama_driver LIKE '%$ex_cari%' OR t.lokasi_kirim LIKE '%$ex_cari%')";
    }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Rekap_Riwayat_Penjualan_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr style="background-color: #4F46E5; color: white;">
            <th>No</th>
            <th>No. Faktur</th>
            <th>Tanggal</th>
            <th>Kasir</th>
            <th>Total Belanja</th>
            <th>Status Order</th>
            <th>Status Bayar</th>
            <th>Driver</th>
          </tr>';

    $q_ex = mysqli_query($conn, "
        SELECT t.*, u.nama_lengkap
        FROM transaksi t LEFT JOIN users u ON t.user_id = u.id
        WHERE $ex_where ORDER BY t.tanggal DESC");

    $no = 1;
    while($row = mysqli_fetch_assoc($q_ex)) {
        $status_bayar_ex = strtolower(trim($row['status_bayar'] ?? 'belum')) === 'lunas' ? 'Lunas' : 'Belum Lunas';

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $row['no_faktur'] . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . ($row['nama_lengkap'] ?: 'Admin') . '</td>';
        echo '<td>' . $row['total_transaksi'] . '</td>';
        echo '<td>' . ucfirst($row['status']) . '</td>';
        echo '<td>' . $status_bayar_ex . '</td>';
        echo '<td>' . ($row['nama_driver'] ?: '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}
?>

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Riwayat Penjualan & Tagihan</h3>
            <p class="text-sm text-gray-500 mt-1">Data penjualan hanya akan masuk Laporan Pendapatan (Omset) bila statusnya sudah dicentang <b>LUNAS</b> dan order <b>SELESAI</b>.</p>
        </div>
    </div>

    <?php
    $rs = trim((string)($_GET['status_bayar'] ?? ''));
    $sel_bayar = '<select name="status_bayar" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">'
        . '<option value="">-- Semua Status --</option>'
        . '<option value="lunas"' . ($rs==='lunas'?' selected':'') . '>Lunas</option>'
        . '<option value="belum"' . ($rs==='belum'?' selected':'') . '>Belum Lunas</option></select>';

    $f_tgl_awal  = htmlspecialchars((string)($_GET['tgl_awal'] ?? ''), ENT_QUOTES, 'UTF-8');
    $f_tgl_akhir = htmlspecialchars((string)($_GET['tgl_akhir'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extra_tgl   = '<input type="date" name="tgl_awal" value="' . $f_tgl_awal . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Mulai">'
                 . '<input type="date" name="tgl_akhir" value="' . $f_tgl_akhir . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Akhir">';

    echo render_filter('Cari no faktur / driver / lokasi...', $sel_bayar . $extra_tgl);
    ?>

    <div class="flex justify-end mb-4">
        <form method="POST">
            <input type="hidden" name="filter_cari" value="<?= htmlspecialchars(ambil_kata_kunci(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_status_bayar" value="<?= htmlspecialchars($rs, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_tgl_awal" value="<?= $f_tgl_awal ?>">
            <input type="hidden" name="filter_tgl_akhir" value="<?= $f_tgl_akhir ?>">
            <button type="submit" name="export_excel" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 shadow">
                <i class="fa-solid fa-file-excel mr-2"></i>Rekap / Download Excel
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700 uppercase">
                <tr>
                    <th class="p-3">No Faktur</th>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3">Total Belanja</th>
                    <th class="p-3 text-center">Status Order</th>
                    <th class="p-3 text-center">Status Lunas</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                // ==========================================================
                // PAGINASI & FILTER SISI SERVER
                // Sebelumnya SELURUH riwayat transaksi dimuat sekaligus.
                // ==========================================================
                $r_cari  = ambil_kata_kunci();
                $r_stat  = trim((string) ($_GET['status_bayar'] ?? ''));
                $r_awal  = trim((string) ($_GET['tgl_awal'] ?? ''));
                $r_akhir = trim((string) ($_GET['tgl_akhir'] ?? ''));
                $r_hal   = ambil_halaman();
                $r_limit = ambil_per_halaman();

                $r_wt = ["t.jenis_transaksi = 'keluar'", 't.id_usaha = ?'];
                $r_pt = [$id_usaha];
                $r_tt = 'i';

                if ($r_stat === 'lunas') {
                    $r_wt[] = "LOWER(TRIM(COALESCE(t.status_bayar,'belum'))) = 'lunas'";
                } elseif ($r_stat === 'belum') {
                    $r_wt[] = "LOWER(TRIM(COALESCE(t.status_bayar,'belum'))) <> 'lunas'";
                }
                if ($r_awal !== '') {
                    $r_wt[] = 'DATE(t.tanggal) >= ?';
                    $r_pt[] = $r_awal;
                    $r_tt  .= 's';
                }
                if ($r_akhir !== '') {
                    $r_wt[] = 'DATE(t.tanggal) <= ?';
                    $r_pt[] = $r_akhir;
                    $r_tt  .= 's';
                }

                [$r_where, $r_params, $r_tipe] = bangun_filter(
                    $r_cari, ['t.no_faktur', 't.nama_driver', 't.lokasi_kirim'],
                    $r_wt, $r_pt, $r_tt
                );

                $r_total  = hitung_total($conn, 'transaksi t', $r_where, $r_params, $r_tipe);
                $r_hal    = batasi_halaman($r_hal, $r_total, $r_limit);
                $r_offset = ($r_hal - 1) * $r_limit;

                $r_rows = ambil_data($conn,
                    'SELECT t.*, u.nama_lengkap FROM transaksi t LEFT JOIN users u ON t.user_id = u.id',
                    $r_where, $r_params, $r_tipe, 'ORDER BY t.tanggal DESC', $r_limit, $r_offset);

                if (!$r_rows) { echo render_kosong(6, 'Tidak ada transaksi yang cocok.'); }

                foreach ($r_rows as $row):
                    $bayar = isset($row['bayar']) && $row['bayar'] > 0 ? $row['bayar'] : $row['total_transaksi'];
                    $status_bayar_db = strtolower(trim($row['status_bayar'] ?? 'belum'));
                    $is_lunas = ($status_bayar_db == 'lunas');
                    
                    // Warna lencana status order
                    $status_order_lower = strtolower(trim($row['status']));
                    $color_order = 'bg-gray-200 text-gray-700';
                    if($status_order_lower == 'selesai') $color_order = 'bg-green-100 text-green-700';
                    elseif($status_order_lower == 'batal') $color_order = 'bg-red-100 text-red-700';
                    elseif($status_order_lower == 'pengiriman') $color_order = 'bg-blue-100 text-blue-700';
                    elseif($status_order_lower == 'persiapan') $color_order = 'bg-yellow-100 text-yellow-700';
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3">
                        <div class="font-bold text-indigo-600"><?= $row['no_faktur'] ?></div>
                        <div class="text-[10px] text-gray-500">Kasir: <?= $row['nama_lengkap'] ?: 'Admin' ?></div>
                    </td>
                    <td class="p-3"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td class="p-3 font-bold">Rp <?= number_format($row['total_transaksi'], 0, ',', '.') ?></td>
                    
                    <td class="p-3 text-center">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="id_transaksi_order" value="<?= $row['id'] ?>">
                            <input type="hidden" name="no_faktur_order" value="<?= $row['no_faktur'] ?>">
                            <input type="hidden" name="ubah_status_order" value="1">
                            
                            <select name="status_order_baru" onchange="if(confirm('Yakin ingin mengubah status order dan menyinkronkan ulang stok?')) this.form.submit(); else this.value='<?= $status_order_lower ?>';" 
                                class="px-2 py-1 rounded text-[10px] font-bold uppercase cursor-pointer border-0 outline-none text-center appearance-none shadow-sm transition hover:scale-105 <?= $color_order ?>">
                                <option value="pending" <?= $status_order_lower == 'pending' ? 'selected' : '' ?> class="bg-white text-gray-700">PENDING</option>
                                <option value="persiapan" <?= $status_order_lower == 'persiapan' ? 'selected' : '' ?> class="bg-white text-gray-700">PERSIAPAN</option>
                                <option value="pengiriman" <?= $status_order_lower == 'pengiriman' ? 'selected' : '' ?> class="bg-white text-gray-700">PENGIRIMAN</option>
                                <option value="selesai" <?= $status_order_lower == 'selesai' ? 'selected' : '' ?> class="bg-white text-gray-700">SELESAI</option>
                                <option value="batal" <?= $status_order_lower == 'batal' ? 'selected' : '' ?> class="bg-white text-gray-700">BATAL</option>
                            </select>
                        </form>
                    </td>

                    <td class="p-3 text-center">
                        <form method="POST" class="inline-block" onsubmit="return confirm('Ubah status pembayaran invoice ini?');">
                            <input type="hidden" name="id_transaksi" value="<?= $row['id'] ?>">
                            <input type="hidden" name="no_faktur_bayar" value="<?= $row['no_faktur'] ?>">
                            
                            <?php if($is_lunas): ?>
                                <input type="hidden" name="status_bayar_baru" value="belum">
                                <button type="submit" name="ubah_status_bayar" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-full text-xs font-bold transition flex items-center gap-1 mx-auto" title="Klik untuk membatalkan status LUNAS">
                                    <i class="fa-solid fa-check-circle"></i> LUNAS
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="status_bayar_baru" value="lunas">
                                <button type="submit" name="ubah_status_bayar" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold transition flex items-center gap-1 mx-auto" title="Klik untuk mengubah menjadi LUNAS">
                                    <i class="fa-solid fa-clock"></i> BELUM LUNAS
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                    
                    <td class="p-3 text-center flex justify-center items-center gap-2">
                        
                        <?php if($role == 'admin'): ?>
                        <a href="index.php?page=edit_invoice&no_faktur=<?= $row['no_faktur'] ?>" 
                           class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-xs flex items-center gap-1"
                           title="Edit Harga/Qty">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </a>
                        <?php endif; ?>

                        <a href="cetak_struk.php?faktur=<?= $row['no_faktur'] ?>" target="_blank" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 text-xs" title="Cetak Struk">
                            <i class="fa-solid fa-print"></i>
                        </a>

                        <?php if($status_order_lower == 'selesai'): ?>
                            <a href="cetak_invoice.php?no_faktur=<?= $row['no_faktur'] ?>" target="_blank" class="bg-purple-600 text-white px-3 py-1 rounded hover:bg-purple-700 text-xs" title="Cetak Invoice A4">
                                <i class="fa-solid fa-file-invoice"></i>
                            </a>
                        <?php else: ?>
                            <button onclick="alert('Pesanan belum Selesai. Invoice belum bisa dicetak.')" class="bg-gray-400 text-white px-3 py-1 rounded cursor-not-allowed text-xs" title="Cetak Invoice (Terkunci)">
                                <i class="fa-solid fa-file-invoice"></i>
                            </button>
                        <?php endif; ?>

                        <button onclick="lihatDetail('<?= $row['no_faktur'] ?>')" class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 text-xs" title="Lihat Detail">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <?php if($role == 'admin'): ?>
                        <form method="POST" onsubmit="return confirm('⚠️ PERINGATAN!!\n\nApakah Anda yakin ingin menghapus data ini?\n\nTindakan ini akan dicatat di Log Aktivitas Dashboard.');" style="display:inline;">
                            <input type="hidden" name="id_hapus" value="<?= $row['id'] ?>">
                            <button type="submit" name="hapus_transaksi" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-xs" title="Hapus Permanen">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($r_hal, $r_total, $r_limit) ?>
</div>

<div id="modalDetail" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50 transition-opacity">
    <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-xl relative transform transition-all">
        <button onclick="document.getElementById('modalDetail').classList.add('hidden')" class="absolute top-4 right-4 text-gray-500 hover:text-red-500">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
            <i class="fa-solid fa-receipt text-indigo-500"></i> Detail Transaksi: <span id="detailFaktur" class="text-indigo-600"></span>
        </h3>
        <div id="isiDetail" class="overflow-y-auto max-h-96 border-t border-b py-2">
            <p class="text-center text-gray-500">Memuat data...</p>
        </div>
        <div class="mt-4 text-right">
            <button onclick="document.getElementById('modalDetail').classList.add('hidden')" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 text-sm font-bold">Tutup</button>
        </div>
    </div>
</div>

<script>
    function lihatDetail(faktur) {
        document.getElementById('modalDetail').classList.remove('hidden');
        document.getElementById('detailFaktur').innerText = faktur;
        
        let formData = new FormData();
        formData.append('get_detail_transaksi', true);
        formData.append('no_faktur', faktur);

        fetch('index.php?page=riwayat_jual', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(html => {
            document.getElementById('isiDetail').innerHTML = html;
        });
    }
</script>

<?php
// PHP HANDLER AJAX UNTUK DETAIL BARANG
if(isset($_POST['get_detail_transaksi'])) {
    ob_clean(); 
    $faktur = $_POST['no_faktur'];
    
    $q = mysqli_query($conn, "
        SELECT td.*, b.nama_barang, b.satuan 
        FROM transaksi_detail td 
        LEFT JOIN barang b ON td.barang_id = b.id 
        WHERE td.no_faktur='$faktur'
    ");
    
    echo '<table class="w-full text-sm border-collapse">';
    echo '<tr class="bg-gray-50 font-bold text-gray-600 border-b"><td class="p-2">Nama Barang</td><td class="p-2 text-center">Qty</td><td class="p-2 text-right">Harga</td><td class="p-2 text-right">Subtotal</td></tr>';
    
    while($d = mysqli_fetch_assoc($q)) {
        echo '<tr class="border-b last:border-0">';
        echo '<td class="p-2 text-gray-800">'.$d['nama_barang'].'</td>';
        echo '<td class="p-2 text-center text-gray-600">'.(float)$d['qty'].' '.$d['satuan'].'</td>';
        echo '<td class="p-2 text-right text-gray-600">'.number_format($d['harga_satuan']).'</td>';
        echo '<td class="p-2 text-right font-bold text-gray-800">'.number_format($d['subtotal']).'</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}
?>