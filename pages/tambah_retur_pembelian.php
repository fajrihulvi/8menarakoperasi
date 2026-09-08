<?php
wajib_akses('tambah_retur_pembelian');

// pages/tambah_retur_pembelian.php

// HANDLE FORM SUBMIT
if(isset($_POST['simpan_retur'])) {
    tolak_jika_tidak_boleh('tambah', 'retur_pembelian', 'index.php?page=retur_pembelian');
    $tanggal     = $_POST['tanggal'];
    $supplier_id = $_POST['supplier_id'];
    $alasan      = mysqli_real_escape_string($conn, $_POST['alasan']);
    
    // Generate No Retur Otomatis (RET-TahunBulan-Urut)
    $today = date("Ym");
    $query = mysqli_query($conn, "SELECT max(no_retur) as last FROM retur_pembelian WHERE no_retur LIKE 'RET-$today%'");
    $data  = mysqli_fetch_assoc($query);
    $lastNo = $data['last'];
    $noUrut = (int) substr($lastNo, 10, 4);
    $noUrut++;
    $no_retur = "RET-" . $today . "-" . sprintf("%04s", $noUrut);

    // --- PROSES UPLOAD FOTO ---
    $nama_foto_baru = ""; // Default kosong jika tidak ada upload
    
    if(!empty($_FILES['bukti']['name'])) {
        $nama_file   = $_FILES['bukti']['name'];
        $tmp_file    = $_FILES['bukti']['tmp_name'];
        $ukuran_file = $_FILES['bukti']['size'];
        $tipe_file   = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        
        // 1. Validasi Ekstensi
        $ekstensi_boleh = ['jpg', 'jpeg', 'png'];
        if(!in_array($tipe_file, $ekstensi_boleh)) {
            echo "<script>alert('Gagal! Hanya boleh upload file JPG, JPEG, atau PNG.');</script>";
            // Stop proses jika file salah
            echo "<script>window.history.back();</script>"; 
            exit;
        }

        // 2. Validasi Ukuran (Maks 5MB)
        if($ukuran_file > 5000000) {
            echo "<script>alert('Ukuran file terlalu besar! Maksimal 5MB.');</script>";
            echo "<script>window.history.back();</script>";
            exit;
        }

        // 3. Rename File (Agar tidak bentrok)
        $nama_foto_baru = "retur_" . uniqid() . "." . $tipe_file;
        
        // 4. Pindahkan File ke Folder Tujuan
        $tujuan = "assets/uploads/retur/" . $nama_foto_baru;
        
        if(!move_uploaded_file($tmp_file, $tujuan)) {
            echo "<script>alert('Gagal mengupload gambar. Periksa permission folder assets/uploads/retur/');</script>";
            exit;
        }
    }
    // ---------------------------

    // Simpan Header Retur
    // Pastikan kolom di database bernama 'bukti_foto'
    $q_header = "INSERT INTO retur_pembelian (no_retur, tanggal, supplier_id, alasan, bukti_foto, id_usaha) 
                 VALUES ('$no_retur', '$tanggal', '$supplier_id', '$alasan', '$nama_foto_baru', '{$_SESSION['id_usaha']}')";
    
    if(mysqli_query($conn, $q_header)) {
        // Ambil ID yang baru dibuat
        $retur_id = mysqli_insert_id($conn);
        
        // Simpan Detail Barang (Looping Input)
        // Asumsi form detail menggunakan array name="barang_id[]" dan name="qty[]"
        if(isset($_POST['barang_id'])) {
            $barang_ids = $_POST['barang_id'];
            $qtys       = $_POST['qty'];

            for($i=0; $i < count($barang_ids); $i++) {
                $b_id = $barang_ids[$i];
                $qty  = $qtys[$i];

                if(!empty($b_id) && $qty > 0) {
                    mysqli_query($conn, "INSERT INTO retur_pembelian_detail (retur_id, barang_id, qty) VALUES ('$retur_id', '$b_id', '$qty')");
                    
                    // (Opsional) Kurangi Stok Gudang jika perlu
                    // mysqli_query($conn, "UPDATE barang SET stok = stok - $qty WHERE id='$b_id'");
                }
            }
        }

        echo "<script>alert('Retur berhasil disimpan!'); window.location='index.php?page=retur_pembelian';</script>";
    } else {
        echo "<script>alert('Gagal Simpan Database: ".mysqli_error($conn)."');</script>";
    }
}
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Tambah Retur Pembelian</h2>
    <a href="index.php?page=retur_pembelian" class="text-indigo-600 hover:underline text-sm"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal Retur</label>
            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded focus:outline-indigo-500" required>
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Supplier</label>
            <select name="supplier_id" class="w-full border p-2 rounded focus:outline-indigo-500 bg-white" required>
                <option value="">-- Pilih Supplier --</option>
                <?php 
                $qs = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama_supplier ASC");
                while($s = mysqli_fetch_assoc($qs)): 
                ?>
                <option value="<?= $s['id'] ?>"><?= $s['nama_supplier'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="mb-6">
        <label class="block text-sm font-bold text-gray-700 mb-2">Alasan Pengembalian</label>
        <textarea name="alasan" rows="2" class="w-full border p-2 rounded focus:outline-indigo-500" placeholder="Contoh: Barang rusak saat diterima, kemasan sobek..." required></textarea>
    </div>

    <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
        <label class="block text-sm font-bold text-blue-800 mb-2">
            <i class="fa-solid fa-camera mr-1"></i> Upload Bukti Foto (Opsional)
        </label>
        <input type="file" name="bukti" accept="image/png, image/jpeg, image/jpg" class="block w-full text-sm text-slate-500
          file:mr-4 file:py-2 file:px-4
          file:rounded-full file:border-0
          file:text-sm file:font-semibold
          file:bg-blue-600 file:text-white
          file:cursor-pointer hover:file:bg-blue-700
        "/>
        <p class="text-xs text-gray-500 mt-1">*Format: JPG/PNG. Maksimal 5MB.</p>
    </div>

    <hr class="mb-6">

    <div class="mb-4">
        <h4 class="font-bold text-gray-700 mb-2">Barang yang diretur:</h4>
        <div id="item-container">
            <div class="flex gap-2 mb-2 item-row">
                <select name="barang_id[]" class="flex-1 border p-2 rounded" required>
                    <option value="">-- Pilih Barang --</option>
                    <?php 
                    $qb = mysqli_query($conn, "SELECT * FROM barang ORDER BY nama_barang ASC");
                    // Simpan opsi barang di variabel biar hemat query saat tambah baris via JS
                    $opsi_barang = "";
                    while($b = mysqli_fetch_assoc($qb)){
                        $opsi_barang .= "<option value='{$b['id']}'>{$b['nama_barang']} (Stok: {$b['stok']})</option>";
                    }
                    echo $opsi_barang;
                    ?>
                </select>
                <input type="number" name="qty[]" placeholder="Qty" class="w-24 border p-2 rounded" min="1" required>
                <button type="button" onclick="hapusBaris(this)" class="bg-red-100 text-red-600 px-3 rounded hover:bg-red-200"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
        <button type="button" onclick="tambahBaris()" class="text-sm text-indigo-600 font-bold hover:underline">+ Tambah Barang Lain</button>
    </div>

    <div class="flex justify-end gap-4 mt-8">
        <a href="index.php?page=retur_pembelian" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-bold hover:bg-gray-300">Batal</a>
        <button type="submit" name="simpan_retur" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 shadow-lg">
            <i class="fa-solid fa-save mr-2"></i> Simpan Retur
        </button>
    </div>
</form>

<script>
    // Script Tambah Baris Barang
    function tambahBaris() {
        const container = document.getElementById('item-container');
        const row = document.createElement('div');
        row.className = 'flex gap-2 mb-2 item-row';
        row.innerHTML = `
            <select name="barang_id[]" class="flex-1 border p-2 rounded" required>
                <option value="">-- Pilih Barang --</option>
                <?= $opsi_barang // Gunakan opsi php yang sudah disimpan ?>
            </select>
            <input type="number" name="qty[]" placeholder="Qty" class="w-24 border p-2 rounded" min="1" required>
            <button type="button" onclick="hapusBaris(this)" class="bg-red-100 text-red-600 px-3 rounded hover:bg-red-200"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(row);
    }

    function hapusBaris(btn) {
        const rows = document.getElementsByClassName('item-row');
        if(rows.length > 1) {
            btn.parentElement.remove();
        } else {
            alert("Minimal harus ada 1 barang.");
        }
    }
</script>