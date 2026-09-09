<?php
// Pastikan Session & Koneksi Aman
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';
require_once 'config/koneksi.php';

$id_usaha = $_SESSION['id_usaha'] ?? 1;

// CEK AKSES: Hanya Admin/Manager
wajib_akses('approval');

// ==========================================
// PROSES EKSEKUSI (APPROVE / REJECT)
// ==========================================
if(isset($_POST['aksi_respon'])) {
    $id_req = mysqli_real_escape_string($conn, $_POST['id_req']);
    $aksi   = $_POST['aksi_respon']; // 'approve' atau 'reject'
    $admin_id = $_SESSION['user_id'];

    // Ambil Data Request
    $q_req = mysqli_query($conn, "SELECT * FROM approval_request WHERE id='$id_req' AND status='pending'");
    $req   = mysqli_fetch_assoc($q_req);

    if($req) {
        // --- JIKA DITOLAK ---
        if($aksi == 'reject') {
            mysqli_query($conn, "UPDATE approval_request SET status='rejected', tgl_respon=NOW(), responden_id='$admin_id' WHERE id='$id_req'");
            echo "<script>alert('Permintaan DITOLAK!'); window.location='index.php?page=approval';</script>";
        } 
        // --- JIKA DISETUJUI ---
        elseif($aksi == 'approve') {
            $data = json_decode($req['data_json'], true);
            $berhasil = false;
            $tipe = $req['tipe_aksi'];

            // 1. UPDATE STOK & HARGA (Dari Menu Mobile/Barang)
            if($tipe == 'update_stok') {
                $id_brg = $data['id_barang'];
                $stok   = $data['stok_baru'];
                $harga  = $data['harga_baru'] ?? $data['harga_jual']; // Handle beda nama field
                
                // Simpan Riwayat Harga Dulu
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli, harga_jual FROM barang WHERE id='$id_brg'"));
                if($old && $old['harga_jual'] != $harga) {
                    mysqli_query($conn, "INSERT INTO riwayat_harga (barang_id, harga_beli_lama, harga_beli_baru, harga_jual_lama, harga_jual_baru, tgl_perubahan, user_id) VALUES ('$id_brg', '{$old['harga_beli']}', '{$old['harga_beli']}', '{$old['harga_jual']}', '$harga', NOW(), '$admin_id')");
                }

                // Update Barang
                $run = mysqli_query($conn, "UPDATE barang SET stok='$stok', harga_jual='$harga' WHERE id='$id_brg'");
                if($run) $berhasil = true;
            }
            
            // 2. TAMBAH BARANG BARU
            elseif($tipe == 'tambah_barang') {
                $kode = $data['kode_barang'];
                $nama = $data['nama_barang'];
                // ... Ambil field lain
                $kat = $data['kategori']; $sup = $data['supplier_id']; $sat = $data['satuan'];
                $hb = $data['harga_beli']; $hh = $data['harga_head']; $hj = $data['harga_jual'];
                $stok = $data['stok_baru']; $min = $data['minimal_order'];
                $kat_id = $data['kategori_id'] ?? null;
                $kat_id_sql = $kat_id === null ? 'NULL' : "'" . (int)$kat_id . "'";

                $run = mysqli_query($conn, "INSERT INTO barang (id_usaha, kode_barang, nama_barang, kategori, kategori_id, supplier_id, satuan, harga_beli, harga_head, harga_jual, stok, minimal_order) VALUES ('$id_usaha', '$kode', '$nama', '$kat', $kat_id_sql, '$sup', '$sat', '$hb', '$hh', '$hj', '$stok', '$min')");
                if($run) $berhasil = true;
            }

            // 3. INPUT SURAT JALAN (Transaksi Keluar)
            elseif($tipe == 'input_sj') {
                $no_faktur = "SJ-" . date('YmdHis');
                $pel_id = $data['pelanggan_id'];
                $driver = $data['nama_driver'];
                $nopol  = $data['nopol'];
                $total  = $data['total'];
                $tgl    = $data['tanggal'] . ' ' . date('H:i:s');

                // Header
                $q_head = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, kembalian, pelanggan_id, nama_driver, nopol, user_id, status, status_bayar, tanggal) 
                           VALUES ('$id_usaha', '$no_faktur', 'keluar', '$total', '$total', 0, '$pel_id', '$driver', '$nopol', '{$req['user_id']}', 'selesai', 'lunas', '$tgl')";
                
                if(mysqli_query($conn, $q_head)) {
                    foreach($data['items'] as $item) {
                        $id_b = $item['id_barang'];
                        $qty  = $item['qty'];
                        $hrg  = $item['harga'];
                        $sub  = $qty * $hrg;
                        
                        // Ambil HPP
                        $d_brg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='$id_b'"));
                        $hpp = $d_brg['harga_beli'] ?? 0;

                        // Detail
                        mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal) VALUES ('$no_faktur', '$id_b', '$qty', '$hrg', '$hpp', '$sub')");
                        // Kurangi Stok
                        mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id='$id_b'");
                    }
                    $berhasil = true;
                }
            }

            // 4. BUAT PO (Pembelian / Barang Masuk)
            elseif($tipe == 'buat_po') {
                $no_po = "PO-" . date('YmdHis');
                $sup_id = $data['supplier_id'];
                $total  = $data['total'];

                // Header
                $q_head = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, supplier_id, user_id, status, status_bayar, tanggal) 
                           VALUES ('$id_usaha', '$no_po', 'masuk', '$total', '$sup_id', '{$req['user_id']}', 'pending', 'belum', NOW())"; // PO status awal pending (belum diterima barangnya)
                
                if(mysqli_query($conn, $q_head)) {
                    foreach($data['items'] as $item) {
                        $nama_brg = mysqli_real_escape_string($conn, $item['nama_barang']);
                        
                        // Cek Barang (Ada/Baru)
                        $cek_b = mysqli_query($conn, "SELECT id FROM barang WHERE nama_barang='$nama_brg' AND id_usaha='$id_usaha' LIMIT 1");
                        if(mysqli_num_rows($cek_b) > 0) {
                            $b = mysqli_fetch_assoc($cek_b);
                            $id_b = $b['id'];
                            // Update Harga
                            mysqli_query($conn, "UPDATE barang SET harga_beli='{$item['harga']}', harga_jual='{$item['harga_jual']}' WHERE id='$id_b'");
                        } else {
                            // Barang Baru
                            $max = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) as m FROM barang"));
                            $kode = "BRG" . sprintf("%03s", $max['m'] + 1);
                            mysqli_query($conn, "INSERT INTO barang (id_usaha, kode_barang, nama_barang, satuan, harga_beli, harga_jual, stok) VALUES ('$id_usaha', '$kode', '$nama_brg', '{$item['satuan']}', '{$item['harga']}', '{$item['harga_jual']}', 0)");
                            $id_b = mysqli_insert_id($conn);
                        }

                        // Detail
                        mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, subtotal) VALUES ('$no_po', '$id_b', '{$item['qty']}', '{$item['harga']}', '{$item['subtotal']}')");
                    }
                    $berhasil = true;
                }
            }

            // --- FINALISASI ---
            if($berhasil) {
                mysqli_query($conn, "UPDATE approval_request SET status='approved', tgl_respon=NOW(), responden_id='$admin_id' WHERE id='$id_req'");
                echo "<script>alert('Permintaan DISETUJUI! Data sistem telah diperbarui.'); window.location='index.php?page=approval';</script>";
            } else {
                echo "<script>alert('Terjadi kesalahan saat mengeksekusi data ke database.');</script>";
            }
        }
    } else {
        echo "<script>alert('Data request tidak valid!'); window.location='index.php?page=approval';</script>";
    }
}
?>

