<?php
require_once __DIR__ . '/../layout/tabel_helper.php';

wajib_akses('warehouse');

// Handle Simpan Data
if(isset($_POST['simpan'])) {
    $id_edit = (int) ($_POST['id_edit'] ?? 0);
    tolak_jika_tidak_boleh($id_edit > 0 ? 'edit' : 'tambah', 'warehouse', 'index.php?page=warehouse');

    $nama = htmlspecialchars($_POST['nama']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $email = htmlspecialchars($_POST['email']);
    $bank = htmlspecialchars($_POST['nama_bank']);
    $rekening = htmlspecialchars($_POST['no_rekening']);
    $akun = htmlspecialchars($_POST['nama_akun_rekening']);

    if ($id_edit > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE warehouse SET nama_warehouse=?, alamat=?, email=?, nama_bank=?, no_rekening=?, nama_akun_rekening=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssssssi', $nama, $alamat, $email, $bank, $rekening, $akun, $id_edit);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO warehouse (nama_warehouse, alamat, email, nama_bank, no_rekening, nama_akun_rekening) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssss', $nama, $alamat, $email, $bank, $rekening, $akun);
    }
    $simpan = mysqli_stmt_execute($stmt);
    if($simpan) echo "<script>window.location='index.php?page=warehouse';</script>";
}

// Handle Hapus (Tetap Hanya Admin)
if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'warehouse', 'index.php?page=warehouse');
    {
        $id = (int) $_GET['hapus'];
        $stmt = mysqli_prepare($conn, "DELETE FROM warehouse WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        echo "<script>window.location='index.php?page=warehouse';</script>";
    }
}
?>

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Data Warehouse</h3>

        <?php if(boleh('tambah','warehouse')): ?>
        <button onclick="bukaModalTambah()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Warehouse
        </button>
        <?php endif; ?>

    </div>

    <?= render_filter('Cari nama warehouse / email / alamat...') ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-100 uppercase font-semibold text-gray-700">
                <tr>
                    <th class="px-4 py-3">Nama Warehouse</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Alamat</th>
                    <th class="px-4 py-3">Bank</th>
                    <th class="px-4 py-3">No. Rekening</th>
                    <th class="px-4 py-3">Nama Akun Rekening</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                // Paginasi & filter sisi server
                $w_cari  = ambil_kata_kunci();
                $w_hal   = ambil_halaman();
                $w_limit = ambil_per_halaman();

                [$w_where, $w_params, $w_tipe] = bangun_filter(
                    $w_cari, ['nama_warehouse', 'email', 'alamat']
                );

                $w_total  = hitung_total($conn, 'warehouse', $w_where, $w_params, $w_tipe);
                $w_hal    = batasi_halaman($w_hal, $w_total, $w_limit);
                $w_offset = ($w_hal - 1) * $w_limit;

                $w_rows = ambil_data($conn, 'SELECT * FROM warehouse', $w_where, $w_params, $w_tipe,
                                     'ORDER BY id DESC', $w_limit, $w_offset);

                if (!$w_rows) { echo render_kosong(7, 'Tidak ada warehouse yang cocok.'); }

                foreach ($w_rows as $row):
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $row['nama_warehouse'] ?></td>
                    <td class="px-4 py-3"><?= $row['email'] ?></td>
                    <td class="px-4 py-3"><?= $row['alamat'] ?></td>
                    <td class="px-4 py-3"><?= $row['nama_bank'] ?></td>
                    <td class="px-4 py-3"><?= $row['no_rekening'] ?></td>
                    <td class="px-4 py-3"><?= $row['nama_akun_rekening'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if(boleh('edit','warehouse')): ?>
                            <button onclick='bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <?php endif; ?>
                        <?php if(boleh('hapus','warehouse')): ?>
                            <a href="index.php?page=warehouse&hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus warehouse ini?')" class="text-red-500 hover:text-red-700" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($w_hal, $w_total, $w_limit) ?>
</div>

<div id="modalWarehouse" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-96 p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4" id="judulModalWarehouse">Tambah Warehouse Baru</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit_warehouse">
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nama Warehouse</label>
                <input type="text" name="nama" id="wh_nama" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Email</label>
                <input type="email" name="email" id="wh_email" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Alamat</label>
                <textarea name="alamat" id="wh_alamat" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nama Bank</label>
                <input type="text" name="nama_bank" id="wh_bank" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nomor Rekening</label>
                <input type="text" name="no_rekening" id="wh_rekening" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">Nama Akun pada Rekening</label>
                <input type="text" name="nama_akun_rekening" id="wh_akun" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModal('modalWarehouse')" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }

    function bukaModalTambah() {
        document.getElementById('judulModalWarehouse').innerText = 'Tambah Warehouse Baru';
        document.getElementById('id_edit_warehouse').value = '';
        document.getElementById('wh_nama').value = '';
        document.getElementById('wh_email').value = '';
        document.getElementById('wh_alamat').value = '';
        document.getElementById('wh_bank').value = '';
        document.getElementById('wh_rekening').value = '';
        document.getElementById('wh_akun').value = '';
        document.getElementById('modalWarehouse').classList.remove('hidden');
    }

    function bukaModalEdit(d) {
        document.getElementById('judulModalWarehouse').innerText = 'Edit Warehouse';
        document.getElementById('id_edit_warehouse').value = d.id;
        document.getElementById('wh_nama').value = d.nama_warehouse || '';
        document.getElementById('wh_email').value = d.email || '';
        document.getElementById('wh_alamat').value = d.alamat || '';
        document.getElementById('wh_bank').value = d.nama_bank || '';
        document.getElementById('wh_rekening').value = d.no_rekening || '';
        document.getElementById('wh_akun').value = d.nama_akun_rekening || '';
        document.getElementById('modalWarehouse').classList.remove('hidden');
    }
</script>
