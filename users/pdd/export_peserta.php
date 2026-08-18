<?php
// 1. Panggil file konfigurasi untuk koneksi database
require_once '../../config/config.php';

// 2. Siapkan header HTTP untuk memberitahu browser agar mengunduh file
$filename = "daftar_peserta_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. Buka output stream PHP untuk menulis file CSV
$output = fopen('php://output', 'w');

// 4. Tulis baris header untuk file CSV
fputcsv($output, ['Kelompok', 'Nama', 'Jenis Kelamin']);

// 5. Ambil data dari tabel 'peserta'
$sql = "SELECT kelompok, nama, jenis_kelamin FROM peserta ORDER BY kelompok, nama";
$result = $conn->query($sql);

// 6. Tulis setiap baris data dari database ke file CSV
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// 7. Tutup koneksi dan hentikan skrip
$conn->close();
exit();
