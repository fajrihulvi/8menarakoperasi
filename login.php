<?php
require 'config/koneksi.php';

// Cek Session aman
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Jika sudah login sepenuhnya, lempar ke index
if(isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$error_msg = "";
$pilih_toko_mode = false; // Flag untuk menampilkan pilihan toko

// --- TANGKAP PESAN DARI INDEX JIKA SESI HABIS ---
if (isset($_GET['msg']) && $_GET['msg'] == 'sesi_habis') {
    $error_msg = "Sesi Anda telah habis. Silakan login kembali demi keamanan.";
}

// ========================================================
// SISTEM ANTI SPAM & LIMITER LOGIN
// ========================================================
$max_attempts = 5;       // Maksimal salah password per IP
$lockout_duration = 900; // 900 detik = 15 menit blokir
$is_locked = false;

// Limiter berbasis IP + database. Tidak bisa di-reset dengan menghapus cookie
// sesi, berbeda dengan limiter lama yang murni mengandalkan $_SESSION.
$ip_pemanggil = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_attempts (
    ip VARCHAR(45) NOT NULL PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    locked_until INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ambil status blokir IP ini
$percobaan_ip = 0;
$stmt_cek = mysqli_prepare($conn, "SELECT attempts, locked_until FROM login_attempts WHERE ip = ?");
if ($stmt_cek) {
    mysqli_stmt_bind_param($stmt_cek, 's', $ip_pemanggil);
    mysqli_stmt_execute($stmt_cek);
    mysqli_stmt_bind_result($stmt_cek, $db_attempts, $db_locked_until);
    if (mysqli_stmt_fetch($stmt_cek)) {
        $percobaan_ip = (int) $db_attempts;
        if ((int) $db_locked_until > time()) {
            $is_locked  = true;
            $time_left  = (int) $db_locked_until - time();
            $menit_left = ceil($time_left / 60);
            $error_msg  = "Sistem Mendeteksi Spam! Akses dari IP Anda diblokir sementara. Coba lagi dalam $menit_left menit.";
        } elseif ((int) $db_locked_until > 0) {
            // Masa hukuman sudah lewat: bersihkan catatan IP ini
            $percobaan_ip = 0;
            $stmt_clr = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE ip = ?");
            if ($stmt_clr) {
                mysqli_stmt_bind_param($stmt_clr, 's', $ip_pemanggil);
                mysqli_stmt_execute($stmt_clr);
                mysqli_stmt_close($stmt_clr);
            }
        }
    }
    mysqli_stmt_close($stmt_cek);
}
// ========================================================

// --- [LOGIKA 2: FINALISASI LOGIN SETELAH PILIH TOKO] ---
if(isset($_POST['pilih_toko']) && isset($_SESSION['temp_login'])) {
    $d = $_SESSION['temp_login'];
    $id_usaha_pilihan = $_POST['pilih_toko']; // Nilai diambil dari value tombol
    
    // Set Session Utama
    $_SESSION['login'] = true;
    $_SESSION['user_id'] = $d['id'];
    $_SESSION['nama']    = $d['nama'] ?? $d['username'];
    $_SESSION['nama_lengkap'] = $d['nama'] ?? $d['username'];
    $_SESSION['id_usaha'] = $id_usaha_pilihan; // Gunakan ID Usaha yang dipilih
    $_SESSION['level']  = $d['level'] ?? 'admin'; 
    $_SESSION['role']   = $d['level'] ?? 'admin';
    
    // MULAI PENGHITUNGAN WAKTU SESI IDLE (1 JAM)
    $_SESSION['last_activity'] = time();
    
    // COOKIE HANYA UNTUK ROLE DRIVER
    if (strtolower($_SESSION['role']) === 'driver') {
        setcookie('id_user', $d['id'], [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        setcookie('key', buat_token_driver($d['id']), [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
    }

    // Hapus sesi temporary
    unset($_SESSION['temp_login']);

    if(function_exists('catat_log')) {
        catat_log($conn, "Login", "User {$d['nama']} masuk ke Toko ID: $id_usaha_pilihan");
    }

    echo "<script>window.location='index.php';</script>";
    exit;
}

// --- [LOGIKA 1: CEK PASSWORD] ---
if(isset($_POST['login']) && !$is_locked) {
    $input_user = mysqli_real_escape_string($conn, $_POST['email']); 
    $pass       = $_POST['password'];

    $table_name = "users";
    $query = "SELECT * FROM $table_name WHERE username='$input_user'";
    $cek = mysqli_query($conn, $query);

    if(mysqli_num_rows($cek) > 0){
        $d = mysqli_fetch_assoc($cek);

        // ========================================================
        // VERIFIKASI PASSWORD
        // Hanya menerima hash bcrypt (password_hash) atau MD5 lama.
        // Password plaintext TIDAK PERNAH diterima.
        // Hash MD5 lama otomatis di-upgrade ke bcrypt saat login sukses.
        // ========================================================
        $password_valid  = false;
        $perlu_upgrade   = false;
        $hash_tersimpan  = $d['password'] ?? '';

        if (password_verify($pass, $hash_tersimpan)) {
            $password_valid = true;
            // Upgrade jika algoritma/cost bcrypt sudah usang
            if (password_needs_rehash($hash_tersimpan, PASSWORD_DEFAULT)) { $perlu_upgrade = true; }
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $hash_tersimpan)
                  && hash_equals(strtolower($hash_tersimpan), md5($pass))) {
            // Akun lama ber-hash MD5: diterima sekali, lalu langsung dinaikkan ke bcrypt
            $password_valid = true;
            $perlu_upgrade  = true;
        }

        if($password_valid) {

            // AUTO-UPGRADE HASH KE BCRYPT
            if ($perlu_upgrade) {
                $hash_baru = password_hash($pass, PASSWORD_DEFAULT);
                $stmt_up = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                if ($stmt_up) {
                    mysqli_stmt_bind_param($stmt_up, 'si', $hash_baru, $d['id']);
                    mysqli_stmt_execute($stmt_up);
                    mysqli_stmt_close($stmt_up);
                }
            }
            
            // RESET ATTEMPT JIKA BERHASIL LOGIN
            // RESET CATATAN PERCOBAAN GAGAL UNTUK IP INI
            $stmt_ok = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE ip = ?");
            if ($stmt_ok) {
                mysqli_stmt_bind_param($stmt_ok, 's', $ip_pemanggil);
                mysqli_stmt_execute($stmt_ok);
                mysqli_stmt_close($stmt_ok);
            }

            $role = strtolower($d['level'] ?? 'admin');
            
            // Role kantor/pengawas -> wajib memilih unit usaha (toko) dulu.
            // Daftarnya dipusatkan di sini agar mudah ditambah saat ada role baru.
            $role_pilih_toko = ['admin', 'super admin', 'accounting', 'invoice', 'gudang', 'viewer'];
            if(in_array($role, $role_pilih_toko, true)) {
                $_SESSION['temp_login'] = $d; // Simpan data user sementara
                $pilih_toko_mode = true;      // Aktifkan tampilan pilih toko
            } else {
                // Untuk role lain (termasuk driver & pelanggan) langsung login
                $_SESSION['login'] = true;
                $_SESSION['user_id'] = $d['id'];
                $_SESSION['nama']    = $d['nama'] ?? $d['username'];
                $_SESSION['nama_lengkap'] = $d['nama'] ?? $d['username'];
                $_SESSION['id_usaha'] = $d['id_usaha']; 
                $_SESSION['level']  = $role; 
                $_SESSION['role']   = $role;
                
                // MULAI PENGHITUNGAN WAKTU SESI IDLE (1 JAM)
                $_SESSION['last_activity'] = time();
                
                // COOKIE HANYA UNTUK ROLE DRIVER
                if ($role === 'driver') {
                    setcookie('id_user', $d['id'], [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => !empty($_SERVER['HTTPS']),
                    ]);
                    setcookie('key', buat_token_driver($d['id']), [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => !empty($_SERVER['HTTPS']),
                    ]);
                }
                
                if(function_exists('catat_log')) {
                    catat_log($conn, "Login", "User berhasil masuk.");
                }
                echo "<script>window.location='index.php';</script>";
                exit;
            }
        } else {
            $salah_login = true;
        }
    } else {
        $salah_login = true;
    }

    // JIKA PASSWORD / USERNAME SALAH TERTANGKAP DI SINI
    if (isset($salah_login)) {
        $percobaan_ip = $percobaan_ip + 1;
        $kunci_sampai = ($percobaan_ip >= $max_attempts) ? (time() + $lockout_duration) : 0;

        // Catat percobaan gagal per IP di database (tahan terhadap penghapusan cookie)
        $stmt_gagal = mysqli_prepare($conn,
            "INSERT INTO login_attempts (ip, attempts, locked_until) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)");
        if ($stmt_gagal) {
            mysqli_stmt_bind_param($stmt_gagal, 'sii', $ip_pemanggil, $percobaan_ip, $kunci_sampai);
            mysqli_stmt_execute($stmt_gagal);
            mysqli_stmt_close($stmt_gagal);
        }

        if ($percobaan_ip >= $max_attempts) {
            $menit = ceil($lockout_duration / 60);
            $error_msg = "Peringatan! Anda salah $max_attempts kali berturut-turut. Sistem mengunci akses selama $menit Menit.";
            $is_locked = true; // Langsung kunci form di halaman ini
        } else {
            $sisa = $max_attempts - $percobaan_ip;
            $error_msg = "Username atau Password Salah! (Sisa percobaan: $sisa)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login System - 8MP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', sans-serif; 
            min-height: 100vh; /* Menggunakan min-height agar bisa menyesuaikan konten */
            color: #333;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px; /* Jarak aman di perangkat kecil */
            overflow-y: auto; /* Mengizinkan scroll jika layar terlalu pendek */
            position: relative;
        }

        /* ---------------------------------------------------- */
        /* BACKGROUND VIDEO FULLSCREEN & RESPONSIVE */
        /* ---------------------------------------------------- */
        .bg-video {
            position: fixed;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            z-index: -2;
            transform: translateX(-50%) translateY(-50%);
            object-fit: cover;
        }

        /* OVERLAY GRADASI */
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(30, 41, 59, 0.2), rgba(15, 23, 42, 0.4));
            z-index: -1;
        }
        
        /* ---------------------------------------------------- */
        /* ANIMASI KOTAK KACA MELAYANG (FLOAT) */
        /* ---------------------------------------------------- */
        .animated-boxes {
            position: fixed; /* Ubah ke fixed agar tidak mengganggu tinggi body */
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            overflow: hidden;
            z-index: 0; 
            margin: 0; padding: 0;
            pointer-events: none; /* Klik tembus ke form */
        }
        .animated-boxes li {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px; height: 20px;
            background: rgba(255, 255, 255, 0.15); 
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
            animation: floatUp 25s linear infinite;
            bottom: -150px;
            border-radius: 8px;
        }
        .animated-boxes li:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .animated-boxes li:nth-child(2) { left: 10%; width: 30px; height: 30px; animation-delay: 2s; animation-duration: 12s; }
        .animated-boxes li:nth-child(3) { left: 70%; width: 40px; height: 40px; animation-delay: 4s; }
        .animated-boxes li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .animated-boxes li:nth-child(5) { left: 65%; width: 35px; height: 35px; animation-delay: 0s; }
        .animated-boxes li:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .animated-boxes li:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .animated-boxes li:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .animated-boxes li:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .animated-boxes li:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }

        @keyframes floatUp {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 8px; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
        }

        /* ---------------------------------------------------- */
        /* DESAIN KOTAK LOGIN / KONTEN */
        /* ---------------------------------------------------- */
        .login-container { 
            position: relative; /* Bukan absolute lagi, agar flexbox yang mengatur posisi */
            margin: auto; /* Tengah secara otomatis */
            z-index: 10; 
            background: rgba(255, 255, 255, 0.35); /* Lebih transparan (0.35) */
            backdrop-filter: blur(12px); /* Blur sedikit diturunkan agar video terlihat */
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5); 
            border-radius: 20px; 
            padding: 40px; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.3); 
            animation: formFloat 6s ease-in-out infinite; 
        }
        
        /* Animasi diubah karena tidak lagi menggunakan absolute positioning (-50%) */
        @keyframes formFloat { 
            0%, 100% { transform: translateY(0px); } 
            50% { transform: translateY(-10px); } 
        }
        
        .logo { text-align: center; margin-bottom: 20px; }
        
        .logo-img { 
            height: 70px; 
            width: auto; 
            margin: 0 auto 15px; 
            display: block; 
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15));
        }

        .logo-icon { width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); }
        
        h1 { font-size: 26px; font-weight: 800; text-align: center; margin-bottom: 5px; color: #1e293b; }
        .subtitle { color: #475569; text-align: center; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { color: #1e293b; font-size: 12px; font-weight: 800; text-transform: uppercase; margin-bottom: 8px; display: block; }
        
        .form-group input { 
            width: 100%; 
            background: rgba(255, 255, 255, 0.6); /* Diubah agar serasi dengan background transparan */
            border: 1px solid #cbd5e1; 
            border-radius: 12px; 
            padding: 15px 20px; 
            font-size: 15px; 
            font-weight: 500; 
            color: #334155; 
            outline: none; 
            transition: 0.3s; 
        }
        .form-group input:focus { border-color: #667eea; background: rgba(255, 255, 255, 0.9); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15); }
        
        .password-toggle { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; padding: 5px; transition: color 0.2s; }
        .password-toggle:hover { color: #1e293b; }

        .login-btn { width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 12px; padding: 16px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; margin-bottom: 15px;}
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); }
        
        .error-message { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: bold; margin-bottom: 20px; text-align: center; border: 1px solid #fecaca; }
        
        /* Tombol Unduh App */
        .btn-download { display: inline-block; padding: 10px 20px; background: rgba(255, 255, 255, 0.6); color: #1e293b; border-radius: 8px; font-size: 13px; font-weight: bold; text-decoration: none; border: 1px solid #cbd5e1; transition: 0.2s; }
        .btn-download:hover { background: #e2e8f0; color: #0f172a; }

        /* Tombol Pilih Toko */
        .store-btn { display: block; width: 100%; text-align: left; padding: 15px; margin-bottom: 12px; background: rgba(255, 255, 255, 0.7); border: 1px solid #cbd5e1; border-radius: 12px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; text-decoration: none; color: #333; }
        .store-btn:hover { background: rgba(255, 255, 255, 0.95); border-color: #667eea; transform: translateX(5px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .store-icon { width: 42px; height: 42px; background: #e0e7ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: #4f46e5; font-size: 18px; flex-shrink: 0; }
        .store-info h4 { font-size: 15px; margin-bottom: 2px; color: #1e293b; font-weight: 700; }
        .store-info p { font-size: 12px; color: #475569; font-weight: 600; }
        
        /* --- KHUSUS TAMPILAN MOBILE (HP) --- */
        @media (max-width: 480px) { 
            .login-container { 
                padding: 30px 25px; 
                animation: none; /* Matikan animasi melayang di HP */
            }
            .logo-img { height: 60px; margin-bottom: 10px; }
            .logo-icon { width: 50px; height: 50px; font-size: 20px; margin-bottom: 10px; }
            h1 { font-size: 22px; }
            .subtitle { margin-bottom: 15px; font-size: 13px; }
            .form-group input { padding: 14px; font-size: 14px; }
            .login-btn { padding: 14px; font-size: 15px; }
            .store-btn { padding: 12px; margin-bottom: 10px; }
            .store-icon { width: 38px; height: 38px; font-size: 16px; margin-right: 12px; }
        }
    </style>
</head>
<body>

    <video autoplay muted loop playsinline class="bg-video">
        <source src="assets/img/GAMBAR_KOPERSI_DELAPAN_MENARA.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <ul class="animated-boxes">
        <li></li><li></li><li></li><li></li><li></li>
        <li></li><li></li><li></li><li></li><li></li>
    </ul>

    <div class="login-container">
        
        <?php if(!$pilih_toko_mode): ?>
            <div class="logo">
                <img src="assets/img/logo_1769284290.PNG" alt="8MP Logo" class="logo-img">
                
                <h1>8MP SYSTEM</h1>
                <p class="subtitle">Akses sistem manajemen logistik & gudang.</p>
            </div>
            
            <?php if($error_msg): ?>
            <div class="error-message"><i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Username</label>
                    <input type="text" id="email" name="email" placeholder="Masukkan username" required autocomplete="off" <?= $is_locked ? 'disabled style="background:#e2e8f0; cursor:not-allowed;"' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required <?= $is_locked ? 'disabled style="background:#e2e8f0; cursor:not-allowed;"' : '' ?>>
                        <button type="button" class="password-toggle" onclick="togglePassword()" <?= $is_locked ? 'disabled' : '' ?>>
                            <svg id="password-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login" class="login-btn" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                    <?= $is_locked ? '<i class="fa-solid fa-lock"></i> SISTEM TERKUNCI' : 'Masuk Ke Sistem' ?>
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 10px;">
                <a href="uploads/app/gps-tracker.apk" class="btn-download">
                    <i class="fa-brands fa-android text-green-500 mr-1"></i> Unduh App Android
                </a>
            </div>

        <?php else: ?>
            <div class="logo">
                <div class="logo-icon" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);"><i class="fa-solid fa-building"></i></div>
                <h1>PILIH AKSES UNIT</h1>
                <p class="subtitle">Selamat datang <b><?= htmlspecialchars($_SESSION['temp_login']['nama'] ?? 'User') ?></b>.<br>Silakan pilih unit yang ingin dikelola:</p>
            </div>
            
            <form method="POST">
                <button type="submit" name="pilih_toko" value="1" class="store-btn">
                    <div class="store-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="store-info">
                        <h4>Koperasi 8 Menara Persada</h4>
                        <p>Unit Induk & Gudang Pusat</p>
                    </div>
                </button>

                <button type="submit" name="pilih_toko" value="2" class="store-btn">
                    <div class="store-icon"><i class="fa-solid fa-bread-slice"></i></div>
                    <div class="store-info">
                        <h4>Roti La Mira</h4>
                        <p>Unit Produksi & Bakery</p>
                    </div>
                </button>
            </form>
            <div style="text-align: center; margin-top: 15px;">
                <a href="login.php" style="color: #64748b; font-size: 13px; text-decoration: none; font-weight: 600;"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Login</a>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 25px; color: #1e293b; font-size: 12px; font-weight: 700;">
            &copy; <?= date('Y') ?> IT SOLUTION System Pro
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const pi = document.getElementById('password'); 
            const icon = document.getElementById('password-icon');
            if (pi.type === 'password') { 
                pi.type = 'text'; 
                icon.innerHTML = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`; 
            } 
            else { 
                pi.type = 'password'; 
                icon.innerHTML = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`; 
            }
        }
    </script>
</body>
</html>