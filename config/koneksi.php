<?php
ini_set('max_execution_time', 30);
// =========================================================================
// DETEKSI ENVIRONMENT (DEVELOPMENT / PRODUCTION)
// =========================================================================
// Sistem menentukan sendiri sedang berjalan di komputer lokal (XAMPP) atau
// di server hosting, berdasarkan alamat yang diakses. Jadi file ini TIDAK
// perlu diubah lagi saat di-upload ke hosting — langsung jalan di keduanya.
//
// Kalau suatu saat ingin memaksa environment tertentu (misal untuk menguji
// setelan produksi di komputer lokal), cukup buka komentar baris di bawah:
//     define('APP_ENV', 'production');
// =========================================================================
if (!defined('APP_ENV')) {
    // Penentu utama: NAMA HOST yang dipakai mengakses aplikasi (identitas server),
    // bukan alamat IP pengunjung. Pengunjung produksi bisa datang dari mana saja,
    // dan aplikasi lokal bisa dibuka dari perangkat lain di jaringan yang sama —
    // jadi REMOTE_ADDR bukan penanda environment yang benar.
    $__host_ini = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $__host_ini = preg_replace('/:\d+$/', '', $__host_ini); // buang nomor port

    $__is_local = in_array($__host_ini, ['localhost', '127.0.0.1', '::1', '[::1]', ''], true)
        || preg_match('/^192\.168\./', $__host_ini)              // jaringan lokal
        || preg_match('/^10\./', $__host_ini)                    // jaringan lokal
        || preg_match('/\.(test|local|localhost)$/i', $__host_ini);

    // Dijalankan lewat terminal (CLI) selalu dianggap development
    if (PHP_SAPI === 'cli') { $__is_local = true; }

    define('APP_ENV', $__is_local ? 'development' : 'production');
    unset($__host_ini, $__is_local);
}

define('IS_DEV', APP_ENV === 'development');

// =========================================================================
// KONFIGURASI PER ENVIRONMENT
// =========================================================================
if (IS_DEV) {
    // ------------------- DEVELOPMENT (XAMPP di komputer) -------------------
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = '8mp_gudang';
} else {
    // ------------------- PRODUCTION (server hosting) ----------------------
    // GANTI 4 baris di bawah dengan data database dari cPanel hosting Anda.
    $db_host = 'localhost';
    $db_user = 'u838890447_8mpersada';
    $db_pass = 'bW8Z^a7&Kp';
    $db_name = 'u838890447_8mpersada';
}

// --- ERROR REPORTING ---
// Development: error ditampilkan agar mudah dilacak.
// Production : error DISEMBUNYIKAN dari pengunjung (hanya dicatat ke log),
//              karena isinya membocorkan path server dan struktur query.
ini_set('display_errors', IS_DEV ? '1' : '0');
ini_set('display_startup_errors', IS_DEV ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(IS_DEV ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED));

// --- SESSION START (Aman dari error) ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ========================================================
// DEFINISIKAN CONSTANT DUMMY LISENSI
// (Mencegah error/blank di Dashboard jika variabel ini dipanggil)
// ========================================================
if (!defined('LISENSI_KODE_SENSOR')) {
    define('LISENSI_KODE_SENSOR', 'PRO-UNLIMITED');
    define('LISENSI_EXPIRED', 'Lifetime');
}

// ========================================================
// --- TIMEZONE SISI PHP ---
date_default_timezone_set('Asia/Jakarta');

// --- KONEKSI DATABASE (kredensial diambil dari blok environment di atas) ---
// Variabel lama $host/$user/$pass/$db tetap disediakan agar file-file yang
// terlanjur memakainya tidak error.
$host = $db_host;
$user = $db_user;
$pass = $db_pass;
$db   = $db_name;

$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    if (IS_DEV) {
        // Di lokal: tampilkan penyebab aslinya supaya mudah diperbaiki
        die('Koneksi Database Gagal: ' . mysqli_connect_error()
            . '<br><small>Environment: ' . APP_ENV . ' — periksa setelan di config/koneksi.php</small>');
    }
    // Di produksi: jangan bocorkan detail server ke pengunjung, cukup catat ke log
    error_log('[8MP] Koneksi database gagal: ' . mysqli_connect_error());
    http_response_code(503);
    die('Sistem sedang tidak dapat terhubung ke database. Silakan coba beberapa saat lagi.');
}

// Pastikan encoding konsisten (mencegah teks berubah jadi tanda tanya)
mysqli_set_charset($conn, 'utf8mb4');

// --- TIMEZONE SISI MYSQL (PERBAIKAN WAKTU) ---
mysqli_query($conn, "SET time_zone = '+07:00'");

