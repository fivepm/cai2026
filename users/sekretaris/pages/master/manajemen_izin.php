<?php
// Izinkan akses hanya untuk sekretaris
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}

$upload_dir = '../../uploads/surat_izin/'; // Path untuk browser

// Proses Aksi (Terima/Tolak)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $izin_id = $_POST['izin_id'] ?? null;
    if ($action != 'hapus') {
        $new_status = ($action === 'terima') ? 'diterima' : 'ditolak';
        $pemroses = $_SESSION['user_nama']; // Ambil nama pemroses dari session

        // Update status dan nama pemroses
        $stmt = $conn->prepare("UPDATE izin SET status = ?, diproses_oleh = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $pemroses, $izin_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'hapus' && $izin_id) {
        // Ambil nama file izin sebelum menghapus data dari DB
        $stmt_select = $conn->prepare("SELECT file_izin FROM izin WHERE id = ?");
        $stmt_select->bind_param("i", $izin_id);
        $stmt_select->execute();
        $file_to_delete = $stmt_select->get_result()->fetch_assoc()['file_izin'];
        $stmt_select->close();

        // Hapus file dari server jika ada
        if ($file_to_delete && file_exists($upload_dir . $file_to_delete)) {
            unlink($upload_dir . $file_to_delete);
        }

        // Hapus data dari database
        $stmt_delete = $conn->prepare("DELETE FROM izin WHERE id = ?");
        $stmt_delete->bind_param("i", $izin_id);

        if ($stmt_delete->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data izin berhasil dihapus.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menghapus data izin.'];
        }
        $stmt_delete->close();
    }

    // Redirect kembali ke halaman yang sama dengan filter yang aktif
    header("Location: sekretaris?page=master/manajemen_izin");
    exit();
}

