<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Check if user has permission (RT or Bendahara)
$user_role = $_SESSION['role'];
if ($user_role !== 'RT' && $user_role !== 'Bendahara') {
    header("Location: ../../auth/login.php");
    exit();
}

$id_user = intval($_POST['id_user']);
$user_rt = $_SESSION['rt_number'];

// Get id_warga from user table and validate RT
$query_get_warga = "SELECT id_warga FROM user WHERE id_user = $id_user AND rt_number = '$user_rt'";
$result_get_warga = mysqli_query($koneksi, $query_get_warga);

if (!$result_get_warga || mysqli_num_rows($result_get_warga) === 0) {
    $_SESSION['error_message'] = 'Data warga tidak ditemukan atau tidak di RT Anda';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

$data_warga = mysqli_fetch_assoc($result_get_warga);
$id_warga = $data_warga['id_warga'];

if (!$id_warga) {
    $_SESSION['error_message'] = 'ID warga tidak valid';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Delete from user table first (due to foreign key constraint)
$delete_user_query = "DELETE FROM user WHERE id_user = $id_user";
if (!mysqli_query($koneksi, $delete_user_query)) {
    $_SESSION['error_message'] = 'Gagal menghapus data user: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Delete from warga table
$delete_warga_query = "DELETE FROM warga WHERE id_warga = $id_warga";
if (!mysqli_query($koneksi, $delete_warga_query)) {
    $_SESSION['error_message'] = 'Gagal menghapus data warga: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Redirect based on user role
$user_role = $_SESSION['role'] ?? 'warga';
if ($user_role === 'Bendahara') {
    header("Location: ../../dashboard_bendahara/warga.php?success=hapus");
} elseif ($user_role === 'RT') {
    header("Location: ../../dashboard_rt/warga.php?success=hapus");
} else {
    header("Location: ../../dashboard_warga/warga.php?success=hapus");
}
exit;
