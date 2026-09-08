<?php
// pages/edit_invoice.php

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- 1. CEK KEAMANAN ---
wajib_akses('edit_invoice');

// --- 2. KONEKSI DATABASE ---
if (!isset($conn)) {
    if (file_exists('../config/koneksi.php')) {
        require '../config/koneksi.php';
    } elseif (file_exists('config/koneksi.php')) {
        require 'config/koneksi.php';
    } else {
        @include '../../config/koneksi.php'; 
    }
}

$no_faktur = $_GET['no_faktur'] ?? '';
$no_faktur_safe = mysqli_real_escape_string($conn, $no_faktur);
$q_trx = mysqli_query($conn, "SELECT * FROM transaksi WHERE no_faktur='$no_faktur_safe'");
$trx   = mysqli_fetch_assoc($q_trx);

if(!$trx) {
    echo "<div class='bg-white p-6 rounded shadow text-red-500 font-bold text-center'>Data Invoice tidak ditemukan.</div>";
    exit;
}

$pelanggan_id = $trx['pelanggan_id'] ?? 0;

// =========================================================================================
// PENGECEKAN STATUS MENDALAM (GEMBOK TOTAL BERLAPIS)
// =========================================================================================
$is_locked = false;

// KUNCI QUANTITY: role tertentu (mis. Accounting) boleh ubah harga TAPI bukan qty.
// Aturan ada di $BLOKIR_EDIT_QTY pada config/hak_akses.php.
$qty_terkunci = !boleh_edit_qty();
$status_tampil = strtoupper($trx['status']);
$status_trx_asli = strtolower(trim($trx['status']));

// 1. Kunci Otomatis Jika Status Transaksi Belum Selesai (Blokir Pending, Persiapan, Pengiriman)
if (in_array($status_trx_asli, ['pending', 'persiapan', 'pengiriman', 'batal'])) {
    $is_locked = true;
}

// 2. Kunci Berlapis: Cek Silang ke Tabel Pesanan (Jika ini adalah order online)
$q_cek_psn = mysqli_query($conn, "SELECT status FROM pesanan WHERE no_pesanan='$no_faktur_safe'");
if($q_cek_psn && mysqli_num_rows($q_cek_psn) > 0) {
    $d_psn = mysqli_fetch_assoc($q_cek_psn);
    $status_pesanan_online = strtolower(trim($d_psn['status']));
    
    // PASTIKAN SEMUA STATUS SELAIN 'SELESAI' DIGEMBOK TOTAL
    if($status_pesanan_online !== 'selesai') {
        $is_locked = true;
        $status_tampil = strtoupper($d_psn['status']); 
    }
}


