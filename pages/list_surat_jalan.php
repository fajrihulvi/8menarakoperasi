<?php
// pages/list_surat_jalan.php
wajib_akses('list_surat_jalan');
?>
<div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Cetak Surat Jalan & Invoice</h3>
            <p class="text-sm text-gray-500">Kelola dokumen pengiriman barang</p>
        </div>
        
        <div class="mt-4 md:mt-0 w-full md:w-auto">
            <input type="text" id="cariSurat" onkeyup="cariTable()" placeholder="Cari No Surat / Driver..." class="border p-2 rounded text-sm w-full md:w-64 focus:outline-indigo-500">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left" id="tabelSurat">
            <thead class="bg-orange-50 text-orange-800 uppercase font-bold text-xs">
                <tr>
                    <th class="p-3">No Surat Jalan</th>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3">Tujuan (Pelanggan)</th>
                    <th class="p-3">Info Pengiriman</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                // Pastikan session dimulai dan ambil ID Usaha
                if (session_status() == PHP_SESSION_NONE) { session_start(); }
                $id_usaha = $_SESSION['id_usaha']; 

                // Query tetap sama seperti kode Anda
                $query = mysqli_query($conn, "
                    SELECT t.*, p.nama_pelanggan, p.alamat as alamat_pelanggan 
                    FROM transaksi t 
                    LEFT JOIN pelanggan p ON t.pelanggan_id = p.id 
                    WHERE t.jenis_transaksi = 'keluar' 
                    AND t.id_usaha = '$id_usaha'
                    ORDER BY t.tanggal DESC
                ");
                
                while($row = mysqli_fetch_assoc($query)):
                    // Ubah Nomor Faktur jadi No Surat Jalan
                    $no_sj = str_replace("TRX", "SJ", $row['no_faktur']);
                    
                    // Cek Driver
                    $driver = !empty($row['nama_driver']) ? $row['nama_driver'] : null;
                    $nopol  = !empty($row['nopol']) ? $row['nopol'] : null;
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3">
                        <div class="font-bold text-gray-800"><?= $no_sj ?></div>
                        <div class="text-[10px] text-gray-400">Ref: <?= $row['no_faktur'] ?></div>
                    </td>
                    <td class="p-3">
                        <div class="font-bold text-gray-700"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($row['tanggal'])) ?> WIB</div>
                    </td>
                    <td class="p-3">
                        <div class="font-bold text-gray-800"><?= $row['nama_pelanggan'] ?: 'Pelanggan Umum' ?></div>
                        <div class="text-xs text-gray-500 truncate w-48" title="<?= $row['alamat_pelanggan'] ?>">
                            <i class="fa-solid fa-location-dot mr-1"></i> <?= $row['alamat_pelanggan'] ?: '-' ?>
                        </div>
                    </td>
                    <td class="p-3">
                        <?php if($driver): ?>
                            <div class="flex items-center gap-2">
                                <div class="bg-blue-100 text-blue-600 p-2 rounded-full h-8 w-8 flex items-center justify-center">
                                    <i class="fa-solid fa-truck"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-700 text-xs"><?= strtoupper($driver) ?></div>
                                    <div class="text-[10px] bg-gray-200 px-1 rounded inline-block text-gray-600 mt-0.5">
                                        <?= strtoupper($nopol) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-red-500 bg-red-50 px-2 py-1 rounded border border-red-100 flex items-center gap-1 w-fit">
                                <i class="fa-solid fa-triangle-exclamation"></i> Belum ada driver
                            </span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="p-3 text-center">
                        <div class="flex justify-center items-center gap-2">
                            <?php if(boleh('edit','invoice')): ?>
                            <a href="index.php?page=edit_invoice&no_faktur=<?= $row['no_faktur'] ?>" 
                               class="bg-blue-600 text-white px-3 py-2 rounded text-xs font-bold hover:bg-blue-700 shadow-sm flex items-center gap-1 transition"
                               title="Edit Harga/Qty Invoice">
                                <i class="fa-solid fa-pen-to-square"></i> EDIT
                            </a>
                            <?php endif; ?>

                            <a href="cetak_surat_jalan.php?no_faktur=<?= $row['no_faktur'] ?>" target="_blank" 
                               class="bg-orange-500 text-white px-3 py-2 rounded text-xs font-bold hover:bg-orange-600 shadow-sm flex items-center justify-center gap-2 transition">
                                <i class="fa-solid fa-print"></i> SJ
                            </a>

                            <a href="cetak_invoice.php?no_faktur=<?= $row['no_faktur'] ?>" target="_blank" 
                               class="bg-purple-600 text-white px-3 py-2 rounded text-xs font-bold hover:bg-purple-700 shadow-sm flex items-center gap-1 transition"
                               title="Cetak Invoice">
                                <i class="fa-solid fa-file-invoice-dollar"></i> INV
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function cariTable() {
    let input = document.getElementById("cariSurat");
    let filter = input.value.toUpperCase();
    let table = document.getElementById("tabelSurat");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let tdSurat = tr[i].getElementsByTagName("td")[0];
        let tdPelanggan = tr[i].getElementsByTagName("td")[2];
        let tdDriver = tr[i].getElementsByTagName("td")[3];
        
        if (tdSurat || tdPelanggan || tdDriver) {
            let txtSurat = tdSurat.textContent || tdSurat.innerText;
            let txtPelanggan = tdPelanggan.textContent || tdPelanggan.innerText;
            let txtDriver = tdDriver.textContent || tdDriver.innerText;
            
            if (txtSurat.toUpperCase().indexOf(filter) > -1 || 
                txtPelanggan.toUpperCase().indexOf(filter) > -1 || 
                txtDriver.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }        
    }
}
</script>