<?php
session_start();
require_once '../config/koneksi.php';
// Periksa login
if (!isset($_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit();
}
// Array bulan Indonesia
$bulan_indo = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
];

// Periksa login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    header('Location: ../auth/login.php');
    exit();
}

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;

// Query untuk mendapatkan semua tahun yang tersedia dari database
$query_tahun = "
    SELECT DISTINCT tahun FROM iuran_rutin WHERE status_pembayaran = 'Lunas'
    UNION
    SELECT DISTINCT YEAR(tanggal_pengeluaran) as tahun FROM pengeluaran_kegiatan
    ORDER BY tahun DESC
";
$result_tahun = mysqli_query($koneksi, $query_tahun);
$tahun_options = [];
while ($row = mysqli_fetch_assoc($result_tahun)) {
    $tahun_options[] = $row['tahun'];
}

// Jika tidak ada data di database, tambahkan tahun saat ini
if (empty($tahun_options)) {
    $tahun_options[] = date('Y');
}

// Pastikan tahun saat ini selalu tersedia
if (!in_array(date('Y'), $tahun_options)) {
    array_unshift($tahun_options, date('Y'));
}

// Load data if tahun is selected
$total_pemasukan = 0;
$total_pengeluaran = 0;
$saldo_akhir = 0;
$table_data = [];
$bulan_labels = [];
$pemasukan_bulanan = [];
$pengeluaran_bulanan = [];
$saldo_bulanan = [];

