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
    $pakai_tabungan = isset($_POST['pakai_tabungan']) ? 'yes' : 'no';
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $status_pembayaran = $_POST['status_pembayaran'];
    $terima_totebag = isset($_POST['terima_totebag']) ? 'ya' : 'tidak';
    $terima_idcard = isset($_POST['terima_idcard']) ? 'ya' : 'tidak';

    // Buat barcode unik
    $barcode = 'OTS-' . strtoupper(bin2hex(random_bytes(8)));

    // Tentukan tanggal pembayaran
    $dibayar_pada = ($status_pembayaran === 'lunas') ? date('Y-m-d H:i:s') : null;

    // Siapkan query INSERT
    $sql = "INSERT INTO peserta (nama, kelompok, jenis_kelamin, barcode, pakai_tabungan, metode_pembayaran, status_pembayaran, terima_totebag, terima_idcard, dibayar_pada) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $nama, $kelompok, $jenis_kelamin, $barcode, $pakai_tabungan, $metode_pembayaran, $status_pembayaran, $terima_totebag, $terima_idcard, $dibayar_pada);

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
    <h1 class="text-3xl font-semibold text-gray-800">Tambah Peserta Baru (OTS)</h1>
    <p class="mt-2 text-gray-600">Gunakan form ini untuk mendaftarkan peserta yang hadir langsung di lokasi acara.</p>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="my-4 p-4 rounded-md <?php echo $_SESSION['message']['type'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
        </div>
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
                    <option value="Gedonkuning">Gedonkuning</option>
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
                    <option value="Line Bank">Line Bank</option>
                    <option value="Dana">Dana</option>
                </select>
            </div>
        </div>

        <!-- Opsi Lain -->
        <div class="border-t pt-6 space-y-4">
            <label class="flex items-center">
                <input type="checkbox" name="pakai_tabungan" class="h-5 w-5 rounded border-gray-300 text-red-600">
                <span class="ml-3 text-gray-700">Menggunakan Tabungan</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="terima_totebag" checked class="h-5 w-5 rounded border-gray-300 text-red-600">
                <span class="ml-3 text-gray-700">Sudah Menerima Totebag</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="terima_idcard" checked class="h-5 w-5 rounded border-gray-300 text-red-600">
                <span class="ml-3 text-gray-700">Sudah Menerima ID Card</span>
            </label>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex justify-end border-t pt-6">
            <button type="submit" class="px-6 py-3 font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700">
                Simpan Peserta
            </button>
        </div>
    </form>
</div>