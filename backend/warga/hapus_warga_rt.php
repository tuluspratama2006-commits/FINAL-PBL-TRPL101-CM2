<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT.';
    header("Location: ../../auth/login.php");
    exit();
}

$id_user = intval($_POST['id_user']);
$user_rt = $_SESSION['rt_number'];

// Verify the user belongs to the RT's area
$query_check_rt = "SELECT rt_number FROM user WHERE id_user = $id_user";
$result_check_rt = mysqli_query($koneksi, $query_check_rt);

if (!$result_check_rt || mysqli_num_rows($result_check_rt) === 0) {
    $_SESSION['error_message'] = 'Data warga tidak ditemukan';
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

$data_rt = mysqli_fetch_assoc($result_check_rt);
if ($data_rt['rt_number'] !== $user_rt) {
    $_SESSION['error_message'] = 'Anda tidak memiliki akses untuk menghapus warga dari RT lain';
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

// Get id_warga from user table
$query_get_warga = "SELECT id_warga FROM user WHERE id_user = $id_user";
$result_get_warga = mysqli_query($koneksi, $query_get_warga);

if (!$result_get_warga || mysqli_num_rows($result_get_warga) === 0) {
    $_SESSION['error_message'] = 'Data warga tidak ditemukan';
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

$data_warga = mysqli_fetch_assoc($result_get_warga);
$id_warga = $data_warga['id_warga'];

if (!$id_warga) {
    $_SESSION['error_message'] = 'ID warga tidak valid';
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

// Delete from user table first (due to foreign key constraint)
$delete_user_query = "DELETE FROM user WHERE id_user = $id_user";
if (!mysqli_query($koneksi, $delete_user_query)) {
    $_SESSION['error_message'] = 'Gagal menghapus data user: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

// Delete from warga table
$delete_warga_query = "DELETE FROM warga WHERE id_warga = $id_warga";
if (!mysqli_query($koneksi, $delete_warga_query)) {
    $_SESSION['error_message'] = 'Gagal menghapus data warga: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

$_SESSION['success_message'] = 'Warga berhasil dihapus';
header("Location: ../../dashboard_rt/warga.php?success=hapus");
exit();
?>
