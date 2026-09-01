<?php
// Izinkan akses hanya untuk superadmin dan admin
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../login");
    exit();
}

$output_dir = '../uploads/surat_undangan/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}
$output_dir_web = '../uploads/surat_undangan/';

// Helper: nama bulan dalam Bahasa Indonesia
if (!function_exists('tanggalIndonesia')) {
    function tanggalIndonesia(int $timestamp = 0): string {
        if ($timestamp === 0) $timestamp = time();
        $bulan = ['Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
        return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
    }
}

// Helper: generate PDF dari template HTML menggunakan mPDF
function generateUndanganPdf(string $template_path, array $placeholders, string $output_path): void {
    $html = file_get_contents($template_path);
    // Key yang mengandung path file gambar tidak boleh di-htmlspecialchars
    $image_keys = ['logo_kmm', 'logo_ldii', 'logo_cai', 'ttd_ketua', 'baris_topik_materi', 'baris_jadwal', 'baris_materi_row'];
    foreach ($placeholders as $key => $value) {
        $safe = in_array($key, $image_keys) ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = str_replace('{{' . $key . '}}', $safe, $html);
    }

    // 2.54cm = 25.4mm di setiap sisi
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 20,
        'margin_right'  => 20,
        'margin_top'    => 18,
        'margin_bottom' => 18,
        'autoScriptToLang' => true,
        'autoLangToFont'   => true,
    ]);
    $mpdf->SetTitle('Surat Undangan Pemateri CAI XLVII 2026');

    // Watermark logo
    $logo_path = realpath(__DIR__ . '/../../../assets/images/Logo 1x1.png');
    if ($logo_path && file_exists($logo_path)) {
        $mpdf->SetWatermarkImage($logo_path, 0.12, [80, 80]);
        $mpdf->showWatermarkImage = true;
    }

    $mpdf->WriteHTML($html);

    // Gabungkan dengan Rundown Pemateri.pdf
    $template_dir = dirname($template_path);
    $rundown_path = $template_dir . '/Rundown Pemateri.pdf';
    if (file_exists($rundown_path)) {
        $pagecount = $mpdf->SetSourceFile($rundown_path);
        for ($i = 1; $i <= $pagecount; $i++) {
            $mpdf->AddPage();
            $tplId = $mpdf->ImportPage($i);
            $mpdf->UseTemplate($tplId);
        }
    }

    $mpdf->Output($output_path, \Mpdf\Output\Destination::FILE);
}

