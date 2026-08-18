<?php
// =======================================================
// 1. MENGHITUNG STATISTIK PENDAFTAR
// =======================================================
$stats_pendaftar = [];
$kelompok_list = ['Bintaran', 'Gedongkuning', 'Jombor', 'Sunten'];

// Inisialisasi struktur array agar tidak ada error jika data kosong
foreach ($kelompok_list as $kelompok) {
    $stats_pendaftar[$kelompok] = [
        'hadir' => ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0],
        'izin' => ['Laki-laki' => 0, 'Perempuan' => 0, 'total' => 0],
        'total_kelompok' => 0
    ];
}
$grand_total_pendaftar = ['hadir' => 0, 'izin' => 0, 'total' => 0];
$data_mumi = ['Bintaran' => 23, 'Gedongkuning' => 29, 'Jombor' => 10, 'Sunten' => 46];

// Ambil data peserta hadir
$result_hadir = $conn->query("SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM peserta GROUP BY kelompok, jenis_kelamin");
if ($result_hadir) {
    while ($row = $result_hadir->fetch_assoc()) {
        if (isset($stats_pendaftar[$row['kelompok']])) {
            $stats_pendaftar[$row['kelompok']]['hadir'][$row['jenis_kelamin']] = (int)$row['jumlah'];
            $stats_pendaftar[$row['kelompok']]['hadir']['total'] += (int)$row['jumlah'];
            $stats_pendaftar[$row['kelompok']]['total_kelompok'] += (int)$row['jumlah'];
            $grand_total_pendaftar['hadir'] += (int)$row['jumlah'];
        }
    }
}

// Ambil data peserta izin
$result_izin = $conn->query("SELECT kelompok, jenis_kelamin, COUNT(id) as jumlah FROM izin GROUP BY kelompok, jenis_kelamin");
if ($result_izin) {
    while ($row = $result_izin->fetch_assoc()) {
        if (isset($stats_pendaftar[$row['kelompok']])) {
            $stats_pendaftar[$row['kelompok']]['izin'][$row['jenis_kelamin']] = (int)$row['jumlah'];
            $stats_pendaftar[$row['kelompok']]['izin']['total'] += (int)$row['jumlah'];
            $stats_pendaftar[$row['kelompok']]['total_kelompok'] += (int)$row['jumlah'];
            $grand_total_pendaftar['izin'] += (int)$row['jumlah'];
        }
    }
}
$grand_total_pendaftar['total'] = $grand_total_pendaftar['hadir'] + $grand_total_pendaftar['izin'];
?>

<!-- Mulai HTML Konten -->
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-800">Dashboard Sekretaris</h1>
        <p class="mt-1 text-gray-600">Selamat datang! Berikut adalah ringkasan data terbaru.</p>
    </div>

    <!-- Bagian Ringkasan Pendaftar -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Ringkasan Pendaftar</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
            <div class="bg-red-100 p-4 rounded-lg">
                <p class="text-sm text-red-700 font-semibold">Total Pendaftar</p>
                <p class="text-3xl font-bold text-red-900"><?php echo $grand_total_pendaftar['total']; ?></p>
            </div>
            <div class="bg-green-100 p-4 rounded-lg">
                <p class="text-sm text-green-700 font-semibold">Peserta Hadir</p>
                <p class="text-3xl font-bold text-green-900"><?php echo $grand_total_pendaftar['hadir']; ?></p>
            </div>
            <div class="bg-yellow-100 p-4 rounded-lg">
                <p class="text-sm text-yellow-700 font-semibold">Peserta Izin</p>
                <p class="text-3xl font-bold text-yellow-900"><?php echo $grand_total_pendaftar['izin']; ?></p>
            </div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm  divide-y">
                <thead class="bg-gray-100">
                    <tr class="font-semibold">
                        <td class="p-3 font-bold">Kelompok</td>
                        <td class="p-3 text-center font-bold">Data Muda/i</td>
                        <td class="p-3 text-center font-bold" colspan="2">Konfirmasi<br>Hadir</td>
                        <td class="p-3 text-center font-bold" colspan="2">Konfirmasi<br>Izin</td>
                        <td class="p-3 text-center font-bold">Total Pendaftar</td>
                        <td class="p-3 text-center font-bold">Persentase Pendaftar</td>
                        <td class="p-3 text-center font-bold bg-red-500">Yang Belum Mendaftar</td>
                    </tr>
                    <tr class="bg-gray-50 text-xs text-gray-600">
                        <td class="p-2"></td>
                        <td class="p-2"></td>
                        <td class="p-2 text-center">L</td>
                        <td class="p-2 text-center">P</td>
                        <td class="p-2 text-center">L</td>
                        <td class="p-2 text-center">P</td>
                        <td class="p-2"></td>
                        <td class="p-2"></td>
                        <td class="p-2 bg-red-500"></td>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($stats_pendaftar as $kelompok => $data): ?>
                        <tr>
                            <td class="p-3 font-semibold"><?php echo $kelompok; ?></td>
                            <td class="p-3 text-center font-bold"><?php echo $data_mumi[$kelompok]; ?></td>
                            <td class="p-3 text-center"><?php echo $data['hadir']['Laki-laki']; ?></td>
                            <td class="p-3 text-center"><?php echo $data['hadir']['Perempuan']; ?></td>
                            <td class="p-3 text-center"><?php echo $data['izin']['Laki-laki']; ?></td>
                            <td class="p-3 text-center"><?php echo $data['izin']['Perempuan']; ?></td>
                            <td class="p-3 text-center font-bold"><?php echo $data['total_kelompok']; ?></td>
                            <td class="p-3 text-center font-bold"><?php echo number_format(($data['total_kelompok'] / $data_mumi[$kelompok]) * 100, 2); ?> %</td>
                            <td class="p-3 text-center font-bold bg-red-500"><?php echo $data_mumi[$kelompok] - $data['total_kelompok']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>