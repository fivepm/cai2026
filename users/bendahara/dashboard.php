<?php
session_start();
// Izinkan akses hanya untuk superadmin dan bendahara (asumsi role 'bendahara' sudah ada)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'bendahara'])) {
    header("Location: ../../login");
    exit();
}
require_once '../../config/config.php';

// Hitung total uang masuk
$result_masuk = $conn->query("SELECT SUM(jumlah) as total FROM log_keuangan WHERE jenis = 'masuk'");
$total_masuk = $result_masuk->fetch_assoc()['total'] ?? 0;

// Hitung total uang keluar
$result_keluar = $conn->query("SELECT SUM(jumlah) as total FROM log_keuangan WHERE jenis = 'keluar'");
$total_keluar = $result_keluar->fetch_assoc()['total'] ?? 0;

// Hitung saldo sekarang
$saldo_sekarang = $total_masuk - $total_keluar;

$nama_user = $_SESSION['user_nama'];
$role_user = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara</title>
    <link rel="icon" type="image/png" href="../../uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100 font-sans">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-30 w-64 px-4 py-7 overflow-y-auto bg-red-700 text-white transition-transform duration-300 md:relative md:translate-x-0">
            <div class="flex items-center justify-center">
                <h2 class="text-center text-2xl font-bold">Bendahara CAI</h2>
                <button @click="sidebarOpen = false" class="md:hidden">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <nav class="mt-10">
                <a href="dashboard" class="flex items-center px-4 py-2 mt-5 text-white-200 bg-red-600 rounded-md">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span class="mx-4 font-medium">Dashboard</span>
                </a>
                <a href="log_keuangan" class="flex items-center px-4 py-2 mt-5 text-white-400 rounded-md hover:bg-red-600">
                    <i class="fas fa-book w-6"></i>
                    <span class="mx-4 font-medium">Log Keuangan</span>
                </a>
                <a href="validasi_pembayaran" class="flex items-center px-4 py-2 mt-5 text-white-400 rounded-md hover:bg-red-600">
                    <i class="fas fa-check-double w-6"></i>
                    <span class="mx-4 font-medium">Validasi Pembayaran</span>
                </a>
            </nav>
        </aside>

        <!-- Konten Utama -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-white border-b-4 border-red-600">
                <button @click="sidebarOpen = true" class="text-gray-500 focus:outline-none md:hidden"><i class="fas fa-bars text-2xl"></i></button>
                <img src="../../uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-10 w-auto">
                <h2 class="px-3 text-xl font-bold text-center text-gray-800">CAI Banguntapan 1 Tahun 2025</h2>
                <div class="flex-1"></div>
                <div x-data="{ dropdownOpen: false }" class="relative">
                    <button @click="dropdownOpen = !dropdownOpen" class="relative z-10 block">
                        <span class="font-medium text-gray-700">Halo, <?php echo htmlspecialchars($nama_user); ?>!</span>
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </button>
                    <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 z-20 w-48 py-2 mt-2 bg-white rounded-md shadow-xl" x-transition>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-500 hover:text-white">Logout</a>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
                <h1 class="text-3xl font-semibold text-gray-800">Ringkasan Keuangan</h1>
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-green-500 text-white p-6 rounded-lg shadow-md">
                        <h2 class="text-lg font-semibold">Total Uang Masuk</h2>
                        <p class="text-4xl font-bold mt-2">Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></p>
                    </div>
                    <div class="bg-red-500 text-white p-6 rounded-lg shadow-md">
                        <h2 class="text-lg font-semibold">Total Uang Keluar</h2>
                        <p class="text-4xl font-bold mt-2">Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></p>
                    </div>
                    <div class="bg-blue-500 text-white p-6 rounded-lg shadow-md">
                        <h2 class="text-lg font-semibold">Saldo Sekarang</h2>
                        <p class="text-4xl font-bold mt-2">Rp <?php echo number_format($saldo_sekarang, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>