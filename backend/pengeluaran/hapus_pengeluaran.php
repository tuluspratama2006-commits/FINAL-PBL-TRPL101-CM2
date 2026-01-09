<?php
// File: backend/pengeluaran/hapus_pengeluaran.php
session_start();
require_once '../../config/koneksi.php';

// Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    header("Location: ../../auth/login.php");
    exit();
}

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID pengeluaran tidak valid!";
    header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
    exit();
}

$id_pengeluaran = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil data bukti file jika ada
$query_select = "SELECT bukti FROM pengeluaran_kegiatan WHERE id_pengeluaran = ?";
$stmt_select = mysqli_prepare($koneksi, $query_select);
mysqli_stmt_bind_param($stmt_select, "i", $id_pengeluaran);
mysqli_stmt_execute($stmt_select);
mysqli_stmt_bind_result($stmt_select, $bukti_file);
mysqli_stmt_fetch($stmt_select);
mysqli_stmt_close($stmt_select);

// Hapus file bukti dari server jika ada
if ($bukti_file) {
    $file_path = "../../uploads/" . $bukti_file;
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Hapus data dari database
$query_delete = "DELETE FROM pengeluaran_kegiatan WHERE id_pengeluaran = ?";
$stmt_delete = mysqli_prepare($koneksi, $query_delete);
mysqli_stmt_bind_param($stmt_delete, "i", $id_pengeluaran);

if (mysqli_stmt_execute($stmt_delete)) {
    $_SESSION['success'] = "Data pengeluaran berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus data: " . mysqli_error($koneksi);
}

mysqli_stmt_close($stmt_delete);

// Redirect kembali ke halaman pengeluaran
header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
exit();
?>