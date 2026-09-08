<?php
wajib_akses('barang_masuk');

// Pastikan session aktif
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// AMBIL ID USAHA DARI SESSION
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// ==========================================
// 1. HANDLE LOGIKA KERANJANG (DRAFT)
// ==========================================

// Reset Keranjang
if(isset($_POST['reset_bm'])) {
    unset($_SESSION['bm_cart']);
    echo "<script>window.location='index.php?page=barang_masuk';</script>";
    exit;
}

// Tambah Item ke Keranjang
if(isset($_POST['tambah_item'])) {
    tolak_jika_tidak_boleh('tambah', 'barang_masuk', 'index.php?page=barang_masuk');
    $nama   = trim($_POST['nama_barang']);
    
    // Filter Koma menjadi Titik agar desimal presisi
    $qty_raw = str_replace(',', '.', $_POST['qty']);
    $qty     = (float)$qty_raw;
    
    // Satuan ditarik dari form (yang sudah otomatis diisi oleh JS)
    $satuan = ucwords(strtolower(trim($_POST['satuan']))); 
    
    if(!empty($nama) && $qty > 0) {
        // ================================================================
        // PERBAIKAN: OTOMATIS TARIK HARGA MASTER DARI DATABASE (TIDAK BISA DIEDIT)
        // ================================================================
        $harga = 0;
        $nama_clean = mysqli_real_escape_string($conn, $nama);
        $q_harga = mysqli_query($conn, "SELECT harga_beli FROM barang WHERE TRIM(LOWER(nama_barang))=TRIM(LOWER('$nama_clean')) AND id_usaha='$id_usaha' LIMIT 1");
        
        if(mysqli_num_rows($q_harga) > 0) {
            $d_harga = mysqli_fetch_assoc($q_harga);
            $harga = (float)$d_harga['harga_beli'];
        }

        if(!isset($_SESSION['bm_cart'])) { $_SESSION['bm_cart'] = []; }

        // Cek barang di keranjang menggunakan TRIM
        $item_exists = false;
        foreach ($_SESSION['bm_cart'] as $index => $item) {
            if (trim(strtolower($item['nama_barang'])) === trim(strtolower($nama))) {
                $_SESSION['bm_cart'][$index]['qty'] += $qty;
                // Harga tetap konsisten dengan master data
                $_SESSION['bm_cart'][$index]['harga'] = $harga; 
                $_SESSION['bm_cart'][$index]['subtotal'] = $_SESSION['bm_cart'][$index]['qty'] * $harga;
                $item_exists = true;
                break;
            }
        }

        if (!$item_exists) {
            $_SESSION['bm_cart'][] = [
                'nama_barang' => $nama,
                'qty'         => $qty,
                'harga'       => $harga,
                'satuan'      => $satuan,
                'subtotal'    => $qty * $harga
            ];
        }
        
        echo "<script>window.location='index.php?page=barang_masuk';</script>";
        exit;
    }
}

// Hapus Item dari Keranjang
if(isset($_GET['hapus_item'])) {
    $idx = $_GET['hapus_item'];
    unset($_SESSION['bm_cart'][$idx]);
    $_SESSION['bm_cart'] = array_values($_SESSION['bm_cart']); // Re-index
    echo "<script>window.location='index.php?page=barang_masuk';</script>";
    exit;
}

