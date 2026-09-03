<?php
// (File: admin/pages/presensi/log_kehadiran.php)

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_kehadiran') {
    $log_id     = $_POST['log_id'];
    $status     = $_POST['status'];
    $keterangan = $_POST['keterangan'];

    // Gunakan waktu PHP Asia/Jakarta agar tidak terpengaruh timezone UTC database
    date_default_timezone_set('Asia/Jakarta');
    $waktu_manual_str = date('Y-m-d H:i:s');

    $stmt_update = $conn->prepare("UPDATE log_presensi SET status = ?, keterangan = ?, waktu_presensi = ? WHERE id = ?");
    $stmt_update->bind_param("sssi", $status, $keterangan, $waktu_manual_str, $log_id);
    if ($stmt_update->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data kehadiran berhasil diperbarui.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memperbarui data kehadiran.'];
    }
    $stmt_update->close();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Ambil data untuk filter
$sesi_list = [];
$result_sesi_list = $conn->query("SELECT id, nama_sesi FROM sesi_presensi ORDER BY nama_sesi");
if ($result_sesi_list) {
    while ($row = $result_sesi_list->fetch_assoc()) {
        $sesi_list[] = $row;
    }
}

// Logika Filter
$filter_sesi   = $_GET['sesi']   ?? '';
$filter_status = $_GET['status'] ?? '';
$search_nama   = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];
$types  = '';

if (!empty($filter_sesi))   { $where_clauses[] = "l.id_sesi = ?";  $params[] = $filter_sesi;          $types .= 'i'; }
if (!empty($filter_status)) { $where_clauses[] = "l.status = ?";   $params[] = $filter_status;        $types .= 's'; }
if (!empty($search_nama))   { $where_clauses[] = "p.nama LIKE ?";  $params[] = '%'.$search_nama.'%'; $types .= 's'; }

$sql_where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$log_data = [];
$sql = "SELECT l.id, p.nama, p.kelompok, s.nama_sesi, l.status, l.waktu_presensi, l.keterangan
        FROM log_presensi l
        JOIN peserta p ON l.id_peserta = p.id
        JOIN sesi_presensi s ON l.id_sesi = s.id
        $sql_where
        ORDER BY l.waktu_presensi DESC, p.nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
if ($result) { while ($row = $result->fetch_assoc()) { $log_data[] = $row; } }
$stmt->close();
?>

<style>
/* Fullscreen Styles */
#log-fullscreen-wrapper:fullscreen {
    background-color: #f3f4f6;
    padding: 2rem;
    overflow-y: auto;
}
#log-fullscreen-wrapper:fullscreen .hide-in-fs {
    display: none !important;
}
.fs-only-btn {
    display: none;
}
#log-fullscreen-wrapper:fullscreen .fs-only-btn {
    display: flex !important;
}

/* Animations from scanner */
@keyframes scanCardIn {
    from { opacity: 0; transform: scale(0.75); }
    to   { opacity: 1; transform: scale(1); }
}
@keyframes drawStroke {
    to { stroke-dashoffset: 0; }
}
.scan-result-card { animation: scanCardIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both; }
.anim-circle      { stroke-dasharray: 166; stroke-dashoffset: 166; animation: drawStroke 0.5s ease-out 0.05s forwards; }
.anim-check       { stroke-dasharray: 55;  stroke-dashoffset: 55;  animation: drawStroke 0.35s ease-out 0.45s forwards; }
.anim-cross1      { stroke-dasharray: 30;  stroke-dashoffset: 30;  animation: drawStroke 0.25s ease-out 0.4s  forwards; }
.anim-cross2      { stroke-dasharray: 30;  stroke-dashoffset: 30;  animation: drawStroke 0.25s ease-out 0.58s forwards; }
</style>

<script>
function logKehadiranData() {
    return {
        isModalOpen: false,
        logId: '',
        currentStatus: '',
        currentKeterangan: '',
        scanResult: { visible: false, status: '', message: '' },
        _dismissTimer: null,
        
        openModal(log) {
            this.logId = log.id;
            this.currentStatus = log.status;
            this.currentKeterangan = log.keterangan || '';
            this.isModalOpen = true;
        },
        toggleFullscreen() {
            const elem = document.getElementById('log-fullscreen-wrapper');
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => console.error(err));
            } else {
                document.exitFullscreen();
            }
        },
        triggerAnimation(nama, status) {
            if (this._dismissTimer) { clearTimeout(this._dismissTimer); }
            
            let animStatus = 'success';
            let message = `${nama} - ${status}`;
            
            if (status === 'Hadir' || status === 'Terlambat' || status === 'Izin') {
                animStatus = 'success';
            } else {
                animStatus = 'error';
            }
            
            this.scanResult = { visible: true, status: animStatus, message: message };
            
            this._dismissTimer = setTimeout(() => {
                this.scanResult.visible = false;
                this._dismissTimer = null;
            }, 3000);
        }
    };
}
</script>

