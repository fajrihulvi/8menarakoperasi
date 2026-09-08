<?php
/**
 * =========================================================================
 * MIGRASI PASSWORD KE BCRYPT (JALANKAN SEKALI, LALU HAPUS FILE INI)
 * =========================================================================
 * Sejak perbaikan keamanan, login TIDAK LAGI menerima password plaintext.
 * Akun lama yang password-nya masih tersimpan sebagai teks biasa harus
 * dikonversi lebih dulu, kalau tidak user tersebut tidak bisa login.
 *
 * Hash MD5 lama TIDAK perlu dimigrasi — sistem menerimanya sekali lalu
 * otomatis menaikkannya ke bcrypt saat user login berikutnya.
 *
 * CARA PAKAI:
 *   1. Nyalakan MySQL, lalu buka: http://localhost/8mp_gudang/migrasi_password.php
 *   2. Periksa daftar akun yang terdeteksi (mode pratinjau, belum mengubah apa pun).
 *   3. Tambahkan ?jalankan=ya di URL untuk benar-benar mengonversi.
 *   4. HAPUS file ini setelah selesai.
 * =========================================================================
 */

require __DIR__ . '/config/koneksi.php';

// Hanya boleh dijalankan dari localhost
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Skrip migrasi hanya boleh dijalankan dari localhost.');
}

$jalankan = (($_GET['jalankan'] ?? '') === 'ya');

header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><style>body{font-family:system-ui,sans-serif;max-width:820px;margin:40px auto;line-height:1.6;color:#1e293b}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px}
.box{border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;margin:16px 0}
.warn{background:#fff7ed;border-color:#fdba74}.ok{background:#f0fdf4;border-color:#86efac}
table{border-collapse:collapse;width:100%;margin-top:10px}
td,th{border:1px solid #e2e8f0;padding:8px 10px;text-align:left;font-size:14px}
th{background:#f8fafc}</style>';

echo '<h2>Migrasi Password ke Bcrypt</h2>';
echo $jalankan
    ? '<div class="box warn"><b>MODE EKSEKUSI</b> — perubahan akan disimpan ke database.</div>'
    : '<div class="box"><b>MODE PRATINJAU</b> — belum ada yang diubah. '
      . 'Tambahkan <code>?jalankan=ya</code> pada URL untuk mengeksekusi.</div>';

$res = mysqli_query($conn, "SELECT id, username, nama, password FROM users ORDER BY id");
if (!$res) {
    die('<div class="box warn">Gagal membaca tabel users: ' . htmlspecialchars(mysqli_error($conn)) . '</div>');
}

$plain = [];
$md5   = [];
$bcrypt = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $p = (string) $row['password'];
    if ($p === '') {
        $plain[] = $row; // password kosong ikut dianggap bermasalah
    } elseif (preg_match('/^\$2[aby]\$/', $p)) {
        $bcrypt++;
    } elseif (preg_match('/^[a-f0-9]{32}$/i', $p)) {
        $md5[] = $row;
    } else {
        $plain[] = $row;
    }
}

echo '<div class="box"><b>Ringkasan:</b><ul>';
echo '<li>Sudah bcrypt (aman, tidak diubah): <b>' . $bcrypt . '</b></li>';
echo '<li>Hash MD5 (otomatis naik saat login, tidak diubah): <b>' . count($md5) . '</b></li>';
echo '<li>Plaintext / format tidak dikenal (<b>perlu migrasi</b>): <b>' . count($plain) . '</b></li>';
echo '</ul></div>';

if (!$plain) {
    echo '<div class="box ok">Tidak ada akun plaintext. Tidak ada yang perlu dimigrasi — '
       . 'Anda bisa langsung menghapus file ini.</div>';
    exit;
}

echo '<table><tr><th>ID</th><th>Username</th><th>Nama</th><th>Status</th></tr>';
foreach ($plain as $u) {
    $status = 'perlu migrasi';
    if ($jalankan) {
        $pw_lama = (string) $u['password'];
        if ($pw_lama === '') {
            $status = 'DILEWATI — password kosong, set manual lewat menu User';
        } else {
            $hash = password_hash($pw_lama, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $hash, $u['id']);
                $status = mysqli_stmt_execute($stmt) ? 'BERHASIL dikonversi' : 'GAGAL menyimpan';
                mysqli_stmt_close($stmt);
            } else {
                $status = 'GAGAL menyiapkan query';
            }
        }
    }
    echo '<tr><td>' . (int) $u['id'] . '</td>'
       . '<td>' . htmlspecialchars($u['username'] ?? '') . '</td>'
       . '<td>' . htmlspecialchars($u['nama'] ?? '') . '</td>'
       . '<td>' . htmlspecialchars($status) . '</td></tr>';
}
echo '</table>';

echo $jalankan
    ? '<div class="box ok">Migrasi selesai. Password lama tetap berfungsi seperti biasa saat login. '
      . '<b>Sekarang hapus file <code>migrasi_password.php</code> ini.</b></div>'
    : '<div class="box">Jika daftar di atas sudah sesuai, jalankan ulang dengan '
      . '<code>?jalankan=ya</code>.</div>';