<div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-slate-800 flex items-center">
            <i class="fa-solid fa-clipboard-check text-indigo-600 mr-2"></i> Persetujuan (Approval)
        </h2>
        <span class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-bold">
            Admin/Manager Mode
        </span>
    </div>

    <?= render_filter('Cari tipe aksi / keterangan / pemohon...') ?>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-xs">
                <tr>
                    <th class="p-4 border-b">Waktu Request</th>
                    <th class="p-4 border-b">User (PO)</th>
                    <th class="p-4 border-b">Tipe</th>
                    <th class="p-4 border-b">Keterangan & Rincian</th>
                    <th class="p-4 border-b text-center w-48">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                // LEFT JOIN agar user yg dihapus tetap muncul datanya
                // Paginasi & filter sisi server
                $a_cari  = ambil_kata_kunci();
                $a_hal   = ambil_halaman();
                $a_limit = ambil_per_halaman();

                [$a_where, $a_params, $a_tipe] = bangun_filter(
                    $a_cari, ['r.tipe_aksi', 'r.keterangan', 'u.nama'],
                    ["r.status='pending'", 'r.id_usaha = ?'], [$id_usaha], 'i'
                );

                $a_total  = hitung_total($conn,
                    'approval_request r LEFT JOIN users u ON r.user_id = u.id',
                    $a_where, $a_params, $a_tipe);
                $a_hal    = batasi_halaman($a_hal, $a_total, $a_limit);
                $a_offset = ($a_hal - 1) * $a_limit;

                $a_rows = ambil_data($conn,
                    'SELECT r.*, u.nama as nama_user FROM approval_request r LEFT JOIN users u ON r.user_id = u.id',
                    $a_where, $a_params, $a_tipe, 'ORDER BY r.tgl_request DESC', $a_limit, $a_offset);

                if($a_rows):
                    foreach($a_rows as $row):
                        // Badge warna & User Check
                        $badge_cls = 'bg-gray-100 text-gray-700';
                        if($row['tipe_aksi'] == 'update_stok') $badge_cls = 'bg-blue-100 text-blue-700';
                        if($row['tipe_aksi'] == 'tambah_barang') $badge_cls = 'bg-green-100 text-green-700'; // Tambahan warna untuk tambah barang
                        if($row['tipe_aksi'] == 'input_sj') $badge_cls = 'bg-orange-100 text-orange-700';
                        if($row['tipe_aksi'] == 'buat_po') $badge_cls = 'bg-purple-100 text-purple-700';
                        
                        $user_show = $row['nama_user'] ? $row['nama_user'] : "<span class='text-red-400 italic'>(User Hilang)</span>";
                        $dt_json = json_decode($row['data_json'], true);
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-4 align-top whitespace-nowrap text-slate-500">
                        <div class="font-bold"><?= date('d M Y', strtotime($row['tgl_request'])) ?></div>
                        <div class="text-xs"><?= date('H:i', strtotime($row['tgl_request'])) ?> WIB</div>
                    </td>
                    <td class="p-4 align-top font-bold text-slate-700">
                        <?= $user_show ?>
                    </td>
                    <td class="p-4 align-top">
                        <span class="<?= $badge_cls ?> px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide">
                            <?= str_replace('_', ' ', $row['tipe_aksi']) ?>
                        </span>
                    </td>
                    <td class="p-4 align-top text-slate-600 leading-relaxed">
                        <?= nl2br($row['keterangan']) ?>
                        
                        <?php 
                        // KONDISI 1: UPDATE STOK (Barang Lama Ditambah)
                        if($row['tipe_aksi'] == 'update_stok') {
                            $id_brg_cek = $dt_json['id_barang'] ?? 0;
                            $q_cek = mysqli_query($conn, "SELECT stok FROM barang WHERE id='$id_brg_cek'");
                            $d_cek = mysqli_fetch_assoc($q_cek);
                            
                            if($d_cek) {
                                $stok_db   = $d_cek['stok'];
                                $stok_baru = $dt_json['stok_baru'];
                                $selisih   = $stok_baru - $stok_db;
                                $tanda     = ($selisih >= 0) ? '+' : '';
                                $harga_dk  = $dt_json['harga_dapur_kereta'] ?? '-';
                                $gabek     = $dt_json['gabek'] ?? '-';
                        ?>
                            <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-md p-3 text-sm">
                                <div class="font-bold text-slate-700 mb-1 border-b border-yellow-200 pb-1">
                                    <i class="fa-solid fa-boxes-stacked"></i> Rincian Penambahan:
                                </div>
                                <div class="grid grid-cols-2 gap-x-4">
                                    <div class="text-gray-500">Stok Awal:</div>
                                    <div class="font-mono"><?= $stok_db ?></div>
                                    <div class="text-gray-500">Stok Baru:</div>
                                    <div class="font-mono"><?= $stok_baru ?></div>
                                    
                                    <div class="col-span-2 bg-white rounded p-1 border border-green-200 text-center mt-2">
                                        <span class="text-xs text-gray-400 uppercase">Input Penambahan</span><br>
                                        <strong class="text-lg text-green-600"><?= $tanda . $selisih ?> Pcs</strong>
                                    </div>

                                    <?php if($harga_dk != '-'): ?>
                                    <div class="mt-2 text-gray-500 col-span-2 pt-1 border-t border-yellow-200">Harga Dapur Kereta:</div>
                                    <div class="col-span-2 font-bold">Rp <?= number_format($harga_dk,0,',','.') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            } 
                        }
                        
                        // KONDISI 2: TAMBAH BARANG (Barang Baru) - INI YANG ANDA MINTA
                        elseif($row['tipe_aksi'] == 'tambah_barang') {
                            $stok_perdana = $dt_json['stok_baru'] ?? 0;
                            $harga_jual   = $dt_json['harga_jual'] ?? 0;
                        ?>
                            <div class="mt-3 bg-green-50 border border-green-200 rounded-md p-3 text-sm">
                                <div class="font-bold text-slate-700 mb-1 border-b border-green-200 pb-1">
                                    <i class="fa-solid fa-box-open"></i> Rincian Barang Baru:
                                </div>
                                <div class="grid grid-cols-2 gap-x-4">
                                    <div class="text-gray-500">Stok Awal:</div>
                                    <div class="font-bold text-lg text-green-700"><?= $stok_perdana ?> Pcs</div>
                                    
                                    <div class="text-gray-500">Harga Jual:</div>
                                    <div class="font-mono">Rp <?= number_format($harga_jual,0,',','.') ?></div>
                                </div>
                            </div>
                        <?php } ?>
                        </td>
                    <td class="p-4 align-top text-center">
                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin?');" class="flex flex-col gap-2">
                            <input type="hidden" name="id_req" value="<?= $row['id'] ?>">
                            
                            <button type="submit" name="aksi_respon" value="approve" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-2 rounded-lg shadow-sm text-xs font-bold transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-check"></i> SETUJUI
                            </button>
                            
                            <button type="submit" name="aksi_respon" value="reject" class="w-full bg-white border border-red-200 text-red-500 hover:bg-red-50 px-3 py-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-xmark"></i> TOLAK
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="p-12 text-center text-slate-400 flex flex-col items-center">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                            <i class="fa-solid fa-check-double text-2xl opacity-30"></i>
                        </div>
                        <span class="font-medium">Semua permintaan sudah diproses.</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($a_hal, $a_total, $a_limit) ?>
</div>