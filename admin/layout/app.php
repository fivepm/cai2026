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
    <title><?php echo ucfirst(str_replace(['_', '/'], ' ', $current_page)); ?> - CAI 2025</title>
    <link rel="icon" type="image/png" href="../uploads/Logo 1x1.png">
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
            border-top-color: #ef4444;
            /* Warna merah tema Anda */
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
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed no-print inset-y-0 left-0 z-30 w-64 px-4 py-2 overflow-y-auto bg-red-900 text-white transition-transform duration-300 md:relative md:translate-x-0">
            <div class="flex items-center justify-center">
                <img src="../uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-10 w-auto">
                <!-- <h2 class="text-2xl font-bold text-yellow-300">Event CAI</h2><button @click="sidebarOpen = false" class="md:hidden"><i class="fas fa-times text-xl"></i></button> -->
            </div>
            <div class="flex items-center justify-center">
                <!-- <img src="../uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto"> -->
                <h2 class="text-2xl font-bold text-yellow-300 text-center">CAI XLIV <br> 2025</h2><button @click="sidebarOpen = false" class="md:hidden"><i class="fas fa-times text-xl"></i></button>
            </div>

            <!-- Navigasi dengan Alpine.js untuk toggle -->
            <nav class="mt-10"
                x-data="{ 
                isMasterDataOpen: <?php echo $isMasterDataPage ? 'true' : 'false'; ?>,
                isKeuanganOpen: <?php echo $isKeuanganPage ? 'true' : 'false'; ?>, 
                isAdministrasiOpen: <?php echo $isAdministrasiPage ? 'true' : 'false'; ?>,
                isPresensiOpen: <?php echo $isPresensiPage ? 'true' : 'false'; ?>,
                isPesertaOpen: <?php echo $isPesertaPage ? 'true' : 'false'; ?>,
                }">
                <a href="admin?page=dashboard" class="flex items-center px-4 py-2 mt-5 rounded-md <?php echo $current_page == 'dashboard' ? 'bg-red-800 text-yellow-300' : 'text-gray-300 hover:bg-red-800 hover:text-yellow-300'; ?>">
                    <i class="fas fa-tachometer-alt w-6"></i><span class="mx-4 font-medium">Dashboard</span>
                </a>

                <!-- Menu Toggle untuk Master Data -->
                <div>
                    <button @click="isMasterDataOpen = !isMasterDataOpen" class="w-full flex items-center justify-between px-4 py-2 mt-5 text-gray-300 hover:bg-red-800 hover:text-yellow-300 rounded-md">
                        <div class="flex items-center">
                            <i class="fas fa-database w-6"></i>
                            <span class="mx-4 font-medium">Master Data</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isMasterDataOpen, 'fa-chevron-right': !isMasterDataOpen }"></i>
                    </button>

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <div x-show="isMasterDataOpen" x-transition class="mt-2 ml-4 space-y-2 border-l-2 border-yellow-500">
                        <?php
                        if ($role_user == 'superadmin') {
                        ?>
                            <a href="admin?page=master/manajemen_admin" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'master/manajemen_admin' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                                Manajemen Staf
                            </a>
                        <?php
                        }
                        ?>
                        <a href="admin?page=master/manajemen_peserta" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'master/manajemen_peserta' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Peserta Hadir
                        </a>
                        <a href="admin?page=master/manajemen_izin" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'master/manajemen_izin' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Peserta Izin
                        </a>
                        <a href="admin?page=master/rekap_pendaftar" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'master/rekap_pendaftar' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Rekap Pendaftar
                        </a>
                    </div>
                </div>

                <!-- Menu Toggle untuk Keuangan -->
                <div>
                    <button @click="isKeuanganOpen = !isKeuanganOpen" class="w-full flex items-center justify-between px-4 py-2 mt-5 text-gray-300 hover:bg-red-800 hover:text-yellow-300 rounded-md">
                        <div class="flex items-center">
                            <i class="fa fa-usd w-6"></i>
                            <span class="mx-4 font-medium">Keuangan</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isKeuanganOpen, 'fa-chevron-right': !isKeuanganOpen }"></i>
                    </button>

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <div x-show="isKeuanganOpen" x-transition class="mt-2 ml-4 space-y-2 border-l-2 border-yellow-500">
                        <a href="admin?page=keuangan/log_keuangan" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'keuangan/log_keuangan' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Log Keuangan
                        </a>
                        <a href="admin?page=keuangan/validasi_pembayaran" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'keuangan/validasi_pembayaran' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Validasi Pembayaran
                        </a>
                    </div>
                </div>

                <!-- Menu Toggle untuk Perizinan -->
                <div>
                    <button @click="isAdministrasiOpen = !isAdministrasiOpen" class="w-full flex items-center justify-between px-4 py-2 mt-5 text-gray-300 hover:bg-red-800 hover:text-yellow-300 rounded-md">
                        <div class="flex items-center">
                            <i class="fa fa-file-text w-6" aria-hidden="true"></i>
                            <span class="mx-4 font-medium">Administrasi</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isAdministrasiOpen, 'fa-chevron-right': !isAdministrasiOpen }"></i>
                    </button>

                    <!-- Sub-menu yang bisa disembunyikan -->
                    <div x-show="isAdministrasiOpen" x-transition class="mt-2 ml-4 space-y-2 border-l-2 border-yellow-500">
                        <a href="admin?page=administrasi/surat_perizinan" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'administrasi/surat_perizinan' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Surat Perizinan
                        </a>
                        <a href="admin?page=administrasi/surat_undangan" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'administrasi/surat_undangan' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Undangan Pemateri
                        </a>
                        <a href="admin?page=administrasi/sesi_penunggu" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'administrasi/sesi_penunggu' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">
                            Sesi Penunggu
                        </a>
                    </div>
                </div>

                <!-- Menu Peserta -->
                <div>
                    <button @click="isPesertaOpen = !isPesertaOpen" class="w-full flex items-center justify-between px-4 py-2 mt-5 text-gray-300 hover:bg-red-800 hover:text-yellow-300 rounded-md">
                        <div class="flex items-center">
                            <i class="fa-solid fa-user w-6"></i>
                            <span class="mx-4 font-medium">Peserta</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isPesertaOpen, 'fa-chevron-right': !isPesertaOpen }"></i>
                    </button>
                    <div x-show="isPesertaOpen" x-transition class="mt-2 ml-4 space-y-2 border-l-2 border-yellow-500">
                        <a href="admin?page=peserta/tambah_peserta" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'peserta/tambah_peserta' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">Tambah Peserta</a>
                        <a href="admin?page=peserta/registrasi_ulang" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'peserta/registrasi_ulang' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">Registrasi Ulang</a>
                    </div>
                </div>

                <!-- Menu Presensi -->
                <div>
                    <button @click="isPresensiOpen = !isPresensiOpen" class="w-full flex items-center justify-between px-4 py-2 mt-5 text-gray-300 hover:bg-red-800 hover:text-yellow-300 rounded-md">
                        <div class="flex items-center">
                            <i class="fa-solid fa-clipboard-user w-6"></i>
                            <span class="mx-4 font-medium">Presensi</span>
                        </div>
                        <i class="fas transition-transform duration-200" :class="{ 'fa-chevron-down': isPresensiOpen, 'fa-chevron-right': !isPresensiOpen }"></i>
                    </button>
                    <div x-show="isPresensiOpen" x-transition class="mt-2 ml-4 space-y-2 border-l-2 border-yellow-500">
                        <a href="admin?page=presensi/manajemen_sesi_presensi" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'presensi/manajemen_sesi_presensi' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">Manajemen Sesi</a>
                        <a href="admin?page=presensi/scanner_kehadiran" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'presensi/scanner_kehadiran' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">Scanner Kehadiran</a>
                        <a href="admin?page=presensi/log_kehadiran" class="block px-4 py-2 rounded-md text-sm <?php echo $current_page == 'presensi/log_kehadiran' ? 'text-yellow-300 font-semibold' : 'text-gray-300 hover:text-yellow-300'; ?>">Log Kehadiran</a>
                    </div>
                </div>

                <!-- Tambahkan menu lain di sini -->
            </nav>
        </aside>

        <!-- Konten Utama -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="flex no-print items-center justify-between px-6 py-4 bg-white border-b-4 border-red-600">
                <button @click="sidebarOpen = true" class="text-gray-500 focus:outline-none md:hidden"><i class="fas fa-bars text-2xl"></i></button>
                <div class="flex-1"></div>
                <div x-data="{ dropdownOpen: false }" class="relative"><button @click="dropdownOpen = !dropdownOpen" class="relative z-10 block"><span class="font-medium text-gray-700">Halo, <?php echo htmlspecialchars($nama_user); ?>!</span><i class="fas fa-chevron-down text-xs ml-1"></i></button>
                    <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 z-20 w-48 py-2 mt-2 bg-white rounded-md shadow-xl" x-transition><a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-500 hover:text-white">Logout</a></div>
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
            <!-- <img src="../uploads/Logo 1x1.png" alt="Logo" class="spinner-logo"> -->
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

</body>

</html>