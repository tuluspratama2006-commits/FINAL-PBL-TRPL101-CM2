<?php
session_start();
require_once '../../config/koneksi.php';

// Debug: Check database connection
if (!$koneksi) {
    echo json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
    exit();
}

if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has permission (RT or Bendahara)
$user_role = $_SESSION['role'];
if ($user_role !== 'RT' && $user_role !== 'Bendahara') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id_user'])) {
    echo json_encode(['error' => 'ID tidak ditemukan']);
    exit;
}

$id_user = intval($_GET['id_user']);

// Get the RT number of the logged-in user
$user_rt = $_SESSION['rt_number'];

$query = "
SELECT
    u.id_user,
    u.username,
    u.email,
    u.nik,
    u.rt_number,
    u.role,
    w.nama_lengkap,
    w.alamat,
    w.no_telepon,
    w.id_warga
FROM user u
LEFT JOIN warga w ON u.id_warga = w.id_warga
WHERE u.id_user = $id_user AND u.rt_number = '$user_rt'
LIMIT 1
";

$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode(['error' => 'Database query error: ' . mysqli_error($koneksi)]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Data warga tidak ditemukan atau tidak memiliki akses']);
    exit;
}

$data = mysqli_fetch_assoc($result);

// Ensure we have valid data
if (!$data) {
    echo json_encode(['error' => 'Data tidak valid']);
    exit;
}

/* Pecah RT / RW */
$rt = $data['rt_number'] ?? '';
$rw = 'RW 01'; // kalau RW belum ada kolomnya

echo json_encode([
    'id_user'      => $data['id_user'],
    'nama_lengkap' => $data['nama_lengkap'] ?? '',
    'email'        => $data['email'] ?? '',
    'nik'          => $data['nik'] ?? '',
    'rt'           => $rt,
    'rw'           => $rw,
    'role'         => $data['role'] ?? 'warga'
]);
