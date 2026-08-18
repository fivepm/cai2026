<?php
// (File: pages/presensi/scanner_kehadiran.php - Versi Final yang Diperbarui)

// Bagian ini hanya akan berjalan jika tidak ada parameter ?api=true
$sesi_list = [];
$result_sesi_list = $conn->query("SELECT id, nama_sesi FROM sesi_presensi ORDER BY nama_sesi");
if ($result_sesi_list) {
    while ($row = $result_sesi_list->fetch_assoc()) {
        $sesi_list[] = $row;
    }
}
?>

<script>
    function scannerKehadiranData() {
        return {
            sesiTerpilih: '',
            sesiAktif: false,
            namaSesiTerpilih: '',
            scanResult: {
                status: '',
                message: ''
            },
            html5QrCode: null,

            mulaiSesi() {
                if (!this.sesiTerpilih) return;
                const selectedOption = document.querySelector(`#sesi_id option[value='${this.sesiTerpilih}']`);
                this.namaSesiTerpilih = selectedOption.textContent;
                this.sesiAktif = true;
                this.scanResult = {
                    status: '',
                    message: ''
                };
                this.$nextTick(() => {
                    this.startScanner();
                });
            },

            gantiSesi() {
                this.stopScanner();
                this.sesiAktif = false;
                this.sesiTerpilih = '';
                this.scanResult = {
                    status: '',
                    message: ''
                };
            },

            startScanner() {
                if (!this.html5QrCode) this.html5QrCode = new Html5Qrcode("reader");
                const config = {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                };
                this.html5QrCode.start({
                            facingMode: "environment"
                        }, config,
                        (decodedText, decodedResult) => {
                            this.html5QrCode.pause();
                            this.handleScan(decodedText);
                        },
                        (errorMessage) => {})
                    .catch((err) => {
                        this.scanResult = {
                            status: 'error',
                            message: 'Gagal memulai kamera. Pastikan Anda memberikan izin dan menggunakan HTTPS.'
                        };
                    });
            },

            stopScanner() {
                if (this.html5QrCode && this.html5QrCode.isScanning) {
                    this.html5QrCode.stop().catch(err => console.error("Gagal menghentikan scanner.", err));
                }
            },

            handleScan(barcode) {
                this.scanResult = {
                    status: 'info',
                    message: 'Memproses...'
                };

                // Mengirim data ke file API khusus
                fetch('pages/presensi/api_presensi.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            barcode: barcode,
                            sesi_id: this.sesiTerpilih
                        })
                    })
                    .then(response => {
                        if (!response.ok) return response.json().then(err => {
                            throw new Error(err.message || 'Error tidak diketahui');
                        });
                        return response.json();
                    })
                    .then(data => {
                        this.scanResult = data;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.scanResult = {
                            status: 'error',
                            message: `Terjadi masalah. Error: ${error.message}`
                        };
                    })
                    .finally(() => {
                        setTimeout(() => {
                            if (this.html5QrCode && this.sesiAktif) this.html5QrCode.resume();
                        }, 2000);
                    });
            }
        };
    }
</script>

<div class="p-6 bg-white rounded-lg shadow-md" x-data="scannerKehadiranData()">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Scanner Kehadiran</h1>

    <!-- Tampilan Awal: Pilih Sesi -->
    <div x-show="!sesiAktif" x-transition>
        <div class="max-w-md mx-auto">
            <label for="sesi_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Sesi Presensi:</label>
            <select x-model="sesiTerpilih" id="sesi_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500">
                <option value="">-- Pilih Sesi --</option>
                <?php foreach ($sesi_list as $sesi_item): ?>
                    <option value="<?php echo $sesi_item['id']; ?>"><?php echo htmlspecialchars($sesi_item['nama_sesi']); ?></option>
                <?php endforeach; ?>
            </select>
            <button @click="mulaiSesi" :disabled="!sesiTerpilih" class="mt-4 w-full px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                Mulai Presensi
            </button>
        </div>
    </div>

    <!-- Tampilan Scanner -->
    <div x-show="sesiAktif" x-transition x-cloak>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-700">Sesi: <span x-text="namaSesiTerpilih" class="text-red-600"></span></h2>
            <button @click="gantiSesi" class="px-3 py-1 text-sm font-semibold text-white bg-gray-600 rounded-md hover:bg-gray-700">Ganti Sesi</button>
        </div>

        <div id="scanner-container" class="w-full max-w-lg mx-auto border-4 border-gray-300 rounded-lg overflow-hidden">
            <div id="reader" class="w-full"></div>
        </div>

        <!-- Area Hasil Scan -->
        <div id="result-container" class="mt-6 text-center">
            <div x-show="scanResult.message" :class="{ 'bg-green-100 text-green-800 border-green-500': scanResult.status === 'success', 'bg-red-100 text-red-800 border-red-500': scanResult.status === 'error' }" class="p-4 border-l-4 rounded-md shadow-sm" x-text="scanResult.message"></div>
        </div>
    </div>
</div>