<?php
require_once __DIR__ . '/../layout/tabel_helper.php';

wajib_akses('kategori');

$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1;

// Handle Simpan Data (Tambah / Edit)
if(isset($_POST['simpan'])) {
    $id_edit = (int) ($_POST['id_edit'] ?? 0);
    tolak_jika_tidak_boleh($id_edit > 0 ? 'edit' : 'tambah', 'kategori', 'index.php?page=kategori');

    $nama = trim(htmlspecialchars($_POST['nama_kategori']));

    if ($nama !== '') {
        if ($id_edit > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE kategori SET nama_kategori=? WHERE id=? AND id_usaha=?");
            mysqli_stmt_bind_param($stmt, 'sii', $nama, $id_edit, $id_usaha_aktif);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO kategori (id_usaha, nama_kategori) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'is', $id_usaha_aktif, $nama);
        }
        if (!mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Gagal menyimpan: kategori dengan nama ini mungkin sudah ada.'); window.history.back();</script>";
            exit();
        }
    }
    echo "<script>window.location='index.php?page=kategori';</script>";
}

// Handle Hapus (Tetap Hanya Admin)
if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'kategori', 'index.php?page=kategori');
    {
        $id = (int) $_GET['hapus'];
        $stmt = mysqli_prepare($conn, "DELETE FROM kategori WHERE id=? AND id_usaha=?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $id_usaha_aktif);
        mysqli_stmt_execute($stmt);
        echo "<script>window.location='index.php?page=kategori';</script>";
    }
}
?>

<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-800">Data Kategori Barang</h3>

        <?php if(boleh('tambah','kategori')): ?>
        <button onclick="bukaModalTambah()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Kategori
        </button>
        <?php endif; ?>

    </div>

    <?= render_filter('Cari nama kategori...') ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-100 uppercase font-semibold text-gray-700">
                <tr>
                    <th class="px-4 py-3">Nama Kategori</th>
                    <th class="px-4 py-3 text-center">Jumlah Barang</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                // Paginasi & filter sisi server
                $k_cari  = ambil_kata_kunci();
                $k_hal   = ambil_halaman();
                $k_limit = ambil_per_halaman();

                [$k_where, $k_params, $k_tipe] = bangun_filter(
                    $k_cari, ['nama_kategori'], ['id_usaha = ?'], [$id_usaha_aktif], 'i'
                );

                $k_total  = hitung_total($conn, 'kategori', $k_where, $k_params, $k_tipe);
                $k_hal    = batasi_halaman($k_hal, $k_total, $k_limit);
                $k_offset = ($k_hal - 1) * $k_limit;

                $k_rows = ambil_data($conn, 'SELECT * FROM kategori', $k_where, $k_params, $k_tipe,
                                     'ORDER BY nama_kategori ASC', $k_limit, $k_offset);

                if (!$k_rows) { echo render_kosong(3, 'Tidak ada kategori yang cocok.'); }

                foreach ($k_rows as $row):
                    $jml = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM barang WHERE kategori_id = " . (int)$row['id']))['n'] ?? 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $row['nama_kategori'] ?></td>
                    <td class="px-4 py-3 text-center"><?= (int)$jml ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if(boleh('edit','kategori')): ?>
                            <button onclick='bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)' class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <?php endif; ?>
                        <?php if(boleh('hapus','kategori')): ?>
                            <a href="index.php?page=kategori&hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus kategori ini? Barang yang memakai kategori ini akan menjadi tanpa kategori.')" class="text-red-500 hover:text-red-700" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($k_hal, $k_total, $k_limit) ?>
</div>

<div id="modalKategori" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-96 p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4" id="judulModalKategori">Tambah Kategori Baru</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit_kategori">
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">Nama Kategori</label>
                <input type="text" name="nama_kategori" id="input_nama_kategori" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleModal('modalKategori')" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }

    function bukaModalTambah() {
        document.getElementById('judulModalKategori').innerText = 'Tambah Kategori Baru';
        document.getElementById('id_edit_kategori').value = '';
        document.getElementById('input_nama_kategori').value = '';
        document.getElementById('modalKategori').classList.remove('hidden');
    }

    function bukaModalEdit(d) {
        document.getElementById('judulModalKategori').innerText = 'Edit Kategori';
        document.getElementById('id_edit_kategori').value = d.id;
        document.getElementById('input_nama_kategori').value = d.nama_kategori;
        document.getElementById('modalKategori').classList.remove('hidden');
    }
</script>
