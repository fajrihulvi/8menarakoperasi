<?php
wajib_akses('order_pelanggan');

// pages/order_pelanggan.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ========================================================
// PROTEKSI 1: CEK SESI MATI (IDLE SEHARIAN)
// ========================================================
if (empty($_SESSION['nama']) || empty($_SESSION['user_id'])) {
    echo "<script>
            alert('Sesi Anda telah habis karena tidak ada aktivitas. Silakan login kembali.');
            window.location.href = 'login.php'; 
          </script>";
    exit;
}

// ========================================================
// AUTO FIX DATABASE: Pastikan kolom ada
// ========================================================
$cek_kolom = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'jenis_harga'");
if($cek_kolom && mysqli_num_rows($cek_kolom) == 0) { @mysqli_query($conn, "ALTER TABLE users ADD jenis_harga VARCHAR(20) DEFAULT 'harga_jual'"); }
$cek_kolom_pel = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'pelanggan_id'");
if($cek_kolom_pel && mysqli_num_rows($cek_kolom_pel) == 0) { @mysqli_query($conn, "ALTER TABLE users ADD pelanggan_id INT(11) DEFAULT 0"); }

// ========================================================
// DETEKSI KUNCI HARGA USER & SINKRONISASI DAPUR
// ========================================================
$user_id = $_SESSION['user_id'];
$jenis_harga_user = 'harga_jual'; 
$pelanggan_id_user = 0;
$nama_pelanggan_default = $_SESSION['nama']; // fallback ke nama akun
$hp_default = '-';
$alamat_default = '-';

$q_user_harga = mysqli_query($conn, "SELECT jenis_harga, pelanggan_id FROM users WHERE id='$user_id'");
if($q_user_harga && mysqli_num_rows($q_user_harga) > 0) {
    $u_data = mysqli_fetch_assoc($q_user_harga);
    $jenis_harga_user = !empty($u_data['jenis_harga']) ? $u_data['jenis_harga'] : 'harga_jual';
    $pelanggan_id_user = (int)($u_data['pelanggan_id'] ?? 0);

    // Ambil data detail profil dapur jika sinkron
    if($pelanggan_id_user > 0) {
        $q_pel = mysqli_query($conn, "SELECT * FROM pelanggan WHERE id='$pelanggan_id_user'");
        if($p_data = mysqli_fetch_assoc($q_pel)) {
            $nama_pelanggan_default = $p_data['nama_pelanggan'];
            $hp_default = !empty($p_data['no_hp']) ? $p_data['no_hp'] : '-';
            $alamat_default = !empty($p_data['alamat']) ? $p_data['alamat'] : '-';
        }
    }
}

// Label untuk Tampilan UI
$label_dapur = "Harga Umum";
if($jenis_harga_user == 'harga_gabek') $label_dapur = "Area: Pangkalpinang";
if($jenis_harga_user == 'harga_kereta') $label_dapur = "Area: Bangka Tengah";
if($jenis_harga_user == 'harga_jebus') $label_dapur = "Area: Bangka Barat";

// --- PENENTUAN TARGET TOKO ---
$id_usaha_user = $_SESSION['id_usaha'] ?? 1;
$id_toko_target = ($_SESSION['role'] == 'invoice') ? 1 : $id_usaha_user;

