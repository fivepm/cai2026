<?php
// Path disesuaikan karena file ini ada di dalam folder 'pages/master'
require_once '../../../../config/config.php';

// Ambil parameter filter dari URL
$filter_kelompok = $_GET['kelompok'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_nama = $_GET['search'] ?? '';

// Query untuk menggabungkan data dari tabel 'peserta' dan 'izin'
$sql = "
    SELECT nama, kelompok, 'Hadir' as status, created_at FROM peserta
    UNION ALL
    SELECT nama, kelompok, status, created_at FROM izin
";

// Buat subquery untuk memungkinkan filtering pada data gabungan
$base_query = "SELECT * FROM ({$sql}) AS semua_pendaftar WHERE 1=1";
$params = [];
$types = '';

if (!empty($filter_kelompok)) {
    $base_query .= " AND kelompok = ?";
    $params[] = $filter_kelompok;
    $types .= 's';
}
if (!empty($filter_status)) {
    if ($filter_status === 'hadir') {
        $base_query .= " AND status = 'Hadir'";
    } else {
        $base_query .= " AND status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
}
if (!empty($search_nama)) {
    $base_query .= " AND nama LIKE ?";
    $params[] = '%' . $search_nama . '%';
    $types .= 's';
}
$base_query .= " ORDER BY kelompok, nama";

$stmt = $conn->prepare($base_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pendaftar_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekap Pendaftar</title>
    <link rel="icon" type="image/png" href="../../../../uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-white p-8 font-sans">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold">REKAPITULASI PENDAFTAR</h1>
        <h2 class="text-xl">CAI 2025</h2>
        <p class="text-sm text-gray-600">Dicetak pada: <?php echo date('d F Y, H:i'); ?></p>
        <?php if ($filter_kelompok || $filter_status): ?>
            <p class="mt-2 text-md font-semibold">
                Filter Aktif:
                <?php echo htmlspecialchars($filter_kelompok ?: 'Semua Kelompok'); ?> |
                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $filter_status)) ?: 'Semua Status'); ?>
            </p>
        <?php endif; ?>
    </div>

    <table class="w-full text-sm border-collapse border border-gray-400">
        <thead class="bg-gray-200">
            <tr>
                <th class="border border-gray-300 p-2 w-12">No.</th>
                <th class="border border-gray-300 p-2 text-left">Nama</th>
                <th class="border border-gray-300 p-2 text-left">Kelompok</th>
                <th class="border border-gray-300 p-2 text-left">Status Konfirmasi</th>
                <th class="border border-gray-300 p-2 text-left">Tanggal Daftar</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1;
            foreach ($pendaftar_list as $pendaftar): ?>
                <tr>
                    <td class="border border-gray-300 p-2 text-center"><?php echo $no++; ?></td>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($pendaftar['nama']); ?></td>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($pendaftar['kelompok']); ?></td>
                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pendaftar['status']))); ?></td>
                    <td class="border border-gray-300 p-2"><?php echo date('d M Y', strtotime($pendaftar['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pendaftar_list)): ?>
                <tr>
                    <td colspan="5" class="p-4 text-center">Tidak ada data yang cocok dengan filter.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-8 text-right no-print">
        <button onclick="window.print()" class="px-4 py-2 bg-red-600 text-white rounded-md">Cetak</button>
    </div>
</body>

</html>