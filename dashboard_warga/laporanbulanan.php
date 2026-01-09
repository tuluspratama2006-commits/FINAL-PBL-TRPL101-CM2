<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

// Cek apakah role adalah warga
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/koneksi.php';

// Ambil data user dari database berdasarkan nik
$query_user = "SELECT u.id_user, u.id_warga, u.rt_number, w.nama_lengkap FROM user u JOIN warga w ON u.id_warga = w.id_warga WHERE u.nik = '" . mysqli_real_escape_string($koneksi, $_SESSION['nik']) . "'";
$result_user = mysqli_query($koneksi, $query_user);
$user_data = mysqli_fetch_assoc($result_user);
$id_warga = $user_data['id_warga'] ?? null;
$rt_number = $user_data['rt_number'] ?? null;

if (!$id_warga) {
    header("Location: ../auth/login.php");
    exit();
}

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : null;

// Array bulan Indonesia
$bulan_indo = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
];

$bulan_nama = ($bulan !== null && isset($bulan_indo[$bulan-1])) ? $bulan_indo[$bulan-1] : '';

// Prepared statement for total pemasukan (semua warga)
$query_pemasukan = "
    SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
    FROM iuran_rutin
    WHERE bulan = ? AND tahun = ? AND status_pembayaran = 'Lunas'
";
$stmt_pemasukan = mysqli_prepare($koneksi, $query_pemasukan);
mysqli_stmt_bind_param($stmt_pemasukan, 'ii', $bulan, $tahun);
mysqli_stmt_execute($stmt_pemasukan);
$result_pemasukan = mysqli_stmt_get_result($stmt_pemasukan);
$total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];
mysqli_stmt_close($stmt_pemasukan);

// Prepared statement for total pengeluaran (semua pengeluaran dari RT yang sama)
$query_pengeluaran = "
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan pk
    LEFT JOIN user u ON pk.id_user = u.id_user
    WHERE MONTH(pk.tanggal_pengeluaran) = ? AND YEAR(pk.tanggal_pengeluaran) = ? AND u.rt_number = ?
";
$stmt_pengeluaran = mysqli_prepare($koneksi, $query_pengeluaran);
mysqli_stmt_bind_param($stmt_pengeluaran, 'iii', $bulan, $tahun, $rt_number);
mysqli_stmt_execute($stmt_pengeluaran);
$result_pengeluaran = mysqli_stmt_get_result($stmt_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];
mysqli_stmt_close($stmt_pengeluaran);

// Hitung saldo
$saldo = $total_pemasukan - $total_pengeluaran;

// Query untuk data transaksi bulan ini menggunakan prepared statements (hanya data warga sendiri)
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

// Query untuk pengeluaran transaksi (semua pengeluaran dari RT yang sama)
$query_pengeluaran_transaksi = "
    SELECT
        'pengeluaran' as tipe,
        pk.tanggal_pengeluaran as tanggal,
        pk.deskripsi as deskripsi,
        pk.kategori as kategori,
        0 as pemasukan,
        pk.jumlah_pengeluaran as pengeluaran
    FROM pengeluaran_kegiatan pk
    LEFT JOIN user u ON pk.id_user = u.id_user
    WHERE MONTH(pk.tanggal_pengeluaran) = ? AND YEAR(pk.tanggal_pengeluaran) = ? AND u.rt_number = ?
";

// Prepare and execute iuran query
$stmt_iuran = mysqli_prepare($koneksi, $query_iuran);
mysqli_stmt_bind_param($stmt_iuran, 'iii', $bulan, $tahun, $id_warga);
mysqli_stmt_execute($stmt_iuran);
$result_iuran = mysqli_stmt_get_result($stmt_iuran);

// Prepare and execute pengeluaran query
$stmt_pengeluaran_transaksi = mysqli_prepare($koneksi, $query_pengeluaran_transaksi);
mysqli_stmt_bind_param($stmt_pengeluaran_transaksi, 'iii', $bulan, $tahun, $rt_number);
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

