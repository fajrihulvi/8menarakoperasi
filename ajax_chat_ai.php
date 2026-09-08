<?php
// 1. KONEKSI DATABASE
require 'config/koneksi.php';

// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// PROTEKSI: chatbot membaca data penjualan/stok, wajib login
wajib_login_ajax();

// 2. AMBIL INPUT USER
$raw_message = $_POST['message'] ?? '';
$msg = strtolower(trim($raw_message)); // Konversi ke huruf kecil
$user_name = $_SESSION['nama'] ?? 'Bos';

// Fungsi Format Rupiah
function format_duit($angka){
    return "Rp " . number_format($angka,0,',','.');
}

// --- FUNGSI GEMINI API (DENGAN AUTO-FIX MODEL) ---
function kirimKeGemini($apiKey, $prompt, $model = 'gemini-1.5-flash') {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $apiKey;
    $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // Nonaktifkan SSL Verify (Penting untuk Localhost/XAMPP)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return "Koneksi Gagal: $error";
    }
    
    curl_close($ch);
    $res = json_decode($response, true);
    
    // LOGIKA AUTO-FIX MODEL
    if (isset($res['error'])) {
        if (strpos($res['error']['message'], 'not found') !== false || strpos($res['error']['message'], 'not supported') !== false) {
            if ($model == 'gemini-1.5-flash') {
                // Coba cari model lain secara otomatis
                $listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
                $ch2 = curl_init($listUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                $listRep = curl_exec($ch2);
                curl_close($ch2);
                
                $listData = json_decode($listRep, true);
                $modelBaru = '';

                if(isset($listData['models'])) {
                    foreach($listData['models'] as $m) {
                        if(isset($m['supportedGenerationMethods']) && in_array("generateContent", $m['supportedGenerationMethods'])) {
                            $modelBaru = str_replace("models/", "", $m['name']);
                            break; 
                        }
                    }
                }

                if(!empty($modelBaru) && $modelBaru !== $model) {
                    return kirimKeGemini($apiKey, $prompt, $modelBaru); 
                }
            }
        }
        return "AI Error: " . $res['error']['message'];
    }

    return $res['candidates'][0]['content']['parts'][0]['text'] ?? "Maaf, AI tidak memberikan respons.";
}

// =================================================================================
// LOGIKA ARTIFICIAL INTELLIGENCE
// =================================================================================

// 1. SAPAAN
if (in_array($msg, ['halo', 'hi', 'hai', 'p', 'test', 'siang', 'pagi', 'sore', 'malam', 'assalamualaikum'])) {
    $respon = [
        "Halo $user_name! 👋 Saya Asisten Cerdas Toko. Bisa bantu cek stok, omset, atau putar musik.",
        "Hai! Mau cek apa hari ini? Ketik 'Stok [Barang]' atau 'Putar [Lagu]'.",
        "Halo bos! Siap membantu 86! Coba tanya 'Tips jualan' atau 'Cek tagihan'."
    ];
    echo $respon[array_rand($respon)];
}

// 2. PEMUTAR AUDIO / MUSIK (YOUTUBE) -- [DIPERBAIKI]
elseif (strpos($msg, 'putar') !== false || strpos($msg, 'play') !== false || strpos($msg, 'dengar') !== false || strpos($msg, 'lagu') !== false || strpos($msg, 'musik') !== false || strpos($msg, 'music') !== false) {
    
    // Bersihkan kata perintah
    $keyword = str_replace(['putar', 'play', 'tolong', 'lagu', 'musik', 'music', 'kan', 'dengarkan', 'carikan', 'cek'], '', $msg);
    $keyword = trim($keyword);
    
    if(empty($keyword) || strlen($keyword) < 3) {
        echo "Mau putar lagu apa? Ketik perintah lengkap, contoh: <b>'Putar lagu Sheila on 7'</b>.";
    } else {
        echo "🎵 <b>Siap!</b> Memutar '$keyword' di Dashboard:<br>";
        
        // PERBAIKAN UTAMA: URL ENCODE
        // Mengubah "Sheila on 7" menjadi "Sheila+on+7" agar link YouTube valid
        $search_term = urlencode($keyword);
        
        echo "<div class='mt-3 rounded-xl overflow-hidden shadow-lg border border-slate-200 bg-black'>";
        echo "<iframe width='100%' height='220' src='https://www.youtube.com/embed?listType=search&list=$search_term&autoplay=1' frameborder='0' allow='autoplay; encrypted-media' allowfullscreen></iframe>";
        echo "</div>";
    }
}

