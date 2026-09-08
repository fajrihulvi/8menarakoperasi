<?php
// PERBAIKAN: Tambah 'invoice'
wajib_akses('neraca_saldo');
$id_usaha = $_SESSION['id_usaha'];
$tgl_akhir = $_GET['per_tgl'] ?? date('Y-m-d');
?>

<div class="bg-white p-8 rounded-lg shadow-lg">
    <div class="flex justify-between items-end mb-6 print:hidden">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Neraca Saldo (Trial Balance)</h1>
            <p class="text-gray-500">Periode berakhir: <b><?= date('d F Y', strtotime($tgl_akhir)) ?></b></p>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="page" value="neraca_saldo">
            <input type="date" name="per_tgl" value="<?= $tgl_akhir ?>" class="border p-2 rounded">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded font-bold">Filter</button>
            <button onclick="window.print()" type="button" class="bg-gray-600 text-white px-4 py-2 rounded font-bold"><i class="fa-solid fa-print"></i></button>
        </form>
    </div>

    <div class="hidden print:block text-center mb-8">
        <h2 class="text-2xl font-bold uppercase"><?= $nama_toko_aktif ?? 'NAMA TOKO' ?></h2>
        <h3 class="text-xl">NERACA SALDO</h3>
        <p>Per Tanggal: <?= date('d F Y', strtotime($tgl_akhir)) ?></p>
    </div>

    <table class="w-full text-sm border-collapse border border-gray-400">
        <thead class="bg-gray-200 font-bold uppercase text-gray-700 text-center">
            <tr>
                <th class="p-3 border border-gray-400">Kode Akun</th>
                <th class="p-3 border border-gray-400">Nama Akun</th>
                <th class="p-3 border border-gray-400">Debit</th>
                <th class="p-3 border border-gray-400">Kredit</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_debit = 0; $total_kredit = 0;
            
            // Query Saldo per akun
            $query = mysqli_query($conn, "
                SELECT a.kode_akun, a.nama_akun, a.posisi_normal,
                SUM(CASE WHEN j.tanggal <= '$tgl_akhir' THEN j.debit ELSE 0 END) as tot_debit,
                SUM(CASE WHEN j.tanggal <= '$tgl_akhir' THEN j.kredit ELSE 0 END) as tot_kredit
                FROM akun_perkiraan a
                LEFT JOIN jurnal_umum j ON a.id = j.akun_id AND j.id_usaha = a.id_usaha
                WHERE a.id_usaha = '$id_usaha'
                GROUP BY a.id
                ORDER BY a.kode_akun ASC
            ");

            while($row = mysqli_fetch_assoc($query)):
                $saldo_debit = 0; $saldo_kredit = 0;
                $d = $row['tot_debit']; $k = $row['tot_kredit'];
                
                // Hitung Saldo berdasarkan posisi normal
                if($row['posisi_normal'] == 'Debit') {
                    $saldo = $d - $k;
                    if($saldo >= 0) $saldo_debit = $saldo; else $saldo_kredit = abs($saldo);
                } else {
                    $saldo = $k - $d;
                    if($saldo >= 0) $saldo_kredit = $saldo; else $saldo_debit = abs($saldo);
                }

                if($saldo_debit == 0 && $saldo_kredit == 0) continue; // Skip jika 0

                $total_debit += $saldo_debit;
                $total_kredit += $saldo_kredit;
            ?>
            <tr class="odd:bg-white even:bg-gray-50 print:bg-white">
                <td class="p-2 border border-gray-400 font-mono"><?= $row['kode_akun'] ?></td>
                <td class="p-2 border border-gray-400 font-bold text-gray-700"><?= $row['nama_akun'] ?></td>
                <td class="p-2 border border-gray-400 text-right"><?= $saldo_debit > 0 ? number_format($saldo_debit) : '-' ?></td>
                <td class="p-2 border border-gray-400 text-right"><?= $saldo_kredit > 0 ? number_format($saldo_kredit) : '-' ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="bg-gray-100 font-bold text-lg print:bg-gray-200">
            <tr>
                <td colspan="2" class="p-3 border border-gray-400 text-right uppercase">Total Neraca</td>
                <td class="p-3 border border-gray-400 text-right text-indigo-700"><?= number_format($total_debit) ?></td>
                <td class="p-3 border border-gray-400 text-right text-indigo-700"><?= number_format($total_kredit) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="p-2 text-center text-xs text-gray-500 italic">
                    * Jika Total Debit & Kredit seimbang, neraca valid.
                </td>
            </tr>
        </tfoot>
    </table>
</div>