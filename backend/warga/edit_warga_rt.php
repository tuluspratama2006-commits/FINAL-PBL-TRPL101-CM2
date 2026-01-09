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
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_rt/warga.php';

// Ambil data dari form
$id_user = mysqli_real_escape_string($koneksi, $_POST['id_user']);
$nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$email = mysqli_real_escape_string($koneksi, $_POST['email']);
$nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
$rt_number = mysqli_real_escape_string($koneksi, $_POST['rt_number']);
$role = mysqli_real_escape_string($koneksi, $_POST['role']);
$password = !empty($_POST['password']) ? trim($_POST['password']) : '';

// Validasi
if (empty($id_user)) {
    $_SESSION['error_message'] = 'ID user harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($nama_lengkap)) {
    $_SESSION['error_message'] = 'Nama lengkap harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($username)) {
    $_SESSION['error_message'] = 'Username harus diisi.';
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

// Validasi password jika diisi
if (!empty($password) && strlen($password) < 6) {
    $_SESSION['error_message'] = 'Password minimal 6 karakter.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah user ada dan dapat diedit
$query_check = "SELECT u.*, w.id_warga FROM user u
                LEFT JOIN warga w ON u.id_warga = w.id_warga
                WHERE u.id_user = '$id_user'";
$result_check = mysqli_query($koneksi, $query_check);

if (!$result_check || mysqli_num_rows($result_check) == 0) {
    $_SESSION['error_message'] = 'Data user tidak ditemukan.';
    header("Location: $redirect_location");
    exit();
}

$row = mysqli_fetch_assoc($result_check);
$id_warga = $row['id_warga'];

// Cek apakah email sudah digunakan user lain
$query_check_email = "SELECT id_user FROM user WHERE email = '$email' AND id_user != '$id_user'";
$result_check_email = mysqli_query($koneksi, $query_check_email);
if (mysqli_num_rows($result_check_email) > 0) {
    $_SESSION['error_message'] = 'Email sudah digunakan oleh user lain.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah username sudah digunakan user lain
$query_check_username = "SELECT id_user FROM user WHERE username = '$username' AND id_user != '$id_user'";
$result_check_username = mysqli_query($koneksi, $query_check_username);
if (mysqli_num_rows($result_check_username) > 0) {
    $_SESSION['error_message'] = 'Username sudah digunakan oleh user lain.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah NIK sudah digunakan user lain
$query_check_nik = "SELECT id_user FROM user WHERE nik = '$nik' AND id_user != '$id_user'";
$result_check_nik = mysqli_query($koneksi, $query_check_nik);
if (mysqli_num_rows($result_check_nik) > 0) {
    $_SESSION['error_message'] = 'NIK sudah digunakan oleh user lain.';
    header("Location: $redirect_location");
    exit();
}

// Mulai transaksi
mysqli_begin_transaction($koneksi);

try {
    // Update tabel warga
    $query_update_warga = "UPDATE warga SET nama_lengkap = '$nama_lengkap' WHERE id_warga = '$id_warga'";
    $result_update_warga = mysqli_query($koneksi, $query_update_warga);

    if (!$result_update_warga) {
        throw new Exception('Gagal mengupdate data warga: ' . mysqli_error($koneksi));
    }

    // Update tabel user
    $query_update_user = "UPDATE user SET
                         username = '$username',
                         email = '$email',
                         nik = '$nik',
                         rt_number = '$rt_number',
                         role = '$role'";

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query_update_user .= ", password = '$hashed_password'";
    }

    $query_update_user .= " WHERE id_user = '$id_user'";

    $result_update_user = mysqli_query($koneksi, $query_update_user);

    if (!$result_update_user) {
        throw new Exception('Gagal mengupdate data user: ' . mysqli_error($koneksi));
    }

    // Commit transaksi
    mysqli_commit($koneksi);

    $_SESSION['success_message'] = 'Data warga berhasil diperbarui.';

} catch (Exception $e) {
    // Rollback transaksi jika ada error
    mysqli_rollback($koneksi);
    $_SESSION['error_message'] = $e->getMessage();
}

header("Location: $redirect_location");
exit();
?>
