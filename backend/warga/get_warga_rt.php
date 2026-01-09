<?php
session_start();
require_once '../../config/koneksi.php';

header('Content-Type: application/json');

// Check if user is logged in and is RT
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    echo json_encode(['error' => 'Akses ditolak. Silakan login sebagai RT.']);
    exit();
}

$rt_number = $_SESSION['rt_number'] ?? '';

if (empty($rt_number)) {
    echo json_encode(['error' => 'RT number tidak ditemukan dalam session.']);
    exit();
}

try {
    // Get filter parameters
    $search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
    $role = isset($_GET['role']) ? mysqli_real_escape_string($koneksi, $_GET['role']) : '';

    // Base query - filter by RT number
    $query = "
        SELECT
            u.id_user,
            u.username,
            u.nik,
            u.rt_number,
            u.role,
            u.email,
            w.id_warga,
            w.nama_lengkap,
            w.alamat,
            w.no_telepon,
            w.status
        FROM user u
        LEFT JOIN warga w ON u.id_warga = w.id_warga
        WHERE u.rt_number = '$rt_number'
    ";

    // Add filters
    $conditions = [];
    if (!empty($search)) {
        $conditions[] = "(w.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%' OR u.nik LIKE '%$search%')";
    }
    if (!empty($role)) {
        $conditions[] = "u.role = '$role'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY w.nama_lengkap ASC";

    $result = mysqli_query($koneksi, $query);

    if (!$result) {
        throw new Exception('Query error: ' . mysqli_error($koneksi));
    }

    $warga_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $warga_data[] = [
            'id_user' => $row['id_user'],
            'id_warga' => $row['id_warga'],
            'username' => $row['username'],
            'nik' => $row['nik'],
            'rt_number' => $row['rt_number'],
            'role' => $row['role'],
            'email' => $row['email'],
            'nama_lengkap' => $row['nama_lengkap'] ?? 'Nama tidak tersedia',
            'alamat' => $row['alamat'] ?? '',
            'no_telepon' => $row['no_telepon'] ?? '',
            'status' => $row['status'] ?? ''
        ];
    }

    // Get summary statistics
    $summary_query = "
        SELECT
            COUNT(*) as total_warga,
            SUM(CASE WHEN role = 'warga' THEN 1 ELSE 0 END) as warga_count,
            SUM(CASE WHEN role = 'RT' THEN 1 ELSE 0 END) as rt_count,
            SUM(CASE WHEN role = 'Bendahara' THEN 1 ELSE 0 END) as bendahara_count
        FROM user
        WHERE rt_number = '$rt_number'
    ";

    $summary_result = mysqli_query($koneksi, $summary_query);
    $summary = mysqli_fetch_assoc($summary_result);

    echo json_encode([
        'success' => true,
        'data' => $warga_data,
        'summary' => [
            'total_warga' => (int)$summary['total_warga'],
            'warga_count' => (int)$summary['warga_count'],
            'rt_count' => (int)$summary['rt_count'],
            'bendahara_count' => (int)$summary['bendahara_count']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
