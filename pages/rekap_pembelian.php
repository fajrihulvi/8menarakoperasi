<?php
// Proteksi Halaman
wajib_akses('rekap_pembelian');

// Filter Tanggal & Supplier
$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filter_sup = $_GET['supplier_id'] ?? '';
$filter_status = $_GET['status_bayar'] ?? '';

?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 border-b pb-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Rekapan Nota Barang Masuk</h3>
            <p class="text-sm text-gray-500">Rincian per item sesuai faktur supplier</p>
        </div>

        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <input type="hidden" name="page" value="rekap_pembelian">
            
            <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="border p-2 rounded text-sm focus:outline-indigo-500">
            <span class="text-gray-400">-</span>
            <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="border p-2 rounded text-sm focus:outline-indigo-500">
            
            <select name="supplier_id" class="border p-2 rounded text-sm bg-white focus:outline-indigo-500">
                <option value="">Semua Supplier</option>
                <?php 
                $sup = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama_supplier ASC");
                while($s = mysqli_fetch_assoc($sup)){
                    $sel = ($filter_sup == $s['id']) ? 'selected' : '';
                    echo "<option value='{$s['id']}' $sel>{$s['nama_supplier']}</option>";
                }
                ?>
            </select>

            <select name="status_bayar" class="border p-2 rounded text-sm bg-white focus:outline-indigo-500">
                <option value="">Semua Status</option>
                <option value="lunas" <?= ($filter_status=='lunas')?'selected':'' ?>>LUNAS</option>
                <option value="belum" <?= ($filter_status=='belum')?'selected':'' ?>>TEMPO</option>
            </select>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-indigo-700">
                <i class="fa-solid fa-filter mr-1"></i> Filter
            </button>
            
            <button type="button" onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-green-700">
                <i class="fa-solid fa-print mr-1"></i> Cetak
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border border-gray-200">
            <thead class="bg-gray-100 text-gray-700 uppercase font-bold text-xs">
                <tr>
                    <th class="p-3 border">No</th>
                    <th class="p-3 border">Tgl Input</th>
                    <th class="p-3 border">Supplier</th>
                    <th class="p-3 border">Item Barang</th>
                    <th class="p-3 border text-center">Qty</th>
                    <th class="p-3 border text-center">Satuan</th>
                    <th class="p-3 border">No Nota (PO)</th>
                    <th class="p-3 border text-right">Nilai (Rp)</th>
                    <th class="p-3 border text-center">Tgl Pelunasan</th>
                    <th class="p-3 border text-center">Ket</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php
                // QUERY COMPLEX JOIN
                $where = "WHERE t.jenis_transaksi = 'masuk' AND DATE(t.tanggal) BETWEEN '$tgl_awal' AND '$tgl_akhir'";
                
                if(!empty($filter_sup)) {
                    $where .= " AND t.supplier_id = '$filter_sup'";
                }
                if(!empty($filter_status)) {
                    $where .= " AND t.status_bayar = '$filter_status'";
                }

                $query = "
                    SELECT 
                        t.tanggal, 
                        s.nama_supplier, 
                        b.nama_barang, 
                        td.qty, 
                        b.satuan, 
                        t.no_faktur, 
                        td.subtotal, 
                        t.status_bayar, 
                        t.tgl_lunas
                    FROM transaksi_detail td
                    JOIN transaksi t ON td.no_faktur = t.no_faktur
                    JOIN barang b ON td.barang_id = b.id
                    JOIN supplier s ON t.supplier_id = s.id
                    $where
                    ORDER BY t.tanggal DESC, t.id DESC
                ";

                $run = mysqli_query($conn, $query);
                $no = 1;
                $grand_total = 0;

                while($r = mysqli_fetch_assoc($run)):
                    $grand_total += $r['subtotal'];
                    
                    // Format Tanggal Input
                    $tgl_input = date('d-m-Y', strtotime($r['tanggal']));
                    
                    // Format Tanggal Lunas
                    if($r['status_bayar'] == 'lunas' && !empty($r['tgl_lunas'])) {
                        $tgl_lunas = date('d-m-Y', strtotime($r['tgl_lunas']));
                    } else {
                        $tgl_lunas = "-";
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 border text-center"><?= $no++ ?></td>
                    <td class="p-2 border text-center"><?= $tgl_input ?></td>
                    <td class="p-2 border font-medium"><?= $r['nama_supplier'] ?></td>
                    <td class="p-2 border"><?= $r['nama_barang'] ?></td>
                    
                    <td class="p-2 border text-center font-bold"><?= (float)$r['qty'] ?></td>
                    <td class="p-2 border text-center"><?= $r['satuan'] ?></td>
                    <td class="p-2 border font-mono text-xs"><?= $r['no_faktur'] ?></td>
                    <td class="p-2 border text-right"><?= number_format($r['subtotal'], 0, ',', '.') ?></td>
                    <td class="p-2 border text-center"><?= $tgl_lunas ?></td>
                    <td class="p-2 border text-center">
                        <?php if($r['status_bayar'] == 'lunas'): ?>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold border border-green-200">LUNAS</span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-200">TEMPO</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>

                <?php if(mysqli_num_rows($run) == 0): ?>
                    <tr><td colspan="10" class="p-4 text-center text-gray-500 italic">Tidak ada data ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold">
                <tr>
                    <td colspan="7" class="p-3 border text-right">TOTAL NILAI</td>
                    <td class="p-3 border text-right text-indigo-700">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                    <td colspan="2" class="p-3 border"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
    @media print {
        body { background: white; }
        nav, aside, form { display: none !important; }
        .shadow-sm { shadow: none !important; box-shadow: none !important; }
        .rounded-xl { border-radius: 0 !important; }
        table { font-size: 10px !important; width: 100%; }
        th, td { border: 1px solid black !important; padding: 4px !important; }
        .text-indigo-700 { color: black !important; }
        /* Paksa cetak warna background badge */
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
</style>