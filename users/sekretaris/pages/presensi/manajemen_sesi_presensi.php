<?php
// (File: pages/presensi/manajemen_sesi_presensi.php)

// Memaksa PHP untuk menampilkan error (berguna untuk debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Logika untuk menangani form action (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'tambah') {
        $nama_sesi = $_POST['nama_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];

        $conn->begin_transaction();
        try {
            $stmt_sesi = $conn->prepare("INSERT INTO sesi_presensi (nama_sesi, tanggal_sesi, waktu_sesi) VALUES (?, ?, ?)");
            $stmt_sesi->bind_param("sss", $nama_sesi, $tanggal_sesi, $waktu_sesi);
            $stmt_sesi->execute();
            $id_sesi_baru = $stmt_sesi->insert_id;
            $stmt_sesi->close();

            $peserta_ids = [];
            $result_peserta = $conn->query("SELECT id FROM peserta");
            while ($row = $result_peserta->fetch_assoc()) {
                $peserta_ids[] = $row['id'];
            }

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
            $stmt_log = $conn->prepare("DELETE FROM log_presensi WHERE id_sesi = ?");
            $stmt_log->bind_param("i", $id_sesi);
            $stmt_log->execute();
            $stmt_log->close();

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

    header("Location: sekretaris?page=presensi/manajemen_sesi_presensi");
    exit();
}

$sesi = $conn->query("SELECT * FROM sesi_presensi ORDER BY tanggal_sesi, waktu_sesi")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<script>
    function manajemenSesi() {
        return {
            isModalOpen: false,
            isDeleteModalOpen: false,
            isEdit: false,
            modalTitle: '',
            sesiId: null,
            sesiNama: '',
            sesiTanggal: '',
            sesiWaktu: '',
            deleteId: null,
            deleteNama: '',

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
            },

            confirmDelete(id, nama) {
                this.deleteId = id;
                this.deleteNama = nama;
                this.isDeleteModalOpen = true;
            }
        }
    }
</script>

<div x-data="manajemenSesi()">

    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manajemen Sesi Presensi</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola sesi dan jadwal presensi peserta</p>
        </div>
        <button @click="openTambahModal()" class="px-5 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 flex items-center gap-2 shadow-md transition-all">
            <i class="fas fa-plus text-sm"></i> Tambah Sesi Baru
        </button>
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="mt-4 p-4 rounded-lg flex items-center gap-3 <?php echo $_SESSION['message']['type'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
            <i class="fas <?php echo $_SESSION['message']['type'] == 'success' ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-500'; ?>"></i>
            <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
        </div>
    <?php unset($_SESSION['message']);
    endif; ?>

    <!-- Tabel Sesi -->
    <div class="mt-5 bg-white shadow-md rounded-xl overflow-hidden">
        <!-- Card Header -->
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-calendar-check text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Daftar Sesi Presensi</span>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-semibold">
                        <th class="px-6 py-3">Nama Sesi</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($sesi)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                                <i class="fas fa-calendar-xmark text-3xl mb-2 block"></i>
                                Belum ada sesi yang dibuat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sesi as $item): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($item['nama_sesi']); ?></td>
                                <td class="px-6 py-4 text-gray-600">
                                    <i class="fas fa-calendar-days text-blue-400 mr-1"></i>
                                    <?php echo date('d M Y', strtotime($item['tanggal_sesi'])); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <i class="fas fa-clock text-blue-400 mr-1"></i>
                                    <?php echo htmlspecialchars($item['waktu_sesi']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <button @click="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)"
                                                class="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                            <i class="fas fa-pen text-xs"></i> Edit
                                        </button>
                                        <button @click="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nama_sesi'], ENT_QUOTES); ?>')"
                                                class="flex items-center gap-1 text-red-500 hover:text-red-700 font-medium transition-colors">
                                            <i class="fas fa-trash-alt text-xs"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="block md:hidden border-t border-gray-100 divide-y divide-gray-100">
            <?php foreach ($sesi as $item): ?>
            <div class="p-4 bg-white hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($item['nama_sesi']); ?></h3>
                </div>
                <p class="text-sm text-gray-600 mb-1"><i class="fas fa-calendar-days mr-1.5 text-blue-400"></i><?php echo date('d M Y', strtotime($item['tanggal_sesi'])); ?></p>
                <p class="text-sm text-gray-600 mb-3"><i class="fas fa-clock mr-1.5 text-blue-400"></i><?php echo htmlspecialchars($item['waktu_sesi']); ?></p>
                <div class="flex gap-2 pt-3 border-t border-gray-100">
                    <button @click="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)"
                            class="flex-1 flex justify-center items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg font-semibold text-xs hover:bg-blue-100 transition-colors">
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <button @click="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['nama_sesi'], ENT_QUOTES); ?>')"
                            class="flex-none flex justify-center items-center px-3 py-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash-alt text-sm"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($sesi)): ?>
            <div class="p-8 text-center text-gray-400"><i class="fas fa-calendar-xmark text-3xl mb-2 block"></i>Belum ada sesi yang dibuat</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah/Edit Sesi -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2">
                        <i class="fas fa-calendar-plus text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white" x-text="modalTitle"></h3>
                        <p class="text-blue-100 text-xs">Isi data sesi presensi</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-white hover:text-blue-200 text-xl leading-none">&times;</button>
            </div>
            <!-- Modal Body -->
            <div class="p-6">
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="id_sesi" x-model="sesiId">
                    <input type="hidden" name="action" :value="isEdit ? 'edit' : 'tambah'">

                    <div>
                        <label for="nama_sesi" class="block text-sm font-semibold text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1 text-blue-500"></i>Nama Sesi
                        </label>
                        <input type="text" id="nama_sesi" name="nama_sesi" x-model="sesiNama" required
                               placeholder="Contoh: Sesi Pagi - Hari 1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label for="tanggal_sesi" class="block text-sm font-semibold text-gray-700 mb-1">
                            <i class="fas fa-calendar-days mr-1 text-blue-500"></i>Tanggal Sesi
                        </label>
                        <input type="date" id="tanggal_sesi" name="tanggal_sesi" x-model="sesiTanggal" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label for="waktu_sesi" class="block text-sm font-semibold text-gray-700 mb-1">
                            <i class="fas fa-clock mr-1 text-blue-500"></i>Waktu Sesi
                        </label>
                        <input type="text" id="waktu_sesi" name="waktu_sesi" x-model="sesiWaktu" required
                               placeholder="Contoh: 08:00 - 09:30"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="isModalOpen = false"
                                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold transition-colors shadow-sm">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-lg p-2">
                    <i class="fas fa-triangle-exclamation text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-white">Konfirmasi Hapus</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-700 text-sm mb-1">Anda akan menghapus sesi:</p>
                <p class="font-bold text-gray-900 mb-3" x-text="deleteNama"></p>
                <p class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg p-3">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    Menghapus sesi ini akan menghapus <strong>semua data kehadiran</strong> yang terkait. Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="mt-5 flex gap-3">
                    <button @click="isDeleteModalOpen = false"
                            class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">
                        Batal
                    </button>
                    <form method="POST" action="" class="flex-1">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_sesi" :value="deleteId">
                        <button type="submit"
                                class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-semibold transition-colors">
                            <i class="fas fa-trash-alt mr-1"></i> Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>