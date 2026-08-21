<?php
// Ambil data yang dibutuhkan oleh layout, seperti nama dan role pengguna
$nama_user = $_SESSION['user_nama'] ?? 'Pengguna';
$role_user = $_SESSION['user_role'] ?? '';
$current_page = $_GET['page'] ?? 'dashboard'; // Untuk menandai menu aktif

// Cek apakah halaman saat ini ada di dalam grup master data
$isMasterDataPage = strpos($current_page, 'master/') === 0;
$isKeuanganPage = strpos($current_page, 'keuangan/') === 0;
$isAdministrasiPage = strpos($current_page, 'administrasi/') === 0;
$isPresensiPage = strpos($current_page, 'presensi/') === 0;
$isPesertaPage = strpos($current_page, 'peserta/') === 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Judul halaman bisa dibuat dinamis -->
    <title><?php echo ucfirst(str_replace(['_', '/'], ' ', $current_page)); ?> - CAI 2026</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <!-- PENTING: Pustaka untuk Scan QR Code -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }

        /* <!-- ============================================= -->
        <!-- KODE BARU: CSS untuk Animasi Loading -->
        <!-- ============================================= --> */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .loading-overlay.show {
            visibility: visible;
            opacity: 1;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid rgba(255, 255, 255, 0.3);
            border-top-color: #2563eb;
            /* Warna biru tema CAI 2026 */
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: white;
            margin-top: 1.5rem;
            font-size: 1.1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed no-print inset-y-0 left-0 z-30 w-64 px-4 py-4 overflow-y-auto bg-blue-600 text-white transition-transform duration-300 md:relative md:translate-x-0 shadow-lg">
            <div>
                <div class="flex items-center justify-center mb-2">
                    <img src="../../assets/images/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto object-contain drop-shadow-sm">
                </div>
                <div class="flex items-center justify-center relative">
                    <h2 class="text-xl font-bold text-white text-center tracking-wide">CAI XLVII <br> 2026</h2>
                    <button @click="sidebarOpen = false" class="md:hidden absolute right-0 text-white hover:text-blue-200"><i class="fas fa-times text-xl"></i></button>
                </div>

                <!-- Navigasi dengan Alpine.js untuk toggle -->
                <nav class="mt-6 space-y-1.5"
                x-data="{ 
                isMasterDataOpen: <?php echo $isMasterDataPage ? 'true' : 'false'; ?>,
                isKeuanganOpen: <?php echo $isKeuanganPage ? 'true' : 'false'; ?>, 
                isAdministrasiOpen: <?php echo $isAdministrasiPage ? 'true' : 'false'; ?>,
                isPresensiOpen: <?php echo $isPresensiPage ? 'true' : 'false'; ?>,
                isPesertaOpen: <?php echo $isPesertaPage ? 'true' : 'false'; ?>,
                }">
                <a href="sekretaris?page=dashboard" class="flex items-center px-4 py-2.5 rounded-lg transition-colors duration-150 <?php echo $current_page == 'dashboard' ? 'bg-blue-800 text-white font-semibold shadow-inner' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                    <i class="fas fa-tachometer-alt w-6"></i><span class="mx-4 font-medium">Dashboard</span>
                </a>

                <!-- Menu Toggle untuk Master Data -->
                <!-- <div>
                    <button @click="isMasterDataOpen = !isMasterDataOpen" class="w-full flex items-center justify-between px-4 py-2.5 text-blue-100 hover:bg-blue-700 hover:text-white transition-colors duration-150 rounded-md">
                        <div class="flex items-center">
                            <i class="fas fa-database w-6"></i>
                            <span class="mx-4 font-medium">Master Data</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isMasterDataOpen, 'fa-chevron-right': !isMasterDataOpen }"></i>
                    </button> -->

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <!-- <div x-show="isMasterDataOpen" x-transition class="mt-1 ml-4 space-y-1 border-l-2 border-blue-400 pl-2">
                        <?php
                        if ($role_user == 'superadmin') {
                        ?>
                            <a href="sekretaris?page=master/manajemen_admin" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'master/manajemen_admin' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                                Manajemen Staf
                            </a>
                        <?php
                        }
                        ?>
                        <a href="sekretaris?page=master/manajemen_peserta" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'master/manajemen_peserta' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Peserta Hadir
                        </a>
                        <a href="sekretaris?page=master/manajemen_izin" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'master/manajemen_izin' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Peserta Izin
                        </a>
                        <a href="sekretaris?page=master/rekap_pendaftar" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'master/rekap_pendaftar' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Rekap Pendaftar
                        </a>
                    </div>
                </div> -->

                <!-- Menu Toggle untuk Keuangan -->
                <!-- <div>
                    <button @click="isKeuanganOpen = !isKeuanganOpen" class="w-full flex items-center justify-between px-4 py-2.5 text-blue-100 hover:bg-blue-700 hover:text-white transition-colors duration-150 rounded-md">
                        <div class="flex items-center">
                            <i class="fa fa-usd w-6"></i>
                            <span class="mx-4 font-medium">Keuangan</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isKeuanganOpen, 'fa-chevron-right': !isKeuanganOpen }"></i>
                    </button> -->

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <!-- <div x-show="isKeuanganOpen" x-transition class="mt-1 ml-4 space-y-1 border-l-2 border-blue-400 pl-2">
                        <a href="sekretaris?page=keuangan/log_keuangan" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'keuangan/log_keuangan' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Log Keuangan
                        </a>
                        <a href="sekretaris?page=keuangan/validasi_pembayaran" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'keuangan/validasi_pembayaran' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Validasi Pembayaran
                        </a>
                    </div>
                </div> -->

                <!-- Menu Toggle untuk Administrasi -->
                <div>
                    <button @click="isAdministrasiOpen = !isAdministrasiOpen" class="w-full flex items-center justify-between px-4 py-2.5 text-blue-100 hover:bg-blue-700 hover:text-white transition-colors duration-150 rounded-md">
                        <div class="flex items-center">
                            <i class="fa fa-file-text w-6" aria-hidden="true"></i>
                            <span class="mx-4 font-medium">Administrasi</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isAdministrasiOpen, 'fa-chevron-right': !isAdministrasiOpen }"></i>
                    </button>

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <div x-show="isAdministrasiOpen" x-transition class="mt-1 ml-4 space-y-1 border-l-2 border-blue-400 pl-2">
                        <a href="sekretaris?page=administrasi/surat_perizinan" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'administrasi/surat_perizinan' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Surat Perizinan
                        </a>
                        <a href="sekretaris?page=administrasi/surat_undangan" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'administrasi/surat_undangan' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Undangan Pemateri
                        </a>
                        <a href="sekretaris?page=administrasi/sesi_penunggu" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'administrasi/sesi_penunggu' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">
                            Sesi Penunggu
                        </a>
                    </div>
                </div>

                <!-- Menu Peserta -->
                <!-- <div>
                    <button @click="isPesertaOpen = !isPesertaOpen" class="w-full flex items-center justify-between px-4 py-2.5 text-blue-100 hover:bg-blue-700 hover:text-white transition-colors duration-150 rounded-md">
                        <div class="flex items-center">
                            <i class="fa-solid fa-user w-6"></i>
                            <span class="mx-4 font-medium">Peserta</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isPesertaOpen, 'fa-chevron-right': !isPesertaOpen }"></i>
                    </button>
                    <div x-show="isPesertaOpen" x-transition class="mt-1 ml-4 space-y-1 border-l-2 border-blue-400 pl-2">
                        <a href="sekretaris?page=peserta/tambah_peserta" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'peserta/tambah_peserta' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">Tambah Peserta</a>
                        <a href="sekretaris?page=peserta/registrasi_ulang" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'peserta/registrasi_ulang' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">Registrasi Ulang</a>
                    </div>
                </div> -->

                <!-- Menu Presensi -->
                 <!-- <div>
                    <button @click="isPresensiOpen = !isPresensiOpen" class="w-full flex items-center justify-between px-4 py-2.5 text-blue-100 hover:bg-blue-700 hover:text-white transition-colors duration-150 rounded-md">
                        <div class="flex items-center">
                            <i class="fa-solid fa-clipboard-user w-6"></i>
                            <span class="mx-4 font-medium">Presensi</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isPresensiOpen, 'fa-chevron-right': !isPresensiOpen }"></i>
                    </button>
                    <div x-show="isPresensiOpen" x-transition class="mt-1 ml-4 space-y-1 border-l-2 border-blue-400 pl-2">
                        <a href="sekretaris?page=presensi/manajemen_sesi_presensi" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'presensi/manajemen_sesi_presensi' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">Manajemen Sesi</a>
                        <a href="sekretaris?page=presensi/scanner_kehadiran" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'presensi/scanner_kehadiran' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">Scanner Kehadiran</a>
                        <a href="sekretaris?page=presensi/log_kehadiran" class="block px-3 py-2 rounded-md text-sm transition-colors duration-150 <?php echo $current_page == 'presensi/log_kehadiran' ? 'bg-blue-800 text-white font-semibold' : 'text-blue-100 hover:bg-blue-700/70 hover:text-white'; ?>">Log Kehadiran</a>
                    </div>
                </div> -->

                <!-- Tambahkan menu lain di sini -->
            </nav>
        </aside>

        <!-- Konten Utama -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex no-print items-center justify-between px-6 py-4 bg-white border-b-4 border-blue-600 shadow-sm">
                <button @click="sidebarOpen = true" class="text-gray-600 hover:text-blue-600 focus:outline-none md:hidden"><i class="fas fa-bars text-2xl"></i></button>
                <div class="flex-1"></div>
                <div x-data="{ dropdownOpen: false }" class="relative"><button @click="dropdownOpen = !dropdownOpen" class="relative z-10 block"><span class="font-medium text-gray-700">Halo, <?php echo htmlspecialchars($nama_user); ?>!</span><i class="fas fa-chevron-down text-xs ml-1"></i></button>
                    <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 z-20 w-48 py-2 mt-2 bg-white rounded-md shadow-xl" x-transition><a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-600 hover:text-white transition-colors">Logout</a></div>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
                <?php echo $content; ?>
            </main>
        </div>
    </div>

    <!-- HTML untuk Animasi Loading -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner-container">
            <div class="spinner"></div>
            <!-- Ganti src dengan path ke logo Anda -->
            <!-- <img src="../../assets/images/Logo 1x1.png" alt="Logo" class="spinner-logo"> -->
        </div>
        <p class="loading-text">Memproses...</p>
    </div>

    <!-- JavaScript untuk Mengontrol Loading -->
    <script>
        const loadingOverlay = document.getElementById('loading-overlay');
        let loadingStartTime = 0; // Variabel untuk menyimpan waktu mulai

        function showLoading() {
            if (loadingOverlay) {
                loadingStartTime = Date.now(); // Catat waktu saat loading dimulai
                loadingOverlay.classList.add('show');
            }
        }

        function hideLoading() {
            if (loadingOverlay) {
                const elapsedTime = Date.now() - loadingStartTime;
                const minimumVisibleTime = 1000; // 1 detik dalam milidetik

                if (elapsedTime < minimumVisibleTime) {
                    // Jika proses terlalu cepat, tunggu sisa waktunya
                    setTimeout(() => {
                        loadingOverlay.classList.remove('show');
                    }, minimumVisibleTime - elapsedTime);
                } else {
                    // Jika proses sudah cukup lama, langsung sembunyikan
                    loadingOverlay.classList.remove('show');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Tampilkan loading saat link navigasi diklik
            const navLinks = document.querySelectorAll('nav a');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = link.getAttribute('href');
                    if (link.target === '_blank' || !href || href.startsWith('#')) return;
                    showLoading();
                });
            });

            // Tampilkan loading saat form di dalam area konten utama disubmit
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.addEventListener('submit', function(e) {
                    if (e.target.tagName === 'FORM') {
                        showLoading();
                    }
                });
            }
        });

        // Sembunyikan loading saat halaman selesai dimuat
        window.addEventListener('pageshow', hideLoading);
    </script>

    <!-- Toast Notification (Flash Messages) -->
    <?php if (isset($_SESSION['success_msg'])): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="fixed bottom-5 right-5 z-[9999]">
        <div class="bg-green-600 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-4">
            <i class="fas fa-check-circle text-2xl"></i>
            <span class="font-semibold tracking-wide"><?php echo htmlspecialchars($_SESSION['success_msg']); ?></span>
            <button @click="show = false" class="ml-4 text-green-200 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php unset($_SESSION['success_msg']); endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="fixed bottom-5 right-5 z-[9999]">
        <div class="bg-blue-600 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-4">
            <i class="fas fa-exclamation-circle text-2xl"></i>
            <span class="font-semibold tracking-wide"><?php echo htmlspecialchars($_SESSION['error_msg']); ?></span>
            <button @click="show = false" class="ml-4 text-red-200 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php unset($_SESSION['error_msg']); endif; ?>

</body>

</html>



