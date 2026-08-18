<?php
// Izinkan akses hanya untuk sekretaris
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}

$upload_dir = '../../uploads/surat_izin/';

// 3. LOGIKA FILTER & PENCARIAN
$filter_kelompok = $_GET['kelompok'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_nama = $_GET['search'] ?? '';

// Query untuk menggabungkan data dari tabel 'peserta' dan 'izin'
$sql = "
    SELECT nama, jenis_kelamin, kelompok, 'Hadir' as status, metode_pembayaran, created_at FROM peserta
    UNION ALL
    SELECT nama, jenis_kelamin, kelompok, status, NULL as metode_pembayaran, created_at FROM izin
";

// Buat subquery untuk memungkinkan filtering pada data gabungan
$base_query = "SELECT * FROM ({$sql}) AS semua_pendaftar WHERE 1=1";
$params = [];
$types = '';

if (!empty($filter_kelompok)) {
    $base_query .= " AND kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= 's';
}
if (!empty($filter_status)) {
    // Sesuaikan filter untuk status 'Hadir'
    if ($filter_status === 'hadir') {
        $base_query .= " AND status = 'Hadir'";
    } else {
        $base_query .= " AND status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
}
if (!empty($search_nama)) {
    $base_query .= " AND nama LIKE ?";
    $params[] = '%' . $search_nama . '%';
    $types .= 's';
}
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
    'izin_lakilaki' => 0,
    'izin_perempuan' => 0,
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

// Hitung dari tabel izin
$result_izin = $conn->query("SELECT jenis_kelamin, COUNT(id) as jumlah FROM izin GROUP BY jenis_kelamin");
if ($result_izin) {
    while ($row = $result_izin->fetch_assoc()) {
        if ($row['jenis_kelamin'] == 'Laki-laki') {
            $stats['izin_lakilaki'] = $row['jumlah'];
        } else {
            $stats['izin_perempuan'] = $row['jumlah'];
        }
    }
}

// Hitung total
$total_laki = $stats['hadir_lakilaki'] + $stats['izin_lakilaki'];
$total_perempuan = $stats['hadir_perempuan'] + $stats['izin_perempuan'];
$total_hadir = $stats['hadir_lakilaki'] + $stats['hadir_perempuan'];
$total_izin = $stats['izin_lakilaki'] + $stats['izin_perempuan'];
$total_pendaftar = $total_hadir + $total_izin;
// =======================================================

// =======================================================
// KODE BARU: Memproses data untuk Modal Detail
// =======================================================
$summary_data = [];
$kelompok_list = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];
$grand_total = ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0];

// Inisialisasi array
foreach ($kelompok_list as $kelompok) {
    $summary_data[$kelompok] = ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0];
}

// Query untuk mengambil data gabungan
$summary_sql = "
    SELECT kelompok, jenis_kelamin, COUNT(nama) as jumlah
    FROM (
        SELECT kelompok, jenis_kelamin, nama FROM peserta
        UNION ALL
        SELECT kelompok, jenis_kelamin, nama FROM izin
    ) AS semua_pendaftar
    GROUP BY kelompok, jenis_kelamin
";

$summary_result = $conn->query($summary_sql);
if ($summary_result) {
    while ($row = $summary_result->fetch_assoc()) {
        $kelompok = $row['kelompok'];
        $jenis_kelamin = $row['jenis_kelamin'];
        $jumlah = (int)$row['jumlah'];

        if (isset($summary_data[$kelompok])) {
            $summary_data[$kelompok][$jenis_kelamin] = $jumlah;
            $summary_data[$kelompok]['total'] += $jumlah;
            $grand_total[$jenis_kelamin] += $jumlah;
            $grand_total['total'] += $jumlah;
        }
    }
}
// =======================================================

$nama_user = $_SESSION['user_nama'];
$role_user = $_SESSION['user_role'];
?>

