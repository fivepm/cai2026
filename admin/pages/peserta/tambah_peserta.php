<?php
// (File: pages/peserta/tambah_peserta.php)

// ===================================================================
// BAGIAN LOGIKA PHP UNTUK MENYIMPAN DATA (FORM MANUAL)
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'tambah_peserta') {
    $nama = $_POST['nama'];
    $kelompok = $_POST['kelompok'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $ukuran_jersey = $_POST['ukuran_jersey'] ?? null;
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $status_pembayaran = $_POST['status_pembayaran'];
    $terima_idcard = isset($_POST['terima_idcard']) ? 'ya' : 'tidak';

    $barcode = 'OTS-' . strtoupper(bin2hex(random_bytes(8)));
    $dibayar_pada = ($status_pembayaran === 'lunas') ? date('Y-m-d H:i:s') : null;

    $sql = "INSERT INTO peserta (nama, kelompok, jenis_kelamin, ukuran_jersey, barcode, metode_pembayaran, status_pembayaran, terima_idcard, dibayar_pada)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $nama, $kelompok, $jenis_kelamin, $ukuran_jersey, $barcode, $metode_pembayaran, $status_pembayaran, $terima_idcard, $dibayar_pada);

    if ($stmt->execute()) {
        $peserta_id = $stmt->insert_id;
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Peserta baru berhasil ditambahkan dengan nama: ' . htmlspecialchars($nama)];
        if ($status_pembayaran === 'lunas') {
            $nominal = 50000;
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
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// ===================================================================
// BAGIAN LOGIKA PHP UNTUK IMPORT CSV
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    $imported = 0;
    $errors   = [];
    $valid_kelompok = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];
    $valid_jk       = ['Laki-laki', 'Perempuan'];
    $valid_jersey   = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL', '7XL', '8XL', '9XL', '10XL', '11XL'];

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $mime = mime_content_type($_FILES['csv_file']['tmp_name']);
        $allowed_mimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];

        if (!in_array($mime, $allowed_mimes) && strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'File harus berformat CSV.'];
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = fgetcsv($handle); // skip header baris pertama

            $row_num = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                if (count($row) < 4) {
                    $errors[] = "Baris {$row_num}: Kolom tidak lengkap (harus ada kelompok, nama, jenis_kelamin, ukuran_jersey).";
                    continue;
                }

                $kelompok      = trim($row[0]);
                $nama          = ucwords(mb_strtolower(trim($row[1]), 'UTF-8'));
                $jenis_kelamin = trim($row[2]);
                $ukuran_jersey = trim($row[3]) ?: null;

                if (empty($nama)) {
                    $errors[] = "Baris {$row_num}: Nama tidak boleh kosong.";
                    continue;
                }
                if (!in_array($kelompok, $valid_kelompok)) {
                    $errors[] = "Baris {$row_num} ({$nama}): Kelompok '{$kelompok}' tidak valid. Pilihan: " . implode(', ', $valid_kelompok);
                    continue;
                }
                if (!in_array($jenis_kelamin, $valid_jk)) {
                    $errors[] = "Baris {$row_num} ({$nama}): Jenis kelamin '{$jenis_kelamin}' tidak valid.";
                    continue;
                }
                if ($ukuran_jersey && !in_array(strtoupper($ukuran_jersey), $valid_jersey)) {
                    $errors[] = "Baris {$row_num} ({$nama}): Ukuran jersey '{$ukuran_jersey}' tidak valid. Pilihan: " . implode(', ', $valid_jersey);
                    continue;
                }

                // Generate barcode otomatis dengan awalan P-
                $barcode = 'P-' . strtoupper(bin2hex(random_bytes(8)));

                $sql_csv = "INSERT INTO peserta (kelompok, nama, jenis_kelamin, ukuran_jersey, barcode, pakai_tabungan, status_pembayaran, terima_totebag, terima_idcard)
                            VALUES (?, ?, ?, ?, ?, 'no', 'belum_diverifikasi', 'tidak', 'tidak')";
                $stmt_csv = $conn->prepare($sql_csv);
                $stmt_csv->bind_param("sssss", $kelompok, $nama, $jenis_kelamin, $ukuran_jersey, $barcode);

                if ($stmt_csv->execute()) {
                    $imported++;
                } else {
                    $errors[] = "Baris {$row_num} ({$nama}): Gagal disimpan - " . $stmt_csv->error;
                }
                $stmt_csv->close();
            }
            fclose($handle);

            if ($imported > 0) {
                $msg = "Berhasil mengimpor {$imported} peserta.";
                if (!empty($errors)) {
                    $msg .= " Namun ada " . count($errors) . " baris yang gagal.";
                }
                $_SESSION['message']       = ['type' => 'success', 'text' => $msg];
                $_SESSION['import_errors'] = $errors;
            } else {
                $_SESSION['message']       = ['type' => 'error', 'text' => 'Tidak ada data yang berhasil diimpor.'];
                $_SESSION['import_errors'] = $errors;
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal mengunggah file. Pastikan file CSV sudah dipilih.'];
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
?>

<div class="p-6 bg-white rounded-lg shadow-md">

    <!-- HEADER + TOMBOL IMPORT -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-gray-800">Tambah Peserta Baru</h1>
            <p class="mt-1 text-gray-600">Gunakan form ini untuk mendaftarkan peserta yang hadir.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <!-- Tombol Download Template CSV -->
            <a href="/uploads/template_import_peserta.csv" download
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-300 rounded-lg hover:bg-green-100 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Template CSV
            </a>
            <!-- Tombol Import CSV -->
            <button type="button" onclick="document.getElementById('modal-import-csv').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
                </svg>
                Import CSV
            </button>
        </div>
    </div>

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
        <?php if (!empty($_SESSION['import_errors'])): ?>
            <div class="mb-4 p-4 bg-yellow-50 border border-yellow-300 rounded-md">
                <p class="font-semibold text-yellow-800 mb-2">Detail baris yang gagal diimpor:</p>
                <ul class="list-disc list-inside space-y-1 text-sm text-yellow-700">
                    <?php foreach ($_SESSION['import_errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php unset($_SESSION['message'], $_SESSION['import_errors']); ?>
    <?php endif; ?>

    <!-- FORM TAMBAH PESERTA MANUAL -->
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
                    <option value="XS">XS</option>
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
                    <option value="9XL">9XL</option>
                    <option value="10XL">10XL</option>
                    <option value="11XL">11XL</option>
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

<!-- ================================================================ -->
<!-- MODAL IMPORT CSV                                                  -->
<!-- ================================================================ -->
<div id="modal-import-csv" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">

        <!-- Header Modal -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-800">Import Data Peserta dari CSV</h2>
            </div>
            <button type="button" onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body Modal -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_csv">

            <div class="px-6 py-5 space-y-5">

                <!-- Info kolom CSV -->
                <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg text-sm text-indigo-800">
                    <p class="font-semibold mb-2">Format kolom CSV yang diperlukan:</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-indigo-100">
                                    <th class="border border-indigo-300 px-2 py-1 text-left">Kolom</th>
                                    <th class="border border-indigo-300 px-2 py-1 text-left">Nilai Valid</th>
                                    <th class="border border-indigo-300 px-2 py-1 text-left">Ket.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="border border-indigo-200 px-2 py-1 font-mono">kelompok</td><td class="border border-indigo-200 px-2 py-1">Bintaran, Gedongkuning, Jombor, Sunten</td><td class="border border-indigo-200 px-2 py-1">Wajib</td></tr>
                                <tr class="bg-indigo-50"><td class="border border-indigo-200 px-2 py-1 font-mono">nama</td><td class="border border-indigo-200 px-2 py-1">Teks bebas</td><td class="border border-indigo-200 px-2 py-1">Wajib</td></tr>
                                <tr><td class="border border-indigo-200 px-2 py-1 font-mono">jenis_kelamin</td><td class="border border-indigo-200 px-2 py-1">Laki-laki, Perempuan</td><td class="border border-indigo-200 px-2 py-1">Wajib</td></tr>
                                <tr class="bg-indigo-50"><td class="border border-indigo-200 px-2 py-1 font-mono">ukuran_jersey</td><td class="border border-indigo-200 px-2 py-1">XS, S, M, L, XL, XXL</td><td class="border border-indigo-200 px-2 py-1">Opsional</td></tr>
                                <tr><td class="border border-indigo-200 px-2 py-1 font-mono">barcode</td><td class="border border-indigo-200 px-2 py-1">-</td><td class="border border-indigo-200 px-2 py-1 text-green-700 font-medium">Otomatis (P-...)</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Area upload file -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV</label>
                    <div id="drop-zone"
                         class="relative flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-all"
                         onclick="document.getElementById('csv_file_input').click()"
                         ondragover="event.preventDefault(); this.classList.add('border-indigo-500','bg-indigo-50')"
                         ondragleave="this.classList.remove('border-indigo-500','bg-indigo-50')"
                         ondrop="handleDrop(event)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p id="drop-label" class="text-sm text-gray-500">Klik atau seret file CSV ke sini</p>
                        <p class="text-xs text-gray-400 mt-1">Hanya file .csv yang diterima</p>
                        <input type="file" id="csv_file_input" name="csv_file" accept=".csv,text/csv" class="hidden" onchange="updateDropLabel(this)">
                    </div>
                </div>

                <!-- Peringatan -->
                <div class="flex gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Barcode dibuat otomatis dengan awalan <strong>P-</strong>. Status pembayaran default: <em>belum_diverifikasi</em>.</span>
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50 rounded-b-2xl">
                <a href="/uploads/template_import_peserta.csv" download
                   class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Unduh Template CSV
                </a>
                <div class="flex gap-3">
                    <button type="button" onclick="closeImportModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                        Import Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function closeImportModal() {
    document.getElementById('modal-import-csv').classList.add('hidden');
}
document.getElementById('modal-import-csv').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
function updateDropLabel(input) {
    const label = document.getElementById('drop-label');
    if (input.files && input.files[0]) {
        label.textContent = 'File dipilih: ' + input.files[0].name;
        label.classList.add('text-indigo-600', 'font-medium');
    }
}
function handleDrop(event) {
    event.preventDefault();
    const zone = document.getElementById('drop-zone');
    zone.classList.remove('border-indigo-500', 'bg-indigo-50');
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const input = document.getElementById('csv_file_input');
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        updateDropLabel(input);
    }
}
</script>
