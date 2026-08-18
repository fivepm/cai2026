<?php
// Tampilkan semua error PHP untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';

// Bagian ini hanya akan berjalan jika ada permintaan POST dari JavaScript
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $barcode = $data['barcode'] ?? '';

    $response = ['status' => 'error', 'message' => 'Data tidak valid.'];

    if (!empty($barcode)) {
        if ($conn->connect_error) {
            $response['message'] = 'Koneksi database gagal: ' . $conn->connect_error;
        } else {
            $stmt = $conn->prepare("SELECT nama, kelompok FROM peserta WHERE barcode = ?");
            $stmt->bind_param("s", $barcode);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $peserta = $result->fetch_assoc();
                $response = ['status' => 'success', 'nama' => $peserta['nama'], 'kelompok' => $peserta['kelompok']];
            } else {
                $response['message'] = 'QR Code tidak terdaftar di database peserta hadir.';
            }
            $stmt->close();
            $conn->close();
        }
    }

    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Presensi</title>
    <link rel="icon" type="image/png" href="uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body class="bg-gray-200">

    <div class="container mx-auto max-w-lg p-4">
        <img src="uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
        <h1 class="text-3xl font-bold text-center text-gray-800 my-4">Scanner Kehadiran Peserta</h1>

        <!-- Tampilan Pilihan Awal -->
        <div id="selection-container" class="space-y-4 mt-8">
            <button id="start-scan-button" class="w-full px-6 py-4 bg-red-600 text-white font-bold rounded-lg shadow-md hover:bg-red-700 flex items-center justify-center text-lg">
                <i class="fas fa-camera mr-3"></i>Pindai dengan Kamera
            </button>
            <input type="file" id="qr-input-file" accept="image/*" class="hidden">
            <button type="button" id="scan-file-button" class="w-full px-6 py-4 bg-gray-600 text-white font-bold rounded-lg shadow-md hover:bg-gray-700 flex items-center justify-center text-lg">
                <i class="fas fa-image mr-3"></i> Pilih dari Galeri
            </button>
        </div>

        <!-- Kontainer untuk scanner kamera -->
        <div id="scanner-view" class="w-full rounded-lg overflow-hidden shadow-lg" style="display: none;">
            <div id="reader"></div>
        </div>

        <div id="result-container" class="mt-6">
            <!-- Hasil pindaian akan ditampilkan di sini -->
        </div>

        <div id="rescan-button-container" class="text-center mt-4" style="display: none;">
            <button id="rescan-button" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg shadow-md hover:bg-blue-700">
                <i class="fas fa-sync-alt mr-2"></i>Scan Ulang
            </button>
        </div>
    </div>

    <script>
        const resultContainer = document.getElementById('result-container');
        const startButton = document.getElementById('start-scan-button');
        const selectionContainer = document.getElementById('selection-container');
        const rescanButton = document.getElementById('rescan-button');
        const rescanButtonContainer = document.getElementById('rescan-button-container');
        const scannerView = document.getElementById('scanner-view');
        const scanFileButton = document.getElementById('scan-file-button');
        const qrInputFile = document.getElementById('qr-input-file');
        const html5QrcodeScanner = new Html5Qrcode("reader");

        function onScanSuccess(decodedText, decodedResult) {
            if (html5QrcodeScanner.isScanning) {
                html5QrcodeScanner.pause();
            }
            scannerView.style.display = 'none';
            resultContainer.innerHTML = `<div class="p-4 bg-gray-100 rounded-lg text-center font-semibold animate-pulse">Memeriksa data...</div>`;

            fetch('scanner_qrcode.php', { // PERBAIKAN DI SINI
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        barcode: decodedText
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(text || `HTTP error! status: ${response.status}`)
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    let resultHtml = '';
                    if (data.status === 'success') {
                        resultHtml = `
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md">
                            <div class="flex items-center">
                                <div class="text-3xl mr-4"><i class="fas fa-check-circle"></i></div>
                                <div>
                                    <p class="font-bold text-xl">${data.nama}</p>
                                    <p class="text-md">${data.kelompok}</p>
                                </div>
                            </div>
                        </div>`;
                    } else {
                        resultHtml = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                            <div class="flex items-center">
                                <div class="text-3xl mr-4"><i class="fas fa-times-circle"></i></div>
                                <div>
                                    <p class="font-bold text-xl">QR Code Tidak Ditemukan</p>
                                    <p class="text-md">${data.message}</p>
                                </div>
                            </div>
                        </div>`;
                    }
                    resultContainer.innerHTML = resultHtml;
                    rescanButtonContainer.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultContainer.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                        <p class="font-bold">Terjadi Kesalahan Server</p>
                        <p class="text-xs mt-2 bg-red-200 p-2 rounded"><code class="break-all">${error.message}</code></p>
                    </div>`;
                    rescanButtonContainer.style.display = 'block';
                });
        }

        startButton.addEventListener('click', () => {
            selectionContainer.style.display = 'none';
            scannerView.style.display = 'block';
            resultContainer.innerHTML = '';
            const config = {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                }
            };
            html5QrcodeScanner.start({
                    facingMode: "environment"
                }, config, onScanSuccess)
                .catch(err => {
                    resultContainer.innerHTML = `<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md"><p class="font-bold">Kamera Gagal Diakses.</p><p>Pastikan Anda menggunakan HTTPS dan telah memberikan izin.</p></div>`;
                    selectionContainer.style.display = 'block';
                    scannerView.style.display = 'none';
                });
        });

        rescanButton.addEventListener('click', () => {
            resultContainer.innerHTML = '';
            rescanButtonContainer.style.display = 'none';
            selectionContainer.style.display = 'block';
            if (html5QrcodeScanner.isScanning) {
                html5QrcodeScanner.stop();
            }
        });

        scanFileButton.addEventListener('click', () => {
            qrInputFile.click();
        });

        qrInputFile.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) {
                return;
            }

            selectionContainer.style.display = 'none';
            resultContainer.innerHTML = `<div class="p-4 bg-gray-100 rounded-lg text-center font-semibold animate-pulse">Memindai gambar...</div>`;

            html5QrcodeScanner.scanFile(file, true)
                .then(onScanSuccess)
                .catch(err => {
                    resultContainer.innerHTML = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                            <p class="font-bold">Gagal Memindai</p>
                            <p>Pastikan gambar jelas dan berisi QR Code.</p>
                        </div>`;
                    rescanButtonContainer.style.display = 'block';
                });
        });
    </script>
</body>

</html>