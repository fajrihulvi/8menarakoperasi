<?php wajib_akses('laporan'); ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-indigo-500">
        <h3 class="font-bold text-gray-700 mb-2">Laporan Stok Barang</h3>
        <p class="text-sm text-gray-500 mb-4">Lihat posisi stok akhir semua barang di gudang.</p>
        <button onclick="printLaporan('stok')" class="text-indigo-600 font-bold text-sm hover:underline">
            <i class="fa-solid fa-print mr-1"></i> Cetak Laporan
        </button>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-green-500">
        <h3 class="font-bold text-gray-700 mb-2">Laporan Penjualan</h3>
        <p class="text-sm text-gray-500 mb-4">Rekap omzet penjualan per periode tanggal. Tabel akan tampil dulu sebelum dicetak.</p>
        <form method="GET" action="laporan_cetak.php" target="_blank" class="flex gap-2">
            <input type="hidden" name="jenis" value="penjualan">
            <input type="date" name="tgl_awal" class="border rounded p-1 text-xs w-full" required>
            <input type="date" name="tgl_akhir" class="border rounded p-1 text-xs w-full" required>
            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Go</button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-yellow-500">
        <h3 class="font-bold text-gray-700 mb-2">Laporan Pembelian</h3>
        <p class="text-sm text-gray-500 mb-4">Rekap pengeluaran belanja ke supplier.</p>
        <button onclick="alert('Fitur Segera Hadir')" class="text-yellow-600 font-bold text-sm hover:underline">
            <i class="fa-solid fa-file-pdf mr-1"></i> Download PDF
        </button>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm p-6">
    <h3 class="font-bold text-gray-800 mb-4">Preview Posisi Stok Saat Ini</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 uppercase">
                <tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Stok</th><th>Nilai Aset</th></tr>
            </thead>
            <tbody class="divide-y">
                <?php
                $q = mysqli_query($conn, "SELECT * FROM barang ORDER BY stok ASC LIMIT 10");
                while($r = mysqli_fetch_assoc($q)):
                    $aset = $r['stok'] * $r['harga_beli'];
                ?>
                <tr>
                    <td class="p-3"><?= $r['kode_barang'] ?></td>
                    <td class="p-3"><?= $r['nama_barang'] ?></td>
                    <td class="p-3"><?= $r['kategori'] ?></td>
                    <td class="p-3 font-bold <?= $r['stok']<5?'text-red-500':'text-green-600' ?>"><?= $r['stok'] ?></td>
                    <td class="p-3"><?= format_rupiah($aset) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function printLaporan(jenis) {
    // Untuk demo sederhana, kita buka print window tabel yang ada
    if(jenis == 'stok') {
        window.print();
    }
}
</script>