if ($tahun !== null) {
    // Query untuk total pemasukan tahunan
    $query_pemasukan = "
        SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
        FROM iuran_rutin
        WHERE tahun = ? AND status_pembayaran = 'Lunas'
    ";
    $stmt_pemasukan = mysqli_prepare($koneksi, $query_pemasukan);
    mysqli_stmt_bind_param($stmt_pemasukan, 'i', $tahun);
    mysqli_stmt_execute($stmt_pemasukan);
    $result_pemasukan = mysqli_stmt_get_result($stmt_pemasukan);
    $total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];
    mysqli_stmt_close($stmt_pemasukan);

    // Query untuk total pengeluaran tahunan
    $query_pengeluaran = "
        SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
        FROM pengeluaran_kegiatan
        WHERE YEAR(tanggal_pengeluaran) = ?
    ";
    $stmt_pengeluaran = mysqli_prepare($koneksi, $query_pengeluaran);
    mysqli_stmt_bind_param($stmt_pengeluaran, 'i', $tahun);
    mysqli_stmt_execute($stmt_pengeluaran);
    $result_pengeluaran = mysqli_stmt_get_result($stmt_pengeluaran);
    $total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];
    mysqli_stmt_close($stmt_pengeluaran);

    // Hitung saldo akhir
    $saldo_akhir = $total_pemasukan - $total_pengeluaran;

    // Query untuk data per bulan
    $query_bulanan = "
        SELECT
            bulan,
            COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END), 0) as pemasukan,
            COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as pengeluaran,
            COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as saldo
        FROM (
            SELECT bulan, jumlah_iuran as jumlah, 'pemasukan' as tipe FROM iuran_rutin WHERE tahun = ? AND status_pembayaran = 'Lunas'
            UNION ALL
            SELECT MONTH(tanggal_pengeluaran) as bulan, jumlah_pengeluaran as jumlah, 'pengeluaran' as tipe FROM pengeluaran_kegiatan WHERE YEAR(tanggal_pengeluaran) = ?
        ) as combined
        GROUP BY bulan
        ORDER BY bulan
    ";
    $stmt_bulanan = mysqli_prepare($koneksi, $query_bulanan);
    mysqli_stmt_bind_param($stmt_bulanan, 'ii', $tahun, $tahun);
    mysqli_stmt_execute($stmt_bulanan);
    $result_bulanan = mysqli_stmt_get_result($stmt_bulanan);

    while ($row = mysqli_fetch_assoc($result_bulanan)) {
        $table_data[] = [
            'bulan' => $bulan_indo[$row['bulan'] - 1],
            'pemasukan' => $row['pemasukan'],
            'pengeluaran' => $row['pengeluaran'],
            'saldo' => $row['saldo']
        ];
        $bulan_labels[] = $bulan_indo[$row['bulan'] - 1];
        $pemasukan_bulanan[] = $row['pemasukan'];
        $pengeluaran_bulanan[] = $row['pengeluaran'];
        $saldo_bulanan[] = $row['saldo'];
    }
    mysqli_stmt_close($stmt_bulanan);
}

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
    <title>Laporan Kas - Tahunan</title>
    
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

        /* Summary Cards */
        .summary-cards {
            margin-bottom: 32px;
        }

        .summary-card {
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .income-card {
            background: linear-gradient(135deg, #e7f5ff 0%, #b3e0ff 100%);
            border-left: 4px solid var(--success-color);
        }

        .expense-card {
            background: linear-gradient(135deg, #ffeaea 0%, #ffb3b3 100%);
            border-left: 4px solid var(--danger-color);
        }

        .balance-card {
            background: linear-gradient(135deg, #fff4e6 0%, #ffd699 100%);
            border-left: 4px solid var(--warning-color);
        }

        .summary-card h6 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .summary-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        /* Chart Container */
        .chart-container-large {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 32px;
            padding: 24px;
        }

        .chart-title {
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

        .chart-title i {
            margin-right: 10px;
            color: black;
        }

        .chart-box-large {
            height: 300px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Month Labels */
        .month-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
            padding: 0 10px;
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

            .chart-box-large {
                height: 250px;
            }

            .table-responsive {
                font-size: 14px;
            }

            .table th, .table td {
                padding: 12px 8px;
                font-size: 12px;
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

        /* METRICS */
        .metric-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top: 20px;}
        .metric-card{background:#fff; border:1px solid #e4e7ec; border-radius:14px; padding:18px; position:relative; min-height:118px; box-shadow:0 2px 6px rgba(0,0,0,0.03)}
        .metric-title{font-size:13px; color:#222}
        .metric-value{font-size:22px; font-weight:700; margin-top:6px}
        .icon-wrapper{position:absolute; top:16px; right:16px; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#E4E8F0}
        .icon-wrapper i,.icon-wrapper span{font-size:22px; color:#0A2A66}
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php 
    $role = $_SESSION['role'] ?? 'guest';
    include 'sidebar.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="laporan-header">
            <div class="header-content">
                <div class="logo-section">
                    <div class="laporan-title">
                        <h1>Laporan Kas Tahunan</h1>
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
                    <a href="laporanbulanan.php" class="nav-link">
                        <i class="bi bi-calendar-month"></i> Laporan Bulanan
                    </a>
                    <a href="laporantahunan.php" class="nav-link active">
                        <i class="bi bi-calendar"></i> Laporan Tahunan
                    </a>
                </nav>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="d-flex gap-3">
                <div class="mb-3 flex-fill">
                    <label class="form-label">Tahun</label>
                    <select class="form-select" id="tahunSelect">
                        <option value="">Pilih Tahun</option>
                        <?php
                        foreach ($tahun_options as $year) {
                            echo "<option value='$year'>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3 d-flex align-items-end">
                    <button class="btn" id="filterBtn" style="background-color: #072f66; color: white; border-color: #072f66;">
                        <i class="bi bi-funnel"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="metric-grid" style="margin-bottom: 32px;">
            <div class="metric-card">
                <div class="metric-title">Total Pemasukan Tahunan</div>
                <div class="metric-value" id="totalPemasukan"><?php echo $tahun !== null ? format_rupiah($total_pemasukan) : 'Rp 0'; ?></div>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Total Pengeluaran Tahunan</div>
                <div class="metric-value" id="totalPengeluaran"><?php echo $tahun !== null ? format_rupiah($total_pengeluaran) : 'Rp 0'; ?></div>
                <div class="icon-wrapper">
                    <i class="bi bi-graph-down"></i>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-title">Saldo Akhir Tahun</div>
                <div class="metric-value" id="saldoAkhir"><?php echo $tahun !== null ? format_rupiah($saldo_akhir) : 'Rp 0'; ?></div>
                <div class="icon-wrapper" id="saldoIcon">
                    <i class="bi bi-<?php echo $tahun !== null && $saldo_akhir >= 0 ? 'graph-up' : 'graph-down'; ?>"></i>
                </div>
            </div>
        </div>

        <!-- Tren Tahunan -->
        <div class="chart-container-large">
            <div class="chart-title">
                <i class="bi bi-graph-up"></i> Tren Tahunan<?php echo $tahun !== null ? ' ' . $tahun : ''; ?>
            </div>
            <div class="chart-box-large">
                <canvas id="trenTahunanChart"></canvas>
            </div>
        </div>

        <!-- Tabel Rincian per Bulan -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="bi bi-table"></i> Rincian per Bulan - <?php echo $tahun !== null ? $tahun : ''; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-end">Pemasukan</th>
                            <th class="text-end">Pengeluaran</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($tahun !== null && !empty($table_data)): ?>
                            <?php foreach ($table_data as $row): ?>
                                <tr>
                                    <td class="fw-medium"><?php echo htmlspecialchars($row['bulan']); ?></td>
                                    <td class="text-end text-success fw-medium"><?php echo format_rupiah($row['pemasukan']); ?></td>
                                    <td class="text-end text-danger fw-medium"><?php echo format_rupiah($row['pengeluaran']); ?></td>
                                    <td class="text-end fw-bold text-warning"><?php echo format_rupiah($row['saldo']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Pilih tahun untuk melihat data</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light" id="tableFooter">
                        <?php if ($tahun !== null): ?>
                            <tr class="border-top border-2">
                                <th class="fw-bold text-primary">Total Tahunan</th>
                                <th class="text-end fw-bold text-success"><?php echo format_rupiah($total_pemasukan); ?></th>
                                <th class="text-end fw-bold text-danger"><?php echo format_rupiah($total_pengeluaran); ?></th>
                                <th class="text-end fw-bold text-warning"><?php echo format_rupiah($saldo_akhir); ?></th>
                            </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart Script -->
    <script>
        // Global variables for charts
        let trenTahunanChart;

        // Data from PHP
        const bulanLabels = <?php echo json_encode($bulan_labels); ?>;
        const pemasukanBulanan = <?php echo json_encode($pemasukan_bulanan); ?>;
        const pengeluaranBulanan = <?php echo json_encode($pengeluaran_bulanan); ?>;
        const saldoBulanan = <?php echo json_encode($saldo_bulanan); ?>;

        // Function to format rupiah
        function formatRupiah(n) {
            const s = n < 0 ? '-' : '';
            return 'Rp ' + s + Math.abs(n).toLocaleString('id-ID');
        }

        // Function to load data from backend
        async function loadLaporanData(tahun) {
            try {
                const response = await fetch(`../backend/laporan/get_laporan_tahunan.php?tahun=${tahun}`);
                const data = await response.json();

                if (response.ok) {
                    updateUI(data, tahun);
                } else {
                    console.error('Error loading data:', data.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        }

        // Function to update UI with data
        function updateUI(data, selectedYear) {
            // Update summary cards
            document.getElementById('totalPemasukan').textContent = formatRupiah(data.total_pemasukan);
            document.getElementById('totalPengeluaran').textContent = formatRupiah(data.total_pengeluaran);
            document.getElementById('saldoAkhir').textContent = formatRupiah(data.saldo_akhir);

            // Update saldo icon
            const saldoIcon = document.getElementById('saldoIcon').querySelector('i');
            saldoIcon.className = data.saldo_akhir >= 0 ? 'bi bi-graph-up' : 'bi bi-graph-down';

            // Update titles with selected year
            document.querySelector('.chart-title').innerHTML = '<i class="bi bi-graph-up"></i> Tren Tahunan ' + selectedYear;
            document.querySelector('.table-title').innerHTML = '<i class="bi bi-table"></i> Rincian per Bulan - ' + selectedYear;

            // Update table
            updateTable(data.table_data, data.total_pemasukan, data.total_pengeluaran, data.saldo_akhir);

            // Update charts
            updateCharts(data.bulan_labels, data.pemasukan_bulanan, data.pengeluaran_bulanan, data.saldo_bulanan);
        }

        // Function to update table
        function updateTable(tableData, totalPemasukan, totalPengeluaran, saldoAkhir) {
            const tableBody = document.getElementById('tableBody');
            const tableFooter = document.getElementById('tableFooter');

            // Clear existing content
            tableBody.innerHTML = '';
            tableFooter.innerHTML = '';

            // Populate table body
            tableData.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-medium">${row.bulan}</td>
                    <td class="text-end text-success fw-medium">${formatRupiah(row.pemasukan)}</td>
                    <td class="text-end text-danger fw-medium">${formatRupiah(row.pengeluaran)}</td>
                    <td class="text-end fw-bold text-warning">${formatRupiah(row.saldo)}</td>
                `;
                tableBody.appendChild(tr);
            });

            // Populate table footer
            const footerTr = document.createElement('tr');
            footerTr.className = 'border-top border-2';
            footerTr.innerHTML = `
                <th class="fw-bold text-primary">Total Tahunan</th>
                <th class="text-end fw-bold text-success">${formatRupiah(totalPemasukan)}</th>
                <th class="text-end fw-bold text-danger">${formatRupiah(totalPengeluaran)}</th>
                <th class="text-end fw-bold text-warning">${formatRupiah(saldoAkhir)}</th>
            `;
            tableFooter.appendChild(footerTr);
        }

        // Function to update charts
        function updateCharts(bulanLabels, pemasukanBulanan, pengeluaranBulanan, saldoBulanan) {
            // Destroy existing charts if they exist
            if (trenTahunanChart) {
                trenTahunanChart.destroy();
            }

            // Create Tren Tahunan Chart
            const ctxTren = document.getElementById('trenTahunanChart').getContext('2d');
            trenTahunanChart = new Chart(ctxTren, {
                type: 'line',
                data: {
                    labels: bulanLabels,
                    datasets: [
                        {
                            label: 'Pemasukan',
                            data: pemasukanBulanan.map(v => v / 1000),
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Pengeluaran',
                            data: pengeluaranBulanan.map(v => v / 1000),
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Saldo',
                            data: saldoBulanan.map(v => v / 1000),
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });

        }

        // Initialize chart on page load if data exists
        document.addEventListener('DOMContentLoaded', function() {
            if (bulanLabels.length > 0) {
                updateCharts(bulanLabels, pemasukanBulanan, pengeluaranBulanan);
            }
        });

        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tahunSelect = document.getElementById('tahunSelect');
            const filterBtn = document.getElementById('filterBtn');
            const exportExcelBtn = document.getElementById('exportExcelBtn');

            // Check if year is provided in URL
            const urlParams = new URLSearchParams(window.location.search);
            const urlTahun = urlParams.get('tahun');

            // If year is provided in URL, set it and load data
            if (urlTahun) {
                tahunSelect.value = urlTahun;
                loadLaporanData(urlTahun);
            }

            // Handle filter button click
            filterBtn.addEventListener('click', function() {
                const selectedTahun = tahunSelect.value;
                if (selectedTahun) {
                    const currentUrl = new URL(window.location);
                    currentUrl.searchParams.set('tahun', selectedTahun);
                    window.location.href = currentUrl.toString();
                }
            });

            // Handle select change
            tahunSelect.addEventListener('change', function() {
                const selectedTahun = tahunSelect.value;
                if (selectedTahun) {
                    const currentUrl = new URL(window.location);
                    currentUrl.searchParams.set('tahun', selectedTahun);
                    window.location.href = currentUrl.toString();
                }
            });

            // Handle export Excel button click
            exportExcelBtn.addEventListener('click', function() {
                const selectedTahun = tahunSelect.value;
                if (selectedTahun) {
                    const exportUrl = `../backend/laporan/export_excel_tahunan.php?tahun=${selectedTahun}`;
                    window.open(exportUrl, '_blank');
                }
            });

            // Handle export PDF button click
            const exportPdfBtn = document.getElementById('exportPdfBtn');
            exportPdfBtn.addEventListener('click', function() {
                const selectedTahun = tahunSelect.value;
                if (selectedTahun) {
                    const exportUrl = `../backend/laporan/export_pdf_tahunan.php?tahun=${selectedTahun}`;
                    window.open(exportUrl, '_blank');
                }
            });
        });
    </script>
</body>
</html>