<?php
// Izinkan akses hanya untuk sekretaris
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}

$upload_dir = '../../uploads/bukti_pembayaran/';
// Proses Aksi (Edit, Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $peserta_id = $_POST['peserta_id'] ?? null;

    if ($action === 'edit' && $peserta_id) {
        $nama = $_POST['nama'];
        $kelompok = $_POST['kelompok'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $metode_pembayaran = $_POST['metode_pembayaran'];
        $status_pembayaran = $_POST['status_pembayaran'];
        $ukuran_jersey = $_POST['ukuran_jersey'] ?? null;

        $stmt = $conn->prepare("UPDATE peserta SET nama=?, kelompok=?, jenis_kelamin=?, metode_pembayaran=?, status_pembayaran=?, ukuran_jersey=? WHERE id=?");
        $stmt->bind_param("ssssssi", $nama, $kelompok, $jenis_kelamin, $metode_pembayaran, $status_pembayaran, $ukuran_jersey, $peserta_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data peserta berhasil diubah.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal mengubah data peserta.'];
        }
        $stmt->close();
    }

    if ($action === 'delete' && $peserta_id) {
        $stmt = $conn->prepare("DELETE FROM peserta WHERE id = ?");
        $stmt->bind_param("i", $peserta_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Data peserta berhasil dihapus.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menghapus data peserta.'];
        }
        $stmt->close();
    }

    header("Location: sekretaris?page=peserta/manajemen_peserta");
    exit();
}


// Logika filter & pencarian
$filter_kelompok = $_GET['kelompok'] ?? '';
$search_nama = $_GET['search'] ?? '';
$sql = "SELECT * FROM peserta WHERE 1=1";
$params = [];
$types = '';
if (!empty($filter_kelompok)) {
    $sql .= " AND kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= 's';
}
if (!empty($search_nama)) {
    $sql .= " AND nama LIKE ?";
    $params[] = '%' . $search_nama . '%';
    $types .= 's';
}
$sql .= " ORDER BY kelompok, nama";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$peserta_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Logika untuk ringkasan detail
$summary_data = [];
$kelompok_list = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];
$grand_total = ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0];
foreach ($kelompok_list as $kelompok) {
    $summary_data[$kelompok] = ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0];
}
$summary_sql = "SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta GROUP BY kelompok, jenis_kelamin";
$summary_result = $conn->query($summary_sql);
if ($summary_result) {
    while ($row = $summary_result->fetch_assoc()) {
        if (isset($summary_data[$row['kelompok']])) {
            $summary_data[$row['kelompok']][$row['jenis_kelamin']] = (int)$row['jumlah'];
            $summary_data[$row['kelompok']]['total'] += (int)$row['jumlah'];
            $grand_total[$row['jenis_kelamin']] += (int)$row['jumlah'];
            $grand_total['total'] += (int)$row['jumlah'];
        }
    }
}

$role_user = $_SESSION['user_role'];
?>

