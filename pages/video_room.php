<?php
wajib_akses('video_room');

// pages/video_room.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$room_id = $_GET['room'] ?? '';
$role = ($_SESSION['role'] == 'driver') ? 'driver' : 'admin';

// Validasi Room
if(empty($room_id)) die("<div style='color:white;text-align:center;padding:20px;'>Error: Room ID Tidak Valid. Tutup tab ini dan coba lagi.</div>");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Call - <?= ucfirst($role) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f172a; margin: 0; overflow: hidden; font-family: 'Segoe UI', sans-serif; }
        
        /* Video Container */
        .video-container { position: relative; width: 100vw; height: 100vh; }
        
        /* Video Lawan (Full Screen) */
        #remoteVideo { width: 100%; height: 100%; object-fit: cover; background: #1e293b; }
        
        /* Video Sendiri (Kecil di Pojok) */
        .local-wrapper { 
            position: absolute; bottom: 20px; right: 20px; 
            width: 120px; height: 160px; 
            border-radius: 12px; border: 2px solid rgba(255,255,255,0.3); 
            z-index: 20; background: #000; overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }
        #localVideo { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }

        /* Status Bar */
        .status-pill {
            position: absolute; top: 20px; left: 50%; transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.8); color: white;
            padding: 8px 20px; border-radius: 50px;
            font-size: 14px; font-weight: 600;
            backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 10px; z-index: 30;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Controls */
        .controls {
            position: absolute; bottom: 30px; left: 50%; 
            transform: translateX(-50%); z-index: 30;
            display: flex; gap: 20px;
        }
        .btn-control {
            width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: white; cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            transition: transform 0.1s;
        }
        .btn-control:active { transform: scale(0.9); }
    </style>
</head>
<body>

    <div class="video-container">
        <div class="status-pill">
            <div id="statusDot" class="w-2.5 h-2.5 rounded-full bg-yellow-500 animate-pulse"></div>
            <span id="statusText">Inisialisasi...</span>
        </div>

        <video id="remoteVideo" autoplay playsinline></video>
        
        <div class="local-wrapper" id="localWrapper">
            <video id="localVideo" autoplay playsinline muted></video>
        </div>

        <div class="controls">
            <button onclick="endCall()" class="btn-control bg-red-600 hover:bg-red-700">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>

    <script>
        const roomId = "<?= $room_id ?>";
        const myRole = "<?= $role ?>";
        const isInitiator = (myRole === 'admin'); // Admin selalu Penelepon

        // --- KONFIGURASI SERVER STUN ---
        const config = {
            iceServers: [
                { urls: "stun:stun.l.google.com:19302" },
                { urls: "stun:stun1.l.google.com:19302" },
                { urls: "stun:stun2.l.google.com:19302" }
            ]
        };

        let pc;
        let localStream;
        let processedSignals = new Set();
        let candidateQueue = []; // Antrian data jaringan

        const statusText = document.getElementById('statusText');
        const statusDot = document.getElementById('statusDot');

        // Fungsi Update Status di Layar
        function updateStatus(msg, color) {
            console.log("[STATUS]", msg);
            statusText.innerText = msg;
            statusDot.className = `w-2.5 h-2.5 rounded-full bg-${color}-500 ${color !== 'green' ? 'animate-pulse' : ''}`;
        }

        async function start() {
            try {
                updateStatus("Membuka Kamera...", "yellow");

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert("Browser tidak mendukung WebRTC atau tidak menggunakan HTTPS.");
                    return;
                }

                // 1. Ambil Akses Kamera & Mic
                localStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" }, 
                    audio: true 
                });
                
                document.getElementById('localVideo').srcObject = localStream;

                // 2. Buat Koneksi WebRTC
                createPeerConnection();

                // 3. Logika Memulai
                if (isInitiator) {
                    updateStatus("Membuat Penawaran...", "blue");
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    sendSignal('offer', offer);
                    updateStatus("Memanggil Driver...", "blue");
                } else {
                    updateStatus("Menunggu Admin...", "yellow");
                }

                // 4. Mulai Cek Sinyal (Polling)
                setInterval(checkSignals, 1500); 

            } catch (err) {
                console.error(err);
                alert("Gagal Akses Kamera! \n1. Pastikan pakai HTTPS. \n2. Izinkan Kamera. \nError: " + err.message);
                updateStatus("Error Kamera", "red");
            }
        }

        function createPeerConnection() {
            try {
                pc = new RTCPeerConnection(config);

                // Masukkan stream lokal ke koneksi
                localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

                // SAAT STREAM VIDEO LAWAN MASUK (PERBAIKAN BLACK SCREEN)
                pc.ontrack = (event) => {
                    console.log("Stream Lawan Diterima!");
                    const remoteVid = document.getElementById('remoteVideo');
                    
                    if (remoteVid.srcObject !== event.streams[0]) {
                        remoteVid.srcObject = event.streams[0];
                        // Paksa mainkan video (untuk mengatasi pemblokiran Autoplay oleh browser HP)
                        remoteVid.play().catch(e => console.log("Mencoba memaksa putar video...", e));
                    }
                    
                    updateStatus("Terhubung", "green");
                };

                // Saat menemukan kandidat jaringan (ICE Candidate)
                pc.onicecandidate = (event) => {
                    if (event.candidate) {
                        sendSignal('candidate', event.candidate);
                    }
                };

                // Monitor Status Koneksi
                pc.oniceconnectionstatechange = () => {
                    const state = pc.iceConnectionState;
                    if(state === 'connected' || state === 'completed') updateStatus("Tersambung (P2P)", "green");
                    if(state === 'disconnected') updateStatus("Koneksi Putus", "red");
                    if(state === 'failed') updateStatus("Gagal Koneksi", "red");
                };
            } catch (e) {
                alert("Gagal membuat koneksi P2P: " + e.message);
            }
        }

        // --- KOMUNIKASI SERVER (SIGNALING) ---
        // PERBAIKAN: Menambahkan 'pages/' pada URL fetch agar tepat sasaran ke file tujuan.

        function sendSignal(type, data) {
            let fd = new FormData();
            fd.append('action', 'send_signal');
            fd.append('room_id', roomId);
            fd.append('type', type);
            fd.append('data', JSON.stringify(data));
            
            // Path diperbaiki menjadi 'pages/ajax_video_signal.php'
            fetch('pages/ajax_video_signal.php', { method: 'POST', body: fd })
            .catch(e => console.log("Signaling error (Send):", e));
        }

        async function checkSignals() {
            let fd = new FormData();
            fd.append('action', 'get_signal');
            fd.append('room_id', roomId);

            try {
                // Path diperbaiki menjadi 'pages/ajax_video_signal.php'
                let res = await fetch('pages/ajax_video_signal.php', { method: 'POST', body: fd });
                let signals = await res.json();

                for (let sig of signals) {
                    if (processedSignals.has(sig.id)) continue;
                    processedSignals.add(sig.id);

                    const data = JSON.parse(sig.data);

                    // LOGIKA DRIVER (PENERIMA)
                    if (sig.type === 'offer' && !isInitiator) {
                        updateStatus("Menerima Panggilan...", "blue");
                        await pc.setRemoteDescription(new RTCSessionDescription(data));
                        processQueue();
                        const answer = await pc.createAnswer();
                        await pc.setLocalDescription(answer);
                        sendSignal('answer', answer);
                        updateStatus("Menghubungkan...", "green");
                    }
                    
                    // LOGIKA ADMIN (PENELEPON)
                    else if (sig.type === 'answer' && isInitiator) {
                        updateStatus("Jawaban Diterima...", "green");
                        if(pc.signalingState === "have-local-offer") {
                            await pc.setRemoteDescription(new RTCSessionDescription(data));
                            processQueue();
                        }
                    }
                    
                    // LOGIKA PERTUKARAN JALUR (CANDIDATE)
                    else if (sig.type === 'candidate') {
                        if (pc.remoteDescription) {
                            await pc.addIceCandidate(new RTCIceCandidate(data));
                        } else {
                            candidateQueue.push(data);
                        }
                    }
                }
            } catch (e) {
                // Silent error (jika koneksi sedang lag)
            }
        }

        async function processQueue() {
            while (candidateQueue.length > 0) {
                let cand = candidateQueue.shift();
                try { await pc.addIceCandidate(new RTCIceCandidate(cand)); } catch(e){}
            }
        }

        function endCall() {
            if(pc) pc.close();
            if(localStream) localStream.getTracks().forEach(track => track.stop());
            window.close();
        }

        // Mulai Sistem
        start();
    </script>
</body>
</html>