<?php
// pages/pelanggan.php
wajib_akses('pelanggan');

if(isset($_POST['simpan'])) {
    $id_edit = (int) ($_POST['id_edit'] ?? 0);
    tolak_jika_tidak_boleh($id_edit > 0 ? 'edit' : 'tambah', 'pelanggan', 'index.php?page=pelanggan');

    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama'] ?? ''));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));

    if ($nama !== '') {
        if ($id_edit > 0) {
            mysqli_query($conn, "UPDATE pelanggan SET nama_pelanggan='$nama', alamat='$alamat' WHERE id='$id_edit'");
            catat_log($conn, "Edit Pelanggan", "Mengubah data pelanggan: $nama");
        } else {
            mysqli_query($conn, "INSERT INTO pelanggan (nama_pelanggan, alamat) VALUES ('$nama', '$alamat')");
            catat_log($conn, "Tambah Pelanggan", "Menambah pelanggan: $nama");
        }
    }
    echo "<script>window.location='index.php?page=pelanggan';</script>";
    exit;
}

if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'pelanggan', 'index.php?page=pelanggan');

    $id = (int) $_GET['hapus'];
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_pelanggan FROM pelanggan WHERE id='$id'"));
    if ($cek) {
        mysqli_query($conn, "DELETE FROM pelanggan WHERE id='$id'");
        catat_log($conn, "Hapus Pelanggan", "Menghapus pelanggan: " . $cek['nama_pelanggan']);
    }
    echo "<script>window.location='index.php?page=pelanggan';</script>";
    exit;
}
?>
<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-4 border-b pb-3">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Data Pelanggan</h3>
            <p class="text-sm text-gray-500">Daftar pelanggan / dapur yang dilayani.</p>
        </div>

        <?php if(boleh('tambah','pelanggan')): ?>
        <button onclick="bukaModalTambah()" class="bg-indigo-600 text-white px-4 py-2 rounded font-bold hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-1"></i> Tambah Pelanggan
        </button>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-bold">
                <tr>
                    <th class="p-3 border text-left">Nama</th>
                    <th class="p-3 border text-left">Alamat</th>
                    <?php if(boleh('edit','pelanggan') || boleh('hapus','pelanggan')): ?><th class="p-3 border text-center w-24">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php
                $q = mysqli_query($conn, "SELECT * FROM pelanggan ORDER BY nama_pelanggan ASC");
                $ada = false;
                while($r = mysqli_fetch_assoc($q)): $ada = true;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3 border font-semibold text-gray-700"><?= htmlspecialchars($r['nama_pelanggan']) ?></td>
                    <td class="p-3 border text-gray-500"><?= htmlspecialchars($r['alamat'] ?? '') ?></td>
                    <?php if(boleh('edit','pelanggan') || boleh('hapus','pelanggan')): ?>
                    <td class="p-3 border text-center">
                        <?php if(boleh('edit','pelanggan')): ?>
                            <button onclick='bukaModalEdit(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)' class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <?php endif; ?>
                        <?php if(boleh('hapus','pelanggan')): ?>
                            <a href="index.php?page=pelanggan&hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus pelanggan ini?')" class="text-red-500 hover:text-red-700" title="Hapus"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
                <?php if(!$ada): ?>
                <tr><td colspan="3" class="p-6 text-center text-gray-400 italic">Belum ada data pelanggan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalPelanggan" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-96 p-6 shadow-xl">
        <h3 class="text-lg font-bold mb-4" id="judulModalPelanggan">Tambah Pelanggan Baru</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit_pelanggan">
            <div class="mb-3">
                <label class="block text-sm font-bold mb-1">Nama Pelanggan</label>
                <input type="text" name="nama" id="pel_nama" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-1">Alamat</label>
                <input type="text" name="alamat" id="pel_alamat" class="w-full border rounded p-2 focus:ring focus:ring-indigo-200" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalPelanggan').classList.add('hidden')" class="bg-gray-200 text-gray-800 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaModalTambah() {
        document.getElementById('judulModalPelanggan').innerText = 'Tambah Pelanggan Baru';
        document.getElementById('id_edit_pelanggan').value = '';
        document.getElementById('pel_nama').value = '';
        document.getElementById('pel_alamat').value = '';
        document.getElementById('modalPelanggan').classList.remove('hidden');
    }

    function bukaModalEdit(d) {
        document.getElementById('judulModalPelanggan').innerText = 'Edit Pelanggan';
        document.getElementById('id_edit_pelanggan').value = d.id;
        document.getElementById('pel_nama').value = d.nama_pelanggan || '';
        document.getElementById('pel_alamat').value = d.alamat || '';
        document.getElementById('modalPelanggan').classList.remove('hidden');
    }
</script>
