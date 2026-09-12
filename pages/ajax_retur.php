<?php
// pages/ajax_retur.php
session_start();
// Matikan error reporting agar warning tidak merusak format JSON
error_reporting(0);
ini_set('display_errors', 0);

require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
wajib_login_ajax(['admin', 'po', 'gudang', 'invoice', 'pelanggan']);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// [BARU] AMBIL ITEM DARI SURAT JALAN (TRANSAKSI) UNTUK DITAMPILKAN DI MODAL RETUR PELANGGAN
if($action == 'get_items_faktur') {
    $user_id   = $_SESSION['user_id'];
    $no_faktur = mysqli_real_escape_string($conn, $_POST['no_faktur']);

    // Pastikan surat jalan ini benar-benar milik pelanggan yang login & sudah selesai dikirim
    $cek_faktur = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.no_faktur FROM transaksi t
        JOIN pesanan ps ON t.no_faktur = ps.no_pesanan
        WHERE t.no_faktur='$no_faktur' AND t.jenis_transaksi='keluar' AND t.status='selesai' AND ps.user_id='$user_id'
    "));

    if(!$cek_faktur) {
        echo json_encode(['status' => 'error', 'msg' => 'Surat jalan tidak ditemukan atau bukan milik Anda']);
        exit;
    }

    $items = [];
    $q_item = mysqli_query($conn, "SELECT td.barang_id AS id_barang, td.qty, b.nama_barang, b.satuan
                                   FROM transaksi_detail td
                                   JOIN barang b ON td.barang_id = b.id
                                   WHERE td.no_faktur = '$no_faktur'");
    while($d = mysqli_fetch_assoc($q_item)) {
        $items[] = [
            'id_barang'   => $d['id_barang'],
            'nama_barang' => $d['nama_barang'],
            'qty_kirim'   => (float)$d['qty'],
            'satuan'      => $d['satuan']
        ];
    }
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// [BARU] AMBIL ITEM DARI PESANAN UNTUK DITAMPILKAN DI MODAL RETUR
if($action == 'get_items_pesanan') {
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    
    // Ambil ID pesanan berdasarkan no_pesanan
    $cek_pesanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM pesanan WHERE no_pesanan='$no_pesanan'"));
    
    if($cek_pesanan) {
        $id_pesanan = $cek_pesanan['id'];
        $items = [];
        
        $q_item = mysqli_query($conn, "SELECT d.id_barang, d.qty, b.nama_barang, b.satuan 
                                       FROM pesanan_detail d 
                                       JOIN barang b ON d.id_barang = b.id 
                                       WHERE d.id_pesanan = '$id_pesanan'");
        
        while($d = mysqli_fetch_assoc($q_item)) {
            $items[] = [
                'id_barang' => $d['id_barang'],
                'nama_barang' => $d['nama_barang'],
                'qty_beli' => (float)$d['qty'],
                'satuan' => $d['satuan']
            ];
        }
        echo json_encode(['status' => 'success', 'items' => $items]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Pesanan tidak ditemukan']);
    }
    exit;
}

// 1. PROSES PENGAJUAN RETUR (PELANGGAN)
if($action == 'ajukan_retur') {
    $id_usaha   = $_SESSION['id_usaha'];
    $user_id    = $_SESSION['user_id'];
    $no_pesanan = mysqli_real_escape_string($conn, $_POST['no_pesanan']);
    $alasan     = mysqli_real_escape_string($conn, $_POST['alasan']);
    $no_retur   = "RET-" . date('ymdHis');

    // Role pelanggan hanya boleh meretur surat jalan miliknya sendiri, yang sudah selesai dikirim,
    // dan belum pernah diajukan retur sebelumnya.
    if(strtolower($_SESSION['role'] ?? '') === 'pelanggan') {
        $cek_faktur = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT t.no_faktur FROM transaksi t
            JOIN pesanan ps ON t.no_faktur = ps.no_pesanan
            WHERE t.no_faktur='$no_pesanan' AND t.jenis_transaksi='keluar' AND t.status='selesai' AND ps.user_id='$user_id'
        "));
        if(!$cek_faktur) {
            echo json_encode(['status' => 'error', 'msg' => 'Surat jalan tidak ditemukan atau bukan milik Anda']);
            exit;
        }

        $cek_dobel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM retur WHERE no_pesanan='$no_pesanan'"));
        if($cek_dobel) {
            echo json_encode(['status' => 'error', 'msg' => 'Surat jalan ini sudah pernah diajukan retur']);
            exit;
        }
    }

    // Validasi Item
    $barang_ids = $_POST['barang_id'] ?? [];
    $qtys       = $_POST['qty_retur'] ?? [];
    $has_item   = false;

    // Cek apakah ada item yang diretur > 0
    foreach($qtys as $q) { if((float)$q > 0) $has_item = true; }

    if(!$has_item) {
        echo json_encode(['status' => 'error', 'msg' => 'Harap isi jumlah barang yang ingin diretur (minimal 1)']);
        exit;
    }

    // Qty retur tidak boleh melebihi qty yang benar-benar dikirim di surat jalan tersebut
    foreach($barang_ids as $k => $id_brg) {
        $qty_minta = (float)($qtys[$k] ?? 0);
        if($qty_minta <= 0) continue;

        $id_brg_safe = mysqli_real_escape_string($conn, $id_brg);
        $q_kirim = mysqli_fetch_assoc(mysqli_query($conn, "SELECT qty FROM transaksi_detail WHERE no_faktur='$no_pesanan' AND barang_id='$id_brg_safe'"));
        $qty_kirim = $q_kirim ? (float)$q_kirim['qty'] : 0;

        if($qty_minta > $qty_kirim) {
            echo json_encode(['status' => 'error', 'msg' => 'Jumlah retur melebihi jumlah barang yang dikirim']);
            exit;
        }
    }

    // Upload Foto Bukti
    $foto_nama = null;
    if(!empty($_FILES['foto']['name'])){
        $target_dir = "../assets/img/bukti_retur/";
        
        // Buat folder jika belum ada (Mencegah warning error)
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nama = "retur_" . time() . "." . $ext;
        
        // Pindahkan file
        move_uploaded_file($_FILES['foto']['tmp_name'], $target_dir . $foto_nama);
    }

    // Insert Header Retur
    $q = mysqli_query($conn, "INSERT INTO retur (id_usaha, no_retur, no_pesanan, user_id, alasan, foto_bukti, status)
                              VALUES ('$id_usaha', '$no_retur', '$no_pesanan', '$user_id', '$alasan', '$foto_nama', 'Menunggu')");

    if($q) {
        $retur_id = mysqli_insert_id($conn);

        // Insert Detail Barang yang diretur
        foreach($barang_ids as $k => $id_brg) {
            $qty = (float)$qtys[$k];
            if($qty > 0) {
                mysqli_query($conn, "INSERT INTO retur_detail (retur_id, barang_id, qty) VALUES ('$retur_id', '$id_brg', '$qty')");
            }
        }
        echo json_encode(['status' => 'success', 'msg' => 'Pengajuan Retur Berhasil! Menunggu keputusan Admin.']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal menyimpan data DB: ' . mysqli_error($conn)]);
    }
    exit;
}

// 2. PROSES KEPUTUSAN RETUR (ADMIN/PO/GUDANG/INVOICE)
// keputusan: 'Ditolak' | 'Mengganti Barang' | 'Potong Jumlah Invoice'
if($action == 'proses_retur') {
    $id_retur  = (int) $_POST['id_retur'];
    $keputusan = $_POST['keputusan'];
    $catatan   = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');

    $keputusan_valid = ['Ditolak', 'Mengganti Barang', 'Potong Jumlah Invoice'];
    if(!in_array($keputusan, $keputusan_valid, true)) {
        echo json_encode(['status' => 'error', 'msg' => 'Keputusan tidak valid']);
        exit;
    }

    $retur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM retur WHERE id='$id_retur'"));
    if(!$retur) {
        echo json_encode(['status' => 'error', 'msg' => 'Retur tidak ditemukan']);
        exit;
    }
    if($retur['status'] !== 'Menunggu') {
        echo json_encode(['status' => 'error', 'msg' => 'Retur ini sudah diproses sebelumnya']);
        exit;
    }

    // ------------------------------------------------------------
    // A. DITOLAK: cukup ubah status, tidak ada efek stok/invoice
    // ------------------------------------------------------------
    if($keputusan === 'Ditolak') {
        mysqli_query($conn, "UPDATE retur SET status='Ditolak', respon_admin='$catatan' WHERE id='$id_retur'");
        echo json_encode(['status' => 'success', 'msg' => 'Retur ditolak']);
        exit;
    }

    $detail_list = [];
    $q_det = mysqli_query($conn, "SELECT * FROM retur_detail WHERE retur_id='$id_retur'");
    while($d = mysqli_fetch_assoc($q_det)) { $detail_list[] = $d; }

    if(!$detail_list) {
        echo json_encode(['status' => 'error', 'msg' => 'Tidak ada item pada retur ini']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // --------------------------------------------------------
        // B. MENGGANTI BARANG: seluruh qty retur -> "Dikembalikan ke Warehouse"
        //    (tidak menambah stok jual; barang dianggap rusak/tidak layak jual ulang)
        // --------------------------------------------------------
        if($keputusan === 'Mengganti Barang') {
            foreach($detail_list as $d) {
                mysqli_query($conn, "INSERT INTO retur_alokasi (retur_detail_id, qty_warehouse, qty_stok_gudang, qty_waste)
                                     VALUES ('{$d['id']}', '{$d['qty']}', 0, 0)");
            }
            mysqli_query($conn, "UPDATE retur SET status='Mengganti Barang', respon_admin='$catatan' WHERE id='$id_retur'");
        }

        // --------------------------------------------------------
        // C. POTONG JUMLAH INVOICE: admin membagi tiap item ke 3 kategori.
        //    - qty_stok_gudang -> menambah barang.stok (layak jual lagi)
        //    - qty_warehouse & qty_waste -> tidak menambah stok
        //    - Nilai invoice (transaksi.total_transaksi) dipotong sebesar qty retur x harga_satuan
        // --------------------------------------------------------
        if($keputusan === 'Potong Jumlah Invoice') {
            $alokasi_post = $_POST['alokasi'] ?? []; // [retur_detail_id => ['warehouse'=>x,'gudang'=>y,'waste'=>z]]
            $potongan_invoice = [];

            foreach($detail_list as $d) {
                $a = $alokasi_post[$d['id']] ?? null;
                if(!$a) { throw new Exception('Alokasi untuk salah satu item belum diisi'); }

                $qw = (float)($a['warehouse'] ?? 0);
                $qg = (float)($a['gudang'] ?? 0);
                $qs = (float)($a['waste'] ?? 0);
                $total_alokasi = $qw + $qg + $qs;

                if(abs($total_alokasi - (float)$d['qty']) > 0.001) {
                    throw new Exception('Total alokasi (Warehouse+Gudang+Waste) harus sama dengan qty retur (' . (float)$d['qty'] . ')');
                }

                mysqli_query($conn, "INSERT INTO retur_alokasi (retur_detail_id, qty_warehouse, qty_stok_gudang, qty_waste)
                                     VALUES ('{$d['id']}', '$qw', '$qg', '$qs')");

                if($qg > 0) {
                    mysqli_query($conn, "UPDATE barang SET stok = stok + $qg WHERE id='{$d['barang_id']}'");
                }

                $potongan_invoice[$d['barang_id']] = (float)$d['qty'];
            }

            // Potong nilai invoice sebesar qty retur x harga_satuan pada surat jalan asal
            $no_faktur_safe = mysqli_real_escape_string($conn, $retur['no_pesanan']);
            $total_potongan = 0;
            foreach($potongan_invoice as $barang_id => $qty_retur) {
                $q_harga = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_satuan FROM transaksi_detail WHERE no_faktur='$no_faktur_safe' AND barang_id='$barang_id'"));
                $harga = $q_harga ? (float)$q_harga['harga_satuan'] : 0;
                $total_potongan += $qty_retur * $harga;
            }

            if($total_potongan > 0) {
                mysqli_query($conn, "UPDATE transaksi SET total_transaksi = GREATEST(total_transaksi - $total_potongan, 0) WHERE no_faktur='$no_faktur_safe'");
                $trx_bayar = mysqli_fetch_assoc(mysqli_query($conn, "SELECT bayar, status_bayar FROM transaksi WHERE no_faktur='$no_faktur_safe'"));
                if($trx_bayar && strtolower($trx_bayar['status_bayar'] ?? '') === 'lunas' && (float)$trx_bayar['bayar'] > 0) {
                    mysqli_query($conn, "UPDATE transaksi SET bayar = GREATEST(bayar - $total_potongan, 0) WHERE no_faktur='$no_faktur_safe'");
                }
            }

            mysqli_query($conn, "UPDATE retur SET status='Potong Jumlah Invoice', respon_admin='$catatan' WHERE id='$id_retur'");
        }

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'msg' => 'Status Retur Diperbarui']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// 3. BUAT SURAT JALAN PENGGANTI (khusus retur berstatus "Mengganti Barang")
if($action == 'buat_sj_pengganti') {
    $id_retur = (int) $_POST['id_retur'];
    $id_usaha = $_SESSION['id_usaha'];
    $user_id  = $_SESSION['user_id'];

    $retur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM retur WHERE id='$id_retur'"));
    if(!$retur) {
        echo json_encode(['status' => 'error', 'msg' => 'Retur tidak ditemukan']);
        exit;
    }
    if($retur['status'] !== 'Mengganti Barang') {
        echo json_encode(['status' => 'error', 'msg' => 'Retur ini bukan berstatus Mengganti Barang']);
        exit;
    }
    if(!empty($retur['no_faktur_pengganti'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Surat jalan pengganti untuk retur ini sudah dibuat']);
        exit;
    }

    $no_faktur_asal = mysqli_real_escape_string($conn, $retur['no_pesanan']);
    $trx_asal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM transaksi WHERE no_faktur='$no_faktur_asal'"));
    if(!$trx_asal) {
        echo json_encode(['status' => 'error', 'msg' => 'Surat jalan asal tidak ditemukan']);
        exit;
    }

    $items = [];
    $q_item = mysqli_query($conn, "SELECT rd.barang_id, rd.qty, td.harga_satuan
                                   FROM retur_detail rd
                                   LEFT JOIN transaksi_detail td ON td.no_faktur='$no_faktur_asal' AND td.barang_id = rd.barang_id
                                   WHERE rd.retur_id='$id_retur'");
    while($it = mysqli_fetch_assoc($q_item)) { $items[] = $it; }

    if(!$items) {
        echo json_encode(['status' => 'error', 'msg' => 'Tidak ada item untuk dibuatkan surat jalan pengganti']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $no_faktur_baru = "RPL-" . date('YmdHis');
        $total_transaksi = 0;
        foreach($items as $it) { $total_transaksi += (float)$it['qty'] * (float)$it['harga_satuan']; }

        $pelanggan_id = (int)($trx_asal['pelanggan_id'] ?? 0);
        $nama_driver  = mysqli_real_escape_string($conn, $trx_asal['nama_driver'] ?? '');
        $nopol        = mysqli_real_escape_string($conn, $trx_asal['nopol'] ?? '');
        $keterangan   = 'Pengganti retur ' . $retur['no_retur'];

        $q_header = mysqli_query($conn, "INSERT INTO transaksi
            (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, kembalian, pelanggan_id, nama_driver, nopol, user_id, status, status_bayar, tanggal, keterangan)
            VALUES ('$id_usaha', '$no_faktur_baru', 'keluar', '$total_transaksi', 0, 0, '$pelanggan_id', '$nama_driver', '$nopol', '$user_id', 'selesai', 'lunas', NOW(), '$keterangan')");

        if(!$q_header) { throw new Exception('Gagal membuat surat jalan pengganti: ' . mysqli_error($conn)); }

        foreach($items as $it) {
            $barang_id = (int)$it['barang_id'];
            $qty       = (float)$it['qty'];
            $harga     = (float)$it['harga_satuan'];
            $subtotal  = $qty * $harga;
            $hpp_row   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='$barang_id'"));
            $hpp       = $hpp_row ? (float)$hpp_row['harga_beli'] : 0;

            mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal)
                                 VALUES ('$no_faktur_baru', '$barang_id', '$qty', '$harga', '$hpp', '$subtotal')");

            mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id='$barang_id'");
        }

        mysqli_query($conn, "UPDATE retur SET no_faktur_pengganti='$no_faktur_baru' WHERE id='$id_retur'");

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'msg' => 'Surat jalan pengganti berhasil dibuat', 'no_faktur' => $no_faktur_baru]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}
?>