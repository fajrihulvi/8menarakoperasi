<?php
// pages/pelanggan.php
wajib_akses('pelanggan');

if(isset($_POST['simpan'])) {
    tolak_jika_tidak_boleh('tambah', 'pelanggan', 'index.php?page=pelanggan');

    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama'] ?? ''));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));

    if ($nama !== '') {
        mysqli_query($conn, "INSERT INTO pelanggan (nama_pelanggan, alamat) VALUES ('$nama', '$alamat')");
        catat_log($conn, "Tambah Pelanggan", "Menambah pelanggan: $nama");
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
    </div>

    <?php if(boleh('tambah','pelanggan')): ?>
    <form method="POST" class="mb-6 flex flex-col md:flex-row gap-2">
        <input type="text" name="nama" placeholder="Nama Pelanggan" class="border p-2 rounded focus:outline-indigo-500" required>
        <input type="text" name="alamat" placeholder="Alamat" class="border p-2 rounded flex-1 focus:outline-indigo-500" required>
        <button type="submit" name="simpan" class="bg-indigo-600 text-white px-4 py-2 rounded font-bold hover:bg-indigo-700">
            <i class="fa-solid fa-plus mr-1"></i> Simpan
        </button>
    </form>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-bold">
                <tr>
                    <th class="p-3 border text-left">Nama</th>
                    <th class="p-3 border text-left">Alamat</th>
                    <?php if(boleh('hapus','pelanggan')): ?><th class="p-3 border text-center w-20">Aksi</th><?php endif; ?>
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
                    <?php if(boleh('hapus','pelanggan')): ?>
                    <td class="p-3 border text-center">
                        <a href="index.php?page=pelanggan&hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus pelanggan ini?')" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></a>
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
