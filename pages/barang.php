<?php
// Pastikan output buffering aman
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Komponen paginasi & filter (server-side)
require_once __DIR__ . '/../layout/tabel_helper.php';

// ==========================================
// 1. CEK KEAMANAN HALAMAN (PENTING!)
// ==========================================
wajib_akses('barang');

$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1;

function cekAtauBuatSupplier($conn, $nama_supplier, $id_usaha) {
    $nama = trim(mysqli_real_escape_string($conn, $nama_supplier));
    if (empty($nama) || $nama == '-') return 0;

    $cek = mysqli_query($conn, "SELECT id FROM supplier WHERE nama_supplier = '$nama' AND id_usaha = '$id_usaha' LIMIT 1");
    if(mysqli_num_rows($cek) > 0) {
        $d = mysqli_fetch_assoc($cek); return $d['id'];
    } else {
        mysqli_query($conn, "INSERT INTO supplier (nama_supplier, alamat, no_telp, id_usaha) VALUES ('$nama', '-', '-', '$id_usaha')");
        return mysqli_insert_id($conn);
    }
    return 0;
}

function cekAtauBuatKategori($conn, $nama_kategori, $id_usaha) {
    $nama = trim(mysqli_real_escape_string($conn, $nama_kategori));
    if (empty($nama)) return null;

    $cek = mysqli_query($conn, "SELECT id FROM kategori WHERE nama_kategori = '$nama' AND id_usaha = '$id_usaha' LIMIT 1");
    if(mysqli_num_rows($cek) > 0) {
        $d = mysqli_fetch_assoc($cek); return $d['id'];
    } else {
        mysqli_query($conn, "INSERT INTO kategori (nama_kategori, id_usaha) VALUES ('$nama', '$id_usaha')");
        return mysqli_insert_id($conn);
    }
}

