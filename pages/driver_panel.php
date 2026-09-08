<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
// Cek sesi driver
wajib_akses('driver_panel');
?>

<div id="gpsWarningBanner" class="hidden fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-black/90 backdrop-blur-md p-6">
    <div class="bg-red-600 text-white text-center py-8 px-6 font-extrabold shadow-2xl rounded-3xl border-4 border-red-800 max-w-sm w-full transition-transform transform scale-100">
        <i class="fa-solid fa-location-crosshairs text-yellow-300 text-6xl mb-4 block animate-bounce"></i> 
        <h2 class="text-2xl mb-2 uppercase tracking-wider">Akses Terkunci!</h2>
        <p class="text-sm font-medium mb-6 text-red-100">Sistem mendeteksi LOKASI / GPS Anda sedang MATI.</p>
        <div class="bg-red-900/80 border border-red-400 p-4 rounded-xl text-xs leading-relaxed shadow-inner">
            <span class="text-white text-sm block mb-2">Mohon seret layar ke bawah dan aktifkan ikon</span>
            <b class="text-yellow-300 text-xl tracking-widest animate-pulse block mb-2">LOKASI / GPS</b>
            <span class="text-white text-sm block">di HP Anda sekarang agar layar ini terbuka otomatis.</span>
        </div>
    </div>
</div>
<div class="max-w-md mx-auto bg-white rounded-xl shadow-lg overflow-hidden md:max-w-2xl mt-4">
    <div class="p-6 relative">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Panel Driver</h1>
                <p class="text-sm text-slate-500">Halo, <?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div id="statusIcon" class="w-4 h-4 rounded-full bg-red-500 shadow-md"></div>
        </div>

        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 mb-6 rounded text-xs text-yellow-800">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i> 
            <b>PENTING AGAR GPS AKURAT:</b> 
            <ul class="list-disc ml-4 mt-1">
                <li>Klik tombol "START DRIVE".</li>
                <li>Jika muncul notifikasi musik (Driver Aktif), <b>JANGAN DI-PAUSE</b>.</li>
                <li>Biarkan browser berjalan. Anda boleh mematikan layar HP (Lock Screen), sistem akan tetap mengirim lokasi.</li>
            </ul>
        </div>

        <div class="mt-2 text-center">
            <div id="statusText" class="text-2xl font-bold text-slate-400 mb-2">OFFLINE</div>
            
            <div id="coords" class="font-mono text-xs text-slate-500 mb-2 bg-slate-100 p-3 rounded border border-slate-200">
                Menunggu GPS...
            </div>
            
            <div id="accuracyInfo" class="text-[10px] text-gray-400 mb-6">
                Akurasi: <span id="accVal">-</span> meter
            </div>

            <audio id="bgAudio" loop playsinline style="display:none;"></audio>

            <button id="btnStart" onclick="startTracking()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-6 rounded-2xl shadow-xl transition active:scale-95 flex items-center justify-center gap-3">
                <i class="fa-solid fa-satellite-dish text-xl"></i> <span>START DRIVE</span>
            </button>
            
            <button id="btnStop" onclick="stopTracking()" class="hidden w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-2xl shadow-xl transition active:scale-95 flex items-center justify-center gap-3">
                <i class="fa-solid fa-power-off text-xl"></i> <span>STOP DRIVE</span>
            </button>
        </div>
    </div>
</div>

<script>
// =========================================================================
// FITUR BARU: PENDETEKSI GPS SUPER CEPAT (0.5 DETIK) & KUNCI LAYAR
// =========================================================================
function pantauGpsKetat() {
    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // JIKA SUKSES (GPS NYALA) -> HILANGKAN TEMBOK HITAM
                let banner = document.getElementById("gpsWarningBanner");
                if (banner) banner.classList.add("hidden");
                
                // Jika GPS sedang nyala, cek agak santai (tiap 3 detik) agar tidak boros baterai
                setTimeout(pantauGpsKetat, 3000); 
            },
            function(error) {
                // JIKA GAGAL (GPS MATI / DITOLAK) -> MUNCULKAN TEMBOK HITAM
                let banner = document.getElementById("gpsWarningBanner");
                if (banner) banner.classList.remove("hidden");
                
                // Cek SUPER CEPAT (tiap 0.5 detik / 500ms) agar saat dihidupkan tembok langsung lenyap seketika!
                setTimeout(pantauGpsKetat, 500); 
            },
            { timeout: 3000, maximumAge: 0, enableHighAccuracy: false }
        );
    }
}

// Jalankan sistem pantau seketika saat halaman dimuat
pantauGpsKetat();
// =========================================================================

// --- DETEKSI APAKAH DIBUKA DARI APLIKASI ANDROID ---
let isUsingAndroidApp = (typeof Android !== "undefined");

