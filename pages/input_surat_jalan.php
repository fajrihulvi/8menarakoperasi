<?php
// pages/input_surat_jalan.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// AUTO FIX DATABASE
$cek_kolom = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'jenis_harga'");
if($cek_kolom && mysqli_num_rows($cek_kolom) == 0) { @mysqli_query($conn, "ALTER TABLE users ADD jenis_harga VARCHAR(20) DEFAULT 'harga_jual'"); }

// CEK AKSES (aturan terpusat di config/hak_akses.php)
wajib_akses('input_surat_jalan');
tolak_jika_tidak_boleh('tambah', 'surat_jalan', 'index.php');

// PROSES SIMPAN DATA (Logika pemotongan stok UTUH sesuai aslinya)
if(isset($_POST['simpan_sj'])) {
    $pelanggan_id = $_POST['pelanggan_id'];
    $tanggal_raw  = $_POST['tanggal']; 
    $tanggal      = $tanggal_raw . ' ' . date('H:i:s'); 
    $nama_driver  = mysqli_real_escape_string($conn, $_POST['nama_driver']);
    $nopol        = mysqli_real_escape_string($conn, $_POST['nopol']);
    $user_id      = $_SESSION['user_id'];
    $catatan_sj   = mysqli_real_escape_string($conn, $_POST['keterangan_sj']);
    $status_transaksi = isset($_POST['status_transaksi']) ? strtolower(trim($_POST['status_transaksi'])) : 'selesai';
    
    $items  = $_POST['id_barang'];
    $qtys   = $_POST['qty'];
    $hargas = $_POST['harga_manual']; 

    $total_transaksi = 0;
    foreach($items as $key => $val) {
        if(empty($items[$key])) continue;
        $total_transaksi += ($qtys[$key] * $hargas[$key]);
    }

    if(sj_butuh_approval()) {
        $data_items = [];
        foreach($items as $key => $val) {
            if(empty($items[$key])) continue;
            $data_items[] = ['id_barang' => $items[$key], 'qty' => $qtys[$key], 'harga' => $hargas[$key]];
        }
        $payload = ['pelanggan_id' => $pelanggan_id, 'nama_driver' => $nama_driver, 'nopol' => $nopol, 'tanggal' => $tanggal_raw, 'total' => $total_transaksi, 'keterangan' => $catatan_sj, 'items' => $data_items, 'status' => $status_transaksi];
        $json_data = json_encode($payload);
        $d_pel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_pelanggan FROM pelanggan WHERE id='$pelanggan_id'"));
        $keterangan = "Request Surat Jalan: ".($d_pel['nama_pelanggan'] ?? 'Unknown')." ($catatan_sj). Driver: $nama_driver. Total: Rp " . number_format($total_transaksi);
        $q_req = "INSERT INTO approval_request (id_usaha, user_id, tipe_aksi, keterangan, data_json, status) VALUES ('$id_usaha', '$user_id', 'input_sj', '$keterangan', '$json_data', 'pending')";
        if(mysqli_query($conn, $q_req)) { echo "<script>alert('Surat Jalan Menunggu Persetujuan Manager!'); window.location='index.php?page=input_surat_jalan';</script>"; } 
        exit(); 
    }

    $no_faktur = "KDMP-" . date('YmdHis'); 
    
    // -------------------------------------------------------------------
    // PERBAIKAN: SET DEFAULT BELUM LUNAS DAN BAYAR = 0
    // -------------------------------------------------------------------
    $query_header = "INSERT INTO transaksi (id_usaha, no_faktur, jenis_transaksi, total_transaksi, bayar, kembalian, pelanggan_id, nama_driver, nopol, user_id, status, status_bayar, tanggal, keterangan) 
                      VALUES ('$id_usaha', '$no_faktur', 'keluar', '$total_transaksi', 0, 0, '$pelanggan_id', '$nama_driver', '$nopol', '$user_id', '$status_transaksi', 'belum', '$tanggal', '$catatan_sj')";

    if(mysqli_query($conn, $query_header)) {
        foreach($items as $key => $id_barang) {
            if(empty($id_barang)) continue;
            $qty_input = $qtys[$key]; $harga_input = $hargas[$key]; $subtotal = $qty_input * $harga_input;
            $hpp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli FROM barang WHERE id='$id_barang' AND id_usaha='$id_usaha'"))['harga_beli'];

            mysqli_query($conn, "INSERT INTO transaksi_detail (no_faktur, barang_id, qty, harga_satuan, hpp, subtotal) VALUES ('$no_faktur', '$id_barang', '$qty_input', '$harga_input', '$hpp', '$subtotal')");
            
            // PEMOTONGAN STOK
            if ($status_transaksi === 'selesai') {
                mysqli_query($conn, "UPDATE barang SET stok = stok - $qty_input WHERE id='$id_barang'");
            }
        }
        echo "<script>alert('Transaksi Berhasil Dibuat dengan status ".strtoupper($status_transaksi)." (BELUM LUNAS)!'); window.open('cetak_surat_jalan.php?no_faktur=$no_faktur', '_blank'); window.location='index.php?page=input_surat_jalan';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan: ".mysqli_error($conn)."');</script>";
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .select2-container .select2-selection--single { height: 42px !important; border: 1px solid #e5e7eb !important; border-radius: 0.5rem !important; padding: 5px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container--default .select2-results__option[aria-disabled=true] { background-color: #f3f4f6; color: #9ca3af; }
</style>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="border-b pb-4 mb-6">
        <h3 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-file-signature mr-2"></i> Input Pengiriman & Penjualan</h3>
        <p class="text-gray-500 text-sm">Harga akan otomatis mengikuti setingan kunci harga milik Dapur/Pelanggan yang dipilih.</p>
    </div>

    <form method="POST" id="formSJ">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6 bg-blue-50 p-4 rounded border border-blue-100 shadow-inner">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Tanggal</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded focus:outline-indigo-500" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-indigo-700 uppercase mb-1"><i class="fa-solid fa-store mr-1"></i> Pelanggan</label>
                <select name="pelanggan_id" id="pilihPelanggan" class="w-full border p-2 rounded focus:outline-indigo-500 select2-pelanggan" onchange="updateSemuaHarga()" required>
                    <option value="" data-jenisharga="harga_jual">-- Pilih Dapur --</option>
                    <?php
                    // MENGAMBIL DATA MASTER PELANGGAN + SINKRONISASI KUNCI HARGA
                    $pel = mysqli_query($conn, "
                        SELECT p.*, 
                               (SELECT jenis_harga FROM users WHERE pelanggan_id = p.id AND jenis_harga != 'harga_jual' LIMIT 1) as harga_lock 
                        FROM pelanggan p 
                        WHERE p.id_usaha='$id_usaha' 
                        ORDER BY p.nama_pelanggan ASC
                    ");
                    while($p = mysqli_fetch_assoc($pel)) { 
                        $hk = !empty($p['harga_lock']) ? $p['harga_lock'] : 'harga_jual';
                        echo "<option value='{$p['id']}' data-jenisharga='{$hk}'>{$p['nama_pelanggan']}</option>"; 
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Nama Driver</label>
                <select name="nama_driver" id="pilihDriver" class="w-full border p-2 rounded focus:outline-indigo-500 bg-white" onchange="isiNopol()" required>
                    <option value="">-- Pilih Driver --</option>
                    <?php
                    $q_driver = mysqli_query($conn, "SELECT nama, nopol FROM users WHERE role='driver' AND id_usaha='$id_usaha' ORDER BY nama ASC");
                    while($d = mysqli_fetch_assoc($q_driver)) { echo "<option value='{$d['nama']}' data-nopol='{$d['nopol']}'>{$d['nama']} ({$d['nopol']})</option>"; }
                    ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">No. Polisi</label>
                <input type="text" name="nopol" id="inputNopol" class="w-full border p-2 rounded bg-gray-200 focus:outline-none text-gray-600 font-bold" placeholder="Otomatis..." readonly>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catatan</label>
                <input type="text" name="keterangan_sj" class="w-full border border-gray-300 p-2 rounded focus:ring-1 focus:ring-indigo-500 outline-none" placeholder="Cth: Senin-Rabu">
            </div>

            <div>
                <label class="block text-xs font-bold text-indigo-700 uppercase mb-1"><i class="fa-solid fa-bell mr-1"></i> Status</label>
                <select name="status_transaksi" class="w-full border-2 border-indigo-300 p-2 rounded focus:outline-indigo-500 bg-white font-bold text-indigo-700 cursor-pointer" required>
                    <option value="pengiriman">Pengiriman (Draft)</option>
                    <option value="pending">Pending</option>
                    <option value="selesai" selected>Selesai (Potong Stok)</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto mb-4">
            <table class="w-full border-collapse border border-gray-300 text-sm" id="tableBarang">
                <thead>
                    <tr class="bg-indigo-600 text-white">
                        <th class="p-3 text-left w-4/12">Nama Barang (Cari)</th>
                        <th class="p-3 text-center w-2/12">Qty</th>
                        <th class="p-3 text-right w-3/12">Harga Jual</th>
                        <th class="p-3 text-right w-2/12">Subtotal</th>
                        <th class="p-3 text-center w-1/12"><i class="fa-solid fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="containerBarang">
                    <tr class="item-row bg-white border-b">
                        <td class="p-2 align-top">
                            <select name="id_barang[]" class="w-full border p-2 rounded bg-gray-50 barang-select" onchange="isiHarga(this)" style="width: 100%;" required>
                                <option value="">-- Ketik Nama Barang --</option>
                                <?php
                                $brg = mysqli_query($conn, "SELECT * FROM barang WHERE id_usaha='$id_usaha' ORDER BY nama_barang ASC");
                                while($b = mysqli_fetch_assoc($brg)) {
                                    $stok = (float)$b['stok'];
                                    $label_stok = ($stok <= 0) ? "HABIS" : $stok;
                                    $harga_jebus_val = isset($b['harga_jebus']) ? $b['harga_jebus'] : 0;
                                    echo "<option value='{$b['id']}' data-harga='{$b['harga_jual']}' data-beli='{$b['harga_beli']}' data-gabek='{$b['harga_gabek']}' data-kereta='{$b['harga_kereta']}' data-jebus='{$harga_jebus_val}'> {$b['kode_barang']} - {$b['nama_barang']} (Stok: $label_stok) </option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td class="p-2 align-top"><input type="number" step="0.01" name="qty[]" class="w-full border p-2 rounded text-center qty-input" value="1" oninput="hitungSubtotal(this)" required></td>
                        <td class="p-2 align-top">
                            <input type="number" step="any" name="harga_manual[]" class="w-full border p-2 rounded text-right harga-input font-bold text-indigo-700" value="0" oninput="hitungSubtotal(this)" required>
                            <div class="text-[10px] text-right mt-1 bg-gray-50 p-1 rounded border border-gray-100 leading-tight">
                                <div class="info-tipe mb-0.5"><span class="text-gray-400 italic">Pilih dapur di atas...</span></div>
                                <div class="info-modal text-gray-500 font-medium">Modal: Rp 0</div>
                            </div>
                        </td>
                        <td class="p-2 text-right font-bold text-gray-700 subtotal-display align-top pt-4">0</td>
                        <td class="p-2 text-center align-top pt-4"><button type="button" class="text-red-500 hover:text-red-700" onclick="hapusBaris(this)"><i class="fa-solid fa-circle-minus text-lg"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center bg-gray-100 p-4 rounded">
            <button type="button" onclick="tambahBaris()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 font-bold text-sm">
                <i class="fa-solid fa-plus mr-1"></i> Tambah Baris Barang
            </button>
            <div class="text-right">
                <span class="text-gray-600 font-bold mr-2">Grand Total:</span>
                <span class="text-2xl font-bold text-indigo-700" id="grandTotal">Rp 0</span>
            </div>
        </div>

        <div class="mt-6 text-right">
            <button type="submit" name="simpan_sj" class="bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg hover:bg-indigo-700 font-bold w-full md:w-auto">
                <i class="fa-solid <?= sj_butuh_approval() ? 'fa-paper-plane' : 'fa-save' ?> mr-2"></i> 
                <?= sj_butuh_approval() ? 'REQUEST APPROVAL KE ADMIN' : 'SIMPAN & CETAK SURAT JALAN' ?>
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    $('.select2-pelanggan').select2(); 
    initSelect2Row($('.item-row:first')); 
});

function isiNopol() {
    var select = document.getElementById("pilihDriver");
    var nopol = select.options[select.selectedIndex].getAttribute("data-nopol");
    document.getElementById("inputNopol").value = nopol || "";
}

function initSelect2Row(row) {
    row.find('.barang-select').select2({ placeholder: "-- Cari Barang --", allowClear: true }).on('select2:select', function (e) { isiHarga(this); });
}

function updateSemuaHarga() {
    document.querySelectorAll('.barang-select').forEach(select => {
        if (select.value) isiHarga(select);
    });
}

function isiHarga(selectElement) {
    let option = selectElement.options[selectElement.selectedIndex];
    if(!option || option.value === "") return;

    let hargaMaster = parseFloat(option.getAttribute('data-harga')) || 0;
    let hargaBeli   = parseFloat(option.getAttribute('data-beli')) || 0;
    let hargaGabek  = parseFloat(option.getAttribute('data-gabek')) || 0;
    let hargaKereta = parseFloat(option.getAttribute('data-kereta')) || 0;
    let hargaJebus  = parseFloat(option.getAttribute('data-jebus')) || 0; 
    
    let row = selectElement.closest('tr');
    let pelangganSelect = document.getElementById('pilihPelanggan');
    let pelangganOption = pelangganSelect.options[pelangganSelect.selectedIndex];
    let pelangganId = pelangganSelect.value;
    
    let jenisHargaDapur = pelangganOption ? pelangganOption.getAttribute('data-jenisharga') : 'harga_jual';

    let hargaFinal = hargaMaster;
    let labelTipe = '<span class="text-blue-600 font-bold"><i class="fa-solid fa-tag"></i> Harga Standar (Umum)</span>';

    if(!pelangganId) {
        labelTipe = '<span class="text-amber-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Pilih dapur di atas!</span>';
    } else {
        if (jenisHargaDapur === 'harga_gabek') {
            hargaFinal = hargaGabek > 0 ? hargaGabek : hargaMaster;
            labelTipe = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-star"></i> Harga Otomatis: Pangkalpinang</span>`;
        } else if (jenisHargaDapur === 'harga_kereta') {
            hargaFinal = hargaKereta > 0 ? hargaKereta : hargaMaster;
            labelTipe = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-star"></i> Harga Otomatis: Bangka Tengah</span>`;
        } else if (jenisHargaDapur === 'harga_jebus') {
            hargaFinal = hargaJebus > 0 ? hargaJebus : hargaMaster;
            labelTipe = `<span class="text-emerald-600 font-bold"><i class="fa-solid fa-star"></i> Harga Otomatis: Bangka Barat</span>`;
        } else {
            hargaFinal = hargaMaster;
            labelTipe = `<span class="text-blue-600 font-bold"><i class="fa-solid fa-tag"></i> Harga Standar (Umum)</span>`;
        }
    }
    
    let inputHarga = row.querySelector('.harga-input');
    inputHarga.value = hargaFinal;
    
    row.querySelector('.info-tipe').innerHTML = labelTipe;
    row.querySelector('.info-modal').innerHTML = "Modal: Rp " + parseInt(hargaBeli).toLocaleString('id-ID');
    
    if(parseInt(hargaFinal) < parseInt(hargaBeli)) { inputHarga.classList.add('bg-red-100', 'text-red-700'); } else { inputHarga.classList.remove('bg-red-100', 'text-red-700'); }
    
    hitungSubtotal(selectElement);
}

function hitungSubtotal(element) {
    let row = element.closest('tr');
    let qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    let harga = parseFloat(row.querySelector('.harga-input').value) || 0;
    
    let select = row.querySelector('select');
    let option = select.options[select.selectedIndex];
    if(option) {
        let hargaBeli = parseFloat(option.getAttribute('data-beli') || 0);
        let inputHarga = row.querySelector('.harga-input');
        if(harga < hargaBeli) { inputHarga.classList.add('border-red-500', 'text-red-600'); } else { inputHarga.classList.remove('border-red-500', 'text-red-600'); }
    }

    let subtotal = qty * harga;
    row.querySelector('.subtotal-display').innerText = subtotal.toLocaleString('id-ID');
    hitungGrandTotal();
}

function hitungGrandTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        let qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        let harga = parseFloat(row.querySelector('.harga-input').value) || 0;
        total += (qty * harga);
    });
    document.getElementById('grandTotal').innerText = "Rp " + total.toLocaleString('id-ID');
}

function tambahBaris() {
    let originalRow = $('.item-row:first');
    originalRow.find('.barang-select').select2('destroy');
    let newRow = originalRow.clone();
    initSelect2Row(originalRow); 
    
    newRow.find('.qty-input').val(1);
    newRow.find('.harga-input').val(0);
    newRow.find('.subtotal-display').text("0");
    newRow.find('.info-tipe').html('<span class="text-gray-400 italic">Pilih dapur di atas...</span>');
    newRow.find('.info-modal').text("Modal: Rp 0");
    newRow.find('select').val("").trigger('change'); 
    
    $('#containerBarang').append(newRow);
    initSelect2Row(newRow);
}

function hapusBaris(btn) {
    let rows = document.querySelectorAll('.item-row');
    if(rows.length > 1) { btn.closest('tr').remove(); hitungGrandTotal(); } else { alert("Minimal harus ada 1 barang!"); }
}
</script>