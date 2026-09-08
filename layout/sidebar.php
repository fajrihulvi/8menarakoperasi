<?php
// --- LOGIKA DATA TOKO ---
$role = $_SESSION['role'] ?? ''; 
$page = $_GET['page'] ?? 'dashboard'; 
$id_usaha_aktif = $_SESSION['id_usaha'] ?? 1;

// Ambil Data Toko Aktif
$q_toko_aktif = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id_usaha = '$id_usaha_aktif'");
$data_toko    = ($q_toko_aktif) ? mysqli_fetch_assoc($q_toko_aktif) : null;
$nama_toko_aktif = $data_toko['nama_usaha'] ?? 'Toko Saya';
$logo_path = "assets/img/" . ($data_toko['logo'] ?? '');

// Hitung Notifikasi Approval (Khusus Admin)
$cek_app = 0;
if(boleh_buka('approval')) {
    $q_app = mysqli_query($conn, "SELECT id FROM approval_request WHERE status='pending' AND id_usaha='$id_usaha_aktif'");
    if($q_app) $cek_app = mysqli_num_rows($q_app);
}

// ==========================================
// PENDETEKSI FOLDER AKTIF (AUTO-OPEN)
// ==========================================
$menu_dapur     = ['master_konversi', 'panel_chef', 'panel_gizi', 'cek_gizi'];
$menu_pelanggan = ['order_pelanggan', 'riwayat_pesanan', 'monitoring_armada'];
$menu_master    = ['barang', 'supplier', 'warehouse', 'pelanggan', 'user', 'audit_stok', 'histori_barang'];
$menu_beli      = ['update_stok_mobile', 'approval', 'po', 'barang_masuk', 'rekap_pembelian', 'retur_pembelian', 'tambah_retur_pembelian'];
$menu_jual      = ['pos', 'input_surat_jalan', 'riwayat_jual', 'list_surat_jalan', 'tracking_driver', 'pesanan_masuk', 'data_retur', 'edit_invoice', 'penjualan', 'histori_transaksi'];
$menu_keuangan  = ['keuangan', 'coa', 'jurnal_umum', 'neraca_saldo', 'laporan_laba_rugi', 'laporan'];
$menu_driver    = ['driver_panel']; // Input SJ & List SJ driver numpang di menu ini
?>

