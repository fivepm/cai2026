<?php
session_start();

// Keamanan: Pastikan pengguna sudah login
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../../login");
    exit();
}

require_once '../../config/config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

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
    // Mulai output buffering. Ini akan "menangkap" semua output dari file konten.
    ob_start();

    // Muat file konten (misal: pages/dashboard.php)
    // Semua HTML di dalamnya akan ditangkap oleh buffer, bukan langsung ditampilkan.
    include $page_file;

    // Ambil konten yang sudah ditangkap dan simpan ke dalam variabel
    $content = ob_get_clean();
} else {
    // Jika halaman tidak ditemukan, tampilkan pesan error
    http_response_code(404);
    $content = "<h1>404 - Halaman Tidak Ditemukan</h1>";
}

// Terakhir, muat file layout utama dan kirimkan variabel $content ke dalamnya
include 'layout/app.php';
