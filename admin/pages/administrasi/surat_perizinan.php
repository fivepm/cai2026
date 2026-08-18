<?php
// Izinkan akses hanya untuk superadmin dan admin
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../login");
    exit();
}

$output_dir = '../uploads/surat_izin_jadi/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}
$output_dir_web = '../uploads/surat_izin_jadi/';

// Proses form saat surat baru dibuat atau dihapus
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'buat_surat') {
        // Setup Google Client
        $credentials_path = '../credentials/credential_cai2025_btp1.json';
        $token_path = '../credentials/token.json';

        $client = new Google\Client();
        $client->setAuthConfig($credentials_path);
        $client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/cai25/oauth2callback.php'); // Sesuaikan path jika perlu
        $client->setScopes([Google\Service\Docs::DOCUMENTS, Google\Service\Drive::DRIVE]);
        $client->setAccessType('offline');

        if (!file_exists($token_path)) {
            die("Aplikasi belum diotorisasi. Silakan otorisasi terlebih dahulu.");
        }

        $accessToken = json_decode(file_get_contents($token_path), true);
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($token_path, json_encode($client->getAccessToken()));
            } else {
                die("Token sudah kedaluwarsa dan tidak bisa diperbarui. Silakan otorisasi ulang.");
            }
        }

        $jenis_surat = $_POST['jenis_surat'];
        $nama_peserta = $_POST['nama_peserta'];
        $kelompok = $_POST['kelompok'];
        $requests = [];
        $detail_data = [];
        $template_id = '';

        $common_requests = [
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{nama_peserta}}'], 'replaceText' => $nama_peserta]]),
            new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{kelompok}}'], 'replaceText' => $kelompok]]),
        ];

        switch ($jenis_surat) {
            case 'Izin Pulang':
                $template_id = '1Pfcy5DdgctCBI0bNSgpBbHgzPRThxdP6k6D7SeU7GrE';
                $detail_data = ['tanggal_pulang' => $_POST['tanggal_pulang'], 'jam_pulang' => $_POST['jam_pulang'], 'alasan' => $_POST['alasan_pulang'], 'tanggal' => date('d')];
                $requests = array_merge($common_requests, [
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tanggal_pulang}}'], 'replaceText' => $detail_data['tanggal_pulang']]]),
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{jam_pulang}}'], 'replaceText' => $detail_data['jam_pulang']]]),
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{alasan}}'], 'replaceText' => $detail_data['alasan']]]),
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tanggal}}'], 'replaceText' => $detail_data['tanggal']]]),
                ]);
                break;
            case 'Tidak Ikut CAI':
                $template_id = '1R3Z2g7INfOf6xzY9JqsQzoDGAycYBp19d-hAqva6-aI';
                $detail_data = ['alasan' => $_POST['alasan_tidak_ikut']];
                $requests = array_merge($common_requests, [
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{alasan}}'], 'replaceText' => $detail_data['alasan']]]),
                ]);
                break;
            case 'Untuk Instansi':
                $template_id = '1pttIwhmEbPqu1-WARJM2qtBKVcghuhjjipn8dhBKI4g';
                $detail_data = ['tujuan_instansi' => $_POST['tujuan_instansi'], 'keperluan' => $_POST['keperluan_instansi'], 'tanggal' => date('d M Y')];
                $requests = array_merge($common_requests, [
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tujuan_instansi}}'], 'replaceText' => $detail_data['tujuan_instansi']]]),
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{keperluan}}'], 'replaceText' => $detail_data['keperluan']]]),
                    new Google\Service\Docs\Request(['replaceAllText' => ['containsText' => ['text' => '{{tanggal}}'], 'replaceText' => $detail_data['tanggal']]]),
                ]);
                break;
        }

        try {
            $driveService = new Google\Service\Drive($client);
            $docsService = new Google\Service\Docs($client);

            $copy = new Google\Service\Drive\DriveFile(['name' => "$jenis_surat - $nama_peserta"]);
            $copiedFile = $driveService->files->copy($template_id, $copy);
            $newDocumentId = $copiedFile->getId();

            $batchUpdateRequest = new Google\Service\Docs\BatchUpdateDocumentRequest(['requests' => $requests]);
            $docsService->documents->batchUpdate($newDocumentId, $batchUpdateRequest);

            $pdfContent = $driveService->files->export($newDocumentId, 'application/pdf', ['alt' => 'media']);
            $driveService->files->delete($newDocumentId);

            $pdf_filename = str_replace(' ', '_', $jenis_surat) . '-' . preg_replace('/[^a-zA-Z0-9]/', '_', $nama_peserta) . '_' . time() . '.pdf';
            file_put_contents($output_dir . $pdf_filename, $pdfContent->getBody()->getContents());

            $detail_json = json_encode($detail_data);
            $stmt = $conn->prepare("INSERT INTO surat_izin_terbuat (jenis_surat, nama_peserta, kelompok, detail_surat, nama_file_pdf) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $jenis_surat, $nama_peserta, $kelompok, $detail_json, $pdf_filename);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            die('Terjadi error: ' . $e->getMessage());
        }
    } elseif ($action === 'hapus') {
        $id = $_POST['id'];
        $stmt_select = $conn->prepare("SELECT nama_file_pdf FROM surat_izin_terbuat WHERE id = ?");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $file_to_delete = $stmt_select->get_result()->fetch_assoc()['nama_file_pdf'];
        $stmt_select->close();

        if ($file_to_delete && file_exists($output_dir . $file_to_delete)) {
            unlink($output_dir . $file_to_delete);
        }

        $stmt_delete = $conn->prepare("DELETE FROM surat_izin_terbuat WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    header("Location: admin?page=administrasi/surat_perizinan");
    exit();
}

// Logika untuk otorisasi OAuth 2.0
$credentials_path = '../credentials/credential_cai2025_btp1.json';
$token_path = '../credentials/token.json';
$client = new Google\Client();
$client->setAuthConfig($credentials_path);
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/cai25/oauth2callback.php'); // Sesuaikan path
$client->setScopes([Google\Service\Docs::DOCUMENTS, Google\Service\Drive::DRIVE]);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');
$is_authorized = false;
if (file_exists($token_path)) {
    $accessToken = json_decode(file_get_contents($token_path), true);
    $client->setAccessToken($accessToken);
    $is_authorized = true;
}
if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($token_path, json_encode($client->getAccessToken()));
    } else {
        $is_authorized = false;
    }
}

