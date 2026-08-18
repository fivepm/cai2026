<?php

header("Location: pendaftaran_ditutup");

session_start();
require_once 'config/config.php';

$registration_success = false;

// Pastikan direktori untuk upload ada
$upload_dir = 'uploads/surat_izin/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
$upload_dir_bukti = 'uploads/bukti_pembayaran/';
if (!is_dir($upload_dir_bukti)) {
    mkdir($upload_dir_bukti, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kelompok = $_POST['kelompok'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $kehadiran = $_POST['kehadiran'] ?? '';
    $pakai_tabungan = isset($_POST['pakai_tabungan']) ? 'yes' : 'no';
    $error = '';

    if (empty($kelompok) || empty($nama) || empty($kehadiran)) {
        $error = 'Semua field wajib diisi.';
    }

    // Logika pemisahan data berdasarkan status kehadiran
    if (empty($error)) {
        if ($kehadiran === 'hadir') {
            // LOGIKA UNTUK PESERTA HADIR (DENGAN PEMBAYARAN)
            $metode_pembayaran = $_POST['pembayaran'] ?? '';
            $nama_bukti_pembayaran = null;

            if (empty($metode_pembayaran)) {
                $error = 'Metode pembayaran wajib dipilih.';
            }

            // Jika metode bukan 'Cash', maka upload bukti wajib
            if ($metode_pembayaran !== 'Cash' && empty($error)) {
                if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
                    $file = $_FILES['bukti_pembayaran'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (in_array($file_ext, $allowed_ext) && $file['size'] < 5000000) { // Maks 5MB
                        $nama_bukti_pembayaran = "bukti_" . uniqid('', true) . '.' . $file_ext;
                        if (!move_uploaded_file($file['tmp_name'], $upload_dir_bukti . $nama_bukti_pembayaran)) {
                            $error = 'Gagal mengupload bukti pembayaran.';
                        }
                    } else {
                        $error = 'Format file bukti bayar tidak valid atau ukuran terlalu besar (Maks 5MB).';
                    }
                } else {
                    $error = 'Bukti pembayaran wajib diunggah.';
                }
            }

            if (empty($error)) {
                $barcode = 'PESERTA-' . strtoupper($kelompok) . '-' . bin2hex(random_bytes(10));
                // Update query INSERT untuk tabel peserta
                $stmt = $conn->prepare("INSERT INTO peserta (kelompok, nama, jenis_kelamin, barcode, pakai_tabungan, metode_pembayaran, bukti_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $kelompok, $nama, $jenis_kelamin, $barcode, $pakai_tabungan, $metode_pembayaran, $nama_bukti_pembayaran);
                if ($stmt->execute()) {
                    $registration_success = true;
                } else {
                    $error = 'Gagal mendaftar: ' . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($kehadiran === 'izin') {
            // JIKA IZIN: Proses upload file dan masukkan ke tabel 'izin'
            $nama_file_izin = null;
            if (isset($_FILES['file_izin']) && $_FILES['file_izin']['error'] == 0) {
                $file = $_FILES['file_izin'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

                if (in_array($file_ext, $allowed_ext) && $file['size'] < 5000000) { // Maks 5MB
                    $nama_file_izin = "surat_izin_" . uniqid('', true) . '.' . $file_ext;
                    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $nama_file_izin)) {
                        $error = 'Gagal mengupload file.';
                    }
                } else {
                    $error = 'Format file tidak valid atau ukuran terlalu besar (Maks 5MB).';
                }
            } else {
                $error = 'Surat izin wajib diunggah jika Anda memilih Izin.';
            }

            // Jika file berhasil diupload, simpan ke DB
            if (empty($error)) {
                // Update query INSERT untuk tabel izin
                $stmt = $conn->prepare("INSERT INTO izin (kelompok, nama, jenis_kelamin, pakai_tabungan, file_izin) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $kelompok, $nama, $jenis_kelamin, $pakai_tabungan, $nama_file_izin);
                if ($stmt->execute()) {
                    $registration_success = true;
                } else {
                    $error = 'Gagal menyimpan data izin: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    if (!empty($error)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => $error];
    } else {
        unset($_SESSION['message']);
    }

    $conn->close();

    if (!$registration_success) {
        header("Location: pendaftaran");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Peserta - Event CAI 2025</title>
    <link rel="icon" type="image/png" href="uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen font-sans p-4">
    <div class="w-full max-w-md">
        <?php if ($registration_success): ?>
            <!-- Tampilan Sukses -->
            <div class="p-8 space-y-6 bg-white rounded-xl shadow-lg text-center">
                <div class="mx-auto bg-green-100 rounded-full h-20 w-20 flex items-center justify-center"><i class="fas fa-check-circle text-5xl text-green-500"></i></div>
                <h2 class="text-3xl font-bold text-gray-800">Pendaftaran Berhasil!</h2>
                <p class="text-gray-600">Data Anda telah kami terima. Jika tidak ingin mendaftarkan peserta lain silahkan tutup halaman ini. Alhamdulillahi Jazaakumullahu Khoiro</p>
                <div class="pt-4">
                    <a href="pendaftaran" class="w-full inline-flex items-center justify-center px-4 py-3 text-base font-semibold text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700">
                        Daftarkan Peserta Lain
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Form Pendaftaran -->
            <div x-data="{ kehadiran: '', kelompok: '' }" class="p-8 space-y-6 bg-white rounded-xl shadow-lg">
                <!-- TAMBAHKAN LOGO DI SINI -->
                <img src="uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
                <h2 class="text-3xl font-bold text-center text-gray-800">Form Pendaftaran<br>CAI XLVI Tahun 2025</h2>
                <p class="text-center text-gray-500">Isi data diri Anda untuk mengikuti CAI 2025.</p>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="p-4 rounded-md <?php echo $_SESSION['message']['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>" role="alert">
                        <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
                    </div>
                <?php unset($_SESSION['message']);
                endif; ?>

                <form id="registrationForm" class="mt-8 space-y-6" action="pendaftaran" method="POST" enctype="multipart/form-data">
                    <div>
                        <label for="kelompok" class="text-sm font-medium text-gray-700">Kelompok</label>
                        <select id="kelompok" name="kelompok" x-model="kelompok" required class="w-full px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="" disabled selected>Pilih Kelompok Anda</option>
                            <option value="Bintaran">Bintaran</option>
                            <option value="Gedongkuning">Gedongkuning</option>
                            <option value="Jombor">Jombor</option>
                            <option value="Sunten">Sunten</option>
                        </select>
                    </div>
                    <div>
                        <label for="nama" class="text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input id="nama" name="nama" type="text" required class="w-full px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Masukkan nama lengkap Anda">
                    </div>
                    <!-- Input Jenis Kelamin -->
                    <div>
                        <label for="jenis_kelamin" class="text-sm font-medium text-gray-700">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" required class="w-full px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg">
                            <option value="" disabled selected>Pilih Jenis Kelamin</option>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-center">
                        <div class="mt-2 flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="kehadiran" value="hadir" x-model="kehadiran" required class="h-4 w-4 text-red-600 border-gray-300">
                                <span class="ml-2">Hadir</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="kehadiran" value="izin" x-model="kehadiran" required class="h-4 w-4 text-red-600 border-gray-300">
                                <span class="ml-2">Izin</span>
                            </label>
                        </div>
                    </div>
                    <div x-show="kehadiran === 'hadir'" x-transition class="p-4 bg-blue-50 border border-gray-200 rounded-lg space-y-4">
                        <h3 class="font-semibold text-gray-800">Pilih Metode Pembayaran</h3>
                        <div x-data="{ pembayaran: '' }">
                            <label for="pembayaran" class="text-sm font-medium text-gray-700">Metode Pembayaran</label>
                            <select id="pembayaran" name="pembayaran" x-model="pembayaran" :required="kehadiran === 'hadir'" class="w-full text-sm px-4 py-2 mt-2 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="" disabled selected>Pilih Metode Pembayaran</option>
                                <option value="Cash">Cash</option>
                                <option value="Line Bank">19742322101 (Line Bank) - a.n Brilliant Azka</option>
                                <option value="Dana">085150731129 (Dana) - a.n Brilliant Azka</option>
                            </select>
                            <div x-show="pembayaran != 'Cash' && pembayaran != ''" x-transition class="py-4 space-y-4">
                                <label for="bukti_pembayaran" class="block text-sm font-medium text-gray-700">Upload Bukti Pembayaran</label>
                                <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                                <p class="text-xs text-gray-500 mt-1">Format: PDF, DOC, DOCX, JPG, PNG. Maks: 5MB.</p>
                            </div>
                        </div>
                    </div>
                    <div x-show="kehadiran === 'izin'" x-transition class="p-4 bg-red-50 border border-gray-200 rounded-lg space-y-4">
                        <h3 class="font-semibold text-gray-800">Formulir Izin</h3>
                        <div>
                            <label for="file_izin" class="block text-sm font-medium text-gray-700">Upload Surat Izin</label>
                            <input type="file" name="file_izin" id="file_izin" :required="kehadiran === 'izin'" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-red-100 file:text-red-700 hover:file:bg-red-200">
                            <p class="text-xs text-gray-500 mt-1">Format: PDF, DOC, DOCX, JPG, PNG. Maks: 5MB.</p>
                        </div>
                        <div><a href="uploads/Surat Permohonan Izin.pdf" download class="inline-flex items-center text-sm font-medium text-red-600 hover:text-red-800"><i class="fas fa-download mr-2"></i>Download Template Surat Izin</a></div>
                        <div>
                            <p class="text-sm">
                                Perlu bantuan untuk membuat surat izin?
                                <a x-show="kelompok === ''" class="text-yellow-700 text-sm">Pilih Kelompok terlebih dahulu</a>
                                <a x-show="kelompok === 'Bintaran'" href="https://wa.me/6288902994122" class="text-sm text-blue-500 underline hover:text-blue-700 hover:underline">Klik disini</a>
                                <a x-show="kelompok === 'Gedongkuning'" href="https://wa.me/6287848248295" class="text-sm text-blue-500 underline hover:text-blue-700 hover:underline">Klik disini</a>
                                <a x-show="kelompok === 'Jombor'" href="https://wa.me/6285150731129" class="text-sm text-blue-500 underline hover:text-blue-700 hover:underline">Klik disini</a>
                                <a x-show="kelompok === 'Sunten'" href="https://wa.me/6287848248295" class="text-sm text-blue-500 underline hover:text-blue-700 hover:underline">Klik disini</a>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center"><input id="pakai_tabungan" name="pakai_tabungan" type="checkbox" class="h-5 w-5 text-red-600 border-gray-300 rounded focus:ring-red-500"><label for="pakai_tabungan" class="ml-3 block text-sm text-gray-900">Saya ingin menggunakan tabungan</label></div>
                    <div class="pt-4"><button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 text-base font-semibold text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700">Daftar Sekarang</button></div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>