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

try {
    // Get filter parameters
    $search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
    $kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : null;

    // Base query
    $query = "
        SELECT
            pk.id_pengeluaran,
            pk.tanggal_pengeluaran,
            pk.kategori,
            pk.deskripsi,
            pk.jumlah_pengeluaran,
            pk.bukti,
            pk.created_at,
            u.username as diajukan_oleh,
            u.rt_number
        FROM pengeluaran_kegiatan pk
        LEFT JOIN user u ON pk.id_user = u.id_user
        WHERE 1=1
    ";

    // Add filters
    $conditions = [];
    if (!empty($search)) {
        $conditions[] = "(pk.deskripsi LIKE '%$search%' OR pk.kategori LIKE '%$search%')";
    }
    if (!empty($kategori)) {
        $conditions[] = "pk.kategori = '$kategori'";
    }
    if ($tahun !== null) {
        $conditions[] = "YEAR(pk.tanggal_pengeluaran) = $tahun";
    }
    if ($bulan !== null) {
        $conditions[] = "MONTH(pk.tanggal_pengeluaran) = $bulan";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY pk.tanggal_pengeluaran DESC, pk.created_at DESC";

    $result = mysqli_query($koneksi, $query);

    if (!$result) {
        throw new Exception('Query error: ' . mysqli_error($koneksi));
    }

    $pengeluaran_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $pengeluaran_data[] = [
            'id_pengeluaran' => $row['id_pengeluaran'],
            'tanggal_pengeluaran' => $row['tanggal_pengeluaran'],
            'kategori' => $row['kategori'],
            'deskripsi' => $row['deskripsi'],
            'jumlah_pengeluaran' => (int)$row['jumlah_pengeluaran'],
            'bukti' => $row['bukti'],
            'diajukan_oleh' => $row['diajukan_oleh'] ?? 'Tidak diketahui',
            'rt_number' => $row['rt_number'],
            'created_at' => $row['created_at']
        ];
    }

    // Get summary statistics
    $summary_query = "
        SELECT
            COUNT(*) as total_pengeluaran,
            SUM(jumlah_pengeluaran) as total_jumlah,
            AVG(jumlah_pengeluaran) as rata_rata
        FROM pengeluaran_kegiatan pk
        LEFT JOIN user u ON pk.id_user = u.id_user
        WHERE 1=1
    ";

    if (!empty($conditions)) {
        $summary_query .= " AND " . implode(" AND ", $conditions);
    }

    $summary_result = mysqli_query($koneksi, $summary_query);
    $summary = mysqli_fetch_assoc($summary_result);

    echo json_encode([
        'success' => true,
        'data' => $pengeluaran_data,
        'summary' => [
            'total_pengeluaran' => (int)$summary['total_pengeluaran'],
            'total_jumlah' => (int)$summary['total_jumlah'],
            'rata_rata' => (float)$summary['rata_rata']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
