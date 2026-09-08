<?php
wajib_akses('konversi_gizi');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'config/koneksi.php';

// CEK AKSES
$role = $_SESSION['role'] ?? '';
$id_usaha = $_SESSION['id_usaha'] ?? 1;

if (!in_array($role, ['admin', 'ahli_gizi', 'chef'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='index.php';</script>";
    exit;
}

// PROSES TAMBAH RUMUS (Hanya Ahli Gizi & Admin)
if(isset($_POST['simpan_rumus'])) {
    $bahan  = mysqli_real_escape_string($conn, $_POST['bahan']);
    $olahan = mysqli_real_escape_string($conn, $_POST['olahan']);
    $faktor = $_POST['faktor']; // Contoh: 2.5
    
    mysqli_query($conn, "INSERT INTO rumus_konversi (id_usaha, nama_bahan_baku, nama_hasil_olahan, faktor_konversi) VALUES ('$id_usaha', '$bahan', '$olahan', '$faktor')");
    echo "<script>window.location='index.php?page=konversi_gizi';</script>";
}

// PROSES HAPUS RUMUS
if(isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM rumus_konversi WHERE id='$id' AND id_usaha='$id_usaha'");
    echo "<script>window.location='index.php?page=konversi_gizi';</script>";
}
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-scale-balanced text-indigo-600 mr-2"></i> Kalkulator Dapur MBG</h2>
        <p class="text-gray-500 text-sm">Mode Akses: <span class="font-bold uppercase text-indigo-600"><?= str_replace('_',' ', $role) ?></span></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <?php if($role == 'ahli_gizi' || $role == 'admin'): ?>
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl shadow-lg p-6 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="font-bold text-xl mb-1"><i class="fa-solid fa-user-doctor mr-2"></i> Panel Ahli Gizi</h3>
                    <p class="text-emerald-100 text-xs mb-4">Hitung kebutuhan belanja bahan baku berdasarkan target porsi.</p>
                    
                    <div class="bg-white/10 backdrop-blur-md p-4 rounded-xl border border-white/20">
                        <label class="block text-xs font-bold uppercase mb-1">Pilih Target Olahan</label>
                        <select id="gizi_item" class="w-full text-gray-800 p-2 rounded mb-3" onchange="hitungGizi()">
                            <option value="0">-- Pilih Menu --</option>
                            <?php 
                            $q1 = mysqli_query($conn, "SELECT * FROM rumus_konversi WHERE id_usaha='$id_usaha'");
                            while($r = mysqli_fetch_assoc($q1)) {
                                echo "<option value='{$r['faktor_konversi']}'>Ingin membuat: {$r['nama_hasil_olahan']} (Dari: {$r['nama_bahan_baku']})</option>";
                            }
                            ?>
                        </select>

                        <label class="block text-xs font-bold uppercase mb-1">Target Jumlah Matang (Kg)</label>
                        <input type="number" id="gizi_target" class="w-full text-gray-800 p-2 rounded mb-3 font-bold" placeholder="Contoh: 10 Kg Nasi" onkeyup="hitungGizi()">

                        <div class="mt-4 pt-4 border-t border-white/30 text-center">
                            <span class="text-sm">Anda Membutuhkan Bahan Baku Mentah:</span>
                            <div class="text-3xl font-bold text-yellow-300 mt-1" id="gizi_hasil">0 Kg</div>
                        </div>
                    </div>
                </div>
                <i class="fa-solid fa-carrot absolute -bottom-4 -right-4 text-9xl text-white opacity-10"></i>
            </div>
            <?php endif; ?>

            <?php if($role == 'chef' || $role == 'admin'): ?>
            <div class="bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl shadow-lg p-6 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="font-bold text-xl mb-1"><i class="fa-solid fa-fire-burner mr-2"></i> Panel Chef</h3>
                    <p class="text-orange-100 text-xs mb-4">Estimasi hasil masakan dari stok bahan baku yang tersedia.</p>
                    
                    <div class="bg-white/10 backdrop-blur-md p-4 rounded-xl border border-white/20">
                        <label class="block text-xs font-bold uppercase mb-1">Bahan Baku Tersedia</label>
                        <select id="chef_item" class="w-full text-gray-800 p-2 rounded mb-3" onchange="hitungChef()">
                            <option value="0">-- Pilih Bahan --</option>
                            <?php 
                            // Reset pointer query untuk dipakai lagi
                            if(isset($q1)) mysqli_data_seek($q1, 0); 
                            else $q1 = mysqli_query($conn, "SELECT * FROM rumus_konversi WHERE id_usaha='$id_usaha'");
                            
                            while($r = mysqli_fetch_assoc($q1)) {
                                echo "<option value='{$r['faktor_konversi']}'>Stok: {$r['nama_bahan_baku']} (Akan jadi: {$r['nama_hasil_olahan']})</option>";
                            }
                            ?>
                        </select>

                        <label class="block text-xs font-bold uppercase mb-1">Jumlah Bahan Mentah (Kg)</label>
                        <input type="number" id="chef_input" class="w-full text-gray-800 p-2 rounded mb-3 font-bold" placeholder="Contoh: 5 Kg Beras" onkeyup="hitungChef()">

                        <div class="mt-4 pt-4 border-t border-white/30 text-center">
                            <span class="text-sm">Estimasi Hasil Olahan Matang:</span>
                            <div class="text-3xl font-bold text-yellow-300 mt-1" id="chef_hasil">0 Kg</div>
                        </div>
                    </div>
                </div>
                <i class="fa-solid fa-utensils absolute -bottom-4 -right-4 text-9xl text-white opacity-10"></i>
            </div>
            <?php endif; ?>

        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 h-full flex flex-col">
                <div class="p-4 border-b bg-slate-50 rounded-t-xl">
                    <h3 class="font-bold text-slate-700">Rumus Konversi</h3>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                    <?php if($role == 'ahli_gizi' || $role == 'admin'): ?>
                    <form method="POST" class="mb-6 bg-indigo-50 p-3 rounded-lg border border-indigo-100">
                        <h4 class="text-xs font-bold text-indigo-700 mb-2 uppercase">Tambah Data Baru</h4>
                        <input type="text" name="bahan" class="w-full mb-2 text-xs p-2 border rounded" placeholder="Nama Bahan Baku (Mentah)" required>
                        <input type="text" name="olahan" class="w-full mb-2 text-xs p-2 border rounded" placeholder="Nama Hasil (Matang)" required>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" name="faktor" class="w-20 text-xs p-2 border rounded" placeholder="Rasio" title="1 Kg Mentah jadi berapa Kg Matang?" required>
                            <button type="submit" name="simpan_rumus" class="flex-1 bg-indigo-600 text-white text-xs rounded font-bold hover:bg-indigo-700">Simpan</button>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1">Ex: 1kg Beras jadi 2.2kg Nasi, isi Rasio: 2.2</p>
                    </form>
                    <?php endif; ?>

                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-100 text-gray-600 font-bold uppercase">
                            <tr>
                                <th class="p-2">Bahan</th>
                                <th class="p-2">Hasil</th>
                                <th class="p-2 text-center">Faktor</th>
                                <?php if($role != 'chef') echo '<th class="p-2"></th>'; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php
                            if(isset($q1)) mysqli_data_seek($q1, 0);
                            while($d = mysqli_fetch_assoc($q1)):
                            ?>
                            <tr>
                                <td class="p-2 font-bold"><?= $d['nama_bahan_baku'] ?></td>
                                <td class="p-2 text-gray-600"><?= $d['nama_hasil_olahan'] ?></td>
                                <td class="p-2 text-center font-mono bg-yellow-50 text-yellow-700 rounded">x<?= $d['faktor_konversi'] ?></td>
                                <?php if($role == 'ahli_gizi' || $role == 'admin'): ?>
                                <td class="p-2 text-right">
                                    <a href="index.php?page=konversi_gizi&hapus=<?= $d['id'] ?>" class="text-red-400 hover:text-red-600" onclick="return confirm('Hapus rumus ini?')"><i class="fa-solid fa-trash"></i></a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// LOGIKA AHLI GIZI (Mencari Kebutuhan Mentah)
// Rumus: Mentah = Target Matang / Faktor
function hitungGizi() {
    let faktor = parseFloat(document.getElementById('gizi_item').value) || 0;
    let target = parseFloat(document.getElementById('gizi_target').value) || 0;
    
    if(faktor > 0 && target > 0) {
        let butuh = target / faktor;
        document.getElementById('gizi_hasil').innerText = butuh.toFixed(2) + " Kg";
    } else {
        document.getElementById('gizi_hasil').innerText = "0 Kg";
    }
}

// LOGIKA CHEF (Mencari Estimasi Hasil)
// Rumus: Matang = Mentah * Faktor
function hitungChef() {
    let faktor = parseFloat(document.getElementById('chef_item').value) || 0;
    let input  = parseFloat(document.getElementById('chef_input').value) || 0;
    
    if(faktor > 0 && input > 0) {
        let hasil = input * faktor;
        document.getElementById('chef_hasil').innerText = hasil.toFixed(2) + " Kg";
    } else {
        document.getElementById('chef_hasil').innerText = "0 Kg";
    }
}
</script>