// =========================================================================================
// 4. PROSES SIMPAN & SINKRONISASI STOK (METODE RESTORE & RE-DEDUCT TOTAL)
// =========================================================================================
if(isset($_POST['simpan_perubahan']) || isset($_POST['simpan_final']) || isset($_POST['simpan_lunas'])) {
    
    // --- BLOKIR BACKEND: Cegah eksekusi paksa jika invoice terkunci ---
    if ($is_locked) {
        echo "<script>alert('TINDAKAN DIBLOKIR: Anda tidak bisa menyimpan atau mengedit data saat pesanan masih berstatus $status_tampil.\\n\\nSelesaikan pesanan di menu Pesanan Masuk terlebih dahulu.'); window.history.back();</script>";
        exit;
    }
    
    mysqli_begin_transaction($conn);

    try {
        $status_awal = strtolower(trim($trx['status'])); 
        $status_bayar_awal = strtolower(trim($trx['status_bayar'] ?? 'belum'));
        
        if(isset($_POST['simpan_lunas'])) {
            $status_baru = 'selesai';
            $status_bayar_baru = 'lunas';
        } elseif(isset($_POST['simpan_final'])) {
            $status_baru = 'selesai';
            $status_bayar_baru = $status_bayar_awal; 
        } else {
            $status_baru = $status_awal;
            $status_bayar_baru = $status_bayar_awal;
        }

        // --- PENAMBAHAN FITUR LOG ---
        $log_aktivitas_arr = [];
        $snapshot_lama = [];
        $q_snap = mysqli_query($conn, "SELECT td.id, td.qty, td.harga_satuan, b.nama_barang FROM transaksi_detail td JOIN barang b ON td.barang_id = b.id WHERE td.no_faktur = '$no_faktur_safe'");
        while($s = mysqli_fetch_assoc($q_snap)) {
            $snapshot_lama[$s['id']] = $s;
        }

        // ---------------------------------------------------------------------------------
        // LANGKAH UTAMA 1: KEMBALIKAN SEMUA STOK LAMA YANG PERNAH DIPOTONG OLEH FAKTUR INI
        // ---------------------------------------------------------------------------------
        if ($status_awal == 'selesai') {
            $q_restore = mysqli_query($conn, "SELECT barang_id, qty FROM transaksi_detail WHERE no_faktur = '$no_faktur_safe'");
            while ($r_res = mysqli_fetch_assoc($q_restore)) {
                $brg_id_res = $r_res['barang_id'];
                $qty_res = (float)$r_res['qty'];
                mysqli_query($conn, "UPDATE barang SET stok = stok + $qty_res WHERE id = '$brg_id_res'");
            }
        }

        $total_baru = 0;

        // A. SINKRONISASI PENGHAPUSAN ITEM 
        // Menghapus item = mengubah quantity, jadi ikut diblokir untuk role yang terkunci.
        if(!empty($_POST['hapus_detail_id']) && !$qty_terkunci) {
            foreach($_POST['hapus_detail_id'] as $id_hapus) {
                $id_hapus_safe = mysqli_real_escape_string($conn, $id_hapus);
                if(isset($snapshot_lama[$id_hapus])) {
                    $log_aktivitas_arr[] = "Menghapus item " . $snapshot_lama[$id_hapus]['nama_barang'] . " (Qty: " . (float)$snapshot_lama[$id_hapus]['qty'] . ")";
                }
                mysqli_query($conn, "DELETE FROM transaksi_detail WHERE id='$id_hapus_safe'");
            }
        }

        // B. SINKRONISASI UPDATE ITEM LAMA 
        if(!empty($_POST['id_detail'])) {
            foreach($_POST['id_detail'] as $index => $id_det) {
                $id_det_safe  = mysqli_real_escape_string($conn, $id_det);
                $id_brg       = mysqli_real_escape_string($conn, $_POST['barang_id'][$index]);
                
                $harga_satuan = (float)str_replace(',', '.', $_POST['harga'][$index]); 
                $qty_baru     = (float)str_replace(',', '.', $_POST['qty'][$index]);

                // Role yang dilarang ubah qty: apapun yang dikirim dari browser diabaikan,
                // qty dikembalikan ke nilai asli dari database.
                if ($qty_terkunci && isset($snapshot_lama[$id_det])) {
                    $qty_baru = (float)$snapshot_lama[$id_det]['qty'];
                }

                $subtotal     = $harga_satuan * $qty_baru;

                if(isset($snapshot_lama[$id_det])) {
                    $old = $snapshot_lama[$id_det];
                    $perubahan_item = [];
                    if((float)$old['qty'] != $qty_baru) $perubahan_item[] = "Qty(" . (float)$old['qty'] . " jadi " . $qty_baru . ")";
                    if((float)$old['harga_satuan'] != $harga_satuan) $perubahan_item[] = "Harga(" . (float)$old['harga_satuan'] . " jadi " . $harga_satuan . ")";
                    if(!empty($perubahan_item)) {
                        $log_aktivitas_arr[] = "Edit " . $old['nama_barang'] . ": " . implode(", ", $perubahan_item);
                    }
                }

                $harga_sql    = number_format($harga_satuan, 2, '.', '');
                $qty_baru_sql = number_format($qty_baru, 2, '.', '');
                $subtotal_sql = number_format($subtotal, 2, '.', '');

                mysqli_query($conn, "UPDATE transaksi_detail SET harga_satuan = '$harga_sql', qty = '$qty_baru_sql', subtotal = '$subtotal_sql' WHERE id = '$id_det_safe'");
                
                if ($status_baru == 'selesai') {
                    mysqli_query($conn, "UPDATE barang SET stok = stok - $qty_baru_sql WHERE id = '$id_brg'");
                }

                $total_baru += $subtotal;
            }
        }

        // C. SINKRONISASI ITEM BARU 
        // Menambah item = menambah quantity, jadi ikut diblokir untuk role yang terkunci.
        if(!empty($_POST['new_barang']) && !$qty_terkunci) {
            foreach($_POST['new_barang'] as $idx => $id_brg_new) {
                if(!empty($id_brg_new) && !empty($_POST['new_qty'][$idx])) {
                    $id_brg_new_safe = mysqli_real_escape_string($conn, $id_brg_new);
                    
                    $qty_n = (float)str_replace(',', '.', $_POST['new_qty'][$idx]);
                    $hrg_n = (float)str_replace(',', '.', $_POST['new_harga'][$idx]);
                    $sub_n = $qty_n * $hrg_n;
                    
                    $q_namabrg = mysqli_query($conn, "SELECT nama_barang FROM barang WHERE id='$id_brg_new_safe'");
                    $d_namabrg = mysqli_fetch_assoc($q_namabrg);
                    $nama_brg_baru = $d_namabrg ? $d_namabrg['nama_barang'] : 'Unknown Item';
                    $log_aktivitas_arr[] = "Tambah item baru " . $nama_brg_baru . " (Qty: " . $qty_n . ", Harga: " . $hrg_n . ")";

                    $qty_n_db = number_format($qty_n, 2, '.', '');
                    $hrg_n_db = number_format($hrg_n, 2, '.', '');
                    $sub_n_db = number_format($sub_n, 2, '.', '');
                    
                    $q_hpp = mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='$id_brg_new_safe'");
                    $d_hpp = mysqli_fetch_assoc($q_hpp);
                    $hpp_db = $d_hpp ? number_format((float)$d_hpp['harga_beli'], 2, '.', '') : '0.00';
                    
                    mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, harga_satuan, qty, hpp, subtotal) 
                                         VALUES ('$no_faktur_safe', '$id_brg_new_safe', '$hrg_n_db', '$qty_n_db', '$hpp_db', '$sub_n_db')");
                    
                    if ($status_baru == 'selesai') {
                        mysqli_query($conn, "UPDATE barang SET stok = stok - $qty_n_db WHERE id = '$id_brg_new_safe'");
                    }

                    $total_baru += $sub_n;
                }
            }
        }

        // D. UPDATE TOTAL TRANSAKSI UTAMA
        $total_baru_db = number_format($total_baru, 2, '.', '');
        mysqli_query($conn, "UPDATE transaksi SET total_transaksi = '$total_baru_db', bayar = '$total_baru_db', status = '$status_baru', status_bayar = '$status_bayar_baru' WHERE no_faktur = '$no_faktur_safe'");
        
        if((float)$trx['total_transaksi'] != $total_baru) {
            $log_aktivitas_arr[] = "Total Tagihan berubah (Rp " . number_format((float)$trx['total_transaksi'], 0, ',', '.') . " jadi Rp " . number_format($total_baru, 0, ',', '.') . ")";
        }
        if(!empty($log_aktivitas_arr)) {
            $detail_log_str = "No Faktur: " . $no_faktur_safe . " | " . implode("; ", $log_aktivitas_arr);
            $detail_log_safe = mysqli_real_escape_string($conn, $detail_log_str);
            $user_id_log = mysqli_real_escape_string($conn, $_SESSION['user_id'] ?? 0);
            $nama_user_log = mysqli_real_escape_string($conn, $_SESSION['nama_user'] ?? 'System');
            $role_log = mysqli_real_escape_string($conn, $_SESSION['role'] ?? 'invoice');
            
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, nama_user, role, aksi, detail, tanggal) 
                                 VALUES ('$user_id_log', '$nama_user_log', '$role_log', 'Edit Invoice', '$detail_log_safe', NOW())");
        }

        mysqli_commit($conn);
                     
        echo "<script>
            alert('BERHASIL! Data berhasil disimpan. (Stok dipotong karena status Selesai).');
            window.location='index.php?page=edit_invoice&no_faktur=$no_faktur_safe';
        </script>";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Gagal menyimpan: Terjadi konflik di database.'); window.history.back();</script>";
    }
}
// =========================================================================================

