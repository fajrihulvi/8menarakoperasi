<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
// PERBAIKAN: Tambah 'invoice'
wajib_akses('coa');

$id_usaha = $_SESSION['id_usaha'];

// TAMBAH AKUN
if(isset($_POST['simpan_akun'])){
    $kode = $_POST['kode']; $nama = $_POST['nama'];
    $kat = $_POST['kategori']; $posisi = $_POST['posisi'];
    mysqli_query($conn, "INSERT INTO akun_perkiraan (id_usaha, kode_akun, nama_akun, kategori, posisi_normal) VALUES ('$id_usaha', '$kode', '$nama', '$kat', '$posisi')");
    echo "<script>window.location='index.php?page=coa';</script>";
}
?>
<div class="bg-white p-6 rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-book-open mr-2"></i> Chart of Accounts (COA)</h2>
        <button onclick="document.getElementById('modalAkun').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded font-bold text-sm"><i class="fa-solid fa-plus"></i> Tambah Akun</button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border">
            <thead class="bg-gray-100">
                <tr><th class="p-3">Kode</th><th class="p-3">Nama Akun</th><th class="p-3">Kategori</th><th class="p-3">Posisi</th></tr>
            </thead>
            <tbody class="divide-y">
                <?php
                $q = mysqli_query($conn, "SELECT * FROM akun_perkiraan WHERE id_usaha='$id_usaha' ORDER BY kode_akun ASC");
                while($row = mysqli_fetch_assoc($q)):
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3 font-mono"><?= $row['kode_akun'] ?></td>
                    <td class="p-3 font-bold"><?= $row['nama_akun'] ?></td>
                    <td class="p-3"><?= $row['kategori'] ?></td>
                    <td class="p-3"><?= $row['posisi_normal'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalAkun" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-900 bg-opacity-50">
    <div class="bg-white p-6 rounded shadow-lg w-96">
        <h3 class="font-bold mb-4">Tambah Akun Baru</h3>
        <form method="POST">
            <div class="mb-2"><label class="block text-xs font-bold">Kode Akun</label><input type="text" name="kode" class="w-full border p-2 rounded" required></div>
            <div class="mb-2"><label class="block text-xs font-bold">Nama Akun</label><input type="text" name="nama" class="w-full border p-2 rounded" required></div>
            <div class="mb-2"><label class="block text-xs font-bold">Kategori</label>
                <select name="kategori" class="w-full border p-2 rounded">
                    <option value="Harta">Harta (Assets)</option><option value="Kewajiban">Kewajiban (Liability)</option>
                    <option value="Modal">Modal (Equity)</option><option value="Pendapatan">Pendapatan (Revenue)</option>
                    <option value="HPP">HPP (COGS)</option><option value="Beban">Beban (Expense)</option>
                </select>
            </div>
            <div class="mb-4"><label class="block text-xs font-bold">Posisi Normal</label>
                <select name="posisi" class="w-full border p-2 rounded"><option value="Debit">Debit</option><option value="Kredit">Kredit</option></select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalAkun').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan_akun" class="bg-indigo-600 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</div>