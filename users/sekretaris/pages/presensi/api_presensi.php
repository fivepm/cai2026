<?php
// (File: pages/presensi/api_presensi.php - Endpoint Khusus untuk API)

// Selalu tampilkan error untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// PENTING: File ini memerlukan koneksi database-nya sendiri karena tidak melewati admin.php
require_once '../../../../config/config.php';

header('Content-Type: application/json');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Koneksi ke database gagal: " . $conn->connect_error);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $barcode = $data['barcode'] ?? null;
    $sesi_id = $data['sesi_id'] ?? null;

    if (!$barcode || !$sesi_id) throw new Exception("Data barcode atau ID sesi tidak lengkap.");

    $stmt_peserta = $conn->prepare("SELECT id, nama, kelompok FROM peserta WHERE barcode = ?");
    $stmt_peserta->bind_param("s", $barcode);
    $stmt_peserta->execute();
    $peserta = $stmt_peserta->get_result()->fetch_assoc();
    $stmt_peserta->close();

    if (!$peserta) throw new Exception("Peserta dengan QR Code ini tidak ditemukan.");

    $stmt_sesi = $conn->prepare("SELECT waktu_sesi FROM sesi_presensi WHERE id = ?");
    $stmt_sesi->bind_param("i", $sesi_id);
    $stmt_sesi->execute();
    $sesi = $stmt_sesi->get_result()->fetch_assoc();
    $stmt_sesi->close();

    if (!$sesi) throw new Exception("Sesi tidak ditemukan.");

    $status_presensi = 'Hadir';
    date_default_timezone_set('Asia/Jakarta');
    $waktu_sekarang = new DateTime();
    $waktu_parts = explode('-', $sesi['waktu_sesi']);
    if (count($waktu_parts) === 2) {
        $waktu_selesai_str = trim($waktu_parts[1]);
        try {
            $waktu_selesai = new DateTime($waktu_selesai_str);
            if ($waktu_sekarang > $waktu_selesai) $status_presensi = 'Terlambat';
        } catch (Exception $e) {
        }
    }

    $stmt_update = $conn->prepare("UPDATE log_presensi SET status = ?, waktu_presensi = NOW() WHERE id_peserta = ? AND id_sesi = ? AND status = 'Belum Presensi'");
    $stmt_update->bind_param("sii", $status_presensi, $peserta['id'], $sesi_id);
    $stmt_update->execute();

    if ($stmt_update->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => "{$peserta['nama']} ({$peserta['kelompok']}) berhasil dicatat {$status_presensi}!"]);
    } else {
        $stmt_cek = $conn->prepare("SELECT status FROM log_presensi WHERE id_peserta = ? AND id_sesi = ?");
        $stmt_cek->bind_param("ii", $peserta['id'], $sesi_id);
        $stmt_cek->execute();
        $status_terakhir = $stmt_cek->get_result()->fetch_assoc()['status'] ?? 'Tidak Ditemukan';
        $stmt_cek->close();
        throw new Exception("Gagal. Peserta ini sudah presensi sebelumnya (Status: {$status_terakhir}).");
    }
    $stmt_update->close();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
exit();
