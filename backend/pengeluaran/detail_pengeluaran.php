<?php
// File: backend/pengeluaran/detail_pengeluaran.php
session_start();
require_once '../../config/koneksi.php';

// Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pengeluaran tidak valid']);
    exit();
}

$id_pengeluaran = mysqli_real_escape_string($koneksi, $_GET['id']);

// Query untuk mengambil detail pengeluaran
$query = "
SELECT 
    p.id_pengeluaran,
    DATE_FORMAT(p.tanggal_pengeluaran, '%d %M %Y') as tanggal_format,
    p.tanggal_pengeluaran as tanggal,
    p.kategori,
    p.deskripsi,
    p.jumlah_pengeluaran as jumlah,
    u.username AS diajukan_oleh,
    p.bukti,
    DATE_FORMAT(p.created_at, '%d %M %Y %H:%i') as dibuat_pada
FROM pengeluaran_kegiatan p
LEFT JOIN user u ON p.id_user = u.id_user
WHERE p.id_pengeluaran = ?
";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $id_pengeluaran);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // Format jumlah ke rupiah
    $row['jumlah_format'] = 'Rp ' . number_format($row['jumlah'], 0, ',', '.');
    
    // Jika ada bukti, tambahkan URL lengkap
    if ($row['bukti']) {
        $row['bukti_url'] = '../../uploads/' . $row['bukti'];
    }
    
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Data tidak ditemukan']);
}

mysqli_stmt_close($stmt);
exit();
?>