// =============================================================
// SECRET SERVER UNTUK TANDA TANGAN COOKIE
// Dibuat otomatis sekali, disimpan di luar akses web (config/).
// Tanpa secret ini, cookie driver tidak bisa dipalsukan dari luar.
// =============================================================
if (!defined('APP_SECRET')) {
    $__secret_file = __DIR__ . '/secret_key.php';
    if (!file_exists($__secret_file)) {
        $__generated = bin2hex(random_bytes(32));
        @file_put_contents(
            $__secret_file,
            "<?php\n// Dibuat otomatis. JANGAN dibagikan / commit ke repository.\nreturn '" . $__generated . "';\n",
            LOCK_EX
        );
        @chmod($__secret_file, 0600);
    }
    $__loaded = @include $__secret_file;
    define('APP_SECRET', is_string($__loaded) && $__loaded !== '' ? $__loaded : 'fallback-insecure-key');
    unset($__secret_file, $__generated, $__loaded);
}

// Membuat token cookie driver: HMAC-SHA256 atas id user, dikunci APP_SECRET.
if (!function_exists('buat_token_driver')) {
    function buat_token_driver($user_id) {
        return hash_hmac('sha256', 'driver|' . $user_id, APP_SECRET);
    }
}

// =============================================================
// FITUR ANTI LOGOUT (AUTO LOGIN BY COOKIE) - KHUSUS DRIVER
// =============================================================
if (empty($_SESSION['login'])) {
    if (isset($_COOKIE['id_user']) && isset($_COOKIE['key'])) {
        $id_cookie  = (int) $_COOKIE['id_user'];
        $key_cookie = (string) $_COOKIE['key'];

        $stmt_ck = mysqli_prepare($conn, "SELECT id, nama, role, id_usaha FROM users WHERE id = ? LIMIT 1");
        if ($stmt_ck) {
            mysqli_stmt_bind_param($stmt_ck, 'i', $id_cookie);
            mysqli_stmt_execute($stmt_ck);
            $res_ck = mysqli_stmt_get_result($stmt_ck);
            $row = $res_ck ? mysqli_fetch_assoc($res_ck) : null;
            mysqli_stmt_close($stmt_ck);

            if ($row) {
                // SYARAT KETAT: Tanda tangan HMAC cocok & Role WAJIB Driver.
                // hash_equals mencegah kebocoran lewat timing attack.
                $token_sah = buat_token_driver($row['id']);
                if (hash_equals($token_sah, $key_cookie) && strtolower($row['role']) === 'driver') {
                    $_SESSION['login']    = true;
                    $_SESSION['user_id']  = $row['id'];
                    $_SESSION['nama']     = $row['nama'];
                    $_SESSION['role']     = $row['role'];
                    $_SESSION['id_usaha'] = $row['id_usaha'];
                }
            }
        }
    }
}

// =============================================================
// PENJAGA ENDPOINT AJAX
// Dipanggil di awal file ajax_*.php agar tidak bisa diakses tanpa login.
// $roles = daftar role yang diizinkan (kosong = semua yang sudah login).
// =============================================================
if (!function_exists('wajib_login_ajax')) {
    function wajib_login_ajax(array $roles = [], $format = 'json') {
        $sudah_login = !empty($_SESSION['login']) && !empty($_SESSION['user_id']);
        $role_user   = strtolower($_SESSION['role'] ?? '');
        $boleh       = $sudah_login && (empty($roles) || in_array($role_user, array_map('strtolower', $roles), true));

        if (!$boleh) {
            http_response_code(403);
            if ($format === 'json') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Akses ditolak. Silakan login kembali.']);
            } else {
                echo 'Akses ditolak. Silakan login kembali.';
            }
            exit;
        }
    }
}

// --- FUNGSI FORMAT RUPIAH ---
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka){
        return "Rp " . number_format($angka, 0, ',', '.');
    }
}

// --- FUNGSI PENCATAT LOG (SISTEM CCTV) ---
if (!function_exists('catat_log')) {
    function catat_log($koneksi, $aksi, $detail) {
        $uid = $_SESSION['user_id'] ?? 0;
        $unama = $_SESSION['nama'] ?? 'System';
        $urole = $_SESSION['role'] ?? 'Guest';
        $tgl = date('Y-m-d H:i:s');
        
        $detail_clean = mysqli_real_escape_string($koneksi, $detail);
        $aksi_clean = mysqli_real_escape_string($koneksi, $aksi);
        $unama_clean = mysqli_real_escape_string($koneksi, $unama);
        
        $sql = "INSERT INTO log_aktivitas (user_id, nama_user, role, aksi, detail, tanggal)
                VALUES ('$uid', '$unama_clean', '$urole', '$aksi_clean', '$detail_clean', '$tgl')";
       
        mysqli_query($koneksi, $sql);
    }
}

// =============================================================
// PUSAT HAK AKSES (RBAC)
// Semua aturan role & permission ada di config/hak_akses.php
// =============================================================
require_once __DIR__ . '/hak_akses.php';

?>