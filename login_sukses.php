<?php
session_start();

// Pastikan pengguna sudah login, jika tidak, alihkan ke halaman login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login");
    exit();
}

// Ambil nama dan role dari session (lebih aman daripada dari URL)
$nama_user = $_SESSION['user_nama'] ?? 'Pengguna';
$role_user = $_SESSION['user_role'] ?? '';

// Tentukan halaman tujuan berdasarkan role pengguna
$redirect_url = 'login'; // Halaman default jika role tidak dikenali

switch ($role_user) {
    case 'superadmin':
    case 'admin':
        $redirect_url = 'admin/admin';
        break;

    case 'ketua kmm bintaran':
    case 'ketua kmm gedongkuning':
    case 'ketua kmm jombor':
    case 'ketua kmm sunten':
        // Asumsi semua ketua diarahkan ke dashboard yang sama.
        // Anda bisa mengubah ini ke halaman lain, misal: 'dashboard_ketua.php'
        $redirect_url = 'users/kmm/rekap_pendaftar';
        break;

    case 'sekretaris':
        $redirect_url = 'users/sekretaris/sekretaris';
        break;

    case 'bendahara':
        $redirect_url = 'users/bendahara/dashboard';
        break;

    case 'sie_pdd':
        $redirect_url = 'users/pdd/pendaftar';
        break;

    case 'panitia':
        $redirect_url = 'users/panitia/rekap_pendaftar';
        break;

    // Anda bisa menambahkan role lain di sini jika ada di masa depan
    // case 'peserta':
    //     $redirect_url = 'profil_peserta.php';
    //     break;

    default:
        // Jika role tidak dikenali, arahkan kembali ke halaman login sebagai pengaman
        $redirect_url = 'login';
        break;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta tag ini akan otomatis mengarahkan ke halaman yang sesuai setelah 2.5 detik -->
    <meta http-equiv="refresh" content="2.5;url=<?php echo $redirect_url; ?>">
    <title>Login Berhasil!</title>
    <link rel="icon" type="image/png" href="uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Animasi untuk SVG */
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #4ade80;
            /* green-400 */
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 10% auto;
            box-shadow: inset 0px 0px 0px #4ade80;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }

        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes scale {

            0%,
            100% {
                transform: none;
            }

            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }

        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 60px #4ade80;
            }
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

    <div class="w-full max-w-md p-8 space-y-4 bg-white rounded-xl shadow-lg text-center">

        <!-- SVG Animasi Centang -->
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
        </svg>

        <h2 class="text-3xl font-bold text-gray-800">Login Berhasil!</h2>
        <p class="text-lg text-gray-600">
            Selamat datang kembali, <strong class="font-semibold"><?php echo htmlspecialchars($nama_user); ?></strong>!
        </p>
        <p class="text-sm text-gray-500">Anda akan diarahkan...</p>
    </div>

</body>

</html>