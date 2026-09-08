<?php
/**
 * =========================================================================
 * OPTIMASI DATABASE — INDEX & ARSIP DATA GPS
 * =========================================================================
 * Skrip ini:
 *   1. Menambahkan index pada kolom yang sering dipakai untuk filter/JOIN.
 *   2. Mengarsipkan data GPS (history_perjalanan) yang lebih dari 30 hari
 *      ke tabel history_perjalanan_arsip — data TIDAK dihapus.
 *
 * AMAN dijalankan berulang kali: index yang sudah ada akan dilewati.
 * Tidak ada data yang dihapus, hanya dipindah ke tabel arsip.
 *
 * CARA PAKAI:
 *   1. Buka http://localhost/8mp_gudang/optimasi_database.php  (mode pratinjau)
 *   2. Tambahkan ?jalankan=ya untuk mengeksekusi
 *   3. HAPUS file ini setelah selesai
 * =========================================================================
 */

require __DIR__ . '/config/koneksi.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Skrip optimasi hanya boleh dijalankan dari localhost.');
}

@set_time_limit(600);
$jalankan   = (($_GET['jalankan'] ?? '') === 'ya');
$hari_arsip = 30;

header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><style>body{font-family:system-ui,sans-serif;max-width:900px;margin:40px auto;line-height:1.6;color:#1e293b}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:13px}
.box{border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;margin:16px 0}
.warn{background:#fff7ed;border-color:#fdba74}.ok{background:#f0fdf4;border-color:#86efac}
table{border-collapse:collapse;width:100%;margin-top:10px}
td,th{border:1px solid #e2e8f0;padding:7px 10px;text-align:left;font-size:13px}
th{background:#f8fafc}</style>';

echo '<h2>Optimasi Database</h2>';
echo $jalankan
    ? '<div class="box warn"><b>MODE EKSEKUSI</b> — perubahan disimpan ke database.</div>'
    : '<div class="box"><b>MODE PRATINJAU</b> — belum ada yang diubah. '
      . 'Tambahkan <code>?jalankan=ya</code> pada URL untuk mengeksekusi.</div>';

/* ---------------------------------------------------------------
 * BAGIAN 1: INDEX
 * ------------------------------------------------------------- */

// Daftar index: tabel => [nama_index => kolom]
$daftar_index = [
    'transaksi' => [
        'idx_tgl'        => 'tanggal',
        'idx_usaha_tgl'  => 'id_usaha, tanggal',
        'idx_pelanggan'  => 'pelanggan_id',
        'idx_status'     => 'status',
    ],
    'transaksi_detail' => [
        'idx_faktur'  => 'no_faktur',
        'idx_barang'  => 'barang_id',
    ],
    'barang' => [
        'idx_nama'     => 'nama_barang',
        'idx_kategori' => 'kategori',
        'idx_supplier' => 'supplier_id',
    ],
    'log_aktivitas' => [
        'idx_tanggal' => 'tanggal',
        'idx_user'    => 'user_id',
    ],
    'pesanan' => [
        'idx_usaha_tgl' => 'id_usaha, tanggal',
        'idx_status'    => 'status',
        'idx_pelanggan' => 'pelanggan_id',
    ],
    'pesanan_detail' => [
        'idx_pesanan' => 'id_pesanan',
        'idx_barang'  => 'id_barang',
    ],
    'approval_request' => [
        'idx_status_usaha' => 'status, id_usaha',
    ],
    'riwayat_harga' => [
        'idx_barang' => 'barang_id',
    ],
    'supplier' => [
        'idx_nama' => 'nama_supplier',
    ],
    'users' => [
        'idx_role'     => 'role',
        'idx_username' => 'username',
    ],
    'history_perjalanan' => [
        'idx_user_waktu' => 'user_id, waktu',
        'idx_waktu'      => 'waktu',
    ],
];

echo '<h3>1. Index Database</h3><table><tr><th>Tabel</th><th>Index</th><th>Kolom</th><th>Status</th></tr>';

$dibuat = 0; $dilewati = 0;
foreach ($daftar_index as $tabel => $indexes) {
    // Lewati tabel yang tidak ada
    $cek_tabel = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tabel) . "'");
    if (!$cek_tabel || mysqli_num_rows($cek_tabel) === 0) {
        echo '<tr><td>' . htmlspecialchars($tabel) . '</td><td colspan="3">tabel tidak ada — dilewati</td></tr>';
        continue;
    }

    // Kumpulkan index yang sudah ada
    $sudah_ada = [];
    $r = @mysqli_query($conn, "SHOW INDEX FROM `$tabel`");
    if ($r) { while ($x = mysqli_fetch_assoc($r)) { $sudah_ada[$x['Key_name']] = true; } }

    foreach ($indexes as $nama_idx => $kolom) {
        $status = '';
        if (isset($sudah_ada[$nama_idx])) {
            $status = 'sudah ada — dilewati';
            $dilewati++;
        } elseif (!$jalankan) {
            $status = 'akan dibuat';
        } else {
            $sql = "ALTER TABLE `$tabel` ADD INDEX `$nama_idx` ($kolom)";
            if (@mysqli_query($conn, $sql)) {
                $status = '<b>BERHASIL dibuat</b>';
                $dibuat++;
            } else {
                $status = 'GAGAL: ' . htmlspecialchars(mysqli_error($conn));
            }
        }
        echo '<tr><td>' . htmlspecialchars($tabel) . '</td><td><code>' . htmlspecialchars($nama_idx)
           . '</code></td><td>' . htmlspecialchars($kolom) . '</td><td>' . $status . '</td></tr>';
    }
}
echo '</table>';
echo '<div class="box">Index dibuat: <b>' . $dibuat . '</b> &nbsp;|&nbsp; sudah ada sebelumnya: <b>' . $dilewati . '</b></div>';

/* ---------------------------------------------------------------
 * BAGIAN 2: ARSIP DATA GPS
 * ------------------------------------------------------------- */

echo '<h3>2. Arsip Data GPS (history_perjalanan)</h3>';

$cek_hp = @mysqli_query($conn, "SHOW TABLES LIKE 'history_perjalanan'");
if (!$cek_hp || mysqli_num_rows($cek_hp) === 0) {
    echo '<div class="box">Tabel <code>history_perjalanan</code> tidak ada — bagian ini dilewati.</div>';
} else {
    $q_total = mysqli_query($conn, "SELECT COUNT(*) n FROM history_perjalanan");
    $total   = (int) mysqli_fetch_assoc($q_total)['n'];

    $q_lama = mysqli_query($conn,
        "SELECT COUNT(*) n FROM history_perjalanan WHERE waktu < DATE_SUB(NOW(), INTERVAL $hari_arsip DAY)");
    $jml_lama = (int) mysqli_fetch_assoc($q_lama)['n'];

    echo '<div class="box">Total baris: <b>' . number_format($total) . '</b><br>'
       . 'Lebih dari ' . $hari_arsip . ' hari (akan diarsipkan): <b>' . number_format($jml_lama) . '</b><br>'
       . 'Tetap di tabel utama: <b>' . number_format($total - $jml_lama) . '</b></div>';

    if ($jml_lama === 0) {
        echo '<div class="box ok">Tidak ada data lama yang perlu diarsipkan.</div>';
    } elseif (!$jalankan) {
        echo '<div class="box">Data akan <b>dipindah</b> (bukan dihapus) ke tabel '
           . '<code>history_perjalanan_arsip</code>.</div>';
    } else {
        // Buat tabel arsip meniru struktur aslinya.
        // AUTO_INCREMENT pada kolom id dibuang supaya nilai id asli ikut tersalin
        // apa adanya (kalau tidak, MySQL menomori ulang dan menimbulkan bentrok).
        $tabel_arsip_baru = false;
        $cek_arsip = @mysqli_query($conn, "SHOW TABLES LIKE 'history_perjalanan_arsip'");
        if (!$cek_arsip || mysqli_num_rows($cek_arsip) === 0) {
            @mysqli_query($conn, "CREATE TABLE history_perjalanan_arsip LIKE history_perjalanan");
            @mysqli_query($conn, "ALTER TABLE history_perjalanan_arsip MODIFY `id` INT(11) NOT NULL");
            $tabel_arsip_baru = true;
        }

        // Daftar kolom eksplisit agar id tersalin persis
        $kolom_arsip = [];
        $rk = @mysqli_query($conn, "SHOW COLUMNS FROM history_perjalanan");
        if ($rk) { while ($x = mysqli_fetch_assoc($rk)) { $kolom_arsip[] = '`' . $x['Field'] . '`'; } }
        $daftar_kolom = implode(', ', $kolom_arsip);

        // Dipindah PER BATCH agar tidak timeout pada data ratusan ribu baris.
        // Setiap batch dibungkus transaksi sendiri: kalau berhenti di tengah,
        // batch yang sudah selesai tetap konsisten (tidak ada data hilang).
        $ukuran_batch = 20000;
        $batas        = "DATE_SUB(NOW(), INTERVAL $hari_arsip DAY)";
        $total_pindah = 0;
        $putaran      = 0;
        $pesan_gagal  = '';

        while (true) {
            $putaran++;
            if ($putaran > 200) { $pesan_gagal = 'Batas putaran tercapai.'; break; }

            mysqli_begin_transaction($conn);
            try {
                $ok1 = mysqli_query($conn,
                    "INSERT INTO history_perjalanan_arsip ($daftar_kolom)
                     SELECT $daftar_kolom FROM history_perjalanan
                     WHERE waktu < $batas ORDER BY id LIMIT $ukuran_batch");
                if (!$ok1) { throw new Exception('Gagal menyalin: ' . mysqli_error($conn)); }
                $disalin = mysqli_affected_rows($conn);

                if ($disalin === 0) { mysqli_rollback($conn); break; } // sudah habis

                $ok2 = mysqli_query($conn,
                    "DELETE FROM history_perjalanan
                     WHERE waktu < $batas ORDER BY id LIMIT $ukuran_batch");
                if (!$ok2) { throw new Exception('Gagal menghapus: ' . mysqli_error($conn)); }
                $dihapus = mysqli_affected_rows($conn);

                if ($disalin !== $dihapus) {
                    throw new Exception("Jumlah tidak cocok (salin $disalin vs hapus $dihapus).");
                }

                mysqli_commit($conn);
                $total_pindah += $disalin;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $pesan_gagal = $e->getMessage();
                break;
            }
        }

        if ($pesan_gagal !== '') {
            echo '<div class="box warn">Berhenti di tengah jalan setelah memindah <b>'
               . number_format($total_pindah) . '</b> baris (batch terakhir dibatalkan, data aman).<br>'
               . htmlspecialchars($pesan_gagal) . '<br>Jalankan ulang untuk melanjutkan sisanya.</div>';
        } else {
            echo '<div class="box ok"><b>' . number_format($total_pindah) . '</b> baris berhasil dipindah ke '
               . '<code>history_perjalanan_arsip</code>. Data lama tetap tersimpan dan bisa ditarik kembali.</div>';
        }

        @mysqli_query($conn, "OPTIMIZE TABLE history_perjalanan");
    }
}

echo $jalankan
    ? '<div class="box ok">Optimasi selesai. <b>Sekarang hapus file <code>optimasi_database.php</code> ini.</b></div>'
    : '<div class="box">Jika sudah sesuai, jalankan ulang dengan <code>?jalankan=ya</code>.</div>';
