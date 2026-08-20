<?php
session_start();

// 1. OTENTIKASI & OTORISASI
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] != 'sie_pdd') {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Anda tidak memiliki hak akses.'];
    header("Location: ../../index.php");
    exit();
}

// 2. KONEKSI & PENGATURAN
require_once '../../config/config.php';
$upload_dir = '../../uploads/surat_izin/';
$sql = "SELECT nama, jenis_kelamin, kelompok, barcode, ukuran_jersey FROM peserta";

// Buat subquery untuk memungkinkan filtering pada data gabungan
$base_query = "SELECT * FROM ({$sql}) AS semua_pendaftar";
$params = [];
$types = '';

$base_query .= " ORDER BY kelompok, nama ASC";

$stmt = $conn->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pendaftar_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// =======================================================
// KODE BARU: Menghitung Statistik Pendaftar
// =======================================================
$stats = [
    'hadir_lakilaki' => 0,
    'hadir_perempuan' => 0,
];

// Hitung dari tabel peserta (hadir)
$result_peserta = $conn->query("SELECT jenis_kelamin, COUNT(id) as jumlah FROM peserta GROUP BY jenis_kelamin");
if ($result_peserta) {
    while ($row = $result_peserta->fetch_assoc()) {
        if ($row['jenis_kelamin'] == 'Laki-laki') {
            $stats['hadir_lakilaki'] = $row['jumlah'];
        } else {
            $stats['hadir_perempuan'] = $row['jumlah'];
        }
    }
}

// Hitung total
$total_hadir = $stats['hadir_lakilaki'] + $stats['hadir_perempuan'];

// =======================================================
// Rekap Ukuran Jersey
// =======================================================
$jersey_order = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL', '8XL', '9XL', '10XL', '11XL'];
$jersey_stats = array_fill_keys($jersey_order, 0);
$jersey_stats['(Belum diisi)'] = 0;

$result_jersey = $conn->query("SELECT COALESCE(NULLIF(TRIM(ukuran_jersey),''), '(Belum diisi)') AS ukuran, COUNT(id) AS jumlah FROM peserta GROUP BY ukuran ORDER BY ukuran");
if ($result_jersey) {
    while ($row = $result_jersey->fetch_assoc()) {
        $uk = $row['ukuran'];
        if (array_key_exists($uk, $jersey_stats)) {
            $jersey_stats[$uk] = (int)$row['jumlah'];
        } else {
            $jersey_stats[$uk] = (int)$row['jumlah'];
        }
    }
}
// Hapus ukuran yang 0 dan tidak terpakai
$jersey_stats = array_filter($jersey_stats, fn($v) => $v > 0);
$total_jersey = array_sum($jersey_stats);
// =======================================================

