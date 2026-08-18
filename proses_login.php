<?php
// Selalu mulai session di baris paling atas
session_start();

// 1. Panggil file konfigurasi database
require_once 'config/config.php';

// Fungsi untuk mengalihkan halaman dengan pesan
function redirect_with_message($location, $message, $type = 'error')
{
    $_SESSION['login_message'] = [
        'text' => $message,
        'type' => $type
    ];
    header("Location: $location");
    exit();
}

// =======================================================
// PENANGANAN LOGIN DARI FORM (USERNAME & PASSWORD)
// =======================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Cek apakah username dan password dikirim
    if (empty($_POST['username']) || empty($_POST['password'])) {
        redirect_with_message('login', 'Username dan password tidak boleh kosong.');
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query untuk mencari user berdasarkan username
    $sql = "SELECT id, nama, username, password, role FROM users WHERE username = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verifikasi password yang di-hash
        if (password_verify($password, $user['password'])) {
            // Jika password cocok, login berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // Alihkan ke dashboard
            header("Location: login_sukses.php");
            exit();
        } else {
            // Jika password salah
            redirect_with_message('login', 'Password yang Anda masukkan salah.');
        }
    } else {
        // Jika username tidak ditemukan
        redirect_with_message('login', 'Username tidak ditemukan.');
    }
    $stmt->close();
}
// =======================================================
// PENANGANAN LOGIN DARI SCAN BARCODE
// =======================================================
elseif (isset($_GET['barcode'])) {
    if (empty($_GET['barcode'])) {
        redirect_with_message('login', 'Kode barcode tidak valid.');
    }

    $kode_barcode = $_GET['barcode'];

    // Query untuk mencari user berdasarkan kode barcode
    $sql = "SELECT id, nama, username, role FROM users WHERE kode_barcode = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kode_barcode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Login berhasil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nama'] = $user['nama'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        // Tentukan kelompok
        if ($user['role'] == 'ketua kmm bintaran') {
            $_SESSION['user_kelompok'] = 'bintaran';
        } else if ($user['role'] == 'ketua kmm gedongkuning') {
            $_SESSION['user_kelompok'] = 'gedongkuning';
        } else if ($user['role'] == 'ketua kmm jombor') {
            $_SESSION['user_kelompok'] = 'jombor';
        } else if ($user['role'] == 'ketua kmm sunten') {
            $_SESSION['user_kelompok'] = 'sunten';
        }

        header("Location: login_sukses");
        exit();
    } else {
        // Jika kode barcode tidak ditemukan
        redirect_with_message('login', 'Kode barcode tidak terdaftar.');
    }
    $stmt->close();
}
// =======================================================
else {
    // Jika file diakses langsung tanpa metode yang benar
    redirect_with_message('login', 'Akses tidak sah.');
}

$conn->close();
