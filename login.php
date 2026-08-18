<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Event CAI 2025</title>
    <link rel="icon" type="image/png" href="uploads/Logo 1x1.png">

    <!-- 1. Tailwind CSS untuk styling -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- 2. Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- 3. Pustaka untuk Scan Barcode -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Style tambahan untuk memastikan video scanner tidak terlalu besar di desktop */
        #reader {
            max-width: 500px;
            margin: 20px auto;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }

        .message-box {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .message-error {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans">

    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg">

        <!-- Kontainer untuk Form Login -->
        <div id="loginContainer">
            <img src="uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
            <h2 class="text-3xl font-bold text-center text-gray-800">Selamat Datang</h2>
            <p class="text-center text-gray-500">Silakan masuk untuk melanjutkan</p>

            <?php
            session_start();
            if (isset($_SESSION['login_message'])) {
                $message = $_SESSION['login_message'];
                echo '<div class="message-box message-' . htmlspecialchars($message['type']) . '">' . htmlspecialchars($message['text']) . '</div>';
                unset($_SESSION['login_message']);
            }
            ?>

            <!-- Form Login -->
            <form id="loginForm" class="mt-8 space-y-6" action="proses_login.php" method="POST">
                <!-- Input Username -->
                <div>
                    <label for="username" class="text-sm font-medium text-gray-700">Username</label>
                    <input id="username" name="username" type="text" required
                        class="w-full px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Masukkan username Anda">
                </div>

                <!-- Input Password -->
                <div>
                    <label for="password" class="text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" required
                        class="w-full px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Masukkan password Anda">
                </div>

                <!-- Grup Tombol -->
                <div class="flex items-center justify-center space-x-4 pt-4">
                    <!-- Tombol Login -->
                    <button type="submit"
                        class="flex-1 inline-flex items-center justify-center px-4 py-3 text-base font-semibold text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login
                    </button>
                    <!-- Tombol Scan Barcode -->
                    <button type="button" id="scanButton"
                        class="flex-1 inline-flex items-center justify-center px-4 py-3 text-base font-semibold text-gray-700 bg-gray-200 border border-transparent rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-all">
                        <i class="fas fa-barcode mr-2"></i> Access Card
                    </button>
                </div>
            </form>
        </div>

        <!-- Kontainer untuk Scanner Barcode (tersembunyi) -->
        <div id="scannerContainer" class="hidden text-center">
            <h3 class="text-2xl font-bold text-gray-800">Scan Barcode Anda</h3>
            <p class="text-gray-500">Arahkan kamera atau pilih dari galeri</p>
            <div id="reader" class="border-2 border-gray-200"></div>

            <!-- Pemisah -->
            <div class="my-4 flex items-center"><span class="flex-grow bg-gray-300 h-px"></span><span class="mx-4 text-gray-500 font-medium">ATAU</span><span class="flex-grow bg-gray-300 h-px"></span></div>

            <!-- Tombol untuk scan dari file -->
            <div>
                <input type="file" id="qr-input-file" accept="image/*" class="hidden">
                <button type="button" id="scanFileButton" class="w-full inline-flex items-center justify-center px-4 py-2 text-base font-semibold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-image mr-2"></i> Pilih Gambar dari Galeri
                </button>
            </div>

            <button id="closeScannerButton" class="w-full mt-6 px-4 py-2 text-base font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none">Batal</button>
        </div>

        <!-- Area untuk menampilkan pesan error/sukses -->
        <div id="messageArea" class="mt-4 text-center text-sm font-medium"></div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginContainer = document.getElementById('loginContainer');
            const scannerContainer = document.getElementById('scannerContainer');
            const scanButton = document.getElementById('scanButton');
            const closeScannerButton = document.getElementById('closeScannerButton');
            const messageArea = document.getElementById('messageArea');
            const qrInputFile = document.getElementById('qr-input-file');
            const scanFileButton = document.getElementById('scanFileButton');

            let html5QrCodeScanner = null;

            function showMessage(message, isError = false) {
                messageArea.textContent = message;
                messageArea.className = 'mt-4 text-center text-sm font-medium ' + (isError ? 'text-red-600' : 'text-green-600');
            }

            const onScanSuccess = (decodedText, decodedResult) => {
                showMessage(`Scan Berhasil! Mengalihkan...`, false);
                stopScanner();
                window.location.href = `proses_login.php?barcode=${encodeURIComponent(decodedText)}`;
            };

            function startScanner() {
                if (!html5QrCodeScanner) {
                    html5QrCodeScanner = new Html5Qrcode("reader");
                }
                loginContainer.classList.add('hidden');
                scannerContainer.classList.remove('hidden');
                showMessage('Membuka kamera...', false);
                const config = {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                };
                html5QrCodeScanner.start({
                        facingMode: "environment"
                    }, config, onScanSuccess)
                    .catch(err => {
                        showMessage('Error: Tidak dapat mengakses kamera.', true);
                        // closeScanner();
                    });
            }

            // **PERBAIKAN 1: Fungsi stopScanner dibuat lebih andal**
            function stopScanner() {
                if (html5QrCodeScanner && html5QrCodeScanner.isScanning) {
                    return html5QrCodeScanner.stop();
                }
                return Promise.resolve(); // Kembalikan promise kosong jika tidak sedang memindai
            }

            function closeScanner() {
                stopScanner().then(() => {
                    scannerContainer.classList.add('hidden');
                    loginContainer.classList.remove('hidden');
                    showMessage('');
                }).catch(err => console.error("Gagal menutup scanner.", err));
            }

            scanButton.addEventListener('click', startScanner);
            closeScannerButton.addEventListener('click', closeScanner);

            scanFileButton.addEventListener('click', () => {
                qrInputFile.click();
            });

            qrInputFile.addEventListener('change', e => {
                const file = e.target.files[0];
                if (!file) {
                    return;
                }

                if (!html5QrCodeScanner) {
                    html5QrCodeScanner = new Html5Qrcode("reader");
                }

                // **PERBAIKAN 2: Hentikan kamera (jika berjalan) sebelum memindai file**
                showMessage('Menghentikan kamera (jika aktif)...', false);
                stopScanner().then(() => {
                    showMessage('Memindai gambar...', false);
                    html5QrCodeScanner.scanFile(file, true)
                        .then(onScanSuccess)
                        .catch(err => {
                            showMessage('Gagal memindai. Pastikan gambar jelas dan berisi QR Code.', true);
                            console.error(`Error scanning file. Reason: ${err}`);
                        });
                }).catch(err => {
                    showMessage('Error saat mencoba memindai file.', true);
                    console.error("Gagal menghentikan kamera sebelum scan file.", err);
                });
            });

            // Menangani submit form login standar
            loginForm.addEventListener('submit', function(e) {
                // e.preventDefault(); // Hapus komentar ini jika ingin menangani login via JavaScript (AJAX)
                showMessage('Mencoba login...', false);
                // Form akan di-submit ke action="proses_login.php" secara normal
            });
        });
    </script>
</body>

</html>