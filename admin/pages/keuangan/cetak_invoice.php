<?php
session_start();
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin'])) {
    header("Location: ../../../login");
    exit();
}
require_once '../../../config/config.php';

$peserta_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT nama, kelompok, dibayar_pada, metode_pembayaran FROM peserta WHERE id = ? AND status_pembayaran = 'lunas'");
$stmt->bind_param("i", $peserta_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Invoice tidak ditemukan atau pembayaran belum lunas.");
}
$peserta = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($peserta['nama']); ?> - <?php echo $peserta_id; ?></title>
    <link rel="icon" type="image/png" href="../../uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                -webkit-print-color-adjust: exact;
                /* Memaksa cetak background */
                print-color-adjust: exact;
                /* Properti standar untuk masa depan */
            }
        }

        /* KODE BARU UNTUK WATERMARK */
        .watermark-container {
            position: relative;
            /* Wajib ada agar watermark bisa diposisikan */
            overflow: hidden;
            /* Mencegah watermark keluar dari kontainer */
        }

        .watermark-bg {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            z-index: 0;
            /* Menempatkan watermark di belakang konten */

            /* Ganti dengan path ke logo Anda */
            background-image: url('../../../uploads/bg_invoice.png');

            background-repeat: repeat;
            /* Mengulang gambar */
            background-size: 150px;
            /* Ukuran logo, sesuaikan jika perlu */

            opacity: 0.06;
            /* Tingkat transparansi, sesuaikan agar tidak terlalu mencolok */

            /* Sudut kemiringan, sesuaikan sesuai selera */
            transform: rotate(-30deg);
        }
    </style>
</head>

<body class="bg-gray-200 p-8">
    <div class="w-full max-w-2xl mx-auto bg-white p-10 shadow-lg font-sans watermark-container">
        <div class="watermark-bg"></div>
        <div class="relative z-10">
            <div class="grid grid-cols-6">
                <h1></h1>
                <h1></h1>
                <img src="../../../uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
                <img src="../../../uploads/logo_kmm.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
                <h1></h1>
                <h1></h1>
            </div>
            <div class="flex justify-between items-center border-b-2 border-black pb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">KUITANSI</h1>
                    <p class="text-gray-500">No. INV/CAI/<?php echo date('Y') . '/' . $peserta_id; ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-bold">PANITIA CAI 2025</h2>
                    <p class="text-sm">Bendahara Umum</p>
                </div>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Telah Diterima Dari:</p>
                    <p class="font-semibold text-lg"><?php echo htmlspecialchars($peserta['nama']); ?></p>
                    <p><?php echo htmlspecialchars($peserta['kelompok']); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Tanggal Konfirmasi Pembayaran:</p>
                    <p class="font-semibold text-lg"><?php echo date('D, d M Y', strtotime($peserta['dibayar_pada'])); ?></p>
                </div>
            </div>
            <div class="mt-8">
                <table class="w-full">
                    <thead class="bg-gray-100/50">
                        <tr class="text-left">
                            <th class="p-3">Deskripsi</th>
                            <th class="p-3 text-right">Shodaqoh Acara CAI 2025 Desa Banguntapan 1</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="">
                            <td class="p-3">Nominal</td>
                            <td class="p-3 text-right">Rp50.000,00</td>
                        </tr>
                    </tbody>
                    <tbody>
                        <tr class="">
                            <td class="p-3">Terbilang</td>
                            <td class="p-3 text-right">Lima Puluh Ribu Rupiah</td>
                        </tr>
                    </tbody>
                    <tbody>
                        <tr class="border-b">
                            <td class="p-3">Dibayar Melalui</td>
                            <td class="p-3 text-right"><?php echo htmlspecialchars($peserta['metode_pembayaran']); ?></td>
                        </tr>
                    </tbody>
                    <tfoot class="font-bold">
                        <tr class="bg-gray-100/50">
                            <td class="p-3">TOTAL</td>
                            <td class="p-3 text-right">Rp50.000,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-10 text-center">
                <p class="font-bold">- LUNAS -</p>
                <p class="text-xs text-gray-500 mt-2">الحمدلله جزاكم الله خيرا</p>
            </div>

            <!-- ============================================= -->
            <!-- KODE BARU: Area Tanda Tangan dan Cap -->
            <!-- ============================================= -->
            <div class="mt-8 flex justify-end">
                <div class="text-center w-56">
                    <p>Bantul, <?php echo date('d F Y'); ?></p>
                    <p class="mb-2">Bendahara,</p>
                    <div class="h-20 mb-2 flex items-center justify-center">
                        <!-- Tempat untuk cap dan tanda tangan -->
                        <img src="../../../uploads/ttd_azka.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
                    </div>
                    <p class="font-semibold border-t-2 pt-1">( Brilliant Azka S )</p>
                </div>
            </div>
            <!-- ============================================= -->
        </div>
    </div>
    <div class="text-center mt-6 no-print"><button onclick="window.print()" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Cetak</button></div>
</body>

</html>