<?php
// pages/retur_saya.php
wajib_akses('retur_saya');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../layout/tabel_helper.php';
$user_id = $_SESSION['user_id'];
?>

<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4">
        <div>
            <h3 class="text-xl font-bold text-gray-800"><i class="fa-solid fa-rotate-left mr-2 text-rose-500"></i>Retur Barang</h3>
            <p class="text-sm text-gray-500">Ajukan retur jika ada barang rusak/busuk dari surat jalan yang sudah diterima</p>
        </div>
    </div>

    <?php
    $f_tgl_awal  = htmlspecialchars((string)($_GET['tgl_awal'] ?? ''), ENT_QUOTES, 'UTF-8');
    $f_tgl_akhir = htmlspecialchars((string)($_GET['tgl_akhir'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extra_tgl   = '<input type="date" name="tgl_awal" value="' . $f_tgl_awal . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Mulai">'
                 . '<input type="date" name="tgl_akhir" value="' . $f_tgl_akhir . '" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white" title="Tanggal Akhir">';

    echo render_filter('Cari no surat jalan / driver...', $extra_tgl);
    ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-rose-50 text-rose-800 uppercase font-bold text-xs">
                <tr>
                    <th class="p-3">No Surat Jalan</th>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3">Info Pengiriman</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $sj_cari  = ambil_kata_kunci();
                $sj_awal  = trim((string) ($_GET['tgl_awal'] ?? ''));
                $sj_akhir = trim((string) ($_GET['tgl_akhir'] ?? ''));
                $sj_hal   = ambil_halaman();
                $sj_limit = ambil_per_halaman();

                $sj_wt = ["t.jenis_transaksi = 'keluar'", "t.status = 'selesai'", 'ps.user_id = ?'];
                $sj_pt = [$user_id];
                $sj_tt = 'i';

                if ($sj_awal !== '') {
                    $sj_wt[] = 'DATE(t.tanggal) >= ?';
                    $sj_pt[] = $sj_awal;
                    $sj_tt  .= 's';
                }
                if ($sj_akhir !== '') {
                    $sj_wt[] = 'DATE(t.tanggal) <= ?';
                    $sj_pt[] = $sj_akhir;
                    $sj_tt  .= 's';
                }

                [$sj_where, $sj_params, $sj_tipe] = bangun_filter(
                    $sj_cari, ['t.no_faktur', 't.nama_driver'],
                    $sj_wt, $sj_pt, $sj_tt
                );

                $sj_from = 'transaksi t JOIN pesanan ps ON t.no_faktur = ps.no_pesanan';

                $sj_total  = hitung_total($conn, $sj_from, $sj_where, $sj_params, $sj_tipe);
                $sj_hal    = batasi_halaman($sj_hal, $sj_total, $sj_limit);
                $sj_offset = ($sj_hal - 1) * $sj_limit;

                $sj_rows = ambil_data($conn, "SELECT t.* FROM $sj_from",
                    $sj_where, $sj_params, $sj_tipe, 'ORDER BY t.tanggal DESC', $sj_limit, $sj_offset);

                if (!$sj_rows) { echo render_kosong(4, 'Belum ada surat jalan yang bisa diretur.'); }

                foreach ($sj_rows as $row):
                    $no_sj  = str_replace('TRX', 'SJ', $row['no_faktur']);
                    $driver = !empty($row['nama_driver']) ? $row['nama_driver'] : null;

                    $q_retur_ada = mysqli_query($conn, "SELECT status FROM retur WHERE no_pesanan='" . mysqli_real_escape_string($conn, $row['no_faktur']) . "' ORDER BY id DESC LIMIT 1");
                    $retur_ada   = $q_retur_ada ? mysqli_fetch_assoc($q_retur_ada) : null;
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
                        <?php if ($driver): ?>
                            <div class="text-xs text-gray-600"><i class="fa-solid fa-truck mr-1 text-blue-500"></i><?= strtoupper($driver) ?></div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-center">
                        <?php if ($retur_ada): ?>
                            <?php $st_color = match($retur_ada['status']) { 'Menunggu'=>'bg-yellow-100 text-yellow-700', 'Mengganti Barang'=>'bg-blue-100 text-blue-700', 'Potong Jumlah Invoice'=>'bg-green-100 text-green-700', 'Ditolak'=>'bg-red-100 text-red-700', default=>'bg-gray-100 text-gray-600' }; ?>
                            <span class="px-2 py-1 rounded-full text-[10px] uppercase font-bold <?= $st_color ?>">Retur: <?= $retur_ada['status'] ?></span>
                        <?php else: ?>
                            <button onclick="bukaModalRetur('<?= htmlspecialchars($row['no_faktur'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($no_sj, ENT_QUOTES, 'UTF-8') ?>')"
                                class="bg-rose-500 text-white px-3 py-2 rounded text-xs font-bold hover:bg-rose-600 shadow-sm inline-flex items-center justify-center gap-2 transition">
                                <i class="fa-solid fa-rotate-left"></i> Ajukan Retur
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($sj_hal, $sj_total, $sj_limit) ?>
</div>

<div class="bg-white rounded-lg shadow-sm p-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fa-solid fa-clock-rotate-left mr-2 text-slate-400"></i>Riwayat Pengajuan Retur Saya</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border">
            <thead class="bg-slate-100 uppercase font-bold text-slate-600 text-xs">
                <tr>
                    <th class="p-3 border">Info Retur</th>
                    <th class="p-3 border">Item Diretur</th>
                    <th class="p-3 border">Alasan & Bukti</th>
                    <th class="p-3 border text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php
                $q_riwayat = mysqli_query($conn, "SELECT * FROM retur WHERE user_id='" . (int)$user_id . "' ORDER BY id DESC");
                if (mysqli_num_rows($q_riwayat) == 0) { echo render_kosong(4, 'Belum pernah mengajukan retur.'); }
                while ($r = mysqli_fetch_assoc($q_riwayat)):
                    $st_color = match($r['status']) { 'Menunggu'=>'bg-yellow-100 text-yellow-700', 'Mengganti Barang'=>'bg-blue-100 text-blue-700', 'Potong Jumlah Invoice'=>'bg-green-100 text-green-700', 'Ditolak'=>'bg-red-100 text-red-700', default=>'bg-gray-100 text-gray-600' };
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 border align-top">
                        <div class="font-bold text-indigo-700"><?= $r['no_retur'] ?></div>
                        <div class="text-[10px] text-gray-500">Ref SJ: <?= str_replace('TRX', 'SJ', $r['no_pesanan']) ?></div>
                        <div class="text-[10px] text-gray-400"><?= date('d/m/y H:i', strtotime($r['tanggal'])) ?></div>
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
                        <?php if(!empty($r['no_faktur_pengganti'])): ?>
                            <div class="text-[10px] mt-1 text-blue-600 border-t pt-1">SJ Pengganti: <?= str_replace('RPL', 'SJ', $r['no_faktur_pengganti']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL AJUKAN RETUR -->
<div id="modalRetur" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[90vh] flex flex-col">
        <div class="p-4 border-b flex justify-between items-center bg-rose-50">
            <h3 class="font-bold text-slate-800">Ajukan Retur — <span id="labelNoSJ"></span></h3>
            <button onclick="tutupModalRetur()" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form id="formRetur" class="overflow-y-auto p-4 flex-1" onsubmit="return submitRetur(event)">
            <input type="hidden" name="no_pesanan" id="inputNoFaktur">

            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Item yang Ingin Diretur</label>
            <div id="daftarItemRetur" class="space-y-2 mb-4">
                <div class="text-center text-slate-400 py-6"><i class="fa-solid fa-circle-notch fa-spin"></i> Memuat item...</div>
            </div>

            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alasan Retur</label>
            <textarea name="alasan" required rows="3" class="w-full border border-slate-200 rounded-lg p-2 text-sm mb-4" placeholder="Cth: Buah busuk, kemasan rusak, dll."></textarea>

            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Foto Bukti (Opsional)</label>
            <input type="file" name="foto" accept="image/*" class="w-full text-sm mb-4">

            <button type="submit" class="w-full bg-rose-600 text-white py-2.5 rounded-lg font-bold hover:bg-rose-700 shadow-sm">
                <i class="fa-solid fa-paper-plane mr-2"></i>Kirim Pengajuan Retur
            </button>
        </form>
    </div>
</div>

<script>
function bukaModalRetur(noFaktur, noSJ) {
    document.getElementById('inputNoFaktur').value = noFaktur;
    document.getElementById('labelNoSJ').innerText = noSJ;
    document.getElementById('daftarItemRetur').innerHTML = '<div class="text-center text-slate-400 py-6"><i class="fa-solid fa-circle-notch fa-spin"></i> Memuat item...</div>';
    document.getElementById('modalRetur').classList.remove('hidden');

    let fd = new FormData();
    fd.append('action', 'get_items_faktur');
    fd.append('no_faktur', noFaktur);

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        const cont = document.getElementById('daftarItemRetur');
        if (d.status !== 'success' || !d.items.length) {
            cont.innerHTML = '<div class="text-center text-slate-400 py-4">Tidak ada item ditemukan.</div>';
            return;
        }
        cont.innerHTML = d.items.map(it => `
            <div class="flex items-center gap-2 border border-slate-200 rounded-lg p-2">
                <input type="checkbox" onchange="const q=document.getElementById('qty_${it.id_barang}'); q.readOnly = !this.checked; if(!this.checked) q.value='';" class="w-4 h-4">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-700">${it.nama_barang}</div>
                    <div class="text-[10px] text-slate-400">Dikirim: ${it.qty_kirim} ${it.satuan}</div>
                </div>
                <input type="number" id="qty_${it.id_barang}" name="qty_retur[]" value="0" min="0" max="${it.qty_kirim}" step="0.01" readonly
                    placeholder="Qty" class="w-24 border border-slate-200 rounded-lg p-1.5 text-sm text-center bg-slate-50">
                <input type="hidden" name="barang_id[]" value="${it.id_barang}">
            </div>
        `).join('');
    })
    .catch(e => {
        document.getElementById('daftarItemRetur').innerHTML = '<div class="text-center text-red-400 py-4">Gagal memuat item.</div>';
    });
}

function tutupModalRetur() {
    document.getElementById('modalRetur').classList.add('hidden');
    document.getElementById('formRetur').reset();
}

function submitRetur(e) {
    e.preventDefault();
    const form = document.getElementById('formRetur');
    const fd = new FormData(form);
    fd.append('action', 'ajukan_retur');

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i>Mengirim...';

    fetch('pages/ajax_retur.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        alert(d.msg);
        if (d.status === 'success') {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i>Kirim Pengajuan Retur';
        }
    })
    .catch(e => {
        alert('Terjadi kesalahan sistem');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i>Kirim Pengajuan Retur';
    });
    return false;
}
</script>
