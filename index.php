<?php
ob_start(); // TAMBAHKAN BARIS INI AGAR EXPORT EXCEL TIDAK ERROR
session_start();
require 'config/koneksi.php';

// ========================================================
// SECURITY GATE: PROTEKSI GLOBAL MUTLAK UNTUK SEMUA USER
// ========================================================
// Mengecek dengan ketat apakah status login, nama, role, dan user_id benar-benar valid
if (empty($_SESSION['login']) || empty($_SESSION['nama']) || empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    // Hancurkan sisa-sisa sesi setengah matang agar tidak error
    session_unset();
    session_destroy();
    
    // Lempar paksa ke halaman login tanpa kompromi
    header("Location: login.php?msg=sesi_habis");
    exit();
}
// ========================================================

// ========================================================
// TAMBAHAN FITUR: SISTEM AUTO-LOGOUT 1 JAM (Kecuali Driver)
// ========================================================
$timeout_duration = 3600; // 3600 detik = 1 jam

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) !== 'driver') {
    // Cek jika user sedang idle (tidak ada aktivitas klik/refresh melebihi 1 jam)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php?msg=sesi_habis");
        exit();
    }
}
// Perbarui waktu aktivitas terakhir pengguna agar timer 1 jam direset ulang
$_SESSION['last_activity'] = time();
// ========================================================


// ========================================================
// Routing Halaman (AMAN DARI LOCAL FILE INCLUSION)
// Nama halaman dibatasi huruf/angka/underscore/dash saja,
// lalu dipastikan hasil akhirnya benar-benar berada di dalam folder pages/.
// ========================================================
$page = $_GET['page'] ?? 'dashboard';

if (!preg_match('/^[A-Za-z0-9_-]+$/', $page)) {
    $page = 'dashboard';
}

$dir_pages  = realpath(__DIR__ . '/pages');
$file_page  = $dir_pages . DIRECTORY_SEPARATOR . $page . '.php';
$real_page  = realpath($file_page);

// Tolak jika file keluar dari folder pages/ (symlink, traversal, dll)
if ($real_page === false || strpos($real_page, $dir_pages . DIRECTORY_SEPARATOR) !== 0) {
    $file_page = $dir_pages . DIRECTORY_SEPARATOR . 'dashboard.php';
    $page      = 'dashboard';
}

// ========================================================
// PENJAGA HAK AKSES TERPUSAT (RBAC)
// Aturan lengkap ada di config/hak_akses.php.
// Halaman yang tidak diizinkan untuk role ini langsung dialihkan,
// sehingga tidak bisa dibuka lewat URL walaupun menunya disembunyikan.
// ========================================================
$halaman_bebas = ['dashboard']; // selalu boleh dibuka user yang sudah login

if (!in_array($page, $halaman_bebas, true) && !boleh_buka($page)) {
    $_SESSION['pesan_akses_ditolak'] = 'Role ' . strtoupper(role_saya())
        . ' tidak memiliki izin membuka halaman "' . $page . '".';
    $page      = 'dashboard';
    $file_page = $dir_pages . DIRECTORY_SEPARATOR . 'dashboard.php';
}
// ========================================================