// Proses form saat undangan baru dibuat atau dihapus
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'buat_undangan') {
        $jenis_undangan = $_POST['jenis_undangan'];
        $nama_pemateri  = $_POST['nama_pemateri'];
        $tanggal_surat  = tanggalIndonesia();

        $template_path = realpath(__DIR__ . '/../../templates/surat/undangan_pemateri.html');
        $assets        = realpath(__DIR__ . '/../../../assets/images/');

        if ($jenis_undangan === 'Makalah CAI') {
            // --- Makalah CAI: setiap judul punya jadwal sendiri ---
            $materi_items = [];
            $judul_all   = $_POST['topik_judul']   ?? [];
            $enabled_all = $_POST['topik_enabled'] ?? [];
            $tgl_all     = $_POST['topik_tanggal'] ?? [];
            $wkt_all     = $_POST['topik_waktu']   ?? [];

            foreach ($judul_all as $i => $judul) {
                if (isset($enabled_all[$i])) {
                    $materi_items[] = [
                        'judul'   => trim($judul),
                        'tanggal' => $tgl_all[$i] ?? '',
                        'waktu'   => $wkt_all[$i] ?? '',
                    ];
                }
            }

            $topik_materi  = json_encode($materi_items, JSON_UNESCAPED_UNICODE);
            $tanggal_acara = null;
            $waktu_acara   = null;

            // Urutkan berdasarkan tanggal
            usort($materi_items, fn($a, $b) => strtotime($a['tanggal']) <=> strtotime($b['tanggal']));

            $baris_materi_row = ''; // Makalah CAI: baris Materi dihilangkan

            // Build baris PDF
            $baris_topik = '';
            $count_materi = count($materi_items);

            if ($count_materi === 1) {
                // Satu judul: layout sederhana (judul, tanggal, waktu)
                $item = $materi_items[0];
                $tgl  = !empty($item['tanggal']) ? tanggalIndonesia((int)strtotime($item['tanggal'])) : '-';
                $wkt  = htmlspecialchars($item['waktu'], ENT_QUOTES, 'UTF-8');
                $baris_topik .= "<tr><td class=\"label\">Judul / Tema</td><td class=\"sep\">:</td><td class=\"val\">" . htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8') . "</td></tr>";
                $baris_topik .= "<tr><td class=\"label\">Tanggal</td><td class=\"sep\">:</td><td class=\"val\">" . $tgl . "</td></tr>";
                $baris_topik .= "<tr><td class=\"label\">Waktu</td><td class=\"sep\">:</td><td class=\"val\">" . $wkt . "</td></tr>";
            } else {
                // Lebih dari satu judul: tiap judul dalam blok sendiri
                foreach ($materi_items as $i => $item) {
                    $tgl  = !empty($item['tanggal']) ? tanggalIndonesia((int)strtotime($item['tanggal'])) : '-';
                    $wkt  = htmlspecialchars($item['waktu'], ENT_QUOTES, 'UTF-8');
                    // Garis pemisah sebelum blok judul ke-2 dst
                    if ($i > 0) {
                        $baris_topik .= "<tr><td colspan='3' style='padding:3pt 0 2pt;'><div style='border-top:1px solid #bbb;margin:0;'></div></td></tr>";
                    }
                    // Header judul ke-N (centered, dashes kiri-kanan)
                    $baris_topik .= "<tr><td colspan='3' style='text-align:center;font-weight:bold;padding:3pt 0 1pt;letter-spacing:0.5pt;'>&mdash;&mdash; Judul " . ($i + 1) . " &mdash;&mdash;</td></tr>";
                    $baris_topik .= "<tr><td class='label'>Judul / Tema</td><td class='sep'>:</td><td class='val' style='text-align:justify;'>" . htmlspecialchars($item['judul'], ENT_QUOTES, 'UTF-8') . "</td></tr>";
                    $baris_topik .= "<tr><td class='label'>Tanggal</td><td class='sep'>:</td><td class='val'>" . $tgl . "</td></tr>";
                    $baris_topik .= "<tr><td class='label'>Waktu</td><td class='sep'>:</td><td class='val'>" . $wkt . "</td></tr>";
                }
            }
            $baris_jadwal = ''; // sudah embedded per materi

        } else {
            // --- Nasehat: satu jadwal global ---
            $topik_materi  = '';
            $tanggal_acara = $_POST['tanggal_acara'] ?? '';
            $waktu_acara   = $_POST['waktu_acara']   ?? '';

            $baris_topik  = ''; // tidak ada judul
            $tgl_display  = tanggalIndonesia((int)strtotime($tanggal_acara));
            $baris_materi_row = "<tr><td class=\"label\">Materi</td><td class=\"sep\">:</td><td class=\"val\">" . htmlspecialchars($jenis_undangan, ENT_QUOTES, 'UTF-8') . "</td></tr>";
            $baris_jadwal = "<tr><td class=\"label\">Tanggal</td><td class=\"sep\">:</td><td class=\"val\">" . $tgl_display . "</td></tr>"
                          . "<tr><td class=\"label\">Waktu</td><td class=\"sep\">:</td><td class=\"val\">" . htmlspecialchars($waktu_acara, ENT_QUOTES, 'UTF-8') . "</td></tr>";
        }

        $placeholders = [
            'nama_pemateri'      => $nama_pemateri,
            'jenis_undangan'     => $jenis_undangan,
            'baris_materi_row'   => $baris_materi_row,
            'baris_topik_materi' => $baris_topik,
            'baris_jadwal'       => $baris_jadwal,
            'tanggal_surat'      => $tanggal_surat,
            'logo_cai'           => $assets . '/Logo 1x1.png',
            'logo_kmm'           => $assets . '/logo_kmm.png',
            'ttd_ketua'          => $assets . '/ttd_ketua.png',
        ];

        $pdf_filename = 'Undangan-' . preg_replace('/[^a-zA-Z0-9]/', '_', $nama_pemateri) . '_' . time() . '.pdf';
        $output_path  = $output_dir . $pdf_filename;

        try {
            generateUndanganPdf($template_path, $placeholders, $output_path);

            $stmt = $conn->prepare("INSERT INTO surat_undangan (jenis_undangan, nama_pemateri, topik_materi, tanggal_acara, waktu_acara, nama_file_pdf) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $jenis_undangan, $nama_pemateri, $topik_materi, $tanggal_acara, $waktu_acara, $pdf_filename);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = 'Surat Undangan berhasil dibuat!';
        } catch (Exception $e) {
            $_SESSION['error_msg'] = 'Terjadi error saat membuat PDF: ' . $e->getMessage();
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
        $_SESSION['success_msg'] = 'Surat Undangan berhasil dihapus!';
    }

    header("Location: admin?page=administrasi/surat_undangan");
    exit();
}

$undangan_list = $conn->query("SELECT * FROM surat_undangan ORDER BY dibuat_pada DESC")->fetch_all(MYSQLI_ASSOC);
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
    box-sizing: border-box;
}
.form-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 2px; }
</style>

