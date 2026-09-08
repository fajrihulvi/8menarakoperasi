<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// CEK AKSES: Hanya Admin
wajib_akses('pengaturan');

$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1;

// ==========================================
// 1. PROSES SIMPAN PENGATURAN
// ==========================================
if(isset($_POST['simpan_pengaturan'])) {
    $nama       = mysqli_real_escape_string($conn, $_POST['nama']);
    $alamat     = mysqli_real_escape_string($conn, $_POST['alamat']);
    $telp       = mysqli_real_escape_string($conn, $_POST['telp']);
    $bank       = mysqli_real_escape_string($conn, $_POST['bank']);
    $norek      = mysqli_real_escape_string($conn, $_POST['norek']);
    $an         = mysqli_real_escape_string($conn, $_POST['an']);
    $pajak      = $_POST['pajak'];
    $api_gemini = mysqli_real_escape_string($conn, $_POST['api_key_gemini']); 
    
    // Upload Logo
    $logo_query = "";
    if(!empty($_FILES['logo']['name'])) {
        $nama_file   = $_FILES['logo']['name'];
        $lokasi_file = $_FILES['logo']['tmp_name'];
        $ext         = pathinfo($nama_file, PATHINFO_EXTENSION);
        // Nama file unik per toko
        $nama_baru   = "logo_" . time() . "_" . $id_usaha_aktif . "." . $ext;
        
        if (!file_exists('assets/img')) { mkdir('assets/img', 0777, true); }
        move_uploaded_file($lokasi_file, "assets/img/" . $nama_baru);
        $logo_query  = ", logo='$nama_baru'";
    }

    // Cek data toko ini sudah ada atau belum
    $cek = mysqli_query($conn, "SELECT id FROM pengaturan WHERE id_usaha = '$id_usaha_aktif'");
    
    if(mysqli_num_rows($cek) > 0) {
        // Update
        $q = "UPDATE pengaturan SET 
              nama_perusahaan='$nama', 
              alamat='$alamat', 
              no_telp='$telp', 
              nama_bank='$bank', 
              no_rek='$norek', 
              atas_nama_rek='$an',
              pajak_persen='$pajak',
              api_key_gemini='$api_gemini'
              $logo_query 
              WHERE id_usaha='$id_usaha_aktif'";
    } else {
        // Insert Baru (Jika toko baru dibuat)
        $logo_val = isset($nama_baru) ? $nama_baru : 'default.png';
        $q = "INSERT INTO pengaturan (id_usaha, nama_perusahaan, alamat, no_telp, nama_bank, no_rek, atas_nama_rek, pajak_persen, api_key_gemini, logo)
              VALUES ('$id_usaha_aktif', '$nama', '$alamat', '$telp', '$bank', '$norek', '$an', '$pajak', '$api_gemini', '$logo_val')";
    }

    if(mysqli_query($conn, $q)) {
        catat_log($conn, "Edit Pengaturan", "Mengubah pengaturan toko ID: $id_usaha_aktif");
        echo "<script>alert('Pengaturan Berhasil Disimpan!'); window.location='index.php?page=pengaturan';</script>";
    } else {
        echo "<script>alert('Gagal: ".mysqli_error($conn)."');</script>";
    }
}

// ==========================================
// 2. PROSES RESET DATA (PER TOKO)
// ==========================================
if(isset($_POST['reset_transaksi'])) {
    
    // 1. Hapus Detail Transaksi (Join ke Header yg punya id_usaha ini)
    $q1 = "DELETE d FROM transaksi_detail d 
           JOIN transaksi t ON d.no_faktur = t.no_faktur 
           WHERE t.id_usaha = '$id_usaha_aktif'";
    mysqli_query($conn, $q1);

    // 2. Hapus Header Transaksi
    $q2 = "DELETE FROM transaksi WHERE id_usaha = '$id_usaha_aktif'";
    mysqli_query($conn, $q2);

    // 3. Hapus Detail Pesanan
    $q3 = "DELETE d FROM pesanan_detail d 
           JOIN pesanan p ON d.id_pesanan = p.id 
           WHERE p.id_usaha = '$id_usaha_aktif'";
    mysqli_query($conn, $q3);

    // 4. Hapus Header Pesanan
    $q4 = "DELETE FROM pesanan WHERE id_usaha = '$id_usaha_aktif'";
    mysqli_query($conn, $q4);

    // 5. Hapus Jurnal Umum (Accounting)
    $q5 = "DELETE FROM jurnal_umum WHERE id_usaha = '$id_usaha_aktif'";
    mysqli_query($conn, $q5);

    // 6. Hapus Log Aktivitas (Opsional, agar bersih total)
    // mysqli_query($conn, "DELETE FROM log_aktivitas WHERE user_id IN (SELECT id FROM users WHERE id_usaha='$id_usaha_aktif')");

    catat_log($conn, "RESET DATA", "Menghapus SELURUH data transaksi toko ID: $id_usaha_aktif");
    
    echo "<script>alert('SEMUA DATA TRANSAKSI TOKO INI BERHASIL DIHAPUS!'); window.location='index.php?page=pengaturan';</script>";
}

