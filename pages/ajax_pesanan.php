<?php
require '../config/koneksi.php';

// Penjaga endpoint: wajib login & role yang berhak (config/hak_akses.php)
// Pelanggan & driver ikut diizinkan karena memakai halaman Riwayat Pesanan.
wajib_login_ajax(['admin', 'po', 'gudang', 'accounting', 'viewer', 'invoice', 'pelanggan', 'driver'], 'html');

if(isset($_POST['get_detail_pesanan'])) {
    $id = (int) ($_POST['id'] ?? 0);

    // Pelanggan hanya boleh melihat detail pesanan miliknya sendiri.
    $role_ajax = strtolower($_SESSION['role'] ?? '');
    $filter_pemilik = '';
    if ($role_ajax === 'pelanggan') {
        $filter_pemilik = " AND p.user_id = '" . (int) ($_SESSION['user_id'] ?? 0) . "'";
    }

    // Ambil detail barang sekaligus status dari tabel header (pesanan)
    $sql = "SELECT d.*, b.nama_barang, b.satuan, p.status
            FROM pesanan_detail d
            JOIN barang b ON d.id_barang = b.id
            JOIN pesanan p ON d.id_pesanan = p.id
            WHERE d.id_pesanan = '$id'" . $filter_pemilik;

    $q = mysqli_query($conn, $sql);
    
    echo '<table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500">
                <tr>
                    <th class="p-3">Barang</th>
                    <th class="p-3 text-center">Qty</th>';
    
    // Tampilkan kolom Aksi hanya jika ada data dan statusnya Pending
    $rows = [];
    $is_pending = false;
    while($row = mysqli_fetch_assoc($q)) {
        $rows[] = $row;
        if($row['status'] == 'Pending') $is_pending = true;
    }

    if($is_pending) {
        echo '<th class="p-3 text-center text-red-500">Aksi</th>';
    }
    
    echo '      </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">';
    
    if(count($rows) > 0) {
        foreach($rows as $r) {
            echo "<tr>
                    <td class='p-3'>
                        <div class='font-medium text-slate-700'>{$r['nama_barang']}</div>
                        <div class='text-[10px] text-slate-400'>ID Detail: #{$r['id']}</div>
                    </td>
                    <td class='p-3 text-center font-bold'>".(float)$r['qty']." <span class='text-gray-400 font-normal'>{$r['satuan']}</span></td>";
            
            // Tombol Hapus Satuan (Manual)
            if($is_pending) {
                echo "<td class='p-3 text-center'>
                        <a href='index.php?page=riwayat_pesanan&hapus_item_id={$r['id']}' 
                           onclick='return confirm(\"Hapus item {$r['nama_barang']} dari pesanan ini?\")' 
                           class='text-red-400 hover:text-red-600 transition-colors'>
                            <i class='fa-solid fa-trash-can text-lg'></i>
                        </a>
                      </td>";
            }
            
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3' class='p-5 text-center text-gray-400'>Data item tidak ditemukan.</td></tr>";
    }
    
    echo '</tbody></table>';
    
    // Info Tambahan di bawah tabel
    if($is_pending) {
        echo '<div class="p-3 bg-amber-50 text-[10px] text-amber-700 border-t border-amber-100 italic">
                <i class="fa-solid fa-circle-info mr-1"></i> Anda bisa menghapus item yang salah input selama status masih Pending.
              </div>';
    }
}
?>