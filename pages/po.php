<?php
wajib_akses('po');

// Pastikan session aktif
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// ==========================================
// 0. AJAX HANDLER (UNTUK CEK BARANG)
// ==========================================
if(isset($_POST['cek_info_barang'])) {
    ob_clean();
    $nama = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $q = mysqli_query($conn, "SELECT * FROM barang WHERE nama_barang = '$nama' LIMIT 1");
    if(mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        // Kirim data JSON ke Javascript
        echo json_encode([
            'status' => 'found',
            'minimal_order' => $d['minimal_order'],
            'harga_head' => $d['harga_head'],
            'harga_beli' => $d['harga_beli'],
            'harga_jual' => $d['harga_jual'],
            'satuan' => $d['satuan']
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit;
}

// ==========================================
// 1. HANDLE KERANJANG PO
// ==========================================

// Reset Keranjang
if(isset($_POST['reset_po'])) {
    unset($_SESSION['po_cart']);
    unset($_SESSION['po_supplier_id']);
    echo "<script>window.location='index.php?page=po';</script>";
}

// Set Supplier
if(isset($_POST['pilih_supplier'])) {
    $_SESSION['po_supplier_id'] = $_POST['supplier_id'];
    $_SESSION['po_cart'] = [];
}

// Tambah Item ke Keranjang
if(isset($_POST['tambah_item'])) {
    tolak_jika_tidak_boleh('tambah', 'po', 'index.php?page=po');
    $nama   = trim($_POST['nama_barang']);
    $qty    = (float)$_POST['qty'];
    $harga  = (float)$_POST['harga_satuan']; // Harga Beli
    $jual   = (float)$_POST['harga_jual'];   // Harga Jual (Baru)
    $satuan = ucwords(strtolower(trim($_POST['satuan'])));
    
    if(!empty($nama) && $qty > 0) {
        $_SESSION['po_cart'][] = [
            'nama_barang' => $nama,
            'qty'         => $qty,
            'harga'       => $harga,
            'harga_jual'  => $jual,
            'satuan'      => $satuan,
            'subtotal'    => $qty * $harga
        ];
    }
}

// Hapus Item
if(isset($_GET['hapus_item'])) {
    $idx = $_GET['hapus_item'];
    unset($_SESSION['po_cart'][$idx]);
    $_SESSION['po_cart'] = array_values($_SESSION['po_cart']);
    echo "<script>window.location='index.php?page=po';</script>";
}

// ==========================================
// 2. SIMPAN PO (FINAL) DENGAN APPROVAL
// ==========================================
if(isset($_POST['simpan_po_final'])) {
    tolak_jika_tidak_boleh('tambah', 'po', 'index.php?page=po');
    $supplier_id = $_SESSION['po_supplier_id'];
    $cart = $_SESSION['po_cart'];
    
    if(!empty($cart)) {
        // --- LOGIKA APPROVAL (MODIFIKASI) ---
        if($_SESSION['role'] == 'po') {
            // Ambil Nama Supplier
            $sup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_supplier FROM supplier WHERE id='$supplier_id'"));
            $nm_sup = $sup['nama_supplier'];

            $grand_total = 0;
            foreach($cart as $c) { $grand_total += $c['subtotal']; }

            // Siapkan Data JSON
            $data_po = [
                'supplier_id' => $supplier_id,
                'total'       => $grand_total,
                'items'       => $cart // Array keranjang langsung disimpan
            ];
            $json_data = json_encode($data_po);
            
            $ket = "Request PO Baru ke: $nm_sup. Total: Rp " . number_format($grand_total);

            // Masukkan ke Approval Request
            $q_req = "INSERT INTO approval_request (id_usaha, user_id, tipe_aksi, keterangan, data_json, status) 
                      VALUES ('$id_usaha', '{$_SESSION['user_id']}', 'buat_po', '$ket', '$json_data', 'pending')";
            
            if(mysqli_query($conn, $q_req)) {
                unset($_SESSION['po_cart']);
                unset($_SESSION['po_supplier_id']);
                $_SESSION['notif_pesan'] = 'PO Dikirim ke Manager untuk Persetujuan!';
                $_SESSION['notif_tipe'] = 'success';
                echo "<script>window.location='index.php?page=po';</script>";
            } else {
                $_SESSION['notif_pesan'] = 'Gagal request approval!';
                $_SESSION['notif_tipe'] = 'error';
            }
            exit; // STOP
        }
        // ------------------------------------

        // --- JIKA ADMIN/MANAGER (LANGSUNG SIMPAN) ---
        $no_po = "PO-" . date('YmdHis');
        $grand_total = 0;
        foreach($cart as $c) { $grand_total += $c['subtotal']; }
        
        // Simpan Header
        $save = mysqli_query($conn, "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, supplier_id, user_id, status, status_bayar, tanggal) 
                                     VALUES ('$id_usaha', '$no_po', 'masuk', '$grand_total', '$supplier_id', '{$_SESSION['user_id']}', 'pending', 'belum', NOW())");
        
        if($save) {
            foreach($cart as $item) {
                $nama = mysqli_real_escape_string($conn, $item['nama_barang']);
                
                // Cek Barang (Ada/Baru)
                $cek = mysqli_query($conn, "SELECT id FROM barang WHERE nama_barang='$nama' LIMIT 1");
                if(mysqli_num_rows($cek) > 0) {
                    $b = mysqli_fetch_assoc($cek);
                    $barang_id = $b['id'];
                    
                    // ==============================================================
                    // PERBAIKAN: JANGAN UPDATE HARGA BELI JIKA 0
                    // ==============================================================
                    if ($item['harga'] > 0) {
                        // Jika ada harganya (lebih dari 0), update stok DAN harga[cite: 1]
                        mysqli_query($conn, "UPDATE barang SET harga_beli='{$item['harga']}', harga_jual='{$item['harga_jual']}' WHERE id='$barang_id'");
                    } else {
                        // Jika harganya 0, HANYA update harga jual saja (Harga beli tidak berubah)[cite: 1]
                        mysqli_query($conn, "UPDATE barang SET harga_jual='{$item['harga_jual']}' WHERE id='$barang_id'");
                    }
                    // ==============================================================
                    
                } else {
                    // Barang Baru
                    $max = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) as m FROM barang"));
                    $kode = "BRG" . sprintf("%03s", $max['m'] + 1);
                    mysqli_query($conn, "INSERT INTO barang (id_usaha, kode_barang, nama_barang, satuan, harga_beli, harga_jual, stok) 
                                         VALUES ('$id_usaha', '$kode', '$nama', '{$item['satuan']}', '{$item['harga']}', '{$item['harga_jual']}', 0)");
                    $barang_id = mysqli_insert_id($conn);
                }
                
                // Simpan Detail
                mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, subtotal) 
                                     VALUES ('$no_po', '$barang_id', '{$item['qty']}', '{$item['harga']}', '{$item['subtotal']}')");
            }
            unset($_SESSION['po_cart']);
            unset($_SESSION['po_supplier_id']);
            $_SESSION['notif_pesan'] = 'PO Berhasil Disimpan!';
            $_SESSION['notif_tipe'] = 'success';
            echo "<script>window.location='index.php?page=po';</script>";
        }
    }
}

// FITUR TAMBAHAN: UPDATE STATUS BAYAR & TERIMA BARANG (HANYA MANAGER/ADMIN YANG BOLEH TERIMA BARANG LANGSUNG)
// Admin PO harusnya hanya request, tapi di sini kita biarkan dulu logic terima barangnya
if(isset($_GET['aksi_bayar']) && isset($_GET['faktur'])) {
    if($_SESSION['role'] == 'po') { 
        $_SESSION['notif_pesan'] = 'Akses Ditolak! Hanya Manager.'; 
        $_SESSION['notif_tipe'] = 'error';
        echo "<script>window.location='index.php?page=po';</script>"; 
        exit; 
    }
    
    $faktur = $_GET['faktur'];
    $status = $_GET['aksi_bayar']; 
    if($status == 'lunas'){ mysqli_query($conn, "UPDATE transaksi SET status_bayar='lunas', tgl_lunas=NOW() WHERE no_faktur='$faktur'"); } 
    else { mysqli_query($conn, "UPDATE transaksi SET status_bayar='belum', tgl_lunas=NULL WHERE no_faktur='$faktur'"); }
    echo "<script>window.location='index.php?page=po';</script>";
}

if(isset($_GET['terima'])) {
    if($_SESSION['role'] == 'po') { 
        $_SESSION['notif_pesan'] = 'Akses Ditolak! Admin PO tidak boleh input stok masuk tanpa approval.'; 
        $_SESSION['notif_tipe'] = 'error';
        echo "<script>window.location='index.php?page=po';</script>"; 
        exit; 
    }

    $faktur = $_GET['terima'];
    $d = mysqli_query($conn, "SELECT * FROM transaksi_detail WHERE no_faktur='$faktur'");
    while($item = mysqli_fetch_assoc($d)) { mysqli_query($conn, "UPDATE barang SET stok = stok + {$item['qty']} WHERE id='{$item['barang_id']}'"); }
    mysqli_query($conn, "UPDATE transaksi SET status='selesai' WHERE no_faktur='$faktur'");
    $_SESSION['notif_pesan'] = 'Stok Ditambahkan!';
    $_SESSION['notif_tipe'] = 'success';
    echo "<script>window.location='index.php?page=po';</script>";
}

if(isset($_GET['hapus_po'])) {
    if($_SESSION['role'] == 'po') { 
        $_SESSION['notif_pesan'] = 'Akses Ditolak! Hanya Manager.'; 
        $_SESSION['notif_tipe'] = 'error';
        echo "<script>window.location='index.php?page=po';</script>"; 
        exit; 
    }

    $faktur = $_GET['hapus_po'];
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM transaksi WHERE no_faktur='$faktur'"));
    if($cek && $cek['status'] == 'selesai') {
        $det = mysqli_query($conn, "SELECT * FROM transaksi_detail WHERE no_faktur='$faktur'");
        while($d = mysqli_fetch_assoc($det)) { mysqli_query($conn, "UPDATE barang SET stok = stok - {$d['qty']} WHERE id='{$d['barang_id']}'"); }
    }
    mysqli_query($conn, "DELETE FROM transaksi_detail WHERE no_faktur='$faktur'");
    mysqli_query($conn, "DELETE FROM transaksi WHERE no_faktur='$faktur'");
    $_SESSION['notif_pesan'] = 'PO Dihapus!';
    $_SESSION['notif_tipe'] = 'success';
    echo "<script>window.location='index.php?page=po';</script>";
}
?>

<!-- Include SweetAlert untuk notifikasi (Wajib ada di bagian head atau body) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Cek Jika ada Notifikasi (Hanya muncul sekali) -->
<?php if (isset($_SESSION['notif_pesan'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                text: "<?= htmlspecialchars($_SESSION['notif_pesan'], ENT_QUOTES, 'UTF-8') ?>",
                icon: "<?= htmlspecialchars($_SESSION['notif_tipe'], ENT_QUOTES, 'UTF-8') ?>",
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        });
    </script>
    <?php unset($_SESSION['notif_pesan']); unset($_SESSION['notif_tipe']); ?>
<?php endif; ?>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="bg-white p-6 rounded-lg shadow-sm h-fit border-t-4 border-indigo-600">
        
        <?php if(!isset($_SESSION['po_supplier_id'])): ?>
            <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">1. Buat PO Baru</h3>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-bold mb-1 text-gray-600">Pilih Supplier</label>
                    <select name="supplier_id" class="w-full border p-3 rounded bg-gray-50 focus:outline-indigo-500" required>
                        <option value="">- Pilih Supplier -</option>
                        <?php 
                        $sup = mysqli_query($conn, "SELECT * FROM supplier WHERE id_usaha='$id_usaha'");
                        while($s = mysqli_fetch_assoc($sup)) { echo "<option value='{$s['id']}'>{$s['nama_supplier']}</option>"; }
                        ?>
                    </select>
                </div>
                <button type="submit" name="pilih_supplier" class="w-full bg-indigo-600 text-white font-bold py-2 rounded hover:bg-indigo-700 transition">Lanjut</button>
            </form>

        <?php else: 
            $sup_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_supplier FROM supplier WHERE id='{$_SESSION['po_supplier_id']}'"));
        ?>
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <div><h3 class="font-bold text-gray-800">2. Input Barang</h3><span class="text-xs text-indigo-600 font-bold"><?= $sup_aktif['nama_supplier'] ?></span></div>
                <form method="POST" onsubmit="return confirm('Reset keranjang?')"><button type="submit" name="reset_po" class="text-xs text-red-500 underline">Ganti</button></form>
            </div>

            <form method="POST" onsubmit="return validasiHarga()">
                <input type="hidden" id="harga_head_db" value="0">

                <div class="mb-2">
                    <label class="block text-xs font-bold text-gray-500">Nama Barang</label>
                    <input list="list_barang" name="nama_barang" id="input_nama_barang" class="w-full border p-2 rounded text-sm focus:outline-indigo-500" placeholder="Ketik nama..." autocomplete="off" required onchange="cekBarang()">
                    <datalist id="list_barang">
                        <?php 
                        $brg = mysqli_query($conn, "SELECT nama_barang FROM barang WHERE id_usaha='$id_usaha' ORDER BY nama_barang ASC");
                        while($b = mysqli_fetch_assoc($brg)) { echo "<option value='{$b['nama_barang']}'>"; }
                        ?>
                    </datalist>
                    <p id="info_min_order" class="text-[10px] font-bold text-blue-600 mt-1"></p>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Harga Beli</label>
                        <input type="number" name="harga_satuan" id="input_harga_beli" class="w-full border p-2 rounded text-sm" placeholder="Rp" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Harga Jual (Baru)</label>
                        <input type="number" name="harga_jual" id="input_harga_jual" class="w-full border p-2 rounded text-sm" placeholder="Rp" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-4">
                     <div>
                        <label class="block text-xs font-bold text-gray-500">Qty</label>
                        <input type="number" step="0.01" name="qty" class="w-full border p-2 rounded text-sm font-bold" placeholder="0.00" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500">Satuan</label>
                        <input type="text" name="satuan" id="input_satuan" class="w-full border p-2 rounded text-sm" placeholder="Pcs/Kg" required>
                    </div>
                </div>

                <button type="submit" name="tambah_item" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:bg-green-700 shadow-sm">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Tambah
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <?php if(isset($_SESSION['po_cart']) && !empty($_SESSION['po_cart'])): ?>
        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
            <h4 class="font-bold text-indigo-800 mb-2">Draft PO</h4>
            <table class="w-full text-sm bg-white rounded shadow-sm">
                <thead class="bg-gray-100 text-gray-600">
                    <tr>
                        <th class="p-2 text-left">Barang</th>
                        <th class="p-2 text-center">Qty</th>
                        <th class="p-2 text-right">Beli</th>
                        <th class="p-2 text-right">Jual</th>
                        <th class="p-2 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total = 0;
                    foreach($_SESSION['po_cart'] as $k => $c): $total += $c['subtotal'];
                    ?>
                    <tr class="border-b">
                        <td class="p-2"><?= $c['nama_barang'] ?></td>
                        <td class="p-2 text-center"><?= $c['qty'] ?> <?= $c['satuan'] ?></td>
                        <td class="p-2 text-right"><?= number_format($c['harga']) ?></td>
                        <td class="p-2 text-right text-green-600"><?= number_format($c['harga_jual']) ?></td>
                        <td class="p-2 text-center"><a href="index.php?page=po&hapus_item=<?= $k ?>" class="text-red-500"><i class="fa-solid fa-times"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="p-2 text-right font-bold">Total: Rp <?= number_format($total) ?></td><td></td></tr>
                </tfoot>
            </table>
            <form method="POST" class="mt-3 text-right">
                <button type="submit" name="simpan_po_final" class="bg-indigo-600 text-white px-6 py-2 rounded font-bold">
                    <?= ($_SESSION['role'] == 'po') ? 'REQUEST APPROVAL' : 'SIMPAN PO' ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm border-t-4 border-gray-600">
            <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Riwayat Purchase Order</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 uppercase text-gray-600 font-bold">
                        <tr>
                            <th class="p-3">No PO</th>
                            <th class="p-3">Supplier</th>
                            <th class="p-3">Total</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Bayar</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $po = mysqli_query($conn, "SELECT t.*, s.nama_supplier FROM transaksi t JOIN supplier s ON t.supplier_id = s.id WHERE t.jenis_transaksi='masuk' AND t.id_usaha='$id_usaha' ORDER BY t.id DESC LIMIT 20");
                        while($r = mysqli_fetch_assoc($po)):
                        ?>
                        <tr class="hover:bg-gray-50 border-b">
                            <td class="p-3 font-bold text-indigo-700"><?= $r['no_faktur'] ?><br><span class="text-xs text-gray-400"><?= date('d/m/y', strtotime($r['tanggal'])) ?></span></td>
                            <td class="p-3"><?= $r['nama_supplier'] ?></td>
                            <td class="p-3 font-bold"><?= format_rupiah($r['total_transaksi']) ?></td>
                            <td class="p-3 text-center">
                                <?php if($r['status']=='pending'): ?>
                                    <span class="bg-yellow-100 text-yellow-700 px-2 rounded text-xs">PROSES</span>
                                    <?php if($_SESSION['role'] != 'po'): ?>
                                        <a href="index.php?page=po&terima=<?= $r['no_faktur'] ?>" onclick="return confirm('Terima Barang?')" class="text-xs text-blue-600 underline block">Terima</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 px-2 rounded text-xs">DITERIMA</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if($r['status_bayar']=='lunas'): ?>
                                    <?php if($_SESSION['role'] != 'po'): ?>
                                        <a href="index.php?page=po&aksi_bayar=belum&faktur=<?= $r['no_faktur'] ?>" class="bg-green-600 text-white px-2 rounded text-xs">LUNAS</a>
                                    <?php else: ?>
                                        <span class="bg-green-600 text-white px-2 rounded text-xs">LUNAS</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if($_SESSION['role'] != 'po'): ?>
                                        <a href="index.php?page=po&aksi_bayar=lunas&faktur=<?= $r['no_faktur'] ?>" class="bg-red-500 text-white px-2 rounded text-xs">TEMPO</a>
                                    <?php else: ?>
                                        <span class="bg-red-500 text-white px-2 rounded text-xs">TEMPO</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <button onclick="lihatDetail('<?= $r['no_faktur'] ?>')" class="text-gray-500"><i class="fa-solid fa-eye"></i></button>
                                <a href="assets/cetak_po.php?no_faktur=<?= $r['no_faktur'] ?>" target="_blank" class="text-gray-500 ml-2"><i class="fa-solid fa-print"></i></a>
                                <?php if($_SESSION['role'] != 'po'): ?>
                                    <a href="index.php?page=po&hapus_po=<?= $r['no_faktur'] ?>" onclick="return confirm('Hapus PO?')" class="text-red-500 ml-2"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalDetail" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-xl relative">
        <button onclick="document.getElementById('modalDetail').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="text-lg font-bold mb-2">Detail PO: <span id="labelFaktur"></span></h3>
        <div id="kontenDetail" class="overflow-y-auto max-h-64"></div>
    </div>
</div>

<script>
// FUNGSI CEK BARANG VIA AJAX
function cekBarang() {
    let nama = document.getElementById('input_nama_barang').value;
    if(nama.length > 2) {
        let formData = new FormData();
        formData.append('cek_info_barang', true);
        formData.append('nama_barang', nama);

        fetch('index.php?page=po', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.status == 'found') {
                // Tampilkan Minimal Order
                let minOrd = data.minimal_order ? data.minimal_order : '-';
                document.getElementById('info_min_order').innerText = "Minimal Order: " + minOrd;
                
                // Simpan Harga Head di Hidden Input
                document.getElementById('harga_head_db').value = data.harga_head;
                
                // Autofill Harga Beli & Jual Terakhir
                document.getElementById('input_harga_beli').value = data.harga_beli;
                document.getElementById('input_harga_jual').value = data.harga_jual;
                document.getElementById('input_satuan').value = data.satuan;
            } else {
                document.getElementById('info_min_order').innerText = "Barang Baru (Belum ada di master)";
                document.getElementById('harga_head_db').value = 0;
            }
        });
    }
}

// FUNGSI VALIDASI HARGA HEAD
function validasiHarga() {
    let hargaInput = parseFloat(document.getElementById('input_harga_beli').value);
    let hargaHead  = parseFloat(document.getElementById('harga_head_db').value);

    // Jika Harga Head ada isinya (bukan 0) DAN Harga Input melebihi Harga Head
    if(hargaHead > 0 && hargaInput > hargaHead) {
        return confirm("PERINGATAN KERAS!\n\nHarga Beli (Rp " + hargaInput + ") melebihi Harga Head/Batas Atas (Rp " + hargaHead + ").\n\nApakah Anda yakin ingin melanjutkan?");
    }
    return true; // Lanjut jika tidak melebihi atau user klik OK
}

function lihatDetail(faktur) {
    document.getElementById('modalDetail').classList.remove('hidden');
    document.getElementById('labelFaktur').innerText = faktur;
    let formData = new FormData(); formData.append('get_detail_po', true); formData.append('faktur', faktur);
    fetch('index.php?page=po', { method: 'POST', body: formData }).then(r => r.text()).then(h => { document.getElementById('kontenDetail').innerHTML = h; });
}
</script>

<?php
// PHP AJAX HANDLER UNTUK DETAIL (Sama seperti sebelumnya)
if(isset($_POST['get_detail_po'])) {
    ob_clean();
    $faktur = $_POST['faktur'];
    $q = mysqli_query($conn, "SELECT td.*, b.nama_barang, b.satuan FROM transaksi_detail td JOIN barang b ON td.barang_id = b.id WHERE td.no_faktur = '$faktur'");
    echo '<table class="w-full text-sm border"><thead class="bg-gray-100"><tr><th class="p-2">Barang</th><th class="p-2 text-center">Qty</th><th class="p-2 text-right">Beli</th><th class="p-2 text-right">Total</th></tr></thead><tbody>';
    while($d = mysqli_fetch_assoc($q)) { echo "<tr><td class='p-2'>{$d['nama_barang']}</td><td class='p-2 text-center'>".(float)$d['qty']." {$d['satuan']}</td><td class='p-2 text-right'>".number_format($d['harga_satuan'])."</td><td class='p-2 text-right'>".number_format($d['subtotal'])."</td></tr>"; }
    echo '</tbody></table>';
    exit;
}
?>