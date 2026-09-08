<?php
wajib_akses('form_order');

// pages/form_order.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// 1. PROSES SIMPAN PESANAN
if(isset($_POST['kirim_pesanan'])) {
    $nama    = mysqli_real_escape_string($conn, $_POST['nama']);
    $hp      = mysqli_real_escape_string($conn, $_POST['hp']);
    $alamat  = mysqli_real_escape_string($conn, $_POST['alamat']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan']);
    $no_pesanan = "ORD-" . date('ymdHis');
    
    // Ambil keranjang
    $cart = $_SESSION['cart_order'] ?? [];
    
    if(!empty($cart)) {
        $total_bayar = 0;
        foreach($cart as $c) { $total_bayar += $c['subtotal']; }

        // Simpan Header
        $q_header = "INSERT INTO pesanan (id_usaha, no_pesanan, nama_pelanggan, no_hp, alamat, total_bayar, status, catatan) 
                     VALUES ('$id_usaha', '$no_pesanan', '$nama', '$hp', '$alamat', '$total_bayar', 'Pending', '$catatan')";
        
        if(mysqli_query($conn, $q_header)) {
            $id_pesanan = mysqli_insert_id($conn);
            
            // Simpan Detail
            foreach($cart as $c) {
                mysqli_query($conn, "INSERT INTO pesanan_detail (id_pesanan, id_barang, qty, harga_satuan, subtotal) 
                                     VALUES ('$id_pesanan', '{$c['id']}', '{$c['qty']}', '{$c['harga']}', '{$c['subtotal']}')");
            }
            
            unset($_SESSION['cart_order']); // Kosongkan keranjang
            echo "<script>alert('Pesanan Berhasil Dikirim! Mohon tunggu konfirmasi admin.'); window.location='index.php?page=form_order';</script>";
        }
    } else {
        echo "<script>alert('Keranjang masih kosong!');</script>";
    }
}

// 2. LOGIKA KERANJANG
if(isset($_POST['add_cart'])) {
    $id = $_POST['id_barang'];
    $qty = (float)$_POST['qty']; 
    
    $q = mysqli_query($conn, "SELECT * FROM barang WHERE id='$id' AND id_usaha='$id_usaha'");
    $b = mysqli_fetch_assoc($q);
    
    if($b) {
        $subtotal = $b['harga_jual'] * $qty;
        $_SESSION['cart_order'][] = [
            'id' => $b['id'], 'nama' => $b['nama_barang'], 
            'harga' => $b['harga_jual'], 'qty' => $qty, 'satuan' => $b['satuan'], 'subtotal' => $subtotal
        ];
    }
}
if(isset($_GET['clear_cart'])) { unset($_SESSION['cart_order']); echo "<script>window.location='index.php?page=form_order';</script>"; }
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-xl shadow-sm">
        <h2 class="text-xl font-bold mb-4 text-indigo-700"><i class="fa-solid fa-store mr-2"></i> Katalog Produk</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 h-[600px] overflow-y-auto custom-scrollbar pr-2">
            <?php
            // PERBAIKAN: Menghapus 'AND stok > 0' agar barang kosong tetap muncul dan bisa di-PO
            $q_brg = mysqli_query($conn, "SELECT * FROM barang WHERE id_usaha='$id_usaha' ORDER BY nama_barang ASC");
            while($b = mysqli_fetch_assoc($q_brg)):
                $stok_class = ($b['stok'] <= 0) ? "text-red-500 font-bold" : "text-gray-500";
                $stok_label = ($b['stok'] <= 0) ? "Stok Kosong (PO)" : "Stok: ".(float)$b['stok']." ".$b['satuan'];
            ?>
            <div class="border rounded-lg p-3 hover:shadow-md transition bg-gray-50">
                <div class="font-bold text-gray-800 text-sm mb-1"><?= $b['nama_barang'] ?></div>
                <div class="text-xs <?= $stok_class ?> mb-2"><?= $stok_label ?></div>
                <div class="text-indigo-600 font-bold mb-3">Rp <?= number_format($b['harga_jual']) ?></div>
                
                <form method="POST" class="flex gap-2 items-center">
                    <input type="hidden" name="id_barang" value="<?= $b['id'] ?>">
                    <input type="number" name="qty" step="0.01" min="0.01" value="1" class="w-20 border rounded p-1 text-center text-sm" placeholder="Qty">
                    <button type="submit" name="add_cart" class="bg-indigo-600 text-white px-3 py-1 rounded text-xs font-bold hover:bg-indigo-700">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-green-500">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Keranjang Belanja</h2>
            <a href="index.php?page=form_order&clear_cart=true" class="text-red-500 text-xs font-bold underline">Kosongkan</a>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg mb-4 h-64 overflow-y-auto">
            <?php if(empty($_SESSION['cart_order'])): ?>
                <p class="text-center text-gray-400 mt-10">Belum ada item.</p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php 
                    $total = 0;
                    foreach($_SESSION['cart_order'] as $item): 
                        $total += $item['subtotal'];
                    ?>
                    <li class="flex justify-between text-sm border-b pb-1">
                        <div>
                            <div class="font-bold"><?= $item['nama'] ?></div>
                            <div class="text-xs text-gray-500"><?= (float)$item['qty'] ?> <?= $item['satuan'] ?> x <?= number_format($item['harga']) ?></div>
                        </div>
                        <div class="font-bold">Rp <?= number_format($item['subtotal']) ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="flex justify-between items-center mb-6 font-bold text-lg">
            <span>Total Bayar:</span>
            <span class="text-green-600">Rp <?= number_format($total ?? 0) ?></span>
        </div>

        <form method="POST">
            <h3 class="font-bold text-sm mb-2 text-gray-700 uppercase">Data Pengiriman</h3>
            <input type="text" name="nama" placeholder="Nama Pelanggan" class="w-full border p-2 rounded mb-2 text-sm" required>
            <input type="text" name="hp" placeholder="Nomor WhatsApp" class="w-full border p-2 rounded mb-2 text-sm" required>
            <textarea name="alamat" placeholder="Alamat Lengkap Pengiriman..." class="w-full border p-2 rounded mb-2 text-sm h-20" required></textarea>
            <input type="text" name="catatan" placeholder="Catatan Tambahan (Opsional)" class="w-full border p-2 rounded mb-4 text-sm">
            
            <button type="submit" name="kirim_pesanan" class="w-full bg-green-600 text-white font-bold py-3 rounded hover:bg-green-700 transition">
                <i class="fa-solid fa-paper-plane mr-2"></i> KIRIM PESANAN
            </button>
        </form>
    </div>
</div>