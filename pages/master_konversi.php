<?php
wajib_akses('master_konversi');

// pages/master_konversi.php

// 1. CEK AKSES
$role_saat_ini = $_SESSION['role'] ?? '';
$akses_boleh = ['admin', 'chef', 'ahli_gizi'];

if (!in_array($role_saat_ini, $akses_boleh)) {
    echo "<script>alert('Akses Ditolak!'); window.location='index.php';</script>";
    exit;
}

// 2. PROSES TAMBAH DATA (Yield Factor) - Hanya Chef & Admin
if (isset($_POST['simpan_yield'])) {
    if($role_saat_ini == 'ahli_gizi') {
        echo "<script>alert('Maaf, Ahli Gizi hanya mode Read Only.');</script>";
    } else {
        $bahan_mentah = mysqli_real_escape_string($conn, $_POST['bahan_mentah']);
        $satuan_mentah = $_POST['satuan_mentah'];
        
        $bahan_matang = mysqli_real_escape_string($conn, $_POST['bahan_matang']);
        $jumlah_hasil = $_POST['jumlah_hasil'];
        $satuan_matang = $_POST['satuan_matang'];
        
        $q = mysqli_query($conn, "INSERT INTO konversi_satuan (bahan_mentah, satuan_mentah, bahan_matang, jumlah_hasil, satuan_matang) 
                                  VALUES ('$bahan_mentah', '$satuan_mentah', '$bahan_matang', '$jumlah_hasil', '$satuan_matang')");
        
        if($q) echo "<script>alert('Rumus Konversi Berhasil Disimpan!'); window.location='index.php?page=master_konversi';</script>";
    }
}

// 3. PROSES HAPUS
if (isset($_GET['hapus']) && in_array($role_saat_ini, ['admin', 'chef'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM konversi_satuan WHERE id='$id'");
    echo "<script>window.location='index.php?page=master_konversi';</script>";
}
?>

<div class="min-h-screen bg-slate-50 p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manajemen Konversi & Yield</h1>
                <p class="text-slate-500 text-sm">Rumus perubahan dari <b class="text-indigo-600">Bahan Mentah</b> menjadi <b class="text-emerald-600">Bahan Matang</b>.</p>
            </div>

            <?php if(in_array($role_saat_ini, ['admin', 'chef'])): ?>
            <button onclick="toggleModal('modalYield')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-lg hover:shadow-indigo-200 transition-all flex items-center gap-2">
                <i class="fa-solid fa-fire-burner"></i> Buat Rumus Baru
            </button>
            <?php else: ?>
            <div class="bg-orange-100 text-orange-600 px-4 py-2 rounded-lg text-xs font-bold border border-orange-200">
                <i class="fa-solid fa-lock mr-1"></i> Mode Baca (Read Only)
            </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <?php 
            $q_data = mysqli_query($conn, "SELECT * FROM konversi_satuan ORDER BY bahan_mentah ASC");
            if(mysqli_num_rows($q_data) > 0):
                while($r = mysqli_fetch_assoc($q_data)):
            ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-6 hover:shadow-md transition-all group relative overflow-hidden">
                
                <div class="flex items-center gap-4 flex-1">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xl shrink-0">
                        <i class="fa-solid fa-wheat-awn"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bahan Mentah</p>
                        <h3 class="font-bold text-slate-800 text-lg">1 <?= $r['satuan_mentah'] ?> <?= $r['bahan_mentah'] ?></h3>
                    </div>
                </div>

                <div class="flex flex-col items-center justify-center text-indigo-500">
                    <i class="fa-solid fa-arrow-right-long text-2xl group-hover:scale-125 transition-transform"></i>
                    <span class="text-[10px] font-bold bg-indigo-50 px-2 py-0.5 rounded-full mt-1">Diproses Menjadi</span>
                </div>

                <div class="flex items-center gap-4 flex-1 justify-end md:text-right">
                    <div>
                        <p class="text-xs font-bold text-emerald-500 uppercase tracking-wider">Hasil (Matang)</p>
                        <h3 class="font-bold text-emerald-700 text-2xl"><?= (float)$r['jumlah_hasil'] ?> <span class="text-base"><?= $r['satuan_matang'] ?></span></h3>
                        <p class="text-sm font-semibold text-slate-700"><?= $r['bahan_matang'] ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-xl shrink-0">
                        <i class="fa-solid fa-bowl-food"></i>
                    </div>
                </div>

                <?php if(in_array($role_saat_ini, ['admin', 'chef'])): ?>
                <a href="index.php?page=master_konversi&hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus rumus ini?')" class="absolute top-2 right-2 text-slate-300 hover:text-red-500 transition p-2">
                    <i class="fa-solid fa-trash-can"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endwhile; else: ?>
                <div class="bg-white p-12 rounded-2xl border border-dashed border-slate-300 text-center">
                    <i class="fa-solid fa-scale-unbalanced text-4xl text-slate-300 mb-4"></i>
                    <p class="text-slate-500 font-medium">Belum ada data konversi.</p>
                    <p class="text-slate-400 text-sm">Silakan Chef menambahkan rumus baru.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="modalYield" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="toggleModal('modalYield')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden animate-fade-in">
        
        <div class="bg-indigo-600 p-4 flex justify-between items-center text-white">
            <h3 class="text-lg font-bold"><i class="fa-solid fa-fire-burner mr-2"></i> Buat Rumus Konversi Baru</h3>
            <button onclick="toggleModal('modalYield')" class="hover:bg-indigo-700 p-1 rounded-full"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>

        <form method="POST" class="p-6">
            <div class="flex flex-col md:flex-row gap-6 items-center">
                
                <div class="flex-1 w-full bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div class="mb-3 text-center">
                        <span class="bg-slate-200 text-slate-600 px-3 py-1 rounded-full text-xs font-bold uppercase">Bahan Mentah</span>
                    </div>
                    
                    <label class="block text-xs font-bold text-slate-500 mb-1">Nama Bahan Mentah</label>
                    <input type="text" name="bahan_mentah" placeholder="Contoh: Beras" class="w-full px-3 py-2 border rounded-lg mb-3 focus:ring-2 focus:ring-indigo-500 outline-none" required>
                    
                    <label class="block text-xs font-bold text-slate-500 mb-1">Basis Satuan (Per 1 ...)</label>
                    <select name="satuan_mentah" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Kg">Kilogram (Kg)</option>
                        <option value="Liter">Liter (L)</option>
                        <option value="Ikat">Ikat</option>
                        <option value="Pcs">Pcs / Buah</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1 italic text-center">*Hitungan selalu per 1 satuan ini</p>
                </div>

                <div class="text-slate-300 text-2xl">
                    <i class="fa-solid fa-circle-arrow-right"></i>
                </div>

                <div class="flex-1 w-full bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                    <div class="mb-3 text-center">
                        <span class="bg-emerald-200 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold uppercase">Hasil Jadi (Matang)</span>
                    </div>
                    
                    <label class="block text-xs font-bold text-emerald-700 mb-1">Nama Bahan Jadi</label>
                    <input type="text" name="bahan_matang" placeholder="Contoh: Nasi Putih" class="w-full px-3 py-2 border border-emerald-300 rounded-lg mb-3 focus:ring-2 focus:ring-emerald-500 outline-none" required>
                    
                    <div class="flex gap-2">
                        <div class="w-1/2">
                            <label class="block text-xs font-bold text-emerald-700 mb-1">Jlh Hasil</label>
                            <input type="number" step="0.01" name="jumlah_hasil" placeholder="2.5" class="w-full px-3 py-2 border border-emerald-300 rounded-lg font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-500 outline-none" required>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-xs font-bold text-emerald-700 mb-1">Satuan</label>
                            <select name="satuan_matang" class="w-full px-3 py-2 border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="Kg">Kg</option>
                                <option value="Liter">Liter</option>
                                <option value="Porsi">Porsi</option>
                                <option value="Mangkok">Mangkok</option>
                                <option value="Gram">Gram</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <div class="mt-6 pt-4 border-t border-slate-100">
                <button type="submit" name="simpan_yield" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-xl hover:bg-indigo-700 shadow-lg hover:shadow-indigo-200 transition-all">
                    Simpan Rumus Konversi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModal(id) {
    const modal = document.getElementById(id);
    if (modal.classList.contains('hidden')) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}
</script>

<style>
    @keyframes fadeIn { from { opacity: 0; transform: translate(-50%, -45%); } to { opacity: 1; transform: translate(-50%, -50%); } }
    .animate-fade-in { animation: fadeIn 0.2s ease-out forwards; }
</style>