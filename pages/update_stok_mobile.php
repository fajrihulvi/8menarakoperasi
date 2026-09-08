<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. KEAMANAN
wajib_akses('update_stok_mobile');

$id_usaha = $_SESSION['id_usaha'] ?? 1;

// =================================================================================
// 2. PROSES AJAX HANDLE (UPDATE STOK / REQUEST APPROVAL)
// =================================================================================
if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == 'update') {
    header('Content-Type: application/json');
    
    $id_barang = $_POST['id'];
    $stok_baru = $_POST['stok'];
    $harga_baru= $_POST['harga'];
    $user_id   = $_SESSION['user_id'];

    // Ambil Data Lama
    $cek = mysqli_query($conn, "SELECT stok, harga_jual, nama_barang, kode_barang FROM barang WHERE id='$id_barang' AND id_usaha='$id_usaha'");
    $d_lama = mysqli_fetch_assoc($cek);
    
    if(!$d_lama) {
        echo json_encode(['status'=>'error', 'msg'=>'Barang tidak ditemukan']);
        exit;
    }

    // A. JIKA ROLE PO -> MASUK APPROVAL REQUEST
    if($_SESSION['role'] == 'po') {
        // Susun Data JSON
        $data_json = json_encode([
            'id_barang'   => $id_barang,
            'stok_baru'   => $stok_baru,
            'harga_jual'  => $harga_baru,
            'stok_lama'   => $d_lama['stok'],
            'harga_lama'  => $d_lama['harga_jual']
        ]);

        $keterangan = "Request Update Mobile: " . $d_lama['nama_barang'];

        $q_req = "INSERT INTO approval_request (id_usaha, user_id, tipe_aksi, keterangan, data_json, status) 
                  VALUES ('$id_usaha', '$user_id', 'update_stok', '$keterangan', '$data_json', 'pending')";
        
        if(mysqli_query($conn, $q_req)) {
            echo json_encode(['status'=>'success', 'msg'=>'Request terkirim ke Admin']);
        } else {
            echo json_encode(['status'=>'error', 'msg'=>'Gagal request approval']);
        }
    } 
    // B. JIKA ADMIN -> LANGSUNG UPDATE
    else {
        $q_upd = "UPDATE barang SET stok='$stok_baru', harga_jual='$harga_baru' WHERE id='$id_barang' AND id_usaha='$id_usaha'";
        if(mysqli_query($conn, $q_upd)) {
            // Catat Log / Riwayat Harga jika berubah
            if($d_lama['harga_jual'] != $harga_baru) {
                mysqli_query($conn, "INSERT INTO riwayat_harga (barang_id, harga_jual_lama, harga_jual_baru, tgl_perubahan, user_id) VALUES ('$id_barang', '{$d_lama['harga_jual']}', '$harga_baru', NOW(), '$user_id')");
            }
            echo json_encode(['status'=>'success', 'msg'=>'Data berhasil diupdate']);
        } else {
            echo json_encode(['status'=>'error', 'msg'=>'Gagal update database']);
        }
    }
    exit; // Stop agar tidak load HTML
}
?>

