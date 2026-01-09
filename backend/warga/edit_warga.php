<?php
session_start();
require_once '../../config/koneksi.php';

// Debug: Check database connection
if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_bendahara/warga.php");
    exit();
}

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

// Get user's RT from session
$user_rt = $_SESSION['rt_number'];

// Validate required POST data
if (!isset($_POST['id_user']) || !isset($_POST['nama_lengkap']) || !isset($_POST['username']) ||
    !isset($_POST['email']) || !isset($_POST['nik']) || !isset($_POST['rt_number']) || !isset($_POST['role'])) {
    $_SESSION['error_message'] = 'Data tidak lengkap';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

$id_user      = intval($_POST['id_user']);
$nama         = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
$username     = mysqli_real_escape_string($koneksi, trim($_POST['username']));
$email        = mysqli_real_escape_string($koneksi, trim($_POST['email']));
$nik          = mysqli_real_escape_string($koneksi, trim($_POST['nik']));
$rt           = mysqli_real_escape_string($koneksi, trim($_POST['rt_number']));
$role         = mysqli_real_escape_string($koneksi, trim($_POST['role']));
$password     = !empty($_POST['password']) ? trim($_POST['password']) : '';

// Validate that the RT matches the user's RT
if ($rt !== $user_rt) {
    $_SESSION['error_message'] = 'Anda hanya dapat mengedit warga di RT Anda sendiri';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Additional validation for Bendahara: cannot change role to RT or Bendahara
if ($user_role === 'Bendahara' && ($role === 'RT' || $role === 'Bendahara')) {
    $_SESSION['error_message'] = 'Sebagai Bendahara, Anda hanya dapat mengedit data warga biasa';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = 'Format email tidak valid';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Validate NIK format (16 digits)
if (!preg_match('/^\d{16}$/', $nik)) {
    $_SESSION['error_message'] = 'NIK harus 16 digit angka';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

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

// Update warga table
$update_warga_query = "UPDATE warga SET nama_lengkap = '$nama' WHERE id_warga = $id_warga";
if (!mysqli_query($koneksi, $update_warga_query)) {
    $_SESSION['error_message'] = 'Gagal update data warga: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Update user table
$update_user_query = "UPDATE user SET
    username = '$username',
    email = '$email',
    nik = '$nik',
    rt_number = '$rt',
    role = '$role'";

if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $update_user_query .= ", password = '$hashed_password'";
}

$update_user_query .= " WHERE id_user = $id_user";

if (!mysqli_query($koneksi, $update_user_query)) {
    $_SESSION['error_message'] = 'Gagal update data user: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Success - redirect based on user role
$_SESSION['success_message'] = 'Data warga berhasil diperbarui';
header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php?success=edit");
exit();
