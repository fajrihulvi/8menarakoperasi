<?php
wajib_akses('tracking_driver');
 if (session_status() == PHP_SESSION_NONE) { session_start(); } ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-rotatedmarker@0.2.0/leaflet.rotatedMarker.js"></script>

<style>
    /* --- STYLE ASLI ANDA --- */
    .map-container { 
        height: calc(100vh - 120px); 
        width: 100%; 
        border-radius: 15px; 
        overflow: hidden; 
        border: 4px solid white; 
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); 
        position: relative;
        background: #f0f0f0; 
    }
    
    .leaflet-marker-icon { transition: all 1s linear; }
    
    .driver-popup .leaflet-popup-content-wrapper { padding: 0; border-radius: 12px; overflow: hidden; }
    .driver-popup .leaflet-popup-content { margin: 0; width: 240px !important; }
    
    .driver-label {
        background: white;
        border: 1px solid #6366f1;
        border-radius: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        font-weight: 800;
        color: #4338ca;
        font-size: 10px;
        padding: 3px 10px;
        white-space: nowrap;
    }
    .driver-label::before { border-top-color: #6366f1; }

    /* --- STYLE BARU UNTUK LABEL TITIK DAPUR --- */
    .dapur-label {
        background: #f59e0b;
        border: 1px solid #d97706;
        border-radius: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        font-weight: 800;
        color: white;
        font-size: 9px;
        padding: 3px 10px;
        white-space: nowrap;
    }
    .dapur-label::before { border-top-color: #f59e0b; }

    /* --- FITUR TAMBAHAN (OVERLAY PANEL) --- */
    .driver-list-overlay {
        position: absolute; top: 10px; right: 10px; z-index: 1000;
        width: 250px; max-height: 60%;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(5px);
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        display: flex; flex-direction: column;
        border: 1px solid rgba(0,0,0,0.1);
    }
    
    .driver-list-header { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .driver-list-body { overflow-y: auto; padding: 5px; }
    
    .driver-item {
        padding: 8px; margin-bottom: 5px;
        background: white; border-radius: 8px;
        cursor: pointer; display: flex; align-items: center; gap: 10px;
        border: 1px solid transparent; transition: all 0.2s;
    }
    .driver-item:hover { background: #f8fafc; border-color: #cbd5e1; }
    .driver-item.active { background: #e0e7ff; border-color: #6366f1; }
    
    .btn-tv-mode {
        position: absolute; bottom: 20px; left: 20px; z-index: 1000;
        background: white; padding: 10px; border-radius: 50%;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        cursor: pointer; color: #475569; transition: transform 0.2s;
        width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;
    }
    .btn-tv-mode:hover { transform: scale(1.1); color: #6366f1; }

    .fullscreen-active {
        position: fixed !important; top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw !important; height: 100vh !important;
        border-radius: 0 !important; border: none !important;
        z-index: 9999;
    }
</style>

<div class="p-4">
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-4 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-map-location-dot mr-2 text-indigo-600"></i> Pantau Armada
            </h2>
            <p class="text-xs text-slate-500">Mode: Google Maps (Realtime)</p>
        </div>
        <div id="statusBadge" class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full font-bold flex items-center gap-2">
            <i class="fa-solid fa-spinner fa-spin"></i> Menghubungkan...
        </div>
    </div>
    
    <div id="mapContainer" class="map-container">
        <div id="map" style="width: 100%; height: 100%;"></div>

        <div class="driver-list-overlay">
            <div class="driver-list-header">
                <span class="text-xs font-bold text-slate-600">LIST DRIVER</span>
                <button onclick="lockAll()" class="text-[10px] bg-slate-200 px-2 py-1 rounded hover:bg-slate-300" title="Lihat Semua">RESET</button>
            </div>
            <div class="driver-list-body custom-scrollbar" id="driverListContainer">
                <div class="text-center text-[10px] text-gray-400 py-2">Loading...</div>
            </div>
        </div>

        <button onclick="toggleFullScreenMap()" class="btn-tv-mode" title="Mode Layar Penuh (TV)">
            <i class="fa-solid fa-expand text-lg"></i>
        </button>
    </div>
</div>

<script>
    // --- 1. SETUP LAYER PETA ---
    var googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20, attribution: '© Google Maps'
    });
    var googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
        maxZoom: 20, attribution: '© Google Maps'
    });

    var map = L.map('map', {
        center: [-2.13, 106.11], 
        zoom: 13,
        layers: [googleStreets] 
    });

    var baseMaps = { "Google Maps (Jalan)": googleStreets, "Google Satelit (Real)": googleHybrid };
    L.control.layers(baseMaps, null, {position: 'bottomright'}).addTo(map);

    // =========================================================================
    // PERBAIKAN SAKLAR ZOOM MANUAL:
    // Hanya matikan Auto-Follow jika Admin benar-benar melakukan interaksi FISIK 
    // pada elemen peta (Klik tahan mouse, Sentuh Layar, atau Scroll Mouse).
    // =========================================================================
    var isUserInteracting = false;
    var mapElement = map.getContainer();
    mapElement.addEventListener('mousedown', function() { isUserInteracting = true; });
    mapElement.addEventListener('touchstart', function() { isUserInteracting = true; });
    mapElement.addEventListener('wheel', function() { isUserInteracting = true; }, {passive: true});


    // =========================================================================
    // FITUR DAPUR MBG
    // =========================================================================
    var dapurIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/3081/3081840.png', 
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        tooltipAnchor: [0, -35]
    });

    var lokasiDapur = [
        { nama: "KOPERASI 8 MENARA PERSADA", lat: -2.1322854194481007, lng: 106.1083185126937 },
        { nama: "SPPG PGI KERETA", lat: -2.3039494875931172, lng: 106.04606518083126 },
        { nama: "SPPG PGI GABEK", lat: -2.085613376522324, lng: 106.0951477644332 }
    ];

    lokasiDapur.forEach(function(dapur) {
        let markerDapur = L.marker([dapur.lat, dapur.lng], { icon: dapurIcon }).addTo(map);
        markerDapur.bindTooltip(dapur.nama, { permanent: true, direction: 'top', className: 'dapur-label', offset: [0, -15] });
    });

    // --- 2. ICON DRIVER BERGERAK ---
    var arrowIconUrl = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NiA1NiIgd2lkdGg9IjU2IiBoZWlnaHQ9IjU2Ij48cGF0aCBkPSJNMjggMEw1NiA1NkwyOCA0MkwwIDU2TDI4IDB6IiBmaWxsPSIjNDMzOENBIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjQiLz48L3N2Zz4=';
    
    var arrowIcon = L.icon({ 
        iconUrl: arrowIconUrl, 
        iconSize: [42, 42], 
        iconAnchor: [21, 21], 
        popupAnchor: [0, -10],
        tooltipAnchor: [0, -25] 
    });

    // --- 3. VARIABEL GLOBAL & MEMORI POSISI (UNTUK AUTO HEADING) ---
    var markers = {};
    var lockedDriverId = 'all'; 
    var lastPositions = {}; // Mengingat posisi sebelumnya
    var currentHeadings = {}; // Mengingat arah hadap sebelumnya

    // RUMUS MENGHITUNG ARAH MATA ANGIN (BEARING) DARI 2 KOORDINAT
    function calculateBearing(lat1, lng1, lat2, lng2) {
        let toRad = Math.PI / 180;
        let toDeg = 180 / Math.PI;
        let dLon = (lng2 - lng1) * toRad;
        
        let y = Math.sin(dLon) * Math.cos(lat2 * toRad);
        let x = Math.cos(lat1 * toRad) * Math.sin(lat2 * toRad) - 
                Math.sin(lat1 * toRad) * Math.cos(lat2 * toRad) * Math.cos(dLon);
        
        let bearing = Math.atan2(y, x) * toDeg;
        return (bearing + 360) % 360; 
    }

    // --- 4. FUNGSI UPDATE DRIVER ---
    function fetchDrivers() {
        let fd = new FormData();
        fd.append('action', 'get_active_drivers');

        fetch('pages/ajax_driver.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(drivers => {
            updateStatusBadge(drivers.length);
            renderDriverList(drivers); 

            let bounds = L.latLngBounds();
            lokasiDapur.forEach(dp => bounds.extend([dp.lat, dp.lng]));

            let activeCount = 0;

            drivers.forEach(d => {
                let id = d.id;
                let lat = parseFloat(d.latitude);
                let lng = parseFloat(d.longitude);
                let nama = d.nama;
                let nopol = d.nopol;
                let isOffline = (d.status_text && d.status_text.includes("Offline"));

                if (!isNaN(lat) && !isNaN(lng)) {
                    let posisi = [lat, lng];

                    let finalHeading = 0;
                    if (lastPositions[id]) {
                        let oldLat = lastPositions[id].lat;
                        let oldLng = lastPositions[id].lng;
                        
                        // Menghindari rotasi aneh jika perbedaan titik sangat kecil
                        if (Math.abs(oldLat - lat) > 0.00001 || Math.abs(oldLng - lng) > 0.00001) {
                            finalHeading = calculateBearing(oldLat, oldLng, lat, lng);
                            currentHeadings[id] = finalHeading; 
                        } else {
                            finalHeading = currentHeadings[id] || 0;
                        }
                    } else {
                        finalHeading = currentHeadings[id] || 0;
                    }
                    
                    lastPositions[id] = {lat: lat, lng: lng};

                    if (markers[id]) {
                        markers[id].setLatLng(posisi);
                        markers[id].setRotationAngle(finalHeading); 
                        if(markers[id].getTooltip().getContent() !== `${nama} (${nopol})`) {
                            markers[id].setTooltipContent(`${nama} (${nopol})`);
                        }
                    } else {
                        let m = L.marker(posisi, { 
                            icon: arrowIcon, 
                            rotationAngle: finalHeading, 
                            rotationOrigin: 'center center' 
                        }).addTo(map);
                        
                        m.bindTooltip(`${nama} (${nopol})`, { permanent: true, direction: 'top', className: 'driver-label', offset: [0, -20] });
                        m.on('click', function() { lockDriver(id); });
                        markers[id] = m;
                    }

                    if (!isOffline) {
                        bounds.extend(posisi);
                        activeCount++;
                    }

                    // KAMERA MENGIKUTI 1 DRIVER SAAT BERJALAN
                    if (lockedDriverId == id && !isUserInteracting) {
                        map.panTo(posisi); 
                    }
                }
            });

            // KAMERA AUTO-ZOOM SAAT MODE 'ALL'
            if (lockedDriverId == 'all' && activeCount > 0 && !isUserInteracting) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            }
        })
        .catch(err => console.error("Error:", err));
    }

    // --- 5. RENDER PANEL LIST DRIVER ---
    function renderDriverList(drivers) {
        let html = '';
        if(drivers.length === 0) html = '<div class="text-center text-[10px] text-gray-400 py-2">Tidak ada driver online</div>';
        
        drivers.forEach(d => {
            let activeClass = (lockedDriverId == d.id) ? 'active' : '';
            let statusText = d.status_text ? d.status_text : "Loading...";
            let statusColor = d.status_color ? d.status_color : "text-gray-400";
            
            let speedText = "";
            let currentSpeed = parseFloat(d.speed) || 0;
            let btnStopHtml = '';

            if (currentSpeed == -1) {
            } else if (currentSpeed > 2) { 
                speedText = `<span class="text-blue-600 font-bold ml-1 text-[10px]"><i class="fa-solid fa-gauge-high"></i> ${currentSpeed} km/j</span>`;
            } else {
                speedText = `<span class="text-gray-400 font-bold ml-1 text-[10px]"> (Berhenti)</span>`;
            }

            if (d.status_text && !d.status_text.includes("Offline")) {
                btnStopHtml = `<button onclick="event.stopPropagation(); forceStopDriver(${d.id}, '${d.nama}')" class="ml-auto bg-red-100 border border-red-300 text-red-600 hover:bg-red-500 hover:text-white px-2 py-1 rounded text-[9px] font-bold shadow-sm z-50"><i class="fa-solid fa-power-off"></i> STOP</button>`;
            }

            html += `
                <div class="driver-item ${activeClass}" onclick="lockDriver(${d.id})">
                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold border border-slate-300">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-xs font-bold text-slate-700">${d.nama} ${speedText}</div>
                        <div class="text-[9px] text-slate-400 mt-1">
                            ${d.nopol} • <span class="${statusColor}">${statusText}</span>
                        </div>
                    </div>
                    ${btnStopHtml}
                    ${lockedDriverId == d.id ? '<i class="fa-solid fa-lock text-xs text-indigo-500 animate-pulse ml-2"></i>' : ''}
                </div>
            `;
        });
        document.getElementById('driverListContainer').innerHTML = html;
    }

    // --- 6. FUNGSI KONTROL ---
    function lockDriver(id) {
        lockedDriverId = id;
        isUserInteracting = false; // Reset interaksi saat nama driver diklik
        if(markers[id]) {
            map.flyTo(markers[id].getLatLng(), 17, { animate: true, duration: 1 });
        }
        fetchDrivers(); // Paksa update seketika
    }

    function lockAll() {
        lockedDriverId = 'all';
        isUserInteracting = false; // Reset interaksi saat tombol RESET diklik
        fetchDrivers();
    }

    function updateStatusBadge(count) {
        const badge = document.getElementById('statusBadge');
        if(count > 0) {
            badge.className = "text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-bold animate-pulse border border-green-200";
            badge.innerHTML = `<span class="relative flex h-2 w-2 mr-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span> ${count} Driver Online`;
        } else {
            badge.className = "text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full font-bold";
            badge.innerText = "Menunggu Driver...";
        }
    }

    function forceStopDriver(id, nama) {
        if(confirm(`⛔ PUTUS KONEKSI: Anda yakin ingin mematikan paksa GPS untuk armada ${nama}?`)) {
            let fd = new FormData();
            fd.append('action', 'force_stop'); fd.append('driver_id', id);
            fetch('pages/ajax_driver.php', { method: 'POST', body: fd }).then(res => fetchDrivers());
        }
    }

    // --- 7. FUNGSI FULLSCREEN TV MODE ---
    function toggleFullScreenMap() {
        let container = document.getElementById('mapContainer');
        if (!document.fullscreenElement) {
            if(container.requestFullscreen) { container.requestFullscreen(); }
            container.classList.add('fullscreen-active');
        } else {
            if(document.exitFullscreen) { document.exitFullscreen(); }
            container.classList.remove('fullscreen-active');
        }
    }
    
    document.addEventListener('fullscreenchange', (event) => {
        if (!document.fullscreenElement) {
            document.getElementById('mapContainer').classList.remove('fullscreen-active');
        }
    });

    setInterval(fetchDrivers, 2000);
    fetchDrivers();
</script>