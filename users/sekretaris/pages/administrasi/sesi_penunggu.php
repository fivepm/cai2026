<?php
// Izinkan akses hanya untuk superadmin dan admin
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['sekretaris'])) {
    header("Location: ../../login");
    exit();
}
// Proses form saat ada data yang dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'tambah') {
        $nama_sesi = $_POST['nama_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi']; // Ambil data tanggal
        $stmt = $conn->prepare("INSERT INTO sesi_penunggu (nama_sesi, waktu_sesi, tanggal_sesi) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_sesi, $waktu_sesi, $tanggal_sesi);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_msg'] = 'Sesi penunggu berhasil ditambahkan!';
    } elseif ($action === 'update') {
        $sesi_id = $_POST['sesi_id'];
        $nama_sesi = $_POST['nama_sesi'];
        $waktu_sesi = $_POST['waktu_sesi'];
        $tanggal_sesi = $_POST['tanggal_sesi']; // Ambil data tanggal
        $jumlah_penunggu = $_POST['jumlah_penunggu'];
        $nama_penunggu = $_POST['nama_penunggu'] ?? [];
        $nama_penunggu_json = json_encode(array_values($nama_penunggu));

        $stmt = $conn->prepare("UPDATE sesi_penunggu SET nama_sesi = ?, waktu_sesi = ?, tanggal_sesi = ?, jumlah_penunggu = ?, nama_penunggu = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $nama_sesi, $waktu_sesi, $tanggal_sesi, $jumlah_penunggu, $nama_penunggu_json, $sesi_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_msg'] = 'Sesi penunggu berhasil diupdate!';
    } elseif ($action === 'hapus') {
        $sesi_id = $_POST['sesi_id'];
        $stmt = $conn->prepare("DELETE FROM sesi_penunggu WHERE id = ?");
        $stmt->bind_param("i", $sesi_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_msg'] = 'Sesi penunggu berhasil dihapus!';
    }

    header("Location: sekretaris?page=administrasi/sesi_penunggu");
    exit();
}

// Ambil semua data sesi dari database
$sesi_list = $conn->query("SELECT * FROM sesi_penunggu ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
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

<div x-data="{ isAddModalOpen: false, isDeleteModalOpen: false, deleteSesiId: null, deleteSesiNama: '', isViewModalOpen: false, viewSesiData: {} }">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Daftar Sesi Penunggu</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola jadwal dan nama penunggu CAI</p>
        </div>
        <button @click="isAddModalOpen = true" class="px-5 py-2.5 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 flex items-center gap-2 shadow-md transition-all">
            <i class="fas fa-plus text-sm"></i> Tambah Sesi Baru
        </button>
    </div>

    <!-- Grid untuk menampilkan kartu sesi -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($sesi_list as $sesi): ?>
            <?php
            $penunggu_array = json_decode($sesi['nama_penunggu'] ?? '[]', true);
            $tanggal_formatted = !empty($sesi['tanggal_sesi']) ? date('d F Y', strtotime($sesi['tanggal_sesi'])) : 'Tanggal belum diatur';
            
            $hari_map = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
            $hari_indonesia = !empty($sesi['tanggal_sesi']) ? $hari_map[date('l', strtotime($sesi['tanggal_sesi']))] : '';
            ?>
            <!-- Kartu Sesi (Alpine.js Component) -->
            <div x-data='{
                    jumlah: <?php echo $sesi['jumlah_penunggu']; ?>,
                    penunggu: <?php echo json_encode($penunggu_array); ?>
                }'
                class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden hover:shadow-lg transition-shadow">
                
                <div class="bg-blue-50 px-5 py-3 border-b border-blue-100 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-blue-600"></i>
                        <span class="font-semibold text-blue-800 text-sm">Jadwal Penunggu</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="isViewModalOpen = true; viewSesiData = { nama: '<?php echo htmlspecialchars($sesi['nama_sesi'], ENT_QUOTES); ?>', hari: '<?php echo $hari_indonesia; ?>', tanggal: '<?php echo $tanggal_formatted; ?>', waktu: '<?php echo htmlspecialchars($sesi['waktu_sesi'], ENT_QUOTES); ?>', penunggu: penunggu };" class="text-blue-500 hover:text-blue-700 bg-blue-100 hover:bg-blue-200 p-1.5 rounded-md transition-colors" title="Lihat Jadwal"><i class="fas fa-eye text-sm"></i></button>
                        <button type="button" @click="isDeleteModalOpen = true; deleteSesiId = <?php echo $sesi['id']; ?>; deleteSesiNama = '<?php echo htmlspecialchars($sesi['nama_sesi'], ENT_QUOTES); ?>';" class="text-red-500 hover:text-red-700 bg-red-100 hover:bg-red-200 p-1.5 rounded-md transition-colors" title="Hapus Jadwal"><i class="fas fa-trash-alt text-sm"></i></button>
                    </div>
                </div>

                <div class="p-5">
                    <form method="POST" action="sekretaris?page=administrasi/sesi_penunggu" class="space-y-4">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="sesi_id" value="<?php echo $sesi['id']; ?>">

                        <div>
                            <input type="text" name="nama_sesi" value="<?php echo htmlspecialchars($sesi['nama_sesi']); ?>" class="text-xl font-bold text-gray-800 border-0 border-b-2 border-gray-200 focus:border-blue-500 focus:ring-0 p-0 pb-1 w-full transition-colors" placeholder="Nama Sesi...">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label"><i class="fas fa-calendar-day mr-1 text-blue-500"></i>Tanggal</label>
                                <input type="date" name="tanggal_sesi" value="<?php echo htmlspecialchars($sesi['tanggal_sesi']); ?>" class="form-input text-xs">
                            </div>
                            <div>
                                <label class="form-label"><i class="fas fa-hourglass-half mr-1 text-blue-500"></i>Waktu</label>
                                <input type="text" name="waktu_sesi" value="<?php echo htmlspecialchars($sesi['waktu_sesi']); ?>" placeholder="08:00 - 09:00" class="form-input text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label"><i class="fas fa-users mr-1 text-blue-500"></i>Jumlah Penunggu</label>
                            <input type="number" name="jumlah_penunggu" x-model.number="jumlah" min="0" max="10" class="form-input">
                        </div>

                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 space-y-2 max-h-48 overflow-y-auto">
                            <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Nama Penunggu</label>
                            <template x-for="i in Array.from({ length: jumlah }, (_, i) => i)">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-400 w-4 text-right" x-text="i + 1"></span>
                                    <input type="text" name="nama_penunggu[]" x-model="penunggu[i]" :placeholder="'Nama penunggu ' + (i + 1)" class="form-input !mt-0">
                                </div>
                            </template>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2 font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors flex items-center justify-center gap-2"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($sesi_list)): ?>
            <div class="col-span-full py-12 text-center bg-white rounded-xl shadow-sm border border-gray-100 text-gray-400">
                <i class="fas fa-calendar-times text-4xl mb-3 block text-gray-300"></i>
                <p>Belum ada sesi penunggu yang dibuat.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah Sesi -->
    <div x-show="isAddModalOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isAddModalOpen = false" class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2"><i class="fas fa-calendar-plus text-white"></i></div>
                    <h3 class="text-lg font-bold text-white">Tambah Sesi Baru</h3>
                </div>
                <button @click="isAddModalOpen = false" class="text-white hover:text-blue-200 text-xl leading-none">&times;</button>
            </div>
            
            <div class="p-6">
                <form method="POST" action="sekretaris?page=administrasi/sesi_penunggu" class="space-y-4">
                    <input type="hidden" name="action" value="tambah">
                    
                    <div>
                        <label for="nama_sesi" class="form-label">Nama Sesi</label>
                        <input type="text" id="nama_sesi" name="nama_sesi" required class="form-input" placeholder="Misal: Penunggu Sesi 1">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_sesi" class="form-label">Tanggal</label>
                            <input type="date" id="tanggal_sesi" name="tanggal_sesi" class="form-input">
                        </div>
                        <div>
                            <label for="waktu_sesi" class="form-label">Waktu</label>
                            <input type="text" id="waktu_sesi" name="waktu_sesi" placeholder="cth: 08:00 - 09:00" class="form-input">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="isAddModalOpen = false" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Batal</button>
                        <button type="submit" class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 shadow transition-colors"><i class="fas fa-check"></i> Tambah</button>
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
                <p class="text-gray-600">Yakin ingin menghapus sesi <strong class="text-gray-800" x-text="deleteSesiNama"></strong>?</p>
                <form method="POST" action="sekretaris?page=administrasi/sesi_penunggu" class="mt-5 flex justify-end gap-3">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="sesi_id" :value="deleteSesiId">
                    <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tampilan Sesi -->
    <div x-show="isViewModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-cloak x-transition>
        <div @click.away="isViewModalOpen = false" id="export-modal-content" class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 mx-4 relative overflow-hidden">
            <!-- Dekorasi Biru Background -->
            <div class="absolute top-0 left-0 w-full h-32 bg-blue-50 -z-10"></div>
            
            
            
            <img src="../../assets/images/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-16 w-auto drop-shadow-md">
            
            <div class="text-center mt-4 mb-6">
                <h3 class="text-2xl font-black text-blue-900 tracking-wide">JADWAL PENUNGGU CAI</h3>
                <h4 class="text-lg font-bold text-gray-800 mt-1" x-text="viewSesiData.nama"></h4>
                <div class="inline-flex flex-col items-center gap-1.5 mt-3 bg-blue-50 px-5 py-2.5 rounded-xl shadow-sm border border-blue-200 text-sm w-4/5">
                    <span class="text-blue-900 font-black tracking-widest uppercase text-xs mb-[-4px]" x-show="viewSesiData.hari" x-text="viewSesiData.hari"></span>
                    <span class="text-blue-800 font-bold"><i class="fas fa-calendar-alt text-blue-600 mr-1.5"></i> <span x-text="viewSesiData.tanggal"></span></span>
                    <div class="w-full h-px bg-blue-200/60 my-0.5"></div>
                    <span class="text-blue-800 font-bold"><i class="fas fa-clock text-blue-600 mr-1.5"></i> <span x-text="viewSesiData.waktu"></span></span>
                </div>
            </div>
            
            <div class="mt-6">
                <h4 class="font-semibold text-sm text-blue-600 uppercase tracking-wider mb-3 text-center border-b border-blue-100 pb-2">Daftar Penunggu</h4>
                <ul class="space-y-2">
                    <template x-for="(penunggu, index) in viewSesiData.penunggu">
                        <li class="flex items-center p-3 bg-blue-50 rounded-lg border border-blue-200 shadow-sm">
                            <div class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mr-3 shadow-sm" x-text="index + 1"></div>
                            <span class="font-bold text-blue-900" x-text="penunggu || '- Kosong -'"></span>
                        </li>
                    </template>
                </ul>
            </div>
            
            <div class="mt-8 flex justify-center gap-3" data-html2canvas-ignore="true">
                <button type="button" @click="isViewModalOpen = false" class="px-6 py-2.5 bg-gray-800 text-white font-semibold rounded-lg shadow-md hover:bg-gray-900 transition-colors">Tutup</button>
                <button type="button" @click="exportSesiPenungguToJPG(viewSesiData.nama)" class="px-6 py-2.5 bg-green-600 text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-file-image"></i> Export JPG
                </button>
            </div>
        </div>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    function exportSesiPenungguToJPG(sesiNama) {
        const captureElement = document.getElementById('export-modal-content');
        
        const closeIcon = captureElement.querySelector('.fa-times');
        const closeBtn = closeIcon ? closeIcon.closest('button') : null;
        if (closeBtn) closeBtn.style.display = 'none';
        
        html2canvas(captureElement, {
            scale: 2,
            useCORS: true,
            backgroundColor: "#ffffff",
            logging: false
        }).then(canvas => {
            if (closeBtn) closeBtn.style.display = '';
            
            const link = document.createElement('a');
            link.download = `Jadwal_Penunggu_${sesiNama || 'Sesi'}.jpg`;
            link.href = canvas.toDataURL('image/jpeg', 0.9);
            link.click();
        }).catch(err => {
            if (closeBtn) closeBtn.style.display = '';
            console.error("Gagal export JPG:", err);
            alert("Terjadi kesalahan saat mengekspor gambar.");
        });
    }
</script>

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
