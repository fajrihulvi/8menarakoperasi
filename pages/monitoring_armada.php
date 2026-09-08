<?php
wajib_akses('monitoring_armada');
 
if (session_status() == PHP_SESSION_NONE) { session_start(); } 

// PROTEKSI AKSES
$role_akses = $_SESSION['role'] ?? '';
if (!in_array($role_akses, ['pelanggan', 'invoice'])) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md mt-20 md:mt-0' role='alert'>
            <p class='font-bold'>Akses Ditolak</p>
            <p>Halaman ini hanya untuk pemantauan pengiriman pelanggan.</p>
          </div>";
    exit();
}

// 1. Identifikasi Pelanggan & Cari Driver Pengirim
$user_id = $_SESSION['user_id'];

// Cari transaksi aktif (status 'pengiriman' atau 'Pending') milik user yang sedang login
// Mengambil 1 transaksi terakhir
$q_sj = mysqli_query($conn, "SELECT nama_driver, nopol, no_faktur, status FROM transaksi 
                              WHERE user_id = '$user_id' 
                              AND (status = 'pengiriman' OR status = 'Pending') 
                              ORDER BY id DESC LIMIT 1");
$data_kiriman = mysqli_fetch_assoc($q_sj);
$driver_ditunggu = $data_kiriman['nama_driver'] ?? '';
$status_order    = $data_kiriman['status'] ?? 'Menunggu';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-rotatedmarker@0.2.0/leaflet.rotatedMarker.js"></script>

<style>
    .map-container { height: 500px; width: 100%; border-radius: 20px; overflow: hidden; border: 4px solid white; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; }
    .driver-label { background: white; border: 2px solid #4f46e5; border-radius: 12px; font-weight: 800; color: #4338ca; font-size: 11px; padding: 6px 12px; white-space: nowrap; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
    
    /* Animasi Pulse untuk Marker jika perlu */
    .pulse-ring { border: 3px solid #4f46e5; border-radius: 50%; height: 40px; width: 40px; position: absolute; animation: pulsate 1s ease-out; opacity: 0.0; }
    @keyframes pulsate { 0% {transform: scale(0.1, 0.1); opacity: 0.0;} 50% {opacity: 1.0;} 100% {transform: scale(1.2, 1.2); opacity: 0.0;} }

    /* Fullscreen Mode */
    .fullscreen-active { position: fixed !important; top: 0; left: 0; right: 0; bottom: 0; width: 100vw !important; height: 100vh !important; z-index: 9999; border-radius: 0 !important; }
    .btn-control { position: absolute; z-index: 1000; background: white; width: 45px; height: 45px; border-radius: 50%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #eee; transition: all 0.2s; }
    .btn-control:hover { transform: scale(1.1); color: #4f46e5; }
    .btn-fullscreen { bottom: 20px; left: 20px; }
</style>

<div class="p-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 mt-14 md:mt-0">
        <div class="md:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-slate-800"><i class="fa-solid fa-truck-fast mr-2 text-indigo-600"></i> Pantau Pengiriman</h2>
                <?php if($driver_ditunggu && $status_order == 'pengiriman'): ?>
                    <p class="text-slate-500 text-sm">Driver <b><?= strtoupper($driver_ditunggu) ?></b> sedang menuju lokasi Anda.</p>
                <?php elseif($driver_ditunggu): ?>
                     <p class="text-slate-500 text-sm">Pesanan diproses. Menunggu driver jalan.</p>
                <?php else: ?>
                    <p class="text-amber-600 text-sm font-medium italic">Belum ada pengiriman aktif.</p>
                <?php endif; ?>
            </div>
            <?php if($driver_ditunggu): ?>
                <div class="hidden md:block text-right">
                    <div class="text-[10px] font-bold text-slate-400 uppercase">No. Polisi</div>
                    <div class="bg-slate-100 px-3 py-1 rounded-lg font-bold text-slate-700"><?= $data_kiriman['nopol'] ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-gradient-to-br from-indigo-600 to-purple-700 p-4 rounded-2xl shadow-lg text-white relative overflow-hidden">
            <div class="relative z-10">
                <h4 class="text-xs font-bold uppercase tracking-widest mb-1"><i class="fa-solid fa-microchip mr-1 text-yellow-300"></i> Status Driver</h4>
                <div id="ai-insight" class="text-sm font-medium italic">Menghubungkan ke satelit...</div>
            </div>
            <i class="fa-solid fa-robot absolute -bottom-4 -right-4 text-7xl opacity-10"></i>
        </div>
    </div>

    <div id="mapContainer" class="map-container">
        <div id="map" class="h-full w-full"></div>
        
        <div onclick="toggleFullScreenMap()" class="btn-control btn-fullscreen" title="Layar Penuh">
            <i class="fa-solid fa-expand text-lg"></i>
        </div>

        <div class="absolute bottom-6 right-6 z-[1000] bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg border border-white flex items-center gap-2">
            <span class="relative flex h-3 w-3"><span class="animate-ping absolute h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative h-3 w-3 rounded-full bg-green-500"></span></span>
            <span class="text-[10px] font-bold text-slate-700 uppercase tracking-wider" id="gps-label">Signal: Real GPS</span>
        </div>
    </div>
</div>

<script>
    // 1. SETUP MAP
    var googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 20 });
    var googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { maxZoom: 20 });

    var map = L.map('map', { center: [-2.13, 106.11], zoom: 13, layers: [googleHybrid] });

    var baseMaps = { "Tampilan Satelit": googleHybrid, "Tampilan Maps Biasa": googleStreets };
    L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

    var arrowIcon = L.icon({ 
        iconUrl: 'assets/img/logo_1769017859.png', // Pastikan icon ini ada, atau ganti default
        iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -20]
    });

    // Variabel dari PHP
    var marker = null;
    var assignedDriver = "<?= $driver_ditunggu ?>"; 
    var assignedNopol = "<?= $data_kiriman['nopol'] ?? '' ?>";
    var hasCentered = false; 

    // 2. FUNGSI TRACKING (LOOPING)
    function updateGPS() {
        if (!assignedDriver) {
             document.getElementById('ai-insight').innerHTML = "Tidak ada driver yang ditugaskan.";
             return;
        }

        // Panggil data semua driver aktif
        fetch('pages/ajax_driver.php?action=get_active_drivers')
        .then(res => res.json())
        .then(drivers => {
            // CARI DRIVER YANG NAMANYA COCOK (Case Insensitive)
            let target = drivers.find(d => d.nama.toLowerCase() === assignedDriver.toLowerCase());
            
            const aiBox = document.getElementById('ai-insight');

            if (target) {
                let lat = parseFloat(target.latitude);
                let lng = parseFloat(target.longitude);
                let heading = parseFloat(target.heading) || 0;
                let speed = parseFloat(target.speed) || 0; 
                let pos = [lat, lng];

                // Update Marker
                if (!marker) {
                    marker = L.marker(pos, { icon: arrowIcon, rotationAngle: heading }).addTo(map);
                } else {
                    marker.setLatLng(pos);
                    if(marker.setRotationAngle) marker.setRotationAngle(heading);
                }
                
                // Fokus Map ke Driver (Hanya sekali di awal)
                if(!hasCentered) {
                    map.flyTo(pos, 16);
                    hasCentered = true;
                }
                
                // Tooltip Info
                marker.bindTooltip(`
                    <div class='text-center'>
                        <b class='uppercase'>${target.nama}</b><br>
                        <span class='text-blue-600 font-bold'>${assignedNopol}</span><br>
                        Kecepatan: ${speed.toFixed(0)} km/h
                    </div>
                `, { permanent: true, className: 'driver-label', offset: [0, -25] });

                // Update Status AI
                if (speed > 5) {
                    aiBox.innerHTML = "<span class='text-green-300 font-bold'>BERGERAK</span> &bull; Driver sedang dalam perjalanan.";
                    document.getElementById('gps-label').innerHTML = "Status: <span class='text-green-600'>Live Moving</span>";
                } else {
                    aiBox.innerHTML = "<span class='text-yellow-300 font-bold'>BERHENTI</span> &bull; Driver sedang diam/macet.";
                    document.getElementById('gps-label').innerHTML = "Status: <span class='text-orange-600'>Idle</span>";
                }

            } else {
                aiBox.innerHTML = "<i class='fa-solid fa-triangle-exclamation mr-2'></i> Driver offline / Hilang sinyal.";
                document.getElementById('gps-label').innerText = "Status: Lost Signal";
            }
        })
        .catch(err => console.log("Gagal memuat driver:", err));
    }

    // --- 3. FUNGSI FULLSCREEN ---
    function toggleFullScreenMap() {
        let container = document.getElementById('mapContainer');
        if (!document.fullscreenElement) {
            container.requestFullscreen();
            container.classList.add('fullscreen-active');
        } else {
            document.exitFullscreen();
            container.classList.remove('fullscreen-active');
        }
    }
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) document.getElementById('mapContainer').classList.remove('fullscreen-active');
    });

    // Jalankan setiap 3 detik
    if (assignedDriver) { 
        setInterval(updateGPS, 3000); 
        updateGPS(); 
    } else {
        document.getElementById('ai-insight').innerHTML = "Belum ada pesanan dikirim.";
    }
</script>