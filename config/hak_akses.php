<?php
// =========================================================================
// config/hak_akses.php  —  PUSAT PENGATURAN HAK AKSES (RBAC)
// =========================================================================
// Semua aturan siapa boleh membuka halaman apa, dan siapa boleh
// tambah/edit/hapus, ditentukan DI FILE INI SAJA.
//
// Cara menambah/mengubah hak akses cukup edit dua peta di bawah:
//   1. $PETA_HALAMAN  -> role apa saja yang boleh MEMBUKA halaman
//   2. $PETA_AKSI     -> role apa saja yang boleh TAMBAH/EDIT/HAPUS
//
// Fungsi yang dipakai di file lain:
//   wajib_akses('nama_halaman')      -> hentikan halaman jika role tidak berhak
//   boleh_buka('nama_halaman')       -> true/false, untuk menyembunyikan menu
//   boleh('tambah', 'barang')        -> true/false, untuk menyembunyikan tombol
//   tolak_jika_tidak_boleh(...)      -> hentikan proses POST/GET yang tidak sah
// =========================================================================

if (!defined('HAK_AKSES_DIMUAT')) {
define('HAK_AKSES_DIMUAT', true);

// -------------------------------------------------------------------------
// DAFTAR ROLE RESMI (dipakai juga sebagai whitelist di halaman User)
// -------------------------------------------------------------------------
$DAFTAR_ROLE = [
    'admin'      => 'Administrator',
    'po'         => 'Admin PO',
    'gudang'     => 'Admin Gudang',
    'accounting' => 'Accounting',
    'viewer'     => 'Viewer (Lihat Saja)',
    'invoice'    => 'Admin Invoice',
    'driver'     => 'Driver',
    'chef'       => 'Chef',
    'ahli_gizi'  => 'Ahli Gizi',
    'pelanggan'  => 'Pelanggan (Order)',
];

// -------------------------------------------------------------------------
// ROLE YANG SIFATNYA HANYA MELIHAT (READ-ONLY MUTLAK)
// Apapun isi $PETA_AKSI, role di sini tidak akan pernah bisa mengubah data.
// -------------------------------------------------------------------------
$ROLE_READONLY = ['viewer'];

// -------------------------------------------------------------------------
// 1. PETA HALAMAN — siapa boleh MEMBUKA halaman apa
//    'admin' tidak perlu ditulis, karena admin selalu boleh semuanya.
// -------------------------------------------------------------------------
$PETA_HALAMAN = [

    // --- Umum ---
    'dashboard'            => ['po', 'gudang', 'accounting', 'viewer', 'invoice', 'pelanggan'],

    // --- Master Data ---
    'barang'               => ['po', 'gudang', 'accounting', 'viewer', 'invoice'],
    'kategori'             => ['po', 'gudang', 'accounting', 'viewer', 'invoice'],
    'jenis_barang'         => ['po', 'gudang', 'accounting', 'viewer', 'invoice'],
    'supplier'             => ['po', 'gudang', 'viewer'],
    'warehouse'            => ['po', 'gudang', 'viewer'],
    'pelanggan'            => ['gudang', 'viewer'],
    'histori_barang'       => ['po', 'gudang', 'accounting', 'viewer', 'invoice'],
    'audit_stok'           => ['accounting'],
    'user'                 => [],           // admin saja
    'pengaturan'           => [],           // admin saja

    // --- Stok & Restock ---
    'po'                   => ['po', 'viewer'],
    'retur_pembelian'      => ['po', 'viewer'],
    'tambah_retur_pembelian' => ['po'],
    'barang_masuk'         => ['gudang', 'viewer'],
    'rekap_pembelian'      => ['gudang', 'accounting', 'viewer'],
    'update_stok_mobile'   => [],           // admin saja
    'approval'             => [],           // admin saja
    'stock_opname'         => ['gudang', 'viewer'], // admin otomatis akses semua halaman

    // --- Penjualan ---
    'pesanan_masuk'        => ['po', 'gudang', 'viewer'],
    'input_surat_jalan'    => ['gudang', 'invoice'],
    'list_surat_jalan'     => ['gudang', 'accounting', 'viewer', 'invoice'],
    'riwayat_jual'         => ['accounting', 'viewer', 'invoice'],
    'edit_invoice'         => ['accounting', 'invoice'],
    'pos'                  => ['invoice'],
    'data_retur'           => ['viewer'],
    'tracking_driver'      => ['viewer'],
    'penjualan'            => ['accounting', 'viewer'],
    'histori_transaksi'    => ['accounting', 'viewer'],
    'laporan'              => ['accounting', 'viewer'],

    // --- Keuangan & Report ---
    'keuangan'             => ['accounting', 'viewer', 'invoice'],
    'coa'                  => ['accounting', 'viewer', 'invoice'],
    'jurnal_umum'          => ['accounting', 'viewer', 'invoice'],
    'neraca_saldo'         => ['accounting', 'viewer', 'invoice'],
    'laporan_laba_rugi'    => ['accounting', 'viewer', 'invoice'],

    // --- Portal Pelanggan ---
    'order_pelanggan'      => ['pelanggan', 'invoice'],
    'riwayat_pesanan'      => ['pelanggan', 'invoice', 'driver'],
    'surat_jalan_saya'     => ['pelanggan', 'invoice'],
    'monitoring_armada'    => ['pelanggan', 'invoice'],
    'form_order'           => ['pelanggan', 'invoice'],

    // --- Dapur ---
    'master_konversi'      => ['chef', 'ahli_gizi'],
    'panel_chef'           => ['chef'],
    'panel_gizi'           => ['chef', 'ahli_gizi'],
    'cek_gizi'             => ['ahli_gizi'],
    'konversi_gizi'        => ['chef', 'ahli_gizi'],

    // --- Driver ---
    'driver_panel'         => ['driver'],
    'driver_panel1'        => ['driver'],
    'video_room'           => ['driver'],
];

// -------------------------------------------------------------------------
// 2. PETA AKSI — siapa boleh TAMBAH / EDIT / HAPUS pada modul tertentu
//    Format: 'modul' => ['tambah' => [...], 'edit' => [...], 'hapus' => [...]]
//    'admin' selalu boleh, tidak perlu ditulis.
//    Role yang boleh MEMBUKA halaman tapi tidak terdaftar di sini =
//    otomatis hanya bisa MELIHAT.
// -------------------------------------------------------------------------
$PETA_AKSI = [

    // Data Barang: PO, Gudang, Accounting hanya lihat (tanpa edit & hapus)
    'barang' => [
        'tambah' => [],
        'edit'   => [],
        'hapus'  => [],
        'import' => [],
    ],

    // Data Kategori: PO & Gudang boleh kelola
    'kategori' => [
        'tambah' => ['po', 'gudang'],
        'edit'   => ['po', 'gudang'],
        'hapus'  => [],           // hapus khusus admin
    ],

    // Data Jenis Barang: PO & Gudang boleh kelola
    'jenis_barang' => [
        'tambah' => ['po', 'gudang'],
        'edit'   => ['po', 'gudang'],
        'hapus'  => [],           // hapus khusus admin
    ],

    // Data Supplier: PO & Gudang boleh kelola
    'supplier' => [
        'tambah' => ['po', 'gudang'],
        'edit'   => ['po', 'gudang'],
        'hapus'  => [],           // hapus khusus admin
    ],

    // Data Warehouse: PO & Gudang boleh kelola
    'warehouse' => [
        'tambah' => ['po', 'gudang'],
        'edit'   => ['po', 'gudang'],
        'hapus'  => [],           // hapus khusus admin
    ],

    // Data Pelanggan: Gudang boleh kelola
    'pelanggan' => [
        'tambah' => ['gudang'],
        'edit'   => ['gudang'],
        'hapus'  => [],           // hapus khusus admin
    ],

    // Purchase Order: PO boleh buat (lewat approval)
    'po' => [
        'tambah' => ['po'],
        'edit'   => ['po'],
        'hapus'  => [],
    ],

    // Retur ke Supplier: PO boleh buat
    'retur_pembelian' => [
        'tambah' => ['po'],
        'edit'   => ['po'],
        'hapus'  => [],
    ],

    // Stock Opname: Gudang boleh ajukan, approve/reject khusus admin
    'stock_opname' => [
        'tambah' => ['gudang'],
        'edit'   => [],
        'hapus'  => [],
    ],

    // Barang Masuk: Gudang boleh input
    'barang_masuk' => [
        'tambah' => ['gudang'],
        'edit'   => ['gudang'],
        'hapus'  => [],
    ],

    // Nota / Rekap Pembelian: lihat saja untuk gudang & accounting
    'rekap_pembelian' => [
        'tambah' => [],
        'edit'   => [],
        'hapus'  => [],
    ],

    // Surat Jalan: Gudang & Invoice boleh input.
    // Gudang WAJIB lewat approval (lihat $ROLE_SJ_BUTUH_APPROVAL).
    'surat_jalan' => [
        'tambah' => ['gudang', 'invoice'],
        'edit'   => ['invoice'],   // gudang TIDAK boleh edit SJ yang sudah dicetak
        'hapus'  => [],
    ],

    // Pesanan Masuk: PO boleh proses, Gudang hanya melihat
    'pesanan_masuk' => [
        'tambah' => [],
        'edit'   => ['po'],
        'hapus'  => [],
    ],

    // Riwayat & Invoice: Accounting boleh edit invoice TAPI bukan quantity
    // (lihat $BLOKIR_EDIT_QTY di bawah)
    'invoice' => [
        'tambah' => ['invoice'],
        'edit'   => ['accounting', 'invoice'],
        'hapus'  => [],
    ],

    // Keuangan & Akuntansi
    'keuangan' => [
        'tambah' => ['accounting', 'invoice'],
        'edit'   => ['accounting', 'invoice'],
        'hapus'  => [],
    ],
];

// -------------------------------------------------------------------------
// 3. ATURAN KHUSUS
// -------------------------------------------------------------------------

// Role yang Surat Jalannya wajib disetujui admin dulu (masuk approval_request)
$ROLE_SJ_BUTUH_APPROVAL = ['po', 'gudang'];

// Role yang boleh membuka invoice tapi DILARANG mengubah quantity barang
$BLOKIR_EDIT_QTY = ['accounting'];

// =========================================================================
// FUNGSI-FUNGSI PEMBANTU
// =========================================================================

// Ambil role user yang sedang login (huruf kecil semua)
if (!function_exists('role_saya')) {
    function role_saya() {
        return strtolower(trim($_SESSION['role'] ?? ''));
    }
}

// Apakah user sekarang administrator?
if (!function_exists('saya_admin')) {
    function saya_admin() {
        return role_saya() === 'admin';
    }
}

// Apakah role ini sifatnya hanya melihat (viewer)?
if (!function_exists('saya_readonly')) {
    function saya_readonly($role = null) {
        global $ROLE_READONLY;
        $role = $role !== null ? strtolower($role) : role_saya();
        return in_array($role, $ROLE_READONLY, true);
    }
}

// Boleh membuka halaman tertentu? (dipakai sidebar untuk sembunyikan menu)
if (!function_exists('boleh_buka')) {
    function boleh_buka($halaman, $role = null) {
        global $PETA_HALAMAN;
        $role = $role !== null ? strtolower($role) : role_saya();

        if ($role === '')      return false;   // belum login
        if ($role === 'admin') return true;    // admin: semua akses tanpa terkecuali

        // Halaman yang tidak terdaftar dianggap TERTUTUP (aman secara default).
        if (!array_key_exists($halaman, $PETA_HALAMAN)) return false;

        return in_array($role, $PETA_HALAMAN[$halaman], true);
    }
}

// Boleh melakukan aksi ('tambah' | 'edit' | 'hapus' | 'import') pada satu modul?
if (!function_exists('boleh')) {
    function boleh($aksi, $modul, $role = null) {
        global $PETA_AKSI;
        $role = $role !== null ? strtolower($role) : role_saya();

        if ($role === '')      return false;
        if ($role === 'admin') return true;    // admin: semua akses tanpa terkecuali
        if (saya_readonly($role)) return false; // viewer: mutlak tidak bisa mengubah

        if (!isset($PETA_AKSI[$modul][$aksi])) return false;

        return in_array($role, $PETA_AKSI[$modul][$aksi], true);
    }
}

// Boleh mengubah quantity pada invoice / surat jalan?
if (!function_exists('boleh_edit_qty')) {
    function boleh_edit_qty($role = null) {
        global $BLOKIR_EDIT_QTY;
        $role = $role !== null ? strtolower($role) : role_saya();

        if ($role === 'admin') return true;
        if (saya_readonly($role)) return false;

        return !in_array($role, $BLOKIR_EDIT_QTY, true);
    }
}

// Surat Jalan dari role ini wajib menunggu persetujuan admin?
if (!function_exists('sj_butuh_approval')) {
    function sj_butuh_approval($role = null) {
        global $ROLE_SJ_BUTUH_APPROVAL;
        $role = $role !== null ? strtolower($role) : role_saya();

        if ($role === 'admin') return false;

        return in_array($role, $ROLE_SJ_BUTUH_APPROVAL, true);
    }
}

// Penjaga halaman: hentikan eksekusi jika role tidak berhak membuka halaman.
if (!function_exists('wajib_akses')) {
    function wajib_akses($halaman) {
        if (boleh_buka($halaman)) return;

        echo "<div class='bg-red-50 border-l-4 border-red-500 text-red-700 p-5 rounded-lg shadow-sm'>
                <p class='font-bold text-lg mb-1'><i class='fa-solid fa-ban mr-2'></i>Akses Ditolak</p>
                <p class='text-sm'>Role <b>" . htmlspecialchars(role_saya()) . "</b> tidak memiliki izin untuk membuka halaman ini.</p>
                <a href='index.php' class='inline-block mt-3 text-sm font-bold text-indigo-600 hover:underline'>&larr; Kembali ke Beranda</a>
              </div>";
        exit();
    }
}

// Penjaga aksi: hentikan proses simpan/hapus yang tidak sah.
// Dipakai di dalam blok if(isset($_POST['simpan'])) { ... }
if (!function_exists('tolak_jika_tidak_boleh')) {
    function tolak_jika_tidak_boleh($aksi, $modul, $kembali_ke = null) {
        if (boleh($aksi, $modul)) return;

        $pesan = saya_readonly()
            ? 'Role Viewer hanya dapat melihat data, tidak dapat mengubah apapun.'
            : 'Role Anda tidak memiliki izin untuk melakukan aksi ini.';

        $tujuan = $kembali_ke ? "window.location='" . addslashes($kembali_ke) . "';" : "window.history.back();";
        echo "<script>alert(" . json_encode($pesan) . "); $tujuan</script>";
        exit();
    }
}

// Nama role yang enak dibaca manusia (untuk ditampilkan di layar)
if (!function_exists('label_role')) {
    function label_role($role) {
        global $DAFTAR_ROLE;
        $role = strtolower(trim($role));
        return $DAFTAR_ROLE[$role] ?? ucfirst($role);
    }
}

} // penutup HAK_AKSES_DIMUAT
