<?php
// pages/panel_gizi.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'config/koneksi.php';

// CEK AKSES
// PERBAIKAN: Menambahkan 'chef' agar tidak ditolak sistem
wajib_akses('panel_gizi');
?>

<div class="max-w-2xl mx-auto mt-6">
    <div class="bg-white p-6 rounded-t-2xl border-b shadow-sm">
        <h2 class="text-2xl font-bold text-emerald-600 flex items-center gap-2">
            <i class="fa-solid fa-user-doctor"></i> Panel Ahli Gizi
        </h2>
        <p class="text-gray-500 text-sm">Hitung kebutuhan belanja bahan mentah dari target porsi matang.</p>
    </div>

    <div class="bg-emerald-50 p-8 rounded-b-2xl shadow-lg border border-emerald-100">
        
        <label class="block text-sm font-bold text-emerald-800 mb-2">Mau Buat Apa?</label>
        <select id="gizi_item" class="w-full p-4 rounded-xl border border-emerald-200 text-lg mb-6 shadow-sm focus:ring-2 focus:ring-emerald-500 outline-none" onchange="hitungGizi()">
            <option value="0">-- Pilih Menu --</option>
            <?php 
            // PERBAIKAN: Mengambil dari tabel konversi_satuan (Sesuai Master Konversi)
            $q = mysqli_query($conn, "SELECT * FROM konversi_satuan ORDER BY bahan_matang ASC");
            while($r = mysqli_fetch_assoc($q)) {
                // Value adalah faktor konversi (jumlah hasil per 1 mentah)
                echo "<option value='{$r['jumlah_hasil']}'>{$r['bahan_matang']} (Bahan: {$r['bahan_mentah']})</option>";
            }
            ?>
        </select>

        <label class="block text-sm font-bold text-emerald-800 mb-2">Target Jumlah Matang (Satuan Jadi)</label>
        <div class="relative mb-8">
            <input type="number" id="gizi_target" class="w-full p-4 pl-6 rounded-xl border border-emerald-200 text-2xl font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="0" onkeyup="hitungGizi()">
            <span class="absolute right-6 top-5 text-gray-400 font-bold">Unit</span>
        </div>

        <div class="bg-white p-6 rounded-xl border-2 border-emerald-200 text-center shadow-inner">
            <p class="text-sm text-gray-500 uppercase tracking-wide font-bold mb-2">Anda Harus Request ke Gudang:</p>
            <div class="text-5xl font-extrabold text-emerald-600" id="gizi_hasil">0</div>
            <p class="text-lg font-bold text-emerald-400 mt-1">Unit Mentah</p>
        </div>

    </div>
</div>

<script>
function hitungGizi() {
    let faktor = parseFloat(document.getElementById('gizi_item').value) || 0;
    let target = parseFloat(document.getElementById('gizi_target').value) || 0;
    
    if(faktor > 0 && target > 0) {
        // RUMUS: Target Matang / Faktor Konversi = Kebutuhan Mentah
        // Contoh: Target 10 Porsi / (2 Porsi per 1 Kg) = 5 Kg Mentah
        let butuh = target / faktor; 
        document.getElementById('gizi_hasil').innerText = butuh.toLocaleString('id-ID', {maximumFractionDigits:2});
    } else {
        document.getElementById('gizi_hasil').innerText = "0";
    }
}
</script>