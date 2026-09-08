<?php
// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- MULTI TOKO: AMBIL ID USAHA DARI SESSION ---
// Default ke 1 jika tidak ada session (untuk keamanan)
$id_usaha = $_SESSION['id_usaha'] ?? 1; 
// ----------------------------------------------

// ==========================================
// 1. CEK KEAMANAN HALAMAN (PENTING!)
// ==========================================
// Kita izinkan: admin, po, DAN invoice
wajib_akses('pos');

$success_faktur = null; 

// ==========================================
// 2. HANDLE KERANJANG
// ==========================================

// A. RESET KERANJANG
if(isset($_POST['reset_cart'])) { 
    unset($_SESSION['pos_cart']); 
    unset($_SESSION['pos_pelanggan']); 
    echo "<script>window.location='index.php?page=pos';</script>"; 
}

// B. TAMBAH KE KERANJANG
if(isset($_POST['add_to_cart'])) {
    $id_barang = $_POST['id_barang'];
    
    // PERBAIKAN: Filter barang berdasarkan toko (id_usaha) agar user tidak bisa inject ID barang toko lain
    $q = mysqli_query($conn, "SELECT * FROM barang WHERE id='$id_barang' AND id_usaha='$id_usaha'");
    $b = mysqli_fetch_assoc($q);
    
    if($b) {
        $cart = $_SESSION['pos_cart'] ?? [];
        $idx_found = -1;
        
        // Cek apakah barang sudah ada di cart
        foreach($cart as $key => $item) { 
            if($item['id'] == $id_barang) { 
                $idx_found = $key; 
                break; 
            } 
        }
        
        $qty_current = ($idx_found > -1) ? $cart[$idx_found]['qty'] : 0;
        
        // Cek Stok
        if( ($qty_current + 1) > $b['stok'] ) { 
            echo "<script>alert('Stok Tidak Cukup! Sisa: ".(float)$b['stok']."');</script>"; 
        } else {
            if($idx_found > -1) { 
                // Update Qty jika sudah ada
                $cart[$idx_found]['qty'] += 1; 
                $cart[$idx_found]['subtotal'] = $cart[$idx_found]['qty'] * $cart[$idx_found]['harga']; 
            } else { 
                // Tambah Baru jika belum ada
                $cart[] = [
                    'id' => $b['id'], 
                    'nama' => $b['nama_barang'], 
                    'kode' => $b['kode_barang'], 
                    'harga' => $b['harga_jual'], 
                    'qty' => 1, 
                    'satuan' => $b['satuan'], 
                    'subtotal' => $b['harga_jual']
                ]; 
            }
            $_SESSION['pos_cart'] = $cart;
        }
    }
}

// C. UPDATE QTY MANUAL
if(isset($_POST['update_qty_manual'])) {
    $idx = $_POST['idx']; 
    $qty_input = (float)$_POST['qty']; 
    
    if(isset($_SESSION['pos_cart'][$idx])) {
        $id = $_SESSION['pos_cart'][$idx]['id'];
        
        // PERBAIKAN: Cek stok dengan filter id_usaha
        $cek_stok = mysqli_query($conn, "SELECT stok FROM barang WHERE id='$id' AND id_usaha='$id_usaha'");
        $stok_db  = mysqli_fetch_assoc($cek_stok)['stok'];
        
        if($qty_input <= 0) { 
            // Hapus jika 0
            unset($_SESSION['pos_cart'][$idx]); 
            $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']); 
        } elseif($qty_input > $stok_db) { 
            echo "<script>alert('Stok tidak cukup! Sisa: ".(float)$stok_db."');</script>"; 
        } else { 
            $_SESSION['pos_cart'][$idx]['qty'] = $qty_input; 
            $_SESSION['pos_cart'][$idx]['subtotal'] = $qty_input * $_SESSION['pos_cart'][$idx]['harga']; 
        }
    }
    echo "<script>window.location='index.php?page=pos';</script>";
}

// D. HAPUS ITEM
if(isset($_GET['hapus_item'])) { 
    $idx = $_GET['hapus_item']; 
    unset($_SESSION['pos_cart'][$idx]); 
    $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']); 
    echo "<script>window.location='index.php?page=pos';</script>"; 
}