// ==========================================
// 2. HANDLE SIMPAN KE DATABASE (FINAL)
// ==========================================
if(isset($_POST['simpan_bm_final'])) {
    tolak_jika_tidak_boleh('tambah', 'barang_masuk', 'index.php?page=barang_masuk');
    $supplier_id = $_POST['supplier_id']; 
    $tgl_masuk   = $_POST['tanggal'];
    $cart        = $_SESSION['bm_cart'] ?? [];
    
    if(!empty($cart)) {
        $no_faktur = "BM-" . date('YmdHis'); 
        $grand_total = 0;
        foreach($cart as $c) { $grand_total += $c['subtotal']; }
        
        $save = mysqli_query($conn, "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, supplier_id, user_id, status, status_bayar, tanggal) 
                                     VALUES ('$id_usaha', '$no_faktur', 'masuk', '$grand_total', 0, '$supplier_id', '{$_SESSION['user_id']}', 'selesai', 'belum', '$tgl_masuk')");
        
        if($save) {
            foreach($cart as $item) {
                $nama = mysqli_real_escape_string($conn, $item['nama_barang']);
                $qty  = (float)$item['qty']; 
                $harga = (float)$item['harga'];
                
                // Cek ke master barang
                $cek = mysqli_query($conn, "SELECT id FROM barang WHERE TRIM(LOWER(nama_barang))=TRIM(LOWER('$nama')) AND id_usaha='$id_usaha' LIMIT 1");
                
                if(mysqli_num_rows($cek) > 0) {
                    $b = mysqli_fetch_assoc($cek);
                    $barang_id = $b['id'];
                    
                    // ================================================================
                    // PERBAIKAN: HANYA UPDATE STOK! TIDAK MENYENTUH HARGA SAMA SEKALI
                    // ================================================================
                    mysqli_query($conn, "UPDATE barang SET stok = stok + $qty WHERE id='$barang_id'");
                } else {
                    $max = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) as m FROM barang"));
                    $kode = "BRG" . sprintf("%03s", $max['m'] + 1);
                    
                    mysqli_query($conn, "INSERT INTO barang (id_usaha, kode_barang, nama_barang, satuan, harga_beli, harga_jual, stok) 
                                         VALUES ('$id_usaha', '$kode', '$nama', '{$item['satuan']}', '$harga', '$harga', '$qty')");
                    $barang_id = mysqli_insert_id($conn);
                }
                
                mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, subtotal) 
                                     VALUES ('$no_faktur', '$barang_id', '$qty', '$harga', '{$item['subtotal']}')");
            }
            
            unset($_SESSION['bm_cart']);
            echo "<script>alert('Barang Masuk Berhasil Disimpan & Stok Bertambah!'); window.location='index.php?page=barang_masuk';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal menyimpan transaksi: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Hapus Riwayat Barang Masuk (Rollback Stok)
if(isset($_GET['hapus_bm'])) {
    $faktur = mysqli_real_escape_string($conn, $_GET['hapus_bm']);
    
    $det = mysqli_query($conn, "SELECT * FROM transaksi_detail WHERE no_faktur='$faktur'");
    while($d = mysqli_fetch_assoc($det)) {
        mysqli_query($conn, "UPDATE barang SET stok = stok - {$d['qty']} WHERE id='{$d['barang_id']}'");
    }
    
    mysqli_query($conn, "DELETE FROM transaksi_detail WHERE no_faktur='$faktur'");
    mysqli_query($conn, "DELETE FROM transaksi WHERE no_faktur='$faktur' AND id_usaha='$id_usaha'");
    
    echo "<script>alert('Transaksi Dihapus & Stok Dikembalikan!'); window.location='index.php?page=barang_masuk';</script>";
    exit;
}

// ==========================================
// 3. PHP AJAX HANDLER UNTUK MODAL DETAIL
// ==========================================
if(isset($_POST['get_detail_bm'])) {
    while (ob_get_level()) ob_end_clean(); // Bersihkan buffer

    $faktur = mysqli_real_escape_string($conn, $_POST['faktur']);
    
    $q = mysqli_query($conn, "
        SELECT td.*, b.nama_barang, b.satuan 
        FROM transaksi_detail td 
        LEFT JOIN barang b ON td.barang_id = b.id 
        WHERE td.no_faktur = '$faktur'
    ");
    
    echo '<table class="w-full text-sm border-collapse">';
    echo '<thead class="bg-gray-100 font-bold text-gray-600 sticky top-0"><tr><th class="p-3 border text-left">Nama Barang</th><th class="p-3 border text-center">Qty Masuk</th><th class="p-3 border text-right">Harga Modal</th><th class="p-3 border text-right">Subtotal</th></tr></thead><tbody class="divide-y">';
    
    $grand = 0;
    $ada_data = false;

    while($d = mysqli_fetch_assoc($q)) {
        $ada_data = true;
        $qty_show = (float)$d['qty'];
        $nama_brg = $d['nama_barang'] ?? '<span class="text-red-500 italic">Barang Terhapus</span>';
        
        echo "<tr class='hover:bg-gray-50 transition'>";
        echo "<td class='p-3 border font-bold text-gray-700'>{$nama_brg}</td>";
        echo "<td class='p-3 border text-center font-bold text-emerald-600 bg-emerald-50'>+ {$qty_show} {$d['satuan']}</td>";
        echo "<td class='p-3 border text-right text-gray-600'>" . number_format($d['harga_satuan'], 0, ',', '.') . "</td>";
        echo "<td class='p-3 border text-right font-bold text-gray-800'>" . number_format($d['subtotal'], 0, ',', '.') . "</td>";
        echo "</tr>";
        $grand += $d['subtotal'];
    }
    
    if(!$ada_data) {
        echo "<tr><td colspan='4' class='p-4 text-center text-red-500 font-bold'>Data detail kosong atau tidak ditemukan di database.</td></tr>";
    }

    echo '</tbody><tfoot class="bg-gray-50 border-t-2 border-gray-300"><tr><td colspan="3" class="p-3 text-right font-bold uppercase text-gray-600">Total Tagihan:</td><td class="p-3 text-right font-bold text-indigo-700 text-lg">Rp '.number_format($grand, 0, ',', '.').'</td></tr></tfoot></table>';
    exit;
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="bg-white p-6 rounded-lg shadow-sm h-fit border-t-4 border-green-600">
        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Input Barang Masuk</h3>
        
        <form method="POST">
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 mb-1">Nama Barang</label>
                <input list="list_barang" id="inputNamaBarang" name="nama_barang" class="w-full border p-2 rounded text-sm focus:outline-green-500" placeholder="Ketik nama / scan..." autocomplete="off" required>
                <datalist id="list_barang">
                    <?php 
                    // Ambil satuan juga untuk dikaitkan dengan javascript
                    $brg = mysqli_query($conn, "SELECT nama_barang, satuan FROM barang WHERE id_usaha='$id_usaha' ORDER BY nama_barang ASC");
                    while($b = mysqli_fetch_assoc($brg)) { 
                        echo "<option value='".htmlspecialchars($b['nama_barang'], ENT_QUOTES)."' data-satuan='".htmlspecialchars($b['satuan'], ENT_QUOTES)."'>"; 
                    }
                    ?>
                </datalist>
            </div>

            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 mb-1">Satuan (Otomatis dari Master)</label>
                <input type="text" id="inputSatuanBarang" name="satuan" class="w-full border p-2 rounded text-sm bg-gray-100 cursor-not-allowed font-bold" placeholder="Terisi otomatis..." readonly required>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 mb-1">Jumlah (Qty Masuk)</label>
                <input type="text" inputmode="decimal" name="qty" class="w-full border p-2 rounded text-sm font-bold border-green-200 bg-green-50" placeholder="Ketik Tanpa Titik (Contoh: 2800)" required>
                <div class="text-[10px] text-red-500 mt-1 italic">*PENTING: Jangan gunakan titik untuk ribuan!</div>
            </div>

            <button type="submit" name="tambah_item" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:bg-green-700 shadow-sm transition">
                <i class="fa-solid fa-plus mr-1"></i> Tambah ke List
            </button>
        </form>

        <?php if(isset($_SESSION['bm_cart']) && !empty($_SESSION['bm_cart'])): ?>
        <form method="POST" class="mt-2 text-center">
            <button type="submit" name="reset_bm" class="text-xs text-red-500 hover:text-red-700 underline font-bold mt-2">Kosongkan Keranjang Draft</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-2 space-y-6">
        
        <?php if(isset($_SESSION['bm_cart']) && !empty($_SESSION['bm_cart'])): ?>
        <div class="bg-green-50 p-5 rounded-lg border border-green-200">
            <h4 class="font-bold text-green-800 mb-3 flex items-center"><i class="fa-solid fa-clipboard-list mr-2"></i> Draft Barang Masuk:</h4>
            
            <div class="bg-white rounded shadow-sm overflow-x-auto mb-4 border">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600 border-b">
                        <tr>
                            <th class="p-2 text-left">Barang</th>
                            <th class="p-2 text-center">Qty</th>
                            <th class="p-2 text-right">Harga Beli</th>
                            <th class="p-2 text-right">Subtotal</th>
                            <th class="p-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php 
                        $total_draft = 0;
                        foreach($_SESSION['bm_cart'] as $k => $c): 
                            $total_draft += $c['subtotal'];
                        ?>
                        <tr>
                            <td class="p-2 font-bold text-gray-800"><?= htmlspecialchars($c['nama_barang']) ?></td>
                            <td class="p-2 text-center"><?= $c['qty'] ?> <?= htmlspecialchars($c['satuan']) ?></td>
                            <td class="p-2 text-right">Rp <?= number_format($c['harga'], 0, ',', '.') ?></td>
                            <td class="p-2 text-right font-bold text-indigo-700">Rp <?= number_format($c['subtotal'], 0, ',', '.') ?></td>
                            <td class="p-2 text-center">
                                <a href="index.php?page=barang_masuk&hapus_item=<?= $k ?>" class="text-red-500 hover:text-red-700 bg-red-50 p-1 rounded font-bold"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-100">
                        <tr>
                            <td colspan="3" class="p-2 text-right font-bold text-gray-600">Total Estimasi Aset Masuk:</td>
                            <td class="p-2 text-right font-bold text-green-700 text-lg">Rp <?= number_format($total_draft, 0, ',', '.') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <form method="POST" class="bg-white p-4 rounded border border-green-100 flex flex-col md:flex-row gap-3 items-end">
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Supplier / Toko Asal</label>
                    <select name="supplier_id" class="w-full border p-2 rounded text-sm" required>
                        <option value="">- Pilih Supplier -</option>
                        <?php 
                        $sup = mysqli_query($conn, "SELECT * FROM supplier WHERE id_usaha='$id_usaha' ORDER BY nama_supplier ASC");
                        while($s = mysqli_fetch_assoc($sup)) { echo "<option value='{$s['id']}'>{$s['nama_supplier']}</option>"; }
                        ?>
                    </select>
                </div>
                <div class="w-full md:w-1/3">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Tanggal & Waktu Masuk</label>
                    <input type="datetime-local" name="tanggal" value="<?= date('Y-m-d\TH:i') ?>" class="w-full border p-2 rounded text-sm" required>
                </div>
                <div class="w-full md:w-1/3">
                    <button type="submit" name="simpan_bm_final" onclick="if(confirm('Apakah Anda yakin data barang sudah benar? Stok akan otomatis bertambah.')){ this.innerText='Menyimpan...'; this.style.opacity='0.5'; this.style.pointerEvents='none'; return true; } else { return false; }" class="w-full bg-indigo-600 text-white font-bold py-2 rounded hover:bg-indigo-700 shadow-md transition">
                        <i class="fa-solid fa-paper-plane mr-1"></i> SIMPAN FINAL
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm border-t-4 border-gray-600">
            <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Riwayat Barang Masuk Terakhir</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 uppercase text-gray-600 font-bold">
                        <tr>
                            <th class="p-3">No Faktur</th>
                            <th class="p-3">Supplier</th>
                            <th class="p-3 text-center">Items</th>
                            <th class="p-3 text-right">Total</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $bm = mysqli_query($conn, "
                            SELECT t.*, s.nama_supplier, 
                            (SELECT COUNT(*) FROM transaksi_detail WHERE no_faktur=t.no_faktur) as jml_item
                            FROM transaksi t 
                            LEFT JOIN supplier s ON t.supplier_id = s.id 
                            WHERE t.jenis_transaksi='masuk' AND t.id_usaha='$id_usaha'
                            ORDER BY t.tanggal DESC LIMIT 20
                        ");
                        while($r = mysqli_fetch_assoc($bm)):
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <div class="font-bold text-green-700"><?= $r['no_faktur'] ?></div>
                                <div class="text-[10px] text-gray-400"><?= date('d M Y, H:i', strtotime($r['tanggal'])) ?></div>
                            </td>
                            <td class="p-3 font-medium text-gray-700"><?= $r['nama_supplier'] ?? 'Tanpa Nama' ?></td>
                            <td class="p-3 text-center">
                                <span class="bg-gray-100 px-2 py-1 rounded text-xs font-bold text-gray-600"><?= $r['jml_item'] ?> Barang</span>
                            </td>
                            <td class="p-3 text-right font-bold text-gray-800">Rp <?= number_format($r['total_transaksi'], 0, ',', '.') ?></td>
                            <td class="p-3 text-center">
                                <button type="button" onclick="lihatDetail('<?= $r['no_faktur'] ?>')" class="bg-gray-200 text-gray-700 px-2 py-1 rounded hover:bg-gray-300 text-xs font-bold mx-1" title="Lihat Isi">
                                    <i class="fa-solid fa-eye"></i> Cek
                                </button>
                                <?php if($_SESSION['role'] == 'admin'): ?>
                                <a href="index.php?page=barang_masuk&hapus_bm=<?= $r['no_faktur'] ?>" onclick="return confirm('PERINGATAN!\n\nHapus riwayat ini akan MENGURANGI KEMBALI stok barang yang sudah masuk. Anda yakin?')" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs font-bold mx-1" title="Hapus Permanen"><i class="fa-solid fa-trash"></i></a>
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

<div id="modalDetail" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-[100] items-center justify-center" style="display: none;">
    <div class="bg-white rounded-lg w-full max-w-2xl p-6 shadow-xl relative m-4">
        <button type="button" onclick="tutupModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-2xl"></i></button>
        <h3 class="text-xl font-bold mb-1 text-gray-800"><i class="fa-solid fa-box-open text-green-600 mr-2"></i> Detail Isi Faktur</h3>
        <p class="text-sm text-gray-500 mb-4 border-b pb-2">Kode Trx: <span id="labelFaktur" class="text-indigo-600 font-bold font-mono">...</span></p>
        <div id="kontenDetail" class="overflow-y-auto max-h-[60vh] custom-scrollbar">
            <p class="text-center italic text-gray-400 mt-10">Mencari data...</p>
        </div>
    </div>
</div>

<script>
// JS UNTUK MENDETEKSI NAMA BARANG DAN MENGISI SATUAN SECARA OTOMATIS
document.getElementById('inputNamaBarang').addEventListener('input', function() {
    let valueInput = this.value.trim().toLowerCase();
    let options = document.querySelectorAll('#list_barang option');
    let inputSatuan = document.getElementById('inputSatuanBarang');
    
    // Kosongkan dulu
    inputSatuan.value = '';
    
    // Cari kesesuaian dari datalist
    for (let option of options) {
        if (option.value.trim().toLowerCase() === valueInput) {
            let satuanTerdeteksi = option.getAttribute('data-satuan');
            if(satuanTerdeteksi) {
                inputSatuan.value = satuanTerdeteksi;
            }
            break;
        }
    }
});

function tutupModal() {
    document.getElementById('modalDetail').style.display = 'none';
}

function lihatDetail(faktur) {
    document.getElementById('modalDetail').style.display = 'flex';
    document.getElementById('labelFaktur').innerText = faktur;
    document.getElementById('kontenDetail').innerHTML = '<p class="text-center italic text-gray-400 mt-10"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data...</p>';
    
    let params = new URLSearchParams();
    params.append('get_detail_bm', 'true');
    params.append('faktur', faktur);
    
    fetch('index.php?page=barang_masuk', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
    })
    .then(html => { 
        document.getElementById('kontenDetail').innerHTML = html; 
    })
    .catch(err => { 
        document.getElementById('kontenDetail').innerHTML = '<p class="text-red-500 text-center font-bold mt-10">Gagal memuat data. Silakan coba lagi.</p>'; 
        console.error('AJAX Error:', err);
    });
}
</script>