<?php
// (File: pages/presensi/manajemen_sesi_presensi.php)

// Memaksa PHP untuk menampilkan error (berguna untuk debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require_once '../../config/config.php';
// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// Logika untuk menangani form action (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'tambah') {
        $nama_sesi = $_POST['nama_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];

        $conn->begin_transaction();
        try {
            // 1. Tambah sesi baru
            $stmt_sesi = $conn->prepare("INSERT INTO sesi_presensi (nama_sesi, tanggal_sesi, waktu_sesi) VALUES (?, ?, ?)");
            $stmt_sesi->bind_param("sss", $nama_sesi, $tanggal_sesi, $waktu_sesi);
            $stmt_sesi->execute();
            $id_sesi_baru = $stmt_sesi->insert_id;
            $stmt_sesi->close();

            // 2. Ambil semua ID peserta yang ada
            $peserta_ids = [];
            $result_peserta = $conn->query("SELECT id FROM peserta");
            while ($row = $result_peserta->fetch_assoc()) {
                $peserta_ids[] = $row['id'];
            }

            // 3. Masukkan semua peserta ke log_presensi untuk sesi baru ini
            if (!empty($peserta_ids)) {
                $stmt_log = $conn->prepare("INSERT INTO log_presensi (id_peserta, id_sesi, status) VALUES (?, ?, 'Belum Presensi')");
                foreach ($peserta_ids as $id_peserta) {
                    $stmt_log->bind_param("ii", $id_peserta, $id_sesi_baru);
                    $stmt_log->execute();
                }
                $stmt_log->close();
            }

            $conn->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Sesi baru berhasil ditambahkan dan semua peserta telah didaftarkan.'];
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menambahkan sesi baru. Error: ' . $exception->getMessage()];
        }
    } elseif ($action === 'edit') {
        $id_sesi = $_POST['id_sesi'];
        $nama_sesi = $_POST['nama_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];

        $stmt = $conn->prepare("UPDATE sesi_presensi SET nama_sesi = ?, tanggal_sesi = ?, waktu_sesi = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nama_sesi, $tanggal_sesi, $waktu_sesi, $id_sesi);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data sesi berhasil diperbarui.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memperbarui data sesi.'];
        }
        $stmt->close();
    } elseif ($action === 'hapus') {
        $id_sesi = $_POST['id_sesi'];

        $conn->begin_transaction();
        try {
            // Hapus semua log presensi yang terkait dengan sesi ini
            $stmt_log = $conn->prepare("DELETE FROM log_presensi WHERE id_sesi = ?");
            $stmt_log->bind_param("i", $id_sesi);
            $stmt_log->execute();
            $stmt_log->close();

            // Hapus sesi itu sendiri
            $stmt_sesi = $conn->prepare("DELETE FROM sesi_presensi WHERE id = ?");
            $stmt_sesi->bind_param("i", $id_sesi);
            $stmt_sesi->execute();
            $stmt_sesi->close();

            $conn->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Sesi dan semua data kehadirannya berhasil dihapus.'];
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menghapus sesi. Error: ' . $exception->getMessage()];
        }
    }

    // Redirect untuk mencegah resubmit form
    header("Location: sekretaris?page=presensi/manajemen_sesi_presensi");
    exit();
}

// Mengambil data sesi yang ada untuk ditampilkan
$sesi = $conn->query("SELECT * FROM sesi_presensi ORDER BY tanggal_sesi, waktu_sesi")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<script>
    function manajemenSesi() {
        return {
            isModalOpen: false,
            isEdit: false,
            modalTitle: '',
            sesiId: null,
            sesiNama: '',
            sesiTanggal: '',
            sesiWaktu: '',

            openTambahModal() {
                this.isEdit = false;
                this.modalTitle = 'Tambah Sesi Baru';
                this.sesiId = null;
                this.sesiNama = '';
                this.sesiTanggal = '';
                this.sesiWaktu = '';
                this.isModalOpen = true;
            },

            openEditModal(sesi) {
                this.isEdit = true;
                this.modalTitle = 'Edit Sesi';
                this.sesiId = sesi.id;
                this.sesiNama = sesi.nama_sesi;
                this.sesiTanggal = sesi.tanggal_sesi;
                this.sesiWaktu = sesi.waktu_sesi;
                this.isModalOpen = true;
            }
        }
    }
</script>

<div class="p-6" x-data="manajemenSesi()">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-800">Manajemen Sesi Presensi</h1>
        <button @click="openTambahModal()" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center">
            <i class="fas fa-plus mr-2"></i>Tambah Sesi Baru
        </button>
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="mt-4 p-4 rounded-md <?php echo $_SESSION['message']['type'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
        </div>
    <?php unset($_SESSION['message']);
    endif; ?>

    <!-- Tabel Sesi -->
    <div class="mt-6 bg-white p-6 rounded-lg shadow-md">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="bg-gray-100">
                    <tr class="text-left font-bold">
                        <th class="px-6 py-3">Nama Sesi</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($sesi)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Belum ada sesi yang dibuat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sesi as $item): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($item['nama_sesi']); ?></td>
                                <td class="px-6 py-4"><?php echo date('d M Y', strtotime($item['tanggal_sesi'])); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($item['waktu_sesi']); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <button @click="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="px-3 py-1 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">Edit</button>

                                    <form method="POST" action="" class="inline-block" onsubmit="return confirm('Menghapus sesi ini akan menghapus SEMUA data kehadiran terkait. Apakah Anda yakin?');">
                                        <input type="hidden" name="id_sesi" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="action" value="hapus" class="px-3 py-1 text-sm text-white bg-gray-600 rounded-md hover:bg-gray-700">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah/Edit Sesi -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4" x-text="modalTitle"></h3>
            <form method="POST" action="">
                <input type="hidden" name="id_sesi" x-model="sesiId">
                <input type="hidden" name="action" :value="isEdit ? 'edit' : 'tambah'">

                <div class="space-y-4">
                    <div>
                        <label for="nama_sesi" class="block text-sm font-medium">Nama Sesi</label>
                        <input type="text" id="nama_sesi" name="nama_sesi" x-model="sesiNama" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="tanggal_sesi" class="block text-sm font-medium">Tanggal Sesi</label>
                        <input type="date" id="tanggal_sesi" name="tanggal_sesi" x-model="sesiTanggal" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="waktu_sesi" class="block text-sm font-medium">Waktu Sesi</e.g., 08:00 - 09:30</label>
                            <input type="text" id="waktu_sesi" name="waktu_sesi" x-model="sesiWaktu" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm" placeholder="Contoh: 08:00 - 09:30">
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-4">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>