// ==========================================
// 3. PROSES PEMBAYARAN (SIMPAN TRANSAKSI)
// ==========================================
if(isset($_POST['proses_bayar'])) {
    $cart = $_SESSION['pos_cart'] ?? [];
    if(!empty($cart)) {
        $grand_total = $_POST['grand_total'];
        $pelanggan   = $_POST['pelanggan_id']; 
        $nama_driver = mysqli_real_escape_string($conn, $_POST['nama_driver']);
        $nopol       = mysqli_real_escape_string($conn, $_POST['nopol']);
        
        // PERBAIKAN KODE FAKTUR: Diubah menjadi awalan INV- untuk transaksi Invoice Kasir
        $no_faktur   = "INV-" . date('YmdHis');
        
        $user_id     = $_SESSION['user_id'];

        // Masukkan id_usaha ke database agar laporan terpisah
        $query_header = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, kembalian, pelanggan_id, nama_driver, nopol, user_id, status, status_bayar, tanggal) 
                         VALUES ('$id_usaha', '$no_faktur', 'keluar', '$grand_total', '$grand_total', 0, '$pelanggan', '$nama_driver', '$nopol', '$user_id', 'selesai', 'lunas', NOW())";
        
        if(mysqli_query($conn, $query_header)) {
            foreach($cart as $item) {
                // Ambil harga beli (HPP) untuk laporan laba rugi
                $modal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='{$item['id']}'"))['harga_beli'];
                
                // Simpan Detail
                mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal) VALUES ('$no_faktur', '{$item['id']}', '{$item['qty']}', '{$item['harga']}', '$modal', '{$item['subtotal']}')");
                
                // Kurangi Stok
                mysqli_query($conn, "UPDATE barang SET stok = stok - {$item['qty']} WHERE id='{$item['id']}'");
            }
            
            // Reset Cart setelah sukses
            unset($_SESSION['pos_cart']);
            $success_faktur = $no_faktur; 
            
            // Catat Log Aktivitas
            catat_log($conn, "Penjualan", "Transaksi Kasir No: $no_faktur senilai Rp ".number_format($grand_total));

        } else {
            echo "<script>alert('Gagal Simpan: ".mysqli_error($conn)."');</script>";
        }
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[85vh]">
    <div class="lg:col-span-2 flex flex-col gap-4">
        <div class="bg-white p-4 rounded shadow-sm">
            <input type="text" id="cariBarang" onkeyup="filterBarang()" placeholder="Cari barang (Nama / Kode)..." class="w-full border p-3 rounded text-lg focus:outline-indigo-600 shadow-inner">
        </div>
        <div class="bg-white p-4 rounded shadow-sm flex-1 overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php 
                // PERBAIKAN QUERY: Filter barang WHERE id_usaha = $id_usaha
                $brg = mysqli_query($conn, "SELECT * FROM barang WHERE stok > 0 AND id_usaha='$id_usaha' ORDER BY nama_barang ASC");
                
                if(mysqli_num_rows($brg) > 0):
                    while($b = mysqli_fetch_assoc($brg)): 
                ?>
                <form method="POST" class="item-barang bg-gray-50 p-3 rounded border hover:border-indigo-500 hover:shadow-md transition cursor-pointer text-center group relative overflow-hidden" onclick="this.submit()">
                    <input type="hidden" name="add_to_cart" value="true">
                    <input type="hidden" name="id_barang" value="<?= $b['id'] ?>">
                    
                    <div class="h-16 bg-indigo-50 rounded mb-2 flex items-center justify-center text-indigo-300 group-hover:text-indigo-600 transition">
                        <i class="fa-solid fa-box text-2xl"></i>
                    </div>
                    
                    <h4 class="font-bold text-sm truncate filter-name text-gray-700 group-hover:text-indigo-700"><?= $b['nama_barang'] ?></h4>
                    <p class="text-xs text-gray-500 mb-1 filter-kode"><?= $b['kode_barang'] ?></p>
                    
                    <div class="flex justify-between items-center mt-2 border-t pt-2 border-gray-200">
                        <div class="text-indigo-700 font-bold text-sm">Rp <?= number_format($b['harga_jual']) ?></div>
                        <div class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">Stok: <?= (float)$b['stok'] ?></div>
                    </div>
                </form>
                <?php endwhile; else: ?>
                    <div class="col-span-full text-center py-10 text-gray-400">
                        <i class="fa-solid fa-box-open text-4xl mb-3"></i>
                        <p>Belum ada barang di toko ini.</p>
                        <a href="index.php?page=barang" class="text-indigo-600 underline text-sm">Tambah Barang</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white p-5 rounded shadow-sm flex flex-col h-full border-t-4 border-indigo-600">
        <div class="flex justify-between items-center border-b pb-3 mb-3">
            <h3 class="font-bold text-lg text-gray-800"><i class="fa-solid fa-cart-shopping mr-2 text-indigo-600"></i> Keranjang</h3>
            <form method="POST"><button name="reset_cart" class="text-red-500 text-xs font-bold bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition"><i class="fa-solid fa-trash-can mr-1"></i> Reset</button></form>
        </div>
        
        <div class="flex-1 overflow-y-auto mb-4 space-y-2 pr-1 custom-scrollbar">
            <?php 
            $grand_total = 0;
            if(isset($_SESSION['pos_cart']) && !empty($_SESSION['pos_cart'])):
                foreach($_SESSION['pos_cart'] as $k => $c): 
                    $grand_total += $c['subtotal'];
            ?>
            <div class="flex justify-between items-center bg-gray-50 p-3 rounded border hover:border-indigo-300 transition group">
                <div class="w-1/2">
                    <div class="font-bold text-sm truncate text-gray-800" title="<?= $c['nama'] ?>"><?= $c['nama'] ?></div>
                    <div class="text-xs text-gray-500">@ Rp <?= number_format($c['harga']) ?> / <?= $c['satuan'] ?></div>
                </div>
                <div class="w-1/4 px-1">
                    <form method="POST" id="form_qty_<?= $k ?>">
                        <input type="hidden" name="update_qty_manual" value="true">
                        <input type="hidden" name="idx" value="<?= $k ?>">
                        <input type="number" step="0.01" name="qty" value="<?= (float)$c['qty'] ?>" class="w-full text-center border border-gray-300 rounded text-sm font-bold text-indigo-700 p-1 focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500" onchange="document.getElementById('form_qty_<?= $k ?>').submit()">
                    </form>
                </div>
                <div class="w-1/4 text-right pl-1">
                    <div class="font-bold text-sm text-gray-800">Rp <?= number_format($c['subtotal']) ?></div>
                    <a href="index.php?page=pos&hapus_item=<?= $k ?>" class="text-[10px] text-red-400 hover:text-red-600 font-medium">Hapus</a>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-300">
                    <i class="fa-solid fa-basket-shopping text-6xl mb-4"></i>
                    <p class="text-sm">Keranjang Kosong</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="border-t pt-4 bg-gray-50 p-4 -mx-5 -mb-5">
            <form method="POST">
                <input type="hidden" name="proses_bayar" value="true">
                <input type="hidden" name="grand_total" value="<?= $grand_total ?>">
                
                <div class="mb-3">
                    <label class="text-[10px] font-bold text-gray-500 uppercase mb-1 block">Pelanggan</label>
                    <select name="pelanggan_id" class="w-full border p-2 rounded text-sm focus:outline-indigo-500 bg-white">
                        <?php 
                        $pel = mysqli_query($conn, "SELECT * FROM pelanggan WHERE id_usaha='$id_usaha' ORDER BY nama_pelanggan ASC"); 
                        while($p = mysqli_fetch_assoc($pel)) { echo "<option value='{$p['id']}'>{$p['nama_pelanggan']}</option>"; } 
                        ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase mb-1 block">Driver</label>
                        <input type="text" name="nama_driver" class="w-full border p-2 rounded text-sm focus:outline-indigo-500" placeholder="Nama Driver...">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase mb-1 block">Nopol</label>
                        <input type="text" name="nopol" class="w-full border p-2 rounded text-sm focus:outline-indigo-500" placeholder="No. Polisi...">
                    </div>
                </div>
                
                <div class="flex justify-between items-center mb-4 bg-white p-4 rounded border border-indigo-100 shadow-sm">
                    <span class="font-bold text-sm text-gray-600">TOTAL BAYAR</span>
                    <span class="text-2xl font-bold text-indigo-700">Rp <?= number_format($grand_total) ?></span>
                </div>
                
                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-lg hover:bg-indigo-700 shadow-lg hover:shadow-xl transition transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed" <?= ($grand_total == 0) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-print mr-2"></i> SIMPAN & CETAK
                </button>
            </form>
        </div>
    </div>
