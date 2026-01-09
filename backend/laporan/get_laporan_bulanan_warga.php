<?php
session_start();
require_once '../../config/koneksi.php';

// Set JSON response header
header('Content-Type: application/json');

// Check database connection
if (!$koneksi) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
    exit();
}

// Check login and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'warga') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get id_warga for the logged-in user
$query_warga = "SELECT u.id_warga FROM user u WHERE u.nik = '" . mysqli_real_escape_string($koneksi, $_SESSION['nik']) . "'";
$result_warga = mysqli_query($koneksi, $query_warga);
$warga_data = mysqli_fetch_assoc($result_warga);
$id_warga = $warga_data['id_warga'] ?? null;

if (!$id_warga) {
    http_response_code(403);
    echo json_encode(['error' => 'User data not found']);
    exit();
}

// Get and validate parameters
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validate bulan
if ($bulan < 1 || $bulan > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Bulan tidak valid']);
    exit();
}

// Validate tahun
if ($tahun < 2000 || $tahun > date('Y') + 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Tahun tidak valid']);
    exit();
}

// Prepared statement for total pemasukan
$query_pemasukan = "
    SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
    FROM iuran_rutin
    WHERE bulan = ? AND tahun = ? AND status_pembayaran = 'Lunas' AND id_warga = ?
";
$stmt_pemasukan = mysqli_prepare($koneksi, $query_pemasukan);
mysqli_stmt_bind_param($stmt_pemasukan, 'iii', $bulan, $tahun, $id_warga);
mysqli_stmt_execute($stmt_pemasukan);
$result_pemasukan = mysqli_stmt_get_result($stmt_pemasukan);
$total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];
mysqli_stmt_close($stmt_pemasukan);

// Prepared statement for total pengeluaran
$query_pengeluaran = "
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan
    WHERE MONTH(tanggal_pengeluaran) = ? AND YEAR(tanggal_pengeluaran) = ?
";
$stmt_pengeluaran = mysqli_prepare($koneksi, $query_pengeluaran);
mysqli_stmt_bind_param($stmt_pengeluaran, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_pengeluaran);
$result_pengeluaran = mysqli_stmt_get_result($stmt_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];
mysqli_stmt_close($stmt_pengeluaran);

// Hitung saldo
$saldo = $total_pemasukan - $total_pengeluaran;

// Query untuk data transaksi bulan ini menggunakan prepared statements
$query_iuran = "
    SELECT
        'iuran' as tipe,
        i.tanggal_pembayaran as tanggal,
        CONCAT('Iuran ', w.nama_lengkap, ' - ', b.bulan_nama, ' ', i.tahun) as deskripsi,
        'Iuran' as kategori,
        i.jumlah_iuran as pemasukan,
        0 as pengeluaran
    FROM iuran_rutin i
    JOIN warga w ON i.id_warga = w.id_warga
    JOIN (
        SELECT 1 as bulan, 'Januari' as bulan_nama UNION ALL
        SELECT 2, 'Februari' UNION ALL
        SELECT 3, 'Maret' UNION ALL
        SELECT 4, 'April' UNION ALL
        SELECT 5, 'Mei' UNION ALL
        SELECT 6, 'Juni' UNION ALL
        SELECT 7, 'Juli' UNION ALL
        SELECT 8, 'Agustus' UNION ALL
        SELECT 9, 'September' UNION ALL
        SELECT 10, 'Oktober' UNION ALL
        SELECT 11, 'November' UNION ALL
        SELECT 12, 'Desember'
    ) b ON i.bulan = b.bulan
    WHERE CAST(i.bulan AS UNSIGNED) = ? AND i.tahun = ? AND i.status_pembayaran = 'Lunas' AND i.id_warga = ?
";

$query_pengeluaran_transaksi = "
    SELECT
        'pengeluaran' as tipe,
        p.tanggal_pengeluaran as tanggal,
        p.deskripsi as deskripsi,
        p.kategori as kategori,
        0 as pemasukan,
        p.jumlah_pengeluaran as pengeluaran
    FROM pengeluaran_kegiatan p
    WHERE MONTH(p.tanggal_pengeluaran) = ? AND YEAR(p.tanggal_pengeluaran) = ?
";

// Prepare and execute iuran query
$stmt_iuran = mysqli_prepare($koneksi, $query_iuran);
mysqli_stmt_bind_param($stmt_iuran, 'iii', $bulan, $tahun, $id_warga);
mysqli_stmt_execute($stmt_iuran);
$result_iuran = mysqli_stmt_get_result($stmt_iuran);

