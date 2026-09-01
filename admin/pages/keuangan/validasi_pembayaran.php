<?php
// Izinkan akses hanya untuk superadmin dan bendahara (asumsi role 'bendahara' sudah ada)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../login");
    exit();
}

$upload_dir = '../uploads/bukti_pembayaran/';

// Proses Aksi (Terima, Tolak, Batalkan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $peserta_id = $_POST['peserta_id'];

    // Ambil data peserta untuk digunakan di log keuangan
    $stmt_peserta = $conn->prepare("SELECT nama FROM peserta WHERE id = ?");
    $stmt_peserta->bind_param("i", $peserta_id);
    $stmt_peserta->execute();
    $nama_peserta = $stmt_peserta->get_result()->fetch_assoc()['nama'];
    $stmt_peserta->close();

    $keterangan_log = "Pembayaran dari " . $nama_peserta . " (ID Peserta: " . $peserta_id . ")";
    $jumlah_pembayaran = 50000; // GANTI DENGAN JUMLAH PEMBAYARAN YANG SEBENARNYA

    if ($action === 'terima') {
        // 1. Ubah status peserta menjadi lunas
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'lunas', dibayar_pada = CURDATE() WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. Tambahkan entri ke log keuangan
        $stmt_log = $conn->prepare("INSERT INTO log_keuangan (tanggal, keterangan, jenis, sumber_pemasukan, jumlah) VALUES (CURDATE(), ?, 'masuk', 'peserta', ?)");
        $stmt_log->bind_param("sd", $keterangan_log, $jumlah_pembayaran);
        $stmt_log->execute();
        $stmt_log->close();
        $_SESSION['success_msg'] = "Pembayaran " . $nama_peserta . " berhasil diterima (Lunas).";
    } elseif ($action === 'tolak') {
        // Ubah status menjadi ditolak
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'ditolak' WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();
        $_SESSION['success_msg'] = "Pembayaran " . $nama_peserta . " ditolak.";
    } elseif ($action === 'batal') {
        // 1. Kembalikan status peserta menjadi belum diverifikasi
        $stmt_update = $conn->prepare("UPDATE peserta SET status_pembayaran = 'belum_diverifikasi', dibayar_pada = NULL WHERE id = ?");
        $stmt_update->bind_param("i", $peserta_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. Hapus entri dari log keuangan
        $stmt_log = $conn->prepare("DELETE FROM log_keuangan WHERE keterangan = ?");
        $stmt_log->bind_param("s", $keterangan_log);
        $stmt_log->execute();
        $stmt_log->close();
        $_SESSION['success_msg'] = "Status lunas " . $nama_peserta . " berhasil dibatalkan.";
    }

    header("Location: admin?page=keuangan/validasi_pembayaran");
    exit();
}

$pembayaran_list = $conn->query("SELECT id, nama, kelompok, metode_pembayaran, status_pembayaran FROM peserta WHERE metode_pembayaran != '' ORDER BY kelompok, nama ASC")->fetch_all(MYSQLI_ASSOC);

$role_user = $_SESSION['user_role'];
?>

<div x-data="{ sidebarOpen: false }" class="flex h-screen bg-blue-50">
    <!-- Konten Utama -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 p-6 overflow-x-hidden overflow-y-auto bg-blue-50">
            <h1 class="text-3xl font-bold text-blue-900">Validasi Pembayaran Peserta</h1>
            <div class="mt-6 overflow-hidden bg-white shadow-md rounded-lg">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap">
                        <thead class="bg-blue-600 text-white">
                            <tr class="text-left font-bold">
                                <th class="px-6 py-3">No</th>
                                <th class="px-6 py-3">Nama</th>
                                <th class="px-6 py-3">Kelompok</th>
                                <th class="px-6 py-3">Metode Pembayaran</th>
                                <th class="px-6 py-3">Status</th>
                                <?php
                                if ($role_user == 'superadmin') {
                                ?>
                                    <th class="px-6 py-3">Aksi</th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php
                            $no = 1;
                            foreach ($pembayaran_list as $p): ?>
                                <tr class="hover:bg-blue-50 transition-colors">
                                    <td class="px-6 py-4"><?php echo $no++; ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($p['nama']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($p['kelompok']); ?></td>
                                    <td class="px-6 py-4 font-medium text-gray-700"><?php echo htmlspecialchars($p['metode_pembayaran']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php echo $p['status_pembayaran'] == 'lunas' ? 'bg-green-500' : ($p['status_pembayaran'] == 'ditolak' ? 'bg-red-500' : 'bg-yellow-500'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $p['status_pembayaran'])); ?>
                                        </span>
                                    </td>
                                    <?php
                                    if ($role_user == 'superadmin') {
                                    ?>
                                        <td class="px-6 py-4">
                                            <form method="POST" class="inline-flex space-x-2">
                                                <input type="hidden" name="peserta_id" value="<?php echo $p['id']; ?>">
                                                <?php if ($p['status_pembayaran'] == 'belum_diverifikasi'): ?>
                                                    <button type="submit" name="action" value="terima" class="px-3 py-1 text-sm text-white bg-green-500 rounded-md">
                                                        Terima
                                                    </button>
                                                <?php elseif ($p['status_pembayaran'] == 'lunas'): ?>
                                                    <button type="submit" name="action" value="batal" class="px-3 py-1 text-sm text-white bg-yellow-500 rounded-md">
                                                        Batalkan Lunas
                                                    </button>
                                                    <a href="pages/keuangan/cetak_invoice.php?id=<?php echo $p['id']; ?>" target="_blank" class="px-3 py-1 text-sm text-white bg-blue-500 rounded-md">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                <?php else: echo '-';
                                                endif; ?>
                                            </form>
                                        </td>
                                    <?php
                                    }
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
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