<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= $nama_toko_aktif ?>">
<link rel="apple-touch-icon" href="<?= $logo_path ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* DESAIN SIDEBAR MODERN & CLEAN */
    aside { font-family: 'Plus Jakarta Sans', sans-serif; }
    .sidebar-scroll::-webkit-scrollbar { width: 5px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    
    /* Tombol Utama (Folder) */
    .nav-btn {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.75rem 1rem; border-radius: 0.75rem;
        color: #475569; font-weight: 600; font-size: 0.875rem;
        transition: all 0.3s ease; width: 100%; cursor: pointer;
        border: 1px solid transparent;
    }
    .nav-btn:hover {
        background-color: #f8fafc; color: #4f46e5;
        transform: translateX(3px);
    }
    .nav-active {
        background: linear-gradient(to right, #eef2ff, #f8fafc) !important;
        color: #4f46e5 !important; border-left: 4px solid #4f46e5 !important;
        border-radius: 0 0.75rem 0.75rem 0; font-weight: 700;
    }
    
    /* Anak Menu (Sub-link) */
    .sub-link {
        display: block; padding: 0.5rem 1rem 0.5rem 2.75rem;
        color: #64748b; font-size: 0.8125rem; font-weight: 500;
        border-radius: 0.5rem; transition: all 0.2s; position: relative;
    }
    .sub-link::before {
        content: ''; position: absolute; left: 1.5rem; top: 50%;
        width: 6px; height: 6px; background-color: #cbd5e1;
        border-radius: 50%; transform: translateY(-50%); transition: all 0.2s;
    }
    .sub-link:hover { color: #4f46e5; background-color: #f1f5f9; transform: translateX(3px); }
    .sub-link:hover::before { background-color: #4f46e5; box-shadow: 0 0 6px rgba(79,70,229,0.4); }
    
    .sub-active { color: #4f46e5; font-weight: 700; background-color: #eef2ff; }
    .sub-active::before { background-color: #4f46e5; width: 8px; height: 8px; box-shadow: 0 0 6px rgba(79,70,229,0.4); }
    
    .arrow-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .submenu-container { overflow: hidden; transition: max-height 0.3s ease-in-out; }

    /* POPUP iOS */
    #ios-install-prompt {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: rgba(255, 255, 255, 0.98); border-top: 1px solid #e2e8f0;
        padding: 20px; box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        z-index: 9999; display: none; text-align: center;
        border-radius: 20px 20px 0 0;
    }
</style>

<!-- Mobile Header -->
<div class="md:hidden fixed top-0 left-0 w-full bg-white/90 backdrop-blur-md border-b border-slate-200 px-4 py-3 z-40 flex items-center justify-between shadow-sm">
    <div class="flex items-center">
        <button onclick="toggleSidebar()" class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-bars text-xl"></i></button>
        <span class="ml-3 font-bold text-slate-800 truncate w-40"><?= $nama_toko_aktif ?></span>
    </div>
</div>

<!-- Overlay Background -->
<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden transition-opacity md:hidden backdrop-blur-sm"></div>

<!-- MAIN SIDEBAR -->
<aside id="sidebar" class="flex flex-col w-[260px] h-screen bg-white border-r border-slate-200 shadow-[4px_0_24px_rgba(0,0,0,0.02)] fixed left-0 top-0 z-50 transition-transform duration-300 -translate-x-full md:translate-x-0">
    
    <!-- Header Logo -->
    <div class="h-24 flex flex-col items-center justify-center px-6 border-b border-slate-100 shrink-0 pt-4 pb-2 bg-slate-50/50">
        <div class="flex items-center justify-center w-full mb-2">
            <?php if(!empty($data_toko['logo']) && file_exists($logo_path)): ?>
                <img src="<?= $logo_path ?>" class="h-8 object-contain">
            <?php else: ?>
                <div class="h-8 w-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white mr-2 shadow-md"><i class="fa-solid fa-store"></i></div>
                <span class="font-extrabold text-lg text-slate-800 tracking-tight">8MP System</span>
            <?php endif; ?>
        </div>
        <div class="text-[10px] font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-full uppercase tracking-wider"><?= $nama_toko_aktif ?></div>
        <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-red-500 absolute right-4 top-6 bg-slate-100 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Navigation List -->
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3 py-4 space-y-1">

        <?php
        // =====================================================================
        // MENU DIBANGUN OTOMATIS DARI HAK AKSES (config/hak_akses.php).
        // Link hanya muncul kalau role-nya memang boleh membuka halaman itu,
        // dan folder hanya muncul kalau ada minimal satu link di dalamnya.
        // =====================================================================

        if (!function_exists('link_menu')):

        // Cetak satu link anak menu, hanya jika role berhak membukanya.
        function link_menu($tujuan, $judul, $warna = '') {
            global $page;
            if (!boleh_buka($tujuan)) return '';
            $aktif = ($page == $tujuan) ? 'sub-active' : $warna;
            return '<a href="index.php?page=' . $tujuan . '" class="sub-link ' . $aktif . '">' . $judul . '</a>';
        }

        // Gabungkan beberapa link; kembalikan string kosong kalau tidak ada satupun.
        function kumpulkan($daftar) {
            $isi = '';
            foreach ($daftar as $tujuan => $judul) {
                $warna = '';
                if (is_array($judul)) { $warna = $judul[1]; $judul = $judul[0]; }
                $isi .= link_menu($tujuan, $judul, $warna);
            }
            return $isi;
        }

        // Cetak satu folder lengkap dengan panah buka/tutup.
        function folder_menu($kunci, $judul, $ikon, $warna_ikon, $isi, $halaman_folder, $lencana = '') {
            global $page;
            if (trim($isi) === '') return;   // folder kosong: jangan ditampilkan
            $terbuka = in_array($page, $halaman_folder);
            ?>
            <div>
                <div onclick="toggleSidebarMenu('menu-<?= $kunci ?>')" class="nav-btn <?= $terbuka ? 'bg-slate-50' : '' ?>">
                    <div class="flex items-center">
                        <i class="fa-solid <?= $ikon ?> w-6 text-center <?= $warna_ikon ?> text-lg"></i>
                        <span class="ml-2"><?= $judul ?></span>
                        <?= $lencana ?>
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 arrow-icon" id="arrow-<?= $kunci ?>" style="transform: <?= $terbuka ? 'rotate(180deg)' : 'rotate(0deg)' ?>"></i>
                </div>
                <div id="menu-<?= $kunci ?>" class="submenu-container mt-1 space-y-1 <?= $terbuka ? '' : 'hidden' ?>">
                    <?= $isi ?>
                </div>
            </div>
            <?php
        }

        endif; // penjaga !function_exists
        ?>

        <!-- DASHBOARD -->
        <?php if (!in_array($role, ['invoice', 'driver', 'chef', 'ahli_gizi'])): ?>
        <a href="index.php?page=dashboard" class="nav-btn <?= $page=='dashboard' ? 'nav-active' : '' ?>">
            <div class="flex items-center"><i class="fa-solid fa-grid-2 w-6 text-center text-indigo-500 text-lg"></i><span class="ml-2">Dashboard</span></div>
        </a>
        <?php endif; ?>

        <!-- 1. MANAJEMEN DAPUR -->
        <?php folder_menu('dapur', 'Manajemen Dapur', 'fa-kitchen-set', 'text-orange-500', kumpulkan([
            'master_konversi' => 'Konversi Penyusutan',
            'panel_chef'      => 'Panel Chef',
            'panel_gizi'      => 'Hitung Belanja (Gizi)',
            'cek_gizi'        => 'Cek Kalori (API)',
        ]), $menu_dapur); ?>

        <!-- 2. PORTAL PELANGGAN -->
        <?php folder_menu('pelanggan', 'Portal Pelanggan', 'fa-users', 'text-pink-500', kumpulkan([
            'order_pelanggan'   => 'Buat Pesanan Baru',
            'riwayat_pesanan'   => 'Riwayat & Retur',
            'monitoring_armada' => 'Lacak Driver Saya',
        ]), $menu_pelanggan); ?>

        <!-- AREA UTAMA (bukan pelanggan / driver / dapur) -->
        <?php if (!in_array($role, ['pelanggan', 'driver', 'chef', 'ahli_gizi'])): ?>

            <div class="pt-5 pb-1 px-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sistem Utama</div>

            <!-- 3. MASTER DATA -->
            <?php folder_menu('master', 'Master Data', 'fa-database', 'text-emerald-500', kumpulkan([
                'barang'     => 'Data Barang',
                'supplier'   => 'Data Supplier',
                'warehouse'  => 'Data Warehouse',
                'pelanggan'  => 'Data Pelanggan',
                'audit_stok' => 'Audit Stok AI',
                'user'       => ['User & Akses', 'text-rose-500'],
            ]), $menu_master); ?>

            <!-- 4. STOK & RESTOCK -->
            <?php
            $lencana_app = ($cek_app > 0 && boleh_buka('approval'))
                ? '<span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-2 animate-pulse">' . $cek_app . '</span>'
                : '';
            folder_menu('beli', 'Stok & Restock', 'fa-truck-ramp-box', 'text-cyan-500', kumpulkan([
                'update_stok_mobile' => 'Update Stok Mobile',
                'approval'           => 'Persetujuan PO',
                'po'                 => 'Buat PO Baru',
                'barang_masuk'       => 'Barang Masuk (BM)',
                'rekap_pembelian'    => 'Rekap Nota Beli',
                'retur_pembelian'    => ['Retur ke Supplier', 'text-rose-500'],
                'order_pelanggan'    => 'Order Bahan Baku',
            ]), $menu_beli, $lencana_app);
            ?>

            <!-- 5. PENJUALAN (SALES) -->
            <?php folder_menu('jual', 'Penjualan (Sales)', 'fa-cart-shopping', 'text-blue-500', kumpulkan([
                'pos'               => 'Kasir (POS)',
                'input_surat_jalan' => 'Input Surat Jalan',
                'riwayat_jual'      => 'Riwayat & Invoice',
                'list_surat_jalan'  => 'Cetak Surat Jalan',
                'pesanan_masuk'     => 'Pesanan Masuk',
                'tracking_driver'   => 'Pantau Driver (Live)',
                'data_retur'        => ['Retur dari Customer', 'text-rose-500'],
            ]), $menu_jual); ?>

            <!-- 6. KEUANGAN & REPORT -->
            <?php
            $isi_keuangan = kumpulkan([
                'keuangan'          => 'Buku Arus Kas',
                'coa'               => 'Daftar Akun (COA)',
                'jurnal_umum'       => 'Jurnal Umum',
                'neraca_saldo'      => 'Neraca Saldo',
                'laporan_laba_rugi' => 'Laba Rugi',
            ]);
            if ($isi_keuangan !== '') {
                $isi_keuangan .= '<a href="laporan_cetak.php?jenis=penjualan&tgl_awal=' . date('Y-m-01')
                              . '&tgl_akhir=' . date('Y-m-d') . '" target="_blank" class="sub-link">Rekap Penjualan</a>';
            }
            folder_menu('keuangan', 'Keuangan &amp; Report', 'fa-chart-pie', 'text-rose-500', $isi_keuangan, $menu_keuangan);
            ?>

            <!-- 7. PENGATURAN -->
            <?php if (boleh_buka('pengaturan')): ?>
            <div class="mt-2">
                <a href="index.php?page=pengaturan" class="nav-btn <?= $page=='pengaturan' ? 'nav-active' : '' ?>">
                    <div class="flex items-center"><i class="fa-solid fa-gear w-6 text-center text-slate-400 text-lg"></i><span class="ml-2">Pengaturan</span></div>
                </a>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- 8. MENU DRIVER -->
        <?php if ($role == 'driver'): ?>
        <?php $is_open = in_array($page, $menu_driver) || in_array($page, ['input_surat_jalan', 'list_surat_jalan']); ?>
        <div>
            <div onclick="toggleSidebarMenu('menu-driver')" class="nav-btn <?= $is_open ? 'bg-slate-50' : '' ?>">
                <div class="flex items-center"><i class="fa-solid fa-steering-wheel w-6 text-center text-green-500 text-lg"></i><span class="ml-2">Portal Driver</span></div>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 arrow-icon" id="arrow-driver" style="transform: <?= $is_open ? 'rotate(180deg)' : 'rotate(0deg)' ?>"></i>
            </div>
            <div id="menu-driver" class="submenu-container mt-1 space-y-1 <?= $is_open ? '' : 'hidden' ?>">
                <a href="index.php?page=driver_panel" class="sub-link <?= $page=='driver_panel' ? 'sub-active' : '' ?>">Panel Driver</a>
            </div>
        </div>
        <?php endif; ?>

    </nav>
    
    <!-- Footer Sidebar -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/80">
        <a href="logout.php" class="flex items-center justify-center w-full px-4 py-2.5 text-sm font-bold text-rose-500 bg-white border border-rose-100 hover:bg-rose-50 hover:border-rose-200 rounded-xl transition-all shadow-sm group">
            <i class="fa-solid fa-power-off mr-2 group-hover:scale-110 transition-transform"></i><span>Keluar Sistem</span>
        </a>
    </div>
</aside>

<!-- Popup Install iOS -->
<div id="ios-install-prompt">
    <div class="flex items-center justify-between mb-2">
        <span class="font-bold text-slate-800">Install Aplikasi ke iPhone?</span>
        <button onclick="document.getElementById('ios-install-prompt').style.display='none'" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times text-lg"></i></button>
    </div>
    <p class="text-sm text-slate-600 mb-3">Aplikasi ini bisa dipasang di iPhone agar lebih mudah diakses.</p>
    <div class="text-xs text-slate-600 bg-slate-50 border border-slate-200 p-3 rounded-xl text-left leading-relaxed">
        1. Ketuk ikon <strong>Share</strong> <i class="fa-solid fa-arrow-up-from-bracket mx-1 text-blue-500"></i> di Safari.<br>
        2. Pilih menu <strong>"Add to Home Screen"</strong> (Tambah ke Utama).<br>
        3. Klik <strong>Add</strong> (Tambah).
    </div>
</div>

<script>
// Logika Animasi Buka/Tutup Folder Accordion
function toggleSidebarMenu(id) {
    const menu = document.getElementById(id);
    const arrow = document.getElementById(id.replace('menu-', 'arrow-'));
    
    if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');
        if(arrow) arrow.style.transform = 'rotate(180deg)';
    } else {
        menu.classList.add('hidden');
        if(arrow) arrow.style.transform = 'rotate(0deg)';
    }
}

// Logika Buka/Tutup Sidebar (Mode HP)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
}

// Logika Deteksi Install iOS
const isIos = () => {
  const userAgent = window.navigator.userAgent.toLowerCase();
  return /iphone|ipad|ipod/.test( userAgent );
}
const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);

if (isIos() && !isInStandaloneMode()) {
    setTimeout(() => {
        document.getElementById('ios-install-prompt').style.display = 'block';
    }, 3000);
}
</script>