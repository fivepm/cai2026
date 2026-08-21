<?php
session_start();

// Otentikasi
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] != 'sie_pdd') {
    http_response_code(403);
    exit('Akses ditolak.');
}

require_once '../../config/config.php';
require_once '../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('ZipArchive tidak tersedia di server ini. Pastikan ekstensi PHP zip sudah aktif.');
}

// Validasi parameter kelompok
if (empty($_GET['kelompok'])) {
    http_response_code(400);
    die('Parameter kelompok tidak ditemukan.');
}

$kelompok = trim($_GET['kelompok']);

// Ambil peserta berdasarkan kelompok
$stmt = $conn->prepare("SELECT nama, kelompok, barcode FROM peserta WHERE kelompok = ? ORDER BY nama ASC");
$stmt->bind_param('s', $kelompok);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    die('Tidak ada data peserta untuk kelompok: ' . htmlspecialchars($kelompok));
}

$peserta_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Siapkan writer
$writer = new PngWriter();

// Buat file ZIP sementara
$tmp_zip = tempnam(sys_get_temp_dir(), 'qr_kel_');
$zip = new ZipArchive();
if ($zip->open($tmp_zip, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Gagal membuat file ZIP.');
}

$errors = [];
foreach ($peserta_list as $p) {
    try {
        $qrCode = new QrCode(
            data: $p['barcode'],
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result_qr = $writer->write($qrCode);

        $safe_name     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $p['nama']);
        $safe_kelompok = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $p['kelompok']);
        $filename      = "{$safe_kelompok}_{$safe_name}.png";

        $zip->addFromString($filename, $result_qr->getString());

    } catch (\Throwable $e) {
        $errors[] = $p['nama'] . ': ' . $e->getMessage();
    }
}

if (!empty($errors)) {
    $zip->addFromString('_GAGAL_GENERATE.txt', "QR Code gagal digenerate untuk:\n" . implode("\n", $errors));
}

$zip->close();

// Nama file ZIP yang dikirim ke browser
$safe_kelompok_dl = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $kelompok);
$zip_filename = 'QRCode_Kelompok_' . $safe_kelompok_dl . '_' . date('Ymd_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($tmp_zip));
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmp_zip);
unlink($tmp_zip);
exit();
