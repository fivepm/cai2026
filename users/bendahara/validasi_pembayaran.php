<?php
session_start();
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'bendahara'])) {
    header("Location: ../../login");
    exit();
}
require_once '../../config/config.php';

$upload_dir = '../../uploads/bukti_pembayaran/';

// Proses Aksi (Terima, Tolak, Batalkan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $peserta_id = $_POST['peserta_id'];

    // Ambil data peserta untuk digunakan di log keuangan
    $stmt_peserta = $conn->prepare("SELECT nama FROM peserta WHERE id = ?");
    $stmt_peserta->bind_param("i", $peserta_id);
    $stmt_peserta->execute();
    $nama_peserta = $stmt_peserta->get_result()->fetch_assoc()['nama'];
    $stmt_peserta->close();

    $keterangan_log = "Pembayaran dari " . $nama_peserta . " (ID Peserta: " . $peserta_id . ")";
    $jumlah_pembayaran = 50000; // GANTI DENGAN JUMLAH PEMBAYARAN YANG SEBENARNYA

    if ($action === 'terima') {
        // 1. Ubah status peserta menjadi lunas
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'lunas', dibayar_pada = CURDATE() WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. Tambahkan entri ke log keuangan
        $stmt_log = $conn->prepare("INSERT INTO log_keuangan (tanggal, keterangan, jenis, sumber_pemasukan, jumlah) VALUES (CURDATE(), ?, 'masuk', 'peserta', ?)");
        $stmt_log->bind_param("sd", $keterangan_log, $jumlah_pembayaran);
        $stmt_log->execute();
        $stmt_log->close();
    } elseif ($action === 'tolak') {
        // Ubah status menjadi ditolak
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'ditolak' WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();
    } elseif ($action === 'batal') {
        // 1. Kembalikan status peserta menjadi belum diverifikasi
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'belum_diverifikasi', dibayar_pada = NULL WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. Hapus entri dari log keuangan
        $stmt_log = $conn->prepare("DELETE FROM log_keuangan WHERE keterangan = ?");
        $stmt_log->bind_param("s", $keterangan_log);
        $stmt_log->execute();
        $stmt_log->close();
    }

    header("Location: validasi_pembayaran");
    exit();
}

$pembayaran_list = $conn->query("SELECT id, nama, kelompok, pakai_tabungan, metode_pembayaran, bukti_pembayaran, status_pembayaran FROM peserta WHERE metode_pembayaran != '' ORDER BY kelompok, nama ASC")->fetch_all(MYSQLI_ASSOC);
$nama_user = $_SESSION['user_nama'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Pembayaran</title>
    <link rel="icon" type="image/png" href="../../uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100 font-sans">
    <div x-data="{ sidebarOpen: false, isBuktiModalOpen: false, buktiUrl: '' }" @keydown.escape.window="isBuktiModalOpen = false" class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-30 w-64 px-4 py-7 overflow-y-auto bg-red-700 text-white transition-transform duration-300 md:relative md:translate-x-0">
            <div class="flex items-center justify-center">
                <h2 class="text-center text-2xl font-bold">Bendahara CAI</h2>
                <button @click="sidebarOpen = false" class="md:hidden">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <nav class="mt-10">
                <a href="dashboard" class="flex items-center px-4 py-2 mt-5 text-white-400 rounded-md hover:bg-red-600">
                    <i class="fas fa-tachometer-alt w-6"></i>
                    <span class="mx-4 font-medium">Dashboard</span>
                </a>
                <a href="log_keuangan" class="flex items-center px-4 py-2 mt-5 text-white-400 rounded-md hover:bg-red-600">
                    <i class="fas fa-book w-6"></i>
                    <span class="mx-4 font-medium">Log Keuangan</span>
                </a>
                <a href="validasi_pembayaran" class="flex items-center px-4 py-2 mt-5 text-white-200 bg-red-600 rounded-md">
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
                <div x-data="{ dropdownOpen: false }" class="relative"><button @click="dropdownOpen = !dropdownOpen" class="relative z-10 block"><span class="font-medium text-gray-700">Halo, <?php echo htmlspecialchars($nama_user); ?>!</span><i class="fas fa-chevron-down text-xs ml-1"></i></button>
                    <div x-show="dropdownOpen" @click.away="dropdownOpen = false" class="absolute right-0 z-20 w-48 py-2 mt-2 bg-white rounded-md shadow-xl" x-transition><a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-500 hover:text-white">Logout</a></div>
                </div>
            </header>
            <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
                <h1 class="text-3xl font-semibold text-gray-800">Validasi Pembayaran Peserta</h1>
                <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="bg-gray-200">
                                <tr class="text-left font-bold">
                                    <th class="px-6 py-3">No</th>
                                    <th class="px-6 py-3">Nama</th>
                                    <th class="px-6 py-3">Kelompok</th>
                                    <th class="px-6 py-3">Tabungan</th>
                                    <th class="px-6 py-3">Metode Pembayaran</th>
                                    <th class="px-6 py-3">Bukti Bayar</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $no = 1;
                                foreach ($pembayaran_list as $p): ?>
                                    <tr>
                                        <td class="px-6 py-4"><?php echo $no++; ?></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($p['nama']); ?></td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($p['kelompok']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $p['pakai_tabungan'] == 'yes' ? 'bg-green-500' : 'bg-red-500'; ?>">
                                                <?php echo ucfirst($p['pakai_tabungan']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($p['metode_pembayaran']); ?></td>
                                        <td class="px-6 py-4">
                                            <?php if (empty($p['bukti_pembayaran'])) {
                                                echo "-";
                                            } else {
                                            ?>
                                                <button @click="isBuktiModalOpen = true; buktiUrl = '<?php echo $upload_dir . htmlspecialchars($p['bukti_pembayaran']); ?>'" class="text-blue-600 hover:underline">
                                                    <i class="fas fa-file-invoice-dollar mr-1"></i>
                                                    Lihat Bukti
                                                </button>
                                            <?php
                                            }
                                            ?>

                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $p['status_pembayaran'] == 'lunas' ? 'bg-green-500' : ($p['status_pembayaran'] == 'ditolak' ? 'bg-red-500' : 'bg-yellow-500'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $p['status_pembayaran'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <form method="POST" class="inline-flex space-x-2">
                                                <input type="hidden" name="peserta_id" value="<?php echo $p['id']; ?>">
                                                <?php if ($p['status_pembayaran'] == 'belum_diverifikasi'): ?>
                                                    <button type="submit" name="action" value="terima" class="px-3 py-1 text-sm text-white bg-green-500 rounded-md">
                                                        Terima
                                                    </button>
                                                <?php elseif ($p['status_pembayaran'] == 'lunas'): ?>
                                                    <button type="submit" name="action" value="batal" class="px-3 py-1 text-sm text-white bg-yellow-500 rounded-md">
                                                        Batalkan Lunas
                                                    </button>
                                                    <a href="cetak_invoice.php?id=<?php echo $p['id']; ?>" target="_blank" class="px-3 py-1 text-sm text-white bg-blue-500 rounded-md">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                <?php else: echo '-';
                                                endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        <!-- Modal Lihat Bukti Pembayaran -->
        <div x-show="isBuktiModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
            <div @click.away="isBuktiModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xl p-4 mx-4 relative">
                <button @click="isBuktiModalOpen = false" class="absolute -top-3 -right-3 bg-red-600 text-white rounded-full h-8 w-8 flex items-center justify-center">&times;</button>
                <img :src="buktiUrl" alt="Bukti Pembayaran" class="w-full h-auto max-h-[80vh] object-contain">
            </div>
        </div>
    </div>
</body>

</html>