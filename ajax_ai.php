<?php
// 1. SETUP KONEKSI & SESSION
require 'config/koneksi.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PROTEKSI: analisis AI berisi data omzet & stok, wajib login
wajib_login_ajax(['admin','po','accounting']);

// Hanya proses jika request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

// ==========================================
// 2. AMBIL DATA DARI DATABASE
// ==========================================
$tgl = date('Y-m-d');

// Data Penjualan
$q_jual = mysqli_query($conn, "SELECT SUM(total_transaksi) as omzet, COUNT(*) as jumlah FROM transaksi WHERE tanggal LIKE '$tgl%'");
$d_jual = mysqli_fetch_assoc($q_jual);
$omzet = $d_jual['omzet'] ?? 0;
$transaksi = $d_jual['jumlah'] ?? 0;

// Data Produk Terlaris
$q_laris = mysqli_query($conn, "SELECT b.nama_barang, SUM(td.qty) as total FROM transaksi_detail td JOIN transaksi t ON td.no_faktur=t.no_faktur JOIN barang b ON td.barang_id=b.id WHERE t.tanggal LIKE '$tgl%' GROUP BY td.barang_id ORDER BY total DESC LIMIT 3");
$barang_laris = [];
while($r = mysqli_fetch_assoc($q_laris)) { $barang_laris[] = $r['nama_barang']; }
$str_laris = !empty($barang_laris) ? implode(", ", $barang_laris) : 'Belum ada';

// Data Stok Menipis
$q_stok = mysqli_query($conn, "SELECT nama_barang, stok FROM barang WHERE stok <= 5 LIMIT 5");
$stok_tipis = [];
while($r = mysqli_fetch_assoc($q_stok)) { $stok_tipis[] = $r['nama_barang'] . "(" . (float)$r['stok'] . ")"; }
$str_stok = !empty($stok_tipis) ? implode(", ", $stok_tipis) : 'Aman';

// Prompt AI
$prompt = "Saya pemilik toko retail. Data hari ini ($tgl):
1. Omzet: Rp " . number_format($omzet) . " ($transaksi transaksi).
2. Terlaris: $str_laris.
3. Stok Kritis: $str_stok.

Berikan analisis bisnis singkat & tegas (max 3 poin) dalam format HTML (gunakan <b>). Fokus: Strategi Omzet & Stok.";

// ==========================================
// 3. FUNGSI KIRIM KE GEMINI (DENGAN AUTO-FIX)
// ==========================================
function kirimKeGemini($apiKey, $model, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $apiKey;
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// ==========================================
// 4. EKSEKUSI UTAMA
// ==========================================
$set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT api_key_gemini FROM pengaturan LIMIT 1"));
$apiKey = trim($set['api_key_gemini'] ?? ''); // TRIM PENTING! Hapus spasi/enter

if(empty($apiKey)) {
    echo "<span class='text-red-500 font-bold'>Error: API Key Kosong!</span><br>Cek menu Pengaturan.";
    exit;
}

// PERCOBAAN 1: Pakai Model Standar (Flash)
$modelUtama = "gemini-1.5-flash";
$result = kirimKeGemini($apiKey, $modelUtama, $prompt);

// Cek Error
if (isset($result['error'])) {
    // Jika errornya "Not Found" (404), berarti model salah/tidak support.
    // KITA CARI MODEL YANG TERSEDIA DI AKUN INI
    if (strpos($result['error']['message'], 'not found') !== false) {
        
        // Request Daftar Model yang tersedia
        $listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
        $ch = curl_init($listUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $listResponse = curl_exec($ch);
        curl_close($ch);
        $listResult = json_decode($listResponse, true);

        if(isset($listResult['models'])) {
            // Cari model pertama yang support 'generateContent'
            $modelBaru = "";
            foreach($listResult['models'] as $m) {
                if(in_array("generateContent", $m['supportedGenerationMethods'])) {
                    $modelBaru = str_replace("models/", "", $m['name']);
                    break;
                }
            }

            if(!empty($modelBaru)) {
                // COBA LAGI DENGAN MODEL YANG DITEMUKAN
                $result2 = kirimKeGemini($apiKey, $modelBaru, $prompt);
                if(isset($result2['candidates'][0]['content']['parts'][0]['text'])) {
                    echo $result2['candidates'][0]['content']['parts'][0]['text'];
                    exit;
                }
            }
        }
    }
    
    // Jika masih gagal juga, tampilkan error asli
    echo "<span class='text-red-500 font-bold'>Gagal:</span> " . $result['error']['message'];
} 
elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    echo $result['candidates'][0]['content']['parts'][0]['text'];
} 
else {
    echo "AI tidak merespon. Coba lagi nanti.";
}
?>