<?php
// Izinkan akses hanya untuk superadmin dan bendahara (asumsi role 'bendahara' sudah ada)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../login");
    exit();
}

$upload_dir = '../uploads/nota_keuangan/';

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;

    // Logika untuk Hapus
    if ($action === 'delete' && $id) {
        // Ambil nama file nota sebelum menghapus
        $stmt_select = $conn->prepare("SELECT nota FROM log_keuangan WHERE id = ?");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $file_to_delete = $stmt_select->get_result()->fetch_assoc()['nota'];
        $stmt_select->close();

        // Hapus file dari server jika ada
        if ($file_to_delete && file_exists($upload_dir . $file_to_delete)) {
            unlink($upload_dir . $file_to_delete);
        }

        $stmt = $conn->prepare("DELETE FROM log_keuangan WHERE id=?");
        $stmt->bind_param("i", $id);
    } else { // Logika untuk Tambah & Edit
        $tanggal = $_POST['tanggal'];
        $keterangan = $_POST['keterangan'];
        $jenis = $_POST['jenis'];
        $jumlah = $_POST['jumlah'];
        $sumber_pemasukan = ($jenis === 'masuk') ? $_POST['sumber_pemasukan'] : null;
        $divisi_pengeluaran = ($jenis === 'keluar') ? $_POST['divisi_pengeluaran'] : null;
        $nama_nota = $_POST['nota_lama'] ?? null; // Ambil nama file lama saat edit

        // Proses upload file baru jika ada
        if (isset($_FILES['nota']) && $_FILES['nota']['error'] == 0) {
            // Hapus file lama jika ada file baru yang diupload saat edit
            if ($action === 'edit' && $nama_nota && file_exists($upload_dir . $nama_nota)) {
                unlink($upload_dir . $nama_nota);
            }
            $file = $_FILES['nota'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nama_nota = "nota_" . uniqid('', true) . '.' . $file_ext;
            move_uploaded_file($file['tmp_name'], $upload_dir . $nama_nota);
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO log_keuangan (tanggal, keterangan, jenis, sumber_pemasukan, divisi_pengeluaran, nota, jumlah) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssd", $tanggal, $keterangan, $jenis, $sumber_pemasukan, $divisi_pengeluaran, $nama_nota, $jumlah);
        } elseif ($action === 'edit' && $id) {
            $stmt = $conn->prepare("UPDATE log_keuangan SET tanggal=?, keterangan=?, jenis=?, sumber_pemasukan=?, divisi_pengeluaran=?, nota=?, jumlah=? WHERE id=?");
            $stmt->bind_param("ssssssdi", $tanggal, $keterangan, $jenis, $sumber_pemasukan, $divisi_pengeluaran, $nama_nota, $jumlah, $id);
        }
    }

    if (isset($stmt)) {
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin?page=keuangan/log_keuangan");
    exit();
}

$log_list = $conn->query("SELECT * FROM log_keuangan ORDER BY tanggal DESC, id DESC")->fetch_all(MYSQLI_ASSOC);

$role_user = $_SESSION['user_role'];
?>

<div x-data="{ 
        sidebarOpen: false, 
        isModalOpen: false, 
        modalTitle: '', 
        formAction: 'add', 
        logData: { jenis: 'masuk' }, 
        isNotaModalOpen: false, 
        notaUrl: '', 
        isDeleteModalOpen: false, 
        deleteLogData: {} 
        }"
    @keydown.escape.window="isNotaModalOpen = false; isDeleteModalOpen = false"
    class="flex h-screen bg-gray-200">

    <!-- Konten Utama -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-gray-100">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-semibold text-gray-800">
                    Log Keuangan
                </h1>
                <?php
                if ($role_user == 'superadmin') {
                ?>
                    <button @click="isModalOpen = true; modalTitle = 'Tambah Transaksi'; formAction = 'add'; logData = {tanggal: new Date().toISOString().slice(0,10), jenis: 'masuk'};" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">
                        <i class="fas fa-plus mr-2"></i>
                        Tambah Transaksi
                    </button>
                <?php
                }
                ?>
            </div>
            <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead class="bg-gray-200">
                            <tr class="text-left font-bold">
                                <th class="px-6 py-3">Tanggal</th>
                                <th class="px-6 py-3">Keterangan</th>
                                <th class="px-6 py-3">Sumber/Divisi</th>
                                <th class="px-6 py-3">Nota</th>
                                <th class="px-6 py-3">Jumlah</th>
                                <?php
                                if ($role_user == 'superadmin') {
                                ?>
                                    <th class="px-6 py-3">Aksi</th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($log_list as $log): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <?php echo date('d M Y', strtotime($log['tanggal'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($log['keterangan']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $log['jenis'] == 'masuk' ? 'bg-green-500' : 'bg-red-500'; ?>">
                                            <?php echo $log['jenis'] == 'masuk' ? ucfirst($log['sumber_pemasukan']) : ucfirst($log['divisi_pengeluaran']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($log['nota']): ?>
                                            <button @click="isNotaModalOpen = true; notaUrl = '<?php echo $upload_dir . $log['nota']; ?>'" class="text-blue-600 hover:underline">
                                                <i class="fas fa-receipt"></i>
                                            </button>
                                        <?php else: echo '-';
                                        endif; ?>
                                    </td>
                                    <td class="px-6 py-4">Rp <?php echo number_format($log['jumlah'], 0, ',', '.'); ?></td>
                                    <?php
                                    if ($role_user == 'superadmin') {
                                    ?>
                                        <td class="px-6 py-4">
                                            <?php
                                            if ($log['sumber_pemasukan'] != 'peserta') {
                                            ?>
                                                <button @click="isModalOpen = true; modalTitle = 'Edit Transaksi'; formAction = 'edit'; logData = <?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>;" class="text-indigo-600 hover:text-indigo-800 mr-3">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button @click="isDeleteModalOpen = true; deleteLogData = <?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>;" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php
                                            }
                                            ?>
                                        </td>
                                    <?php
                                    }
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4" x-text="modalTitle"></h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" :value="formAction">
                <input type="hidden" name="id" :value="logData.id">
                <input type="hidden" name="nota_lama" :value="logData.nota">
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium">Tanggal</label><input type="date" name="tanggal" x-model="logData.tanggal" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div>
                    <div><label class="block text-sm font-medium">Keterangan</label><input type="text" name="keterangan" x-model="logData.keterangan" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div>
                    <div><label class="block text-sm font-medium">Jenis</label><select name="jenis" x-model="logData.jenis" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="masuk">Masuk</option>
                            <option value="keluar">Keluar</option>
                        </select></div>
                    <div x-show="logData.jenis === 'masuk'">
                        <label class="block text-sm font-medium">Sumber Pemasukan</label>
                        <select name="sumber_pemasukan" x-model="logData.sumber_pemasukan" :required="logData.jenis === 'masuk'" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="peserta">Peserta</option>
                            <option value="kas">Kas</option>
                            <option value="anggaran desa">Anggaran Desa</option>
                        </select>
                    </div>
                    <div x-show="logData.jenis === 'keluar'">
                        <label class="block text-sm font-medium">Divisi Pengeluaran</label>
                        <select name="divisi_pengeluaran" x-model="logData.divisi_pengeluaran" :required="logData.jenis === 'keluar'" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="Acara">Acara</option>
                            <option value="Konsumsi">Konsumsi</option>
                            <option value="PDD">PDD</option>
                            <option value="Kesehatan">Kesehatan</option>
                            <option value="Perlengkapan">Perlengkapan</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium">Jumlah (Rp)</label><input type="number" step="100" name="jumlah" x-model="logData.jumlah" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div>
                    <div><label class="block text-sm font-medium">Upload Nota (Opsional)</label><input type="file" name="nota" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                        <div x-show="logData.nota"><span class="text-xs text-gray-500">File saat ini: <a :href="`<?php echo $upload_dir; ?>${logData.nota}`" target="_blank" class="text-blue-500" x-text="logData.nota"></a></span></div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Simpan</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Lihat Nota -->
    <div x-show="isNotaModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isNotaModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xl p-4 mx-4 relative"><button @click="isNotaModalOpen = false" class="absolute -top-3 -right-3 bg-red-600 text-white rounded-full h-8 w-8 flex items-center justify-center">&times;</button><img :src="notaUrl" alt="Tampilan Nota" class="w-full h-auto max-h-[80vh] object-contain"></div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
            <h3 class="text-xl font-bold">Konfirmasi Hapus</h3>
            <p class="mt-2">Apakah Anda yakin ingin menghapus transaksi: <strong x-text="deleteLogData.keterangan"></strong>?</p>
            <form method="POST" action="" class="mt-6 flex justify-end space-x-4">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" :value="deleteLogData.id">
                <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Ya, Hapus</button>
            </form>
        </div>
    </div>
</div>