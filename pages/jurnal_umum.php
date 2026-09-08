<?php
// PERBAIKAN: Tambah 'invoice'
wajib_akses('jurnal_umum');
$id_usaha = $_SESSION['id_usaha'];

// SIMPAN JURNAL
if(isset($_POST['simpan_jurnal'])){
    $tgl = $_POST['tanggal'];
    $ref = $_POST['no_ref'];
    $ket = $_POST['keterangan'];
    
    // Loop Inputan
    $akun = $_POST['akun_id'];
    $debit = $_POST['debit'];
    $kredit = $_POST['kredit'];
    
    for($i=0; $i<count($akun); $i++){
        if($akun[$i] != ""){
            $d = $debit[$i] ?: 0; $k = $kredit[$i] ?: 0;
            mysqli_query($conn, "INSERT INTO jurnal_umum (id_usaha, tanggal, no_ref, akun_id, keterangan, debit, kredit) VALUES ('$id_usaha', '$tgl', '$ref', '{$akun[$i]}', '$ket', '$d', '$k')");
        }
    }
    echo "<script>alert('Jurnal berhasil disimpan'); window.location='index.php?page=jurnal_umum';</script>";
}
?>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-lg shadow-sm border-t-4 border-indigo-600">
        <h3 class="font-bold text-gray-800 mb-4">Input Jurnal Umum / Penyesuaian</h3>
        <form method="POST">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div><label class="text-xs font-bold">Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded"></div>
                <div><label class="text-xs font-bold">No. Ref / Bukti</label><input type="text" name="no_ref" class="w-full border p-2 rounded" placeholder="JU-<?= date('ymd') ?>"></div>
                <div><label class="text-xs font-bold">Keterangan</label><input type="text" name="keterangan" class="w-full border p-2 rounded" placeholder="Contoh: Penyesuaian stok..."></div>
            </div>
            
            <table class="w-full text-sm border mb-4">
                <thead class="bg-gray-100"><tr><th class="p-2">Akun</th><th class="p-2 w-32">Debit</th><th class="p-2 w-32">Kredit</th></tr></thead>
                <tbody id="areaJurnal">
                    <?php for($x=0;$x<2;$x++): ?>
                    <tr>
                        <td class="p-2">
                            <select name="akun_id[]" class="w-full border p-1">
                                <option value="">-- Pilih Akun --</option>
                                <?php 
                                $qa = mysqli_query($conn, "SELECT * FROM akun_perkiraan WHERE id_usaha='$id_usaha' ORDER BY kode_akun ASC");
                                while($a = mysqli_fetch_assoc($qa)) echo "<option value='{$a['id']}'>{$a['kode_akun']} - {$a['nama_akun']}</option>";
                                ?>
                            </select>
                        </td>
                        <td class="p-2"><input type="number" name="debit[]" class="w-full border p-1 text-right" placeholder="0"></td>
                        <td class="p-2"><input type="number" name="kredit[]" class="w-full border p-1 text-right" placeholder="0"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <button type="submit" name="simpan_jurnal" class="bg-indigo-600 text-white px-6 py-2 rounded font-bold hover:bg-indigo-700">Simpan Jurnal</button>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm">
        <h3 class="font-bold text-gray-800 mb-4">Riwayat Jurnal (Terbaru)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2">Tanggal</th>
                        <th class="p-2">No. Ref</th>
                        <th class="p-2">Akun</th>
                        <th class="p-2">Ket</th>
                        <th class="p-2 text-right">Debit</th>
                        <th class="p-2 text-right">Kredit</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php
                    $qj = mysqli_query($conn, "SELECT j.*, a.nama_akun, a.kode_akun FROM jurnal_umum j JOIN akun_perkiraan a ON j.akun_id = a.id WHERE j.id_usaha='$id_usaha' ORDER BY j.tanggal DESC, j.id DESC LIMIT 50");
                    while($j = mysqli_fetch_assoc($qj)):
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-2"><?= date('d/m/y', strtotime($j['tanggal'])) ?></td>
                        <td class="p-2 font-mono text-xs"><?= $j['no_ref'] ?></td>
                        <td class="p-2 font-bold text-gray-700"><?= $j['kode_akun'] ?> - <?= $j['nama_akun'] ?></td>
                        <td class="p-2 text-gray-500 italic"><?= $j['keterangan'] ?></td>
                        <td class="p-2 text-right <?= $j['debit']>0?'font-bold':'' ?>"><?= $j['debit']>0 ? number_format($j['debit']) : '-' ?></td>
                        <td class="p-2 text-right <?= $j['kredit']>0?'font-bold':'' ?>"><?= $j['kredit']>0 ? number_format($j['kredit']) : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>