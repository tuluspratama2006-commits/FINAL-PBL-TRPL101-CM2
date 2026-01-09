<?php
session_start();
require_once '../../config/koneksi.php';

if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_rt/warga.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RT', 'Bendahara'])) {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT atau Bendahara.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_rt/warga.php';

// Ambil data dari form
$nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
$email = mysqli_real_escape_string($koneksi, $_POST['email']);
$nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
$rt_number = mysqli_real_escape_string($koneksi, $_POST['rt_number']);
$role = mysqli_real_escape_string($koneksi, $_POST['role']);

// Validasi khusus untuk Bendahara: hanya bisa menambah warga untuk RT sendiri dan hanya role 'warga'
if ($_SESSION['role'] === 'Bendahara') {
    if ($rt_number !== $_SESSION['rt_number']) {
        $_SESSION['error_message'] = 'Bendahara hanya dapat menambah warga untuk RT ' . $_SESSION['rt_number'] . '.';
        header("Location: $redirect_location");
        exit();
    }
    if ($role !== 'warga') {
        $_SESSION['error_message'] = 'Bendahara hanya dapat menambah warga biasa.';
        header("Location: $redirect_location");
        exit();
    }
}

// Validasi
if (empty($nama_lengkap)) {
    $_SESSION['error_message'] = 'Nama lengkap harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($email)) {
    $_SESSION['error_message'] = 'Email harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($nik)) {
    $_SESSION['error_message'] = 'NIK harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($rt_number)) {
    $_SESSION['error_message'] = 'RT harus dipilih.';
    header("Location: $redirect_location");
    exit();
}

if (empty($role)) {
    $_SESSION['error_message'] = 'Role harus dipilih.';
    header("Location: $redirect_location");
    exit();
}

// Validasi format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = 'Format email tidak valid.';
    header("Location: $redirect_location");
    exit();
}

// Validasi NIK (16 digit)
if (!preg_match('/^\d{16}$/', $nik)) {
    $_SESSION['error_message'] = 'NIK harus 16 digit angka.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah email sudah ada
$query_check_email = "SELECT id_user FROM user WHERE email = '$email'";
$result_check_email = mysqli_query($koneksi, $query_check_email);
if (mysqli_num_rows($result_check_email) > 0) {
    $_SESSION['error_message'] = 'Email sudah terdaftar.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah NIK sudah ada
$query_check_nik = "SELECT id_user FROM user WHERE nik = '$nik'";
$result_check_nik = mysqli_query($koneksi, $query_check_nik);
if (mysqli_num_rows($result_check_nik) > 0) {
    $_SESSION['error_message'] = 'NIK sudah terdaftar.';
    header("Location: $redirect_location");
    exit();
}

// Generate username dari email
$username = explode('@', $email)[0];

// Cek apakah username sudah ada, jika ya tambahkan angka
$original_username = $username;
$counter = 1;
while (true) {
    $query_check_username = "SELECT id_user FROM user WHERE username = '$username'";
    $result_check_username = mysqli_query($koneksi, $query_check_username);
    if (mysqli_num_rows($result_check_username) == 0) {
        break;
    }
    $username = $original_username . $counter;
    $counter++;
}

// Generate password default
$default_password = password_hash('123456', PASSWORD_DEFAULT);

// Insert ke tabel warga dulu
$query_warga = "INSERT INTO warga (nama_lengkap, alamat, no_telepon, status, created_at)
                VALUES ('$nama_lengkap', '', '', 'aktif', NOW())";

if (mysqli_query($koneksi, $query_warga)) {
    $id_warga = mysqli_insert_id($koneksi);

    // Insert ke tabel user
    $query_user = "INSERT INTO user (username, password, email, nik, rt_number, role, id_warga, created_at)
                   VALUES ('$username', '$default_password', '$email', '$nik', '$rt_number', '$role', '$id_warga', NOW())";

    if (mysqli_query($koneksi, $query_user)) {
        $_SESSION['success_message'] = 'Warga berhasil ditambahkan. Username: ' . $username . ', Password default: 123456';
    } else {
        // Hapus data warga jika gagal insert user
        mysqli_query($koneksi, "DELETE FROM warga WHERE id_warga = '$id_warga'");
        $_SESSION['error_message'] = 'Gagal menambahkan user: ' . mysqli_error($koneksi);
    }
} else {
    $_SESSION['error_message'] = 'Gagal menambahkan warga: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>