if(isset($_POST['cetak_pdf'])) {
    while (ob_get_level()) { ob_end_clean(); }
    $q_toko = mysqli_query($conn, "SELECT nama_usaha FROM master_usaha WHERE id='$id_usaha_aktif'");
    $nm_toko = mysqli_fetch_assoc($q_toko)['nama_usaha'] ?? 'Toko';
    
    // TANGKAP FILTER
    $filter_search = mysqli_real_escape_string($conn, $_POST['filter_search'] ?? '');
    $filter_kat = mysqli_real_escape_string($conn, $_POST['filter_kategori'] ?? '');
    
    $where_sql = "b.id_usaha = '$id_usaha_aktif'";
    $subtitle = "";
    
    if (!empty($filter_kat)) {
        $where_sql .= " AND b.kategori = '$filter_kat'";
        $subtitle .= "Kategori: " . $filter_kat . " ";
    }
    if (!empty($filter_search)) {
        $where_sql .= " AND (b.nama_barang LIKE '%$filter_search%' OR b.kode_barang LIKE '%$filter_search%' OR b.kategori LIKE '%$filter_search%')";
        $subtitle .= "| Pencarian: " . $filter_search;
    }
    
    echo '<!DOCTYPE html><html><head><title>Cetak Stok Barang</title>';
    echo '<style>
            body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #333; padding: 8px; text-align: left; }
            th { background-color: #f3f4f6; text-transform: uppercase; font-size: 11px; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-red { color: #dc2626; font-weight: bold; }
            @media print { .no-print { display: none; } }
          </style></head><body onload="window.print()">';
          
    echo '<div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Sekarang / Save PDF</button>
          </div>';
          
    echo '<h2 style="text-align:center; margin-bottom: 5px;">Laporan Stok Barang - ' . $nm_toko . '</h2>';
    if (!empty($subtitle)) { echo '<p style="text-align:center; font-weight:bold; margin-top:0;">' . trim($subtitle, '| ') . '</p>'; }
    echo '<p style="text-align:center; margin-top: 0; color: #666;">Tanggal Cetak: ' . date('d/m/Y H:i') . '</p>';
    
    echo '<table>
            <thead>
                <tr>
                    <th class="text-center">No</th>
                    <th>Kategori</th>
                    <th>Nama Barang</th>
                    <th>Kode</th>
                    <th class="text-center">Satuan</th>
                    <th class="text-center">Stok Saat Ini</th>
                    <th class="text-center">Stok Minimal</th>
                    <th>Supplier</th>
                    <th class="text-right">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody>';
    
    $q_pdf = mysqli_query($conn, "SELECT b.*, s.nama_supplier FROM barang b LEFT JOIN supplier s ON b.supplier_id = s.id WHERE $where_sql ORDER BY b.kategori ASC, b.nama_barang ASC");
    $no_pdf = 1;
    $total_nominal_pdf = 0; // Variabel untuk menyimpan Grand Total
    
    while($r = mysqli_fetch_assoc($q_pdf)) {
        $stok_val = (float)$r['stok'];
        $min_val = (float)$r['min_stok'];
        $harga_beli = (float)$r['harga_beli'];
        $color_class = ($stok_val <= $min_val) ? 'text-red' : '';
        
        $nominal = $stok_val * $harga_beli;
        $total_nominal_pdf += $nominal;
        
        echo '<tr>
                <td class="text-center">'.$no_pdf++.'</td>
                <td>'.$r['kategori'].'</td>
                <td><strong>'.$r['nama_barang'].'</strong></td>
                <td>'.$r['kode_barang'].'</td>
                <td class="text-center">'.$r['satuan'].'</td>
                <td class="text-center '.$color_class.'">'.$stok_val.'</td>
                <td class="text-center text-gray-500">'.$min_val.'</td>
                <td>'.($r['nama_supplier'] ?? '-').'</td>
                <td class="text-right font-bold">'.number_format($nominal, 0, ',', '.').'</td>
              </tr>';
    }
    
    echo '</tbody>
          <tfoot>
              <tr>
                  <th colspan="8" class="text-right">TOTAL NOMINAL KESELURUHAN STOCK:</th>
                  <th class="text-right text-red" style="font-size:14px;">Rp '.number_format($total_nominal_pdf, 0, ',', '.').'</th>
              </tr>
          </tfoot>
          </table></body></html>';
    exit();
}

if(isset($_POST['export_barang'])) {
    while (ob_get_level()) { ob_end_clean(); }

    $filter_search = mysqli_real_escape_string($conn, $_POST['filter_search'] ?? '');
    $filter_kat = mysqli_real_escape_string($conn, $_POST['filter_kategori'] ?? '');
    
    $where_sql = "b.id_usaha = '$id_usaha_aktif'";
    if (!empty($filter_kat)) { $where_sql .= " AND b.kategori = '$filter_kat'"; }
    if (!empty($filter_search)) { $where_sql .= " AND (b.nama_barang LIKE '%$filter_search%' OR b.kode_barang LIKE '%$filter_search%' OR b.kategori LIKE '%$filter_search%')"; }

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Master_Data_Barang_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr style="background-color: #4F46E5; color: white;">
            <th>No</th>
            <th>Kategori</th>
            <th>Jenis Barang</th>
            <th>Nama Barang</th>
            <th>Kode Barang</th>
            <th>Satuan</th>
            <th>Stok</th>
            <th>Stok Minimal</th>
            <th>Supplier</th>
            <th>Harga Pangkalpinang</th>
            <th>Harga Bangka Tengah</th>
            <th>Harga Bangka Barat</th>
            <th>Proyeksi Harga Beli (Modal)</th>
            <th>Proyeksi Harga Jual (Umum)</th>
            <th>Harga HET</th>
            <th>Minimal Order</th>
            <th>Nominal</th>
          </tr>';
    
    $query = mysqli_query($conn, "SELECT b.*, s.nama_supplier, jb.jenis_barang FROM barang b LEFT JOIN supplier s ON b.supplier_id = s.id LEFT JOIN jenis_barang jb ON b.jenis_barang_id = jb.id WHERE $where_sql ORDER BY b.id ASC");

    $no = 1;
    while($row = mysqli_fetch_assoc($query)) {
        $nominal_excel = (float)$row['stok'] * (float)$row['harga_beli'];

        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . $row['kategori'] . '</td>';
        echo '<td>' . ($row['jenis_barang'] ?? '-') . '</td>';
        echo '<td>' . $row['nama_barang'] . '</td>';
        echo '<td>' . $row['kode_barang'] . '</td>';
        echo '<td>' . $row['satuan'] . '</td>';
        echo '<td>' . $row['stok'] . '</td>';
        echo '<td>' . $row['min_stok'] . '</td>';
        echo '<td>' . ($row['nama_supplier'] ?? '-') . '</td>';
        echo '<td>' . $row['harga_gabek'] . '</td>';
        echo '<td>' . $row['harga_kereta'] . '</td>';
        echo '<td>' . ($row['harga_jebus'] ?? 0) . '</td>';
        echo '<td>' . $row['harga_beli'] . '</td>';
        echo '<td>' . $row['harga_jual'] . '</td>';
        echo '<td>' . $row['harga_head'] . '</td>';
        echo '<td>' . $row['minimal_order'] . '</td>';
        echo '<td>' . $nominal_excel . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit(); 
}

if(isset($_POST['hapus_semua_barang'])) {
    tolak_jika_tidak_boleh('hapus', 'barang', 'index.php?page=barang');
    {
        mysqli_query($conn, "DELETE FROM barang WHERE id_usaha = '$id_usaha_aktif'"); 
        catat_log($conn, "Reset Barang", "Menghapus SELURUH data barang Toko ID: $id_usaha_aktif");
        echo "<script>alert('SELURUH Data Barang Toko INI Berhasil Dihapus!'); window.location='index.php?page=barang';</script>";
    }
}

if(isset($_POST['import_barang'])) {
    tolak_jika_tidak_boleh('import', 'barang', 'index.php?page=barang');
    {
        $fileName = $_FILES['file_csv']['tmp_name'];
        if($_FILES['file_csv']['size'] > 0) {
            $file = fopen($fileName, "r"); fgetcsv($file); 
            $sukses = 0; $update = 0;
            while(($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                $kategori = mysqli_real_escape_string($conn, $column[1]);
                $kategori_id = cekAtauBuatKategori($conn, $column[1], $id_usaha_aktif);
                $nama_raw = mysqli_real_escape_string($conn, $column[2]);
                $nama = ucwords(strtolower(trim($nama_raw)));
                $nama_sup = $column[3]; $min_order = mysqli_real_escape_string($conn, $column[4]);
                $satuan = mysqli_real_escape_string($conn, $column[6]);
                
                $harga_beli = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[7]));
                $harga_head = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[13]));
                $harga_jual = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[15]));
                
                $harga_gabek = isset($column[16]) ? (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[16])) : 0;
                $harga_kereta = isset($column[17]) ? (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[17])) : 0;
                $harga_jebus = isset($column[18]) ? (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $column[18])) : 0;
                
                if(empty($nama)) continue;
                
                $supplier_id = cekAtauBuatSupplier($conn, $nama_sup, $id_usaha_aktif);
                $cek = mysqli_query($conn, "SELECT id FROM barang WHERE LOWER(nama_barang) = LOWER('$nama') AND id_usaha = '$id_usaha_aktif'");
                
                if(mysqli_num_rows($cek) > 0) {
                    $d = mysqli_fetch_assoc($cek); $id = $d['id'];
                    $kategori_id_sql = $kategori_id === null ? 'NULL' : "'$kategori_id'";
                    mysqli_query($conn, "UPDATE barang SET kategori='$kategori', kategori_id=$kategori_id_sql, supplier_id='$supplier_id', satuan='$satuan', minimal_order='$min_order', harga_beli='$harga_beli', harga_head='$harga_head', harga_jual='$harga_jual', harga_gabek='$harga_gabek', harga_kereta='$harga_kereta', harga_jebus='$harga_jebus' WHERE id='$id'");
                    $update++;
                } else {
                    $max = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) as m FROM barang"));
                    $kode = "BRG" . sprintf("%03s", $max['m'] + 1 + $sukses);
                    $kategori_id_sql = $kategori_id === null ? 'NULL' : "'$kategori_id'";
                    mysqli_query($conn, "INSERT INTO barang (id_usaha, kode_barang, nama_barang, kategori, kategori_id, supplier_id, satuan, harga_beli, harga_head, harga_jual, harga_gabek, harga_kereta, harga_jebus, stok, minimal_order) VALUES ('$id_usaha_aktif', '$kode', '$nama', '$kategori', $kategori_id_sql, '$supplier_id', '$satuan', '$harga_beli', '$harga_head', '$harga_jual', '$harga_gabek', '$harga_kereta', '$harga_jebus', 0, '$min_order')");
                    $sukses++;
                }
            }
            fclose($file);
            catat_log($conn, "Import Barang", "Import CSV Selesai. Baru: $sukses, Update: $update");
            echo "<script>alert('Import Selesai! Data Baru: $sukses, Data Diupdate: $update'); window.location='index.php?page=barang';</script>";
        }
    }
}

