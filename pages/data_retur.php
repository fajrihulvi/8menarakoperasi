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
                    $st_color = match($r['status']) {
                        'Menunggu'               => 'bg-yellow-100 text-yellow-700',
                        'Mengganti Barang'       => 'bg-blue-100 text-blue-700',
                        'Potong Jumlah Invoice'  => 'bg-green-100 text-green-700',
                        'Ditolak'                => 'bg-red-100 text-red-700',
                        default                  => 'bg-gray-100 text-gray-600',
                    };

                    $detail_items = [];
                    $q_det = mysqli_query($conn, "SELECT rd.id, rd.qty, rd.barang_id, b.nama_barang, b.satuan
                                                  FROM retur_detail rd
                                                  JOIN barang b ON rd.barang_id = b.id
                                                  WHERE rd.retur_id='{$r['id']}'");
                    while($d = mysqli_fetch_assoc($q_det)) { $detail_items[] = $d; }
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
                            <?php foreach($detail_items as $d): ?>
                                <li><?= $d['nama_barang'] ?> (<b><?= (float)$d['qty'] ?> <?= $d['satuan'] ?></b>)</li>
                            <?php endforeach; ?>
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
                        <?php if($r['status'] == 'Menunggu'): ?>
                            <div class="flex flex-col gap-1">
                                <button type="button" data-id="<?= (int)$r['id'] ?>" data-keputusan="Mengganti Barang"
                                    data-items="<?= htmlspecialchars(json_encode($detail_items), ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="bukaModalKeputusanDari(this)" class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700">Mengganti Barang</button>
                                <button type="button" data-id="<?= (int)$r['id'] ?>" data-keputusan="Potong Jumlah Invoice"
                                    data-items="<?= htmlspecialchars(json_encode($detail_items), ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="bukaModalKeputusanDari(this)" class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700">Potong Jumlah Invoice</button>
                                <button type="button" onclick="prosesTolak(<?= (int)$r['id'] ?>)" class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700">Tolak</button>
                            </div>
                        <?php elseif($r['status'] == 'Mengganti Barang'): ?>
                            <?php if(!empty($r['no_faktur_pengganti'])): ?>
                                <span class="text-xs text-green-600 font-bold"><i class="fa-solid fa-circle-check"></i> SJ Pengganti: <?= $r['no_faktur_pengganti'] ?></span>
                            <?php else: ?>
                                <button onclick="buatSJPengganti(<?= $r['id'] ?>)" class="bg-indigo-600 text-white px-2 py-1 rounded text-xs hover:bg-indigo-700">
                                    <i class="fa-solid fa-truck-fast mr-1"></i>Buat Surat Jalan Pengganti
                                </button>
                            <?php endif; ?>
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

<!-- MODAL KEPUTUSAN: Mengganti Barang / Potong Jumlah Invoice -->
<div id="modalKeputusan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[90vh] flex flex-col">
        <div class="p-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800" id="judulModalKeputusan">Keputusan Retur</h3>
            <button onclick="tutupModalKeputusan()" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="formKeputusan" class="overflow-y-auto p-4 flex-1">
            <input type="hidden" id="inputIdRetur" name="id_retur">
            <input type="hidden" id="inputKeputusan" name="keputusan">

            <div id="areaAlokasi" class="space-y-3 mb-4"></div>

            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catatan untuk Pelanggan (Opsional)</label>
            <textarea name="catatan" rows="2" class="w-full border border-slate-200 rounded-lg p-2 text-sm mb-4"></textarea>

            <button type="button" id="btnSimpanKeputusan" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-bold hover:bg-indigo-700 shadow-sm">
                Simpan Keputusan
            </button>
        </form>
    </div>
</div>

<script>
function prosesTolak(id) {
    let catatan = prompt("Masukkan catatan penolakan untuk pelanggan (Opsional):", "");
    if(catatan === null) return;
    if(!confirm("Tolak pengajuan retur ini?")) return;

    let fd = new FormData();
    fd.append('action', 'proses_retur');
    fd.append('id_retur', id);
    fd.append('keputusan', 'Ditolak');
    fd.append('catatan', catatan);

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { alert(d.msg); location.reload(); })
    .catch(e => alert('Terjadi kesalahan sistem'));
}

