<?php
// (File: pages/peserta/registrasi_ulang.php)

// ===================================================================
// BAGIAN LOGIKA PHP
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $peserta_id = $_POST['peserta_id'];

    if ($_POST['action'] === 'update_registrasi') {
        $ambil_jersey  = isset($_POST['ambil_jersey'])  ? 'ya' : 'tidak';
        $ambil_idcard  = isset($_POST['ambil_idcard'])  ? 'ya' : 'tidak';

        $stmt_update = $conn->prepare("UPDATE peserta SET registrasi_ulang = 'ya', terima_jersey = ?, terima_idcard = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $ambil_jersey, $ambil_idcard, $peserta_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Registrasi Ulang berhasil.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memperbarui data registrasi.'];
        }
        $stmt_update->close();
    } elseif ($_POST['action'] === 'batalkan_registrasi') {
        $stmt_update = $conn->prepare("UPDATE peserta SET registrasi_ulang = 'tidak', terima_jersey = 'tidak', terima_idcard = 'tidak' WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Registrasi ulang berhasil dibatalkan.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal membatalkan registrasi.'];
        }
        $stmt_update->close();
    }

    header("Location: sekretaris?page=peserta/registrasi_ulang");
    exit();
}

// Logika Filter dan Pencarian
$search_query = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
$types  = '';

if (!empty($search_query)) {
    $where_clause  = "WHERE nama LIKE ? OR kelompok LIKE ?";
    $search_param  = '%' . $search_query . '%';
    $params        = [$search_param, $search_param];
    $types         = 'ss';
}

$peserta_list = [];
$sql  = "SELECT * FROM peserta $where_clause ORDER BY kelompok, nama ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $peserta_list[] = $row;
    }
}
$stmt->close();
?>