<!-- Mulai HTML Konten -->
<?php if (isset($_SESSION['message'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $_SESSION['message']['type']; ?>',
                title: '<?php echo $_SESSION['message']['type'] == 'success' ? 'Berhasil!' : 'Gagal!'; ?>',
                text: '<?php echo htmlspecialchars($_SESSION['message']['text'], ENT_QUOTES, 'UTF-8'); ?>',
                showConfirmButton: false,
                timer: 2000
            });
        });
    </script>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div x-data="{ 
        isFilterModalOpen: false, 
        isDetailModalOpen: false,
        isQrCodeModalOpen: false, qrCodeValue: '', qrCodeName: '',
        isDownloading: false,
        isEditModalOpen: false, editData: {},
        isDeleteModalOpen: false, deleteData: {},
        isBuktiModalOpen: false, buktiUrl: '',

        showQrCode(value, name) {
            this.isQrCodeModalOpen = true;
            this.qrCodeValue = value;
            this.qrCodeName = name;
            this.$nextTick(() => {
                const qrElement = document.getElementById('qrcode-display');
                qrElement.innerHTML = ''; // Membersihkan QR code sebelumnya
                new QRCode(qrElement, {
                    text: value,
                    width: 200,
                    height: 200,
                });
            });
        },
        downloadAllQrCodes(participants) {
            if (participants.length === 0) { alert('Tidak ada data peserta untuk diunduh.'); return; }
            this.isDownloading = true;
            let count = 0;
            const interval = setInterval(() => {
                if (count >= participants.length) {
                    clearInterval(interval);
                    this.isDownloading = false;
                    alert('Semua QR Code selesai diunduh.');
                    return;
                }
                const p = participants[count];
                const filename = `qrcode-${p.nama.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
                this.downloadQrCode(p.barcode, filename);
                count++;
            }, 500);
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
        },
        printAttendance() {
            const filterParams = new URLSearchParams(window.location.search).toString();
            window.open(`pages/master/print_hadir.php?${filterParams}`, '_blank');
        }
    }"
    @keydown.escape.window="isFilterModalOpen = false; isDetailModalOpen = false; isQrCodeModalOpen = false; isEditModalOpen = false; isDeleteModalOpen = false; isBuktiModalOpen = false">

    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Manajemen Peserta Hadir</h1>
        <div class="flex space-x-2">
            <button @click="isFilterModalOpen = true" class="px-3 py-2 text-sm font-semibold text-gray-700 bg-white rounded-md shadow-sm hover:bg-gray-50 flex items-center"><i class="fas fa-filter mr-2"></i>Filter</button>
            <button @click="isDetailModalOpen = true" class="px-3 py-2 text-sm font-semibold text-gray-700 bg-white rounded-md shadow-sm hover:bg-gray-50 flex items-center"><i class="fas fa-chart-pie mr-2"></i>Ringkasan</button>
            <button @click="downloadAllQrCodes(allParticipants)" :disabled="isDownloading" class="px-3 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:bg-blue-300 flex items-center">
                <i class="fas fa-cloud-download-alt mr-2"></i><span x-text="isDownloading ? 'Mengunduh...' : 'Download QR'"></span>
            </button>
        </div>
    </div>

    <div class="mt-5 bg-white shadow-md rounded-xl overflow-hidden">
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-list text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Data Tabel</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-bold">
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kelompok</th>
                        <th class="px-4 py-3">JK</th>
                        <th class="px-4 py-3">Ukuran Jersey</th>
                        <th class="px-4 py-3">Metode Bayar</th>
                        <th class="px-4 py-3">Status Bayar</th>
                        <th class="px-4 py-3">QR Code</th>
                        <?php
                        if ($role_user == 'sekretaris') {
                        ?>
                            <th class="px-4 py-3">Aksi</th>
                        <?php
                        }
                        ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    foreach ($peserta_list as $peserta): ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-4 py-2"><?php echo $no++; ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($peserta['nama']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($peserta['kelompok']); ?></td>
                            <td class="px-4 py-2"><?php echo substr($peserta['jenis_kelamin'], 0, 1); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($peserta['ukuran_jersey'] ?? '-'); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($peserta['metode_pembayaran']); ?></td>
                            <td class="px-4 py-2"><span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $peserta['status_pembayaran'] == 'lunas' ? 'bg-green-500' : ($peserta['status_pembayaran'] == 'ditolak' ? 'bg-red-500' : 'bg-yellow-500'); ?>"><?php echo ucfirst(str_replace('_', ' ', $peserta['status_pembayaran'])); ?></span></td>
                            <td class="px-4 py-2 text-sm">
                                <button @click="showQrCode('<?php echo htmlspecialchars($peserta['barcode']); ?>', '<?php echo htmlspecialchars($peserta['nama'], ENT_QUOTES); ?>')" class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 mr-2"><i class="fas fa-qrcode"></i></button>
                                <button @click="downloadQrCode('<?php echo htmlspecialchars($peserta['barcode']); ?>', `qrcode-<?php echo htmlspecialchars($peserta['nama']); ?>.png`)" class="px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600"><i class="fas fa-download"></i></button>
                            </td>
                            <?php if ($role_user == 'sekretaris'): ?>
                            <td class="px-4 py-2">
                                <button @click="isEditModalOpen = true; editData = <?php echo htmlspecialchars(json_encode($peserta), ENT_QUOTES, 'UTF-8'); ?>;" class="text-indigo-600 hover:text-indigo-800 mr-3"><i class="fas fa-pencil-alt"></i></button>
                                <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($peserta), ENT_QUOTES, 'UTF-8'); ?>;" class="text-red-600 hover:text-red-800"><i class="fas fa-trash-alt"></i></button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Edit -->
    <div x-show="isEditModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isEditModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 mx-4">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white">Edit Peserta</h3>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="peserta_id" :value="editData.id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm">Nama</label><input type="text" name="nama" x-model="editData.nama" required class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label class="block text-sm">Kelompok</label><select name="kelompok" x-model="editData.kelompok" required class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="Bintaran">Bintaran</option>
                            <option value="Gedongkuning">Gedongkuning</option>
                            <option value="Jombor">Jombor</option>
                            <option value="Sunten">Sunten</option>
                        </select></div>
                    <div><label class="block text-sm">Jenis Kelamin</label><select name="jenis_kelamin" x-model="editData.jenis_kelamin" required class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select></div>
                    <div><label class="block text-sm">Metode Pembayaran</label><select name="metode_pembayaran" x-model="editData.metode_pembayaran" class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="Cash">Cash</option>
                            <option value="Transfer">Transfer</option>
                        </select></div>
                    <div><label class="block text-sm">Ukuran Jersey</label><select name="ukuran_jersey" x-model="editData.ukuran_jersey" class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="">-- Pilih Ukuran --</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="2XL">2XL</option>
                            <option value="3XL">3XL</option>
                            <option value="4XL">4XL</option>
                            <option value="5XL">5XL</option>
                            <option value="6XL">6XL</option>
                            <option value="7XL">7XL</option>
                            <option value="8XL">8XL</option>
                        </select></div>
                    <div class="md:col-span-2"><label class="block text-sm">Status Pembayaran</label><select name="status_pembayaran" x-model="editData.status_pembayaran" required class="mt-1 w-full border-gray-300 rounded-md">
                            <option value="belum_diverifikasi">Belum Diverifikasi</option>
                            <option value="lunas">Lunas</option>
                            <option value="ditolak">Ditolak</option>
                        </select></div>
                </div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isEditModalOpen = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Simpan</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 mx-4">
            <div class="bg-red-600 px-6 py-4 flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white">Konfirmasi Hapus</h3>
            </div>
            <p class="mt-2">Yakin ingin menghapus peserta <strong x-text="deleteData.nama"></strong>?</p>
            <form method="POST" action="" class="mt-6 flex justify-end space-x-4">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="peserta_id" :value="deleteData.id">
                <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Ya, Hapus</button>
            </form>
        </div>
    </div>

    <!-- Modal Filter, Ringkasan, Lihat QR -->
    <div x-show="isFilterModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isFilterModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 mx-4">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white">Filter Peserta</h3>
            </div>
            <form action="sekretaris" method="GET" class="space-y-4">
                <input type="hidden" name="page" value="peserta/manajemen_peserta">
                <div><label class="block text-sm">Cari Nama</label><input type="text" name="search" value="<?php echo htmlspecialchars($search_nama); ?>" class="mt-1 w-full border-gray-300 rounded-md"></div>
                <div><label class="block text-sm">Filter Kelompok</label><select name="kelompok" class="mt-1 w-full border-gray-300 rounded-md">
                        <option value="">Semua Kelompok</option>
                        <option value="Bintaran" <?php if ($filter_kelompok == 'Bintaran') echo 'selected'; ?>>Bintaran</option>
                        <option value="Gedongkuning" <?php if ($filter_kelompok == 'Gedongkuning') echo 'selected'; ?>>Gedongkuning</option>
                        <option value="Jombor" <?php if ($filter_kelompok == 'Jombor') echo 'selected'; ?>>Jombor</option>
                        <option value="Sunten" <?php if ($filter_kelompok == 'Sunten') echo 'selected'; ?>>Sunten</option>
                    </select></div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isFilterModalOpen = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Terapkan</button></div>
            </form>
        </div>
    </div>
    <div x-show="isDetailModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isDetailModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4 text-center">Ringkasan Peserta Hadir</h3>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                        <tr class="text-left font-bold">
                            <th class="px-6 py-3">Kelompok</th>
                            <th class="px-6 py-3 text-center">Laki-laki</th>
                            <th class="px-6 py-3 text-center">Perempuan</th>
                            <th class="px-6 py-3 text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200"><?php foreach ($summary_data as $kelompok => $data): ?><tr>
                                <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($kelompok); ?></td>
                                <td class="px-6 py-4 text-center"><?php echo $data['Laki-laki']; ?></td>
                                <td class="px-6 py-4 text-center"><?php echo $data['Perempuan']; ?></td>
                                <td class="px-6 py-4 text-center font-bold"><?php echo $data['total']; ?></td>
                            </tr><?php endforeach; ?></tbody>
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
            <div class="mt-6 flex justify-end"><button type="button" @click="isDetailModalOpen = false" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Tutup</button></div>
        </div>
    </div>
    <div x-show="isQrCodeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isQrCodeModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-xs p-6 mx-4 text-center">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white" x-text="`QR Code untuk ${qrCodeName}`"></h3>
            </div>
            <div class="my-4 p-4 bg-white">
                <div id="qrcode-display" class="flex justify-center"></div>
            </div><button type="button" @click="isQrCodeModalOpen = false" class="mt-6 w-full px-4 py-2 bg-blue-600 text-white rounded-lg">Tutup</button>
        </div>
    </div>
</div>

<script>
    const allParticipants = <?php echo json_encode(array_map(function ($p) {
                                return ['nama' => $p['nama'], 'barcode' => $p['barcode']];
                            }, $peserta_list)); ?>;
</script>
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>