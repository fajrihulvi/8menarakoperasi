<?php
// FILE: ajax_pos.php
// KHUSUS UNTUK MENANGANI LOGIKA KERANJANG (SUPAYA BERSIH DARI HTML INDEX)

require 'config/koneksi.php'; // Pastikan path ini benar

// PROTEKSI: keranjang POS milik sesi kasir, wajib login
wajib_login_ajax(['admin','po','accounting','invoice']);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Matikan error agar tidak merusak JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => '', 'cart_html' => ''];
$action = $_POST['action'] ?? '';
$id     = $_POST['id'] ?? null;

// --- A. LOGIKA TAMBAH (ADD) ---
if ($action == 'add') {
    $q = mysqli_query($conn, "SELECT * FROM barang WHERE id='$id'");
    $b = mysqli_fetch_assoc($q);
    
    if ($b && $b['stok'] > 0) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
        if (isset($_SESSION['cart'][$id])) {
            if ($_SESSION['cart'][$id]['qty'] + 1 <= $b['stok']) {
                $_SESSION['cart'][$id]['qty'] += 1;
            } else {
                $response['alert'] = "Stok maksimal tercapai!";
            }
        } else {
            $_SESSION['cart'][$id] = [
                'nama' => $b['nama_barang'],
                'harga' => $b['harga_jual'],
                'satuan' => $b['satuan'],
                'qty' => 1,
                'stok_max' => $b['stok']
            ];
        }
        $response['status'] = 'success';
    } else {
        $response['alert'] = "Stok Habis!";
    }
}

// --- B. LOGIKA UPDATE (PLUS/MINUS/MANUAL) ---
elseif (isset($_SESSION['cart'][$id])) {
    $current_qty = (float)$_SESSION['cart'][$id]['qty'];
    $stok_db     = (float)$_SESSION['cart'][$id]['stok_max'];

    if ($action == 'manual') {
        $val = (float)$_POST['value'];
        if ($val > 0 && $val <= $stok_db) {
            $_SESSION['cart'][$id]['qty'] = $val;
        } else {
            $response['alert'] = "Stok tidak cukup! Sisa: $stok_db"; 
        }
    } 
    elseif ($action == 'plus') {
        if ($current_qty + 1 <= $stok_db) {
            $_SESSION['cart'][$id]['qty'] += 1;
        } else {
            $response['alert'] = "Stok Maksimal!";
        }
    } 
    elseif ($action == 'minus') {
        if ($current_qty - 1 > 0) {
            $_SESSION['cart'][$id]['qty'] -= 1;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }
    elseif ($action == 'delete') {
        unset($_SESSION['cart'][$id]);
    }
    $response['status'] = 'success';
}

// --- C. LOGIKA RENDER HTML (Kirim Balik ke Layar) ---
ob_start();
$total_cart = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $kid => $item) {
        $subtotal = $item['harga'] * $item['qty'];
        $total_cart += $subtotal;
        ?>
        <div class="flex justify-between items-center border-b border-dashed pb-2 mb-2 animation-fade-in text-gray-900">
            <div class="flex-1 overflow-hidden">
                <div class="font-bold text-sm text-gray-900 line-clamp-1"><?= $item['nama'] ?></div>
                <div class="text-xs text-gray-500"><?= number_format($item['harga'],0,',','.') ?> / <?= $item['satuan'] ?></div>
            </div>
            
            <div class="flex items-center gap-1 mx-2 bg-gray-100 p-1 rounded border border-gray-300">
                <button type="button" onclick="updateCart(<?= $kid ?>, 'minus')" class="w-7 h-7 flex items-center justify-center bg-white border rounded hover:bg-red-100 text-gray-800 font-bold shadow-sm">-</button>
                
                <input type="number" value="<?= (float)$item['qty'] ?>" step="0.01" min="0.01"
                       class="w-16 h-7 text-center border-0 bg-transparent text-sm font-bold text-gray-900 focus:ring-0 outline-none p-0"
                       onchange="updateCart(<?= $kid ?>, 'manual', this.value)">
                
                <button type="button" onclick="updateCart(<?= $kid ?>, 'plus')" class="w-7 h-7 flex items-center justify-center bg-white border rounded hover:bg-green-100 text-indigo-700 font-bold shadow-sm">+</button>
            </div>

            <div class="text-right pl-2 w-20">
                <div class="font-bold text-gray-800 text-sm"><?= number_format($subtotal,0,',','.') ?></div>
                <button onclick="updateCart(<?= $kid ?>, 'delete')" class="text-[10px] text-red-500 hover:text-red-700 underline font-semibold cursor-pointer">Hapus</button>
            </div>
        </div>
        <?php
    }
} else {
    echo '<div class="h-40 flex flex-col items-center justify-center text-gray-400"><i class="fa-solid fa-basket-shopping text-5xl mb-2 opacity-30"></i><p>Keranjang Kosong</p></div>';
}
$html_list = ob_get_clean();

$response['cart_html'] = $html_list;
$response['total_raw'] = $total_cart;

echo json_encode($response);
?>