$nama_user = $_SESSION['user_nama'];
$role_user = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pendaftar - CAI 2026</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <script>
        function pendaftarData() {
            return {
                isQrCodeModalOpen: false, 
                qrCodeValue: '', 
                qrCodeName: '', 
                isDownloading: false,

                showQrCode(value, name) {
                    this.isQrCodeModalOpen = true; 
                    this.qrCodeValue = value; 
                    this.qrCodeName = name;
                    this.$nextTick(() => {
                        const qrEl = document.getElementById('qrcode-display');
                        qrEl.innerHTML = ''; 
                        new QRCode(qrEl, { text: value, width: 200, height: 200 });
                    });
                },
                downloadQrCode(value, filename) {
                    const t = document.createElement('div'); 
                    t.style.display = 'none'; 
                    document.body.appendChild(t);
                    new QRCode(t, { text: value, width: 256, height: 256 });
                    setTimeout(() => {
                        const i = t.querySelector('img');
                        if (i) { 
                            const l = document.createElement('a'); 
                            l.href = i.src; 
                            l.download = filename; 
                            l.click(); 
                        }
                        document.body.removeChild(t);
                    }, 100);
                },
                async downloadAllQrCodesZip() {
                    this.isDownloading = true;
                    try {
                        const response = await fetch('download_qr_zip.php');
                        if (!response.ok) throw new Error('Terjadi kesalahan pada server');
                        
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        
                        const disposition = response.headers.get('Content-Disposition');
                        let filename = 'QRCode_Semua_Peserta.zip';
                        if (disposition && disposition.indexOf('filename=') !== -1) {
                            filename = disposition.split('filename=')[1].replace(/['"]/g, '');
                        }
                        
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                    } catch (error) {
                        alert('Gagal mengunduh file ZIP: ' + error.message);
                    } finally {
                        this.isDownloading = false;
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-100 font-sans">

    <!-- Konten Utama -->
    <div x-data="pendaftarData()"
        @keydown.escape.window="isQrCodeModalOpen=false"
        class="flex-1 flex flex-col overflow-hidden relative">
        <header class="flex items-center justify-between px-6 py-4 bg-white border-b-4 border-blue-600">
            <img src="../../assets/images/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-10 w-auto">
            <h2 class="px-3 text-xl font-bold text-center text-gray-800">CAI Banguntapan 1 - Tahun 2026</h2>
            <div class="flex-1"></div>
            <div x-data="{ dropdownOpen: false }" class="relative"><button @click="dropdownOpen = !dropdownOpen" class="relative z-10 block"><span class="font-medium text-gray-700">Halo, <?php echo htmlspecialchars($nama_user); ?>!</span><i class="fas fa-chevron-down text-xs ml-1"></i></button>
                <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 z-20 w-48 py-2 mt-2 bg-white rounded-md shadow-xl" x-transition><a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-500 hover:text-white">Logout</a></div>
            </div>
        </header>

        <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
            <h1 class="text-center text-3xl font-semibold text-gray-800">Rekapitulasi Pendaftar <br> Desa Banguntapan 1</h1>

            <div class="py-3 flex flex-wrap justify-center gap-3">
                <!-- Tombol Download ZIP QR Code -->
                <button @click="downloadAllQrCodesZip()"
                        class="px-4 py-2 font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-file-zipper"></i>
                    Download Semua QR Code (.zip)
                </button>
                <!-- Tombol Export CSV -->
                <a href="export_peserta" class="px-4 py-2 font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 flex items-center gap-2 transition-colors">
                    <i class="fas fa-file-csv"></i>Export ke CSV
                </a>
            </div>

            <!-- ======================================================= -->
            <!-- Tampilan Statistik -->
            <!-- ======================================================= -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Card Peserta Hadir -->
                <div class="bg-white p-6 rounded-lg shadow-md flex flex-col items-center">
                    <div class="flex items-center mb-2">
                        <div class="p-3 bg-green-500 rounded-full mr-4">
                            <i class="fas fa-user-check text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Total Pendaftar</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $total_hadir; ?></p>
                        </div>
                    </div>
                    <div class="mt-4 border-t pt-2 text-sm text-gray-600 space-y-1 w-full text-center">
                        <p>Laki-laki: <span class="font-semibold"><?php echo $stats['hadir_lakilaki']; ?></span></p>
                        <p>Perempuan: <span class="font-semibold"><?php echo $stats['hadir_perempuan']; ?></span></p>
                    </div>
                </div>

                <!-- Card Rekap Ukuran Jersey -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-indigo-500 rounded-full mr-4">
                            <i class="fas fa-shirt text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Rekap Ukuran Jersey</p>
                            <p class="text-3xl font-bold text-gray-900"><?php echo $total_jersey; ?> <span class="text-base font-normal text-gray-500">peserta</span></p>
                        </div>
                    </div>
                    <div class="border-t pt-3">
                        <?php if (empty($jersey_stats)): ?>
                            <p class="text-sm text-gray-400 text-center">Belum ada data ukuran jersey.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <?php foreach ($jersey_stats as $ukuran => $jumlah): ?>
                                    <div class="flex justify-between items-center py-1 border-b border-gray-100">
                                        <span class="font-medium text-gray-700">
                                            <?php if ($ukuran === '(Belum diisi)'): ?>
                                                <span class="text-gray-400 italic"><?php echo htmlspecialchars($ukuran); ?></span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($ukuran); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                            <?php echo $jumlah; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <!-- ======================================================= -->

            <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead class="bg-gray-200">
                            <tr class="text-left font-bold">
                                <th class="px-6 py-3">No</th>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Kelompok</th>
                                <th class="px-6 py-3">Jenis Kelamin</th>
                                <th class="px-6 py-3">Ukuran Jersey</th>
                                <th class="px-6 py-3">Barcode / QR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php
                            $no = 1;
                            foreach ($pendaftar_list as $pendaftar): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-gray-500"><?php echo $no++; ?></td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($pendaftar['nama']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($pendaftar['kelompok']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($pendaftar['jenis_kelamin']); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($pendaftar['ukuran_jersey'])): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                <?php echo htmlspecialchars($pendaftar['ukuran_jersey']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-300 text-xs italic">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <button @click="showQrCode('<?php echo htmlspecialchars($pendaftar['barcode']); ?>', '<?php echo htmlspecialchars($pendaftar['nama'], ENT_QUOTES); ?>')" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 mr-2" title="Lihat QR Code">
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                        <button @click="downloadQrCode('<?php echo htmlspecialchars($pendaftar['barcode']); ?>', 'qrcode-<?php echo htmlspecialchars($pendaftar['nama']); ?>.png')" class="px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600" title="Download QR Code">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;
                            if (empty($pendaftar_list)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">Tidak ada data pendaftar yang cocok.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        <!-- Modal Lihat QR Code -->
        <div x-show="isQrCodeModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
            <div @click.away="isQrCodeModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xs p-6 mx-4 text-center">
                <h3 class="text-xl font-bold" x-text="`QR Code untuk ${qrCodeName}`"></h3>
                <div class="my-4 p-4 bg-white">
                    <div id="qrcode-display" class="flex justify-center"></div>
                </div>
                <button type="button" @click="isQrCodeModalOpen = false" class="mt-6 w-full px-4 py-2 bg-red-600 text-white rounded-md">Tutup</button>
            </div>
        </div>
        
        <!-- Loading Overlay untuk Download ZIP -->
        <div x-show="isDownloading" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black bg-opacity-75" x-transition x-cloak style="display: none;">
            <div class="w-16 h-16 border-4 border-gray-300 border-t-blue-500 rounded-full animate-spin mb-4"></div>
            <p class="text-lg font-semibold text-white tracking-wide">Mempersiapkan file ZIP... Mohon tunggu.</p>
        </div>
    </div>
    <?php $conn->close(); ?>
</body>
<script>
    const allParticipants = <?php echo json_encode($pendaftar_list); ?>;
</script>


</html>