if (isUsingAndroidApp) {
    // 1. Kirim ID Sesi secara diam-diam ke Aplikasi Android
    Android.setDriverId('<?= isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : "0" ?>');
    
    // 2. Karena Aplikasi Android bekerja 24 jam secara otomatis, 
    //    kita ubah tampilan UI agar Driver tidak merasa perlu klik "Start" lagi
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('statusText').innerText = "SYSTEM BACKGROUND AKTIF";
        document.getElementById('statusText').className = "text-xl font-bold text-green-600 mb-2 animate-pulse";
        document.getElementById('statusIcon').className = "w-4 h-4 rounded-full bg-green-500 animate-ping";
        
        // Sembunyikan tombol manual (Cegah kebingungan start ulang saat aplikasi diclose)
        document.getElementById('btnStart').style.display = 'none';
        if(document.getElementById('btnStop')) document.getElementById('btnStop').style.display = 'none';
        if(document.getElementById('accuracyInfo')) document.getElementById('accuracyInfo').style.display = 'none';
        
        document.getElementById('coords').innerHTML = "<b class='text-green-600'><i class='fa-solid fa-shield-halved'></i> Aplikasi Merekam Otomatis</b><br><span class='text-xs text-gray-500'>Radar tetap berjalan meskipun Anda menutup/me-minimize aplikasi atau mematikan layar (Lock Screen).</span>";
    });

} else {
    // =========================================================================
    // JIKA DIBUKA VIA BROWSER BIASA (CHROME), GUNAKAN TOMBOL MANUAL
    // =========================================================================
    let watchId = null;
    let wakeLock = null;
    let bgAudio = document.getElementById('bgAudio');
    let lastLat = 0, lastLng = 0;
    let isTracking = false;

    const silentAudioBase64 = "data:audio/mp3;base64,//NExAAAAANIAAAAAExBTUUzLjEwMKqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq";
    bgAudio.src = silentAudioBase64;

    document.addEventListener('DOMContentLoaded', function() {
        if ("Notification" in window && Notification.permission !== "granted") {
            Notification.requestPermission();
        }
        document.getElementById('btnStart').onclick = function() { startTrackingManual(); };
        if(document.getElementById('btnStop')) document.getElementById('btnStop').onclick = function() { stopTrackingManual(); };
    });

    async function requestWakeLock() {
        try { wakeLock = await navigator.wakeLock.request('screen'); } catch (err) {}
    }

    function startTrackingManual() {
        if (!navigator.geolocation) { alert("HP Anda tidak mendukung GPS."); return; }
        
        bgAudio.play().catch(e => {});
        requestWakeLock();
        isTracking = true;

        document.getElementById('btnStart').classList.add('hidden');
        document.getElementById('btnStop').classList.remove('hidden');
        document.getElementById('statusText').innerText = "ONLINE (WEB TRACKING)";
        document.getElementById('statusText').className = "text-xl font-bold text-green-600 mb-2 animate-pulse";
        document.getElementById('statusIcon').className = "w-4 h-4 rounded-full bg-green-500 animate-ping";

        const gpsOptions = { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 };
        watchId = navigator.geolocation.watchPosition(processPosition, handleError, gpsOptions);
    }

    function stopTrackingManual() {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        if (wakeLock !== null) wakeLock.release();
        
        bgAudio.pause(); bgAudio.currentTime = 0;
        isTracking = false;
        
        sendOfflineSignal(); 

        document.getElementById('btnStart').classList.remove('hidden');
        document.getElementById('btnStop').classList.add('hidden');
        document.getElementById('statusText').innerText = "OFFLINE";
        document.getElementById('statusText').className = "text-2xl font-bold text-slate-400 mb-2";
        document.getElementById('statusIcon').className = "w-4 h-4 rounded-full bg-red-500";
        document.getElementById('coords').innerText = "Pelacakan dihentikan.";
        document.getElementById('accVal').innerText = "-";
    }

    function processPosition(position) {
        if(!isTracking) return;
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy; 
        const speed = position.coords.speed || 0;       
        
        document.getElementById('accVal').innerText = Math.round(accuracy);
        if (accuracy > 100 && lastLat !== 0) return;
        
        lastLat = lat; lastLng = lng;
        const time = new Date().toLocaleTimeString();
        document.getElementById('coords').innerHTML = `Lat: ${lat.toFixed(5)}<br>Lng: ${lng.toFixed(5)}<br><span class="text-[10px] text-gray-500">Update: ${time}</span>`;

        if (navigator.onLine) { kirimKeServer(lat, lng, position.coords.heading || 0, speed); }
    }

    function kirimKeServer(lat, lng, heading, speed) {
        let fd = new FormData();
        fd.append('action', 'update_location');
        fd.append('lat', lat);
        fd.append('lng', lng);
        fd.append('heading', heading);
        fd.append('speed', speed); 
        fetch('pages/ajax_driver.php', { method: 'POST', body: fd, keepalive: true }).catch(e => {});
    }

    function sendOfflineSignal() {
        let fd = new FormData();
        fd.append('action', 'set_offline');
        fetch('pages/ajax_driver.php', { method: 'POST', body: fd, keepalive: true });
    }

    function handleError(error) {
        document.getElementById('coords').innerText = "Mencari Sinyal GPS...";
    }
}
</script>