// Logika filter & pencarian
$filter_kelompok = $_GET['kelompok'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_nama = $_GET['search'] ?? '';

$sql = "SELECT id, kelompok, nama, pakai_tabungan, file_izin, status, diproses_oleh, created_at FROM izin WHERE 1=1";
$params = [];
$types = '';
if (!empty($filter_kelompok)) {
    $sql .= " AND kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= 's';
}
if (!empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($search_nama)) {
    $sql .= " AND nama LIKE ?";
    $params[] = '%' . $search_nama . '%';
    $types .= 's';
}
$sql .= " ORDER BY status, created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$izin_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$role_user = $_SESSION['user_role'];
?>

<!-- Mulai HTML Konten -->
<div x-data="{ 
        isFilterModalOpen: false, 
        isSuratModalOpen: false, 
        suratUrl: '',

        viewSuratIzin(fileUrl) {
            const extension = fileUrl.split('.').pop().toLowerCase();
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (imageExtensions.includes(extension)) {
                this.isSuratModalOpen = true;
                this.suratUrl = fileUrl;
            } else {
                // Untuk PDF dan file lain (doc, docx), buka di tab baru
                window.open(fileUrl, '_blank');
            }
        }
    }"
    @keydown.escape.window="isSuratModalOpen = false">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-800">Manajemen Peserta Izin</h1>
        <button @click="isFilterModalOpen = true" class="px-4 py-2 font-semibold text-gray-700 bg-white rounded-md shadow-sm hover:bg-gray-50 flex items-center">
            <i class="fas fa-filter mr-2"></i>Filter & Cari
        </button>
    </div>

    <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="bg-gray-200">
                    <tr class="text-left font-bold">
                        <th class="px-6 py-3">No</th>
                        <th class="px-6 py-3">Nama</th>
                        <th class="px-6 py-3">Kelompok</th>
                        <th class="px-6 py-3">File Izin</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Diproses Oleh</th>
                        <?php
                        if ($role_user == 'superadmin') {
                        ?>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        <?php
                        }
                        ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    foreach ($izin_list as $izin): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo $no++; ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($izin['nama']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($izin['kelompok']); ?></td>
                            <td class="px-6 py-4">
                                <button @click="viewSuratIzin('<?php echo $upload_dir . htmlspecialchars($izin['file_izin']); ?>')" class="text-blue-600 hover:underline">
                                    <i class="fas fa-file-alt mr-1"></i>Lihat File
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $status_text = ucwords(str_replace('_', ' ', $izin['status']));
                                $status_color = 'bg-gray-400';
                                if ($izin['status'] == 'diterima') $status_color = 'bg-green-500';
                                if ($izin['status'] == 'perlu_verifikasi') $status_color = 'bg-yellow-500';
                                if ($izin['status'] == 'ditolak') $status_color = 'bg-red-500';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($izin['diproses_oleh'] ?? '-'); ?></td>
                            <?php
                            if ($role_user == 'superadmin') {
                            ?>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($izin['status'] == 'perlu_verifikasi'): ?>
                                        <form method="POST" action="" class="inline-flex space-x-2">
                                            <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                            <button type="submit" name="action" value="terima" class="px-3 py-1 text-sm text-white bg-green-500 rounded-md hover:bg-green-600">Terima</button>
                                            <button type="submit" name="action" value="tolak" class="px-3 py-1 text-sm text-white bg-red-500 rounded-md hover:bg-red-600">Tolak</button>
                                        </form>
                                    <?php
                                    endif; ?>
                                    <form method="POST" action="" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data izin ini secara permanen?');">
                                        <input type="hidden" name="izin_id" value="<?php echo $izin['id']; ?>">
                                        <button type="submit" name="action" value="hapus" class="px-3 py-1 text-sm text-white bg-gray-600 rounded-md hover:bg-gray-700">
                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            <?php
                            }
                            ?>
                        </tr>
                    <?php endforeach;
                    if (empty($izin_list)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">Tidak ada data izin yang cocok.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Filter -->
    <div x-show="isFilterModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isFilterModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4">Filter Peserta Izin</h3>
            <form action="admin.php" method="GET" class="space-y-4">
                <input type="hidden" name="page" value="master/manajemen_izin">
                <div><label class="block text-sm">Cari Nama</label><input type="text" name="search" value="<?php echo htmlspecialchars($search_nama); ?>" class="mt-1 w-full border-gray-300 rounded-md"></div>
                <div><label class="block text-sm">Filter Kelompok</label><select name="kelompok" class="mt-1 w-full border-gray-300 rounded-md">
                        <option value="">Semua Kelompok</option>
                        <option value="Bintaran" <?php if ($filter_kelompok == 'Bintaran') echo 'selected'; ?>>Bintaran</option>
                        <option value="Gedongkuning" <?php if ($filter_kelompok == 'Gedongkuning') echo 'selected'; ?>>Gedongkuning</option>
                        <option value="Jombor" <?php if ($filter_kelompok == 'Jombor') echo 'selected'; ?>>Jombor</option>
                        <option value="Sunten" <?php if ($filter_kelompok == 'Sunten') echo 'selected'; ?>>Sunten</option>
                    </select></div>
                <div><label class="block text-sm">Filter Status</label><select name="status" class="mt-1 w-full border-gray-300 rounded-md">
                        <option value="">Semua Status</option>
                        <option value="perlu_verifikasi" <?php if ($filter_status == 'perlu_verifikasi') echo 'selected'; ?>>Perlu Verifikasi</option>
                        <option value="diterima" <?php if ($filter_status == 'diterima') echo 'selected'; ?>>Diterima</option>
                        <option value="ditolak" <?php if ($filter_status == 'ditolak') echo 'selected'; ?>>Ditolak</option>
                    </select></div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isFilterModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Terapkan Filter</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Lihat Surat Izin (hanya untuk gambar) -->
    <div x-show="isSuratModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isSuratModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xl p-4 mx-4 relative">
            <button @click="isSuratModalOpen = false" class="absolute -top-3 -right-3 bg-red-600 text-white rounded-full h-8 w-8 flex items-center justify-center">&times;</button>
            <img :src="suratUrl" alt="Tampilan Surat Izin" class="w-full h-auto max-h-[80vh] object-contain">
        </div>
    </div>
</div>