if(isset($_POST['simpan_barang'])) {
    $__aksi_brg = !empty($_POST['id_barang']) ? 'edit' : 'tambah';
    tolak_jika_tidak_boleh($__aksi_brg, 'barang', 'index.php?page=barang');
    {
        $kode = $_POST['kode_barang'];
        $kat = $_POST['kategori'];
        $kategori_id = cekAtauBuatKategori($conn, $kat, $id_usaha_aktif);
        $jenis_barang_id = !empty($_POST['jenis_barang_id']) ? (int)$_POST['jenis_barang_id'] : null;
        $nama_sup = $_POST['nama_supplier'];
        $satuan = $_POST['satuan'];
        
        $beli = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_beli'])); 
        $head = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_head'])); 
        $jual = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_jual']));
        $stok = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['stok'])); 
        $min_ord = mysqli_real_escape_string($conn, $_POST['minimal_order']);
        $min_stok = (int)$_POST['min_stok']; 
        
        $harga_gabek = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_gabek']));
        $harga_kereta = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_kereta']));
        $harga_jebus = (float) preg_replace("/[^0-9.]/", "", str_replace(',', '.', $_POST['harga_jebus']));

        $supplier_id = cekAtauBuatSupplier($conn, $nama_sup, $id_usaha_aktif);
        $id_barang = $_POST['id_barang'];

        $nama_raw = mysqli_real_escape_string($conn, $_POST['nama_barang']);
        $nama = ucwords(strtolower(trim($nama_raw)));

        $cek_duplikat = "SELECT id FROM barang WHERE LOWER(nama_barang) = LOWER('$nama') AND id_usaha = '$id_usaha_aktif'";
        if(!empty($id_barang)) {
            $cek_duplikat .= " AND id != '$id_barang'";
        }
        
        $q_cek = mysqli_query($conn, $cek_duplikat);
        if(mysqli_num_rows($q_cek) > 0) {
            echo "<script>alert('GAGAL: Item dengan nama \"$nama\" sudah ada di database!'); window.history.back();</script>";
            exit();
        }

        if($_SESSION['role'] == 'po') {
            $data_aksi = [
                'id_barang'   => $id_barang, 'kode_barang' => $kode, 'nama_barang' => $nama,
                'kategori'    => $kat, 'kategori_id' => $kategori_id, 'jenis_barang_id' => $jenis_barang_id, 'supplier_id' => $supplier_id, 'satuan'      => $satuan,
                'harga_beli'  => $beli, 'harga_head'  => $head, 'harga_jual'  => $jual,
                'harga_gabek' => $harga_gabek, 'harga_kereta'=> $harga_kereta, 'harga_jebus' => $harga_jebus, 'stok_baru'   => $stok,
                'minimal_order' => $min_ord, 'min_stok' => $min_stok
            ];
            $json_data = json_encode($data_aksi);
            
            if(!empty($id_barang)) {
                $tipe_aksi = 'update_stok'; 
                $ket = "Request Update Barang: $nama. Harga Khusus: Pangkalpinang($harga_gabek), Bangka Tengah($harga_kereta), Bangka Barat($harga_jebus)";
            } else {
                $tipe_aksi = 'tambah_barang';
                $ket = "Request Tambah Barang Baru: $nama ($kode)";
            }

            $user_id = $_SESSION['user_id'];
            $q_req = "INSERT INTO approval_request (id_usaha, user_id, tipe_aksi, keterangan, data_json, status) 
                      VALUES ('$id_usaha_aktif', '$user_id', '$tipe_aksi', '$ket', '$json_data', 'pending')";
            
            if(mysqli_query($conn, $q_req)) {
                echo "<script>alert('PERMINTAAN TERKIRIM! Data menunggu persetujuan Manager.'); window.location='index.php?page=barang';</script>";
            } else { echo "<script>alert('Gagal mengirim permintaan approval!');</script>"; }
        } 
        else {
            if(!empty($id_barang)) {
                $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT harga_beli, harga_jual FROM barang WHERE id='$id_barang'"));
                if($old['harga_beli'] != $beli || $old['harga_jual'] != $jual) {
                    $user_id = $_SESSION['user_id'] ?? 0;
                    mysqli_query($conn, "INSERT INTO riwayat_harga (barang_id, harga_beli_lama, harga_beli_baru, harga_jual_lama, harga_jual_baru, tgl_perubahan, user_id) VALUES ('$id_barang', '{$old['harga_beli']}', '$beli', '{$old['harga_jual']}', '$jual', NOW(), '$user_id')");
                }
                
                $kategori_id_sql = $kategori_id === null ? 'NULL' : "'$kategori_id'";
                $jenis_barang_id_sql = $jenis_barang_id === null ? 'NULL' : "'$jenis_barang_id'";
                $query = "UPDATE barang SET kode_barang='$kode', nama_barang='$nama', kategori='$kat', kategori_id=$kategori_id_sql, jenis_barang_id=$jenis_barang_id_sql, supplier_id='$supplier_id', satuan='$satuan', harga_beli='$beli', harga_head='$head', harga_jual='$jual', harga_gabek='$harga_gabek', harga_kereta='$harga_kereta', harga_jebus='$harga_jebus', stok='$stok', minimal_order='$min_ord', min_stok='$min_stok' WHERE id='$id_barang' AND id_usaha='$id_usaha_aktif'";
                catat_log($conn, "Edit Barang", "Update data barang: $nama");
            } else {
                $kategori_id_sql = $kategori_id === null ? 'NULL' : "'$kategori_id'";
                $jenis_barang_id_sql = $jenis_barang_id === null ? 'NULL' : "'$jenis_barang_id'";
                $query = "INSERT INTO barang (id_usaha, kode_barang, nama_barang, kategori, kategori_id, jenis_barang_id, supplier_id, satuan, harga_beli, harga_head, harga_jual, harga_gabek, harga_kereta, harga_jebus, stok, minimal_order, min_stok) VALUES ('$id_usaha_aktif', '$kode', '$nama', '$kat', $kategori_id_sql, $jenis_barang_id_sql, '$supplier_id', '$satuan', '$beli', '$head', '$jual', '$harga_gabek', '$harga_kereta', '$harga_jebus', '$stok', '$min_ord', '$min_stok')";
                catat_log($conn, "Tambah Barang", "Menambah barang baru: $nama");
            }
            if(mysqli_query($conn, $query)) { echo "<script>alert('Data Barang Berhasil Disimpan!'); window.location='index.php?page=barang';</script>"; }
        }
    }
}

