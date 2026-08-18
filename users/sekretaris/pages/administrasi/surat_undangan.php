<?php
// Izinkan akses hanya untuk sekretaris
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}

$output_dir = '../../uploads/surat_undangan/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}
$output_dir_web = '../../uploads/surat_undangan/';

// Proses form saat surat baru dibuat atau dihapus
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'buat_undangan') {
        // ... (Logika otentikasi Google OAuth 2.0) ...
        $credentials_path = '../../credentials/credential_cai2025_btp1.json';
        $token_path = '../../credentials/token.json';
        $client = new Google\Client();
        $client->setAuthConfig($credentials_path);
        $client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/cai25/oauth2callback.php'); // Sesuaikan path
        $client->setScopes([Google\Service\Docs::DOCUMENTS, Google\Service\Drive::DRIVE]);
        $client->setAccessType('offline');
        if (!file_exists($token_path)) {
            die("Aplikasi belum diotorisasi.");
        }
        $accessToken = json_decode(file_get_contents($token_path), true);
        $client->setAccessToken($accessToken);
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($token_path, json_encode($client->getAccessToken()));
            } else {
                die("Token kedaluwarsa, otorisasi ulang diperlukan.");
            }
        }

        // Ambil data dari form
        $jenis_undangan = $_POST['jenis_undangan'];
        $nama_pemateri = $_POST['nama_pemateri'];
        $topik_materi = $_POST['topik_materi'];
        if ($_POST['topik_materi'] == "") {
            $topik_materi = "-";
        }
        $tanggal_acara = $_POST['tanggal_acara'];
        $waktu_acara = $_POST['waktu_acara'];
        $tanggal_surat = date('d F Y');

        $template_id = '1c375mzMGG0or9lX9_6MsNFhQg7JKMcgz_Cl2esYag1Q';

        $requests = [
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{jenis_undangan}}'], 'replaceText' => $jenis_undangan]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{nama_pemateri}}'], 'replaceText' => $nama_pemateri]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{topik_materi}}'], 'replaceText' => $topik_materi]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tanggal_acara}}'], 'replaceText' => date('d F Y', strtotime($tanggal_acara))]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{waktu_acara}}'], 'replaceText' => $waktu_acara]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tanggal_surat}}'], 'replaceText' => $tanggal_surat]]),
        ];

        try {
            $driveService = new Google\Service\Drive($client);
            $docsService = new Google\Service\Docs($client);
            $copy = new Google\Service\Drive\DriveFile(['name' => "Undangan ($jenis_undangan) - $nama_pemateri"]);
            $copiedFile = $driveService->files->copy($template_id, $copy);
            $newDocumentId = $copiedFile->getId();
            $batchUpdateRequest = new Google\Service\Docs\BatchUpdateDocumentRequest(['requests' => $requests]);
            $docsService->documents->batchUpdate($newDocumentId, $batchUpdateRequest);
            $pdfContent = $driveService->files->export($newDocumentId, 'application/pdf', ['alt' => 'media']);
            $driveService->files->delete($newDocumentId);

            $pdf_filename = "Undangan-" . preg_replace('/[^a-zA-Z0-9]/', '_', $nama_pemateri) . '_' . time() . '.pdf';
            file_put_contents($output_dir . $pdf_filename, $pdfContent->getBody()->getContents());

            $stmt = $conn->prepare("INSERT INTO surat_undangan (jenis_undangan, nama_pemateri, topik_materi, tanggal_acara, waktu_acara, nama_file_pdf) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $jenis_undangan, $nama_pemateri, $topik_materi, $tanggal_acara, $waktu_acara, $pdf_filename);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            die('Terjadi error: ' . $e->getMessage());
        }
    } elseif ($action === 'hapus') {
        $id = $_POST['id'];
        // Ambil nama file sebelum menghapus dari DB
        $stmt_select = $conn->prepare("SELECT nama_file_pdf FROM surat_undangan WHERE id = ?");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $file_to_delete = $stmt_select->get_result()->fetch_assoc()['nama_file_pdf'];
        $stmt_select->close();

        // Hapus file dari server
        if ($file_to_delete && file_exists($output_dir . $file_to_delete)) {
            unlink($output_dir . $file_to_delete);
        }

        // Hapus data dari DB
        $stmt_delete = $conn->prepare("DELETE FROM surat_undangan WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    header("Location: sekretaris?page=administrasi/surat_undangan");
    exit();
}

$undangan_list = $conn->query("SELECT * FROM surat_undangan ORDER BY dibuat_pada DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Mulai HTML Konten -->
<div x-data="{ isModalOpen: false, isDeleteModalOpen: false, deleteData: {} }">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-800">Daftar Surat Undangan Pemateri</h1>
        <button @click="isModalOpen = true" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Buat Undangan Baru
        </button>
    </div>

    <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="bg-gray-200">
                    <tr class="text-left font-bold">
                        <th class="px-6 py-3">No</th>
                        <th class="px-6 py-3">Jenis Undangan</th>
                        <th class="px-6 py-3">Nama Pemateri</th>
                        <th class="px-6 py-3">Topik Materi</th>
                        <th class="px-6 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $no = 1;
                    foreach ($undangan_list as $undangan): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 font-semibold">
                                <?php echo htmlspecialchars($undangan['jenis_undangan']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo htmlspecialchars($undangan['nama_pemateri']); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($undangan['topik_materi'] == "Membangun Peradaban Hijau: Upaya LDII Dalam Pelestarian Lingkungan Dan Pencapaian Kedaulatan Pangan Untuk Mewujudkan Islam Rahmatan Lil Alamin") {
                                    echo "Materi Organisasi";
                                } else {
                                    echo htmlspecialchars($undangan['topik_materi']);
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 space-x-3">
                                <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                    <i class="fas fa-eye mr-1"></i>
                                    Lihat
                                </a>
                                <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" download class="text-green-600 hover:underline">
                                    <i class="fas fa-download mr-1"></i>
                                    Download
                                </a>
                                <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($undangan), ENT_QUOTES, 'UTF-8'); ?>" class="text-red-600 hover:underline">
                                    <i class="fas fa-trash-alt mr-1"></i>
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Buat Undangan Baru -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
        <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
            <h3 class="text-2xl font-bold mb-4">Buat Undangan Baru</h3>
            <form x-data="{ jenisUndanganPilihan: 'Nasehat Pembukaan' }" method="POST" action="" class="space-y-4" onsubmit="showLoading()">
                <input type="hidden" name="action" value="buat_undangan">
                <div>
                    <label for="jenis_undangan" class="block text-sm font-medium">Jenis Undangan</label>
                    <!-- --- PERUBAHAN DI SINI: Tambahkan x-model --- -->
                    <select id="jenis_undangan" name="jenis_undangan" x-model="jenisUndanganPilihan" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        <option value="Nasehat Pembukaan">Nasehat Pembukaan</option>
                        <option value="Nasehat Penutupan">Nasehat Penutupan</option>
                        <option value="Nasehat Shubuh">Nasehat Shubuh</option>
                        <option value="Makalah CAI">Makalah CAI</option>
                    </select>
                </div>
                <div><label for="nama_pemateri" class="block text-sm font-medium">Nama Pemateri</label><input type="text" id="nama_pemateri" name="nama_pemateri" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                <div x-show="jenisUndanganPilihan === 'Nasehat Penutupan' || jenisUndanganPilihan === 'Nasehat Shubuh'" x-transition>
                    <label for="topik_materi_text" class="block text-sm font-medium">Tema Materi</label>
                    <input type="text" id="topik_materi_text" name="topik_materi" :required="jenisUndanganPilihan === 'Nasehat Penutupan' || jenisUndanganPilihan === 'Nasehat Shubuh'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div x-show="jenisUndanganPilihan === 'Makalah CAI'" x-transition>
                    <label for="topik_materi_select" class="block text-sm font-medium">Pilih Judul Makalah</label>
                    <select id="topik_materi_select" name="topik_materi" :required="jenisUndanganPilihan === 'Makalah CAI'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        <option value="" disabled selected>-- Pilih Judul --</option>
                        <!-- Tambahkan pilihan makalah di sini -->
                        <option value="Meraih Sukses Pendidikan Generus (Dunia Akhirot)">Materi 1 : Meraih Sukses Pendidikan Generus (Dunia Akhirot)</option>
                        <option value="Mewujudkan Pembiasaan 29 Karakter Luhur Jamaah Dimana Saja Berada">Materi 2 : Mewujudkan Pembiasaan 29 Karakter Luhur Jamaah Dimana Saja Berada</option>
                        <option value="Peran Lima Unsur Dalam Menyukseskan Pembinaan Generasi Penerus">Materi 3 : Peran Lima Unsur Dalam Menyukseskan Pembinaan Generasi Penerus</option>
                        <option value="Bijak Dalam Menghadapi Akhir Zaman">Materi 4 : Bijak Dalam Menghadapi Akhir Zaman</option>
                        <option value="Memberdayakan Generus Untuk Kelestarian Qur'an Hadits Jamaah">Materi 5 : Memberdayakan Generus Untuk Kelestarian Qur'an Hadits Jamaah</option>
                        <option value="Membangun Peradaban Hijau: Upaya LDII Dalam Pelestarian Lingkungan Dan Pencapaian Kedaulatan Pangan Untuk Mewujudkan Islam Rahmatan Lil Alamin">Materi Organisasi</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label for="tanggal_acara" class="block text-sm font-medium">Tanggal Acara</label><input type="date" id="tanggal_acara" name="tanggal_acara" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                    <div><label for="waktu_acara" class="block text-sm font-medium">Waktu Acara</label><input type="text" id="waktu_acara" name="waktu_acara" required placeholder="cth: 09:00 - 11:00 WIB" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                </div>
                <div class="mt-6 flex justify-end space-x-4"><button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Buat & Simpan PDF</button></div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
            <h3 class="text-xl font-bold">Konfirmasi Hapus</h3>
            <p class="mt-2">Yakin ingin menghapus surat untuk <strong x-text="deleteData.nama_pemateri"></strong>? File PDF juga akan dihapus permanen.</p>
            <form method="POST" action="" class="mt-6 flex justify-end space-x-4">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" :value="deleteData.id">
                <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Ya, Hapus</button>
            </form>
        </div>
    </div>
</div>