<?php
wajib_akses('cek_gizi');

// pages/cek_gizi.php
// FITUR: CEK KALORI (POWERED BY GEMINI AI - AUTO DISCOVER MODEL)

$role_saat_ini = $_SESSION['role'] ?? '';
if (!in_array($role_saat_ini, ['admin', 'ahli_gizi'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$hasil = null;
$error = null;

// FUNGSI KHUSUS MEMANGGIL GEMINI
function panggilGemini($apiKey, $model, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $apiKey;
    $data = [
        "contents" => [["parts" => [["text" => $prompt]]]],
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
        ],
        "generationConfig" => [
            "temperature" => 0.1 // Kurangi imajinasi AI agar format tidak ngawur
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

if (isset($_POST['cek_api'])) {
    $input_asli = trim($_POST['query']);
    
    // Ambil API Key Gemini dari tabel pengaturan
    $set = mysqli_fetch_assoc(mysqli_query($conn, "SELECT api_key_gemini FROM pengaturan LIMIT 1"));
    $apiKey = trim($set['api_key_gemini'] ?? '');

    if(empty($apiKey)) {
        $error = "API Key Gemini belum diseting! Silakan masukkan API Key di menu Pengaturan.";
    } else {
        $prompt = "Kamu adalah ahli gizi profesional. Tugasmu menganalisis kandungan gizi dari makanan berikut: '$input_asli'. " .
                  "Estimasi akurat berdasarkan database gizi umum (USDA/AKG). " .
                  "PENTING: Wajib balas HANYA dengan array JSON murni, tanpa teks awalan/akhiran. " .
                  "Format persis seperti ini: " .
                  "[{\"name\": \"Nama Makanan\", \"calories\": 150, \"serving_size_g\": 100, \"protein_g\": 5.5, \"fat_total_g\": 2.0, \"carbohydrates_total_g\": 20.5}]. " .
                  "Pisahkan makanan jika inputnya lebih dari satu jenis.";

        // 1. COBA MENGGUNAKAN MODEL LATEST
        $res = panggilGemini($apiKey, "gemini-1.5-flash-latest", $prompt);

        // 2. JIKA MODEL TIDAK DITEMUKAN, AUTO-DISCOVER MODEL YANG TERSEDIA
        if (isset($res['error']) && strpos(strtolower($res['error']['message']), 'not found') !== false) {
            
            // Tanya Google model apa saja yang tersedia di API Key ini
            $listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
            $ch = curl_init($listUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $listResponse = curl_exec($ch);
            curl_close($ch);
            $listResult = json_decode($listResponse, true);

            if(isset($listResult['models'])) {
                foreach($listResult['models'] as $m) {
                    if(isset($m['supportedGenerationMethods']) && in_array("generateContent", $m['supportedGenerationMethods'])) {
                        // Bersihkan string 'models/' dari nama
                        $modelBaru = str_replace("models/", "", $m['name']);
                        
                        // Eksekusi ulang dengan model yang ditemukan
                        $res = panggilGemini($apiKey, $modelBaru, $prompt);
                        if (!isset($res['error'])) {
                            break; // Jika berhasil, hentikan pencarian
                        }
                    }
                }
            }
        }

        // 3. PENGECEKAN ERROR & PROSES DATA
        if (isset($res['error'])) {
            $error = "API Error: " . $res['error']['message'];
        } elseif (isset($res['candidates'][0]['finishReason']) && $res['candidates'][0]['finishReason'] !== 'STOP') {
            $error = "AI memblokir balasan karena sensor keamanan: " . $res['candidates'][0]['finishReason'];
        } elseif (isset($res['candidates'][0]['content']['parts'][0]['text'])) {
            
            $text_resp = trim($res['candidates'][0]['content']['parts'][0]['text']);
            
            // Jaring Pengaman Regex: Mengambil HANYA teks di dalam kurung siku JSON [...]
            if (preg_match('/\[.*\]/s', $text_resp, $matches)) {
                $text_resp = $matches[0];
            }

            $data_json = json_decode($text_resp, true);
            
            // Auto-fix jika AI bandel mengembalikan 1 Objek {} dan bukan Array [{}]
            if (is_array($data_json) && isset($data_json['name'])) {
                $data_json = [$data_json];
            }

            if (is_array($data_json) && count($data_json) > 0) {
                $hasil = $data_json;
            } else {
                $error = "AI merespon tapi format data rusak. Respon Mentah: " . htmlspecialchars(substr($text_resp, 0, 150));
            }
            
        } else {
            $error = "Respon AI Tidak Dikenali. Coba refresh halaman.";
        }
    }
}
?>

<div class="min-h-screen bg-slate-50 p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><i class="fa-solid fa-utensils text-emerald-500 mr-2"></i> Cek Gizi Lengkap AI</h1>
                <p class="text-slate-500 text-sm">Hitung kalori dan makronutrisi dari makanan sehari-hari Anda.</p>
            </div>
            <span class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full text-xs font-bold border border-indigo-200 shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-brain"></i> Powered by IT SOLUTION
            </span>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <form method="POST" class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Masukkan Menu Makanan (Bisa campuran)</label>
                    <input type="text" name="query" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-emerald-500 text-lg" 
                           placeholder="Contoh: 1 porsi ayam bakar, 100g tempe, dan es teh manis" required value="<?= $_POST['query'] ?? '' ?>">
                </div>
                <button type="submit" name="cek_api" class="bg-emerald-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-emerald-700 transition h-fit md:mt-6 shadow-lg hover:shadow-emerald-200">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2"></i> Analisis AI
                </button>
            </form>
            <p class="text-xs text-slate-400 mt-3 italic"><i class="fa-solid fa-circle-info mr-1"></i> AI akan mendeteksi makanan secara pintar, baik menggunakan satuan porsi, sendok, maupun gram.</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl border border-red-200 text-left mb-6 shadow-sm">
                <p class="font-bold text-lg"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Kendala Ditemukan</p>
                <p class="text-sm mt-2 font-mono bg-white/50 p-3 rounded-lg border border-red-100"><?= $error ?></p>
            </div>
        <?php endif; ?>

        <?php if($hasil): ?>
            <div class="bg-indigo-50 text-indigo-700 px-4 py-3 rounded-lg mb-4 text-xs border border-indigo-100 font-medium shadow-sm">
                <i class="fa-solid fa-check-circle mr-1"></i> AI berhasil menguraikan: <b>"<?= htmlspecialchars($_POST['query']) ?>"</b>
            </div>

            <div class="grid gap-6 animate-fade-in">
                <?php 
                $total_cal = 0; 
                foreach($hasil as $item): 
                    $cal = (float) ($item['calories'] ?? 0);
                    $total_cal += $cal;
                    $nama_makanan = $item['name'] ?? 'Menu Tidak Diketahui';
                ?>
                
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition">
                    <div class="bg-emerald-50 p-4 border-b border-emerald-100 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-emerald-600 font-bold capitalize text-lg shadow-sm">
                                <?= substr($nama_makanan, 0, 1) ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 capitalize text-lg leading-tight"><?= $nama_makanan ?></h3>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="block text-2xl font-bold text-emerald-600"><?= number_format($cal, 1) ?></span>
                            <span class="text-xs text-emerald-600 font-bold uppercase">Kalori (kkal)</span>
                        </div>
                    </div>

                    <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        
                        <div class="p-2 rounded-lg bg-slate-50 border border-slate-100">
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Estimasi Porsi</p>
                            <p class="text-lg font-bold text-slate-700"><?= $item['serving_size_g'] ?? 0 ?>g</p>
                        </div>

                        <div class="p-2 rounded-lg bg-blue-50 border border-blue-100">
                            <p class="text-[10px] text-blue-400 font-bold uppercase tracking-wider mb-1">Protein</p>
                            <p class="text-lg font-bold text-blue-700"><?= is_numeric($item['protein_g'] ?? null) ? number_format($item['protein_g'], 1) : 0 ?>g</p>
                        </div>

                        <div class="p-2 rounded-lg bg-red-50 border border-red-100">
                            <p class="text-[10px] text-red-400 font-bold uppercase tracking-wider mb-1">Lemak Total</p>
                            <p class="text-lg font-bold text-red-700"><?= is_numeric($item['fat_total_g'] ?? null) ? number_format($item['fat_total_g'], 1) : 0 ?>g</p>
                        </div>

                        <div class="p-2 rounded-lg bg-yellow-50 border border-yellow-100">
                            <p class="text-[10px] text-yellow-500 font-bold uppercase tracking-wider mb-1">Karbohidrat</p>
                            <p class="text-lg font-bold text-yellow-700"><?= is_numeric($item['carbohydrates_total_g'] ?? null) ? number_format($item['carbohydrates_total_g'], 1) : 0 ?>g</p>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-5 rounded-xl font-bold text-center text-xl shadow-lg mt-2 flex justify-between items-center px-4 md:px-8">
                    <span class="text-sm md:text-xl">TOTAL ENERGI</span>
                    <span><?= number_format($total_cal, 1) ?> kkal</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
</style>