<div x-data="{ isModalOpen: false, isDeleteModalOpen: false, deleteData: {} }">

    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Surat Undangan Pemateri</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola dan buat surat undangan pemateri CAI XLVII 2026</p>
        </div>
        <button @click="isModalOpen = true" class="px-5 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 flex items-center gap-2 shadow-md transition-all">
            <i class="fas fa-plus text-sm"></i> Buat Undangan Baru
        </button>
    </div>

    <!-- Tabel -->
    <div class="mt-5 bg-white shadow-md rounded-xl overflow-hidden">
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-envelope-open-text text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Daftar Undangan Dibuat</span>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-semibold">
                        <th class="px-5 py-3">No</th>
                        <th class="px-5 py-3">Jenis Undangan</th>
                        <th class="px-5 py-3">Nama Pemateri</th>
                        <th class="px-5 py-3">Topik Materi</th>
                        <th class="px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $no = 1; foreach ($undangan_list as $undangan): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 text-gray-500"><?php echo $no++; ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($undangan['jenis_undangan']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 font-medium text-gray-800"><?php echo htmlspecialchars($undangan['nama_pemateri']); ?></td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <?php
                            $topik_raw = $undangan['topik_materi'];
                            $decoded   = json_decode($topik_raw, true);
                            if (is_array($decoded)) {
                                // Format JSON (Makalah CAI multi-judul)
                                $lines = [];
                                foreach ($decoded as $idx => $item) {
                                    $lines[] = '<span class="block text-xs text-gray-500 font-medium mb-0.5">Judul ' . ($idx + 1) . '</span><span class="block">' . htmlspecialchars($item['judul']) . '</span>';
                                }
                                echo implode('<div class="my-1 border-t border-gray-100"></div>', $lines);
                            } elseif ($topik_raw === "Membangun Peradaban Hijau: Upaya LDII Dalam Pelestarian Lingkungan Dan Pencapaian Kedaulatan Pangan Untuk Mewujudkan Islam Rahmatan Lil Alamin") {
                                echo 'Materi Organisasi';
                            } else {
                                echo htmlspecialchars($topik_raw);
                            }
                            ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" target="_blank" class="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium transition-colors"><i class="fas fa-eye text-xs"></i> Lihat</a>
                                <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" download class="flex items-center gap-1 text-emerald-600 hover:text-emerald-800 font-medium transition-colors"><i class="fas fa-download text-xs"></i> Unduh</a>
                                <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($undangan), ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-1 text-red-500 hover:text-red-700 font-medium transition-colors"><i class="fas fa-trash-alt text-xs"></i> Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($undangan_list)): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Belum ada undangan yang dibuat</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tampilan Mobile (Cards) -->
        <div class="block md:hidden border-t border-gray-100 divide-y divide-gray-100">
            <?php foreach ($undangan_list as $undangan): ?>
            <div class="p-4 bg-white hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start mb-2">
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        <?php echo htmlspecialchars($undangan['jenis_undangan']); ?>
                    </span>
                    <span class="text-xs text-gray-400 font-medium whitespace-nowrap"><i class="fas fa-clock mr-1"></i><?php echo date('d/m/Y', strtotime($undangan['dibuat_pada'] ?? 'now')); ?></span>
                </div>
                <h3 class="font-bold text-gray-800 text-lg mb-1"><?php echo htmlspecialchars($undangan['nama_pemateri']); ?></h3>
                <p class="text-sm text-gray-600 mb-4"><i class="fas fa-book mr-1.5 text-blue-500"></i>
                    <?php if ($undangan['topik_materi'] == "Membangun Peradaban Hijau: Upaya LDII Dalam Pelestarian Lingkungan Dan Pencapaian Kedaulatan Pangan Untuk Mewujudkan Islam Rahmatan Lil Alamin") {
                        echo "Materi Organisasi";
                    } else {
                        echo htmlspecialchars($undangan['topik_materi']);
                    } ?>
                </p>
                <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">
                    <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" target="_blank" class="flex-1 flex justify-center items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg font-semibold text-xs hover:bg-blue-100 transition-colors"><i class="fas fa-eye"></i> Lihat</a>
                    <a href="<?php echo $output_dir_web . htmlspecialchars($undangan['nama_file_pdf']); ?>" download class="flex-1 flex justify-center items-center gap-1.5 px-3 py-2 bg-emerald-50 text-emerald-600 rounded-lg font-semibold text-xs hover:bg-emerald-100 transition-colors"><i class="fas fa-download"></i> Unduh</a>
                    <button @click="isDeleteModalOpen = true; deleteData = <?php echo htmlspecialchars(json_encode($undangan), ENT_QUOTES, 'UTF-8'); ?>" class="flex-none flex justify-center items-center px-3 py-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"><i class="fas fa-trash-alt text-sm"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($undangan_list)): ?>
            <div class="p-8 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Belum ada undangan yang dibuat</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Buat Undangan Baru -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2"><i class="fas fa-envelope-open-text text-white"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Buat Undangan Baru</h3>
                        <p class="text-blue-100 text-xs">Isi data untuk menghasilkan PDF otomatis</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-white hover:text-blue-200 text-xl leading-none">&times;</button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 max-h-[75vh] overflow-y-auto">
                <form x-data="{ jenisUndanganPilihan: 'Nasehat Pembukaan', materiChecked: [false,false,false,false,false,false] }" method="POST" action="admin.php?page=administrasi/surat_undangan" class="space-y-4" onsubmit="showLoading()">
                    <input type="hidden" name="action" value="buat_undangan">

                    <div>
                        <label for="jenis_undangan" class="form-label"><i class="fas fa-list-alt mr-1 text-blue-500"></i>Jenis Undangan</label>
                        <select id="jenis_undangan" name="jenis_undangan" x-model="jenisUndanganPilihan" required class="form-input">
                            <option value="Nasehat Pembukaan">Nasehat Pembukaan</option>
                            <option value="Nasehat Penutupan">Nasehat Penutupan</option>
                            <option value="Makalah CAI">Makalah CAI</option>
                        </select>
                    </div>

                    <div>
                        <label for="nama_pemateri" class="form-label"><i class="fas fa-user mr-1 text-blue-500"></i>Nama Pemateri</label>
                        <input type="text" id="nama_pemateri" name="nama_pemateri" required class="form-input" placeholder="Nama lengkap pemateri...">
                    </div>

                    <!-- Daftar Materi dengan Jadwal Masing-masing (Makalah CAI) -->
                    <div x-show="jenisUndanganPilihan === 'Makalah CAI'" x-transition>
                        <label class="form-label"><i class="fas fa-book-open mr-1 text-blue-500"></i>Pilih Judul Makalah <span class="text-gray-400 font-normal">(centang untuk memilih, lalu isi jadwal)</span></label>
                        <div class="mt-1 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100">

                            <?php
                            $materis = [
                                0 => 'Dampak Perbuatan Maksiat',
                                1 => 'Wajibnya Menjaga Generus dari Kemaksiatan di Akhir Zaman',
                                2 => 'Wajibnya Menerampilkan 29 Karakter Luhur sebagai Bekal Sukses Generus',
                                3 => 'Mencetak Generus yang Sukses Dunia dan Akhirat',
                                4 => 'Menjemput Pertolongan Allah dengan Menolong Agama Allah',
                                5 => 'Meningkatkan Semangat Juang Muballigh-Muballighot',
                            ];
                            foreach ($materis as $idx => $judul):
                            ?>
                            <div>
                                <label class="flex items-start gap-3 px-4 py-2.5 hover:bg-blue-50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="topik_enabled[<?php echo $idx; ?>]" value="1"
                                        x-model="materiChecked[<?php echo $idx; ?>]"
                                        class="mt-0.5 w-4 h-4 accent-blue-600 cursor-pointer flex-shrink-0">
                                    <input type="hidden" name="topik_judul[<?php echo $idx; ?>]" value="<?php echo htmlspecialchars($judul); ?>">
                                    <span class="text-sm text-gray-700 font-medium">Materi <?php echo $idx+1; ?> &mdash; <?php echo htmlspecialchars($judul); ?></span>
                                </label>
                                <!-- Sub-form tanggal & waktu (muncul saat dicentang) -->
                                <div x-show="materiChecked[<?php echo $idx; ?>]" x-transition
                                    class="px-4 pb-3 pt-1 bg-blue-50 border-t border-blue-100 grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label text-xs text-blue-700"><i class="fas fa-calendar-alt mr-1"></i>Tanggal</label>
                                        <input type="date" name="topik_tanggal[<?php echo $idx; ?>]"
                                            :required="materiChecked[<?php echo $idx; ?>]" class="form-input text-sm">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs text-blue-700"><i class="fas fa-clock mr-1"></i>Waktu</label>
                                        <input type="text" name="topik_waktu[<?php echo $idx; ?>]"
                                            :required="materiChecked[<?php echo $idx; ?>]"
                                            placeholder="cth: 09:00 - 11:00 WIB" class="form-input text-sm">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                        </div>
                        <p x-show="!materiChecked.some(v => v)" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Pilih minimal satu judul makalah</p>
                    </div>

                    <!-- Tanggal & Waktu Global (hanya untuk Nasehat) -->
                    <div x-show="jenisUndanganPilihan !== 'Makalah CAI'" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_acara" class="form-label"><i class="fas fa-calendar-alt mr-1 text-blue-500"></i>Tanggal Acara</label>
                            <input type="date" id="tanggal_acara" name="tanggal_acara" :required="jenisUndanganPilihan !== 'Makalah CAI'" class="form-input">
                        </div>
                        <div>
                            <label for="waktu_acara" class="form-label"><i class="fas fa-clock mr-1 text-blue-500"></i>Waktu Acara</label>
                            <input type="text" id="waktu_acara" name="waktu_acara" :required="jenisUndanganPilihan !== 'Makalah CAI'" placeholder="cth: 09:00 - 11:00 WIB" class="form-input">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-3 border-t border-gray-100">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button>
                        <button type="submit" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 shadow transition-colors">
                            <i class="fas fa-file-pdf"></i> Buat &amp; Simpan PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isDeleteModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-red-600 px-6 py-4 flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-full p-2"><i class="fas fa-exclamation-triangle text-white text-sm"></i></div>
                <h3 class="text-lg font-bold text-white">Konfirmasi Hapus</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600">Yakin ingin menghapus surat untuk <strong class="text-gray-800" x-text="deleteData.nama_pemateri"></strong>? File PDF juga akan dihapus secara permanen.</p>
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

<?php if (isset($_SESSION['success_msg']) || isset($_SESSION['error_msg'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?php echo addslashes($_SESSION['success_msg']); ?>',
            showConfirmButton: false,
            timer: 2000
        });
        <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?php echo addslashes($_SESSION['error_msg']); ?>',
            showConfirmButton: false,
            timer: 2000
        });
        <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    });
</script>
<?php endif; ?>