// 3. CEK TAGIHAN / BELUM LUNAS (PO/INVOICE)
elseif (strpos($msg, 'belum lunas') !== false || strpos($msg, 'hutang') !== false || strpos($msg, 'tagihan') !== false || strpos($msg, 'po belum bayar') !== false) {
    $q = mysqli_query($conn, "SELECT t.*, p.nama_pelanggan FROM transaksi t LEFT JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.jenis_transaksi='keluar' AND t.status_bayar != 'lunas' ORDER BY t.tanggal DESC LIMIT 5");
    
    if(mysqli_num_rows($q) > 0) {
        echo "📄 <b>Daftar Tagihan Belum Lunas (Terbaru):</b><br><hr class='my-2 border-dashed'>";
        while($r = mysqli_fetch_assoc($q)) {
            echo "<div class='mb-2 text-xs'>• <b>{$r['nama_pelanggan']}</b><br>No: {$r['no_faktur']} ({$r['status_bayar']})<br>Total: <span class='text-red-500 font-bold'>" . format_duit($r['total_transaksi'] - $r['bayar']) . "</span></div>";
        }
        echo "<small class='text-gray-400'>*Menampilkan 5 data terakhir.</small>";
    } else {
        echo "🎉 <b>Luar Biasa!</b> Tidak ada tagihan gantung. Semua transaksi tercatat LUNAS.";
    }
}

// 4. CEK SURAT JALAN (PENGIRIMAN)
elseif (strpos($msg, 'surat jalan') !== false || strpos($msg, 'sj') !== false || strpos($msg, 'pengiriman') !== false || strpos($msg, 'kirim') !== false) {
    $q = mysqli_query($conn, "SELECT t.*, p.nama_pelanggan FROM transaksi t LEFT JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.no_faktur LIKE 'SJ-%' ORDER BY t.tanggal DESC LIMIT 5");
    
    if(mysqli_num_rows($q) > 0) {
        echo "🚚 <b>Riwayat Surat Jalan Terakhir:</b><br><hr class='my-2 border-dashed'>";
        while($r = mysqli_fetch_assoc($q)) {
            echo "<div class='mb-2 pb-2 border-b border-gray-100 last:border-0 text-xs'><b>{$r['no_faktur']}</b> - " . date('d/m H:i', strtotime($r['tanggal'])) . "<br>Customer: {$r['nama_pelanggan']}<br>Driver: <span class='text-indigo-600 font-bold'>{$r['nama_driver']}</span> ({$r['nopol']})</div>";
        }
    } else {
        echo "Belum ada data Surat Jalan yang terekam di sistem.";
    }
}

// 5. CEK BARANG TERLARIS
elseif (strpos($msg, 'laku') !== false || strpos($msg, 'laris') !== false || strpos($msg, 'top') !== false || strpos($msg, 'favorit') !== false) {
    $q = mysqli_query($conn, "SELECT b.nama_barang, SUM(td.qty) as total_jual FROM transaksi_detail td JOIN barang b ON td.barang_id = b.id GROUP BY td.barang_id ORDER BY total_jual DESC LIMIT 3");
    
    if(mysqli_num_rows($q) > 0) {
        echo "🏆 <b>Top 3 Barang Terlaris:</b><br>";
        $no = 1;
        while($r = mysqli_fetch_assoc($q)) { echo "$no. <b>{$r['nama_barang']}</b> (Terjual: {$r['total_jual']})<br>"; $no++; }
    } else { echo "Belum ada data penjualan yang cukup untuk analisa."; }
}

