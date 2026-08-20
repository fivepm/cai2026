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
        <h1 class="text-3xl font-semibold text-gray-800">Dashboard Admin</h1>
        <p class="mt-1 text-gray-600">Selamat datang! Berikut adalah ringkasan data terbaru.</p>
    </div>

    <!-- Bagian Ringkasan Pendaftar -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Ringkasan Pendaftar</h2>
        <div class="hidden md:grid md:grid-cols-3 gap-4 text-center">
            <div class="bg-blue-100 p-4 rounded-lg">
                <p class="text-sm text-blue-700 font-semibold">Total Pendaftar</p>
                <p class="text-3xl font-bold text-blue-900"><?php echo $grand_total_pendaftar['total']; ?></p>
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
        <!-- Tampilan Desktop (Tabel) -->
        <div class="hidden md:block mt-6 overflow-hidden bg-white shadow ring-1 ring-gray-200 rounded-xl">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th rowspan="2" class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Kelompok</th>
                            <th colspan="3" class="px-6 py-3 text-center text-sm font-bold text-green-800 uppercase tracking-wider border-b border-gray-200 bg-green-50">Konfirmasi Hadir</th>
                            <th colspan="3" class="px-6 py-3 text-center text-sm font-bold text-yellow-800 uppercase tracking-wider border-b border-gray-200 bg-yellow-50">Konfirmasi Izin</th>
                            <th rowspan="2" class="px-6 py-4 text-center text-sm font-bold text-blue-800 uppercase tracking-wider border-b border-gray-200 bg-blue-50">Total Pendaftar</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 bg-green-50 border-b border-gray-200">L</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 bg-green-50 border-b border-gray-200">P</th>
                            <th class="px-4 py-2 text-center text-xs font-bold text-green-700 bg-green-100 border-b border-gray-200">Total</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 bg-yellow-50 border-b border-gray-200">L</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 bg-yellow-50 border-b border-gray-200">P</th>
                            <th class="px-4 py-2 text-center text-xs font-bold text-yellow-700 bg-yellow-100 border-b border-gray-200">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($stats_pendaftar as $kelompok => $data): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800"><?php echo $kelompok; ?></td>
                                
                                <!-- Hadir -->
                                <td class="px-4 py-4 text-center text-sm text-gray-600"><?php echo $data['hadir']['Laki-laki']; ?></td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600"><?php echo $data['hadir']['Perempuan']; ?></td>
                                <td class="px-4 py-4 text-center text-sm font-bold text-green-700 bg-green-50/50"><?php echo $data['hadir']['total']; ?></td>
                                
                                <!-- Izin -->
                                <td class="px-4 py-4 text-center text-sm text-gray-600"><?php echo $data['izin']['Laki-laki']; ?></td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600"><?php echo $data['izin']['Perempuan']; ?></td>
                                <td class="px-4 py-4 text-center text-sm font-bold text-yellow-700 bg-yellow-50/50"><?php echo $data['izin']['total']; ?></td>
                                
                                <!-- Total Kelompok -->
                                <td class="px-6 py-4 text-center text-base font-bold text-blue-700 bg-blue-50/50"><?php echo $data['total_kelompok']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Grand Total Row -->
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 uppercase">Total Keseluruhan</td>
                            <td colspan="3" class="px-6 py-4 text-center text-base text-green-800 bg-green-100"><?php echo $grand_total_pendaftar['hadir']; ?></td>
                            <td colspan="3" class="px-6 py-4 text-center text-base text-yellow-800 bg-yellow-100"><?php echo $grand_total_pendaftar['izin']; ?></td>
                            <td class="px-6 py-4 text-center text-lg text-blue-900 bg-blue-200"><?php echo $grand_total_pendaftar['total']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tampilan Mobile (Cards) -->
        <div class="block md:hidden mt-6 space-y-4">
            <!-- Grand Total Card -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-md text-white p-5 relative overflow-hidden">
                <i class="fas fa-chart-pie absolute -bottom-4 -right-4 text-blue-500 opacity-30 text-7xl"></i>
                <h3 class="font-bold mb-3 border-b border-blue-400/50 pb-2 text-lg">Total Keseluruhan</h3>
                <div class="relative z-10 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-blue-100 font-medium">Total Hadir</span>
                        <span class="font-bold bg-white/20 px-2.5 py-0.5 rounded text-sm"><?php echo $grand_total_pendaftar['hadir']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-blue-100 font-medium">Total Izin</span>
                        <span class="font-bold bg-white/20 px-2.5 py-0.5 rounded text-sm"><?php echo $grand_total_pendaftar['izin']; ?></span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-blue-400/50 mt-3">
                        <span class="text-sm font-semibold text-blue-50 uppercase tracking-wide">Grand Total</span>
                        <span class="text-2xl font-bold text-yellow-300"><?php echo $grand_total_pendaftar['total']; ?></span>
                    </div>
                </div>
            </div>

            <?php foreach ($stats_pendaftar as $kelompok => $data): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-gray-800 text-lg"><?php echo $kelompok; ?></h3>
                    <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2.5 py-1 rounded-full border border-blue-200">Total: <?php echo $data['total_kelompok']; ?></span>
                </div>
                <div class="p-4 space-y-3">
                    <!-- Section Hadir -->
                    <div class="bg-green-50/70 rounded-lg p-3 border border-green-100">
                        <div class="flex justify-between items-center mb-2 pb-1 border-b border-green-200">
                            <span class="text-sm font-bold text-green-800"><i class="fas fa-check-circle mr-1"></i> Hadir</span>
                            <span class="text-sm font-bold text-green-700 bg-green-200 px-2 py-0.5 rounded-md"><?php echo $data['hadir']['total']; ?></span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600 font-medium px-1">
                            <span>Laki-laki: <span class="text-gray-900 font-bold"><?php echo $data['hadir']['Laki-laki']; ?></span></span>
                            <span>Perempuan: <span class="text-gray-900 font-bold"><?php echo $data['hadir']['Perempuan']; ?></span></span>
                        </div>
                    </div>
                    <!-- Section Izin -->
                    <div class="bg-yellow-50/70 rounded-lg p-3 border border-yellow-100">
                        <div class="flex justify-between items-center mb-2 pb-1 border-b border-yellow-200">
                            <span class="text-sm font-bold text-yellow-800"><i class="fas fa-envelope-open-text mr-1"></i> Izin</span>
                            <span class="text-sm font-bold text-yellow-700 bg-yellow-200 px-2 py-0.5 rounded-md"><?php echo $data['izin']['total']; ?></span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600 font-medium px-1">
                            <span>Laki-laki: <span class="text-gray-900 font-bold"><?php echo $data['izin']['Laki-laki']; ?></span></span>
                            <span>Perempuan: <span class="text-gray-900 font-bold"><?php echo $data['izin']['Perempuan']; ?></span></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>