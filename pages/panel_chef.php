<?php
// pages/panel_chef.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'config/koneksi.php';

// CEK AKSES
wajib_akses('panel_chef');
?>

<div class="max-w-2xl mx-auto mt-6">
    <div class="bg-white p-6 rounded-t-2xl border-b shadow-sm">
        <h2 class="text-2xl font-bold text-orange-600 flex items-center gap-2">
            <i class="fa-solid fa-fire-burner"></i> Panel Chef
        </h2>
        <p class="text-gray-500 text-sm">Estimasi hasil masakan matang dari stok mentah yang tersedia.</p>
    </div>

    <div class="bg-orange-50 p-8 rounded-b-2xl shadow-lg border border-orange-100">
        
        <label class="block text-sm font-bold text-orange-800 mb-2">Punya Bahan Apa?</label>
        <select id="chef_item" class="w-full p-4 rounded-xl border border-orange-200 text-lg mb-6 shadow-sm focus:ring-2 focus:ring-orange-500 outline-none" onchange="hitungChef()">
            <option value="0">-- Pilih Bahan Baku --</option>
            <?php 
            // PERBAIKAN: Mengambil dari tabel konversi_satuan (Sesuai Master Konversi)
            $q = mysqli_query($conn, "SELECT * FROM konversi_satuan ORDER BY bahan_mentah ASC");
            while($r = mysqli_fetch_assoc($q)) {
                // Logic: 1 Mentah jadi Berapa Matang (jumlah_hasil)
                echo "<option value='{$r['jumlah_hasil']}'>{$r['bahan_mentah']} (Akan jadi: {$r['bahan_matang']})</option>";
            }
            ?>
        </select>

        <label class="block text-sm font-bold text-orange-800 mb-2">Jumlah Mentah Tersedia (Satuan Mentah)</label>
        <div class="relative mb-8">
            <input type="number" id="chef_input" class="w-full p-4 pl-6 rounded-xl border border-orange-200 text-2xl font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-orange-500 outline-none" placeholder="0" onkeyup="hitungChef()">
            <span class="absolute right-6 top-5 text-gray-400 font-bold">Unit</span>
        </div>

        <div class="bg-white p-6 rounded-xl border-2 border-orange-200 text-center shadow-inner">
            <p class="text-sm text-gray-500 uppercase tracking-wide font-bold mb-2">Estimasi Hasil Matang:</p>
            <div class="text-5xl font-extrabold text-orange-600" id="chef_hasil">0</div>
            <p class="text-lg font-bold text-orange-400 mt-1">Hasil Jadi</p>
        </div>

    </div>
</div>

<script>
function hitungChef() {
    let faktor = parseFloat(document.getElementById('chef_item').value) || 0;
    let input  = parseFloat(document.getElementById('chef_input').value) || 0;
    
    if(faktor > 0 && input > 0) {
        let hasil = input * faktor; // RUMUS: Mentah * Faktor Konversi
        document.getElementById('chef_hasil').innerText = hasil.toLocaleString('id-ID', {maximumFractionDigits:2});
    } else {
        document.getElementById('chef_hasil').innerText = "0";
    }
}
</script>