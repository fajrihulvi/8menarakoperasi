<?php
wajib_akses('stock_opname');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';

$role     = $_SESSION['role'] ?? '';
$user_id  = $_SESSION['user_id'] ?? 0;
$id_usaha = $_SESSION['id_usaha'] ?? 1;

// ==========================================
// 1. TAMBAH ITEM KE KERANJANG OPNAME (SESSION)
// ==========================================
if(isset($_POST['tambah_item_opname'])) {
    tolak_jika_tidak_boleh('tambah', 'stock_opname', 'index.php?page=stock_opname');

    $id_barang  = (int)$_POST['id_barang'];
    $stok_fisik = (float)$_POST['stok_fisik'];
    $alasan     = trim($_POST['alasan'] ?? '');

    $b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, nama_barang, satuan, stok FROM barang WHERE id='$id_barang' AND id_usaha='$id_usaha'"));

    if($b && $alasan !== '') {
        $stok_sistem = (float)$b['stok'];
        $_SESSION['opname_cart'][] = [
            'barang_id'   => $b['id'],
            'nama_barang' => $b['nama_barang'],
            'satuan'      => $b['satuan'],
            'stok_sistem' => $stok_sistem,
            'stok_fisik'  => $stok_fisik,
            'selisih'     => $stok_fisik - $stok_sistem,
            'alasan'      => $alasan,
        ];
    } else {
        echo "<script>alert('Alasan wajib diisi dan barang harus valid!');</script>";
    }
    echo "<script>window.location='index.php?page=stock_opname';</script>";
}

// Hapus item dari keranjang
if(isset($_GET['hapus_item_opname'])) {
    $idx = (int)$_GET['hapus_item_opname'];
    unset($_SESSION['opname_cart'][$idx]);
    if(isset($_SESSION['opname_cart'])) { $_SESSION['opname_cart'] = array_values($_SESSION['opname_cart']); }
    echo "<script>window.location='index.php?page=stock_opname';</script>";
}

// Reset keranjang
if(isset($_POST['reset_opname'])) {
    unset($_SESSION['opname_cart']);
    echo "<script>window.location='index.php?page=stock_opname';</script>";
}