<style>
    /* HACK: Sembunyikan Sidebar & Header Utama */
    #sidebar, header, #mobileOverlay, .bg-white.shadow-sm.h-16 { display: none !important; }
    
    /* Layout Utama */
    main { 
        padding: 0 !important; margin: 0 !important; 
        height: 100vh !important; width: 100vw !important;
        background: #f8fafc; position: fixed; top: 0; left: 0; 
        overflow-y: auto; z-index: 9999;
    }

    .sticky-header {
        position: sticky; top: 0; z-index: 50;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px);
        border-bottom: 1px solid #e2e8f0; padding: 16px;
        box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05);
    }

    .card-item {
        background: white; border-radius: 16px; padding: 16px; margin-bottom: 12px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02); border: 1px solid #f1f5f9;
        transition: all 0.3s; position: relative; overflow: hidden;
    }
    
    /* Style Kartu Pending Approval */
    .card-item.pending {
        border: 2px solid #fbbf24; background-color: #fffbeb; opacity: 0.9;
    }
    
    .badge-pending {
        position: absolute; top: 0; right: 0;
        background: #fbbf24; color: #92400e; font-size: 10px; font-weight: 800;
        padding: 4px 12px; border-bottom-left-radius: 12px;
        box-shadow: -2px 2px 5px rgba(0,0,0,0.05); z-index: 20;
    }

    /* Input Besar */
    .input-besar {
        font-size: 18px !important; padding: 12px !important;
        border-radius: 12px !important; border: 1px solid #cbd5e1;
        width: 100%; font-weight: 700; color: #1e293b; text-align: center;
        background-color: #fff; transition: all 0.2s;
    }
    .input-besar:focus { border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); outline: none; }
    .input-besar:disabled { background-color: #e2e8f0; color: #94a3b8; cursor: not-allowed; }

    /* Label & Badge */
    .label-kecil { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 4px; }
    
    .badge-sistem {
        display: inline-block; padding: 3px 8px; border-radius: 6px;
        font-size: 11px; font-weight: 700; color: #475569; background: #f1f5f9;
        border: 1px solid #e2e8f0; margin-bottom: 8px;
    }
    .badge-sistem.tipis { background: #fef2f2; color: #ef4444; border-color: #fee2e2; }
    
    /* Tombol Simpan */
    .btn-simpan {
        width: 100%; margin-top: 16px; 
        background: #4f46e5; color: white; font-weight: 800; 
        padding: 14px; border-radius: 12px; 
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: all 0.2s;
    }
    .btn-simpan:active { transform: scale(0.98); }
    .btn-simpan:disabled { background: #cbd5e1; color: #64748b; box-shadow: none; cursor: not-allowed; }
</style>

<div class="pb-32">
    <div class="sticky-header">
        <div class="flex justify-between items-center mb-4">
            <a href="index.php?page=dashboard" class="flex items-center gap-2 text-slate-600 bg-slate-100 px-4 py-2 rounded-xl font-bold text-sm hover:bg-slate-200 transition">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
            <div class="text-right">
                <h1 class="text-lg font-black text-slate-800 leading-none">STOK OPNAME</h1>
                <span class="text-[10px] font-bold text-indigo-600 tracking-wider bg-indigo-50 px-2 py-0.5 rounded">MODE ADMIN PO</span>
            </div>
        </div>

        <div class="relative">
            <input type="text" id="cariBarang" onkeyup="filterList()" placeholder="Cari nama barang / kode..." class="w-full bg-slate-100 border-none rounded-xl py-3.5 pl-11 pr-4 text-base font-semibold focus:ring-2 focus:ring-indigo-500 placeholder-slate-400">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-4 text-slate-400 text-lg"></i>
        </div>
    </div>

    <div class="p-4" id="areaListBarang">
        <?php
        // Query Barang + Cek apakah ada request pending di approval_request
        // Subquery mencari request 'update_stok' yang status='pending' dan JSON-nya mengandung ID barang ini
        $query = "
            SELECT b.*, 
            (SELECT data_json FROM approval_request ar 
             WHERE ar.tipe_aksi = 'update_stok' 
             AND ar.status = 'pending' 
             AND ar.id_usaha = '$id_usaha'
             AND ar.data_json LIKE CONCAT('%\"id_barang\":\"', b.id, '\"%')
             LIMIT 1
            ) as pending_json
            FROM barang b 
            WHERE b.id_usaha='$id_usaha' 
            ORDER BY b.nama_barang ASC
        ";
        
        $q = mysqli_query($conn, $query);
        
        if(mysqli_num_rows($q) > 0) {
            while($r = mysqli_fetch_assoc($q)):
                $stok_db  = (float)$r['stok'];
                $bg_badge = ($stok_db <= 5) ? 'tipis' : '';
                
                // Cek Status Pending
                $json_pending = $r['pending_json'];
                $is_pending   = !empty($json_pending);
                
                // Jika pending, ambil nilai stok yg diajukan dari JSON
                $stok_pending = 0;
                if($is_pending) {
                    $data_pending = json_decode($json_pending, true);
                    $stok_pending = $data_pending['stok_baru'] ?? 0;
                }
                
                // Class CSS Card
                $class_card = $is_pending ? 'pending' : '';
        ?>
        
        <div class="card-item relative item-row <?= $class_card ?>" id="card-<?= $r['id'] ?>" data-nama="<?= strtolower($r['nama_barang']) ?> <?= strtolower($r['kode_barang']) ?>">
            
            <?php if($is_pending): ?>
                <div class="badge-pending" id="badge-pending-<?= $r['id'] ?>">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> MENUNGGU ACC (<?= $stok_pending ?>)
                </div>
            <?php else: ?>
                <div class="badge-pending hidden" id="badge-pending-<?= $r['id'] ?>"></div>
            <?php endif; ?>

            <div class="mb-4">
                <h3 class="font-bold text-slate-800 text-[15px] leading-snug mb-1"><?= $r['nama_barang'] ?></h3>
                <div class="flex gap-2">
                    <span class="bg-indigo-50 text-indigo-600 text-[10px] font-bold px-2 py-0.5 rounded border border-indigo-100 font-mono"><?= $r['kode_barang'] ?></span>
                    <span class="bg-gray-50 text-gray-500 text-[10px] font-bold px-2 py-0.5 rounded border border-gray-100"><?= $r['kategori'] ?></span>
                </div>
            </div>

            <form onsubmit="simpanData(event, <?= $r['id'] ?>)" id="form-<?= $r['id'] ?>">
                <div class="flex gap-4">
                    
                    <div class="flex-1">
                        <div class="flex justify-between items-center">
                            <label class="label-kecil">STOK FISIK</label>
                            <div class="badge-sistem <?= $bg_badge ?>">
                                Sistem: <?= $stok_db ?>
                            </div>
                        </div>
                        <input type="number" step="0.01" name="stok" id="stok-<?= $r['id'] ?>" 
                               value="<?= $stok_db ?>" 
                               class="input-besar bg-slate-50 focus:bg-white" 
                               placeholder="0"
                               <?= $is_pending ? 'disabled' : '' ?>>
                        <div class="text-right text-[10px] text-slate-400 mt-1 font-bold"><?= $r['satuan'] ?></div>
                    </div>

                    <div class="flex-1">
                        <label class="label-kecil text-green-600">HARGA JUAL</label>
                        <div class="badge-sistem opacity-0 mb-2">Spacer</div> 
                        <input type="number" name="harga" id="harga-<?= $r['id'] ?>" 
                               value="<?= (float)$r['harga_jual'] ?>" 
                               class="input-besar text-green-700 bg-green-50/50 border-green-200 focus:border-green-500 focus:bg-white" 
                               placeholder="0"
                               <?= $is_pending ? 'disabled' : '' ?>>
                    </div>
                </div>

                <button type="submit" id="btn-<?= $r['id'] ?>" class="btn-simpan" <?= $is_pending ? 'disabled' : '' ?>>
                    <?php if($is_pending): ?>
                        <i class="fa-solid fa-lock"></i> SEDANG DIPROSES
                    <?php else: ?>
                        <i class="fa-solid fa-paper-plane"></i> KIRIM PERUBAHAN
                    <?php endif; ?>
                </button>
            </form>

            <div id="loading-<?= $r['id'] ?>" class="absolute inset-0 bg-white/90 hidden flex-col items-center justify-center z-10 rounded-xl backdrop-blur-[1px]">
                <i class="fa-solid fa-circle-notch fa-spin text-3xl text-indigo-600 mb-2"></i>
                <span class="text-xs font-bold text-indigo-800 animate-pulse">Mengirim Request...</span>
            </div>
        </div>
        <?php 
            endwhile; 
        } else {
            echo "<div class='text-center py-16 text-slate-400'><i class='fa-solid fa-box-open text-5xl mb-3 opacity-30'></i><p class='font-medium'>Belum ada data barang.</p></div>";
        }
        ?>
    </div>

    <div id="msgKosong" class="hidden text-center py-16 text-slate-400">
        <i class="fa-solid fa-magnifying-glass text-4xl mb-3 opacity-30"></i>
        <p>Barang tidak ditemukan.</p>
    </div>
</div>

<script>
// 1. PENCARIAN REALTIME
function filterList() {
    let keyword = document.getElementById('cariBarang').value.toLowerCase();
    let items = document.getElementsByClassName('item-row');
    let visibleCount = 0;

    for (let i = 0; i < items.length; i++) {
        let nama = items[i].getAttribute('data-nama');
        if (nama.includes(keyword)) {
            items[i].style.display = "block";
            visibleCount++;
        } else {
            items[i].style.display = "none";
        }
    }
    document.getElementById('msgKosong').classList.toggle('hidden', visibleCount > 0);
}

// 2. SIMPAN DATA (AJAX ke Halaman Ini Sendiri)
function simpanData(e, id) {
    e.preventDefault();
    
    let stokBaru = document.getElementById('stok-' + id).value;
    let hargaBaru = document.getElementById('harga-' + id).value;
    let loading = document.getElementById('loading-' + id);
    let card = document.getElementById('card-' + id);
    let btn = document.getElementById('btn-' + id);
    let badge = document.getElementById('badge-pending-' + id);
    
    // Tampilkan Loading
    loading.classList.remove('hidden');
    loading.style.display = 'flex';

    let fd = new FormData();
    fd.append('id', id);
    fd.append('stok', stokBaru);
    fd.append('harga', hargaBaru);

    // KIRIM REQUEST KE HALAMAN INI SENDIRI VIA PARAMETER GET
    fetch('index.php?page=update_stok_mobile&ajax_action=update', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json()) 
    .then(data => {
        // Sembunyikan Loading
        loading.classList.add('hidden');
        loading.style.display = 'none';
        
        if(data.status === 'success') {
            // --- UPDATE TAMPILAN TANPA RELOAD (UX TRICK) ---
            <?php if($_SESSION['role'] == 'po'): ?>
                // 1. Ubah Style Card jadi Kuning (Pending)
                card.classList.add('pending');
                
                // 2. Munculkan Badge 'MENUNGGU ACC'
                badge.classList.remove('hidden');
                badge.innerHTML = '<i class="fa-solid fa-hourglass-half mr-1"></i> MENUNGGU ACC (' + stokBaru + ')';
                
                // 3. Disable Input & Tombol
                document.getElementById('stok-' + id).disabled = true;
                document.getElementById('harga-' + id).disabled = true;
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-lock"></i> SEDANG DIPROSES';
                
                alert('Request stok berhasil dikirim ke Manager!');
            <?php else: ?>
                alert('Stok Berhasil Diupdate!');
            <?php endif; ?>
            
        } else {
            alert('Gagal: ' + data.msg);
        }
    })
    .catch(err => {
        loading.classList.add('hidden');
        loading.style.display = 'none';
        console.error(err);
        alert('Koneksi Gagal! Cek jaringan Anda.');
    });
}
</script>