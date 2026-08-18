<?php

// 1. Memanggil file konfigurasi database
// Pastikan path/jalur file ini benar sesuai struktur folder Anda.
require_once 'config/config.php';

// ---------------------------------------------------
// DATA PENGGUNA YANG AKAN DITAMBAHKAN
// ---------------------------------------------------
$nama = "Super Admin";
$username = "superadmincai2025";
$plain_password = "adminutamacai2025";
$role = "superadmin";

// ---------------------------------------------------
// PROSES PENAMBAHAN USER
// ---------------------------------------------------

// 3. Hash password untuk keamanan
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

// 4. Buat kode barcode yang unik secara otomatis
$kode_barcode = 'CAI-' . bin2hex(random_bytes(16));

// 5. Cek apakah username sudah ada
$check_sql = "SELECT id FROM users WHERE username = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo "❌ Error: Username '{$username}' sudah terdaftar. Proses dibatalkan.";
} else {
    // 6. Gunakan Prepared Statement untuk memasukkan data
    $sql = "INSERT INTO users (nama, username, password, role, kode_barcode) VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $nama, $username, $hashed_password, $role, $kode_barcode);

    // 7. Eksekusi query dan berikan pesan
    if ($stmt->execute()) {
        echo "✅ **Sukses!** Pengguna 'Super Admin' dengan username '{$username}' berhasil ditambahkan.";
    } else {
        echo "❌ **Error:** Gagal menambahkan pengguna. " . $stmt->error;
    }

    $stmt->close();
}

$check_stmt->close();
$conn->close();

// <?php
// session_start();
// require_once 'vendor/autoload.php';

// // Path ke file kredensial dan file token
// $credentials_path = 'credentials/credential_cai2025_btp1.json';
// $token_path = 'credentials/token.json';

// $client = new Google\Client();
// $client->setAuthConfig($credentials_path);
// $client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/cai25/oauth2callback.php'); // Sesuaikan path jika perlu
// $client->setAccessType('offline'); // Wajib untuk mendapatkan refresh token
// $client->setPrompt('select_account consent'); // Memaksa persetujuan untuk refresh token

// // Jika ada parameter 'code' di URL, berarti pengguna baru saja login
// if (isset($_GET['code'])) {
//     $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
//     $client->setAccessToken($token);

//     // Simpan token ke file
//     if (array_key_exists('error', $token)) {
//         throw new Exception(join(', ', $token));
//     }

//     // Simpan seluruh token, termasuk refresh token
//     file_put_contents($token_path, json_encode($client->getAccessToken()));

//     // Arahkan kembali ke halaman utama pembuatan surat
//     header('Location: admin/admin?page=master/surat_perizinan');
//     exit();
// } else {
//     echo "Kode otorisasi tidak ditemukan.";
// }
