<?php
// (File: pages/peserta/tambah_peserta.php)

// ===================================================================
// BAGIAN LOGIKA PHP UNTUK MENYIMPAN DATA
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'tambah_peserta') {
    // Ambil semua data dari form
    $nama = $_POST['nama'];
    $kelompok = $_POST['kelompok'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ukuran_jersey = $_POST['ukuran_jersey'] ?? null;
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $status_pembayaran = $_POST['status_pembayaran'];

    $barcode = 'OTS-' . strtoupper(bin2hex(random_bytes(8)));
    $dibayar_pada = ($status_pembayaran === 'lunas') ? date('Y-m-d H:i:s') : null;

    $sql = "INSERT INTO peserta (nama, kelompok, jenis_kelamin, ukuran_jersey, barcode, metode_pembayaran, status_pembayaran, dibayar_pada)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $nama, $kelompok, $jenis_kelamin, $ukuran_jersey, $barcode, $metode_pembayaran, $status_pembayaran, $dibayar_pada);

    if ($stmt->execute()) {
        $peserta_id = $stmt->insert_id;
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Peserta baru berhasil ditambahkan dengan nama: ' . htmlspecialchars($nama)];

        // Otomatisasi ke log keuangan jika status lunas
        if ($status_pembayaran === 'lunas') {
            $nominal = 50000; // Asumsi biaya pendaftaran
            $jenis = 'masuk';
            $sumber = 'Peserta';
            $keterangan_log = "Pembayaran dari Peserta (OTS) ID: " . $peserta_id;
            $stmt_log_add = $conn->prepare("INSERT INTO log_keuangan (tanggal, nominal, jenis, keterangan, sumber_pemasukan) VALUES (NOW(), ?, ?, ?, ?)");
            $stmt_log_add->bind_param("isss", $nominal, $jenis, $keterangan_log, $sumber);
            $stmt_log_add->execute();
            $stmt_log_add->close();
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menambahkan peserta. Error: ' . $stmt->error];
    }
    $stmt->close();

    // Redirect untuk mencegah resubmit form
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
?>

<div class="p-6 bg-white rounded-lg shadow-md">
    <h1 class="text-3xl font-semibold text-gray-800">Tambah Peserta Baru</h1>
    <p class="mt-2 text-gray-600">Gunakan form ini untuk mendaftarkan peserta yang hadir.</p>

    <!-- NOTIFIKASI -->
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

    <!-- Form Tambah Peserta -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="mt-6 space-y-6 border-t pt-6">
        <input type="hidden" name="action" value="tambah_peserta">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Nama Lengkap -->
            <div>
                <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Masukkan nama lengkap">
            </div>
            <!-- Kelompok -->
            <div>
                <label for="kelompok" class="block text-sm font-medium text-gray-700">Kelompok</label>
                <select id="kelompok" name="kelompok" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Kelompok --</option>
                    <option value="Bintaran">Bintaran</option>
                    <option value="Gedongkuning">Gedongkuning</option>
                    <option value="Jombor">Jombor</option>
                    <option value="Sunten">Sunten</option>
                </select>
            </div>
            <!-- Jenis Kelamin -->
            <div>
                <label for="jenis_kelamin" class="block text-sm font-medium text-gray-700">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
            </div>
            <!-- Ukuran Jersey -->
            <div>
                <label for="ukuran_jersey" class="block text-sm font-medium text-gray-700">Ukuran Jersey</label>
                <select id="ukuran_jersey" name="ukuran_jersey" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
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
                </select>
            </div>
            <!-- Status Pembayaran -->
            <div>
                <label for="status_pembayaran" class="block text-sm font-medium text-gray-700">Status Pembayaran</label>
                <select id="status_pembayaran" name="status_pembayaran" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="lunas">Lunas</option>
                    <option value="belum_diverifikasi">Belum Lunas</option>
                </select>
            </div>
            <!-- Metode Pembayaran -->
            <div>
                <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode Pembayaran</label>
                <select id="metode_pembayaran" name="metode_pembayaran" required class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="Cash">Cash</option>
                    <option value="Transfer">Transfer</option>
                </select>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex justify-end border-t pt-6">
            <button type="submit" class="px-6 py-3 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Simpan Peserta
            </button>
        </div>
    </form>
</div>