if(isset($_GET['hapus'])) {
    tolak_jika_tidak_boleh('hapus', 'barang', 'index.php?page=barang');
    {
        $id = $_GET['hapus'];
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_barang FROM barang WHERE id='$id' AND id_usaha='$id_usaha_aktif'"));
        if($cek) {
            $nm_brg = $cek['nama_barang'] ?? 'Unknown';
            mysqli_query($conn, "DELETE FROM barang WHERE id='$id' AND id_usaha='$id_usaha_aktif'");
            catat_log($conn, "Hapus Barang", "Menghapus barang: $nm_brg");
        }
        echo "<script>window.location='index.php?page=barang';</script>";
    }
}
?>

<div class="bg-white p-6 rounded-lg shadow-sm">
    <div class="flex flex-col gap-4 mb-4">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="text-xl font-bold text-gray-800">Master Data Barang (Stok Gudang)</h3>
            <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg font-bold border border-blue-200">
                <i class="fa-solid fa-store mr-2"></i> 
                <?php 
                    $nm_toko = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_usaha FROM master_usaha WHERE id='$id_usaha_aktif'"));
                    echo $nm_toko['nama_usaha'] ?? 'Toko';
                ?>
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <?php
            // Filter dikirim ke server (bukan lagi disaring JavaScript di browser)
            $kat_terpilih = trim((string) ($_GET['kategori'] ?? ''));
            $opsi_kat = '<select name="kategori" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">'
                      . '<option value="">-- Semua Kategori --</option>';
            $q_kat = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id_usaha='$id_usaha_aktif' ORDER BY nama_kategori ASC");
            while ($k = mysqli_fetch_assoc($q_kat)) {
                $kv = htmlspecialchars($k['nama_kategori'], ENT_QUOTES, 'UTF-8');
                $opsi_kat .= '<option value="' . $kv . '"' . ($k['nama_kategori'] === $kat_terpilih ? ' selected' : '') . '>' . $kv . '</option>';
            }
            $opsi_kat .= '</select>';
            ?>
            <div class="w-full md:w-2/3">
                <?= render_filter('Cari Nama / Kode / Kategori...', $opsi_kat) ?>
            </div>
            
            <div class="flex flex-wrap gap-2 justify-end w-full md:w-1/2">
                <?php if(boleh('tambah','barang')): ?>
                    <button onclick="openModal()" class="bg-indigo-600 text-white px-3 py-2 rounded font-bold hover:bg-indigo-700 text-xs shadow"><i class="fa-solid fa-plus mr-1"></i> Tambah</button>
                <?php endif; ?>
                <?php if(boleh('import','barang')): ?>
                    <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-green-600 text-white px-3 py-2 rounded font-bold hover:bg-green-700 text-xs shadow"><i class="fa-solid fa-file-import mr-1"></i> Import CSV</button>
                <?php endif; ?>
                <?php if(boleh('hapus','barang')): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('RESET SEMUA DATA BARANG??')"><button type="submit" name="hapus_semua_barang" class="bg-red-600 text-white px-3 py-2 rounded font-bold hover:bg-red-700 text-xs shadow"><i class="fa-solid fa-bomb mr-1"></i> Reset</button></form>
                <?php endif; ?>
                
                <form method="POST" class="inline" id="formExcel" onsubmit="updateHiddenFilters()">
                    <input type="hidden" name="filter_search" class="hidden-search">
                    <input type="hidden" name="filter_kategori" class="hidden-kategori">
                    <button type="submit" name="export_barang" class="bg-gray-600 text-white px-3 py-2 rounded font-bold hover:bg-gray-700 text-xs shadow"><i class="fa-solid fa-file-excel mr-1"></i> Excel</button>
                </form>
                
                <form method="POST" class="inline" target="_blank" id="formPdf" onsubmit="updateHiddenFilters()">
                    <input type="hidden" name="filter_search" class="hidden-search">
                    <input type="hidden" name="filter_kategori" class="hidden-kategori">
                    <button type="submit" name="cetak_pdf" class="bg-red-600 text-white px-3 py-2 rounded font-bold hover:bg-red-700 text-xs shadow"><i class="fa-solid fa-file-pdf mr-1"></i> Cetak PDF</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto max-h-[70vh]">
        <table class="w-full text-sm text-left border whitespace-nowrap" id="tabelBarang">
            <thead class="bg-gray-100 uppercase text-gray-600 font-bold sticky top-0 z-10 shadow-sm text-[10px]">
                <tr>
                    <th class="p-3 border text-center w-24">Aksi / Edit</th>
                    <th class="p-3 border">Kategori</th>
                    <th class="p-3 border">Jenis Barang</th>
                    <th class="p-3 border">Nama Barang</th>
                    <th class="p-3 border text-center">Satuan</th>
                    <th class="p-3 border text-center">Stock</th>
                    <th class="p-3 border text-center">Stock Min.</th>
                    <th class="p-3 border">Supplier</th>
                    <th class="p-3 border text-right bg-blue-50 text-blue-800">Harga PANGKALPINANG</th>
                    <th class="p-3 border text-right bg-blue-50 text-blue-800">Harga BANGKA TENGAH</th>
                    <th class="p-3 border text-right bg-blue-50 text-blue-800">Harga BANGKA BARAT</th>
                    <th class="p-3 border text-right text-red-600">Proyeksi Harga Beli</th>
                    <th class="p-3 border text-right text-green-600">Proyeksi Harga Jual</th>
                    <th class="p-3 border text-right bg-yellow-50">Harga HET</th>
                    <th class="p-3 border text-center">Min. Order</th>
                    <th class="p-3 border text-right bg-indigo-50 text-indigo-800">Nominal (Aset)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                // ==========================================================
                // PAGINASI & FILTER SISI SERVER
                // Sebelumnya SEMUA barang dimuat lalu disaring oleh JavaScript,
                // sehingga browser harus merender ratusan baris setiap kali.
                // Sekarang hanya satu halaman data yang diambil dari database.
                // ==========================================================
                $b_cari  = ambil_kata_kunci();
                $b_kat   = trim((string) ($_GET['kategori'] ?? ''));
                $b_hal   = ambil_halaman();
                $b_limit = ambil_per_halaman();

                $b_where_tambahan = ['b.id_usaha = ?'];
                $b_params_tambahan = [$id_usaha_aktif];
                $b_tipe_tambahan   = 'i';

                if ($b_kat !== '') {
                    $b_where_tambahan[]  = 'b.kategori = ?';
                    $b_params_tambahan[] = $b_kat;
                    $b_tipe_tambahan    .= 's';
                }

                [$b_where, $b_params, $b_tipe] = bangun_filter(
                    $b_cari,
                    ['b.nama_barang', 'b.kode_barang', 'b.kategori'],
                    $b_where_tambahan, $b_params_tambahan, $b_tipe_tambahan
                );

                $b_total  = hitung_total($conn, 'barang b', $b_where, $b_params, $b_tipe);
                $b_hal    = batasi_halaman($b_hal, $b_total, $b_limit);
                $b_offset = ($b_hal - 1) * $b_limit;

                $b_select = "
                    SELECT b.*, s.nama_supplier, jb.jenis_barang,
                    (SELECT COUNT(*) FROM approval_request ar
                     WHERE ar.tipe_aksi = 'update_stok'
                     AND ar.status = 'pending'
                     AND ar.data_json LIKE CONCAT('%\"id_barang\":\"', b.id, '\"%')
                    ) as is_pending
                    FROM barang b
                    LEFT JOIN supplier s ON b.supplier_id = s.id
                    LEFT JOIN jenis_barang jb ON b.jenis_barang_id = jb.id";

                $b_rows = ambil_data($conn, $b_select, $b_where, $b_params, $b_tipe,
                                     'ORDER BY b.id DESC', $b_limit, $b_offset);

                if (!$b_rows) { echo render_kosong(15, 'Tidak ada barang yang cocok dengan pencarian.'); }

                foreach ($b_rows as $r):
                    $is_pending = ($r['is_pending'] > 0);
                    $bg_row = $is_pending ? 'bg-yellow-50 border-l-4 border-yellow-400' : 'hover:bg-gray-50';
                    
                    $stok_val = (float)$r['stok'];
                    $min_stok_val = (float)$r['min_stok'];
                    $stok_color = ($stok_val <= $min_stok_val) ? 'text-red-600 font-bold' : 'text-gray-900 font-bold';
                    
                    // HITUNG NOMINAL UNTUK TABEL UI
                    $nominal_ui = $stok_val * (float)$r['harga_beli'];
                ?>
                <tr class="<?= $bg_row ?> transition">
                    
                    <td class="p-3 border text-center flex justify-center gap-1">
                        <?php if(boleh('edit','barang')): ?>
                            <button onclick='editBarang(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)' 
                                    class="<?= $is_pending ? 'text-gray-400 cursor-not-allowed' : 'text-indigo-600 hover:text-indigo-800' ?>" 
                                    <?= $is_pending ? 'disabled title="Sedang menunggu persetujuan Manager"' : '' ?> >
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            
                        <?php endif; ?>
                        <?php if(boleh('hapus','barang')): ?>
                            <a href="index.php?page=barang&hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus?')" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                        
                        <button onclick="lihatHistori('<?= $r['id'] ?>', '<?= addslashes($r['nama_barang']) ?>')" class="text-orange-500 hover:text-orange-700"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    </td>

                    <td class="p-3 border font-bold text-gray-600 barang-kategori">
                        <?= $r['kategori'] ?>
                    </td>

                    <td class="p-3 border text-center">
                        <?php if(!empty($r['jenis_barang'])): ?>
                            <span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?= strtolower($r['jenis_barang']) === 'sppg' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700' ?>">
                                <?= htmlspecialchars($r['jenis_barang'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-300 text-xs">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="p-3 border">
                        <div class="font-bold text-gray-800 barang-nama">
                            <?= $r['nama_barang'] ?>
                            <?php if($is_pending) echo '<span class="ml-2 bg-yellow-400 text-white text-[9px] px-2 py-0.5 rounded font-bold uppercase tracking-wider">Menunggu ACC</span>'; ?>
                        </div>
                        <div class="text-xs text-gray-500 font-mono barang-meta"><?= $r['kode_barang'] ?></div>
                    </td>
                    
                    <td class="p-3 border text-center font-bold bg-gray-50"><?= $r['satuan'] ?></td> 
                    
                    <td class="p-3 border text-center"><span class="px-2 py-1 rounded <?= $r['stok']<5?'bg-red-100 text-red-700':'bg-green-100 text-green-700' ?> font-bold <?= $stok_color ?>"><?= $stok_val ?></span></td>
                    
                    <td class="p-3 border text-center text-gray-500 font-bold"><?= $min_stok_val ?></td>

                    <td class="p-3 border text-indigo-600 font-medium"><?= $r['nama_supplier'] ?? '-' ?></td>
                    
                    <td class="p-3 border text-right bg-blue-50 font-bold text-blue-700"><?= number_format((float)$r['harga_gabek']) ?></td>
                    
                    <td class="p-3 border text-right bg-blue-50 font-bold text-blue-700"><?= number_format((float)$r['harga_kereta']) ?></td>
                    
                    <td class="p-3 border text-right bg-blue-50 font-bold text-blue-700"><?= number_format((float)($r['harga_jebus'] ?? 0)) ?></td>

                    <td class="p-3 border text-right text-red-600"><?= number_format((float)$r['harga_beli']) ?></td>
                    
                    <td class="p-3 border text-right text-green-600 font-bold"><?= number_format((float)$r['harga_jual']) ?></td>
                    
                    <td class="p-3 border text-right bg-yellow-50 font-bold"><?= number_format((float)$r['harga_head']) ?></td>

                    <td class="p-3 border text-gray-600 italic text-center"><?= $r['minimal_order'] ?></td>

                    <td class="p-3 border text-right font-bold text-indigo-700 bg-indigo-50">Rp <?= number_format($nominal_ui, 0, ',', '.') ?></td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= render_paginasi($b_hal, $b_total, $b_limit) ?>
</div>

<div id="modalImport" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md p-6 relative animate-fade-in-up">
        <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        <h3 class="text-lg font-bold mb-4">Import Data Barang</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4"><input type="file" name="file_csv" accept=".csv" class="w-full border p-2 rounded" required></div>
            <button type="submit" name="import_barang" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:bg-green-700">Upload & Proses</button>
        </form>
    </div>
</div>

<div id="modalBarang" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-lg p-6 relative animate-fade-in-up overflow-y-auto max-h-[90vh]">
        <h3 class="text-lg font-bold mb-4" id="modalTitle">Tambah Barang</h3>
        <form method="POST">
            <input type="hidden" name="id_barang" id="id_barang">
            <div class="grid grid-cols-2 gap-4 mb-3">
                <div><label class="block text-xs font-bold text-gray-500">Kode Barang</label><input type="text" name="kode_barang" id="kode_barang" class="w-full border p-2 rounded" required></div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Kategori</label>
                    <select name="kategori" id="kategori" class="w-full border p-2 rounded">
                        <option value="">-- Pilih Kategori --</option>
                        <?php
                        $qk = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id_usaha = '$id_usaha_aktif' ORDER BY nama_kategori ASC");
                        while($k = mysqli_fetch_assoc($qk)) {
                            $kv = htmlspecialchars($k['nama_kategori'], ENT_QUOTES, 'UTF-8');
                            echo "<option value=\"$kv\">$kv</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500">Jenis Barang</label>
                <select name="jenis_barang_id" id="jenis_barang_id" class="w-full border p-2 rounded">
                    <option value="">-- Pilih Jenis Barang --</option>
                    <?php
                    $qjb = mysqli_query($conn, "SELECT id, jenis_barang FROM jenis_barang ORDER BY jenis_barang ASC");
                    while($jb = mysqli_fetch_assoc($qjb)) {
                        echo '<option value="' . $jb['id'] . '">' . htmlspecialchars($jb['jenis_barang'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3"><label class="block text-xs font-bold text-gray-500">Nama Barang</label><input type="text" name="nama_barang" id="nama_barang" class="w-full border p-2 rounded" required></div>
            <div class="mb-3"><label class="block text-xs font-bold text-gray-500">Supplier</label><input list="list_supplier" name="nama_supplier" id="nama_supplier" class="w-full border p-2 rounded" placeholder="Auto Create"><datalist id="list_supplier"><?php $qs = mysqli_query($conn, "SELECT nama_supplier FROM supplier WHERE id_usaha = '$id_usaha_aktif' ORDER BY nama_supplier ASC"); while($s = mysqli_fetch_assoc($qs)) { echo "<option value='{$s['nama_supplier']}'>"; } ?></datalist></div>
            
            <div class="grid grid-cols-3 gap-2 mb-3 bg-blue-50 p-3 rounded-lg border border-blue-200">
                <div><label class="block text-[9px] font-bold text-blue-700 uppercase mb-1">Harga PANGKALPINANG</label><input type="number" step="any" name="harga_gabek" id="harga_gabek" class="w-full border p-2 rounded font-bold text-blue-800" placeholder="0"></div>
                <div><label class="block text-[9px] font-bold text-blue-700 uppercase mb-1">Harga BANGKA TENGAH</label><input type="number" step="any" name="harga_kereta" id="harga_kereta" class="w-full border p-2 rounded font-bold text-blue-800" placeholder="0"></div>
                <div><label class="block text-[9px] font-bold text-blue-700 uppercase mb-1">Harga BANGKA BARAT</label><input type="number" step="any" name="harga_jebus" id="harga_jebus" class="w-full border p-2 rounded font-bold text-blue-800" placeholder="0"></div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3 bg-yellow-50 p-2 rounded border border-yellow-200">
                <div><label class="block text-xs font-bold text-gray-700">Proyeksi Harga Beli</label><input type="number" step="any" name="harga_beli" id="harga_beli" class="w-full border p-2 rounded" required></div>
                <div><label class="block text-xs font-bold text-red-600">Harga Head (Max)</label><input type="number" step="any" name="harga_head" id="harga_head" class="w-full border p-2 rounded border-red-200"></div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div><label class="block text-xs font-bold text-gray-500">Proyeksi Harga Jual</label><input type="number" step="any" name="harga_jual" id="harga_jual" class="w-full border p-2 rounded" required></div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Satuan</label>
                    <input list="list_satuan" type="text" name="satuan" id="satuan" class="w-full border p-2 rounded" placeholder="Pilih/Ketik">
                    <datalist id="list_satuan">
                        <option value="Kg"><option value="Gram"><option value="Ons"><option value="Ton"><option value="Kwintal">
                        <option value="Liter"><option value="ML"><option value="Galon"><option value="Botol"><option value="Kaleng">
                        <option value="Pcs"><option value="Unit"><option value="Buah"><option value="Pasang"><option value="Set">
                        <option value="Box"><option value="Dus"><option value="Karton"><option value="Pack"><option value="Bal">
                        <option value="Sak"><option value="Karung"><option value="Renteng"><option value="Lusin"><option value="Kodi">
                        <option value="Gros"><option value="Ikat"><option value="Bungkus">
                        <option value="Meter"><option value="CM"><option value="Roll"><option value="Lembar"><option value="Batang">
                    </datalist>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div><label class="block text-xs font-bold text-gray-500">Stok Awal</label><input type="number" step="any" name="stok" id="stok" class="w-full border p-2 rounded" required></div>
                <div><label class="block text-xs font-bold text-red-600">Stok Minimal</label><input type="number" name="min_stok" id="min_stok" class="w-full border p-2 rounded border-red-200" value="5"></div>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-blue-600">Minimal Order (Catatan)</label><input type="text" name="minimal_order" id="minimal_order" class="w-full border p-2 rounded border-blue-200">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalBarang').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">Batal</button>
                <button type="submit" name="simpan_barang" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    <?= ($_SESSION['role'] == 'po') ? 'Request Approval' : 'Simpan' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modalHistori" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-lg p-6 relative animate-fade-in-up">
        <button onclick="document.getElementById('modalHistori').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="text-lg font-bold mb-2">Riwayat Harga</h3>
        <p class="text-sm text-gray-500 mb-4" id="labelNamaBarang">...</p>
        <div id="kontenHistori" class="overflow-y-auto max-h-64 border rounded p-2 bg-gray-50"></div>
    </div>
</div>

<script>
function updateHiddenFilters() {
    let inputSearch = document.getElementById('searchInput').value;
    let inputKategori = document.getElementById('filterKategori').value;
    
    document.querySelectorAll('.hidden-search').forEach(el => el.value = inputSearch);
    document.querySelectorAll('.hidden-kategori').forEach(el => el.value = inputKategori);
}

function cariBarang() {
    let inputSearch = document.getElementById('searchInput').value.toUpperCase().trim();
    let inputKategori = document.getElementById('filterKategori').value.toUpperCase().trim();
    let tr = document.getElementById('tabelBarang').getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let n = tr[i].getElementsByClassName('barang-nama')[0];
        let m = tr[i].getElementsByClassName('barang-meta')[0];
        let k = tr[i].getElementsByClassName('barang-kategori')[0]; 
        
        if (n || m || k) {
            let textN = n ? n.innerText.toUpperCase().trim() : '';
            let textM = m ? m.innerText.toUpperCase().trim() : '';
            let textK = k ? k.innerText.toUpperCase().trim() : '';
            
            let textAll = textN + " " + textM + " " + textK;
            let matchSearch = textAll.indexOf(inputSearch) > -1;
            let matchKategori = (inputKategori === "") || (textK === inputKategori);
            
            tr[i].style.display = (matchSearch && matchKategori) ? "" : "none";
        }
    }
}
function openModal() {
    document.getElementById('modalBarang').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Tambah Barang';
    document.getElementById('id_barang').value = '';
    document.getElementById('kode_barang').value = 'BRG' + Math.floor(Math.random()*10000);
    document.getElementById('nama_barang').value = '';
    document.getElementById('nama_supplier').value = '';
    document.getElementById('jenis_barang_id').value = '';
    document.getElementById('harga_beli').value = '';
    document.getElementById('harga_head').value = '';
    document.getElementById('harga_jual').value = '';
    document.getElementById('harga_gabek').value = '0';
    document.getElementById('harga_kereta').value = '0';
    document.getElementById('harga_jebus').value = '0';
    document.getElementById('stok').value = '0';
    document.getElementById('min_stok').value = '5';
    document.getElementById('satuan').value = '';
    document.getElementById('minimal_order').value = '';
}
function editBarang(d) {
    document.getElementById('modalBarang').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Edit Barang';
    document.getElementById('id_barang').value = d.id;
    document.getElementById('kode_barang').value = d.kode_barang;
    document.getElementById('nama_barang').value = d.nama_barang;
    document.getElementById('nama_supplier').value = d.nama_supplier;
    document.getElementById('kategori').value = d.kategori;
    document.getElementById('jenis_barang_id').value = d.jenis_barang_id || '';
    document.getElementById('satuan').value = d.satuan;
    document.getElementById('harga_beli').value = d.harga_beli;
    document.getElementById('harga_head').value = d.harga_head;
    document.getElementById('harga_jual').value = d.harga_jual;
    document.getElementById('harga_gabek').value = d.harga_gabek;
    document.getElementById('harga_kereta').value = d.harga_kereta;
    document.getElementById('harga_jebus').value = d.harga_jebus;
    document.getElementById('stok').value = d.stok;
    document.getElementById('min_stok').value = d.min_stok;
    document.getElementById('minimal_order').value = d.minimal_order;
}
function lihatHistori(id, nama) {
    document.getElementById('modalHistori').classList.remove('hidden');
    document.getElementById('labelNamaBarang').innerText = nama;
    let fd = new FormData(); fd.append('get_histori', true); fd.append('id', id);
    fetch('index.php?page=barang', {method: 'POST', body: fd}).then(r=>r.text()).then(h=>{document.getElementById('kontenHistori').innerHTML=h;});
}
</script>

<?php
if(isset($_POST['get_histori'])) {
    ob_clean(); $id = $_POST['id'];
    $q = mysqli_query($conn, "SELECT * FROM riwayat_harga WHERE barang_id='$id' ORDER BY tgl_perubahan DESC");
    if(mysqli_num_rows($q)==0) echo '<p class="text-center text-gray-500 p-4">Kosong.</p>';
    else {
        echo '<table class="w-full text-xs text-left"><thead class="bg-gray-200 font-bold"><tr><th class="p-2">Tgl</th><th class="p-2 text-right">Beli</th><th class="p-2 text-right">Jual</th></tr></thead><tbody>';
        while($h=mysqli_fetch_assoc($q)) echo "<tr class='border-b bg-white'><td class='p-2'>".date('d/m/y',strtotime($h['tgl_perubahan']))."</td><td class='p-2 text-right'>".number_format((float)$h['harga_beli_lama'])."-><b>".number_format((float)$h['harga_beli_baru'])."</b></td><td class='p-2 text-right'>".number_format((float)$h['harga_jual_lama'])."-><b>".number_format((float)$h['harga_jual_baru'])."</b></td></tr>";
        echo '</tbody></table>';
    }
    exit;
}
?>