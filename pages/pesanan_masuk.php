<?php
wajib_akses('pesanan_masuk');

// pages/pesanan_masuk.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// ==========================================
// AUTO FIX DATABASE: Pastikan tabel pesanan punya pelanggan_id
// ==========================================
$cek_kolom_pel = @mysqli_query($conn, "SHOW COLUMNS FROM pesanan LIKE 'pelanggan_id'");
if($cek_kolom_pel && mysqli_num_rows($cek_kolom_pel) == 0) { 
    @mysqli_query($conn, "ALTER TABLE pesanan ADD pelanggan_id INT(11) DEFAULT 0 AFTER user_id"); 
}

// ==========================================
// 1. LOGIKA HAPUS PESANAN
// ==========================================
if(isset($_POST['hapus_pesanan'])) {
    tolak_jika_tidak_boleh('hapus', 'pesanan_masuk', 'index.php?page=pesanan_masuk');
    $id_hapus = $_POST['id_hapus'];
    mysqli_query($conn, "DELETE FROM pesanan_detail WHERE id_pesanan='$id_hapus'");
    $del = mysqli_query($conn, "DELETE FROM pesanan WHERE id='$id_hapus'");
    
    if($del) { echo "<script>alert('Pesanan berhasil dihapus!'); window.location='index.php?page=pesanan_masuk';</script>"; }
}