$surat_list = $conn->query("SELECT * FROM surat_izin_terbuat ORDER BY dibuat_pada DESC")->fetch_all(MYSQLI_ASSOC);
$peserta_hadir_list = $conn->query("SELECT nama, kelompok FROM peserta ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Mulai HTML Konten -->
<?php if (!$is_authorized): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
        <p class="font-bold">Aksi Diperlukan</p>
        <p>Aplikasi ini perlu izin untuk mengakses Google Drive dan Google Docs Anda. Silakan hubungkan akun Google Anda untuk melanjutkan.</p>
        <a href="<?php echo $client->createAuthUrl(); ?>" class="mt-4 inline-block bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
            <i class="fab fa-google mr-2"></i> Hubungkan Akun Google
        </a>
    </div>
<?php else: ?>

    <script>
        function suratOtomatisData() {
            return {
                isModalOpen: false,
                templatePilihan: 'Izin Pulang',
                allParticipants: <?php echo json_encode($peserta_hadir_list); ?>,
                filteredParticipants: [],
                selectedKelompok: '',
                selectedPeserta: '',
                isViewModalOpen: false,
                viewFileUrl: '',
                isDeleteModalOpen: false,
                deleteData: {},
                isProcessing: false, // State untuk loading

                filterParticipants() {
                    this.filteredParticipants = this.allParticipants.filter(p => p.kelompok === this.selectedKelompok);
                    this.selectedPeserta = '';
                },
                viewFile(fileUrl) {
                    const extension = fileUrl.split('.').pop().toLowerCase();
                    if (extension === 'pdf') {
                        window.open(fileUrl, '_blank');
                    } else {
                        this.isViewModalOpen = true;
                        this.viewFileUrl = fileUrl;
                    }
                }
            }
        }
    </script>

    <div x-data="suratOtomatisData()">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-semibold text-gray-800">Daftar Surat Perizinan</h1>
            <button @click="isModalOpen = true" class="px-4 py-2 font-semibold text-white bg-red-600 rounded-md hover:bg-red-700 flex items-center"><i class="fas fa-plus mr-2"></i>Buat Surat Baru</button>
        </div>

        <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="bg-gray-200">
                        <tr class="text-left font-bold">
                            <th class="px-6 py-3">No</th>
                            <th class="px-6 py-3">Jenis Surat</th>
                            <th class="px-6 py-3">Nama Peserta</th>
                            <th class="px-6 py-3">Kelompok</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $no = 1;
                        foreach ($surat_list as $surat): ?><tr>
                                <td class="px-6 py-4"><?php echo $no++; ?></td>
                                <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($surat['jenis_surat']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($surat['nama_peserta']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($surat['kelompok']); ?></td>
                                <td class="px-6 py-4 space-x-3"><button @click="viewFile('<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>')" class="text-blue-600 hover:underline"><i class="fas fa-eye mr-1"></i>Lihat</button><a href="<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>" download class="text-green-600 hover:underline"><i class="fas fa-download mr-1"></i>Download</a><button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($surat), ENT_QUOTES, 'UTF-8'); ?>" class="text-red-600 hover:underline"><i class="fas fa-trash-alt mr-1"></i>Hapus</button></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>

        <!-- Modal Buat Surat Baru -->
        <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak>
            <div @click.away="isModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4">
                <h3 class="text-2xl font-bold mb-4">Buat Surat Baru</h3>
                <form method="POST" action="" class="space-y-4" @submit="isProcessing = true">
                    <input type="hidden" name="action" value="buat_surat">
                    <div><label for="jenis_surat" class="block text-sm font-medium">Pilih Template Surat</label><select id="jenis_surat" name="jenis_surat" x-model="templatePilihan" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            <option>Izin Pulang</option>
                            <option>Tidak Ikut CAI</option>
                            <option>Untuk Instansi</option>
                        </select></div>

                    <div x-show="templatePilihan === 'Izin Pulang'" x-transition class="space-y-4 border-t pt-4">
                        <div><label for="kelompok_1" class="block text-sm font-medium">Pilih Kelompok</label><select id="kelompok_1" name="kelompok" x-model="selectedKelompok" @change="filterParticipants()" :required="templatePilihan === 'Izin Pulang'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option>
                                <option>Gedongkuning</option>
                                <option>Jombor</option>
                                <option>Sunten</option>
                            </select></div>
                        <div><label for="nama_peserta_1" class="block text-sm font-medium">Pilih Nama Peserta</label><select id="nama_peserta_1" name="nama_peserta" x-model="selectedPeserta" :disabled="!selectedKelompok" :required="templatePilihan === 'Izin Pulang'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100">
                                <option value="" disabled>-- Pilih Peserta --</option><template x-for="p in filteredParticipants">
                                    <option :value="p.nama" x-text="p.nama"></option>
                                </template>
                            </select></div>
                        <div><label for="tanggal_pulang" class="block text-sm font-medium">Tanggal Pulang</label><input type="date" id="tanggal_pulang" name="tanggal_pulang" :required="templatePilihan === 'Izin Pulang'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                        <div><label for="jam_pulang" class="block text-sm font-medium">Jam Pulang</label><input type="time" id="jam_pulang" name="jam_pulang" :required="templatePilihan === 'Izin Pulang'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                        <div><label for="alasan_pulang" class="block text-sm font-medium">Alasan</label><textarea id="alasan_pulang" name="alasan_pulang" rows="2" :required="templatePilihan === 'Izin Pulang'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea></div>
                    </div>
                    <div x-show="templatePilihan === 'Tidak Ikut CAI'" x-transition class="space-y-4 border-t pt-4">
                        <div><label for="nama_peserta_2" class="block text-sm font-medium">Nama Peserta</label><input type="text" id="nama_peserta_2" name="nama_peserta" :required="templatePilihan === 'Tidak Ikut CAI'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" placeholder="Ketik nama peserta..."></div>
                        <div><label for="kelompok_2" class="block text-sm font-medium">Pilih Kelompok</label><select id="kelompok_2" name="kelompok" :required="templatePilihan === 'Tidak Ikut CAI'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option>
                                <option>Gedongkuning</option>
                                <option>Jombor</option>
                                <option>Sunten</option>
                            </select></div>
                        <div><label for="alasan_tidak_ikut" class="block text-sm font-medium">Alasan Tidak Mengikuti</label><textarea id="alasan_tidak_ikut" name="alasan_tidak_ikut" rows="3" :required="templatePilihan === 'Tidak Ikut CAI'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea></div>
                    </div>
                    <div x-show="templatePilihan === 'Untuk Instansi'" x-transition class="space-y-4 border-t pt-4">
                        <div><label for="kelompok_3" class="block text-sm font-medium">Pilih Kelompok</label><select id="kelompok_3" name="kelompok" x-model="selectedKelompok" @change="filterParticipants()" :required="templatePilihan === 'Untuk Instansi'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option>
                                <option>Gedongkuning</option>
                                <option>Jombor</option>
                                <option>Sunten</option>
                            </select></div>
                        <div><label for="nama_peserta_3" class="block text-sm font-medium">Pilih Nama Peserta</label><select id="nama_peserta_3" name="nama_peserta" x-model="selectedPeserta" :disabled="!selectedKelompok" :required="templatePilihan === 'Untuk Instansi'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm disabled:bg-gray-100">
                                <option value="" disabled>-- Pilih Peserta --</option><template x-for="p in filteredParticipants">
                                    <option :value="p.nama" x-text="p.nama"></option>
                                </template>
                            </select></div>
                        <div><label for="tujuan_instansi" class="block text-sm font-medium">Tujuan Sekolah / Instansi</label><input type="text" id="tujuan_instansi" name="tujuan_instansi" :required="templatePilihan === 'Untuk Instansi'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></div>
                        <div><label for="keperluan_instansi" class="block text-sm font-medium">Keperluan</label><textarea id="keperluan_instansi" name="keperluan_instansi" rows="2" :required="templatePilihan === 'Untuk Instansi'" class="mt-1 w-full border-gray-300 rounded-md shadow-sm"></textarea></div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button>
                        <button type="submit" :disabled="isProcessing" class="px-4 py-2 bg-red-600 text-white rounded-md flex items-center justify-center w-48 disabled:bg-red-300">
                            <span x-show="!isProcessing">Buat & Simpan PDF</span>
                            <span x-show="isProcessing" x-cloak class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Memproses...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="isViewModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
            <div @click.away="isViewModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-xl p-4 mx-4 relative"><button @click="isViewModalOpen = false" class="absolute -top-3 -right-3 bg-red-600 text-white rounded-full h-8 w-8 flex items-center justify-center">&times;</button><img :src="viewFileUrl" alt="Tampilan File" class="w-full h-auto max-h-[80vh] object-contain"></div>
        </div>
        <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" x-cloak>
            <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4">
                <h3 class="text-xl font-bold">Konfirmasi Hapus</h3>
                <p class="mt-2">Yakin ingin menghapus surat untuk <strong x-text="deleteData.nama_peserta"></strong>? File PDF juga akan dihapus permanen.</p>
                <form method="POST" action="" class="mt-6 flex justify-end space-x-4"><input type="hidden" name="action" value="hapus"><input type="hidden" name="id" :value="deleteData.id"><button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-200 rounded-md">Batal</button><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md">Ya, Hapus</button></form>
            </div>
        </div>
    </div>
<?php endif; ?>