// AMBIL DATA SAAT INI
$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha = '$id_usaha_aktif'"));
if(!$data) {
    $data = ['nama_perusahaan'=>'','alamat'=>'','no_telp'=>'','nama_bank'=>'','no_rek'=>'','atas_nama_rek'=>'','logo'=>'','pajak_persen'=>0, 'api_key_gemini'=>''];
}
?>

<div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2">Pengaturan Toko</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <div class="space-y-4">
                <h3 class="font-bold text-indigo-600 border-b pb-1">Identitas Usaha</h3>
                
                <div>
                    <label class="block text-sm font-bold text-gray-600 mb-1">Nama Perusahaan / Toko</label>
                    <input type="text" name="nama" value="<?= $data['nama_perusahaan'] ?>" class="w-full border p-2 rounded focus:ring-2 focus:ring-indigo-500" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-600 mb-1">Alamat Lengkap</label>
                    <textarea name="alamat" class="w-full border p-2 rounded h-24 focus:ring-2 focus:ring-indigo-500" required><?= $data['alamat'] ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-600 mb-1">No. Telepon / HP</label>
                    <input type="text" name="telp" value="<?= $data['no_telp'] ?>" class="w-full border p-2 rounded">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-600 mb-1">Logo Toko</label>
                    <div class="flex items-center gap-4 p-3 border border-dashed rounded bg-gray-50">
                        <?php if(!empty($data['logo'])): ?>
                            <img src="assets/img/<?= $data['logo'] ?>" class="h-16 object-contain bg-white border p-1 rounded">
                        <?php endif; ?>
                        <input type="file" name="logo" class="text-sm text-gray-500 w-full">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Format: JPG/PNG. Biarkan kosong jika tidak ingin ganti.</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="space-y-4">
                    <h3 class="font-bold text-green-600 border-b pb-1">Keuangan & Invoice</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-600 mb-1">Nama Bank</label>
                            <input type="text" name="bank" value="<?= $data['nama_bank'] ?>" class="w-full border p-2 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-600 mb-1">Atas Nama</label>
                            <input type="text" name="an" value="<?= $data['atas_nama_rek'] ?>" class="w-full border p-2 rounded">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">Nomor Rekening</label>
                        <input type="text" name="norek" value="<?= $data['no_rek'] ?>" class="w-full border p-2 rounded font-mono">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">Pajak / PPN (%)</label>
                        <input type="number" name="pajak" value="<?= $data['pajak_persen'] ?>" class="w-full border p-2 rounded w-24">
                        <span class="text-xs text-gray-400 ml-2">* Biarkan 0 jika tidak pakai.</span>
                    </div>
                </div>

                <div class="space-y-4 pt-4 border-t">
                    <h3 class="font-bold text-purple-600 border-b pb-1"><i class="fa-solid fa-robot mr-2"></i>Integrasi AI</h3>
                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-1">Gemini API Key</label>
                        <input type="text" name="api_key_gemini" value="<?= $data['api_key_gemini'] ?? '' ?>" class="w-full border p-2 rounded bg-purple-50 font-mono text-sm" placeholder="Paste API Key...">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t text-right">
            <button type="submit" name="simpan_pengaturan" class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-indigo-700 shadow-lg transition transform hover:scale-105">
                <i class="fa-solid fa-save mr-2"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<div class="bg-red-50 rounded-xl shadow-sm p-6 border border-red-200">
    <div class="flex items-start gap-4">
        <div class="bg-red-100 p-3 rounded-full text-red-600">
            <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-red-700 mb-1">Zona Bahaya (Reset Data)</h3>
            <p class="text-red-600 text-sm mb-4">
                Fitur ini akan menghapus <b>SEMUA RIWAYAT TRANSAKSI & PESANAN</b> pada toko ini saja.<br>
                Data master (Barang, Supplier, Pelanggan) <b>TIDAK</b> akan terhapus.<br>
                Gunakan fitur ini jika ingin memulai pembukuan dari nol.
            </p>
            
            <form method="POST" onsubmit="return confirm('PERINGATAN!\n\nApakah Anda yakin ingin MENGHAPUS SEMUA DATA TRANSAKSI untuk toko ini?\nTindakan ini TIDAK BISA DIBATALKAN.\n\nKlik OK untuk melanjutkan.')">
                <button type="submit" name="reset_transaksi" class="bg-red-600 text-white px-6 py-2 rounded font-bold hover:bg-red-700 transition shadow-sm border border-red-700">
                    <i class="fa-solid fa-trash-can mr-2"></i> RESET DATA TOKO INI
                </button>
            </form>
        </div>
    </div>
</div>