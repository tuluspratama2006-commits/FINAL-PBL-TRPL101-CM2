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
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : null;
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';

    // Base query - filter by RT number
    $query = "
        SELECT
            ir.id_iuran,
            ir.id_warga,
            ir.bulan,
            ir.tahun,
            ir.jumlah_iuran,
            ir.tanggal_pembayaran,
            ir.status_pembayaran,
            ir.created_at,
            w.nama_lengkap,
            w.alamat,
            w.no_telepon,
            u.username,
            u.nik
        FROM iuran_rutin ir
        LEFT JOIN warga w ON ir.id_warga = w.id_warga
        LEFT JOIN user u ON w.id_warga = u.id_warga
        WHERE u.rt_number = '$rt_number'
    ";

    // Add filters
    $conditions = [];
    if (!empty($search)) {
        $conditions[] = "(w.nama_lengkap LIKE '%$search%' OR u.username LIKE '%$search%' OR u.nik LIKE '%$search%')";
    }
    if ($bulan !== null) {
        $conditions[] = "ir.bulan = $bulan";
    }
    if ($tahun !== null) {
        $conditions[] = "ir.tahun = $tahun";
    }
    if (!empty($status)) {
        $conditions[] = "ir.status_pembayaran = '$status'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY ir.tahun DESC, ir.bulan DESC, w.nama_lengkap ASC";

    $result = mysqli_query($koneksi, $query);

    if (!$result) {
        throw new Exception('Query error: ' . mysqli_error($koneksi));
    }

    $iuran_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $iuran_data[] = [
            'id_iuran' => $row['id_iuran'],
            'id_warga' => $row['id_warga'],
            'nama_lengkap' => $row['nama_lengkap'] ?? 'Nama tidak tersedia',
            'username' => $row['username'] ?? '',
            'nik' => $row['nik'] ?? '',
            'alamat' => $row['alamat'] ?? '',
            'no_telepon' => $row['no_telepon'] ?? '',
            'bulan' => $row['bulan'],
            'tahun' => $row['tahun'],
            'jumlah_iuran' => (int)$row['jumlah_iuran'],
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'status_pembayaran' => $row['status_pembayaran'],
            'created_at' => $row['created_at']
        ];
    }

    // Get summary statistics
    $summary_query = "
        SELECT
            COUNT(*) as total_iuran,
            SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as lunas_count,
            SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN 1 ELSE 0 END) as belum_count,
            SUM(CASE WHEN status_pembayaran = 'Lunas' THEN jumlah_iuran ELSE 0 END) as total_lunas,
            SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN jumlah_iuran ELSE 0 END) as total_belum
        FROM iuran_rutin ir
        LEFT JOIN warga w ON ir.id_warga = w.id_warga
        LEFT JOIN user u ON w.id_warga = u.id_warga
        WHERE u.rt_number = '$rt_number'
    ";

    if (!empty($conditions)) {
        $summary_query .= " AND " . implode(" AND ", $conditions);
    }

    $summary_result = mysqli_query($koneksi, $summary_query);
    $summary = mysqli_fetch_assoc($summary_result);

    echo json_encode([
        'success' => true,
        'data' => $iuran_data,
        'summary' => [
            'total_iuran' => (int)$summary['total_iuran'],
            'lunas_count' => (int)$summary['lunas_count'],
            'belum_count' => (int)$summary['belum_count'],
            'total_lunas' => (int)$summary['total_lunas'],
            'total_belum' => (int)$summary['total_belum']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