// --- PROSES SIMPAN ---
if(isset($_POST['kirim_pesanan'])) {
    $items    = $_POST['id_barang'] ?? [];
    $qtys     = $_POST['qty'] ?? [];
    $tanggals = $_POST['tanggal_periode'] ?? [];
    $catatans = $_POST['catatan_item'] ?? [];

    // Kelompokkan baris item berdasarkan tanggal periode yang sama,
    // sehingga tiap tanggal berbeda menjadi 1 pesanan (no_pesanan) terpisah.
    $grup_per_tanggal = [];
    foreach($items as $key => $id_barang) {
        if(empty($id_barang)) continue;
        $tgl = trim((string)($tanggals[$key] ?? ''));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) continue;
        $grup_per_tanggal[$tgl][] = [
            'id_barang' => (int)$id_barang,
            'qty'       => (float)($qtys[$key] ?? 0),
            'catatan'   => trim((string)($catatans[$key] ?? '')),
        ];
    }

    if(count($grup_per_tanggal) > 0) {
        $urutan = 0;
        $jumlah_pesanan_dibuat = 0;

        foreach($grup_per_tanggal as $tgl_periode => $baris_item) {
            $tgl_valid = mysqli_real_escape_string($conn, $tgl_periode);
            $urutan++;
            $no_pesanan = "ORD-" . date('ymdHis') . "-" . $urutan;

            $total_bayar = 0;
            foreach($baris_item as $it) {
                $db = mysqli_fetch_assoc(mysqli_query($conn, "SELECT $jenis_harga_user, harga_jual FROM barang WHERE id='{$it['id_barang']}'"));
                $h_satuan = (isset($db[$jenis_harga_user]) && (float)$db[$jenis_harga_user] > 0) ? (float)$db[$jenis_harga_user] : (float)$db['harga_jual'];
                $total_bayar += ($h_satuan * $it['qty']);
            }

            // Ringkas catatan per item ke keterangan header agar tetap terbaca
            // di daftar pesanan tanpa harus membuka detail.
            $ringkasan_catatan = [];
            foreach($baris_item as $it) {
                if($it['catatan'] !== '') { $ringkasan_catatan[] = $it['catatan']; }
            }
            $keterangan_pesanan = "[PERIODE: " . date('d/m/Y', strtotime($tgl_valid)) . "]";
            if($ringkasan_catatan) {
                $keterangan_pesanan .= " [NOTE: " . implode('; ', $ringkasan_catatan) . "]";
            }
            $keterangan_pesanan = mysqli_real_escape_string($conn, $keterangan_pesanan);

            $q_header = "INSERT INTO pesanan (id_usaha, user_id, no_pesanan, nama_pelanggan, no_hp, alamat, total_bayar, status, keterangan, tanggal)
                         VALUES ('$id_toko_target', '$user_id', '$no_pesanan', '$nama_pelanggan_default', '$hp_default', '$alamat_default', '$total_bayar', 'Pending', '$keterangan_pesanan', '$tgl_valid')";

            if(mysqli_query($conn, $q_header)) {
                $id_pesanan = mysqli_insert_id($conn);
                foreach($baris_item as $it) {
                    $db = mysqli_fetch_assoc(mysqli_query($conn, "SELECT $jenis_harga_user, harga_jual FROM barang WHERE id='{$it['id_barang']}'"));
                    $h_fix = (isset($db[$jenis_harga_user]) && (float)$db[$jenis_harga_user] > 0) ? (float)$db[$jenis_harga_user] : (float)$db['harga_jual'];
                    $subtotal = $h_fix * $it['qty'];
                    $catatan_item = mysqli_real_escape_string($conn, $it['catatan']);

                    mysqli_query($conn, "INSERT INTO pesanan_detail (id_pesanan, id_barang, qty, harga_satuan, subtotal, catatan)
                                         VALUES ('$id_pesanan', '{$it['id_barang']}', '{$it['qty']}', '$h_fix', '$subtotal', '$catatan_item')");
                }
                $jumlah_pesanan_dibuat++;
            }
        }

        if($jumlah_pesanan_dibuat > 0) {
            echo "<script>alert('Berhasil! $jumlah_pesanan_dibuat pesanan terkirim (dikelompokkan per tanggal).'); window.location='index.php?page=riwayat_pesanan';</script>";
        } else {
            echo "<script>alert('Gagal membuat pesanan! Silakan coba lagi.');</script>";
        }
    } else {
        echo "<script>alert('Silakan pilih barang dan tanggal periode terlebih dahulu!');</script>";
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
    <div class="mb-6 border-b pb-4 flex flex-col md:flex-row justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800"><i class="fa-solid fa-cart-plus mr-2 text-indigo-600"></i> Form Order Barang</h2>
            <p class="text-slate-500 text-sm">Login: <b><?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8') ?></b></p>
        </div>
        
        <?php if($pelanggan_id_user > 0): ?>
            <div class="bg-green-50 border border-green-200 p-3 rounded-lg shadow-sm">
                <p class="text-[10px] text-green-700 font-bold mb-1 uppercase tracking-wide"><i class="fa-solid fa-link"></i> Terhubung ke Dapur:</p>
                <div class="text-sm font-bold text-gray-800"><?= $nama_pelanggan_default ?></div>
                <div class="text-[10px] text-indigo-600 font-bold mt-1 bg-indigo-100 px-2 py-0.5 rounded inline-block"><?= $label_dapur ?></div>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 p-3 rounded-lg shadow-sm">
                <div class="text-sm font-bold text-yellow-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Akun belum dihubungkan</div>
                <div class="text-[10px] text-gray-500 mt-1">Harap hubungi Admin untuk menghubungkan profil dapur Anda.</div>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST">
        <div class="grid grid-cols-1 gap-4 mb-6">
            <div class="bg-cyan-50 p-4 rounded-xl border border-cyan-100">
                <label class="block text-xs font-bold text-cyan-700 uppercase mb-2">Pilih Warehouse</label>
                <select id="filterWarehouse" onchange="terapkanFilterWarehouse()" class="w-full border p-3 rounded-lg shadow-sm focus:ring-2 focus:ring-cyan-500 outline-none bg-white">
                    <option value="">-- Semua Warehouse --</option>
                    <?php
                    $q_wh = mysqli_query($conn, "SELECT id, nama_warehouse FROM warehouse WHERE id_usaha='$id_toko_target' ORDER BY nama_warehouse ASC");
                    while($w = mysqli_fetch_assoc($q_wh)) {
                        echo '<option value="' . $w['id'] . '">' . htmlspecialchars($w['nama_warehouse'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    ?>
                </select>
                <p class="text-[11px] text-cyan-700 mt-2"><i class="fa-solid fa-circle-info mr-1"></i> Pilih warehouse untuk menyaring daftar barang. Biarkan "Semua Warehouse" untuk melihat seluruh barang.</p>
            </div>
        </div>

        <p class="text-xs text-slate-500 mb-3"><i class="fa-solid fa-circle-info text-indigo-500 mr-1"></i> Setiap baris punya tanggal periode dan catatan sendiri. Baris dengan tanggal berbeda akan otomatis dijadikan pesanan terpisah per hari.</p>

        <div class="overflow-x-auto mb-4 border rounded-xl">
            <table class="w-full text-sm text-left">
                <thead class="bg-indigo-600 text-white uppercase text-xs">
                    <tr>
                        <th class="p-3 w-[13%]">Tanggal Periode</th>
                        <th class="p-3 w-[22%]">Nama Barang</th>
                        <th class="p-3 w-[10%] text-center">Satuan</th>
                        <th class="p-3 w-[13%] text-right">Harga (<?= $label_dapur ?>)</th>
                        <th class="p-3 w-[10%] text-center">Qty</th>
                        <th class="p-3 w-[26%]">Catatan (Opsional)</th>
                        <th class="p-3 w-[6%] text-center"><i class="fa-solid fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="containerBarang">
                    <tr class="item-row border-b bg-white">
                        <td class="p-2">
                            <input type="date" name="tanggal_periode[]" class="w-full border p-2 rounded text-center font-bold" required>
                        </td>
                        <td class="p-2">
                            <select name="id_barang[]" class="w-full barang-select" required>
                                <option value="">-- Cari Barang --</option>
                                <?php
                                $sql = "SELECT id, nama_barang, satuan, warehouse_id, harga_jual, harga_gabek, harga_kereta, harga_jebus FROM barang WHERE id_usaha='$id_toko_target' ORDER BY nama_barang ASC";
                                $q = mysqli_query($conn, $sql);
                                while($b = mysqli_fetch_assoc($q)) {

                                    // KUNCIAN MUTLAK SINKRONISASI LAYAR
                                    $harga_final = (isset($b[$jenis_harga_user]) && (float)$b[$jenis_harga_user] > 0) ? (float)$b[$jenis_harga_user] : (float)$b['harga_jual'];
                                    $wh_barang = (int)($b['warehouse_id'] ?? 0);
                                    $nama_brg = htmlspecialchars($b['nama_barang'], ENT_QUOTES, 'UTF-8');
                                    $satuan_brg = htmlspecialchars($b['satuan'], ENT_QUOTES, 'UTF-8');

                                    echo "<option value='{$b['id']}' data-satuan='$satuan_brg' data-harga='$harga_final' data-warehouse='$wh_barang'>$nama_brg</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td class="p-2 text-center"><input type="text" class="w-full border p-2 rounded text-center bg-gray-100 satuan-input text-xs" readonly placeholder="-"></td>
                        <td class="p-2 text-right"><input type="text" class="w-full border p-2 rounded text-right bg-gray-50 harga-input font-bold text-blue-700" readonly placeholder="0"></td>
                        <td class="p-2"><input type="number" step="0.01" name="qty[]" value="1" class="w-full border p-2 rounded text-center font-bold" required></td>
                        <td class="p-2"><input type="text" name="catatan_item[]" class="w-full border p-2 rounded text-sm" placeholder="Cth: jangan terlalu matang"></td>
                        <td class="p-2 text-center"><button type="button" class="text-red-400" onclick="hapusBaris(this)"><i class="fa-solid fa-circle-minus text-xl"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button" onclick="tambahBaris()" class="mb-6 bg-green-50 text-green-700 border border-green-200 px-4 py-2 rounded-lg font-bold text-sm hover:bg-green-100 transition">
            <i class="fa-solid fa-plus mr-1"></i> Tambah Baris
        </button>

        <div class="p-4 bg-yellow-50 border border-yellow-100 rounded-xl text-center">
            <button type="submit" name="kirim_pesanan" class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-indigo-700 transition w-full md:w-auto">
                <i class="fa-solid fa-paper-plane mr-2"></i> KIRIM ORDER SEKARANG
            </button>
        </div>
    </form>
</div>

<script>
// Simpan seluruh daftar barang sekali di awal, dipakai untuk membangun ulang
// isi dropdown setiap kali warehouse berganti.
let semuaOpsiBarang = [];

$(document).ready(function() {
    $('.item-row:first').find('.barang-select option').each(function() {
        const opt = $(this);
        if (!opt.val()) return; // lewati placeholder
        semuaOpsiBarang.push({
            id: opt.val(),
            nama: opt.text(),
            satuan: opt.data('satuan'),
            harga: opt.data('harga'),
            warehouse: String(opt.data('warehouse') || '0')
        });
    });
    initSelect2($('.item-row:first'));
});

function initSelect2(row) {
    row.find('.barang-select').select2({ placeholder: "Ketik nama barang...", width: '100%' })
    .on('select2:select', function (e) {
        let opt = $(this).find(':selected');
        row.find('.satuan-input').val(opt.data('satuan'));
        row.find('.harga-input').val(new Intl.NumberFormat('id-ID').format(opt.data('harga')));
    });
}

// Bangun ulang isi satu dropdown barang sesuai warehouse yang dipilih.
function isiOpsiBarang(select, idWarehouse) {
    const nilaiSebelumnya = select.val();
    let html = '<option value="">-- Cari Barang --</option>';
    let nilaiMasihAda = false;

    semuaOpsiBarang.forEach(function (b) {
        if (idWarehouse !== '' && b.warehouse !== idWarehouse) return;
        if (b.id === nilaiSebelumnya) nilaiMasihAda = true;
        html += '<option value="' + b.id + '" data-satuan="' + b.satuan +
                '" data-harga="' + b.harga + '" data-warehouse="' + b.warehouse + '">' + b.nama + '</option>';
    });

    select.html(html);
    // Pertahankan pilihan lama bila barangnya masih ada di warehouse ini.
    select.val(nilaiMasihAda ? nilaiSebelumnya : '');
    return nilaiMasihAda;
}

// Terapkan filter warehouse ke semua baris yang sedang tampil.
function terapkanFilterWarehouse() {
    const idWarehouse = $('#filterWarehouse').val();

    $('.item-row').each(function () {
        const row = $(this);
        const select = row.find('.barang-select');

        select.select2('destroy');
        const masihAda = isiOpsiBarang(select, idWarehouse);
        initSelect2(row);

        // Kosongkan satuan & harga bila barang yang dipilih tidak ada di warehouse ini.
        if (!masihAda) {
            row.find('.satuan-input').val('');
            row.find('.harga-input').val('0');
        }
    });
}

function tambahBaris() {
    let originalRow = $('.item-row:first');
    let tanggalTerakhir = $('.item-row:last').find('input[name="tanggal_periode[]"]').val();
    originalRow.find('.barang-select').select2('destroy');
    let newRow = originalRow.clone();
    initSelect2(originalRow);
    newRow.find('input').val("");
    newRow.find('input[name="tanggal_periode[]"]').val(tanggalTerakhir || "");
    newRow.find('input[type="number"]').val(1);
    newRow.find('.harga-input').val("0");

    // Baris baru mengikuti warehouse yang sedang aktif.
    const selectBaru = newRow.find('.barang-select');
    isiOpsiBarang(selectBaru, $('#filterWarehouse').val());

    $('#containerBarang').append(newRow);
    initSelect2(newRow);
}

function hapusBaris(btn) { if($('.item-row').length > 1) $(btn).closest('tr').remove(); }
</script>