<?php
// Selalu mulai session
session_start();

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
session_destroy();

// Alihkan ke halaman login dengan pesan sukses (opsional)
// Anda bisa menambahkan pesan jika mau, tapi biasanya langsung redirect saja
header("Location: ../login");
exit();
