<?php
// Izinkan akses hanya untuk superadmin dan bendahara (asumsi role 'bendahara' sudah ada)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin'])) {
    header("Location: ../../login");
    exit();
}

// Proses Aksi (Tambah, Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;

    if ($action === 'add' || $action === 'edit') {
        $nama = $_POST['nama'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $role = $_POST['role'];

        if ($action === 'add') {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $kode_barcode = 'QR-' . strtoupper(str_replace(' ', '_', $role)) . '-' . bin2hex(random_bytes(10));
            $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role, kode_barcode) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nama, $username, $hashed_password, $role, $kode_barcode);
        } else { // Edit
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $nama, $username, $hashed_password, $role, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nama, $username, $role, $id);
            }
        }
    } elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'superadmin'");
        $stmt->bind_param("i", $id);
    }

    if (isset($stmt)) {
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin.php?page=master/manajemen_admin"); // Redirect kembali ke halaman yang benar
    exit();
}

// Ambil data staf termasuk kode_barcode
$staf_list = $conn->query("SELECT id, nama, username, role, kode_barcode FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Mulai HTML Konten -->
<div x-data="{ 
        isModalOpen: false, modalTitle: '', formAction: 'add', stafData: { role: 'admin' },
        isQrCodeModalOpen: false, qrCodeValue: '', qrCodeName: '',

        showQrCode(value, name) {
            this.isQrCodeModalOpen = true;
            this.qrCodeValue = value;
            this.qrCodeName = name;
            this.$nextTick(() => {
                const qrElement = document.getElementById('qrcode-display');
                qrElement.innerHTML = ''; // Kosongkan div
                new QRCode(qrElement, {
                    text: value,
                    width: 200,
                    height: 200,
                });
            });
        },
        
        downloadQrCode(value, filename) {
            const tempDiv = document.createElement('div');
            tempDiv.style.display = 'none';
            document.body.appendChild(tempDiv);
            new QRCode(tempDiv, { text: value, width: 256, height: 256 });
            setTimeout(() => {
                const qrImage = tempDiv.querySelector('img');
                if (qrImage) {
                    const link = document.createElement('a');
                    link.href = qrImage.src;
                    link.download = filename;
                    link.click();
                }
                document.body.removeChild(tempDiv);
            }, 100);
        }
    }"
    @keydown.escape.window="isModalOpen = false; isQrCodeModalOpen = false">

    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-800">Manajemen Staf</h1>
        <button @click="isModalOpen = true; modalTitle = 'Tambah Staf Baru'; formAction = 'add'; stafData = { role: 'admin' };" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700"><i class="fas fa-plus mr-2"></i>Tambah Staf</button>
    </div>

    <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="bg-gray-200">
                    <tr class="text-left font-bold">
                        <th class="px-6 py-3">No</th>
                        <th class="px-6 py-3">Nama</th>
                        <th class="px-6 py-3">Username</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">QR Code</th>
                        <th class="px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    foreach ($staf_list as $staf): ?>
                        <tr class="hover:bg-yellow-300">
                            <td class="px-6 py-4"><?php echo $no++; ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($staf['nama']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($staf['username']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $staf['role']))); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <button @click="showQrCode('<?php echo htmlspecialchars($staf['kode_barcode']); ?>', '<?php echo htmlspecialchars($staf['nama'], ENT_QUOTES); ?>')" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 mr-2"><i class="fas fa-qrcode"></i></button>
                                <button @click="downloadQrCode('<?php echo htmlspecialchars($staf['kode_barcode']); ?>', 'qrcode-<?php echo htmlspecialchars($staf['username']); ?>.png')" class="px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600"><i class="fas fa-download"></i></button>
                            </td>
                            <td class="px-6 py-4">
                                <button @click="isModalOpen = true; modalTitle = 'Edit Staf'; formAction = 'edit'; stafData = <?php echo htmlspecialchars(json_encode($staf), ENT_QUOTES, 'UTF-8'); ?>;" class="text-indigo-600 hover:text-indigo-800 mr-3"><i class="fas fa-pencil-alt"></i></button>
                                <form method="POST" action="admin.php?page=master/manajemen_admin" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus staf ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $staf['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah/Edit Staf -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4" x-text="modalTitle"></h3>
            <form method="POST" action="admin.php?page=master/manajemen_admin">
                <input type="hidden" name="action" :value="formAction"><input type="hidden" name="id" :value="stafData.id">
                <div class="space-y-4">
                    <div><label class="block text-sm">Nama</label><input type="text" name="nama" x-model="stafData.nama" required class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label class="block text-sm">Username</label><input type="text" name="username" x-model="stafData.username" required class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label class="block text-sm">Role</label><select name="role" x-model="stafData.role" required class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="admin">Admin</option>
                            <option value="pembina">Pembina</option>
                            <option value="sekretaris">Sekretaris</option>
                            <option value="bendahara">Bendahara</option>
                            <option value="sie_acara">Divisi Acara</option>
                            <option value="sie_pdd">Divisi PDD</option>
                            <option value="ketua kmm bintaran">Ketua KMM Bintaran</option>
                            <option value="ketua kmm gedongkuning">Ketua KMM Gedongkuning</option>
                            <option value="ketua kmm jombor">Ketua KMM Jombor</option>
                            <option value="ketua kmm sunten">Ketua KMM Sunten</option>
                            <option value="panitia">Panitia</option>
                        </select></div>
                    <div><label class="block text-sm">Password</label><input type="password" name="password" :placeholder="formAction === 'edit' ? 'Kosongkan jika tidak diubah' : ''" class="mt-1 w-full border-gray-300 rounded-md"></div>
                </div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Simpan</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Lihat QR Code -->
    <div x-show="isQrCodeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isQrCodeModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xs p-6 mx-4 text-center">
            <h3 class="text-xl font-bold" x-text="`QR Code untuk ${qrCodeName}`"></h3>
            <div class="my-4 p-4 bg-white">
                <div id="qrcode-display" class="flex justify-center"></div>
            </div>
            <button type="button" @click="isQrCodeModalOpen = false" class="mt-6 w-full px-4 py-2 bg-red-600 text-white rounded-md">Tutup</button>
        </div>
    </div>
</div>

<!-- Tambahkan script qrcode.js jika belum ada di layout utama -->
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>