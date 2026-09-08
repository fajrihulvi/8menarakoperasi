<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang Dalam Pemeliharaan</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 flex items-center justify-center px-4 sm:px-6 lg:px-8 relative overflow-hidden">

    <!-- Background Glow Effects -->
    <div class="absolute top-1/4 left-1/4 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 translate-x-1/2 translate-y-1/2 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-xl w-full mx-auto text-center relative z-10">
        
        <!-- Logo / Brand Icon -->
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl mb-8 group">
            <i class="fa-solid fa-server text-3xl text-blue-500 animate-pulse"></i>
        </div>

        <!-- Main Heading -->
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight mb-3">
            Sistem Sedang Pemeliharaan
        </h1>
        <p class="text-slate-400 text-base sm:text-lg mb-8 max-w-md mx-auto">
            Kami sedang melakukan peningkatan performa infrastruktur server untuk memberikan pengalaman yang lebih baik dan aman.
        </p>

        <!-- Schedule & Status Card -->
        <div class="bg-slate-900/80 backdrop-blur-md border border-slate-800/80 rounded-2xl p-6 mb-8 shadow-2xl text-left">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-800">
                <span class="text-xs font-semibold tracking-wider uppercase text-blue-400 bg-blue-500/10 px-3 py-1 rounded-full border border-blue-500/20">
                    <i class="fa-solid fa-circle text-[8px] mr-1.5 animate-ping"></i> Scheduled Maintenance
                </span>
                <span class="text-sm text-slate-400 font-medium">
                    <i class="fa-regular fa-clock mr-1.5 text-slate-500"></i> 12:00 - 12:10 WIB
                </span>
            </div>

            <!-- Update Details List -->
            <div class="space-y-3">
                <div class="flex items-start space-x-3">
                    <div class="mt-1 flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs border border-emerald-500/20">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-200">Migrasi Data</p>
                        <p class="text-xs text-slate-400">Migrasi dari Database ke versi terbaru untuk kestabilan dan kecepatan eksekusi.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-3">
                    <div class="mt-1 flex-shrink-0 w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs border border-emerald-500/20">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-200">Patch & Hardening Keamanan (Security Update)</p>
                        <p class="text-xs text-slate-400">Pembaruan firewall sistem, modul keamanan web, dan enkripsi data end-to-end dan Integrasi ke system keamanan Global Cloudflare.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Help / Refresh Note -->
        <div class="text-xs text-slate-500">
            <p>Terima kasih atas kesabaran Anda. Halaman ini akan memuat ulang otomatis setelah pemeliharaan selesai.</p>
            <button onclick="window.location.reload()" class="mt-3 inline-flex items-center space-x-2 text-slate-400 hover:text-slate-200 transition-colors bg-slate-900 hover:bg-slate-800 px-4 py-2 rounded-lg border border-slate-800">
                <i class="fa-solid fa-rotate-right"></i>
                <span>Cek Status Server</span>
            </button>
        </div>

    </div>

    <!-- JavaScript Countdown Logic (Target: 02:00 WIB) -->
    <script>
        // Mengatur waktu target pukul 02:00 WIB hari ini
        function updateCountdown() {
            const now = new Date();
            const targetTime = new Date();
            
            // Set target jam 02:00:00
            targetTime.setHours(2, 0, 0, 0);

            // Jika saat ini sudah lewat jam 02:00 WIB, set target ke hari berikutnya (opsional, untuk amannya)
            if (now > targetTime) {
                targetTime.setDate(targetTime.getDate() + 1);
            }

            const diff = targetTime - now;

            if (diff <= 0) {
                document.getElementById("hours").innerText = "00";
                document.getElementById("minutes").innerText = "00";
                document.getElementById("seconds").innerText = "00";
                setTimeout(() => window.location.reload(), 3000);
                return;
            }

            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById("hours").innerText = String(hours).padStart(2, '0');
            document.getElementById("minutes").innerText = String(minutes).padStart(2, '0');
            document.getElementById("seconds").innerText = String(seconds).padStart(2, '0');
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
</body>
</html>