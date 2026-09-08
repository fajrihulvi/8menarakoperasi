<header class="h-16 bg-white border-b flex items-center justify-between px-6 shadow-sm sticky top-0 z-10">
    <div class="flex items-center">
        <button class="md:hidden text-gray-500 mr-4"><i class="fa-solid fa-bars"></i></button>
        <h2 class="text-lg font-semibold text-gray-700"><?= $info['nama_perusahaan'] ?></h2>
    </div>
    <div class="flex items-center gap-4">
        
        <button id="btnMuteNotif" onclick="toggleMute()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2">
            <i class="fa-solid fa-volume-high" id="muteIcon"></i>
            <span id="muteText">Suara ON</span>
        </button>
        <div class="text-right hidden sm:block">
            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-indigo-600 uppercase"><?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center font-bold text-lg">
            <?= substr($_SESSION['nama'], 0, 1) ?>
        </div>
    </div>
</header>

<script>
// Variabel Global untuk menyimpan ID Watcher
let watchId = null;

document.addEventListener('DOMContentLoaded', function() {
    if (window.Capacitor) {
        // Tunggu sebentar agar plugin siap
        setTimeout(startTrackingKuat, 1000);
    }
});

async function startTrackingKuat() {
    const BackgroundGeolocation = window.Capacitor.Plugins.BackgroundGeolocation;

    // 1. Tambahkan Listener (Penerima Lokasi)
    // Ini akan terus berjalan walaupun aplikasi diminimize
    const watcher = await BackgroundGeolocation.addWatcher(
        {
            // OPSI WAJIB AGAR TIDAK MATI
            backgroundMessage: "Aplikasi 8MP sedang aktif melacak.",
            backgroundTitle: "Mode Driver",
            requestPermissions: true,
            stale: false,
            distanceFilter: 5 // Kirim update tiap 5 meter (lebih akurat)
        },
        function (location, error) {
            if (error) {
                if (error.code === "NOT_AUTHORIZED") {
                    if (window.confirm("Aplikasi butuh izin 'Sepanjang Waktu' agar tidak mati saat buka YouTube. Buka pengaturan?")) {
                        BackgroundGeolocation.openSettings();
                    }
                }
                return console.error(error);
            }

            // --- KIRIM KE SERVER ---
            console.log("Lokasi Background:", location.latitude, location.longitude);
            
            // Dikirim ke endpoint lokal api_gps.php (format form-encoded).
            // Sebelumnya menembak https://8mp.store/api/update_lokasi.php yang
            // sudah tidak aktif, sehingga pengiriman lokasi selalu gagal.
            fetch('api_gps.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    lat: location.latitude,
                    lng: location.longitude,
                    driver_id: "<?= htmlspecialchars($_SESSION['user_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>",
                    status_gps: 'ON'
                })
            }).catch(err => console.log("Gagal kirim lokasi:", err));
        }
    );
    
    watchId = watcher;
    console.log("Pelacak Anti-Mati dimulai!");
}

// Opsional: Matikan pelacak saat Logout
function stopTracking() {
    if (watchId) {
        const BackgroundGeolocation = window.Capacitor.Plugins.BackgroundGeolocation;
        BackgroundGeolocation.removeWatcher({ id: watchId });
    }
}

// =========================================================================
// LOGIKA MUTE / UNMUTE NOTIFIKASI
// =========================================================================
let isNotifMuted = localStorage.getItem('isNotifMuted') === 'true'; 

// Ganti 'suara_notif.mp3' dengan nama/path file audio notifikasi Anda
let audioNotif = new Audio('suara_notif.mp3'); 

function updateMuteButtonUI() {
    let icon = document.getElementById('muteIcon');
    let text = document.getElementById('muteText');
    if (!icon || !text) return;

    if (isNotifMuted) {
        icon.className = "fa-solid fa-volume-xmark text-red-500";
        text.innerText = "Suara OFF";
    } else {
        icon.className = "fa-solid fa-volume-high text-green-500";
        text.innerText = "Suara ON";
    }
}

function toggleMute() {
    isNotifMuted = !isNotifMuted; 
    localStorage.setItem('isNotifMuted', isNotifMuted); 
    updateMuteButtonUI();
}

function playNotificationSound() {
    if (!isNotifMuted) {
        audioNotif.play().catch(error => console.log("Gagal memutar suara: ", error));
    }
}

document.addEventListener("DOMContentLoaded", updateMuteButtonUI);
</script>