// ==========================================
// 2. AJUKAN OPNAME (KIRIM KE ADMIN UNTUK APPROVAL)
// ==========================================
if(isset($_POST['ajukan_opname'])) {
    tolak_jika_tidak_boleh('tambah', 'stock_opname', 'index.php?page=stock_opname');

    $cart    = $_SESSION['opname_cart'] ?? [];
    $catatan = trim(htmlspecialchars($_POST['catatan_umum'] ?? ''));

    if(!empty($cart)) {
        $no_opname = "SO-" . date('YmdHis');

        $stmt = mysqli_prepare($conn, "INSERT INTO stock_opname (id_usaha, no_opname, user_id, catatan, status) VALUES (?, ?, ?, ?, 'pending')");
        mysqli_stmt_bind_param($stmt, 'isis', $id_usaha, $no_opname, $user_id, $catatan);

        if(mysqli_stmt_execute($stmt)) {
            $opname_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            foreach($cart as $item) {
                $stmt_d = mysqli_prepare($conn, "INSERT INTO stock_opname_detail (stock_opname_id, barang_id, stok_sistem, stok_fisik, selisih, alasan) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_d, 'iiddds', $opname_id, $item['barang_id'], $item['stok_sistem'], $item['stok_fisik'], $item['selisih'], $item['alasan']);
                mysqli_stmt_execute($stmt_d);
                mysqli_stmt_close($stmt_d);
            }

            if(function_exists('catat_log')) {
                catat_log($conn, 'Ajukan Stock Opname', "Mengajukan stock opname $no_opname (" . count($cart) . " item) untuk persetujuan admin.");
            }

            unset($_SESSION['opname_cart']);
            $_SESSION['notif_opname'] = 'Stock Opname berhasil diajukan! Menunggu persetujuan Admin.';
            echo "<script>window.location='index.php?page=stock_opname';</script>";
        } else {
            echo "<script>alert('Gagal mengajukan Stock Opname.');</script>";
        }
    } else {
        echo "<script>alert('Tambahkan minimal 1 item sebelum mengajukan.');</script>";
    }
}

// ==========================================
// 3. APPROVE / REJECT (KHUSUS ADMIN)
// ==========================================
if(isset($_POST['respon_opname'])) {
    if($role !== 'admin') {
        echo "<script>alert('Akses ditolak! Hanya Admin yang boleh menyetujui Stock Opname.');</script>";
    } else {
        $id_opname = (int)$_POST['id_opname'];
        $aksi      = $_POST['respon_opname']; // 'approve' | 'reject'
        $alasan_tolak = trim(htmlspecialchars($_POST['alasan_tolak'] ?? ''));

        $so = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_opname WHERE id='$id_opname' AND status='pending'"));

        if($so) {
            if($aksi === 'approve') {
                $details = mysqli_query($conn, "SELECT * FROM stock_opname_detail WHERE stock_opname_id='$id_opname'");
                while($d = mysqli_fetch_assoc($details)) {
                    mysqli_query($conn, "UPDATE barang SET stok='{$d['stok_fisik']}' WHERE id='{$d['barang_id']}'");
                }

                mysqli_query($conn, "UPDATE stock_opname SET status='approved', responden_id='$user_id', tgl_respon=NOW() WHERE id='$id_opname'");

                if(function_exists('catat_log')) {
                    catat_log($conn, 'Approve Stock Opname', "Menyetujui stock opname {$so['no_opname']}. Stok sistem disesuaikan ke hasil hitung fisik.");
                }
                $_SESSION['notif_opname'] = 'Stock Opname disetujui, stok sistem telah disesuaikan.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE stock_opname SET status='rejected', responden_id=?, tgl_respon=NOW(), alasan_tolak=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'isi', $user_id, $alasan_tolak, $id_opname);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if(function_exists('catat_log')) {
                    catat_log($conn, 'Tolak Stock Opname', "Menolak stock opname {$so['no_opname']}.");
                }
                $_SESSION['notif_opname'] = 'Stock Opname ditolak.';
            }
        }
        echo "<script>window.location='index.php?page=stock_opname';</script>";
    }
}
?>