// 6. CEK STOK MENIPIS / KRITIS
elseif (strpos($msg, 'habis') !== false || strpos($msg, 'tipis') !== false || strpos($msg, 'kritis') !== false || strpos($msg, 'sedikit') !== false) {
    $q = mysqli_query($conn, "SELECT nama_barang, stok FROM barang WHERE stok <= 5 ORDER BY stok ASC LIMIT 5");
    if(mysqli_num_rows($q) > 0) {
        echo "⚠️ <b>Peringatan Stok Menipis:</b><br><ul class='list-disc pl-4 mt-2 text-xs'>";
        while($r = mysqli_fetch_assoc($q)) { echo "<li><b>{$r['nama_barang']}</b> sisa <span class='text-red-500 font-bold'>{$r['stok']}</span></li>"; }
        echo "</ul><br>Segera Restock ya, $user_name!";
    } else { echo "Mantap $user_name! Semua stok terpantau aman (di atas 5 unit)."; }
}

// 7. CEK OMSET HARI INI
elseif (strpos($msg, 'omset') !== false || strpos($msg, 'pendapatan') !== false || strpos($msg, 'dapat berapa') !== false) {
    $tgl = date('Y-m-d');
    $q = mysqli_query($conn, "SELECT SUM(total_transaksi) as total, COUNT(*) as jlh FROM transaksi WHERE DATE(tanggal) = '$tgl' AND jenis_transaksi='keluar'");
    $d = mysqli_fetch_assoc($q);
    echo "💰 <b>Laporan Hari Ini (" . date('d M Y') . "):</b><br>Omset: <b class='text-green-600 text-lg'>" . format_duit($d['total'] ?? 0) . "</b><br>Total: <b>{$d['jlh']} Transaksi</b>";
}

// 8. PENCARIAN DEFAULT (DATABASE + GEMINI AI FALLBACK)
else {
    // A. Cek Database Dulu (Prioritas Data Toko)
    $clean_msg = mysqli_real_escape_string($conn, str_replace(['cek', 'stok', 'stock', 'harga', 'cari', 'lihat', 'info'], '', $msg));
    $clean_msg = trim($clean_msg);

    $cek_barang = false;
    if(strlen($clean_msg) >= 3) {
        $q_barang = mysqli_query($conn, "SELECT * FROM barang WHERE nama_barang LIKE '%$clean_msg%' OR kode_barang LIKE '%$clean_msg%' LIMIT 3");
        if(mysqli_num_rows($q_barang) > 0) $cek_barang = true;
    }

    if($cek_barang) {
        echo "📦 <b>Data Barang Ditemukan:</b><br><hr class='my-2 border-dashed'>";
        while($b = mysqli_fetch_assoc($q_barang)) {
            $status_stok = ($b['stok'] <= 0) ? "<span class='text-red-500 font-bold'>HABIS</span>" : "Stok: <b>{$b['stok']}</b>";
            echo "<div class='mb-2 pb-2 border-b border-gray-100 last:border-0 text-xs'><b>{$b['nama_barang']}</b> <span class='text-gray-400'>({$b['kode_barang']})</span><br>$status_stok | Harga: <span class='text-indigo-600 font-bold'>" . format_duit($b['harga_jual']) . "</span></div>";
        }
    } else {
        // B. Jika Tidak Ada di DB, Tanya Gemini (General Knowledge)
        $set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT api_key_gemini FROM pengaturan LIMIT 1"));
        $apiKey = trim($set['api_key_gemini'] ?? '');

        if(!empty($apiKey)) {
            $context = "Kamu adalah asisten toko pintar bernama 'CimaxBot'. Jawab pertanyaan user dengan ringkas, ramah, dan gunakan emoji jika perlu. Jangan bertele-tele. Pertanyaan: $raw_message";
            
            echo "🤖 <b>AI Menjawab:</b><br>" . kirimKeGemini($apiKey, $context);
        } else {
            echo "Maaf, barang '<b>$clean_msg</b>' tidak ditemukan di database.<br>Dan fitur Tanya Jawab AI belum aktif (API Key kosong).";
        }
    }
}
?>