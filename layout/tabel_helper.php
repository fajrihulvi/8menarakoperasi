<?php
/**
 * =========================================================================
 * KOMPONEN PAGINASI & FILTER TABEL (dipakai ulang di banyak halaman)
 * =========================================================================
 * Cara pakai di halaman:
 *
 *   require_once __DIR__ . '/../layout/tabel_helper.php';
 *
 *   $cari   = ambil_kata_kunci();                       // ?cari=...
 *   $hal    = ambil_halaman();                          // ?hal=...
 *   $limit  = ambil_per_halaman();                      // ?per=...
 *
 *   [$where, $params, $tipe] = bangun_filter($cari, ['nama_barang','kode_barang']);
 *   $total  = hitung_total($conn, 'barang', $where, $params, $tipe);
 *   $hal    = batasi_halaman($hal, $total, $limit);
 *   $offset = ($hal - 1) * $limit;
 *
 *   $rows = ambil_data($conn, "SELECT * FROM barang", $where, $params, $tipe,
 *                      'ORDER BY nama_barang ASC', $limit, $offset);
 *
 *   ... render tabel ...
 *   echo render_paginasi($hal, $total, $limit);
 * =========================================================================
 */

if (!defined('TABEL_HELPER_LOADED')) {
    define('TABEL_HELPER_LOADED', true);

    /** Pilihan jumlah baris per halaman */
    function opsi_per_halaman() { return [30, 50, 100, 200, 500]; }

    /** Ambil kata kunci pencarian dari URL */
    function ambil_kata_kunci($param = 'cari') {
        return trim((string) ($_GET[$param] ?? ''));
    }

    /** Ambil nomor halaman dari URL (minimal 1) */
    function ambil_halaman($param = 'hal') {
        $h = (int) ($_GET[$param] ?? 1);
        return $h < 1 ? 1 : $h;
    }

    /** Ambil jumlah baris per halaman (hanya nilai yang diizinkan) */
    function ambil_per_halaman($param = 'per', $default = 30) {
        $p = (int) ($_GET[$param] ?? $default);
        return in_array($p, opsi_per_halaman(), true) ? $p : $default;
    }

    /**
     * Bangun potongan WHERE untuk pencarian di beberapa kolom sekaligus.
     * Mengembalikan [sql_where, params, tipe_bind] untuk prepared statement.
     */
    function bangun_filter($kata_kunci, array $kolom, array $where_tambahan = [], array $params_tambahan = [], $tipe_tambahan = '') {
        $klausa = $where_tambahan;
        $params = $params_tambahan;
        $tipe   = $tipe_tambahan;

        if ($kata_kunci !== '' && $kolom) {
            $bagian = [];
            foreach ($kolom as $k) {
                $bagian[] = "$k LIKE ?";
                $params[] = '%' . $kata_kunci . '%';
                $tipe    .= 's';
            }
            $klausa[] = '(' . implode(' OR ', $bagian) . ')';
        }

        $sql = $klausa ? ' WHERE ' . implode(' AND ', $klausa) : '';
        return [$sql, $params, $tipe];
    }

    /** Jalankan prepared statement dengan parameter dinamis */
    function _jalankan_stmt($conn, $sql, array $params, $tipe) {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) { return false; }
        if ($params) {
            mysqli_stmt_bind_param($stmt, $tipe, ...$params);
        }
        if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return false; }
        $res = mysqli_stmt_get_result($stmt);
        $out = [];
        if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
        mysqli_stmt_close($stmt);
        return $out;
    }

    /** Hitung total baris yang cocok dengan filter (untuk menentukan jumlah halaman) */
    function hitung_total($conn, $dari, $where, array $params = [], $tipe = '') {
        $rows = _jalankan_stmt($conn, "SELECT COUNT(*) AS n FROM $dari" . $where, $params, $tipe);
        return $rows ? (int) $rows[0]['n'] : 0;
    }

    /** Pastikan nomor halaman tidak melebihi jumlah halaman yang ada */
    function batasi_halaman($hal, $total, $limit) {
        $maks = max(1, (int) ceil($total / max(1, $limit)));
        return min(max(1, (int) $hal), $maks);
    }

    /** Ambil satu halaman data */
    function ambil_data($conn, $select, $where, array $params, $tipe, $order = '', $limit = 25, $offset = 0) {
        $sql = $select . $where . ' ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $rows = _jalankan_stmt($conn, $sql, $params, $tipe);
        return $rows === false ? [] : $rows;
    }

    /** Bangun URL dengan mempertahankan parameter lain yang sedang aktif */
    function url_dengan($ubah = []) {
        $q = array_merge($_GET, $ubah);
        foreach ($q as $k => $v) { if ($v === '' || $v === null) { unset($q[$k]); } }
        return '?' . http_build_query($q);
    }

    /**
     * Kotak pencarian + pemilih jumlah baris.
     * $placeholder menjelaskan kolom apa saja yang bisa dicari.
     */
    function render_filter($placeholder = 'Cari data...', $extra_html = '') {
        $cari = htmlspecialchars(ambil_kata_kunci(), ENT_QUOTES, 'UTF-8');
        $per  = ambil_per_halaman();

        // Pertahankan parameter lain (mis. page=barang) sebagai hidden input
        $hidden = '';
        foreach ($_GET as $k => $v) {
            if (in_array($k, ['cari', 'hal', 'per'], true)) { continue; }
            if (is_array($v)) { continue; }
            $hidden .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                     . '" value="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '">';
        }

        $opsi = '';
        foreach (opsi_per_halaman() as $o) {
            $opsi .= '<option value="' . $o . '"' . ($o === $per ? ' selected' : '') . '>' . $o . ' baris</option>';
        }

        $html  = '<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">' . $hidden;
        $html .= '<div class="relative flex-1 min-w-[200px]">';
        $html .= '<i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>';
        $html .= '<input type="text" name="cari" value="' . $cari . '" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" '
               . 'class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">';
        $html .= '</div>';
        $html .= $extra_html;
        $html .= '<select name="per" onchange="this.form.submit()" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">' . $opsi . '</select>';
        $html .= '<button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">Cari</button>';
        if ($cari !== '') {
            $html .= '<a href="' . htmlspecialchars(url_dengan(['cari' => null, 'hal' => null]), ENT_QUOTES, 'UTF-8')
                   . '" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200 transition">Reset</a>';
        }
        $html .= '</form>';
        return $html;
    }

    /** Navigasi halaman + keterangan "Menampilkan X–Y dari Z data" */
    function render_paginasi($hal, $total, $limit) {
        if ($total <= 0) { return ''; }
        $maks = max(1, (int) ceil($total / max(1, $limit)));
        $dari = ($hal - 1) * $limit + 1;
        $sampai = min($hal * $limit, $total);

        $h  = '<div class="flex flex-wrap items-center justify-between gap-3 mt-4 text-sm">';
        $h .= '<span class="text-slate-500">Menampilkan <b>' . number_format($dari) . '</b>–<b>'
            . number_format($sampai) . '</b> dari <b>' . number_format($total) . '</b> data</span>';

        if ($maks > 1) {
            $h .= '<div class="flex items-center gap-1">';

            $kelas_aktif  = 'px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold';
            $kelas_normal = 'px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition';
            $kelas_mati   = 'px-3 py-1.5 rounded-lg bg-slate-50 text-slate-300 cursor-not-allowed';

            // Tombol sebelumnya
            $h .= ($hal > 1)
                ? '<a href="' . htmlspecialchars(url_dengan(['hal' => $hal - 1]), ENT_QUOTES, 'UTF-8') . '" class="' . $kelas_normal . '">&laquo;</a>'
                : '<span class="' . $kelas_mati . '">&laquo;</span>';

            // Tampilkan maksimal 5 nomor di sekitar halaman aktif
            $awal = max(1, $hal - 2);
            $akhir = min($maks, $awal + 4);
            $awal = max(1, $akhir - 4);

            if ($awal > 1) {
                $h .= '<a href="' . htmlspecialchars(url_dengan(['hal' => 1]), ENT_QUOTES, 'UTF-8') . '" class="' . $kelas_normal . '">1</a>';
                if ($awal > 2) { $h .= '<span class="px-1 text-slate-400">…</span>'; }
            }
            for ($i = $awal; $i <= $akhir; $i++) {
                $h .= ($i === $hal)
                    ? '<span class="' . $kelas_aktif . '">' . $i . '</span>'
                    : '<a href="' . htmlspecialchars(url_dengan(['hal' => $i]), ENT_QUOTES, 'UTF-8') . '" class="' . $kelas_normal . '">' . $i . '</a>';
            }
            if ($akhir < $maks) {
                if ($akhir < $maks - 1) { $h .= '<span class="px-1 text-slate-400">…</span>'; }
                $h .= '<a href="' . htmlspecialchars(url_dengan(['hal' => $maks]), ENT_QUOTES, 'UTF-8') . '" class="' . $kelas_normal . '">' . $maks . '</a>';
            }

            // Tombol berikutnya
            $h .= ($hal < $maks)
                ? '<a href="' . htmlspecialchars(url_dengan(['hal' => $hal + 1]), ENT_QUOTES, 'UTF-8') . '" class="' . $kelas_normal . '">&raquo;</a>'
                : '<span class="' . $kelas_mati . '">&raquo;</span>';

            $h .= '</div>';
        }
        $h .= '</div>';
        return $h;
    }

    /** Pesan saat hasil pencarian kosong */
    function render_kosong($kolom_span = 6, $pesan = 'Tidak ada data yang cocok.') {
        return '<tr><td colspan="' . (int) $kolom_span . '" class="text-center py-10 text-slate-400">'
             . '<i class="fa-regular fa-folder-open text-3xl mb-2 block"></i>'
             . htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
}