<div id="log-fullscreen-wrapper" class="w-full relative" x-data="logKehadiranData()" @trigger-anim.window="triggerAnimation($event.detail.nama, $event.detail.status)">

    <!-- Overlay Notifikasi Hasil Scan -->
    <div x-show="scanResult.visible"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-6"
         style="display:none;">
        <template x-if="scanResult.visible">
            <div class="scan-result-card bg-white rounded-3xl shadow-2xl p-8 max-w-xs w-full text-center">
                <template x-if="scanResult.status === 'success'">
                    <div class="mb-4 flex justify-center">
                        <svg class="w-20 h-20" viewBox="0 0 52 52" fill="none">
                            <circle class="anim-circle" cx="26" cy="26" r="25" stroke="#22c55e" stroke-width="2"/>
                            <path class="anim-check" d="M14 27l8 8 16-16" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </template>
                <template x-if="scanResult.status === 'error'">
                    <div class="mb-4 flex justify-center">
                        <svg class="w-20 h-20" viewBox="0 0 52 52" fill="none">
                            <circle class="anim-circle" cx="26" cy="26" r="25" stroke="#ef4444" stroke-width="2"/>
                            <line class="anim-cross1" x1="17" y1="17" x2="35" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                            <line class="anim-cross2" x1="35" y1="17" x2="17" y2="35" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </div>
                </template>
                <p class="font-semibold text-gray-800 text-base leading-snug" x-text="scanResult.message"></p>
                <p class="text-xs text-gray-400 mt-2">Data diperbarui otomatis</p>
            </div>
        </template>
    </div>

    <!-- Exit Fullscreen Button -->
    <button @click="toggleFullscreen" class="fs-only-btn fixed top-6 right-6 z-50 px-4 py-2 bg-red-600/90 hover:bg-red-600 text-white rounded-xl shadow-lg transition-all items-center gap-2 cursor-pointer backdrop-blur-sm">
        <i class="fas fa-compress"></i> Keluar
    </button>

    <!-- Header -->
    <div class="flex justify-between items-center hide-in-fs">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Log Kehadiran Peserta</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pantau dan kelola status kehadiran semua peserta</p>
        </div>
        <button @click="toggleFullscreen()" class="px-4 py-2 bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2 shadow-sm">
            <i class="fas fa-expand"></i> Fullscreen
        </button>
    </div>

    <!-- Notifikasi -->
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

    <!-- Form Filter -->
    <div class="mt-5 bg-white rounded-xl shadow-md overflow-hidden hide-in-fs">
        <div class="bg-blue-600 px-6 py-3 flex items-center gap-2">
            <i class="fas fa-filter text-white text-sm"></i>
            <span class="text-white font-semibold text-sm">Filter Data</span>
        </div>
        <div class="p-5">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="page" value="presensi/log_kehadiran">
                <div>
                    <label for="search" class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-magnifying-glass mr-1 text-blue-500"></i>Cari Nama
                    </label>
                    <input type="text" name="search" id="search"
                           value="<?php echo htmlspecialchars($search_nama); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Nama peserta...">
                </div>
                <div>
                    <label for="sesi" class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-calendar-check mr-1 text-blue-500"></i>Filter Sesi
                    </label>
                    <select name="sesi" id="sesi"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Sesi</option>
                        <?php foreach ($sesi_list as $sesi_item): ?>
                            <option value="<?php echo $sesi_item['id']; ?>" <?php if ($filter_sesi == $sesi_item['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sesi_item['nama_sesi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-1">
                        <i class="fas fa-circle-half-stroke mr-1 text-blue-500"></i>Filter Status
                    </label>
                    <select name="status" id="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua Status</option>
                        <option value="Belum Presensi" <?php if ($filter_status == 'Belum Presensi') echo 'selected'; ?>>Belum Presensi</option>
                        <option value="Hadir"          <?php if ($filter_status == 'Hadir')          echo 'selected'; ?>>Hadir</option>
                        <option value="Terlambat"      <?php if ($filter_status == 'Terlambat')      echo 'selected'; ?>>Terlambat</option>
                        <option value="Izin"           <?php if ($filter_status == 'Izin')           echo 'selected'; ?>>Izin</option>
                    </select>
                </div>
                <button type="submit"
                        class="w-full px-4 py-2 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm flex items-center justify-center gap-2 text-sm">
                    <i class="fas fa-filter"></i> Terapkan Filter
                </button>
            </form>
        </div>
    </div>

    <!-- Tabel Log Kehadiran -->
    <div class="mt-5 bg-white shadow-md rounded-xl overflow-hidden">
        <div class="bg-blue-600 px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-table-list text-white text-sm"></i>
                <span class="text-white font-semibold text-sm">Data Kehadiran</span>
            </div>
            <span class="bg-white bg-opacity-20 text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                <span id="log-count"><?php echo count($log_data); ?></span> data
            </span>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50 text-blue-800 border-b border-blue-100">
                    <tr class="text-left font-semibold">
                        <th class="px-5 py-3">Nama Peserta</th>
                        <th class="px-5 py-3">Kelompok</th>
                        <th class="px-5 py-3">Sesi</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Waktu Presensi</th>
                        <th class="px-5 py-3">Keterangan</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="desktop-log-table" class="divide-y divide-gray-100">
                    <?php if (empty($log_data)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                Tidak ada data untuk ditampilkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($log_data as $log): ?>
                            <?php
                            $badge = match($log['status']) {
                                'Hadir'          => 'bg-green-100 text-green-800',
                                'Terlambat'      => 'bg-yellow-100 text-yellow-800',
                                'Izin'           => 'bg-blue-100 text-blue-800',
                                'Belum Presensi' => 'bg-gray-100 text-gray-600',
                                default          => 'bg-gray-100 text-gray-600'
                            };
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors" data-log-id="<?php echo $log['id']; ?>" data-status="<?php echo htmlspecialchars($log['status']); ?>" data-nama="<?php echo htmlspecialchars($log['nama']); ?>">
                                <td class="px-5 py-3.5 font-medium text-gray-800"><?php echo htmlspecialchars($log['nama']); ?></td>
                                <td class="px-5 py-3.5 text-gray-600"><?php echo htmlspecialchars($log['kelompok']); ?></td>
                                <td class="px-5 py-3.5 text-gray-600"><?php echo htmlspecialchars($log['nama_sesi']); ?></td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $badge; ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <?php echo $log['waktu_presensi'] ? date('d M Y, H:i:s', strtotime($log['waktu_presensi'])) : '<span class="text-gray-300">—</span>'; ?>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600"><?php echo htmlspecialchars($log['keterangan'] ?: '—'); ?></td>
                                <td class="px-5 py-3.5 text-center">
                                    <button @click="openModal(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                                            class="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium transition-colors mx-auto">
                                        <i class="fas fa-pen text-xs"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div id="mobile-log-cards" class="block md:hidden border-t border-gray-100 divide-y divide-gray-100">
            <?php foreach ($log_data as $log): ?>
            <?php
            $badge = match($log['status']) {
                'Hadir'          => 'bg-green-100 text-green-800',
                'Terlambat'      => 'bg-yellow-100 text-yellow-800',
                'Izin'           => 'bg-blue-100 text-blue-800',
                'Belum Presensi' => 'bg-gray-100 text-gray-600',
                default          => 'bg-gray-100 text-gray-600'
            };
            ?>
            <div class="p-4 bg-white hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start mb-1">
                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($log['nama']); ?></h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $badge; ?>">
                        <?php echo htmlspecialchars($log['status']); ?>
                    </span>
                </div>
                <p class="text-xs text-gray-500 mb-0.5"><i class="fas fa-users mr-1 text-blue-400"></i><?php echo htmlspecialchars($log['kelompok']); ?></p>
                <p class="text-xs text-gray-500 mb-0.5"><i class="fas fa-calendar-check mr-1 text-blue-400"></i><?php echo htmlspecialchars($log['nama_sesi']); ?></p>
                <p class="text-xs text-gray-500 mb-3">
                    <i class="fas fa-clock mr-1 text-blue-400"></i>
                    <?php echo $log['waktu_presensi'] ? date('d M Y, H:i:s', strtotime($log['waktu_presensi'])) : '—'; ?>
                </p>
                <button @click="openModal(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                        class="w-full flex justify-center items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-600 rounded-lg font-semibold text-xs hover:bg-blue-100 transition-colors">
                    <i class="fas fa-pen"></i> Edit Kehadiran
                </button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($log_data)): ?>
            <div class="p-8 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>Tidak ada data untuk ditampilkan.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Edit Kehadiran -->
    <div x-show="isModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50"
         @keydown.escape.window="isModalOpen = false" x-cloak x-transition>
        <div @click.away="isModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2"><i class="fas fa-user-check text-white"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Edit Data Kehadiran</h3>
                        <p class="text-blue-100 text-xs">Perbarui status dan keterangan peserta</p>
                    </div>
                </div>
                <button @click="isModalOpen = false" class="text-white hover:text-blue-200 text-xl leading-none">&times;</button>
            </div>
            <div class="p-6">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="space-y-4">
                    <input type="hidden" name="action" value="update_kehadiran">
                    <input type="hidden" name="log_id" :value="logId">
                    <div>
                        <label for="edit_status" class="block text-sm font-semibold text-gray-700 mb-1">
                            <i class="fas fa-circle-half-stroke mr-1 text-blue-500"></i>Status Kehadiran
                        </label>
                        <select id="edit_status" name="status" x-model="currentStatus"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="Belum Presensi">Belum Presensi</option>
                            <option value="Hadir">Hadir</option>
                            <option value="Terlambat">Terlambat</option>
                            <option value="Izin">Izin</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_keterangan" class="block text-sm font-semibold text-gray-700 mb-1">
                            <i class="fas fa-note-sticky mr-1 text-blue-500"></i>Keterangan
                        </label>
                        <textarea id="edit_keterangan" name="keterangan" x-model="currentKeterangan" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Tambahkan keterangan..."></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="isModalOpen = false"
                                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold transition-colors shadow-sm">
                            <i class="fas fa-save mr-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let lastTopLogId = null;
        let lastTopStatus = null;
        
        // Inisialisasi status log teratas saat halaman pertama kali dimuat
        const initialTopRow = document.querySelector("#desktop-log-table tr[data-log-id]");
        if (initialTopRow) {
            lastTopLogId = initialTopRow.getAttribute("data-log-id");
            lastTopStatus = initialTopRow.getAttribute("data-status");
        }

        setInterval(() => {
            const modalEl = document.querySelector("[x-show=\"isModalOpen\"]");
            const isModalOpen = modalEl && modalEl.style.display !== "none";
            const isSearching = document.activeElement === document.getElementById("search") || document.activeElement === document.getElementById("sesi") || document.activeElement === document.getElementById("status");
            
            if (!isModalOpen && !isSearching) {
                const url = window.location.href;
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, "text/html");
                        
                        // Deteksi perubahan pada baris teratas (ada scan baru atau update status)
                        const newTopRow = doc.querySelector("#desktop-log-table tr[data-log-id]");
                        if (newTopRow) {
                            const newLogId = newTopRow.getAttribute("data-log-id");
                            const newStatus = newTopRow.getAttribute("data-status");
                            const newNama = newTopRow.getAttribute("data-nama");
                            
                            // Cek jika mode fullscreen aktif dan ada perubahan signifikan
                            if (document.fullscreenElement) {
                                if (lastTopLogId !== null && (newLogId !== lastTopLogId || (newLogId === lastTopLogId && newStatus !== lastTopStatus))) {
                                    // Panggil triggerAnimation di Alpine component (karena x-data sekarang function)
                                    // Hack di Alpine v3: ambil instance data dan eksekusi
                                    const alpineEl = document.getElementById('log-fullscreen-wrapper');
                                    // Pastikan menggunakan Alpine._x_dataStack jika perlu, tapi kita coba akses function di dalam scope jika mungkin.
                                    // Cara mudah mengirim event ke elemen Alpine:
                                    alpineEl.dispatchEvent(new CustomEvent('trigger-anim', { detail: { nama: newNama, status: newStatus } }));
                                }
                            }
                            
                            lastTopLogId = newLogId;
                            lastTopStatus = newStatus;
                        }
                        
                        const newDesktop = doc.getElementById("desktop-log-table");
                        const oldDesktop = document.getElementById("desktop-log-table");
                        if (newDesktop && oldDesktop) oldDesktop.innerHTML = newDesktop.innerHTML;
                        
                        const newMobile = doc.getElementById("mobile-log-cards");
                        const oldMobile = document.getElementById("mobile-log-cards");
                        if (newMobile && oldMobile) oldMobile.innerHTML = newMobile.innerHTML;

                        const newCount = doc.getElementById("log-count");
                        const oldCount = document.getElementById("log-count");
                        if (newCount && oldCount) oldCount.innerHTML = newCount.innerHTML;
                    })
                    .catch(error => console.error("Error fetching updates:", error));
            }
        }, 2000);
    });
</script>
