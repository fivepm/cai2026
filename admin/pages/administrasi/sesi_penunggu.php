<?php
// Izinkan akses hanya untuk superadmin dan admin
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../login");
    exit();
}
// Proses form saat ada data yang dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'tambah') {
        $nama_sesi = $_POST['nama_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi']; // Ambil data tanggal
        $stmt = $conn->prepare("INSERT INTO sesi_penunggu (nama_sesi, waktu_sesi, tanggal_sesi) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_sesi, $waktu_sesi, $tanggal_sesi);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'update') {
        $sesi_id = $_POST['sesi_id'];
        $nama_sesi = $_POST['nama_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi']; // Ambil data tanggal
        $jumlah_penunggu = $_POST['jumlah_penunggu'];
        $nama_penunggu = $_POST['nama_penunggu'] ?? [];
        $nama_penunggu_json = json_encode(array_values($nama_penunggu));

        $stmt = $conn->prepare("UPDATE sesi_penunggu SET nama_sesi = ?, waktu_sesi = ?, tanggal_sesi = ?, jumlah_penunggu = ?, nama_penunggu = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $nama_sesi, $waktu_sesi, $tanggal_sesi, $jumlah_penunggu, $nama_penunggu_json, $sesi_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'hapus') {
        $sesi_id = $_POST['sesi_id'];
        $stmt = $conn->prepare("DELETE FROM sesi_penunggu WHERE id = ?");
        $stmt->bind_param("i", $sesi_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin.php?page=administrasi/sesi_penunggu");
    exit();
}

// Ambil semua data sesi dari database
$sesi_list = $conn->query("SELECT * FROM sesi_penunggu ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Mulai HTML Konten -->
<div x-data="{ isAddModalOpen: false, isDeleteModalOpen: false, deleteSesiId: null, deleteSesiNama: '', isViewModalOpen: false, viewSesiData: {} }">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-800">Daftar Sesi Penunggu</h1>
        <button @click="isAddModalOpen = true" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center">
            <i class="fas fa-plus mr-2"></i>Tambah Sesi Baru
        </button>
    </div>

    <!-- Grid untuk menampilkan kartu sesi -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php foreach ($sesi_list as $sesi): ?>
            <?php
            $penunggu_array = json_decode($sesi['nama_penunggu'] ?? '[]', true);
            $tanggal_formatted = !empty($sesi['tanggal_sesi']) ? date('d F Y', strtotime($sesi['tanggal_sesi'])) : 'Tanggal belum diatur';
            ?>
            <!-- Kartu Sesi (Alpine.js Component) -->
            <div x-data='{
                    jumlah: <?php echo $sesi['jumlah_penunggu']; ?>,
                    penunggu: <?php echo json_encode($penunggu_array); ?>
                }'
                class="bg-white p-6 rounded-xl shadow-md">

                <form method="POST" action="admin.php?page=administrasi/sesi_penunggu" class="space-y-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="sesi_id" value="<?php echo $sesi['id']; ?>">

                    <div class="flex justify-between items-start">
                        <div class="flex-grow">
                            <label class="block text-xs font-medium text-gray-500">Nama Sesi</label>
                            <input type="text" name="nama_sesi" value="<?php echo htmlspecialchars($sesi['nama_sesi']); ?>" class="text-xl font-bold text-gray-800 border-0 border-b-2 border-transparent focus:border-red-500 focus:ring-0 p-0 w-full">
                        </div>
                        <div class="flex items-center space-x-3">
                            <button type="button" @click="isViewModalOpen = true; viewSesiData = { nama: '<?php echo htmlspecialchars($sesi['nama_sesi'], ENT_QUOTES); ?>', tanggal: '<?php echo $tanggal_formatted; ?>', waktu: '<?php echo htmlspecialchars($sesi['waktu_sesi'], ENT_QUOTES); ?>', penunggu: penunggu };" class="text-gray-400 hover:text-blue-600"><i class="fas fa-eye"></i></button>
                            <button type="button" @click="isDeleteModalOpen = true; deleteSesiId = <?php echo $sesi['id']; ?>; deleteSesiNama = '<?php echo htmlspecialchars($sesi['nama_sesi'], ENT_QUOTES); ?>';" class="text-gray-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Sesi</label>
                            <input type="date" name="tanggal_sesi" value="<?php echo htmlspecialchars($sesi['tanggal_sesi']); ?>" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Waktu Sesi</label>
                            <input type="text" name="waktu_sesi" value="<?php echo htmlspecialchars($sesi['waktu_sesi']); ?>" placeholder="cth: 08:00 - 09:00" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jumlah Penunggu</label>
                        <input type="number" name="jumlah_penunggu" x-model.number="jumlah" min="0" max="10" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Nama Penunggu</label>
                        <template x-for="i in Array.from({ length: jumlah }, (_, i) => i)">
                            <input type="text" name="nama_penunggu[]" x-model="penunggu[i]" :placeholder="'Penunggu ' + (i + 1)" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        </template>
                    </div>

                    <div class="pt-2 text-right">
                        <button type="submit" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Simpan</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Tambah Sesi -->
    <div x-show="isAddModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isAddModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4">Tambah Sesi Baru</h3>
            <form method="POST" action="admin.php?page=administrasi/sesi_penunggu" class="space-y-4">
                <input type="hidden" name="action" value="tambah">
                <div><label for="nama_sesi" class="block text-sm font-medium">Nama Sesi</label><input type="text" id="nama_sesi" name="nama_sesi" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                <div><label for="tanggal_sesi" class="block text-sm font-medium">Tanggal Sesi</label><input type="date" id="tanggal_sesi" name="tanggal_sesi" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                <div><label for="waktu_sesi" class="block text-sm font-medium">Waktu Sesi</label><input type="text" id="waktu_sesi" name="waktu_sesi" placeholder="cth: 08:00 - 09:00" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isAddModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Tambah</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
            <h3 class="text-xl font-bold">Konfirmasi Hapus</h3>
            <p class="mt-2">Yakin ingin menghapus sesi <strong x-text="deleteSesiNama"></strong>?</p>
            <form method="POST" action="admin.php?page=administrasi/sesi_penunggu" class="mt-6 flex justify-end space-x-4">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="sesi_id" :value="deleteSesiId">
                <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Ya, Hapus</button>
            </form>
        </div>
    </div>

    <!-- Modal Tampilan Sesi -->
    <div x-show="isViewModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isViewModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
            <img src="../uploads/bg_invoice.png" alt="Logo Acara" class="mx-auto h-10 w-auto">
            <h3 class="text-2xl font-bold text-center text-gray-800">JADWAL PENUNGGU CAI</h3>
            <h3 class="text-2xl font-bold text-center text-gray-800" x-text="viewSesiData.nama"></h3>
            <p class="text-center text-gray-500" x-text="'Tanggal : ' + viewSesiData.tanggal"></p>
            <p class="text-center text-gray-500" x-text="'Waktu : ' + viewSesiData.waktu"></p>
            <div class="mt-6 border-t pt-4">
                <h4 class="font-semibold text-lg text-center mb-2">Daftar Penunggu</h4>
                <ul class="list-decimal list-inside text-center space-y-1">
                    <template x-for="penunggu in viewSesiData.penunggu">
                        <li x-text="penunggu || '- Kosong -'"></li>
                    </template>
                </ul>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" @click="isViewModalOpen = false" class="px-4 py-2 bg-red-600 text-white rounded-md">Tutup</button>
            </div>
        </div>
    </div>
</div>