<?php
wajib_akses('data_retur');

// pages/data_retur.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$id_usaha = $_SESSION['id_usaha'];
?>

<div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
    <h2 class="text-2xl font-bold text-slate-800 mb-6"><i class="fa-solid fa-rotate-left mr-2 text-red-600"></i> Data Retur Barang</h2>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border">
            <thead class="bg-slate-100 uppercase font-bold text-slate-600">
                <tr>
                    <th class="p-3 border w-2/12">Info Retur</th>
                    <th class="p-3 border w-2/12">Pelanggan</th>
                    <th class="p-3 border w-3/12">Item Diretur</th>
                    <th class="p-3 border w-2/12">Alasan & Bukti</th>
                    <th class="p-3 border text-center w-1/12">Status</th>
                    <th class="p-3 border text-center w-2/12">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php
                $q = mysqli_query($conn, "SELECT r.*, u.nama AS nama_user 
                                          FROM retur r 
                                          JOIN users u ON r.user_id = u.id 
                                          WHERE r.id_usaha='$id_usaha' ORDER BY r.id DESC");

                while($r = mysqli_fetch_assoc($q)):
                    $st_color = match($r['status']) { 'Pending'=>'bg-yellow-100 text-yellow-700', 'Disetujui'=>'bg-green-100 text-green-700', 'Ditolak'=>'bg-red-100 text-red-700' };
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 border align-top">
                        <div class="font-bold text-indigo-700"><?= $r['no_retur'] ?></div>
                        <div class="text-[10px] text-gray-500">Ref: <?= $r['no_pesanan'] ?></div>
                        <div class="text-[10px] text-gray-400"><?= date('d/m/y H:i', strtotime($r['tanggal'])) ?></div>
                    </td>
                    <td class="p-3 border align-top font-bold text-slate-600">
                        <?= $r['nama_user'] ?>
                    </td>
                    <td class="p-3 border align-top">
                        <ul class="list-disc list-inside text-xs text-red-600 font-medium">
                            <?php
                            $q_det = mysqli_query($conn, "SELECT rd.qty, b.nama_barang, b.satuan 
                                                          FROM retur_detail rd 
                                                          JOIN barang b ON rd.barang_id = b.id 
                                                          WHERE rd.retur_id='{$r['id']}'");
                            while($d = mysqli_fetch_assoc($q_det)) {
                                echo "<li>{$d['nama_barang']} (<b>".(float)$d['qty']." {$d['satuan']}</b>)</li>";
                            }
                            ?>
                        </ul>
                    </td>
                    <td class="p-3 border align-top">
                        <div class="text-xs italic mb-2">"<?= $r['alasan'] ?>"</div>
                        <?php if($r['foto_bukti']): ?>
                            <a href="assets/img/bukti_retur/<?= $r['foto_bukti'] ?>" target="_blank" class="text-indigo-500 text-xs underline"><i class="fa-solid fa-image"></i> Lihat Bukti</a>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Tidak ada foto</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 border align-top text-center">
                        <span class="px-2 py-1 rounded-full text-[10px] uppercase font-bold <?= $st_color ?>"><?= $r['status'] ?></span>
                        <?php if($r['respon_admin']): ?>
                            <div class="text-[10px] mt-1 text-gray-500 border-t pt-1">Note: <?= $r['respon_admin'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 border align-top text-center">
                        <?php if($r['status'] == 'Pending'): ?>
                        <div class="flex flex-col gap-1">
                            <button onclick="prosesRetur(<?= $r['id'] ?>, 'Disetujui')" class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">Setujui (Stok+)</button>
                            <button onclick="prosesRetur(<?= $r['id'] ?>, 'Ditolak')" class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700">Tolak</button>
                        </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400"><i class="fa-solid fa-lock"></i> Selesai</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function prosesRetur(id, keputusan) {
    let catatan = prompt("Masukkan catatan untuk pelanggan (Opsional):", "");
    if(catatan === null) return; 

    if(!confirm("Anda yakin mengubah status menjadi " + keputusan + "?")) return;

    let fd = new FormData();
    fd.append('action', 'proses_retur');
    fd.append('id_retur', id);
    fd.append('keputusan', keputusan);
    fd.append('catatan', catatan);

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        alert(d.msg);
        location.reload();
    })
    .catch(e => alert('Terjadi kesalahan sistem'));
}
</script>