// Prepare and execute pengeluaran query
$stmt_pengeluaran_transaksi = mysqli_prepare($koneksi, $query_pengeluaran_transaksi);
mysqli_stmt_bind_param($stmt_pengeluaran_transaksi, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_pengeluaran_transaksi);
$result_pengeluaran_transaksi = mysqli_stmt_get_result($stmt_pengeluaran_transaksi);

// Combine results
$transaksi_data = [];
while ($row = mysqli_fetch_assoc($result_iuran)) {
    $transaksi_data[] = $row;
}
while ($row = mysqli_fetch_assoc($result_pengeluaran_transaksi)) {
    $transaksi_data[] = $row;
}

// Sort by tanggal DESC
usort($transaksi_data, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

mysqli_stmt_close($stmt_iuran);
mysqli_stmt_close($stmt_pengeluaran_transaksi);

// Query untuk breakdown per kategori pengeluaran
$query_breakdown = "
    SELECT
        kategori,
        SUM(jumlah_pengeluaran) as total
    FROM pengeluaran_kegiatan
    WHERE MONTH(tanggal_pengeluaran) = ? AND YEAR(tanggal_pengeluaran) = ?
    GROUP BY kategori
    ORDER BY total DESC
";
$stmt_breakdown = mysqli_prepare($koneksi, $query_breakdown);
mysqli_stmt_bind_param($stmt_breakdown, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_breakdown);
$result_breakdown = mysqli_stmt_get_result($stmt_breakdown);

// Prepare chart data
$chart_labels = [];
$chart_data = [];
$pie_data = [];
$chart_colors = [];
$color_palette = ['#6f42c1', '#fd7e14', '#ffc107', '#dc3545', '#198754', '#0d6efd', '#17a2b8', '#6c757d', '#e83e8c', '#20c997'];
$category_color_map = [];
$breakdown_data = [];

if (mysqli_num_rows($result_breakdown) > 0) {
    $color_index = 0;
    while ($row = mysqli_fetch_assoc($result_breakdown)) {
        $chart_labels[] = $row['kategori'];
        $chart_data[] = $row['total'] / 1000; // Convert to thousands for bar chart
        $pie_data[] = $row['total']; // Raw data for pie chart

        // Assign colors from palette for multicolored chart
        $color = $color_palette[$color_index % count($color_palette)];
        $chart_colors[] = $color;
        $category_color_map[$row['kategori']] = $color;

        // Add to breakdown data
        $breakdown_data[] = [
            'kategori' => $row['kategori'],
            'total' => $row['total'],
            'color' => $color
        ];

        $color_index++;
    }
}

// Add color for 'Iuran' category
$category_color_map['Iuran'] = '#198754'; // Green color for income

// Query to get min and max years from database
$query_min_max_years = "
    SELECT
        MIN(tahun) as min_year,
        MAX(tahun) as max_year
    FROM (
        SELECT tahun FROM iuran_rutin
        UNION
        SELECT YEAR(tanggal_pengeluaran) as tahun FROM pengeluaran_kegiatan
    ) as years
";
$result_years = mysqli_query($koneksi, $query_min_max_years);
$row_years = mysqli_fetch_assoc($result_years);
$min_year = $row_years['min_year'] ?? date('Y');
$max_year = $row_years['max_year'] ?? date('Y');

// Prepare transactions with badge_color
$transactions = [];
foreach ($transaksi_data as $transaction) {
    $transactions[] = [
        'tanggal' => $transaction['tanggal'],
        'deskripsi' => $transaction['deskripsi'],
        'kategori' => $transaction['kategori'],
        'pemasukan' => (int)$transaction['pemasukan'],
        'pengeluaran' => (int)$transaction['pengeluaran'],
        'badge_color' => $category_color_map[$transaction['kategori']] ?? '#6c757d'
    ];
}

// Return JSON in expected format
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => [
        'summary' => [
            'total_pemasukan' => (int)$total_pemasukan,
            'total_pengeluaran' => (int)$total_pengeluaran,
            'saldo_akhir' => (int)$saldo
        ],
        'chart' => [
            'labels' => $chart_labels,
            'pie_data' => $pie_data,
            'bar_data' => $chart_data,
            'colors' => $chart_colors
        ],
        'breakdown' => $breakdown_data,
        'transactions' => $transactions
    ]
]);
?>
