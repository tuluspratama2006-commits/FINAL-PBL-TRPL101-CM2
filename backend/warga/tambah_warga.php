<?php
session_start();
require_once '../../config/koneksi.php';

// Debug: Check database connection
if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_rt/warga.php");
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
if (!isset($_POST['nama_lengkap']) || !isset($_POST['email']) || !isset($_POST['nik']) ||
    !isset($_POST['rt_number']) || !isset($_POST['role'])) {
    $_SESSION['error_message'] = 'Data tidak lengkap';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

$nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
$email    = mysqli_real_escape_string($koneksi, trim($_POST['email']));
$nik      = mysqli_real_escape_string($koneksi, trim($_POST['nik']));
$rt       = mysqli_real_escape_string($koneksi, trim($_POST['rt_number']));
$role     = mysqli_real_escape_string($koneksi, trim($_POST['role']));

// Validate that the RT matches the user's RT
if ($rt !== $user_rt) {
    $_SESSION['error_message'] = 'Anda hanya dapat menambah warga di RT Anda sendiri';
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Additional validation for Bendahara: cannot add RT or Bendahara roles
if ($user_role === 'Bendahara' && ($role === 'RT' || $role === 'Bendahara')) {
    $_SESSION['error_message'] = 'Sebagai Bendahara, Anda hanya dapat menambah warga biasa';
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

// Insert warga data
$insert_warga_query = "INSERT INTO warga (nama_lengkap) VALUES ('$nama')";
if (!mysqli_query($koneksi, $insert_warga_query)) {
    $_SESSION['error_message'] = 'Gagal menambah data warga: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

$id_warga = mysqli_insert_id($koneksi);

// Generate default login credentials
$default_username = strtolower(str_replace(' ', '_', $nama)) . '_' . $id_warga;
$default_password = password_hash('password123', PASSWORD_DEFAULT);

// Insert user data
$insert_user_query = "INSERT INTO user (id_warga, username, email, password, nik, rt_number, role)
                     VALUES ('$id_warga', '$default_username', '$email', '$default_password', '$nik', '$rt', '$role')";

if (!mysqli_query($koneksi, $insert_user_query)) {
    // If user insert fails, delete the warga record to maintain data integrity
    mysqli_query($koneksi, "DELETE FROM warga WHERE id_warga = $id_warga");
    $_SESSION['error_message'] = 'Gagal menambah data user: ' . mysqli_error($koneksi);
    header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php");
    exit();
}

// Success - redirect based on user role
$_SESSION['success_message'] = 'Data warga berhasil ditambahkan';
header("Location: ../../dashboard_" . strtolower($user_role) . "/warga.php?success=tambah");
exit();
