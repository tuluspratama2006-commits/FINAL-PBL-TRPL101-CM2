<?php
session_start();
require_once '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Bendahara') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai Bendahara.';
    header("Location: ../../auth/login.php");
    exit();
}

$id_iuran = mysqli_real_escape_string($koneksi, $_POST['id']);

// Tampilkan data iuran untuk konfirmasi
$query = "SELECT i.*, w.nama_lengkap 
            FROM iuran_rutin i
            JOIN warga w ON i.id_warga = w.id_warga
            WHERE i.id_iuran = '$id_iuran'";
$result = mysqli_query($koneksi, $query);
$data = mysqli_fetch_assoc($result);

$_SESSION['hapus_data'] = $data;
$_SESSION['hapus_id'] = $id_iuran;

// Redirect ke halaman konfirmasi
header("Location: ../../dashboard_bendahara/konfirmasi_hapus.php");
exit();
?>