// ==========================================
// AJAX: DAFTAR ITEM + SISA YANG BELUM DIKIRIM
// Dipakai modal Pengiriman untuk memilih item mana yang dibawa kali ini.
// ==========================================
if(isset($_POST['get_item_kirim'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $id_psn = (int) ($_POST['id_pesanan'] ?? 0);
    $items = [];

    // Surat jalan disimpan sebagai transaksi 'keluar' dengan nomor
    // "<no_pesanan>/SJ<urutan>", sehingga seluruh pengiriman satu pesanan
    // bisa ditelusuri lewat pola nomornya tanpa perlu tabel tambahan.
    $d_psn_aj  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT no_pesanan FROM pesanan WHERE id='$id_psn'"));
    $no_psn_aj = mysqli_real_escape_string($conn, $d_psn_aj['no_pesanan'] ?? '');

    $q_it = mysqli_query($conn, "
        SELECT d.id, d.id_barang, d.qty, d.catatan, b.nama_barang, b.satuan,
               COALESCE((SELECT SUM(td.qty) FROM transaksi_detail td
                         WHERE td.barang_id = d.id_barang
                           AND td.no_faktur LIKE '$no_psn_aj/SJ%'), 0) AS qty_terkirim
        FROM pesanan_detail d
        JOIN barang b ON d.id_barang = b.id
        WHERE d.id_pesanan = '$id_psn'
        ORDER BY b.nama_barang ASC");

    while($it = mysqli_fetch_assoc($q_it)) {
        $qty    = (float) $it['qty'];
        $kirim  = (float) $it['qty_terkirim'];
        $sisa   = max(0, $qty - $kirim);
        $items[] = [
            'id'           => (int) $it['id'],
            'nama_barang'  => $it['nama_barang'],
            'satuan'       => $it['satuan'],
            'catatan'      => $it['catatan'] ?? '',
            'qty'          => $qty,
            'qty_terkirim' => $kirim,
            'sisa'         => $sisa,
            'lunas_kirim'  => $sisa <= 0,
        ];
    }

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// ==========================================
// BUAT SURAT JALAN (BISA BERTAHAP / SEBAGIAN ITEM)
// ==========================================
if(isset($_POST['buat_surat_jalan'])) {
    $id_psn      = (int) ($_POST['id_pesanan'] ?? 0);
    $nama_driver = mysqli_real_escape_string($conn, $_POST['nama_driver'] ?? '');
    $nopol       = mysqli_real_escape_string($conn, $_POST['nopol'] ?? '');
    $kirim       = $_POST['qty_kirim'] ?? [];   // [pesanan_detail_id => qty]

    $d_psn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT no_pesanan, status, pelanggan_id FROM pesanan WHERE id='$id_psn' AND id_usaha='$id_usaha'"));

    if(!$d_psn) {
        echo "<script>alert('Pesanan tidak ditemukan.'); window.location='index.php?page=pesanan_masuk';</script>";
    } else {
        $no_pesanan_safe = mysqli_real_escape_string($conn, $d_psn['no_pesanan']);

        // Validasi tiap baris terhadap sisa yang benar-benar belum dikirim,
        // supaya total terkirim tidak pernah melebihi jumlah yang dipesan.
        $baris_sah = [];
        $total_nilai = 0;
        foreach($kirim as $id_detail => $qty_minta) {
            $id_detail = (int) $id_detail;
            $qty_minta = (float) str_replace(',', '.', $qty_minta);
            if($qty_minta <= 0) continue;

            $d_it = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT d.id, d.id_barang, d.qty, d.harga_satuan,
                       COALESCE((SELECT SUM(td.qty) FROM transaksi_detail td
                                 WHERE td.barang_id = d.id_barang
                                   AND td.no_faktur LIKE '$no_pesanan_safe/SJ%'), 0) AS qty_terkirim
                FROM pesanan_detail d
                WHERE d.id = '$id_detail' AND d.id_pesanan = '$id_psn'"));

            if(!$d_it) continue;
            $sisa = (float)$d_it['qty'] - (float)$d_it['qty_terkirim'];
            if($sisa <= 0) continue;

            $qty_kirim = min($qty_minta, $sisa);
            $harga     = (float) $d_it['harga_satuan'];
            $d_brg     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='{$d_it['id_barang']}'"));

            $baris_sah[] = [
                'id_barang' => (int) $d_it['id_barang'],
                'qty_kirim' => $qty_kirim,
                'harga'     => $harga,
                'hpp'       => (float) ($d_brg['harga_beli'] ?? 0),
                'subtotal'  => $harga * $qty_kirim,
            ];
            $total_nilai += $harga * $qty_kirim;
        }

        if(!$baris_sah) {
            echo "<script>alert('Pilih minimal satu item yang masih punya sisa kirim.'); window.location='index.php?page=pesanan_masuk';</script>";
        } else {
            // Nomor surat jalan berurutan per pesanan: ORD-xxx/SJ1, /SJ2, dst.
            $n_sj = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM transaksi WHERE no_faktur LIKE '$no_pesanan_safe/SJ%'"));
            $urut_sj = (int) ($n_sj['n'] ?? 0) + 1;
            $no_sj   = $d_psn['no_pesanan'] . '/SJ' . $urut_sj;
            $no_sj_safe = mysqli_real_escape_string($conn, $no_sj);

            $user_id_admin = (int) ($_SESSION['user_id'] ?? 0);
            $pel_id_sj = (int) ($d_psn['pelanggan_id'] ?? 0);

            // Surat jalan disimpan sebagai transaksi 'keluar' (status pending,
            // belum lunas) agar tampil di menu Cetak Surat Jalan yang sudah ada.
            $q_sj = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, status, status_bayar, tanggal, user_id, pelanggan_id, nama_driver, nopol)
                     VALUES ('$id_usaha', '$no_sj_safe', 'keluar', '$total_nilai', 0, 'pending', 'belum', NOW(), '$user_id_admin', '$pel_id_sj', '$nama_driver', '$nopol')";

            if(mysqli_query($conn, $q_sj)) {
                foreach($baris_sah as $b) {
                    mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal)
                                         VALUES ('$no_sj_safe', '{$b['id_barang']}', '{$b['qty_kirim']}', '{$b['harga']}', '{$b['hpp']}', '{$b['subtotal']}')");
                }

                // Status pesanan naik ke Pengiriman (stok belum dipotong di tahap ini).
                mysqli_query($conn, "UPDATE pesanan SET status='Pengiriman', nama_driver='$nama_driver', nopol='$nopol' WHERE id='$id_psn'");

                if(function_exists('catat_log')) {
                    catat_log($conn, 'Buat Surat Jalan', "Surat jalan $no_sj untuk pesanan {$d_psn['no_pesanan']} (" . count($baris_sah) . " item)");
                }

                $no_sj_url = urlencode($no_sj);
                echo "<script>alert('Surat Jalan $no_sj dibuat (" . count($baris_sah) . " item).'); window.open('cetak_surat_jalan.php?no_faktur=$no_sj_url', '_blank'); window.location='index.php?page=pesanan_masuk';</script>";
            } else {
                echo "<script>alert('Gagal membuat surat jalan.'); window.location='index.php?page=pesanan_masuk';</script>";
            }
        }
    }
}

// ==========================================
// 2. PROSES UPDATE (ADMIN) & HITUNG ULANG HARGA
// ==========================================
if(isset($_POST['update_pesanan'])) {
    tolak_jika_tidak_boleh('edit', 'pesanan_masuk', 'index.php?page=pesanan_masuk');
    $id_pesanan   = $_POST['id_pesanan'];
    $status_baru  = trim($_POST['status_baru']); 
    $nama_driver  = mysqli_real_escape_string($conn, $_POST['nama_driver']);
    $nopol        = mysqli_real_escape_string($conn, $_POST['nopol']);
    
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT no_pesanan, status, user_id FROM pesanan WHERE id='$id_pesanan'"));
    $no_pesanan  = $cek['no_pesanan'];
    $user_id_pemesan = $cek['user_id'];
    $status_lama = strtolower(trim($cek['status']));
    $status_baru_lower = strtolower(trim($status_baru));

    // SINKRONISASI OTOMATIS: AMBIL DATA DAPUR DARI AKUN PEMESAN
    $jenis_harga_user = 'harga_jual';
    $pelanggan_id_real = 0;
    $nama_real = '';
    $alamat_real = '';

    if($user_id_pemesan > 0) {
        $q_user = mysqli_query($conn, "SELECT jenis_harga, pelanggan_id FROM users WHERE id='$user_id_pemesan'");
        if($u = mysqli_fetch_assoc($q_user)) {
            $jenis_harga_user = !empty($u['jenis_harga']) ? $u['jenis_harga'] : 'harga_jual';
            $pelanggan_id_real = (int)$u['pelanggan_id'];
        }
    }

    if($pelanggan_id_real > 0) {
        $q_pel = mysqli_query($conn, "SELECT nama_pelanggan, alamat FROM pelanggan WHERE id='$pelanggan_id_real'");
        if($p = mysqli_fetch_assoc($q_pel)) {
            $nama_real = $p['nama_pelanggan'];
            $alamat_real = $p['alamat'];
        }
    } else {
        $d_lama = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_pelanggan, alamat FROM pesanan WHERE id='$id_pesanan'"));
        $nama_real = $d_lama['nama_pelanggan'];
        $alamat_real = $d_lama['alamat'];
    }

    // Status yang menandakan barang sudah keluar gudang (stok dipotong).
    // "Diterima" = barang sampai ke pelanggan tapi belum lunas.
    // "Selesai"  = barang sampai DAN sudah lunas.
    $status_potong_stok = ['diterima', 'selesai'];
    $stok_terpotong_lama = in_array($status_lama, $status_potong_stok, true);
    $stok_terpotong_baru = in_array($status_baru_lower, $status_potong_stok, true);

    // MESIN HITUNG ULANG HARGA & MANAJEMEN STOK STRICT
    $items_query = mysqli_query($conn, "SELECT * FROM pesanan_detail WHERE id_pesanan='$id_pesanan'");
    $new_total = 0;
    $items_to_insert = [];

    while($item = mysqli_fetch_assoc($items_query)) {
        $id_brg = $item['id_barang'];
        $qty = (float)$item['qty'];
        
        $q_brg = mysqli_query($conn, "SELECT harga_beli, harga_jual, harga_gabek, harga_kereta, harga_jebus FROM barang WHERE id='$id_brg'");
        $d_brg = mysqli_fetch_assoc($q_brg);
        
        $hpp = $d_brg['harga_beli'] ?? 0; 
        $h_final = (isset($d_brg[$jenis_harga_user]) && (float)$d_brg[$jenis_harga_user] > 0) ? (float)$d_brg[$jenis_harga_user] : (float)$d_brg['harga_jual'];
        
        $subtotal = $h_final * $qty;
        $new_total += $subtotal;

        // POTONG STOK STRICT
        // Hanya bergerak saat melintasi batas "sudah keluar gudang", sehingga
        // perpindahan Diterima -> Selesai tidak memotong stok dua kali.
        if ($stok_terpotong_baru && !$stok_terpotong_lama) {
            mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id = '$id_brg'");
        }
        elseif ($stok_terpotong_lama && !$stok_terpotong_baru) {
            mysqli_query($conn, "UPDATE barang SET stok = stok + $qty WHERE id = '$id_brg'");
        }
        
        $items_to_insert[] = ['id_barang' => $id_brg, 'qty' => $qty, 'harga_satuan' => $h_final, 'hpp' => $hpp, 'subtotal' => $subtotal];
        mysqli_query($conn, "UPDATE pesanan_detail SET harga_satuan='$h_final', subtotal='$subtotal' WHERE id='{$item['id']}'");
    }

    mysqli_query($conn, "UPDATE pesanan SET status='$status_baru', nama_driver='$nama_driver', nopol='$nopol', nama_pelanggan='$nama_real', alamat='$alamat_real', pelanggan_id='$pelanggan_id_real', total_bayar='$new_total' WHERE id='$id_pesanan'");

    // -------------------------------------------------------------------
    // PERBAIKAN: SINKRONISASI KE TABEL TRANSAKSI (TIDAK OTOMATIS LUNAS)
    // -------------------------------------------------------------------
    // Transaksi dianggap 'selesai' begitu barang keluar gudang (Diterima / Selesai),
    // sehingga invoice & retur sudah bisa diproses walau belum lunas.
    $status_trx_baru = $stok_terpotong_baru ? 'selesai' : 'pending';

    $cek_trx = mysqli_query($conn, "SELECT id, status_bayar, bayar FROM transaksi WHERE no_faktur='$no_pesanan'");

    if(mysqli_num_rows($cek_trx) == 0) {
        // JIKA INVOICE BELUM PERNAH DIBUAT (BUAT BARU)
        $user_kasir_id = $_SESSION['user_id'] ?? 0;

        // Lunas hanya bila status "Selesai"; "Diterima" tetap belum lunas.
        $status_bayar_baru = ($status_baru_lower === 'selesai') ? 'lunas' : 'belum';
        $nominal_bayar = ($status_baru_lower === 'selesai') ? $new_total : 0;

        $q_trx = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, status, status_bayar, tanggal, user_id, pelanggan_id, nama_driver, nopol) 
                  VALUES ('$id_usaha', '$no_pesanan', 'keluar', '$new_total', '$nominal_bayar', '$status_trx_baru', '$status_bayar_baru', NOW(), '$user_kasir_id', '$pelanggan_id_real', '$nama_driver', '$nopol')";
        
        if(mysqli_query($conn, $q_trx)) {
            foreach($items_to_insert as $itm) {
                mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal) 
                                     VALUES ('$no_pesanan', '{$itm['id_barang']}', '{$itm['qty']}', '{$itm['harga_satuan']}', '{$itm['hpp']}', '{$itm['subtotal']}')");
            }
        }
    } else {
        // Status bayar dibiarkan apa adanya agar pelunasan manual admin tidak ter-reset.
        // Pengecualian: memilih "Selesai" berarti sekaligus menandai LUNAS.
        $set_bayar = '';
        if ($status_baru_lower === 'selesai') {
            $set_bayar = ", status_bayar='lunas', bayar='$new_total', tgl_lunas=NOW()";
        }

        mysqli_query($conn, "UPDATE transaksi SET
                             status='$status_trx_baru',
                             nama_driver='$nama_driver',
                             nopol='$nopol',
                             pelanggan_id='$pelanggan_id_real',
                             total_transaksi='$new_total'
                             $set_bayar
                             WHERE no_faktur='$no_pesanan'");
                             
        mysqli_query($conn, "DELETE FROM transaksi_detail WHERE no_faktur='$no_pesanan'");
        foreach($items_to_insert as $itm) {
            mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal) 
                                 VALUES ('$no_pesanan', '{$itm['id_barang']}', '{$itm['qty']}', '{$itm['harga_satuan']}', '{$itm['hpp']}', '{$itm['subtotal']}')");
        }
    }
    
    // Notifikasi
    if($status_baru_lower == 'pengiriman') {
        echo "<script>alert('Pesanan diproses ke Pengiriman!\\nSurat Jalan otomatis dibuat di riwayat tanpa memotong stok.'); window.open('cetak_surat_jalan.php?no_faktur=$no_pesanan', '_blank'); window.location='index.php?page=pesanan_masuk';</script>";
    } elseif($status_baru_lower == 'diterima') {
        echo "<script>alert('Pesanan DITERIMA pelanggan!\\nStok telah dipotong dan invoice diterbitkan dengan status BELUM LUNAS.\\nPelanggan kini bisa mengajukan retur barang.'); window.location='index.php?page=pesanan_masuk';</script>";
    } elseif($status_baru_lower == 'selesai') {
        echo "<script>alert('Pesanan SELESAI!\\nStok dipotong dan invoice ditandai LUNAS.'); window.location='index.php?page=pesanan_masuk';</script>";
    } else {
        echo "<script>alert('Status Pesanan Diperbarui!'); window.location='index.php?page=pesanan_masuk';</script>";
    }
}

