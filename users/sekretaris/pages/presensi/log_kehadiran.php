<?php
// (File: pages/presensi/log_kehadiran.php)

// Logika untuk memproses form edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_kehadiran') {
    $log_id = $_POST['log_id'];
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'];

    // Update data di database
    $stmt_update = $conn->prepare("UPDATE log_presensi SET status = ?, keterangan = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $status, $keterangan, $log_id);
    if ($stmt_update->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data kehadiran berhasil diperbarui.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memperbarui data kehadiran.'];
    }
    $stmt_update->close();

    // Redirect untuk mencegah resubmit form
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


// Ambil data untuk filter
$sesi_list = [];
$result_sesi_list = $conn->query("SELECT id, nama_sesi FROM sesi_presensi ORDER BY nama_sesi");
if ($result_sesi_list) {
    while ($row = $result_sesi_list->fetch_assoc()) {
        $sesi_list[] = $row;
    }
}

// Logika Filter
$filter_sesi = $_GET['sesi'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_nama = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_sesi)) {
    $where_clauses[] = "l.id_sesi = ?";
    $params[] = $filter_sesi;
    $types .= 'i';
}
if (!empty($filter_status)) {
    $where_clauses[] = "l.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($search_nama)) {
    $where_clauses[] = "p.nama LIKE ?";
    $params[] = '%' . $search_nama . '%';
    $types .= 's';
}

$sql_where = '';
if (!empty($where_clauses)) {
    $sql_where = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Query utama untuk mengambil data log
$log_data = [];
$sql = "SELECT l.id, p.nama, p.kelompok, s.nama_sesi, l.status, l.waktu_presensi, l.keterangan 
        FROM log_presensi l
        JOIN peserta p ON l.id_peserta = p.id
        JOIN sesi_presensi s ON l.id_sesi = s.id
        $sql_where
        ORDER BY s.id, p.nama";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $log_data[] = $row;
    }
}
$stmt->close();
?>

<div class="p-6 bg-white rounded-lg shadow-md"
    x-data="{
        isModalOpen: false,
        logId: '',
        currentStatus: '',
        currentKeterangan: '',
        
        openModal(log) {
            this.logId = log.id;
            this.currentStatus = log.status;
            this.currentKeterangan = log.keterangan || '';
            this.isModalOpen = true;
        }
     }">
    <h1 class="text-3xl font-semibold text-gray-800">Log Kehadiran Peserta</h1>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="mt-4 p-4 rounded-md <?php echo $_SESSION['message']['type'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Form Filter -->
    <div class="mt-6 p-4 bg-gray-50 rounded-lg border">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="page" value="presensi/log_kehadiran">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700">Cari Nama</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_nama); ?>" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Nama peserta...">
            </div>
            <div>
                <label for="sesi" class="block text-sm font-medium text-gray-700">Filter Sesi</label>
                <select name="sesi" id="sesi" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">Semua Sesi</option>
                    <?php foreach ($sesi_list as $sesi_item): ?>
                        <option value="<?php echo $sesi_item['id']; ?>" <?php if ($filter_sesi == $sesi_item['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($sesi_item['nama_sesi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Filter Status</label>
                <select name="status" id="status" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">Semua Status</option>
                    <option value="Belum Presensi" <?php if ($filter_status == 'Belum Presensi') echo 'selected'; ?>>Belum Presensi</option>
                    <option value="Hadir" <?php if ($filter_status == 'Hadir') echo 'selected'; ?>>Hadir</option>
                    <option value="Terlambat" <?php if ($filter_status == 'Terlambat') echo 'selected'; ?>>Terlambat</option>
                </select>
            </div>
            <button type="submit" class="w-full px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">
                Filter
            </button>
        </form>
    </div>

    <!-- Tabel Log Kehadiran -->
    <div class="mt-6 overflow-x-auto">
        <table class="w-full whitespace-nowrap">
            <thead class="bg-gray-100">
                <tr class="text-left font-semibold">
                    <th class="px-6 py-3">Nama Peserta</th>
                    <th class="px-6 py-3">Kelompok</th>
                    <th class="px-6 py-3">Sesi</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Waktu Presensi</th>
                    <th class="px-6 py-3">Keterangan</th>
                    <th class="px-6 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($log_data)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">Tidak ada data untuk ditampilkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($log_data as $log): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($log['nama']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($log['kelompok']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($log['nama_sesi']); ?></td>
                            <td class="px-6 py-4">
                                <?php
                                $status_class = '';
                                if ($log['status'] == 'Hadir') $status_class = 'bg-green-100 text-green-800';
                                elseif ($log['status'] == 'Terlambat') $status_class = 'bg-yellow-100 text-yellow-800';
                                elseif ($log['status'] == 'Belum Presensi') $status_class = 'bg-gray-100 text-gray-800';
                                elseif ($log['status'] == 'Izin') $status_class = 'bg-yellow-100 text-yellow-800';
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($log['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo $log['waktu_presensi'] ? date('d M Y, H:i:s', strtotime($log['waktu_presensi'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($log['keterangan'] ?: '-'); ?></td>
                            <td class="px-6 py-4 text-center">
                                <button @click="openModal(<?php echo htmlspecialchars(json_encode($log)); ?>)" class="px-3 py-1 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Edit Kehadiran -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" @keydown.escape.window="isModalOpen = false" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4">Edit Data Kehadiran</h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                <input type="hidden" name="action" value="update_kehadiran">
                <input type="hidden" name="log_id" :value="logId">

                <div class="space-y-4">
                    <div>
                        <label for="edit_status" class="block text-sm font-medium">Status Kehadiran</label>
                        <select id="edit_status" name="status" x-model="currentStatus" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="Belum Presensi">Belum Presensi</option>
                            <option value="Hadir">Hadir</option>
                            <option value="Terlambat">Terlambat</option>
                            <option value="Izin">Izin</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_keterangan" class="block text-sm font-medium">Keterangan</label>
                        <textarea id="edit_keterangan" name="keterangan" x-model="currentKeterangan" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Tambahkan keterangan..."></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-4">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>