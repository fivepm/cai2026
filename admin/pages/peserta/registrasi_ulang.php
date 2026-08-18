<?php
// (File: pages/peserta/registrasi_ulang.php)

// ===================================================================
// BAGIAN LOGIKA PHP
// ===================================================================
// Cek jika ada aksi dari form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $peserta_id = $_POST['peserta_id'];

    if ($_POST['action'] === 'update_registrasi') {
        // Ambil data dari form
        $status_pembayaran = $_POST['status_pembayaran'];
        $ambil_totebag = isset($_POST['ambil_totebag']) ? 'ya' : 'tidak';
        $ambil_idcard = isset($_POST['ambil_idcard']) ? 'ya' : 'tidak';
        $dibayar_pada = ($status_pembayaran === 'lunas' && empty($_POST['dibayar_pada_hidden'])) ? date('Y-m-d H:i:s') : $_POST['dibayar_pada_hidden'];

        // Update data peserta
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = ?, terima_totebag = ?, terima_idcard = ?, dibayar_pada = ? WHERE id = ?");
        // Jika dibayar_pada kosong, kirim NULL
        $tanggal_bayar_db = !empty($dibayar_pada) ? $dibayar_pada : null;
        $stmt_update->bind_param("ssssi", $status_pembayaran, $ambil_totebag, $ambil_idcard, $tanggal_bayar_db, $peserta_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data registrasi berhasil diperbarui.'];

            // Logika otomatisasi ke log_keuangan
            $stmt_log_check = $conn->prepare("SELECT id FROM log_keuangan WHERE keterangan LIKE ?");
            $keterangan_log = "Pembayaran dari Peserta ID: " . $peserta_id;
            $stmt_log_check->bind_param("s", $keterangan_log);
            $stmt_log_check->execute();
            $log_exists = $stmt_log_check->get_result()->fetch_assoc();
            $stmt_log_check->close();

            if ($status_pembayaran === 'lunas' && !$log_exists) {
                // Tambahkan ke log keuangan jika belum ada
                $jumlah = 50000; // Asumsi biaya pendaftaran
                $jenis = 'masuk';
                $sumber = 'Peserta';
                $stmt_log_add = $conn->prepare("INSERT INTO log_keuangan (tanggal, jumlah, jenis, keterangan, sumber_pemasukan) VALUES (NOW(), ?, ?, ?, ?)");
                $stmt_log_add->bind_param("dsss", $jumlah, $jenis, $keterangan_log, $sumber);
                $stmt_log_add->execute();
                $stmt_log_add->close();
            } elseif ($status_pembayaran !== 'lunas' && $log_exists) {
                // Hapus dari log keuangan jika ada dan status diubah jadi belum lunas
                $stmt_log_delete = $conn->prepare("DELETE FROM log_keuangan WHERE id = ?");
                $stmt_log_delete->bind_param("i", $log_exists['id']);
                $stmt_log_delete->execute();
                $stmt_log_delete->close();
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memperbarui data registrasi.'];
        }
        $stmt_update->close();
    }

    header("Location: admin?page=peserta/registrasi_ulang");
    exit();
}

// Logika Filter dan Pencarian
$search_query = $_GET['search'] ?? '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_clause = "WHERE nama LIKE ? OR kelompok LIKE ?";
    $search_param = '%' . $search_query . '%';
    $params = [$search_param, $search_param];
    $types = 'ss';
}

// Ambil semua data peserta
$peserta_list = [];
$sql = "SELECT * FROM peserta $where_clause ORDER BY nama ASC";
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

<div class="p-6 bg-white rounded-lg shadow-md"
    x-data="{
        isModalOpen: false,
        peserta: {},
        openModal(data) {
            this.peserta = data;
            this.isModalOpen = true;
        }
    }">

    <h1 class="text-3xl font-semibold text-gray-800">Registrasi Ulang Peserta</h1>
    <p class="mt-2 text-gray-600">Gunakan halaman ini untuk memvalidasi pembayaran dan mencatat pengambilan item oleh peserta.</p>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="mt-4 p-4 rounded-md <?php echo $_SESSION['message']['type'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Form Pencarian -->
    <div class="mt-6">
        <form method="GET">
            <input type="hidden" name="page" value="peserta/registrasi_ulang">
            <div class="flex">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Cari nama atau kelompok..." class="w-full px-4 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-red-500">
                <button type="submit" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-r-md hover:bg-red-700">Cari</button>
            </div>
        </form>
    </div>

    <!-- Tabel Peserta -->
    <div class="mt-6 overflow-x-auto">
        <table class="w-full whitespace-nowrap">
            <thead class="bg-gray-100">
                <tr class="text-left font-semibold">
                    <th class="px-6 py-3">Nama</th>
                    <th class="px-6 py-3">Kelompok</th>
                    <th class="px-6 py-3 text-center">Pembayaran</th>
                    <th class="px-6 py-3 text-center">Totebag</th>
                    <th class="px-6 py-3 text-center">ID Card</th>
                    <th class="px-6 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($peserta_list)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Tidak ada peserta yang cocok dengan pencarian Anda.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($peserta_list as $p): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($p['kelompok']); ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($p['status_pembayaran'] == 'lunas'): ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Lunas</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($p['terima_totebag'] == 'ya'): ?>
                                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-gray-400 text-lg"></i>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($p['terima_idcard'] == 'ya'): ?>
                                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-gray-400 text-lg"></i>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button @click="openModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                                    Kelola
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Kelola Registrasi -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" @keydown.escape.window="isModalOpen = false" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-2">Kelola Registrasi</h3>
            <p class="text-lg text-gray-700 mb-6" x-text="peserta.nama"></p>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                <input type="hidden" name="action" value="update_registrasi">
                <input type="hidden" name="peserta_id" :value="peserta.id">
                <input type="hidden" name="dibayar_pada_hidden" :value="peserta.dibayar_pada">

                <div class="space-y-6">
                    <!-- Status Pembayaran -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Pembayaran</label>
                        <select name="status_pembayaran" :value="peserta.status_pembayaran" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="belum_diverifikasi">Belum Lunas</option>
                            <option value="lunas">Lunas</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1" x-show="peserta.dibayar_pada">
                            Lunas pada: <span x-text="new Date(peserta.dibayar_pada).toLocaleString('id-ID')"></span>
                        </p>
                    </div>

                    <!-- Checklist Item -->
                    <div class="border-t pt-4">
                        <p class="block text-sm font-medium text-gray-700 mb-3">Item Diterima</p>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="ambil_totebag" value="ya" :checked="peserta.terima_totebag === 'ya'" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <span class="ml-3 text-gray-700">Sudah Menerima Totebag</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="ambil_idcard" value="ya" :checked="peserta.terima_idcard === 'ya'" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <span class="ml-3 text-gray-700">Sudah Menerima ID Card</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-4">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>