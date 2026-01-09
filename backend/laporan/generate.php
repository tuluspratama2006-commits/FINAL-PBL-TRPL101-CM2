<?php
require_once '../config/koneksi.php';

// Debug: Check database connection
if (!$koneksi) {
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal: ' . mysqli_connect_error()
    ]);
    exit();
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Include fungsi generate
require_once 'proses_laporan.php';

try {
    $id_laporan = generateLaporanBulanan($bulan, $tahun);
    
    echo json_encode([
        'success' => true,
        'message' => 'Laporan berhasil digenerate',
        'id_laporan' => $id_laporan
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>