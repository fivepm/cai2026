<?php
session_start();

// Keamanan: Pastikan pengguna sudah login
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../login");
    exit();
}

require_once '../config/config.php';
require_once dirname(__DIR__, 1) . '/vendor/autoload.php';

// Tentukan halaman yang akan dimuat berdasarkan parameter URL
// Jika tidak ada parameter, default-nya adalah 'dashboard'
$page = $_GET['page'] ?? 'dashboard';

// Daftar halaman yang diizinkan untuk mencegah serangan path traversal
$allowed_pages = [
    'dashboard',
    //master
    'master/manajemen_admin',
    'master/manajemen_peserta',
    'master/print_hadir',
    'master/manajemen_izin',
    'master/rekap_pendaftar',
    //keuangan
    'keuangan/log_keuangan',
    'keuangan/validasi_pembayaran',
    //administrasi
    'administrasi/surat_perizinan',
    'administrasi/surat_undangan',
    'administrasi/sesi_penunggu',
    //presensi
    'presensi/manajemen_sesi_presensi',
    'presensi/scanner_kehadiran',
    'presensi/log_kehadiran',
    //peserta
    'peserta/registrasi_ulang',
    'peserta/tambah_peserta',
    // Tambahkan nama file lain di sini
];

// Tentukan path file konten
$page_file = "pages/{$page}.php";

// Periksa apakah file halaman yang diminta ada dan diizinkan
if (in_array($page, $allowed_pages) && file_exists($page_file)) {
    // Cek apakah ini permintaan API
    if (isset($_GET['api']) && $_GET['api'] === 'true') {
        // Jika ya, jalankan logikanya saja dan berhenti (TIDAK ADA LAYOUT)
        require_once $page_file;
    } else {
        // Jika tidak, tampilkan dengan layout seperti biasa
        ob_start();
        require_once $page_file;
        $content = ob_get_clean();
        require_once 'layout/app.php';
    }
} else {
    // Jika halaman tidak ditemukan, tampilkan pesan error
    http_response_code(404);
    $content = "<h1>404 - Halaman Tidak Ditemukan</h1>";
}

// Terakhir, muat file layout utama dan kirimkan variabel $content ke dalamnya
// include 'layout/app.php';