<div class="p-6 bg-white rounded-xl shadow-md"
    x-data="{
        /* ── QR Scanner Modal ── */
        isScannerOpen: false,
        scanResult: '',
        scanError: '',
        scanLoading: false,
        html5QrCode: null,
        isProcessingScan: false,

        openScanner() {
            this.isScannerOpen = true;
            this.scanResult = '';
            this.scanError = '';
            this.isProcessingScan = false;
            this.$nextTick(() => { this.startCamera(); });
        },
        closeScanner() {
            this.stopCamera();
            this.isScannerOpen = false;
        },
        startCamera() {
            this.html5QrCode = new Html5Qrcode('qr-reader');
            this.html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    this.findPesertaByBarcode(decodedText);
                },
                (errorMessage) => {
                    // Abaikan pesan peringatan yang terjadi selama pemindaian berkelanjutan
                }
            ).catch(() => {
                this.scanError = 'Kamera tidak dapat diakses. Pastikan izin kamera diberikan.';
            });
        },
        stopCamera() {
            if (this.html5QrCode) {
                try {
                    this.html5QrCode.stop().catch(() => {});
                } catch(e) {}
            }
        },
        findPesertaByBarcode(barcode) {
            if(this.isProcessingScan) return;
            this.isProcessingScan = true;
            this.scanLoading = true;
            this.scanError = '';
            
            const found = allPesertaData.find(p => p.barcode === barcode.trim());
            this.scanLoading = false;
            
            if (found) {
                // Hentikan pemindaian dan buka modal setelah janji terpenuhi
                if (this.html5QrCode) {
                    this.html5QrCode.stop().then(() => {
                        this.isScannerOpen = false;
                        this.openModal(found);
                    }).catch(() => {
                        this.isScannerOpen = false;
                        this.openModal(found);
                    });
                } else {
                    this.isScannerOpen = false;
                    this.openModal(found);
                }
            } else {
                this.scanError = 'QR Code tidak ditemukan: ' + barcode;
                // Allow another scan attempt after a delay
                setTimeout(() => { this.isProcessingScan = false; }, 2000);
            }
        },

        /* ── Registrasi Modal ── */
        isModalOpen: false,
        peserta: {},

        openModal(data) {
            this.peserta = data;
            this.isModalOpen = true;
        },
        get isLunas() {
            return this.peserta.status_pembayaran === 'lunas';
        }
    }"
    @keydown.escape.window="closeScanner(); isModalOpen = false">

    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Registrasi Ulang Peserta</h1>
            <p class="mt-1 text-gray-500 text-sm">Scan QR Code peserta untuk memulai proses registrasi ulang.</p>
        </div>
        <button @click="openScanner()"
                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm flex-shrink-0">
            <i class="fas fa-qrcode"></i>
            Registrasi Ulang
        </button>
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?php echo $_SESSION['message']['type']; ?>',
                    title: '<?php echo $_SESSION['message']['type'] == 'success' ? 'Berhasil!' : 'Gagal!'; ?>',
                    text: '<?php echo htmlspecialchars($_SESSION['message']['text'], ENT_QUOTES, 'UTF-8'); ?>',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        </script>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Form Pencarian -->
    <div class="mt-5">
        <form method="GET">
            <input type="hidden" name="page" value="peserta/registrasi_ulang">
            <div class="flex rounded-xl overflow-hidden shadow-sm border border-gray-200">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                       placeholder="Cari nama atau kelompok..."
                       class="w-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <button type="submit" class="px-5 py-2.5 font-semibold text-white bg-blue-600 hover:bg-blue-700 text-sm transition-colors">
                    <i class="fas fa-search mr-1"></i> Cari
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel Peserta -->
    <div class="mt-5 bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-list text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Daftar Peserta</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-semibold">
                        <th class="px-5 py-3 text-center">Status Reg.</th>
                        <th class="px-5 py-3">Nama</th>
                        <th class="px-5 py-3">Kelompok</th>
                        <th class="px-5 py-3">Ukuran Jersey</th>
                        <th class="px-5 py-3 text-center">Pembayaran</th>
                        <th class="px-5 py-3 text-center">Jersey</th>
                        <th class="px-5 py-3 text-center">ID Card</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($peserta_list)): ?>
                        <tr>
                            <td colspan="6" class="px-5 py-6 text-center text-gray-400">
                                <i class="fas fa-search text-2xl mb-2 block"></i>
                                Tidak ada peserta yang cocok dengan pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($peserta_list as $p): ?>
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-5 py-3 text-center">
                                    <?php if ($p['registrasi_ulang'] === 'ya'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Sudah</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 font-medium"><?php echo htmlspecialchars($p['nama']); ?></td>
                                <td class="px-5 py-3 text-gray-600"><?php echo htmlspecialchars($p['kelompok']); ?></td>
                                <td class="px-5 py-3 text-gray-600"><?php echo htmlspecialchars($p['ukuran_jersey'] ?? '-'); ?></td>
                                <td class="px-5 py-3 text-center">
                                    <?php if ($p['status_pembayaran'] == 'lunas'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Belum Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <?php if ($p['terima_jersey'] == 'ya'): ?>
                                        <i class="fas fa-check-circle text-green-500 text-base"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-gray-300 text-base"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <?php if ($p['terima_idcard'] == 'ya'): ?>
                                        <i class="fas fa-check-circle text-green-500 text-base"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-gray-300 text-base"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MODAL QR SCANNER                                                   -->
    <!-- ================================================================ -->
    <div x-show="isScannerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60" x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-qrcode text-white text-lg"></i>
                    <h3 class="text-lg font-bold text-white">Scan QR Code</h3>
                </div>
                <button @click="closeScanner()" class="text-white/80 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-5">
                <!-- Error -->
                <div x-show="scanError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700" style="display: none;">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span x-text="scanError"></span>
                </div>
                <!-- Loading -->
                <div x-show="scanLoading" class="text-center py-4 text-blue-600" style="display: none;">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                    <span class="text-sm">Mencari data peserta...</span>
                </div>
                <!-- QR Reader -->
                <div id="qr-reader" class="w-full rounded-xl overflow-hidden bg-gray-100 min-h-[200px]"></div>
                <p class="mt-3 text-center text-xs text-gray-400">Arahkan kamera ke QR Code peserta</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MODAL REGISTRASI ULANG                                             -->
    <!-- ================================================================ -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">

            <!-- Header Modal -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h3 class="text-lg font-bold text-white">Registrasi Ulang</h3>
            </div>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                <input type="hidden" name="action" :value="peserta.registrasi_ulang === 'ya' ? 'batalkan_registrasi' : 'update_registrasi'">
                <input type="hidden" name="peserta_id" :value="peserta.id">

                <div class="px-6 py-5 space-y-5">

                    <!-- Info Nama -->
                    <div class="mb-2">
                        <h4 class="text-xl font-bold text-gray-800" x-text="peserta.nama"></h4>
                    </div>

                    <!-- Info Peserta -->
                    <div class="grid grid-cols-2 gap-3 p-4 bg-blue-50 rounded-xl text-sm border border-blue-100">
                        <div>
                            <span class="text-blue-500 font-medium block">Kelompok</span>
                            <span class="text-gray-800 font-semibold" x-text="peserta.kelompok"></span>
                        </div>
                        <div>
                            <span class="text-blue-500 font-medium block">Jenis Kelamin</span>
                            <span class="text-gray-800 font-semibold" x-text="peserta.jenis_kelamin"></span>
                        </div>
                        <div>
                            <span class="text-blue-500 font-medium block">Ukuran Jersey</span>
                            <span class="text-gray-800 font-semibold" x-text="peserta.ukuran_jersey || '-'"></span>
                        </div>
                        <div>
                            <span class="text-blue-500 font-medium block">Metode Bayar</span>
                            <span class="text-gray-800 font-semibold" x-text="peserta.metode_pembayaran || '-'"></span>
                        </div>
                        <div class="col-span-2 mt-1">
                            <span class="text-blue-500 font-medium block mb-1">Status Pembayaran</span>
                            <span x-show="isLunas" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800 border border-green-200">
                                <i class="fas fa-check-circle"></i> Lunas
                            </span>
                            <span x-show="!isLunas" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                <i class="fas fa-clock"></i> Belum Lunas
                            </span>
                        </div>
                    </div>

                    <!-- Checklist Item — disabled jika belum lunas -->
                    <div class="border-t pt-4">
                        <p class="text-sm font-semibold text-gray-700 mb-3">Item Diterima</p>

                        <!-- Peringatan jika belum lunas -->
                        <div x-show="!isLunas" class="mb-3 flex items-center gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-700" style="display: none;">
                            <i class="fas fa-lock text-yellow-500"></i>
                            <span>Status belum lunas. Item tidak dapat diambil, lunasi di halaman daftar peserta.</span>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-3 p-3 rounded-xl border transition-colors"
                                   :class="isLunas ? 'border-gray-200 hover:bg-gray-50 cursor-pointer' : 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed'">
                                <input type="checkbox" name="ambil_jersey" value="ya"
                                       :checked="peserta.terima_jersey === 'ya'"
                                       :disabled="!isLunas"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 block">Sudah Menerima Jersey</span>
                                    <span class="text-xs text-gray-400">Centang jika peserta sudah menerima jersey</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 rounded-xl border transition-colors"
                                   :class="isLunas ? 'border-gray-200 hover:bg-gray-50 cursor-pointer' : 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed'">
                                <input type="checkbox" name="ambil_idcard" value="ya"
                                       :checked="peserta.terima_idcard === 'ya'"
                                       :disabled="!isLunas"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 block">Sudah Menerima ID Card</span>
                                    <span class="text-xs text-gray-400">Centang jika peserta sudah menerima ID Card</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-3">
                    <button type="button" @click="isModalOpen = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <!-- Tombol Batalkan Registrasi -->
                    <button type="submit" x-show="peserta.registrasi_ulang === 'ya'"
                            class="px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-times mr-1"></i> Batalkan Registrasi
                    </button>
                    <!-- Tombol Simpan Registrasi -->
                    <button type="submit" x-show="peserta.registrasi_ulang !== 'ya'" :disabled="!isLunas"
                            :class="isLunas ? 'bg-blue-600 hover:bg-blue-700 cursor-pointer shadow-sm' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition-colors">
                        <i class="fas fa-check mr-1"></i> Simpan Registrasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const allPesertaData = <?php echo json_encode($peserta_list); ?>;
</script>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>