// Query untuk breakdown per kategori pengeluaran (hanya pengeluaran warga sendiri)
$query_breakdown = "
    SELECT
        kategori,
        SUM(jumlah_pengeluaran) as total
    FROM pengeluaran_kegiatan pk
    LEFT JOIN user u ON pk.id_user = u.id_user
    WHERE MONTH(pk.tanggal_pengeluaran) = ? AND YEAR(pk.tanggal_pengeluaran) = ? AND u.id_warga = ?
    GROUP BY kategori
    ORDER BY total DESC
";
$stmt_breakdown = mysqli_prepare($koneksi, $query_breakdown);
mysqli_stmt_bind_param($stmt_breakdown, 'iii', $bulan, $tahun, $id_warga);
mysqli_stmt_execute($stmt_breakdown);
$result_breakdown = mysqli_stmt_get_result($stmt_breakdown);

// Collect chart data from breakdown
$chart_labels = [];
$chart_data = [];
$pie_data = [];
$chart_colors = [];
$color_palette = ['#6f42c1', '#fd7e14', '#ffc107', '#dc3545', '#198754', '#0d6efd', '#17a2b8', '#6c757d', '#e83e8c', '#20c997'];
$category_color_map = [];
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
        $color_index++;
    }
}
// Add color for 'Iuran' category
$category_color_map['Iuran'] = '#198754'; // Green color for income

// Query to get min and max years from database (berdasarkan data warga sendiri)
$query_min_max_years = "
    SELECT
        MIN(tahun) as min_year,
        MAX(tahun) as max_year
    FROM (
        SELECT tahun FROM iuran_rutin WHERE id_warga = ?
        UNION
        SELECT YEAR(tanggal_pengeluaran) as tahun FROM pengeluaran_kegiatan pk
        LEFT JOIN user u ON pk.id_user = u.id_user WHERE u.id_warga = ?
    ) as years
";
$stmt_years = mysqli_prepare($koneksi, $query_min_max_years);
mysqli_stmt_bind_param($stmt_years, 'ii', $id_warga, $id_warga);
mysqli_stmt_execute($stmt_years);
$result_years = mysqli_stmt_get_result($stmt_years);
$row_years = mysqli_fetch_assoc($result_years);
$min_year = $row_years['min_year'] ?? date('Y');
$max_year = $row_years['max_year'] ?? date('Y');
mysqli_stmt_close($stmt_years);