<div x-data="{ sidebarOpen: false, isFilterModalOpen: false, isDetailModalOpen: false,
        printAttendance() {
            const filterParams = new URLSearchParams(window.location.search).toString();
            window.open(`pages/master/print_hadir.php?${filterParams}`, '_blank');
        }  
        }" @keydown.escape.window="isFilterModalOpen = false" class="flex-1 flex flex-col overflow-hidden">

    <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
        <h1 class="text-center text-3xl font-semibold text-gray-800">Rekapitulasi Pendaftar<br>Desa Banguntapan 1</h1>

        <div class="mt-2 flex justify-between items-center">
            <h1 class="text-3xl font-semibold text-gray-800"></h1>
            <div class="flex space-x-4">
                <button @click="isDetailModalOpen = true" class="no-print px-4 py-2 font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-chart-pie mr-2"></i>Lihat Detail
                </button>
                <button @click="printAttendance()" class="px-3 py-2 text-sm font-semibold text-white bg-gray-600 rounded-md hover:bg-gray-700 flex items-center">
                    <i class="fas fa-print mr-2"></i>Cetak
                </button>
                <button @click="isFilterModalOpen = true" class="no-print px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center">
                    <i class="fas fa-filter mr-2"></i>Filter & Cari
                </button>
            </div>
        </div>

        <!-- ======================================================= -->
        <!-- KODE BARU: Tampilan Statistik -->
        <!-- ======================================================= -->
        <div class="mt-6 grid grid-cols-3 md:grid-cols-3 gap-6">
            <!-- Card Total Pendaftar -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-2">
                    <div class="p-3 bg-blue-500 rounded-full mr-4">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500">Total Pendaftar</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_pendaftar; ?></p>
                    </div>
                </div>
                <div class="mt-4 border-t pt-2 text-sm text-gray-600 space-y-1">
                    <p>Laki-laki: <span class="font-semibold"><?php echo $total_laki; ?></span></p>
                    <p>Perempuan: <span class="font-semibold"><?php echo $total_perempuan; ?></span></p>
                </div>
            </div>

            <!-- Card Peserta Hadir -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-2">
                    <div class="p-3 bg-green-500 rounded-full mr-4">
                        <i class="fas fa-user-check text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500">Total Hadir</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_hadir; ?></p>
                    </div>
                </div>
                <div class="mt-4 border-t pt-2 text-sm text-gray-600 space-y-1">
                    <p>Laki-laki: <span class="font-semibold"><?php echo $stats['hadir_lakilaki']; ?></span></p>
                    <p>Perempuan: <span class="font-semibold"><?php echo $stats['hadir_perempuan']; ?></span></p>
                </div>
            </div>

            <!-- Card Peserta Izin -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex items-center mb-2">
                    <div class="p-3 bg-yellow-500 rounded-full mr-4">
                        <i class="fas fa-user-clock text-white text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500">Total Izin</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_izin; ?></p>
                    </div>
                </div>
                <div class="mt-4 border-t pt-2 text-sm text-gray-600 space-y-1">
                    <p>Laki-laki: <span class="font-semibold"><?php echo $stats['izin_lakilaki']; ?></span></p>
                    <p>Perempuan: <span class="font-semibold"><?php echo $stats['izin_perempuan']; ?></span></p>
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
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Tanggal Daftar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $no = 1;
                        foreach ($pendaftar_list as $pendaftar): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo $no++; ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($pendaftar['nama']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($pendaftar['kelompok']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($pendaftar['jenis_kelamin']); ?></td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status_text = ucwords(str_replace('_', ' ', $pendaftar['status']));
                                    $status_color = 'bg-green-500'; // Default untuk Hadir
                                    if ($pendaftar['status'] == 'diterima') {
                                        $status_color = 'bg-blue-500';
                                        $status_text = 'Izin Diterima';
                                    }
                                    if ($pendaftar['status'] == 'perlu_verifikasi') {
                                        $status_color = 'bg-yellow-500';
                                        $status_text = 'Izin Pending';
                                    }
                                    if ($pendaftar['status'] == 'ditolak') {
                                        $status_color = 'bg-red-500';
                                        $status_text = 'Izin Ditolak';
                                    }
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('d M Y, H:i', strtotime($pendaftar['created_at'])); ?></td>
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

        <!-- ============================================= -->
        <!-- TAMBAHKAN KODE MODAL FILTER DI SINI -->
        <!-- ============================================= -->
        <div x-show="isFilterModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
            <div @click.away="isFilterModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
                <h3 class="text-2xl font-bold mb-4">Filter & Cari Pendaftar</h3>
                <form action="sekretaris" method="GET" class="space-y-4">
                    <input type="hidden" name="page" value="master/rekap_pendaftar">
                    <div>
                        <label for="search" class="block text-sm font-medium">Cari Nama</label>
                        <input type="text" id="search" name="search" placeholder="Cari nama..." value="<?php echo htmlspecialchars($search_nama); ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="kelompok" class="block text-sm font-medium">Filter Kelompok</label>
                        <select id="kelompok" name="kelompok" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Semua Kelompok</option>
                            <option value="Bintaran" <?php if ($filter_kelompok == 'Bintaran') echo 'selected'; ?>>Bintaran</option>
                            <option value="Gedongkuning" <?php if ($filter_kelompok == 'Gedongkuning') echo 'selected'; ?>>Gedongkuning</option>
                            <option value="Jombor" <?php if ($filter_kelompok == 'Jombor') echo 'selected'; ?>>Jombor</option>
                            <option value="Sunten" <?php if ($filter_kelompok == 'Sunten') echo 'selected'; ?>>Sunten</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium">Filter Status</label>
                        <select id="status" name="status" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="">Semua Status</option>
                            <option value="hadir" <?php if ($filter_status == 'hadir') echo 'selected'; ?>>Hadir</option>
                            <option value="diterima" <?php if ($filter_status == 'diterima') echo 'selected'; ?>>Izin Diterima</option>
                            <option value="ditolak" <?php if ($filter_status == 'ditolak') echo 'selected'; ?>>Izin Ditolak</option>
                            <option value="perlu_verifikasi" <?php if ($filter_status == 'perlu_verifikasi') echo 'selected'; ?>>Perlu Verifikasi</option>
                        </select>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="button" @click="isFilterModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================= -->
        <!-- KODE BARU: Modal Detail Pendaftar -->
        <!-- ============================================= -->
        <div x-show="isDetailModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
            <div @click.away="isDetailModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 mx-4">
                <h3 class="text-2xl font-bold mb-4 text-center">Ringkasan Pendaftar per Kelompok</h3>
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead class="bg-gray-200">
                            <tr class="text-left font-bold">
                                <th class="px-6 py-3">Kelompok</th>
                                <th class="px-6 py-3 text-center">Laki-laki</th>
                                <th class="px-6 py-3 text-center">Perempuan</th>
                                <th class="px-6 py-3 text-center">Total per Kelompok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($summary_data as $kelompok => $data): ?>
                                <tr>
                                    <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($kelompok); ?></td>
                                    <td class="px-6 py-4 text-center"><?php echo $data['Laki-laki']; ?></td>
                                    <td class="px-6 py-4 text-center"><?php echo $data['Perempuan']; ?></td>
                                    <td class="px-6 py-4 text-center font-bold"><?php echo $data['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-800 text-white font-bold">
                            <tr>
                                <td class="px-6 py-3">Grand Total</td>
                                <td class="px-6 py-3 text-center"><?php echo $grand_total['Laki-laki']; ?></td>
                                <td class="px-6 py-3 text-center"><?php echo $grand_total['Perempuan']; ?></td>
                                <td class="px-6 py-3 text-center"><?php echo $grand_total['total']; ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" @click="isDetailModalOpen = false" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Tutup</button>
                </div>
            </div>
        </div>
    </main>
</div>