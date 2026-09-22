<?php
require_once __DIR__ . '/../layout/tabel_helper.php';

wajib_akses('supplier');

// Handle Simpan Data
// KITA TAMBAHKAN 'accounting' DISINI
if(isset($_POST['simpan'])) {
    $id_edit = (int) ($_POST['id_edit'] ?? 0);
    tolak_jika_tidak_boleh($id_edit > 0 ? 'edit' : 'tambah', 'supplier', 'index.php?page=supplier');

    $nama = htmlspecialchars($_POST['nama']);
    $alamat = htmlspecialchars($_POST['alamat']);
    $telp = htmlspecialchars($_POST['telp']);
    $bank = htmlspecialchars($_POST['nama_bank']);
    $rekening = htmlspecialchars($_POST['no_rekening']);
    $akun = htmlspecialchars($_POST['nama_akun_rekening']);

    if ($id_edit > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE supplier SET nama_supplier=?, alamat=?, no_telp=?, nama_bank=?, no_rekening=?, nama_akun_rekening=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssssssi', $nama, $alamat, $telp, $bank, $rekening, $akun, $id_edit);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO supplier (nama_supplier, alamat, no_telp, nama_bank, no_rekening, nama_akun_rekening) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssss', $nama, $alamat, $telp, $bank, $rekening, $akun);
    }
    $simpan = mysqli_stmt_execute($stmt);
    if($simpan) echo "<script>window.location='index.php?page=supplier';</script>";
}

// Handle Hapus (Tetap Hanya Admin)
if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'supplier', 'index.php?page=supplier');
    {
        $id = (int) $_GET['hapus'];
        $stmt = mysqli_prepare($conn, "DELETE FROM supplier WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        echo "<script>window.location='index.php?page=supplier';</script>";
    }
}
?>

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Data Supplier</h3>
        
        <?php if(boleh('tambah','supplier')): ?>
        <button onclick="bukaModalTambah()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Supplier
        </button>
        <?php endif; ?>
        
    </div>

    <?= render_filter('Cari nama supplier / telepon / alamat...') ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-100 uppercase font-semibold text-gray-700">
                <tr>
                    <th class="px-4 py-3">Nama Supplier</th>
                    <th class="px-4 py-3">No. Telepon</th>
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
                $s_cari  = ambil_kata_kunci();
                $s_hal   = ambil_halaman();
                $s_limit = ambil_per_halaman();

                [$s_where, $s_params, $s_tipe] = bangun_filter(
                    $s_cari, ['nama_supplier', 'no_telp', 'alamat']
                );

                $s_total  = hitung_total($conn, 'supplier', $s_where, $s_params, $s_tipe);
                $s_hal    = batasi_halaman($s_hal, $s_total, $s_limit);
                $s_offset = ($s_hal - 1) * $s_limit;

                $s_rows = ambil_data($conn, 'SELECT * FROM supplier', $s_where, $s_params, $s_tipe,
                                     'ORDER BY id DESC', $s_limit, $s_offset);

                if (!$s_rows) { echo render_kosong(7, 'Tidak ada supplier yang cocok.'); }

                foreach ($s_rows as $row):
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $row['nama_supplier'] ?></td>
                    <td class="px-4 py-3"><?= $row['no_telp'] ?></td>
                    <td class="px-4 py-3"><?= $row['alamat'] ?></td>
                    <td class="px-4 py-3"><?= $row['nama_bank'] ?></td>
                    <td class="px-4 py-3"><?= $row['no_rekening'] ?></td>
                    <td class="px-4 py-3"><?= $row['nama_akun_rekening'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if(boleh('edit','supplier')): ?>
                            <button onclick='bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <?php endif; ?>
                        <?php if(boleh('hapus','supplier')): ?>
                            <a href="index.php?page=supplier&hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus supplier ini?')" class="text-red-500 hover:text-red-700" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($s_hal, $s_total, $s_limit) ?>
</div>

<div id="modalSupplier" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-96 p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4" id="judulModalSupplier">Tambah Supplier Baru</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit_supplier">
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nama Supplier</label>
                <input type="text" name="nama" id="sup_nama" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">No. Telepon</label>
                <input type="text" name="telp" id="sup_telp" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Alamat</label>
                <textarea name="alamat" id="sup_alamat" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nama Bank</label>
                <input type="text" name="nama_bank" id="sup_bank" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nomor Rekening</label>
                <input type="text" name="no_rekening" id="sup_rekening" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">Nama Akun pada Rekening</label>
                <input type="text" name="nama_akun_rekening" id="sup_akun" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModal('modalSupplier')" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }

    function bukaModalTambah() {
        document.getElementById('judulModalSupplier').innerText = 'Tambah Supplier Baru';
        document.getElementById('id_edit_supplier').value = '';
        document.getElementById('sup_nama').value = '';
        document.getElementById('sup_telp').value = '';
        document.getElementById('sup_alamat').value = '';
        document.getElementById('sup_bank').value = '';
        document.getElementById('sup_rekening').value = '';
        document.getElementById('sup_akun').value = '';
        document.getElementById('modalSupplier').classList.remove('hidden');
    }

    function bukaModalEdit(d) {
        document.getElementById('judulModalSupplier').innerText = 'Edit Supplier';
        document.getElementById('id_edit_supplier').value = d.id;
        document.getElementById('sup_nama').value = d.nama_supplier || '';
        document.getElementById('sup_telp').value = d.no_telp || '';
        document.getElementById('sup_alamat').value = d.alamat || '';
        document.getElementById('sup_bank').value = d.nama_bank || '';
        document.getElementById('sup_rekening').value = d.no_rekening || '';
        document.getElementById('sup_akun').value = d.nama_akun_rekening || '';
        document.getElementById('modalSupplier').classList.remove('hidden');
    }
</script>