// Fungsi format rupiah
function format_rupiah($n){
    $s = $n < 0 ? '-' : '';
    return 'Rp '.$s.number_format(abs($n),0,',','.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kas - Bulanan</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --border-radius: 12px;
            --card-border: #e4e7ec;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 24px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 80px);
        }

        @media (min-width: 769px) {
            .main-content.sidebar-open {
                margin-left: 260px;
            }
        }

        /* Header Laporan */
        .laporan-header {
            background: var(--white);
            padding: 24px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .laporan-title h1 {
            color: #1a1a1a;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .laporan-title p {
            color: #6c757d;
            margin: 0;
            font-size: 16px;
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
        }

        /* Navigation */
        .nav-tabs {
            border: none;
            background: transparent;
            padding: 0;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 8px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        /* Export and Input Buttons */
        .btn-export {
            background-color: white;
            color: #495057;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .btn-input {
            background-color: #072f66;
            color: white;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
        }

        /* Filter Card */
        .filter-card {
            background: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-card .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .filter-card .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
        }

        .filter-card .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 20px;
        }

        /* METRICS */
        .metric-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top: 20px;}
        .metric-card{background:#fff; border:1px solid var(--card-border); border-radius:14px; padding:18px; position:relative; min-height:118px; box-shadow:0 2px 6px rgba(0,0,0,0.03)}
        .metric-title{font-size:13px; color:#222}
        .metric-value{font-size:22px; font-weight:700; margin-top:6px}
        .metric-badge{display:inline-block; margin-top:10px; padding:6px 10px; border-radius:10px; font-weight:600; font-size:13px; background:#d9fbe7; color:#0b7a3a}
        .icon-wrapper{position:absolute; top:16px; right:16px; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#E4E8F0}
        .icon-wrapper i,.icon-wrapper span{font-size:22px; color:#0A2A66}

        /* Chart and Breakdown Section */
        .chart-breakdown-row {
            margin-bottom: 32px;
        }

        .chart-container, .breakdown-section {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
        }

        .chart-container {
            padding: 24px;
        }

        .breakdown-section {
            padding: 24px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: black;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-left: -10px;
            text-align: left;
        }

        .section-title i {
            margin-right: 10px;
            color: black;
        }

        .chart-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .chart-controls .btn {
            border-radius: 6px;
            font-size: 14px;
            padding: 6px 12px;
            background-color: #072f66;
            color: white;
            border: none;
            transition: all 0.2s ease;
        }

        .chart-controls .btn:active {
            transform: scale(0.95);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .chart-box {
            height: 300px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Breakdown Items */
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
        }

        .breakdown-item .category-name {
            font-weight: 500;
            color: #495057;
            flex: 1;
        }

        .breakdown-item .amount {
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .full-separator {
            border-bottom: 2px solid #dee2e6;
            margin: 0 -24px;
        }

        .total-breakdown {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
        }

        .total-breakdown .category-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
        }

        /* Table */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
            margin-top: 32px;
        }

        .table-header {
            padding: 24px 24px 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 0;
        }

        .table-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .table-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .table-container .btn {
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        .table-container .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            color: #495057;
            border: none;
            padding: 18px 24px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody td {
            padding: 18px 24px;
            border: none;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
            font-size: 14px;
        }

        .table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .table tbody tr:hover {
            background-color: #e3f2fd;
            transition: all 0.2s ease;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge-category {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
                margin-top: 70px;
            }

            .laporan-header {
                padding: 20px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .logo-section {
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .laporan-title h1 {
                font-size: 24px;
            }

            .summary-card {
                padding: 20px;
            }

            .summary-card h3 {
                font-size: 24px;
            }

            .chart-box {
                height: 250px;
            }

            .chart-breakdown-row .col-md-6 {
                margin-bottom: 24px;
            }

            .table-responsive {
                font-size: 14px;
            }

            .table th, .table td {
                padding: 12px 8px;
                font-size: 12px;
            }

            .table th:nth-child(2), .table td:nth-child(2) {
                min-width: 200px;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="laporan-header">
            <div class="header-content">
                <div class="logo-section">
                    <div class="laporan-title">
                        <h1>Laporan Kas Bulanan</h1>
                        <p>Laporan keuangan bulanan dan tahunan</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-export" id="exportExcelBtn">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </button>
                    <button class="btn btn-input" id="exportPdfBtn">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
            <!-- Navigation -->
            <div class="mt-4">
                <nav class="nav nav-tabs">
                    <a href="laporanbulanan.php" class="nav-link active">
                        <i class="bi bi-calendar-month"></i> Laporan Bulanan
                    </a>
                    <a href="laporantahunan.php" class="nav-link">
                        <i class="bi bi-calendar"></i> Laporan Tahunan
                    </a>
                </nav>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="d-flex gap-3 align-items-end">
                <div class="mb-3 flex-fill">
                    <label class="form-label">Pilih Bulan</label>
                    <select class="form-select" id="bulanSelect">
                        <option value="" <?php echo !isset($_GET['bulan']) || empty($_GET['bulan']) ? 'selected' : ''; ?>>Pilih Bulan</option>
                        <option value="01" <?php echo $bulan == 1 ? 'selected' : ''; ?>>Januari</option>
                        <option value="02" <?php echo $bulan == 2 ? 'selected' : ''; ?>>Februari</option>
                        <option value="03" <?php echo $bulan == 3 ? 'selected' : ''; ?>>Maret</option>
                        <option value="04" <?php echo $bulan == 4 ? 'selected' : ''; ?>>April</option>
                        <option value="05" <?php echo $bulan == 5 ? 'selected' : ''; ?>>Mei</option>
                        <option value="06" <?php echo $bulan == 6 ? 'selected' : ''; ?>>Juni</option>
                        <option value="07" <?php echo $bulan == 7 ? 'selected' : ''; ?>>Juli</option>
                        <option value="08" <?php echo $bulan == 8 ? 'selected' : ''; ?>>Agustus</option>
                        <option value="09" <?php echo $bulan == 9 ? 'selected' : ''; ?>>September</option>
                        <option value="10" <?php echo $bulan == 10 ? 'selected' : ''; ?>>Oktober</option>
                        <option value="11" <?php echo $bulan == 11 ? 'selected' : ''; ?>>November</option>
                        <option value="12" <?php echo $bulan == 12 ? 'selected' : ''; ?>>Desember</option>
                    </select>
                </div>
                <div class="mb-3 flex-fill">
                    <label class="form-label">Tahun</label>
                    <select class="form-select" id="tahunSelect">
                        <option value="" <?php echo !isset($_GET['tahun']) || empty($_GET['tahun']) ? 'selected' : ''; ?>>Pilih Tahun</option>
                        <?php
                        for ($year = $min_year; $year <= $max_year; $year++) {
                            $selected = ($year == $tahun) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <button class="btn btn-input" id="filterBtn" onclick="updateFilter()">
                        <i class="bi bi-search"></i> Generate Laporan
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="metric-grid" style="margin-bottom: 32px;">
            <div class="metric-card">
                <div class="metric-title">Total Pemasukan</div>
                <div class="metric-value"><?php echo format_rupiah($total_pemasukan); ?></div>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Total Pengeluaran</div>
                <div class="metric-value"><?php echo format_rupiah($total_pengeluaran); ?></div>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-down"></i>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Saldo Akhir</div>
                <div class="metric-value"><?php echo format_rupiah($saldo); ?></div>
                <div class="icon-wrapper">
                    <i class="bi bi-<?php echo $saldo >= 0 ? 'graph-up' : 'graph-down'; ?>"></i>
                </div>
            </div>
        </div>
        <!-- Komposisi Pengeluaran and Breakdown -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="section-title" style="color: black;">
                        <i class="bi bi-pie-chart"></i> Komposisi Pengeluaran
                    </div>
                    <div class="chart-controls">
                        <input type="radio" class="btn-check" name="chartType" id="pieBtn" autocomplete="off" checked>
                        <label class="btn btn-input" for="pieBtn">
                            <i class="bi bi-pie-chart"></i> Pie Chart
                        </label>
                        <input type="radio" class="btn-check" name="chartType" id="barBtn" autocomplete="off">
                        <label class="btn btn-input" for="barBtn">
                            <i class="bi bi-bar-chart"></i> Bar Chart
                        </label>
                    </div>
                    <div class="chart-box">
                        <canvas id="pieChart" class="chart-canvas" style="display: block;"></canvas>
                        <canvas id="barChart" class="chart-canvas" style="display: none;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="breakdown-section">
                    <div class="section-title" style="color: black;">
                        <i class="bi bi-bar-chart"></i> Breakdown per Kategori
                    </div>
                    <div class="breakdown-container">
                        <?php
                        if (mysqli_num_rows($result_breakdown) > 0) {
                            mysqli_data_seek($result_breakdown, 0); // Reset pointer
                            $color_index = 0;
                            while ($row = mysqli_fetch_assoc($result_breakdown)) {
                                $kategori = $row['kategori'];
                                $total = $row['total'];

                                // Use the same color as the chart
                                $color = $color_palette[$color_index % count($color_palette)];

                                echo "<div class='breakdown-item'>";
                                echo "<span class='category-name'>{$kategori}</span>";
                                echo "<span class='amount'><span class='badge' style='background-color: {$color}; color: white;'>" . format_rupiah($total) . "</span></span>";
                                echo "</div>";
                                echo "<div class='full-separator'></div>";
                                $color_index++;
                            }
                        } else {
                            echo "<div class='text-center text-muted'>Tidak ada data pengeluaran untuk bulan ini</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Transaksi -->
        <div class="table-container" style="margin-top: 32px;">
            <div class="d-flex justify-content-between align-items-center mb-4" style="padding: 16px 24px 0 24px;">
                <div class="table-title">Detail Transaksi - <?php echo $bulan_nama . ' ' . $tahun; ?></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Deskripsi</th>
                            <th>Kategori</th>
                            <th class="text-end">Pemasukan</th>
                            <th class="text-end">Pengeluaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($transaksi_data) > 0) {
                            foreach ($transaksi_data as $row) {
                                $tanggal = date('d/m/Y', strtotime($row['tanggal']));
                                $deskripsi = $row['deskripsi'];
                                $kategori = $row['kategori'];
                                $pemasukan = $row['pemasukan'];
                                $pengeluaran = $row['pengeluaran'];

                                // Use the same color as the chart for the category
                                $badge_color = isset($category_color_map[$kategori]) ? $category_color_map[$kategori] : '#6c757d'; // Default gray if not found

                                echo "<tr>";
                                echo "<td>{$tanggal}</td>";
                                echo "<td>{$deskripsi}</td>";
                                echo "<td><span class='badge-category' style='background-color: {$badge_color}; color: white;'>{$kategori}</span></td>";
                                echo "<td class='text-end'>" . ($pemasukan > 0 ? format_rupiah($pemasukan) : '-') . "</td>";
                                echo "<td class='text-end text-danger fw-bold'>" . ($pengeluaran > 0 ? format_rupiah($pengeluaran) : '-') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center text-muted'>Tidak ada data transaksi untuk bulan ini</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>


    </div>
    <!-- Chart Script -->
    <script>
        // Pie Chart for Komposisi Pengeluaran
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pie_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Bar Chart for Komposisi Pengeluaran
        const ctxBar = document.getElementById('barChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value * 1000).toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });



        // Chart toggle functionality
        const pieBtn = document.getElementById('pieBtn');
        const barBtn = document.getElementById('barBtn');
        const pieChart = document.getElementById('pieChart');
        const barChart = document.getElementById('barChart');

        pieBtn.addEventListener('change', function() {
            if (this.checked) {
                pieChart.style.display = 'block';
                barChart.style.display = 'none';
            }
        });

        barBtn.addEventListener('change', function() {
            if (this.checked) {
                barChart.style.display = 'block';
                pieChart.style.display = 'none';
            }
        });

        // Function to update filter automatically
        function updateFilter() {
            const bulan = document.getElementById('bulanSelect').value;
            const tahun = document.getElementById('tahunSelect').value;
            window.location.href = `?bulan=${bulan}&tahun=${tahun}`;
        }

        // Handle export Excel button click
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            const bulan = document.getElementById('bulanSelect').value || '<?php echo $bulan; ?>';
            const tahun = document.getElementById('tahunSelect').value || '<?php echo $tahun; ?>';
            const exportUrl = `../backend/laporan/export_laporan_excel_warga.php?bulan=${bulan}&tahun=${tahun}`;
            window.open(exportUrl, '_blank');
        });

        // Handle export PDF button click
        document.getElementById('exportPdfBtn').addEventListener('click', function() {
            const bulan = document.getElementById('bulanSelect').value || '<?php echo $bulan; ?>';
            const tahun = document.getElementById('tahunSelect').value || '<?php echo $tahun; ?>';
            const exportUrl = `../backend/laporan/export_laporan_pdf_warga.php?bulan=${bulan}&tahun=${tahun}`;
            window.open(exportUrl, '_blank');
        });

        // Handle Generate button click
        document.getElementById('filterBtn').addEventListener('click', function() {
            updateFilter();
        });

        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Page initialization complete
        });
    </script>
 < / b o d y > 
 < / h t m l > 
 
 