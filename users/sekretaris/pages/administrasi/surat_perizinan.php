<?php
// Izinkan akses hanya untuk superadmin dan admin
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}

$output_dir = '../../uploads/surat_izin_jadi/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}
$output_dir_web = '../../uploads/surat_izin_jadi/';

// Helper: nama bulan dalam Bahasa Indonesia
function tanggalIndonesia(int $timestamp = 0): string {
    if ($timestamp === 0) $timestamp = time();
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

// Helper: generate PDF dari template HTML menggunakan mPDF
function generateSuratPdf(string $template_path, array $placeholders, string $output_path): void {
    $html = file_get_contents($template_path);
    // Key yang mengandung path file gambar tidak boleh di-htmlspecialchars
    $image_keys = ['logo_kmm', 'logo_ldii', 'logo_cai', 'ttd_ketua'];
    foreach ($placeholders as $key => $value) {
        $safe = in_array($key, $image_keys) ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = str_replace('{{' . $key . '}}', $safe, $html);
    }

    // 2.54cm = 25.4mm di setiap sisi
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 25.4,
        'margin_right'  => 25.4,
        'margin_top'    => 25.4,
        'margin_bottom' => 25.4,
    ]);
    $mpdf->SetTitle('Surat Perizinan CAI XLVII 2026');

    // Watermark logo
    $logo_path = realpath(__DIR__ . '/../../../../assets/images/Logo 1x1.png');
    if ($logo_path && file_exists($logo_path)) {
        $mpdf->SetWatermarkImage($logo_path, 0.12, [80, 80]);
        $mpdf->showWatermarkImage = true;
    }

    $mpdf->WriteHTML($html);
    $mpdf->Output($output_path, \Mpdf\Output\Destination::FILE);
}

// Proses form saat surat baru dibuat atau dihapus
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'buat_surat') {
        $jenis_surat   = $_POST['jenis_surat'];
        $nama_peserta  = $_POST['nama_peserta'];
        $kelompok      = $_POST['kelompok'];
        $detail_data   = [];
        $template_path = '';

        $template_base = realpath(__DIR__ . '/../../../../admin/templates/surat/');

        switch ($jenis_surat) {
            case 'Izin Pulang':
                $template_path = $template_base . '/izin_pulang.html';
                $detail_data = [
                    'nama_peserta'  => $nama_peserta,
                    'kelompok'      => $kelompok,
                    'tanggal_pulang'=> tanggalIndonesia((int)strtotime($_POST['tanggal_pulang'])),
                    'jam_pulang'    => $_POST['jam_pulang'],
                    'alasan'        => $_POST['alasan_pulang'],
                    'tanggal'       => tanggalIndonesia(),
                ];
                break;

            case 'Tidak Ikut CAI':
                $template_path = $template_base . '/tidak_ikut_cai.html';
                $detail_data = [
                    'nama_peserta' => $nama_peserta,
                    'kelompok'     => $kelompok,
                    'alasan'       => $_POST['alasan_tidak_ikut'],
                ];
                break;

            case 'Untuk Instansi':
                $template_path = $template_base . '/untuk_instansi.html';
                $assets = realpath(__DIR__ . '/../../../../assets/images/');
                $detail_data = [
                    'nama_peserta'    => $nama_peserta,
                    'tujuan_instansi' => $_POST['tujuan_instansi'],
                    'tanggal'         => tanggalIndonesia(),
                    'logo_kmm'        => $assets . '/logo_kmm.png',
                    'logo_ldii'       => $assets . '/logo_ldii.png',
                    'ttd_ketua'       => $assets . '/ttd_ketua.png',
                ];
                break;

            default:
                die('Jenis surat tidak dikenal.');
        }

        $pdf_filename = str_replace(' ', '_', $jenis_surat) . '-' . preg_replace('/[^a-zA-Z0-9]/', '_', $nama_peserta) . '_' . time() . '.pdf';
        $output_path  = $output_dir . $pdf_filename;

        try {
            generateSuratPdf($template_path, $detail_data, $output_path);

            // Simpan ke DB (detail_surat menyimpan semua field kecuali nama_peserta & kelompok)
            $detail_for_db = $detail_data;
            unset($detail_for_db['nama_peserta'], $detail_for_db['kelompok']);
            $detail_json = json_encode($detail_for_db);

            $stmt = $conn->prepare("INSERT INTO surat_izin_terbuat (jenis_surat, nama_peserta, kelompok, detail_surat, nama_file_pdf) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $jenis_surat, $nama_peserta, $kelompok, $detail_json, $pdf_filename);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = 'Surat Perizinan berhasil dibuat!';
        } catch (Exception $e) {
            $_SESSION['error_msg'] = 'Terjadi error saat membuat PDF: ' . $e->getMessage();
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
        $_SESSION['success_msg'] = 'Surat Perizinan berhasil dihapus!';
    }

    header("Location: sekretaris?page=administrasi/surat_perizinan");
    exit();
}

$surat_list         = $conn->query("SELECT * FROM surat_izin_terbuat ORDER BY dibuat_pada DESC")->fetch_all(MYSQLI_ASSOC);
$peserta_hadir_list = $conn->query("SELECT nama, kelompok FROM peserta ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Mulai HTML Konten -->
<style>
.form-input {
    display: block; width: 100%;
    padding: 0.5rem 0.75rem;
    margin-top: 0.25rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fff;
}
.form-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.form-input:disabled { background: #f3f4f6; color: #9ca3af; }
.form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 2px; }
</style>

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
            isProcessing: false,

            filterParticipants() {
                this.filteredParticipants = this.allParticipants.filter(p => p.kelompok === this.selectedKelompok);
                this.selectedPeserta = '';
            },
            viewFile(fileUrl) {
                const extension = fileUrl.split('.').pop().toLowerCase();
                if (extension === 'pdf') { window.open(fileUrl, '_blank'); }
                else { this.isViewModalOpen = true; this.viewFileUrl = fileUrl; }
            }
        }
    }
