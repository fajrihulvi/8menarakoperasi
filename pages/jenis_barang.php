<?php
require_once __DIR__ . '/../layout/tabel_helper.php';

wajib_akses('jenis_barang');

// Handle Simpan Data (Tambah / Edit)
if(isset($_POST['simpan'])) {
    $id_edit = (int) ($_POST['id_edit'] ?? 0);
    tolak_jika_tidak_boleh($id_edit > 0 ? 'edit' : 'tambah', 'jenis_barang', 'index.php?page=jenis_barang');

    $nama = trim(htmlspecialchars($_POST['jenis_barang']));

    if ($nama !== '') {
        if ($id_edit > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE jenis_barang SET jenis_barang=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'si', $nama, $id_edit);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO jenis_barang (jenis_barang) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 's', $nama);
        }
        if (!mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Gagal menyimpan: kategori konsumen dengan nama ini mungkin sudah ada.'); window.history.back();</script>";
            exit();
        }
    }
    echo "<script>window.location='index.php?page=jenis_barang';</script>";
}

// Handle Hapus (Tetap Hanya Admin)
if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'jenis_barang', 'index.php?page=jenis_barang');
    {
        $id = (int) $_GET['hapus'];
        $stmt = mysqli_prepare($conn, "DELETE FROM jenis_barang WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        echo "<script>window.location='index.php?page=jenis_barang';</script>";
    }
}
?>

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Data Kategori Konsumen</h3>

        <?php if(boleh('tambah','jenis_barang')): ?>
        <button onclick="bukaModalTambah()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Kategori Konsumen
        </button>
        <?php endif; ?>

    </div>

    <?= render_filter('Cari kategori konsumen...') ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-100 uppercase font-semibold text-gray-700">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Kategori Konsumen</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                // Paginasi & filter sisi server
                $jb_cari  = ambil_kata_kunci();
                $jb_hal   = ambil_halaman();
                $jb_limit = ambil_per_halaman();

                [$jb_where, $jb_params, $jb_tipe] = bangun_filter(
                    $jb_cari, ['jenis_barang']
                );

                $jb_total  = hitung_total($conn, 'jenis_barang', $jb_where, $jb_params, $jb_tipe);
                $jb_hal    = batasi_halaman($jb_hal, $jb_total, $jb_limit);
                $jb_offset = ($jb_hal - 1) * $jb_limit;

                $jb_rows = ambil_data($conn, 'SELECT * FROM jenis_barang', $jb_where, $jb_params, $jb_tipe,
                                     'ORDER BY id ASC', $jb_limit, $jb_offset);

                if (!$jb_rows) { echo render_kosong(3, 'Tidak ada kategori konsumen yang cocok.'); }

                foreach ($jb_rows as $row):
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><?= $row['id'] ?></td>
                    <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($row['jenis_barang'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if(boleh('edit','jenis_barang')): ?>
                            <button onclick='bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <?php endif; ?>
                        <?php if(boleh('hapus','jenis_barang')): ?>
                            <a href="index.php?page=jenis_barang&hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus kategori konsumen ini?')" class="text-red-500 hover:text-red-700" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($jb_hal, $jb_total, $jb_limit) ?>
</div>

<div id="modalJenisBarang" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-96 p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4" id="judulModalJenisBarang">Tambah Kategori Konsumen Baru</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit_jenis">
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">Kategori Konsumen</label>
                <input type="text" name="jenis_barang" id="input_jenis_barang" placeholder="Cth: Horeka (Hotel, Resto, Kafe)" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModal('modalJenisBarang')" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }

    function bukaModalTambah() {
        document.getElementById('judulModalJenisBarang').innerText = 'Tambah Kategori Konsumen Baru';
        document.getElementById('id_edit_jenis').value = '';
        document.getElementById('input_jenis_barang').value = '';
        document.getElementById('modalJenisBarang').classList.remove('hidden');
    }

    function bukaModalEdit(d) {
        document.getElementById('judulModalJenisBarang').innerText = 'Edit Kategori Konsumen';
        document.getElementById('id_edit_jenis').value = d.id;
        document.getElementById('input_jenis_barang').value = d.jenis_barang;
        document.getElementById('modalJenisBarang').classList.remove('hidden');
    }
</script>