// Init ID (Legacy)
$id_usaha_session = $_SESSION['id_usaha'] ?? 0;
$q_init = mysqli_query($conn, "SELECT MAX(id) as max_id FROM pesanan WHERE id_usaha = '$id_usaha_session'");
$d_init = mysqli_fetch_assoc($q_init);
$initial_last_id = $d_init['max_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>8MP System V1</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="assets/img/icon-192.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .fade-in { animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Chat Box Animation */
        .chat-box { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform-origin: bottom right; }
        .chat-hide { opacity: 0; transform: scale(0.9); pointer-events: none; display: none; }
        .chat-show { opacity: 1; transform: scale(1); pointer-events: auto; display: flex; }
        
        /* Scrollbar Halus */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        
        /* Animasi Tombol Berdenyut */
        .btn-pulse { animation: pulse-red 1.5s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } /* Diubah ke warna hijau/emerald agar cocok dgn tombol */
            70% { box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Animasi Teks Dering */
        .ring-anim { animation: shake 0.5s infinite; }
        @keyframes shake {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(5deg); }
            50% { transform: rotate(0deg); }
            75% { transform: rotate(-5deg); }
            100% { transform: rotate(0deg); }
        }

        /* TAMBAHAN: Animasi Popup Masuk (Mengambang Halus dari Kanan) */
        @keyframes slideInRight {
            0% { transform: translateX(120%); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        .slide-in-right { 
            animation: slideInRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800">

    <div class="flex min-h-screen relative z-0">
        <div class="z-40 relative">
            <?php include 'layout/sidebar.php'; ?>
        </div>

        <main class="flex-1 md:ml-64 transition-all duration-300 w-full relative overflow-x-hidden z-0">
            <header class="flex justify-between items-center mb-8 sticky top-0 z-30 bg-[#f8fafc]/90 backdrop-blur-sm px-4 md:px-8 py-4 border-b border-transparent hover:border-slate-200 transition-colors">
                <div>
                    <p class="text-sm font-bold text-slate-500 flex items-center gap-2">
                        <i class="fa-regular fa-calendar"></i> <?= date('l, d F Y') ?>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="testAudio()" class="w-9 h-9 rounded-full bg-white border border-slate-200 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 flex items-center justify-center transition shadow-sm" title="Tes Suara Notifikasi">
                        <i class="fa-solid fa-volume-high"></i>
                    </button>

                    <button onclick="toggleChat()" class="w-9 h-9 rounded-full bg-white border border-slate-200 text-indigo-600 hover:bg-indigo-50 flex items-center justify-center transition shadow-sm md:hidden">
                        <i class="fa-solid fa-comments"></i>
                    </button>
                    <div class="relative" id="userMenuWrapper">
                        <div onclick="toggleUserMenu()" class="flex items-center gap-3 bg-white pl-4 pr-2 py-1.5 rounded-full border border-slate-200 shadow-sm cursor-pointer hover:shadow-md transition">
                            <div class="text-right hidden md:block">
                                <p class="text-xs font-bold text-slate-700 leading-none"><?= htmlspecialchars($_SESSION['nama'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="text-[10px] text-indigo-500 font-bold uppercase tracking-wide bg-indigo-50 px-2 py-0.5 rounded-full mt-1 inline-block">
                                    <?= htmlspecialchars($_SESSION['role'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-sm shadow-inner">
                                <?= substr($_SESSION['nama'] ?? 'U', 0, 1) ?>
                            </div>
                        </div>

                        <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-50">
                            <button onclick="bukaModalPassword()" class="w-full text-left px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 flex items-center gap-2 transition">
                                <i class="fa-solid fa-key text-indigo-500 w-4"></i> Ganti Password
                            </button>
                            <a href="logout.php" class="w-full text-left px-4 py-2.5 text-sm font-semibold text-rose-500 hover:bg-rose-50 flex items-center gap-2 transition">
                                <i class="fa-solid fa-right-from-bracket w-4"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="fade-in min-h-[80vh]">
                <?php if (!empty($_SESSION['pesan_akses_ditolak'])): ?>
                    <div class="mb-5 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-sm">
                        <p class="font-bold"><i class="fa-solid fa-ban mr-2"></i>Akses Ditolak</p>
                        <p class="text-sm"><?= htmlspecialchars($_SESSION['pesan_akses_ditolak']) ?></p>
                    </div>
                    <?php unset($_SESSION['pesan_akses_ditolak']); ?>
                <?php endif; ?>

                <?php if (saya_readonly()): ?>
                    <div class="mb-5 bg-amber-50 border-l-4 border-amber-400 text-amber-800 p-3 rounded-lg flex items-center gap-2">
                        <i class="fa-solid fa-eye"></i>
                        <span class="text-sm font-semibold">Mode Lihat Saja &mdash; Anda masuk sebagai Viewer, semua tombol tambah/edit/hapus dinonaktifkan.</span>
                    </div>
                <?php endif; ?>

                <?php 
                    if (file_exists($file_page)) include $file_page;
                    else echo "<div class='text-center py-20 font-bold text-gray-400'>Halaman tidak ditemukan</div>";
                ?>
            </div>

            <footer class="mt-12 border-t border-slate-200 pt-6 pb-6 text-center text-slate-400 text-xs font-medium">
                &copy; <?= date('Y') ?> 8MP System
            </footer>
        </main>
    </div>

    <audio id="incomingSound" loop>
        <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
    </audio>

    <audio id="orderSound">
        <source src="https://assets.mixkit.co/active_storage/sfx/1114/1114-preview.mp3" type="audio/mpeg">
    </audio>

    <div id="incomingCallModal" class="hidden fixed inset-0 z-[9999] bg-slate-900/70 backdrop-blur-sm flex items-center justify-center transition-opacity duration-300">
        
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 flex flex-col items-center text-slate-800 transform transition-all border border-slate-100 text-center relative overflow-hidden">
            
            <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-b-[20%] opacity-10"></div>
            
            <div class="mb-5 relative z-10 mt-2">
                <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center text-3xl text-slate-300 font-bold ring-4 ring-indigo-50 shadow-md animate-bounce">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="absolute -bottom-1 -right-1 bg-green-500 w-7 h-7 rounded-full flex items-center justify-center border-2 border-white text-white shadow-sm">
                    <i class="fa-solid fa-video text-[10px]"></i>
                </div>
            </div>
            
            <h2 class="text-lg font-black mb-1 text-slate-800 ring-anim z-10 uppercase tracking-wide">Panggilan Masuk</h2>
            <p class="text-slate-500 mb-8 text-sm font-medium z-10" id="callerName">Admin Memanggil...</p>

            <div class="flex gap-6 items-center z-10 w-full justify-center">
                <button onclick="rejectCall()" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-xl group-hover:bg-rose-500 group-hover:text-white transition-all transform hover:scale-105 shadow-sm border border-rose-100">
                        <i class="fa-solid fa-phone-slash"></i>
                    </div>
                    <span class="text-[11px] font-bold text-slate-500">Tolak</span>
                </button>

                <button onclick="acceptCall()" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xl shadow-lg btn-pulse group-hover:bg-emerald-600 transition-all transform hover:scale-105">
                        <i class="fa-solid fa-video"></i>
                    </div>
                    <span class="text-[11px] font-bold text-slate-500">Terima</span>
                </button>
            </div>
        </div>
    </div>

    <div id="modalGantiPassword" class="hidden fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full relative">
            <button onclick="tutupModalPassword()" class="absolute top-4 right-4 text-slate-400 hover:text-red-500">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-key text-indigo-500"></i> Ganti Password
            </h3>
            <form id="formGantiPassword" onsubmit="return submitGantiPassword(event)">
                <div class="mb-3">
                    <label class="block text-xs font-bold text-slate-500 mb-1">Password Lama</label>
                    <input type="password" name="password_lama" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-bold text-slate-500 mb-1">Password Baru</label>
                    <input type="password" name="password_baru" required minlength="6" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="konfirmasi_password" required minlength="6" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                <p id="pesanErrorPassword" class="text-xs text-rose-500 font-semibold mb-3 hidden"></p>
                <button type="submit" id="btnSubmitPassword" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                    Simpan Password Baru
                </button>
            </form>
        </div>
    </div>

    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3 pointer-events-none w-auto h-auto">
        
        <div id="teamChatContainer" class="chat-box chat-hide pointer-events-auto bg-white w-80 md:w-96 h-[500px] rounded-xl shadow-2xl border border-slate-200 flex-col overflow-hidden ring-1 ring-slate-900/5">
            <div class="bg-indigo-600 p-3 px-4 flex justify-between items-center text-white shadow-md flex-shrink-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-users text-sm"></i>
                    <div>
                        <h4 class="font-bold text-sm leading-tight">Team Chat</h4>
                        <p class="text-[10px] text-indigo-200">Diskusi & Video Call</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button onclick="startVideoCall()" title="Mulai Video Call" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/40 flex items-center justify-center transition">
                        <i class="fa-solid fa-video"></i>
                    </button>
                    <button onclick="toggleChat()" class="hover:text-red-200 transition"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div class="bg-indigo-50 border-b p-2 flex gap-2 overflow-x-auto custom-scrollbar flex-shrink-0 min-h-[50px]" id="onlineUsersList">
                <span class="text-[10px] text-gray-400 italic px-2 py-1">Memuat...</span>
            </div>

            <div id="chatContent" class="flex-1 p-4 overflow-y-auto bg-slate-50 space-y-3 text-sm custom-scrollbar"></div>

            <form id="formChat" onsubmit="kirimPesan(event)" class="bg-white p-2 border-t flex gap-2 items-center flex-shrink-0">
                <input type="text" id="inputPesan" class="flex-1 bg-gray-100 border-0 rounded-full px-4 py-2 text-sm focus:ring-1 focus:ring-indigo-500 outline-none" placeholder="Tulis pesan..." autocomplete="off">
                <button type="submit" class="w-9 h-9 bg-indigo-600 text-white rounded-full flex items-center justify-center hover:bg-indigo-700 transition shadow-sm">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </form>
        </div>

        <button onclick="toggleChat()" class="pointer-events-auto w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 transition-all flex items-center justify-center relative group">
            <i class="fa-solid fa-comments text-xl"></i>
            <span class="absolute top-0 right-0 w-3.5 h-3.5 bg-green-400 border-2 border-white rounded-full"></span>
        </button>
    </div>

    <script>
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js');

        // --- DROPDOWN MENU USER (GANTI PASSWORD & LOGOUT) ---
        function toggleUserMenu() {
            document.getElementById('userMenuDropdown').classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('userMenuWrapper');
            const dropdown = document.getElementById('userMenuDropdown');
            if (wrapper && !wrapper.contains(e.target)) dropdown.classList.add('hidden');
        });

        function bukaModalPassword() {
            document.getElementById('userMenuDropdown').classList.add('hidden');
            document.getElementById('formGantiPassword').reset();
            document.getElementById('pesanErrorPassword').classList.add('hidden');
            document.getElementById('modalGantiPassword').classList.remove('hidden');
        }
        function tutupModalPassword() {
            document.getElementById('modalGantiPassword').classList.add('hidden');
        }

        function submitGantiPassword(e) {
            e.preventDefault();
            const form = e.target;
            const errBox = document.getElementById('pesanErrorPassword');
            const btn = document.getElementById('btnSubmitPassword');
            errBox.classList.add('hidden');

            const baru = form.password_baru.value;
            const konfirmasi = form.konfirmasi_password.value;
            if (baru !== konfirmasi) {
                errBox.textContent = 'Konfirmasi password baru tidak cocok.';
                errBox.classList.remove('hidden');
                return false;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

            const fd = new FormData(form);
            fetch('ajax_ganti_password.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = 'Simpan Password Baru';
                if (data.status === 'success') {
                    alert('Password berhasil diubah!');
                    tutupModalPassword();
                } else {
                    errBox.textContent = data.message || 'Gagal mengubah password.';
                    errBox.classList.remove('hidden');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = 'Simpan Password Baru';
                errBox.textContent = 'Terjadi kesalahan koneksi.';
                errBox.classList.remove('hidden');
            });
            return false;
        }

        // Variabel Global
        const chatBox = document.getElementById('teamChatContainer');
        const chatContent = document.getElementById('chatContent');
        const onlineList = document.getElementById('onlineUsersList');
        const incomingSound = document.getElementById('incomingSound');
        const orderSound = document.getElementById('orderSound');
        
        let isChatOpen = false;
        let lastCallId = 0; 
        let currentCallLink = ""; 
        let isFirstLoad = true; 

        // --- GLOBAL VAR UNTUK NOTIFIKASI PELANGGAN ---
        let lastNotifKey = ""; // Menyimpan ID + Status terakhir
        let playCount = 0;     // Menghitung berapa kali bunyi
        
        // ==============================================================
        // FLAG PENANDA AGAR NOTIFIKASI HANYA MUNCUL SEKALI SETELAH DITUTUP
        // ==============================================================
        let notifDitutup = false; 

        // --- AUTO UNLOCK AUDIO ---
        document.addEventListener('click', function() {
            if (orderSound.paused) {
                orderSound.play().then(() => {
                    orderSound.pause();
                    orderSound.currentTime = 0;
                }).catch(() => {});
            }
            if (incomingSound.paused) {
                incomingSound.play().then(() => {
                    incomingSound.pause();
                    incomingSound.currentTime = 0;
                }).catch(() => {});
            }
        }, { once: true });

        function testAudio() {
            orderSound.currentTime = 0;
            orderSound.play().then(() => alert('Suara Berhasil Diputar!')).catch(e => alert('Browser memblokir suara. Silakan klik halaman sekali lalu coba lagi.'));
        }

        // --- NOTIFIKASI REALTIME ---
        setInterval(() => {
            loadData(false); // Cek Chat
            cekNotifikasi(); // Cek Orderan
        }, 5000); 

        // --- FUNGSI CEK NOTIFIKASI BARU ---
        function cekNotifikasi() {
            fetch('pages/ajax_notif.php')
            .then(res => res.json())
            .then(data => {
                
                // 1. LOGIKA PELANGGAN (BUNYI 2 KALI SAJA)
                if (data.status === 'bunyi_pelanggan') {
                    let currentKey = data.no_pesanan + '_' + data.status_order;

                    // Jika ini notifikasi baru (status berubah atau order baru)
                    if (currentKey !== lastNotifKey) {
                        lastNotifKey = currentKey;
                        playCount = 0; // Reset counter
                        notifDitutup = false; // Reset flag penutup jika orderan berubah status
                    }

                    // Jika belum bunyi 2 kali dan notif belum ditutup
                    if (playCount < 2 && !notifDitutup) {
                        orderSound.play().catch(e => console.log("Audio autoplay blocked"));
                        showToast(data.pesan, false);
                        playCount++; 
                    }
                }
                
                // 2. LOGIKA ADMIN (BUNYI TERUS SAMPAI DIPROSES ATAU DITUTUP MANUAL)
                else if (data.status === 'bunyi_admin') {
                    if (!notifDitutup) {
                        orderSound.play().catch(e => console.log("Audio autoplay blocked"));
                        showToast(data.pesan, false);
                    }
                }

            })
            .catch(err => {});
        }

        function showToast(pesan, isAudioRequest = false) {
            // Cek duplikat toast agar tidak numpuk visualnya
            if(!isAudioRequest) {
                let existing = document.querySelectorAll('.toast-msg');
                for(let t of existing) { if(t.innerText.includes(pesan)) return; }
            }

            const div = document.createElement('div');
            // DESAIN POPUP DIUBAH: Menghilangkan animate-bounce diganti slide-in-right, 
            // bentuk lebih rapi menjadi persegi panjang mengambang dengan shadow elegan.
            div.className = `toast-msg fixed top-6 right-6 ${isAudioRequest ? 'bg-gradient-to-r from-orange-500 to-amber-500' : 'bg-gradient-to-r from-indigo-600 to-blue-500'} text-white px-5 py-4 rounded-xl shadow-[0_15px_40px_-10px_rgba(0,0,0,0.4)] z-[100] cursor-pointer flex items-center gap-4 border border-white/20 slide-in-right min-w-[320px] max-w-sm backdrop-blur-sm`;
            
            div.innerHTML = `
                <div class="bg-white/20 w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 shadow-inner">
                    <i class="fa-solid ${isAudioRequest ? 'fa-volume-high' : 'fa-bell'} text-lg"></i>
                </div> 
                <div class="flex-1">
                    <b class="text-sm tracking-wide block mb-0.5">${pesan}</b>
                    <span class="text-[11px] opacity-80 font-medium">${isAudioRequest ? 'Klik untuk aktifkan suara' : 'Tutup notifikasi'}</span>
                </div>
                <button class="opacity-50 hover:opacity-100 transition px-2"><i class="fa-solid fa-xmark text-lg"></i></button>
            `;
            
            div.onclick = function() { 
                if(isAudioRequest) {
                    orderSound.play();
                    div.remove();
                } else {
                    div.remove(); 
                    // SET VARIABEL PENANDA MENJADI TRUE SAAT TOMBOL DITUTUP
                    notifDitutup = true; 
                }
            }; 
            
            document.body.appendChild(div);
        }

        // Fungsi Buka/Tutup Chat
        function toggleChat() {
            if (chatBox.classList.contains('chat-hide')) {
                chatBox.classList.remove('chat-hide');
                chatBox.classList.add('chat-show');
                isChatOpen = true;
                setTimeout(() => document.getElementById('inputPesan').focus(), 100);
            } else {
                chatBox.classList.remove('chat-show');
                chatBox.classList.add('chat-hide');
                isChatOpen = false;
            }
        }

        function startVideoCall() {
            if(!confirm("Mulai Video Call dengan Team?")) return;
            const btn = event.currentTarget;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            const fd = new FormData();
            fd.append('action', 'video_call');
            fetch('ajax_chat_team.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = '<i class="fa-solid fa-video"></i>';
                if(data.status === 'success') {
                    loadData(true);
                    window.open(data.link, '_blank'); 
                } else { 
                    alert("Gagal: " + (data.message || 'Error server')); 
                }
            })
            .catch(() => { btn.innerHTML = '<i class="fa-solid fa-video"></i>'; });
        }

        function acceptCall() {
            incomingSound.pause();
            incomingSound.currentTime = 0;
            document.getElementById('incomingCallModal').classList.add('hidden');
            if(currentCallLink) window.open(currentCallLink, '_blank');
        }

        function rejectCall() {
            incomingSound.pause();
            incomingSound.currentTime = 0;
            document.getElementById('incomingCallModal').classList.add('hidden');
        }

        function loadData(forceScroll = false) {
            fetch('ajax_chat_team.php?action=get_all')
            .then(res => res.json())
            .then(data => {
                if(isChatOpen && data.users) {
                    onlineList.innerHTML = data.users.map(u => `
                        <div class="flex flex-col items-center min-w-[45px]">
                            <div class="w-8 h-8 rounded-full bg-white border ${u.is_me ? 'border-green-400' : 'border-indigo-200'} flex items-center justify-center text-[10px] font-bold text-indigo-600 relative shadow-sm">
                                ${u.inisial} <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border border-white rounded-full"></span>
                            </div>
                            <span class="text-[9px] text-gray-600 mt-1 truncate w-12 text-center">${u.nama}</span>
                        </div>
                    `).join('');
                }
                if(data.chats && data.chats.length > 0) {
                    const lastMsg = data.chats[data.chats.length - 1];
                    if (isFirstLoad) {
                        lastCallId = lastMsg.id; 
                        isFirstLoad = false;
                    } 
                    else if (lastMsg.id > lastCallId) {
                        lastCallId = lastMsg.id; 
                        if (lastMsg.is_video && !lastMsg.is_me) {
                            currentCallLink = lastMsg.link_video;
                            document.getElementById('callerName').innerText = lastMsg.nama + " mengajak Video Call";
                            document.getElementById('incomingCallModal').classList.remove('hidden');
                            incomingSound.play().catch(e => console.log("Audio autoplay blocked"));
                        }
                    }
                    if(isChatOpen) {
                        const currentScroll = chatContent.scrollTop;
                        const isAtBottom = (chatContent.scrollHeight - chatContent.clientHeight) <= (currentScroll + 150);
                        chatContent.innerHTML = data.chats.map(c => {
                            if (c.is_video) {
                                let btnClass = c.is_me ? "bg-white/20 text-white" : "bg-indigo-600 text-white shadow-lg btn-pulse";
                                return `<div class='flex ${c.is_me ? 'justify-end' : 'justify-start'} gap-2 mb-3'>${!c.is_me ? `<div class='w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-indigo-500 flex-shrink-0 flex items-center justify-center text-white text-[10px] font-bold shadow-sm mt-1'>${c.inisial}</div>` : ''}<div class='flex flex-col ${c.is_me ? 'items-end' : 'items-start'} max-w-[85%]'><div class='${c.is_me ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-700'} p-3 px-4 rounded-2xl ${c.is_me ? 'rounded-tr-none' : 'rounded-tl-none'} text-sm shadow-sm'><div class="font-bold mb-2 flex items-center gap-2"><i class="fa-solid fa-video"></i> Video Call Masuk</div><a href="${c.link_video}" target="_blank" class="block text-center py-2 px-4 rounded-lg font-bold text-xs ${btnClass} hover:scale-105 no-underline">GABUNG SEKARANG</a></div><span class='text-[9px] text-gray-400 mt-0.5'>${c.waktu}</span></div></div>`;
                            } else {
                                return `<div class='flex ${c.is_me ? 'justify-end' : 'justify-start'} gap-2 mb-2'>${!c.is_me ? `<div class='w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-[10px]'>${c.inisial}</div>` : ''}<div class='flex flex-col ${c.is_me ? 'items-end' : 'items-start'} max-w-[85%]'>${!c.is_me ? `<span class='text-[9px] text-gray-500 ml-1 font-bold'>${c.nama}</span>` : ''}<div class='${c.is_me ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-700'} p-2 px-3 rounded-2xl ${c.is_me ? 'rounded-tr-none' : 'rounded-tl-none'} text-sm shadow-sm'>${c.pesan}</div><span class='text-[9px] text-gray-400 mt-0.5'>${c.waktu}</span></div></div>`;
                            }
                        }).join('');
                        if(forceScroll || isAtBottom) {
                            setTimeout(() => chatContent.scrollTop = chatContent.scrollHeight, 50);
                        }
                    }
                }
            })
            .catch(e => console.error("Sync error:", e));
        }

        function kirimPesan(e) {
            e.preventDefault();
            const input = document.getElementById('inputPesan');
            const pesan = input.value.trim();
            if (!pesan) return;
            const formData = new FormData();
            formData.append('pesan', pesan);
            formData.append('action', 'send_chat');
            input.value = '';
            fetch('ajax_chat_team.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if(data.status === 'success') loadData(true); });
        }
    </script>
    
    
</body>
</html>