</script>

<div x-data="suratOtomatisData()">

    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Surat Perizinan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola dan buat surat perizinan peserta CAI XLVII 2026</p>
        </div>
        <button @click="isModalOpen = true" class="px-5 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 flex items-center gap-2 shadow-md transition-all">
            <i class="fas fa-plus text-sm"></i> Buat Surat Baru
        </button>
    </div>

    <!-- Tabel -->
    <div class="mt-5 bg-white shadow-md rounded-xl overflow-hidden">
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-file-alt text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Daftar Surat Dibuat</span>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-semibold">
                        <th class="px-5 py-3">No</th>
                        <th class="px-5 py-3">Jenis Surat</th>
                        <th class="px-5 py-3">Nama Peserta</th>
                        <th class="px-5 py-3">Kelompok</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $no = 1; foreach ($surat_list as $surat): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 text-gray-500"><?php echo $no++; ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold
                                <?php echo match($surat['jenis_surat']) {
                                    'Izin Pulang'    => 'bg-yellow-100 text-yellow-800',
                                    'Tidak Ikut CAI' => 'bg-red-100 text-red-800',
                                    'Untuk Instansi' => 'bg-blue-100 text-blue-800',
                                    default          => 'bg-gray-100 text-gray-700'
                                }; ?>">
                                <?php echo htmlspecialchars($surat['jenis_surat']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-gray-800"><?php echo htmlspecialchars($surat['nama_peserta']); ?></td>
                        <td class="px-5 py-3.5 text-gray-600"><?php echo htmlspecialchars($surat['kelompok']); ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <button @click="viewFile('<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>')" class="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium transition-colors"><i class="fas fa-eye text-xs"></i> Lihat</button>
                                <a href="<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>" download class="flex items-center gap-1 text-emerald-600 hover:text-emerald-800 font-medium transition-colors"><i class="fas fa-download text-xs"></i> Unduh</a>
                                <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($surat), ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-1 text-red-500 hover:text-red-700 font-medium transition-colors"><i class="fas fa-trash-alt text-xs"></i> Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($surat_list)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Belum ada surat yang dibuat</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tampilan Mobile (Cards) -->
        <div class="block md:hidden border-t border-gray-100 divide-y divide-gray-100">
            <?php foreach ($surat_list as $surat): ?>
            <div class="p-4 bg-white hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start mb-2">
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold
                        <?php echo match($surat['jenis_surat']) {
                            'Izin Pulang'    => 'bg-yellow-100 text-yellow-800',
                            'Tidak Ikut CAI' => 'bg-red-100 text-red-800',
                            'Untuk Instansi' => 'bg-blue-100 text-blue-800',
                            default          => 'bg-gray-100 text-gray-700'
                        }; ?>">
                        <?php echo htmlspecialchars($surat['jenis_surat']); ?>
                    </span>
                    <span class="text-xs text-gray-400 font-medium whitespace-nowrap"><i class="fas fa-clock mr-1"></i><?php echo date('d/m/Y', strtotime($surat['dibuat_pada'] ?? 'now')); ?></span>
                </div>
                <h3 class="font-bold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($surat['nama_peserta']); ?></h3>
                <p class="text-sm text-gray-600 mb-4"><i class="fas fa-users mr-1.5 text-blue-500"></i>Kelompok: <?php echo htmlspecialchars($surat['kelompok']); ?></p>
                <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                    <button @click="viewFile('<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>')" class="flex-1 flex justify-center items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg font-semibold text-xs hover:bg-blue-100 transition-colors"><i class="fas fa-eye"></i> Lihat</button>
                    <a href="<?php echo $output_dir_web . htmlspecialchars($surat['nama_file_pdf']); ?>" download class="flex-1 flex justify-center items-center gap-1.5 px-3 py-2 bg-emerald-50 text-emerald-600 rounded-lg font-semibold text-xs hover:bg-emerald-100 transition-colors"><i class="fas fa-download"></i> Unduh</a>
                    <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($surat), ENT_QUOTES, 'UTF-8'); ?>" class="flex-none flex justify-center items-center px-3 py-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"><i class="fas fa-trash-alt text-sm"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($surat_list)): ?>
            <div class="p-8 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Belum ada surat yang dibuat</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Buat Surat Baru -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2"><i class="fas fa-file-signature text-white"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Buat Surat Baru</h3>
                        <p class="text-blue-100 text-xs">Isi data untuk menghasilkan PDF otomatis</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-white hover:text-blue-200 text-xl leading-none">&times;</button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 max-h-[75vh] overflow-y-auto">
                <form method="POST" action="" class="space-y-4" @submit="isProcessing = true">
                    <input type="hidden" name="action" value="buat_surat">

                    <div>
                        <label for="jenis_surat" class="form-label"><i class="fas fa-list-alt mr-1 text-blue-500"></i>Pilih Template Surat</label>
                        <select id="jenis_surat" name="jenis_surat" x-model="templatePilihan" class="form-input">
                            <option>Izin Pulang</option>
                            <option>Tidak Ikut CAI</option>
                            <option>Untuk Instansi</option>
                        </select>
                    </div>

                    <!-- Izin Pulang -->
                    <div x-show="templatePilihan === 'Izin Pulang'" x-transition class="space-y-3 border-t border-blue-100 pt-4">
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Data Surat Izin Pulang</p>
                        <div>
                            <label for="kelompok_1" class="form-label">Pilih Kelompok</label>
                            <select id="kelompok_1" name="kelompok" x-model="selectedKelompok" @change="filterParticipants()" :required="templatePilihan === 'Izin Pulang'" class="form-input">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option><option>Gedongkuning</option><option>Jombor</option><option>Sunten</option>
                            </select>
                        </div>
                        <div>
                            <label for="nama_peserta_1" class="form-label">Pilih Nama Peserta</label>
                            <select id="nama_peserta_1" name="nama_peserta" x-model="selectedPeserta" :disabled="!selectedKelompok" :required="templatePilihan === 'Izin Pulang'" class="form-input">
                                <option value="" disabled>-- Pilih Peserta --</option>
                                <template x-for="p in filteredParticipants"><option :value="p.nama" x-text="p.nama"></option></template>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="tanggal_pulang" class="form-label">Tanggal Pulang</label>
                                <input type="date" id="tanggal_pulang" name="tanggal_pulang" :required="templatePilihan === 'Izin Pulang'" class="form-input">
                            </div>
                            <div>
                                <label for="jam_pulang" class="form-label">Jam Pulang</label>
                                <input type="time" id="jam_pulang" name="jam_pulang" :required="templatePilihan === 'Izin Pulang'" class="form-input">
                            </div>
                        </div>
                        <div>
                            <label for="alasan_pulang" class="form-label">Alasan</label>
                            <textarea id="alasan_pulang" name="alasan_pulang" rows="2" :required="templatePilihan === 'Izin Pulang'" class="form-input resize-none" placeholder="Tulis alasan izin pulang..."></textarea>
                        </div>
                    </div>

                    <!-- Tidak Ikut CAI -->
                    <div x-show="templatePilihan === 'Tidak Ikut CAI'" x-transition class="space-y-3 border-t border-blue-100 pt-4">
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Data Surat Tidak Ikut CAI</p>
                        <div>
                            <label for="nama_peserta_2" class="form-label">Nama Peserta</label>
                            <input type="text" id="nama_peserta_2" name="nama_peserta" :required="templatePilihan === 'Tidak Ikut CAI'" class="form-input" placeholder="Ketik nama peserta...">
                        </div>
                        <div>
                            <label for="kelompok_2" class="form-label">Pilih Kelompok</label>
                            <select id="kelompok_2" name="kelompok" :required="templatePilihan === 'Tidak Ikut CAI'" class="form-input">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option><option>Gedongkuning</option><option>Jombor</option><option>Sunten</option>
                            </select>
                        </div>
                        <div>
                            <label for="alasan_tidak_ikut" class="form-label">Alasan Tidak Mengikuti</label>
                            <textarea id="alasan_tidak_ikut" name="alasan_tidak_ikut" rows="3" :required="templatePilihan === 'Tidak Ikut CAI'" class="form-input resize-none" placeholder="Tulis alasan..."></textarea>
                        </div>
                    </div>

                    <!-- Untuk Instansi -->
                    <div x-show="templatePilihan === 'Untuk Instansi'" x-transition class="space-y-3 border-t border-blue-100 pt-4">
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Data Surat Untuk Instansi</p>
                        <div>
                            <label for="kelompok_3" class="form-label">Pilih Kelompok</label>
                            <select id="kelompok_3" name="kelompok" x-model="selectedKelompok" @change="filterParticipants()" :required="templatePilihan === 'Untuk Instansi'" class="form-input">
                                <option value="" disabled selected>-- Pilih Kelompok --</option>
                                <option>Bintaran</option><option>Gedongkuning</option><option>Jombor</option><option>Sunten</option>
                            </select>
                        </div>
                        <div>
                            <label for="nama_peserta_3" class="form-label">Pilih Nama Peserta</label>
                            <select id="nama_peserta_3" name="nama_peserta" x-model="selectedPeserta" :disabled="!selectedKelompok" :required="templatePilihan === 'Untuk Instansi'" class="form-input">
                                <option value="" disabled>-- Pilih Peserta --</option>
                                <template x-for="p in filteredParticipants"><option :value="p.nama" x-text="p.nama"></option></template>
                            </select>
                        </div>
                        <div>
                            <label for="tujuan_instansi" class="form-label">Tujuan Sekolah / Instansi</label>
                            <input type="text" id="tujuan_instansi" name="tujuan_instansi" :required="templatePilihan === 'Untuk Instansi'" class="form-input" placeholder="Nama sekolah / instansi tujuan...">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button>
                        <button type="submit" :disabled="isProcessing" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 disabled:opacity-60 transition-colors shadow">
                            <span x-show="!isProcessing"><i class="fas fa-file-pdf mr-1"></i>Buat &amp; Simpan PDF</span>
                            <span x-show="isProcessing" x-cloak class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-red-600 px-6 py-4 flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-full p-2"><i class="fas fa-exclamation-triangle text-white text-sm"></i></div>
                <h3 class="text-lg font-bold text-white">Konfirmasi Hapus</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600">Yakin ingin menghapus surat untuk <strong class="text-gray-800" x-text="deleteData.nama_peserta"></strong>? File PDF juga akan dihapus secara permanen.</p>
                <form method="POST" action="" class="mt-5 flex justify-end gap-3">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id" :value="deleteData.id">
                    <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

</div>


