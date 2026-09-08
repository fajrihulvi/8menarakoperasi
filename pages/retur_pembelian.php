<?php
wajib_akses('retur_pembelian');

// pages/retur_pembelian.php

// 1. DEFINISI FOLDER GAMBAR (PENTING!)
// Pastikan folder ini ada: "assets/uploads/retur/"
// Jika folder anda berbeda, ubah path di bawah ini.
$folder_upload = "assets/uploads/retur/"; 

?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Data Retur Pembelian</h2>
        <p class="text-sm text-gray-500">Kelola pengembalian barang ke supplier</p>
    </div>
    <a href="index.php?page=tambah_retur_pembelian" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition shadow-sm font-bold text-sm">
        <i class="fa-solid fa-plus mr-2"></i> Buat Retur Baru
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-xs border-b">
                <tr>
                    <th class="p-4 text-center">No</th>
                    <th class="p-4">Tanggal</th>
                    <th class="p-4">No. Retur</th>
                    <th class="p-4">Supplier</th>
                    <th class="p-4">Alasan</th>
                    <th class="p-4 text-center">Bukti</th>
                    <th class="p-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $no = 1;
                // Query Data Retur
                $q = mysqli_query($conn, "SELECT r.*, s.nama_supplier 
                                          FROM retur_pembelian r 
                                          LEFT JOIN supplier s ON r.supplier_id = s.id 
                                          ORDER BY r.tanggal DESC");
                
                while($d = mysqli_fetch_assoc($q)):
                    // Cek File Bukti
                    $file_bukti = $d['bukti_foto']; // Pastikan nama kolom di DB 'bukti_foto' atau sesuaikan
                    $path_bukti = $folder_upload . $file_bukti;
                    $ada_bukti = (!empty($file_bukti) && file_exists($path_bukti));
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 text-center font-bold text-gray-500"><?= $no++ ?></td>
                    <td class="p-4"><?= date('d/m/Y', strtotime($d['tanggal'])) ?></td>
                    <td class="p-4 font-bold text-indigo-600"><?= $d['no_retur'] ?></td>
                    <td class="p-4"><?= $d['nama_supplier'] ?? 'Umum' ?></td>
                    <td class="p-4 text-gray-600 italic max-w-xs truncate"><?= $d['alasan'] ?></td>
                    
                    <td class="p-4 text-center">
                        <?php if($ada_bukti): ?>
                            <button onclick="lihatBukti('<?= $path_bukti ?>', '<?= $d['no_retur'] ?>')" 
                                    class="bg-blue-50 text-blue-600 px-3 py-1 rounded text-xs font-bold border border-blue-200 hover:bg-blue-100 transition">
                                <i class="fa-regular fa-image mr-1"></i> Lihat
                            </button>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs italic">Tanpa Bukti</span>
                        <?php endif; ?>
                    </td>

                    <td class="p-4 text-center">
                        <div class="flex justify-center gap-2">
                            <a href="cetak_retur_pembelian.php?id=<?= $d['id'] ?>" target="_blank" 
                               class="w-8 h-8 rounded bg-gray-100 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition" title="Cetak Nota">
                                <i class="fa-solid fa-print"></i>
                            </a>

                            <a href="index.php?page=retur_pembelian&hapus=<?= $d['id'] ?>" 
                               onclick="return confirm('Yakin hapus data retur ini? Stok tidak akan kembali otomatis.')"
                               class="w-8 h-8 rounded bg-red-50 text-red-600 flex items-center justify-center hover:bg-red-100 transition" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                
                <?php if(mysqli_num_rows($q) == 0): ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-gray-400">Belum ada data retur pembelian.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalBukti" class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="relative max-w-4xl w-full max-h-screen flex flex-col items-center">
        <button onclick="tutupBukti()" class="absolute -top-10 right-0 text-white hover:text-red-400 transition text-2xl">
            <i class="fa-solid fa-xmark"></i> Tutup
        </button>
        
        <div class="text-white mb-2 font-bold" id="judulBukti">Bukti Retur</div>

        <img id="imgBukti" src="" alt="Bukti Retur" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl border-4 border-white/20">
        
        <a id="linkDownload" href="" download class="mt-4 bg-indigo-600 text-white px-6 py-2 rounded-full font-bold hover:bg-indigo-700 transition shadow-lg flex items-center gap-2">
            <i class="fa-solid fa-download"></i> Download Gambar
        </a>
    </div>
</div>

<script>
    function lihatBukti(url, noretur) {
        const modal = document.getElementById('modalBukti');
        const img = document.getElementById('imgBukti');
        const judul = document.getElementById('judulBukti');
        const link = document.getElementById('linkDownload');

        // Set Data
        img.src = url;
        judul.innerText = "Bukti Retur: " + noretur;
        link.href = url;

        // Tampilkan Modal
        modal.classList.remove('hidden');
    }

    function tutupBukti() {
        document.getElementById('modalBukti').classList.add('hidden');
    }

    // Tutup jika klik di luar gambar
    document.getElementById('modalBukti').addEventListener('click', function(e) {
        if (e.target === this) tutupBukti();
    });
</script>

<?php
// LOGIKA HAPUS DATA (PHP)
if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'retur_pembelian', 'index.php?page=retur_pembelian');
    $id = $_GET['hapus'];
    
    // Hapus File Gambarnya dulu biar server bersih
    $cek = mysqli_query($conn, "SELECT bukti_foto FROM retur_pembelian WHERE id='$id'");
    $data_img = mysqli_fetch_assoc($cek);
    if(!empty($data_img['bukti_foto']) && file_exists($folder_upload . $data_img['bukti_foto'])) {
        unlink($folder_upload . $data_img['bukti_foto']);
    }

    // Hapus Detail & Header
    mysqli_query($conn, "DELETE FROM retur_pembelian_detail WHERE retur_id='$id'");
    mysqli_query($conn, "DELETE FROM retur_pembelian WHERE id='$id'");
    
    echo "<script>alert('Data Retur berhasil dihapus!'); window.location='index.php?page=retur_pembelian';</script>";
}
?>