function bukaModalKeputusanDari(btn) {
    const idRetur   = parseInt(btn.dataset.id, 10);
    const keputusan = btn.dataset.keputusan;
    const items     = JSON.parse(btn.dataset.items);
    bukaModalKeputusan(idRetur, keputusan, items);
}

function bukaModalKeputusan(idRetur, keputusan, items) {
    document.getElementById('inputIdRetur').value = idRetur;
    document.getElementById('inputKeputusan').value = keputusan;
    document.getElementById('judulModalKeputusan').innerText = keputusan;

    const area = document.getElementById('areaAlokasi');

    if (keputusan === 'Mengganti Barang') {
        area.innerHTML = `
            <div class="bg-blue-50 border border-blue-100 text-blue-700 text-xs rounded-lg p-3">
                Seluruh qty retur berikut akan dicatat sebagai <b>"Dikembalikan ke Warehouse"</b> (tidak menambah stok jual).
                Surat jalan pengganti bisa dibuat setelah ini disimpan.
            </div>
            <ul class="list-disc list-inside text-sm text-slate-700 mt-2">
                ${items.map(it => `<li>${it.nama_barang} — <b>${parseFloat(it.qty)} ${it.satuan}</b></li>`).join('')}
            </ul>
        `;
    } else {
        area.innerHTML = `
            <div class="bg-green-50 border border-green-100 text-green-700 text-xs rounded-lg p-3 mb-2">
                Bagi qty retur tiap barang ke 3 kategori. Total ketiganya harus sama dengan qty retur.
                Kategori "Stok Gudang" akan otomatis menambah stok jual kembali.
            </div>
            ${items.map(it => `
                <div class="border border-slate-200 rounded-lg p-3">
                    <div class="text-sm font-bold text-slate-700 mb-2">${it.nama_barang} <span class="text-slate-400 font-normal">(Retur: ${parseFloat(it.qty)} ${it.satuan})</span></div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold">Warehouse</label>
                            <input type="number" name="alokasi[${it.id}][warehouse]" min="0" step="0.01" value="0" class="w-full border border-slate-200 rounded p-1.5 text-sm text-center">
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold">Stok Gudang</label>
                            <input type="number" name="alokasi[${it.id}][gudang]" min="0" step="0.01" value="0" class="w-full border border-slate-200 rounded p-1.5 text-sm text-center">
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold">Waste</label>
                            <input type="number" name="alokasi[${it.id}][waste]" min="0" step="0.01" value="0" class="w-full border border-slate-200 rounded p-1.5 text-sm text-center">
                        </div>
                    </div>
                </div>
            `).join('')}
        `;
    }

    document.getElementById('modalKeputusan').classList.remove('hidden');
    document.getElementById('modalKeputusan').classList.add('flex');
}

function tutupModalKeputusan() {
    document.getElementById('modalKeputusan').classList.add('hidden');
    document.getElementById('modalKeputusan').classList.remove('flex');
    document.getElementById('formKeputusan').reset();
}

function submitKeputusan(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('formKeputusan');
    const fd = new FormData(form);
    fd.append('action', 'proses_retur');

    if (!confirm('Simpan keputusan "' + document.getElementById('inputKeputusan').value + '" untuk retur ini?')) return false;

    const btn = document.getElementById('btnSimpanKeputusan');
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        alert(d.msg);
        if (d.status === 'success') {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerText = 'Simpan Keputusan';
        }
    })
    .catch(e => {
        alert('Terjadi kesalahan sistem');
        btn.disabled = false;
        btn.innerText = 'Simpan Keputusan';
    });
    return false;
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formKeputusan');
    if (form) {
        form.addEventListener('submit', submitKeputusan);
    }
    const btn = document.getElementById('btnSimpanKeputusan');
    if (btn) {
        btn.addEventListener('click', submitKeputusan);
    }
});

function buatSJPengganti(idRetur) {
    if(!confirm("Buat surat jalan pengganti untuk retur ini? Stok akan dipotong sesuai qty retur.")) return;

    let fd = new FormData();
    fd.append('action', 'buat_sj_pengganti');
    fd.append('id_retur', idRetur);

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        alert(d.msg);
        if (d.status === 'success') {
            window.open('cetak_surat_jalan.php?no_faktur=' + d.no_faktur, '_blank');
            location.reload();
        }
    })
    .catch(e => alert('Terjadi kesalahan sistem'));
}
</script>