// ==========================================
// 3. EXPORT EXCEL (mengikuti filter yang sedang aktif)
// ==========================================
if(isset($_POST['export_excel'])) {
    while (ob_get_level()) { ob_end_clean(); }

    $ex_cari  = mysqli_real_escape_string($conn, $_POST['filter_cari'] ?? '');
    $ex_stat  = mysqli_real_escape_string($conn, $_POST['filter_status'] ?? '');
    $ex_awal  = mysqli_real_escape_string($conn, $_POST['filter_tgl_awal'] ?? '');
    $ex_akhir = mysqli_real_escape_string($conn, $_POST['filter_tgl_akhir'] ?? '');

    $ex_where = "p.id_usaha = '$id_usaha'";
    if ($ex_stat !== '')  { $ex_where .= " AND p.status = '$ex_stat'"; }
    if ($ex_awal !== '')  { $ex_where .= " AND DATE(p.tanggal) >= '$ex_awal'"; }
    if ($ex_akhir !== '') { $ex_where .= " AND DATE(p.tanggal) <= '$ex_akhir'"; }
    if ($ex_cari !== '') {
        $ex_where .= " AND (p.no_pesanan LIKE '%$ex_cari%' OR p.nama_pelanggan LIKE '%$ex_cari%' OR p.keterangan LIKE '%$ex_cari%' OR u.nama LIKE '%$ex_cari%')";
    }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Rekap_Pesanan_Masuk_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr style="background-color: #4F46E5; color: white;">
            <th>No</th>
            <th>No. Pesanan</th>
            <th>Tanggal</th>
            <th>User Order / Dapur</th>
            <th>Catatan</th>
            <th>Item</th>
            <th>Status</th>
            <th>Driver</th>
            <th>Nopol</th>
            <th>Total Bayar</th>
          </tr>';

    $q_ex = mysqli_query($conn, "
        SELECT p.*, u.nama as akun_pemesan
        FROM pesanan p LEFT JOIN users u ON p.user_id = u.id
        WHERE $ex_where ORDER BY p.id DESC");

    $no = 1;
    while($row = mysqli_fetch_assoc($q_ex)) {
        $det = mysqli_query($conn, "SELECT d.qty, b.nama_barang, b.satuan FROM pesanan_detail d JOIN barang b ON d.id_barang = b.id WHERE d.id_pesanan='{$row['id']}'");
        $items = [];
        while($d = mysqli_fetch_assoc($det)) { $items[] = $d['nama_barang'] . ' (' . (float)$d['qty'] . ' ' . $d['satuan'] . ')'; }

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $row['no_pesanan'] . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($row['tanggal'])) . '</td>';
        echo '<td>' . $row['nama_pelanggan'] . '</td>';
        echo '<td>' . $row['keterangan'] . '</td>';
        echo '<td>' . implode('; ', $items) . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '<td>' . $row['nama_driver'] . '</td>';
        echo '<td>' . $row['nopol'] . '</td>';
        echo '<td>' . $row['total_bayar'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit();
}
?>

<div class="bg-white p-6 rounded-lg shadow-sm">
    <h2 class="text-2xl font-bold mb-6 text-gray-800"><i class="fa-solid fa-list-check mr-2"></i> Verifikasi Pesanan Masuk</h2>
    
    <?php
    $pms = trim((string)($_GET['status'] ?? ''));
    $sel_status = '<select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white"><option value="">-- Semua Status --</option>';
    foreach (['Pending','Persiapan','Pengiriman','Diterima','Selesai','Batal'] as $st) {
        $sel_status .= '<option value="' . $st . '"' . ($pms === $st ? ' selected' : '') . '>' . $st . '</option>';
    }
    $sel_status .= '</select>';

    $f_tgl_awal  = htmlspecialchars((string)($_GET['tgl_awal'] ?? ''), ENT_QUOTES, 'UTF-8');
    $f_tgl_akhir = htmlspecialchars((string)($_GET['tgl_akhir'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extra_tgl   = '<input type="date" name="tgl_awal" value="' . $f_tgl_awal . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Mulai">'
                 . '<input type="date" name="tgl_akhir" value="' . $f_tgl_akhir . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Akhir">';

    echo render_filter('Cari no pesanan / dapur / catatan...', $sel_status . $extra_tgl);
    ?>

    <div class="flex justify-end mb-4">
        <form method="POST">
            <input type="hidden" name="filter_cari" value="<?= htmlspecialchars(ambil_kata_kunci(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_status" value="<?= htmlspecialchars($pms, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="filter_tgl_awal" value="<?= $f_tgl_awal ?>">
            <input type="hidden" name="filter_tgl_akhir" value="<?= $f_tgl_akhir ?>">
            <button type="submit" name="export_excel" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 shadow">
                <i class="fa-solid fa-file-excel mr-2"></i>Rekap / Download Excel
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border table-fixed min-w-[1000px]">
            <thead class="bg-gray-100 uppercase font-bold text-gray-600 text-xs">
                <tr>
                    <th class="p-3 border w-[130px]">Order Info</th>
                    <th class="p-3 border w-[180px]">User Order / Dapur</th>
                    <th class="p-3 border text-indigo-700 w-[170px]">Catatan Orderan</th>
                    <th class="p-3 border w-[40%]">Detail Item</th>
                    <th class="p-3 border text-center w-[90px]">Status</th>
                    <th class="p-3 border text-center w-[130px]">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php
                // Paginasi & filter sisi server
                $pm_cari  = ambil_kata_kunci();
                $pm_stat  = trim((string) ($_GET['status'] ?? ''));
                $pm_awal  = trim((string) ($_GET['tgl_awal'] ?? ''));
                $pm_akhir = trim((string) ($_GET['tgl_akhir'] ?? ''));
                $pm_hal   = ambil_halaman();
                $pm_limit = ambil_per_halaman();

                $pm_wt = ['p.id_usaha = ?'];
                $pm_pt = [$id_usaha];
                $pm_tt = 'i';

                if ($pm_stat !== '') {
                    $pm_wt[] = 'p.status = ?';
                    $pm_pt[] = $pm_stat;
                    $pm_tt  .= 's';
                }
                if ($pm_awal !== '') {
                    $pm_wt[] = 'DATE(p.tanggal) >= ?';
                    $pm_pt[] = $pm_awal;
                    $pm_tt  .= 's';
                }
                if ($pm_akhir !== '') {
                    $pm_wt[] = 'DATE(p.tanggal) <= ?';
                    $pm_pt[] = $pm_akhir;
                    $pm_tt  .= 's';
                }

                [$pm_where, $pm_params, $pm_tipe] = bangun_filter(
                    $pm_cari, ['p.no_pesanan', 'p.nama_pelanggan', 'p.keterangan', 'u.nama'],
                    $pm_wt, $pm_pt, $pm_tt
                );

                $pm_total  = hitung_total($conn, 'pesanan p LEFT JOIN users u ON p.user_id = u.id',
                                          $pm_where, $pm_params, $pm_tipe);
                $pm_hal    = batasi_halaman($pm_hal, $pm_total, $pm_limit);
                $pm_offset = ($pm_hal - 1) * $pm_limit;

                $pm_rows = ambil_data($conn,
                    'SELECT p.*, u.nama as akun_pemesan, u.jenis_harga, u.pelanggan_id as u_pelanggan_id
                     FROM pesanan p LEFT JOIN users u ON p.user_id = u.id',
                    $pm_where, $pm_params, $pm_tipe, 'ORDER BY p.id DESC', $pm_limit, $pm_offset);

                if(!$pm_rows) { echo "<tr><td colspan='6' class='p-5 text-center text-gray-400'>Belum ada pesanan masuk.</td></tr>"; }

                foreach($pm_rows as $row):
                    $color = match($row['status']) { 'Pending'=>'bg-gray-200','Persiapan'=>'bg-yellow-100','Pengiriman'=>'bg-blue-100','Diterima'=>'bg-teal-100','Selesai'=>'bg-green-100','Batal'=>'bg-red-100', default=>'bg-gray-200' };
                    
                    $pelanggan_id_display = $row['u_pelanggan_id'] > 0 ? $row['u_pelanggan_id'] : $row['pelanggan_id'];
                    $nama_dapur_display = $row['nama_pelanggan'];
                    $label_harga = "Harga Umum";
                    
                    if($row['jenis_harga'] == 'harga_gabek') $label_harga = "Pangkalpinang";
                    if($row['jenis_harga'] == 'harga_kereta') $label_harga = "Bangka Tengah";
                    if($row['jenis_harga'] == 'harga_jebus') $label_harga = "Bangka Barat";
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3 border align-top break-words">
                        <div class="font-mono font-bold text-indigo-700"><?= $row['no_pesanan'] ?></div>
                        <div class="text-[10px] text-gray-400"><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></div>
                    </td>
                    <td class="p-3 border align-top break-words">
                        <div class="font-bold text-gray-700"><?= $nama_dapur_display ?></div>
                        <?php if($pelanggan_id_display > 0): ?>
                            <div class="text-[10px] text-green-600 font-bold mt-1 bg-green-50 px-2 py-0.5 inline-block rounded"><i class="fa-solid fa-link"></i> Terkunci: <?= $label_harga ?></div>
                        <?php else: ?>
                            <div class="text-[10px] text-red-500 font-bold mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Pemesan Belum Disinkronisasi</div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="p-3 border align-top break-words">
                        <div class="bg-indigo-50 text-indigo-700 px-3 py-2 rounded-lg text-xs font-bold italic border border-indigo-100">
                            <?= htmlspecialchars($row['keterangan'] ?: '-') ?>
                        </div>
                    </td>

                    <td class="p-3 border align-top">
                        <?php
                            $det = mysqli_query($conn, "SELECT d.*, b.nama_barang, b.satuan FROM pesanan_detail d JOIN barang b ON d.id_barang = b.id WHERE d.id_pesanan='{$row['id']}'");
                            $items = [];
                            while($d = mysqli_fetch_assoc($det)) { $items[] = $d; }
                        ?>
                        <?php if($items): ?>
                            <!-- Dua kolom di layar lebar agar daftar item tidak memanjang ke bawah -->
                            <ul class="text-xs text-gray-600 columns-1 lg:columns-2 gap-x-6">
                            <?php foreach($items as $d): ?>
                                <li class="break-inside-avoid mb-1 flex items-start gap-1.5">
                                    <i class="fa-solid fa-circle text-[4px] mt-1.5 text-gray-400 shrink-0"></i>
                                    <span class="break-words">
                                        <?= htmlspecialchars($d['nama_barang'], ENT_QUOTES, 'UTF-8') ?>
                                        <b class="text-gray-800 whitespace-nowrap">(<?= (float)$d['qty'] ?> <?= htmlspecialchars($d['satuan'], ENT_QUOTES, 'UTF-8') ?>)</b>
                                        <?php if(trim((string)($d['catatan'] ?? '')) !== ''): ?>
                                            <span class="block text-[10px] text-amber-700 italic"><i class="fa-solid fa-note-sticky mr-1"></i><?= htmlspecialchars($d['catatan'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                            <div class="text-[10px] text-gray-400 mt-2 pt-1 border-t border-dashed"><?= count($items) ?> item</div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400 italic">Tidak ada item.</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 border text-center align-top">
                        <span class="px-2 py-1 rounded text-[10px] uppercase font-bold <?= $color ?>"><?= $row['status'] ?></span>
                    </td>
                    <td class="p-3 border text-center align-top">
                        <div class="flex items-center justify-center gap-2">
                            <?php if(boleh('edit','pesanan_masuk')): ?>
                            <button onclick='prosesPesanan(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="bg-indigo-600 text-white px-3 py-2 rounded text-xs font-bold hover:bg-indigo-700 shadow">
                                <i class="fa-solid fa-pen-to-square"></i> Proses
                            </button>
                            <?php endif; ?>

                            <?php if(boleh('hapus','pesanan_masuk')): ?>
                            <form method="POST" onsubmit="return confirm('Hapus pesanan?');">
                                <input type="hidden" name="id_hapus" value="<?= $row['id'] ?>">
                                <button type="submit" name="hapus_pesanan" class="bg-red-500 text-white px-3 py-2 rounded text-xs font-bold hover:bg-red-600 shadow">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if(!boleh('edit','pesanan_masuk') && !boleh('hapus','pesanan_masuk')): ?>
                                <span class="text-[11px] text-gray-400 italic">Lihat saja</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($pm_hal, $pm_total, $pm_limit) ?>
</div>

<div id="modalProses" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md animate-fade-in">
        <div class="p-4 border-b flex justify-between items-center bg-indigo-50 rounded-t-xl">
            <h3 class="font-bold text-indigo-800">Verifikasi Order: <span id="lblNoPesanan"></span></h3>
            <button type="button" onclick="document.getElementById('modalProses').classList.add('hidden')" class="text-indigo-400 hover:text-indigo-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" class="p-5">
            <input type="hidden" name="id_pesanan" id="input_id">
            
            <div class="mb-3 bg-green-50 p-3 rounded border border-green-100">
                <label class="block text-[10px] font-bold text-green-700 uppercase mb-1"><i class="fa-solid fa-lock"></i> Identitas Dapur & Harga Terkunci</label>
                <div id="lblNamaPelangganModal" class="text-sm font-bold text-gray-800"></div>
                <div id="lblAreaHargaModal" class="text-xs text-indigo-600 font-bold mt-1"></div>
            </div>

            <div class="mb-3 bg-amber-50 p-3 rounded border border-amber-100">
                <label class="block text-[10px] font-bold text-amber-700 uppercase">Ringkasan Catatan:</label>
                <div id="lblCatatanModal" class="text-xs italic text-gray-700 mt-1"></div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Driver</label>
                    <select name="nama_driver" id="input_driver" class="w-full border p-2 rounded bg-white" onchange="isiNopolOtomatis()">
                        <option value="">-- Pilih Driver --</option>
                        <?php
                        $q_drv = mysqli_query($conn, "SELECT nama, nopol FROM users WHERE role='driver' AND id_usaha='$id_usaha' ORDER BY nama ASC");
                        while($d = mysqli_fetch_assoc($q_drv)) { echo "<option value='{$d['nama']}' data-nopol='{$d['nopol']}'>{$d['nama']}</option>"; }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nopol</label>
                    <input type="text" name="nopol" id="input_nopol" class="w-full border p-2 rounded bg-gray-100" readonly>
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                <select name="status_baru" id="input_status" onchange="cekStatusKirim()" class="w-full border p-2 rounded bg-indigo-50 text-indigo-800 font-bold">
                    <option value="Pending">Pending</option>
                    <option value="Persiapan">Persiapan</option>
                    <option value="Pengiriman">Pengiriman (Buat Surat Jalan)</option>
                    <option value="Diterima">Diterima (Potong Stok &amp; Belum Lunas)</option>
                    <option value="Selesai">Selesai (Potong Stok &amp; Lunas)</option>
                    <option value="Batal">Batal</option>
                </select>
            </div>

            <!-- Panel pemilihan item: hanya tampil saat status Pengiriman -->
            <div id="panelKirim" class="mb-5 hidden">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Item yang Dikirim</label>
                    <button type="button" onclick="toggleSemuaItem()" id="btnPilihSemua" class="text-[11px] font-bold text-indigo-600 hover:underline">Pilih Semua Sisa</button>
                </div>
                <div id="isiItemKirim" class="border rounded-lg divide-y max-h-64 overflow-y-auto bg-white">
                    <p class="p-4 text-center text-xs text-gray-400">Memuat item...</p>
                </div>
                <div class="mt-2 flex justify-between items-center bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
                    <span class="text-xs font-bold text-indigo-700 uppercase">Total item dikirim</span>
                    <span id="lblTotalKirim" class="text-sm font-bold text-indigo-800">0 item</span>
                </div>
                <p id="pesanSemuaTerkirim" class="hidden mt-2 text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                    <i class="fa-solid fa-circle-check mr-1"></i> Semua item pesanan ini sudah dibuatkan surat jalan.
                </p>
            </div>

            <button type="submit" name="update_pesanan" id="btnSimpanProses" class="w-full bg-indigo-600 text-white font-bold py-3 rounded hover:bg-indigo-700">SIMPAN & PROSES</button>
            <button type="submit" name="buat_surat_jalan" id="btnBuatSJ" class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700 hidden">
                <i class="fa-solid fa-truck-fast mr-1"></i> BUAT SURAT JALAN
            </button>
        </form>
    </div>
</div>

<script>
function prosesPesanan(data) {
    document.getElementById('modalProses').classList.remove('hidden');
    document.getElementById('lblNoPesanan').innerText = data.no_pesanan;
    document.getElementById('input_id').value = data.id;
    document.getElementById('input_status').value = data.status;
    
    document.getElementById('lblNamaPelangganModal').innerText = data.nama_pelanggan || 'Tidak Diketahui';
    
    let labelHarga = "Kalkulasi Harga: Umum";
    if(data.jenis_harga === 'harga_gabek') labelHarga = "Kalkulasi Harga Otomatis: Pangkalpinang";
    if(data.jenis_harga === 'harga_kereta') labelHarga = "Kalkulasi Harga Otomatis: Bangka Tengah";
    if(data.jenis_harga === 'harga_jebus') labelHarga = "Kalkulasi Harga Otomatis: Bangka Barat";
    
    document.getElementById('lblAreaHargaModal').innerText = labelHarga;
    document.getElementById('lblCatatanModal').innerText = data.keterangan || '-';

    let drvSelect = document.getElementById('input_driver');
    drvSelect.value = data.nama_driver || '';
    isiNopolOtomatis();

    idPesananAktif = data.id;
    cekStatusKirim();
}

// ===== PEMILIHAN ITEM UNTUK SURAT JALAN (PENGIRIMAN BERTAHAP) =====
let idPesananAktif = 0;

function cekStatusKirim() {
    const status = document.getElementById('input_status').value;
    const panel  = document.getElementById('panelKirim');
    const btnSimpan = document.getElementById('btnSimpanProses');
    const btnSJ  = document.getElementById('btnBuatSJ');

    if (status === 'Pengiriman') {
        panel.classList.remove('hidden');
        btnSimpan.classList.add('hidden');
        btnSJ.classList.remove('hidden');
        muatItemKirim();
    } else {
        panel.classList.add('hidden');
        btnSimpan.classList.remove('hidden');
        btnSJ.classList.add('hidden');
    }
}

function muatItemKirim() {
    const wadah = document.getElementById('isiItemKirim');
    wadah.innerHTML = '<p class="p-4 text-center text-xs text-gray-400">Memuat item...</p>';

    const fd = new FormData();
    fd.append('get_item_kirim', true);
    fd.append('id_pesanan', idPesananAktif);

    fetch('index.php?page=pesanan_masuk', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success' || !d.items.length) {
                wadah.innerHTML = '<p class="p-4 text-center text-xs text-gray-400">Tidak ada item pada pesanan ini.</p>';
                return;
            }

            const semuaTerkirim = d.items.every(it => it.lunas_kirim);
            document.getElementById('pesanSemuaTerkirim').classList.toggle('hidden', !semuaTerkirim);
            document.getElementById('btnBuatSJ').disabled = semuaTerkirim;
            document.getElementById('btnBuatSJ').classList.toggle('opacity-50', semuaTerkirim);
            document.getElementById('btnBuatSJ').classList.toggle('cursor-not-allowed', semuaTerkirim);

            wadah.innerHTML = d.items.map(it => barisItem(it)).join('');
            hitungTotalKirim();
        })
        .catch(() => {
            wadah.innerHTML = '<p class="p-4 text-center text-xs text-red-500">Gagal memuat item.</p>';
        });
}

function barisItem(it) {
    const nonaktif = it.lunas_kirim;
    // Item yang sudah terkirim penuh: checkbox dimatikan agar tidak bisa dikirim ulang.
    const badge = nonaktif
        ? '<span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">Terkirim penuh</span>'
        : '<span class="text-[10px] text-gray-500">Sisa: <b>' + it.sisa + ' ' + it.satuan + '</b></span>';

    const catatan = it.catatan
        ? '<div class="text-[10px] text-amber-700 italic mt-0.5"><i class="fa-solid fa-note-sticky mr-1"></i>' + escHtml(it.catatan) + '</div>'
        : '';

    return '<label class="flex items-center gap-3 p-3 ' + (nonaktif ? 'bg-gray-50' : 'hover:bg-slate-50') + '">'
         + '<input type="checkbox" class="cek-item w-4 h-4 shrink-0" data-id="' + it.id + '" data-sisa="' + it.sisa + '"'
         + (nonaktif ? ' disabled' : '') + ' onchange="saatCentang(this)">'
         + '<div class="flex-1 min-w-0">'
         + '<div class="text-sm font-semibold ' + (nonaktif ? 'text-gray-400' : 'text-gray-800') + ' truncate">' + escHtml(it.nama_barang) + '</div>'
         + '<div class="text-[10px] text-gray-500">Dipesan ' + it.qty + ' ' + it.satuan
         + ' &middot; terkirim ' + it.qty_terkirim + '</div>' + catatan
         + '</div>'
         + '<div class="text-right shrink-0">' + badge
         + '<input type="number" step="0.01" min="0" max="' + it.sisa + '" name="qty_kirim[' + it.id + ']"'
         + ' class="qty-item w-20 border p-1 rounded text-right text-xs mt-1 block" value="" placeholder="0"'
         + (nonaktif ? ' disabled' : '') + ' oninput="hitungTotalKirim()">'
         + '</div></label>';
}

function escHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Mencentang item otomatis mengisi qty dengan seluruh sisanya.
function saatCentang(cb) {
    const baris = cb.closest('label');
    const input = baris.querySelector('.qty-item');
    input.value = cb.checked ? cb.dataset.sisa : '';
    hitungTotalKirim();
}

function toggleSemuaItem() {
    const kotak = document.querySelectorAll('.cek-item:not(:disabled)');
    const adaYangBelum = Array.from(kotak).some(c => !c.checked);
    kotak.forEach(c => { c.checked = adaYangBelum; saatCentang(c); });
}

function hitungTotalKirim() {
    let jml = 0;
    document.querySelectorAll('.qty-item:not(:disabled)').forEach(inp => {
        if (parseFloat(inp.value) > 0) jml++;
    });
    document.getElementById('lblTotalKirim').innerText = jml + ' item';
}

function isiNopolOtomatis() {
    let select = document.getElementById("input_driver");
    if(select.selectedIndex >= 0) {
        let nopol = select.options[select.selectedIndex].getAttribute("data-nopol");
        document.getElementById("input_nopol").value = nopol || "";
    }
}
</script>