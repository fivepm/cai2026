<?php
session_start();
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CAI 2026</title>
    <link rel="icon" type="image/png" href="assets/images/Logo 1x1.png">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="apple-touch-icon" href="assets/images/Logo%201x1.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- 1. Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- 2. Tailwind CSS untuk styling -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- 3. Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- 4. Pustaka untuk Scan Barcode / QR Code -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        #reader {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
        }

        #reader video {
            border-radius: 0.5rem;
            object-fit: cover;
        }

        .pulse-animation {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Splash Screen Styles */
        #splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #ffffff;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s ease-out, visibility 0.6s ease-out;
        }

        #splash-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .splash-logos {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 2rem;
            animation: slideUp 0.8s ease-out;
        }

        .splash-logos img {
            height: 90px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }

        .splash-text {
            text-align: center;
            animation: fadeIn 1.2s ease-out;
        }

        .splash-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .splash-text p {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .loader {
            margin-top: 2rem;
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <!-- Splash Screen -->
    <div id="splash-screen">
        <div class="splash-logos">
            <img src="assets/images/Logo 1x1.png" alt="Logo CAI">
            <img src="assets/images/logo_kmm.png" alt="Logo KMM">
        </div>
        <div class="splash-text">
            <h2>CAI 2026</h2>
            <p>Memuat Aplikasi...</p>
        </div>
        <div class="loader"></div>
    </div>

    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200/80 border-t-[6px] border-t-blue-600 overflow-hidden transition-all duration-300 p-6 sm:p-8 relative">

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="flex justify-center items-center gap-4 mb-3">
                <img src="assets/images/Logo 1x1.png" alt="Logo CAI" class="h-20 w-auto object-contain drop-shadow-sm">
                <img src="assets/images/logo_kmm.png" alt="Logo KMM" class="h-20 w-auto object-contain drop-shadow-sm">
            </div>
            <hr class="mb-3">
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Login</h1>
            <p class="text-sm text-slate-500 mt-1">CAI Banguntapan 1 - Tahun 2026</p>
            
            <?php if (isset($_ENV['APP_ENV']) && strtolower($_ENV['APP_ENV']) === 'local'): ?>
            <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-800 text-xs font-semibold rounded-full border border-amber-200">
                <i class="fas fa-code"></i> Mode Lokal
            </div>
            <?php endif; ?>
        </div>

        <!-- Pesan Error / Notifikasi Session dari Server -->
        <?php if (isset($_SESSION['login_message'])): ?>
            <?php
            $msg = $_SESSION['login_message'];
            $isError = ($msg['type'] ?? 'error') === 'error';
            unset($_SESSION['login_message']);
            ?>
            <div class="mb-5 p-3.5 rounded-xl text-sm font-medium flex items-start space-x-3 <?php echo $isError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'; ?>">
                <i class="fas <?php echo $isError ? 'fa-circle-exclamation' : 'fa-circle-check'; ?> text-lg mt-0.5 flex-shrink-0"></i>
                <div class="flex-1"><?php echo htmlspecialchars($msg['text']); ?></div>
            </div>
        <?php endif; ?>

        <!-- Tampilan Pilihan Metode Scan -->
        <div id="selectionContainer" class="space-y-4">
            <div class="text-center mb-4">
                <p class="text-sm font-medium text-slate-600">Pilih cara pemindaian Kartu Akses Anda:</p>
            </div>

            <!-- Tombol 1: Pindai dengan Kamera -->
            <button type="button" id="startScannerBtn"
                class="w-full group flex items-center p-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center text-xl mr-4 flex-shrink-0 group-hover:scale-105 transition-transform">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="text-left flex-1">
                    <div class="font-semibold text-base">Pindai dengan Kamera</div>
                    <div class="text-xs text-blue-100 mt-0.5">Scan langsung menggunakan kamera perangkat</div>
                </div>
                <i class="fas fa-chevron-right text-blue-200 text-sm ml-2 group-hover:translate-x-1 transition-transform"></i>
            </button>

            <!-- Tombol 2: Pilih dari Galeri -->
            <input type="file" id="qr-input-file" accept="image/*" class="hidden">
            <button type="button" id="scanFileBtn"
                class="w-full group flex items-center p-4 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl shadow-sm hover:shadow transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                <div class="w-12 h-12 rounded-lg bg-slate-200 flex items-center justify-center text-xl text-slate-600 mr-4 flex-shrink-0 group-hover:scale-105 transition-transform">
                    <i class="fas fa-image"></i>
                </div>
                <div class="text-left flex-1">
                    <div class="font-semibold text-base">Pilih dari Galeri</div>
                    <div class="text-xs text-slate-500 mt-0.5">Unggah foto atau screenshot QR Code</div>
                </div>
                <i class="fas fa-chevron-right text-slate-400 text-sm ml-2 group-hover:translate-x-1 transition-transform"></i>
            </button>
        </div>

        <!-- Tampilan Scanner Kamera (Hidden Default) -->
        <div id="scannerContainer" class="hidden text-center space-y-4">
            <div class="bg-blue-50 text-blue-800 text-xs font-medium px-3 py-2 rounded-lg flex items-center justify-center space-x-2">
                <i class="fas fa-info-circle"></i>
                <span>Arahkan kamera ke QR Code pada kartu akses Anda</span>
            </div>

            <!-- Viewfinder Reader -->
            <div id="reader" class="bg-black/5 min-h-[260px] flex items-center justify-center"></div>

            <!-- Tombol Tutup Scanner -->
            <button type="button" id="closeScannerBtn"
                class="w-full py-2.5 px-4 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold text-sm rounded-xl transition-all duration-200 flex items-center justify-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Pilihan</span>
            </button>
        </div>

        <!-- Status / Feedback Message Dinamis -->
        <div id="statusMessage" class="hidden mt-4 p-3 rounded-xl text-sm font-medium text-center"></div>

    </div>

    <script>
        // Service Worker Registration for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(error => {
                        console.log('ServiceWorker registration failed: ', error);
                    });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Hide Splash Screen after 1.5 seconds
            setTimeout(() => {
                const splashScreen = document.getElementById('splash-screen');
                if (splashScreen) {
                    splashScreen.classList.add('hidden');
                    // Remove from DOM after transition completes
                    setTimeout(() => splashScreen.remove(), 600);
                }
            }, 1500);

            const selectionContainer = document.getElementById('selectionContainer');
            const scannerContainer = document.getElementById('scannerContainer');
            const startScannerBtn = document.getElementById('startScannerBtn');
            const closeScannerBtn = document.getElementById('closeScannerBtn');
            const scanFileBtn = document.getElementById('scanFileBtn');
            const qrInputFile = document.getElementById('qr-input-file');
            const statusMessage = document.getElementById('statusMessage');

            let html5QrCodeScanner = null;

            function showStatus(text, type = 'info') {
                statusMessage.textContent = text;
                statusMessage.className = 'mt-4 p-3 rounded-xl text-sm font-medium text-center ';
                
                if (type === 'error') {
                    statusMessage.className += 'bg-red-50 text-red-700 border border-red-200';
                } else if (type === 'success') {
                    statusMessage.className += 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                } else {
                    statusMessage.className += 'bg-blue-50 text-blue-700 border border-blue-200 pulse-animation';
                }
                statusMessage.classList.remove('hidden');
            }

            function hideStatus() {
                statusMessage.textContent = '';
                statusMessage.classList.add('hidden');
            }

            // Callback ketika QR Code terdeteksi
            const onScanSuccess = (decodedText, decodedResult) => {
                showStatus('QR Code terdeteksi! Memverifikasi...', 'success');
                stopScanner().then(() => {
                    // Redirect ke proses_login.php
                    window.location.href = `proses_login.php?barcode=${encodeURIComponent(decodedText)}`;
                }).catch(() => {
                    window.location.href = `proses_login.php?barcode=${encodeURIComponent(decodedText)}`;
                });
            };

            function stopScanner() {
                if (html5QrCodeScanner && html5QrCodeScanner.isScanning) {
                    return html5QrCodeScanner.stop();
                }
                return Promise.resolve();
            }

            // Buka Scanner Kamera
            function openCameraScanner() {
                hideStatus();
                selectionContainer.classList.add('hidden');
                scannerContainer.classList.remove('hidden');

                if (!html5QrCodeScanner) {
                    html5QrCodeScanner = new Html5Qrcode("reader");
                }

                showStatus('Membuka kamera...', 'info');

                const config = {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                };

                html5QrCodeScanner.start(
                    { facingMode: "environment" },
                    config,
                    onScanSuccess
                ).then(() => {
                    hideStatus();
                }).catch(err => {
                    console.error("Gagal membuka kamera:", err);
                    showStatus('Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.', 'error');
                });
            }

            // Tutup Scanner Kamera dan Kembali
            function closeCameraScanner() {
                stopScanner().then(() => {
                    scannerContainer.classList.add('hidden');
                    selectionContainer.classList.remove('hidden');
                    hideStatus();
                }).catch(err => {
                    console.error("Error menghentikan kamera:", err);
                    scannerContainer.classList.add('hidden');
                    selectionContainer.classList.remove('hidden');
                    hideStatus();
                });
            }

            startScannerBtn.addEventListener('click', openCameraScanner);
            closeScannerBtn.addEventListener('click', closeCameraScanner);

            // Buka file dialog galeri
            scanFileBtn.addEventListener('click', () => {
                qrInputFile.value = ''; // Reset input agar event change terpanggil meski file sama
                qrInputFile.click();
            });

            // Handle file QR Code yang dipilih dari galeri
            qrInputFile.addEventListener('change', e => {
                const file = e.target.files[0];
                if (!file) return;

                if (!html5QrCodeScanner) {
                    html5QrCodeScanner = new Html5Qrcode("reader");
                }

                showStatus('Memindai gambar QR Code...', 'info');

                stopScanner().then(() => {
                    html5QrCodeScanner.scanFile(file, true)
                        .then(onScanSuccess)
                        .catch(err => {
                            console.error("Gagal scan gambar:", err);
                            showStatus('QR Code tidak terdeteksi pada gambar. Pastikan gambar jelas dan memiliki QR Code.', 'error');
                        });
                }).catch(err => {
                    console.error("Gagal stop scanner sebelum scan file:", err);
                    showStatus('Terjadi kesalahan saat memproses gambar.', 'error');
                });
            });
        });
    </script>
</body>

</html>