<?php if(!empty($_SESSION['notif_opname'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() { alert(<?= json_encode($_SESSION['notif_opname']) ?>); });
    </script>
    <?php unset($_SESSION['notif_opname']); ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <?php if(boleh('tambah', 'stock_opname')): ?>
    <div class="bg-white p-6 rounded-lg shadow-sm h-fit border-t-4 border-cyan-600">
        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2"><i class="fa-solid fa-clipboard-list mr-1 text-cyan-600"></i> Input Hasil Hitung Fisik</h3>

        <form method="POST">
            <div class="mb-2">
                <label class="block text-xs font-bold text-gray-500">Nama Barang</label>
                <select name="id_barang" class="w-full border p-2 rounded text-sm" required>
                    <option value="">-- Pilih Barang --</option>
                    <?php
                    $brg = mysqli_query($conn, "SELECT id, nama_barang, satuan, stok FROM barang WHERE id_usaha='$id_usaha' ORDER BY nama_barang ASC");
                    while($b = mysqli_fetch_assoc($brg)) {
                        echo "<option value='{$b['id']}'>{$b['nama_barang']} (Stok sistem: " . (float)$b['stok'] . " {$b['satuan']})</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-2">
                <label class="block text-xs font-bold text-gray-500">Stok Fisik (Hasil Hitung)</label>
                <input type="number" step="0.01" name="stok_fisik" class="w-full border p-2 rounded text-sm font-bold" placeholder="0.00" required>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500">Alasan Selisih <span class="text-red-500">*</span></label>
                <input type="text" name="alasan" class="w-full border p-2 rounded text-sm" placeholder="Cth: Barang rusak, susut, salah catat, dll" required>
            </div>

            <button type="submit" name="tambah_item_opname" class="w-full bg-cyan-600 text-white font-bold py-2 rounded hover:bg-cyan-700 shadow-sm">
                <i class="fa-solid fa-plus-circle mr-1"></i> Tambah ke Daftar
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="<?= boleh('tambah', 'stock_opname') ? 'lg:col-span-2' : 'lg:col-span-3' ?> space-y-6">

        <?php if(!empty($_SESSION['opname_cart'])): ?>
        <div class="bg-cyan-50 p-4 rounded-lg border border-cyan-200">
            <h4 class="font-bold text-cyan-800 mb-2">Draft Pengajuan Stock Opname</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm bg-white rounded shadow-sm">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="p-2 text-left">Barang</th>
                            <th class="p-2 text-center">Stok Sistem</th>
                            <th class="p-2 text-center">Stok Fisik</th>
                            <th class="p-2 text-center">Selisih</th>
                            <th class="p-2 text-left">Alasan</th>
                            <th class="p-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($_SESSION['opname_cart'] as $k => $c):
                            $tanda = $c['selisih'] > 0 ? '+' : '';
                            $warna_selisih = $c['selisih'] == 0 ? 'text-gray-500' : ($c['selisih'] > 0 ? 'text-green-600' : 'text-red-600');
                        ?>
                        <tr class="border-b">
                            <td class="p-2"><?= htmlspecialchars($c['nama_barang'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-2 text-center"><?= $c['stok_sistem'] ?> <?= $c['satuan'] ?></td>
                            <td class="p-2 text-center font-bold"><?= $c['stok_fisik'] ?> <?= $c['satuan'] ?></td>
                            <td class="p-2 text-center font-bold <?= $warna_selisih ?>"><?= $tanda . $c['selisih'] ?></td>
                            <td class="p-2 text-xs text-gray-600"><?= htmlspecialchars($c['alasan'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-2 text-center"><a href="index.php?page=stock_opname&hapus_item_opname=<?= $k ?>" class="text-red-500"><i class="fa-solid fa-times"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" class="mt-3">
                <div class="mb-3">
                    <label class="block text-xs font-bold text-gray-600 mb-1">Catatan Umum (Opsional)</label>
                    <input type="text" name="catatan_umum" class="w-full border p-2 rounded text-sm" placeholder="Cth: Opname rutin bulanan Gudang Utama">
                </div>
                <div class="flex justify-between gap-2">
                    <button type="submit" name="reset_opname" onclick="return confirm('Reset seluruh daftar?')" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-bold">Reset</button>
                    <button type="submit" name="ajukan_opname" class="bg-cyan-600 text-white px-6 py-2 rounded font-bold text-sm hover:bg-cyan-700">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Ajukan ke Admin
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if($role === 'admin'): ?>
        <div class="bg-white p-6 rounded-lg shadow-sm border-t-4 border-amber-500">
            <h3 class="font-bold text-gray-800 mb-4 border-b pb-2"><i class="fa-solid fa-hourglass-half mr-1 text-amber-500"></i> Menunggu Persetujuan</h3>

            <?php
            $q_pending = mysqli_query($conn, "SELECT so.*, u.nama FROM stock_opname so LEFT JOIN users u ON so.user_id = u.id WHERE so.status='pending' AND so.id_usaha='$id_usaha' ORDER BY so.tgl_pengajuan ASC");
            if(mysqli_num_rows($q_pending) == 0):
            ?>
                <p class="text-center text-gray-400 py-6">Tidak ada pengajuan Stock Opname yang menunggu.</p>
            <?php else: while($so = mysqli_fetch_assoc($q_pending)): ?>
                <div class="border rounded-lg p-4 mb-4 bg-amber-50 border-amber-200">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-bold text-gray-800"><?= $so['no_opname'] ?></div>
                            <div class="text-xs text-gray-500">Diajukan oleh <b><?= htmlspecialchars($so['nama'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></b> &middot; <?= date('d/m/Y H:i', strtotime($so['tgl_pengajuan'])) ?></div>
                            <?php if(!empty($so['catatan'])): ?>
                                <div class="text-xs italic text-gray-600 mt-1"><?= htmlspecialchars($so['catatan'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="overflow-x-auto mb-3">
                        <table class="w-full text-xs bg-white rounded border">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="p-2 text-left">Barang</th>
                                    <th class="p-2 text-center">Sistem</th>
                                    <th class="p-2 text-center">Fisik</th>
                                    <th class="p-2 text-center">Selisih</th>
                                    <th class="p-2 text-left">Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_det = mysqli_query($conn, "SELECT sod.*, b.nama_barang, b.satuan FROM stock_opname_detail sod LEFT JOIN barang b ON sod.barang_id = b.id WHERE sod.stock_opname_id='{$so['id']}'");
                                while($d = mysqli_fetch_assoc($q_det)):
                                    $tanda = $d['selisih'] > 0 ? '+' : '';
                                    $warna = $d['selisih'] == 0 ? 'text-gray-500' : ($d['selisih'] > 0 ? 'text-green-600' : 'text-red-600');
                                ?>
                                <tr class="border-b last:border-0">
                                    <td class="p-2"><?= $d['nama_barang'] ?></td>
                                    <td class="p-2 text-center"><?= (float)$d['stok_sistem'] ?> <?= $d['satuan'] ?></td>
                                    <td class="p-2 text-center font-bold"><?= (float)$d['stok_fisik'] ?> <?= $d['satuan'] ?></td>
                                    <td class="p-2 text-center font-bold <?= $warna ?>"><?= $tanda . (float)$d['selisih'] ?></td>
                                    <td class="p-2"><?= htmlspecialchars($d['alasan'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-2">
                        <form method="POST" onsubmit="return confirm('Setujui Stock Opname ini? Stok sistem akan langsung disesuaikan.')" class="flex-1">
                            <input type="hidden" name="id_opname" value="<?= $so['id'] ?>">
                            <button type="submit" name="respon_opname" value="approve" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-2 rounded text-xs font-bold">
                                <i class="fa-solid fa-check"></i> SETUJUI
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirmTolak(this)" class="flex-1">
                            <input type="hidden" name="id_opname" value="<?= $so['id'] ?>">
                            <input type="hidden" name="alasan_tolak" class="input-alasan-tolak">
                            <button type="submit" name="respon_opname" value="reject" class="w-full bg-white border border-red-300 text-red-500 hover:bg-red-50 py-2 rounded text-xs font-bold">
                                <i class="fa-solid fa-xmark"></i> TOLAK
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm border-t-4 border-gray-600">
            <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Riwayat Stock Opname</h3>

            <?= render_filter('Cari no opname / pengaju...') ?>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 uppercase text-gray-600 font-bold">
                        <tr>
                            <th class="p-3">No Opname</th>
                            <th class="p-3">Pengaju</th>
                            <th class="p-3 text-center">Item</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $so_cari  = ambil_kata_kunci();
                        $so_hal   = ambil_halaman();
                        $so_limit = ambil_per_halaman();

                        [$so_where, $so_params, $so_tipe] = bangun_filter(
                            $so_cari, ['so.no_opname', 'u.nama'],
                            ['so.id_usaha = ?'], [$id_usaha], 'i'
                        );

                        $so_total  = hitung_total($conn, 'stock_opname so LEFT JOIN users u ON so.user_id = u.id', $so_where, $so_params, $so_tipe);
                        $so_hal    = batasi_halaman($so_hal, $so_total, $so_limit);
                        $so_offset = ($so_hal - 1) * $so_limit;

                        $so_rows = ambil_data($conn,
                            'SELECT so.*, u.nama FROM stock_opname so LEFT JOIN users u ON so.user_id = u.id',
                            $so_where, $so_params, $so_tipe, 'ORDER BY so.id DESC', $so_limit, $so_offset);

                        if (!$so_rows) { echo render_kosong(5, 'Belum ada riwayat Stock Opname.'); }

                        foreach($so_rows as $row):
                            $jml_item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM stock_opname_detail WHERE stock_opname_id='{$row['id']}'"))['n'] ?? 0;
                            $badge = 'bg-yellow-100 text-yellow-700';
                            if($row['status'] === 'approved') $badge = 'bg-green-100 text-green-700';
                            if($row['status'] === 'rejected') $badge = 'bg-red-100 text-red-700';
                        ?>
                        <tr class="hover:bg-gray-50 border-b">
                            <td class="p-3 font-bold text-cyan-700"><?= $row['no_opname'] ?><br><span class="text-xs text-gray-400 font-normal"><?= date('d/m/y H:i', strtotime($row['tgl_pengajuan'])) ?></span></td>
                            <td class="p-3"><?= htmlspecialchars($row['nama'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-3 text-center"><?= (int)$jml_item ?></td>
                            <td class="p-3 text-center"><span class="<?= $badge ?> px-2 py-1 rounded text-xs font-bold uppercase"><?= $row['status'] ?></span></td>
                            <td class="p-3 text-center">
                                <button onclick="lihatDetailOpname(<?= $row['id'] ?>, '<?= $row['no_opname'] ?>')" class="text-gray-500"><i class="fa-solid fa-eye"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= render_paginasi($so_hal, $so_total, $so_limit) ?>
        </div>
    </div>
</div>

<div id="modalDetailOpname" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-xl relative">
        <button onclick="document.getElementById('modalDetailOpname').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="text-lg font-bold mb-2">Detail Opname: <span id="labelNoOpname"></span></h3>
        <div id="kontenDetailOpname" class="overflow-y-auto max-h-80"></div>
    </div>
</div>

<script>
function confirmTolak(form) {
    let alasan = prompt('Alasan penolakan (opsional):', '');
    if(alasan === null) return false; // user klik Cancel
    form.querySelector('.input-alasan-tolak').value = alasan;
    return confirm('Yakin tolak Stock Opname ini?');
}

function lihatDetailOpname(id, no) {
    document.getElementById('modalDetailOpname').classList.remove('hidden');
    document.getElementById('labelNoOpname').innerText = no;
    let formData = new FormData();
    formData.append('get_detail_opname', true);
    formData.append('id_opname', id);
    fetch('index.php?page=stock_opname', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(h => { document.getElementById('kontenDetailOpname').innerHTML = h; });
}
</script>

<?php
// PHP AJAX HANDLER UNTUK DETAIL RIWAYAT
if(isset($_POST['get_detail_opname'])) {
    ob_clean();
    $id_opname_det = (int)$_POST['id_opname'];
    $q = mysqli_query($conn, "SELECT sod.*, b.nama_barang, b.satuan FROM stock_opname_detail sod LEFT JOIN barang b ON sod.barang_id = b.id WHERE sod.stock_opname_id='$id_opname_det'");

    echo '<table class="w-full text-xs border"><thead class="bg-gray-100"><tr><th class="p-2 text-left">Barang</th><th class="p-2 text-center">Sistem</th><th class="p-2 text-center">Fisik</th><th class="p-2 text-center">Selisih</th><th class="p-2 text-left">Alasan</th></tr></thead><tbody>';
    while($d = mysqli_fetch_assoc($q)) {
        $tanda = $d['selisih'] > 0 ? '+' : '';
        $warna = $d['selisih'] == 0 ? 'text-gray-500' : ($d['selisih'] > 0 ? 'text-green-600' : 'text-red-600');
        echo "<tr class='border-b'><td class='p-2'>{$d['nama_barang']}</td><td class='p-2 text-center'>" . (float)$d['stok_sistem'] . " {$d['satuan']}</td><td class='p-2 text-center font-bold'>" . (float)$d['stok_fisik'] . " {$d['satuan']}</td><td class='p-2 text-center font-bold $warna'>$tanda" . (float)$d['selisih'] . "</td><td class='p-2'>" . htmlspecialchars($d['alasan'], ENT_QUOTES, 'UTF-8') . "</td></tr>";
    }
    echo '</tbody></table>';
    exit;
}
?>