$harga_khusus_dapur = [];
if ($pelanggan_id > 0) {
    $q_histori = mysqli_query($conn, "
        SELECT td.barang_id, td.harga_satuan 
        FROM transaksi_detail td
        JOIN transaksi t ON td.no_faktur = t.no_faktur
        WHERE t.pelanggan_id = '$pelanggan_id' AND t.status = 'selesai'
        ORDER BY t.tanggal ASC, t.id ASC
    ");
    while ($hist = mysqli_fetch_assoc($q_histori)) {
        $harga_khusus_dapur[$hist['barang_id']] = (float)$hist['harga_satuan']; 
    }
}

$q_all_barang = mysqli_query($conn, "SELECT id, nama_barang, kode_barang, harga_jual FROM barang ORDER BY nama_barang ASC");
$options_barang = "<option value=''>-- Pilih Barang --</option>";
while($b = mysqli_fetch_assoc($q_all_barang)) {
    $harga_db = (float)$b['harga_jual'];
    $harga_fix = isset($harga_khusus_dapur[$b['id']]) ? $harga_khusus_dapur[$b['id']] : $harga_db;
    $nama_aman = htmlspecialchars($b['nama_barang'], ENT_QUOTES, 'UTF-8');
    $options_barang .= "<option value='{$b['id']}' data-harga='{$harga_fix}'>{$nama_aman} ({$b['kode_barang']}) - Rp " . number_format($harga_fix, 0, ',', '.') . "</option>";
}

$details = mysqli_query($conn, "SELECT td.*, b.nama_barang, b.kode_barang, b.id as id_barang_asli 
                                FROM transaksi_detail td 
                                JOIN barang b ON td.barang_id = b.id 
                                WHERE td.no_faktur='$no_faktur_safe'");

$q_pel = mysqli_query($conn, "SELECT nama_pelanggan FROM pelanggan WHERE id='$pelanggan_id'");
$d_pel = mysqli_fetch_assoc($q_pel);
$nama_dapur = $d_pel ? $d_pel['nama_pelanggan'] : 'Umum';
?>

<div class="bg-white p-6 rounded-lg shadow-lg">

    <?php if($is_locked): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-r shadow-sm">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fa-solid fa-lock text-yellow-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-bold text-yellow-800 uppercase">INVOICE TERKUNCI (STATUS: <?= $status_tampil ?>)</h3>
                <div class="mt-1 text-sm text-yellow-700">
                    <p>Pesanan ini belum berstatus Selesai. Anda <b>tidak bisa mengubah data atau qty</b> pada tahap ini untuk menghindari kebocoran/selisih stok gudang.</p>
                    <p class="mt-2 font-bold text-red-600">Silakan proses pesanan dan ubah status menjadi SELESAI melalui menu "Pesanan Masuk" terlebih dahulu!</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($qty_terkunci && !$is_locked): ?>
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-r shadow-sm flex items-center gap-3">
        <i class="fa-solid fa-lock text-blue-500 text-xl"></i>
        <div class="text-sm text-blue-800">
            <b class="uppercase">Quantity Terkunci</b>
            <p>Role <b><?= htmlspecialchars(label_role(role_saya())) ?></b> boleh menyesuaikan harga, tetapi <b>tidak boleh mengubah quantity</b> barang.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Cek & Edit Invoice</h2>
            <p class="text-sm text-gray-500">
                No. Faktur: <span class="font-mono font-bold text-indigo-600"><?= htmlspecialchars($no_faktur) ?></span> 
                | Dapur Tujuan: <span class="font-bold text-emerald-600 uppercase"><?= htmlspecialchars($nama_dapur) ?></span>
            </p>
        </div>
        <a href="index.php?page=list_surat_jalan" class="text-gray-500 hover:text-gray-800 font-bold text-sm">Kembali</a>
    </div>

    <form method="POST" id="formInvoice">
        <div id="container_hapus"></div>
        <div class="overflow-x-auto border rounded-lg mb-4">
            <table class="w-full text-sm text-left">
                <thead class="bg-indigo-50 text-indigo-700 uppercase font-bold">
                    <tr>
                        <th class="p-3">Nama Barang</th>
                        <th class="p-3 w-24 text-center">Qty Fix</th>
                        <th class="p-3 w-40 text-right">Harga Satuan (Rp)</th>
                        <th class="p-3 w-40 text-right">Subtotal</th>
                        <th class="p-3 w-12 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y" id="tbody_items">
                    <?php 
                    $grand_total = 0;
                    $input_state = ($is_locked || $qty_terkunci) ? 'readonly class="w-full border p-2 rounded text-center font-bold bg-gray-100 text-gray-500 cursor-not-allowed input-qty" title="Role Anda tidak boleh mengubah quantity"' : 'class="w-full border p-2 rounded text-center font-bold focus:ring-2 focus:ring-indigo-500 input-qty"';
                    $harga_state = $is_locked ? 'readonly class="w-full border p-2 rounded text-right font-bold bg-gray-100 text-gray-500 cursor-not-allowed input-harga"' : 'class="w-full border p-2 rounded text-right font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-500 input-harga"';
                    
                    while($row = mysqli_fetch_assoc($details)): 
                        $grand_total += $row['subtotal'];
                        $qty_clean = str_replace('.00', '', rtrim(number_format((float)$row['qty'], 2, '.', ''), '0')); 
                        if(substr($qty_clean, -1) == '.') $qty_clean = rtrim($qty_clean, '.');
                        $harga_satuan_clean = (float)$row['harga_satuan'];
                    ?>
                    <tr class="hover:bg-gray-50 item-row">
                        <td class="p-3">
                            <div class="font-bold text-gray-700"><?= htmlspecialchars($row['nama_barang']) ?></div>
                            <div class="text-[10px] text-gray-400"><?= htmlspecialchars($row['kode_barang']) ?></div>
                            <input type="hidden" name="id_detail[]" value="<?= $row['id'] ?>">
                            <input type="hidden" name="barang_id[]" value="<?= $row['id_barang_asli'] ?>">
                        </td>
                        <td class="p-3">
                            <input type="number" step="any" min="0" name="qty[]" value="<?= $qty_clean ?>" <?= $input_state ?> oninput="hitungTotal()" required>
                        </td>
                        <td class="p-3">
                            <input type="number" step="any" min="0" name="harga[]" value="<?= $harga_satuan_clean ?>" <?= $harga_state ?> oninput="hitungTotal()" required>
                        </td>
                        <td class="p-3 text-right font-bold text-gray-700 display-subtotal">
                            <?= number_format($row['subtotal'], 0, ',', '.') ?>
                        </td>
                        <td class="p-3 text-center">
                            <?php if(!$is_locked && !$qty_terkunci): ?>
                                <button type="button" class="text-red-500 font-bold" onclick="hapusRowLama('<?= $row['id'] ?>', this)"><i class="fa-solid fa-trash"></i></button>
                            <?php else: ?>
                                <span class="text-gray-300"><i class="fa-solid fa-lock"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="bg-gray-100 font-bold text-gray-700">
                    <tr>
                        <td colspan="3" class="p-3 text-right">TOTAL TAGIHAN:</td>
                        <td class="p-3 text-right text-indigo-700 text-lg" id="grandTotalDisplay">
                            Rp <?= number_format($grand_total, 0, ',', '.') ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if(!$is_locked): ?>
            <?php if(!$qty_terkunci): ?>
            <div class="mb-8">
                <button type="button" onclick="tambahBarisBaru()" class="bg-emerald-500 text-white px-4 py-2 rounded shadow font-bold text-sm">
                    <i class="fa-solid fa-plus"></i> Tambah Item Baru
                </button>
            </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row justify-end gap-3 border-t pt-4">
                <button type="submit" name="simpan_perubahan" onclick="return confirm('Yakin simpan perubahan?')" class="bg-amber-500 text-white px-6 py-3 rounded-lg font-bold">
                     <?= (strtolower(trim($trx['status'])) == 'selesai') ? 'SIMPAN & SINKRONISASI STOK' : 'SIMPAN DRAFT' ?>
                </button>
                <?php if(strtolower(trim($trx['status_bayar'] ?? '')) != 'lunas'): ?>
                <button type="submit" name="simpan_lunas" class="bg-green-600 text-white px-8 py-3 rounded-lg font-bold">LUNAS (MASUK OMSET)</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mt-6 bg-gray-100 p-5 rounded-lg border border-gray-300 text-center">
                <i class="fa-solid fa-user-lock text-gray-400 text-3xl mb-2"></i>
                <p class="text-gray-600 font-bold">Fungsi Edit dan Simpan Dinonaktifkan Sementara</p>
                <p class="text-xs text-gray-500 mt-1">Status orderan ini masih <b><?= $status_tampil ?></b>. Silakan selesaikan orderan dari menu Pesanan Masuk.</p>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function hitungTotal() {
    let rows = document.querySelectorAll('.item-row');
    let grandTotal = 0;

    rows.forEach(row => {
        let valQty = row.querySelector('.input-qty').value.replace(',', '.');
        let valHarga = row.querySelector('.input-harga').value.replace(',', '.');
        
        let qty = parseFloat(valQty) || 0;
        let harga = parseFloat(valHarga) || 0;
        let subtotal = qty * harga;

        row.querySelector('.display-subtotal').innerText = subtotal.toLocaleString('id-ID');
        grandTotal += subtotal;
    });
    document.getElementById('grandTotalDisplay').innerText = "Rp " + grandTotal.toLocaleString('id-ID');
}

function hapusRowLama(idDetail, btn) {
    if(confirm('Yakin ingin menghapus item ini? Stok akan otomatis dikembalikan jika sebelumnya telah selesai.')) {
        let input = document.createElement("input");
        input.type = "hidden"; input.name = "hapus_detail_id[]"; input.value = idDetail;
        document.getElementById('container_hapus').appendChild(input);
        btn.closest('tr').remove();
        hitungTotal();
    }
}

function hapusRowBaru(btn) { btn.closest('tr').remove(); hitungTotal(); }

function tambahBarisBaru() {
    let tbody = document.getElementById('tbody_items');
    let options = `<?= $options_barang ?>`;
    let tr = document.createElement('tr');
    tr.className = "hover:bg-gray-50 item-row";
    tr.innerHTML = `
        <td class="p-3"><select name="new_barang[]" class="w-full border p-2 rounded text-sm" onchange="setHargaBaru(this)" required>${options}</select></td>
        <td class="p-3"><input type="number" step="any" min="0" name="new_qty[]" value="1" class="w-full border p-2 text-center input-qty" oninput="hitungTotal()" required></td>
        <td class="p-3"><input type="number" step="any" min="0" name="new_harga[]" value="0" class="w-full border p-2 text-right input-harga" oninput="hitungTotal()" required></td>
        <td class="p-3 text-right font-bold display-subtotal">0</td>
        <td class="p-3 text-center"><button type="button" class="text-red-500 font-bold" onclick="hapusRowBaru(this)"><i class="fa-solid fa-trash"></i></button></td>
    `;
    tbody.appendChild(tr); hitungTotal();
}

function setHargaBaru(selectEl) {
    let option = selectEl.options[selectEl.selectedIndex];
    let harga = parseFloat(option.getAttribute('data-harga')) || 0;
    selectEl.closest('tr').querySelector('.input-harga').value = harga;
    hitungTotal();
}
</script>