</div>

<?php if($success_faktur): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-70 backdrop-blur-sm animate-fade-in">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-green-500"></div>
        
        <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-check text-3xl"></i>
        </div>
        
        <h3 class="text-xl font-bold text-gray-800 mb-1">Transaksi Berhasil!</h3>
        <p class="text-sm text-gray-500 mb-6">Faktur No: <b class="text-gray-800"><?= $success_faktur ?></b></p>
        
        <div class="space-y-3">
            <a href="cetak_invoice.php?no_faktur=<?= $success_faktur ?>" target="_blank" class="flex items-center justify-center w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition shadow-md">
                <i class="fa-solid fa-print mr-2"></i> CETAK INVOICE (A4)
            </a>
            <a href="cetak_surat_jalan.php?no_faktur=<?= $success_faktur ?>" target="_blank" class="flex items-center justify-center w-full bg-gray-700 text-white font-bold py-3 rounded-lg hover:bg-gray-800 transition shadow-md">
                <i class="fa-solid fa-file-lines mr-2"></i> CETAK SURAT JALAN
            </a>
            <a href="cetak_struk.php?no_faktur=<?= $success_faktur ?>" target="_blank" class="flex items-center justify-center w-full bg-white border border-gray-300 text-gray-700 font-bold py-3 rounded-lg hover:bg-gray-50 transition shadow-sm">
                <i class="fa-solid fa-receipt mr-2"></i> CETAK STRUK KECIL
            </a>
        </div>
        
        <button onclick="window.location='index.php?page=pos'" class="mt-6 text-gray-400 hover:text-gray-600 text-xs font-bold uppercase tracking-wide">Tutup & Transaksi Baru</button>
    </div>
</div>
<?php endif; ?>

<script>
function filterBarang() {
    let input = document.getElementById('cariBarang').value.toLowerCase();
    let items = document.getElementsByClassName('item-barang');
    for (let i = 0; i < items.length; i++) {
        let name = items[i].getElementsByClassName('filter-name')[0].innerText.toLowerCase();
        let kode = items[i].getElementsByClassName('filter-kode')[0].innerText.toLowerCase();
        
        if (name.includes(input) || kode.includes(input)) {
            items[i].style.display = "";
        } else {
            items[i].style.display = "none";
        }
    }
}
</script>

<style>
/* Custom Scrollbar untuk area keranjang dan barang */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>