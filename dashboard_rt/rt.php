<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    header("Location: ../auth/login.php");
    exit();
}

// Fungsi format rupiah
function format_rupiah($n) {
    $s = $n < 0 ? '-' : '';
    return 'Rp ' . $s . number_format(abs($n), 0, ',', '.');
}

// Fungsi untuk menjalankan query dengan error handling
function execute_query($query, $error_msg = "Database error") {
    global $koneksi;
    $result = mysqli_query($koneksi, $query);
    if (!$result) {
        die($error_msg . ": " . mysqli_error($koneksi));
    }
    return $result;
}

// Array bulan Indonesia
$bulan_indo = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
];

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Auto-update overdue iuran status
$update_overdue_query = "
    UPDATE iuran_rutin
    SET status_pembayaran = 'Belum Lunas'
    WHERE status_pembayaran = 'Lunas'
    AND CONCAT(tahun, '-', LPAD(bulan, 2, '0'), '-01') < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    AND (tanggal_pembayaran IS NULL OR tanggal_pembayaran < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
";
execute_query($update_overdue_query, "Error updating overdue status");

// Data dashboard
$dashboard_data = [];

// Total warga aktif
$result = execute_query("SELECT COUNT(*) as total FROM warga", "Error getting total warga");
$dashboard_data['total_warga'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Data iuran bulan ini
$result = execute_query("
    SELECT
        SUM(CASE WHEN status_pembayaran = 'Lunas' THEN jumlah_iuran ELSE 0 END) as lunas,
        SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN jumlah_iuran ELSE 0 END) as belum_lunas,
        COUNT(*) as total_iuran,
        SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as jumlah_lunas
    FROM iuran_rutin
", "Error getting iuran data");
$iuran_data = mysqli_fetch_assoc($result) ?? [];
$dashboard_data['iuran_lunas'] = $iuran_data['jumlah_lunas'] . "/" . $iuran_data['total_iuran'];
$dashboard_data['iuran_belum_lunas'] = $iuran_data['belum_lunas'] ?? 0;

// Get detailed overdue information
$result = execute_query("
    SELECT COUNT(*) as jumlah_belum_lunas,
           SUM(jumlah_iuran) as total_belum_lunas,
           GROUP_CONCAT(DISTINCT w.nama_lengkap SEPARATOR ', ') as nama_warga_belum_lunas
    FROM iuran_rutin i
    LEFT JOIN warga w ON i.id_warga = w.id_warga
    WHERE i.status_pembayaran = 'Belum Lunas'
    AND CONCAT(i.tahun, '-', LPAD(i.bulan, 2, '0'), '-01') < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
", "Error getting overdue details");
$overdue_data = mysqli_fetch_assoc($result) ?? [];
$dashboard_data['jumlah_belum_lunas'] = $overdue_data['jumlah_belum_lunas'] ?? 0;
$dashboard_data['total_belum_lunas'] = $overdue_data['total_belum_lunas'] ?? 0;
$dashboard_data['nama_warga_belum_lunas'] = $overdue_data['nama_warga_belum_lunas'] ?? '';

// Saldo kas total
$result = execute_query("
    SELECT
        (SELECT COALESCE(SUM(jumlah_iuran), 0) FROM iuran_rutin WHERE status_pembayaran = 'Lunas') -
        (SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM pengeluaran_kegiatan) as saldo
", "Error calculating saldo");
$dashboard_data['saldo_kas'] = mysqli_fetch_assoc($result)['saldo'] ?? 0;

// Pengeluaran terakhir
$result = execute_query("
    SELECT jumlah_pengeluaran as total
    FROM pengeluaran_kegiatan
    ORDER BY tanggal_pengeluaran DESC
    LIMIT 1
", "Error getting latest pengeluaran");
$dashboard_data['pengeluaran_terakhir'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Data tahunan
$result = execute_query("
    SELECT
        (SELECT COALESCE(SUM(jumlah_iuran), 0) FROM iuran_rutin WHERE tahun = '$current_year' AND status_pembayaran = 'Lunas') as pemasukan_tahun,
        (SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM pengeluaran_kegiatan WHERE YEAR(tanggal_pengeluaran) = '$current_year') as pengeluaran_tahun
", "Error getting yearly data");
$data_tahunan = mysqli_fetch_assoc($result);
$dashboard_data['pemasukan_tahun'] = $data_tahunan['pemasukan_tahun'] ?? 0;
$dashboard_data['pengeluaran_tahun'] = $data_tahunan['pengeluaran_tahun'] ?? 0;

// Load data for current year
$table_data = [];
$bulan_labels = [];
$pemasukan_bulanan = [];
$pengeluaran_bulanan = [];
$saldo_bulanan = [];

// Query untuk data per bulan tahun ini
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
mysqli_stmt_bind_param($stmt_bulanan, 'ii', $current_year, $current_year);
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

// Breakdown per RT dari database (all months)
$rt_breakdown = [];
$result = execute_query("
    SELECT
        u.rt_number as nama_rt,
        COUNT(DISTINCT w.id_warga) as total_warga,
        SUM(CASE WHEN i.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN i.status_pembayaran = 'Belum Lunas' THEN 1 ELSE 0 END) as belum_lunas,
        GROUP_CONCAT(DISTINCT CASE WHEN i.status_pembayaran = 'Lunas' THEN w.nama_lengkap ELSE NULL END SEPARATOR ', ') as nama_lunas,
        GROUP_CONCAT(DISTINCT CASE WHEN i.status_pembayaran = 'Belum Lunas' THEN w.nama_lengkap ELSE NULL END SEPARATOR ', ') as nama_belum_lunas
    FROM user u
    LEFT JOIN warga w ON u.id_warga = w.id_warga
    LEFT JOIN iuran_rutin i ON w.id_warga = i.id_warga
    WHERE u.role = 'warga'
    GROUP BY u.rt_number
    ORDER BY u.rt_number
", "Error getting RT breakdown");
while ($row = mysqli_fetch_assoc($result)) {
    $rt_breakdown[] = $row;
}

// Hitung persentase perubahan saldo (vs bulan lalu)
$last_month = date('m', strtotime('-1 month'));
$last_year = date('Y', strtotime('-1 month'));
$result = execute_query("
    SELECT
        (SELECT COALESCE(SUM(jumlah_iuran), 0) FROM iuran_rutin WHERE status_pembayaran = 'Lunas' AND bulan = '$last_month' AND tahun = '$last_year') -
        (SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM pengeluaran_kegiatan WHERE MONTH(tanggal_pengeluaran) = '$last_month' AND YEAR(tanggal_pengeluaran) = '$last_year') as saldo_bulan_lalu
", "Error calculating last month saldo");
$saldo_bulan_lalu = mysqli_fetch_assoc($result)['saldo_bulan_lalu'] ?? 0;
$dashboard_data['persentase_perubahan'] = $saldo_bulan_lalu != 0 ?
    round((($dashboard_data['saldo_kas'] - $saldo_bulan_lalu) / abs($saldo_bulan_lalu)) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

    <style>
    :root{
        --sidebar-bg:#072f66;
        --sidebar-accent:#0b3f77;
        --card-border:#e4e7ec;
        --muted:#8d96a5;
        --page-bg:#f2f6fb;
        --primary-blue:#1e40af;
        --success-green:#10b981;
        --warning-orange:#f59e0b;
        --danger-red:#ef4444;
    }
    *{box-sizing:border-box}
    body{
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        background:var(--page-bg);
        margin:0;
        -webkit-font-smoothing:antialiased;
        color:#1e293b;
    }

    /* Main content adjustment untuk sidebar yang sudah ada */
    .main-content {
        margin-left: 0;
        margin-top: 60px;
        padding: 28px;
        min-height: calc(100vh - 60px);
        transition: margin-left 0.3s ease;
    }

    /* Page Header */
    .page-header {
        margin-bottom: 30px;
        padding-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title h1 {
        color: #1e293b;
        font-weight: 700;
        font-size: 24px;
        margin-bottom: 4px;
    }

    .page-subtitle {
        color: #64748b;
        font-size: 0.95rem;
        font-weight: 400;
    }

    /* TOP METRICS GRID */
    .top-metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .metric-card-dashboard {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .metric-card-dashboard:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .metric-title-dash {
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .metric-value-dash {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
    }

    .metric-change-dash {
        font-size: 13px;
        font-weight: 500;
        color: #10b981;
    }

    .metric-change-dash.negative {
        color: #ef4444;
    }

    .metric-badge-dash {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
    }

    .badge-success-dash {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning-dash {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger-dash {
        background: #fee2e2;
        color: #991b1b;
    }

    /* CHART SECTION - Disesuaikan dengan gambar pertama (layout lebar) */
    .chart-full-width {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        margin-bottom: 24px;
    }

    .chart-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .chart-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .chart-subtitle {
        font-size: 14px;
        color: #64748b;
        margin-top: 4px;
    }

    .chart-body-full {
        padding: 24px;
        display: flex;
        flex-direction: column;
    }

    .chart-container-wide {
        height: 300px; /* Increased height for wider chart display */
        width: 100%;
        position: relative;
    }

    /* TWO COLUMN LAYOUT UNTUK BREAKDOWN DAN RINGKASAN */
    .secondary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    /* BREAKDOWN SECTION - Disesuaikan dengan gambar kedua */
    .breakdown-section {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
    }

    .breakdown-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .breakdown-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .breakdown-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        padding: 24px;
    }

    .rt-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
    }

    .rt-title-link {
        text-decoration: none;
        color: #1e293b;
    }

    .rt-title-link:hover {
        text-decoration: underline;
    }

    .rt-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }

    .stat-value-link {
        text-decoration: none;
        color: inherit;
    }

    .stat-value-link:hover {
        text-decoration: underline;
    }

    .rt-stats {
        display: flex;
        justify-content: space-between;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }

    .stat-item {
        text-align: center;
    }

    .stat-label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
    }

    .stat-value.success {
        color: #10b981;
    }

    .stat-value.warning {
        color: #f59e0b;
    }

    .stat-names {
        margin-top: 4px;
        font-size: 11px;
        line-height: 1.3;
        max-height: 40px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .success-names {
        color: #065f46;
    }

    .warning-names {
        color: #92400e;
    }

    /* RINGKASAN KEUANGAN SECTION */
    .summary-section {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
    }

    .summary-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-label {
        font-size: 14px;
        color: #64748b;
    }

    .summary-value {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
    }

    .summary-value.positive {
        color: #10b981;
    }

    .summary-value.negative {
        color: #ef4444;
    }

    /* ACTIONS SECTION - Disesuaikan dengan gambar ketiga (paling bawah) */
    .actions-bottom-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .action-bottom-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        text-decoration: none;
        color: #1e293b;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .action-bottom-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: #1e293b;
    }

    .action-bottom-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 16px;
    }

    .icon-iuran {
        background: #dbeafe;
        color: #1e40af;
    }

    .icon-pengeluaran {
        background: #dcfce7;
        color: #166534;
    }

    .icon-warga {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .action-bottom-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .action-bottom-desc {
        font-size: 14px;
        color: #64748b;
        line-height: 1.5;
    }

    /* ALERT SECTION - Disesuaikan dengan gambar ketiga */
    .alert-bottom-section {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .alert-bottom-content {
        flex: 1;
    }

    .alert-bottom-title {
        font-size: 16px;
        font-weight: 700;
        color: #92400e;
        margin-bottom: 4px;
    }

    .alert-bottom-text {
        font-size: 14px;
        color: #92400e;
        margin: 0;
    }

    .alert-bottom-btn {
        background: #92400e;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }

    .alert-bottom-btn:hover {
        background: #78350f;
        color: white;
        text-decoration: none;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .top-metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .secondary-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-bottom-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 16px;
        }
        
        .top-metrics-grid {
            grid-template-columns: 1fr;
        }
        
        .breakdown-grid {
            grid-template-columns: 1fr;
        }
        
        .actions-bottom-grid {
            grid-template-columns: 1fr;
        }
        
        .alert-bottom-section {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
        }
    }

    /* Icon Styles untuk metrics */
    .metric-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .icon-money {
        background: #dbeafe;
        color: #1e40af;
    }

    .icon-people {
        background: #dcfce7;
        color: #166534;
    }

    .icon-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .icon-report {
        background: #f3e8ff;
        color: #7c3aed;
    }

    /* Button Styles */
    .btn-view-report {
        background: #1e40af;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
        display: inline-block;
        text-decoration: none;
    }

    .btn-view-report:hover {
        background: #1e3a8a;
        color: white;
        text-decoration: none;
    }

    /* Chart Legend */
    .chart-legend-wide {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-top: 16px;
    }

    .legend-item-wide {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .legend-color-wide {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }

    .legend-text-wide {
        font-size: 12px;
        font-weight: 600;
        color: #1e293b;
    }

    .color-saldo {
        background: #3b82f6;
    }
    </style>
</head>
<body>

<!-- Include Sidebar (yang sudah ada) -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>Dashboard Ketua RT</h1>
            <div class="page-subtitle">Monitoring keuangan dan aktivitas RT/RW</div>
        </div>
        <a href="laporanbulanan.php" class="btn-view-report">Lihat Laporan Lengkap</a>
    </div>

    <!-- TOP METRICS -->
    <div class="top-metrics-grid">
        <!-- Saldo Total RT -->
        <div class="metric-card-dashboard">
            <div class="metric-title-dash">Saldo Total RT</div>
            <div class="metric-value-dash"><?= format_rupiah($dashboard_data['saldo_kas']) ?></div>
            <div class="metric-change-dash<?= $dashboard_data['persentase_perubahan'] < 0 ? ' negative' : '' ?>">
                <?= ($dashboard_data['persentase_perubahan'] >= 0 ? '+' : '') . $dashboard_data['persentase_perubahan'] ?>% vs bulan lalu
            </div>
            <div class="metric-icon icon-money">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>

        <!-- Total Warga Aktif -->
        <div class="metric-card-dashboard">
            <div class="metric-title-dash">Total Warga Aktif</div>
            <div class="metric-value-dash"><?= $dashboard_data['total_warga'] ?></div>
            <div class="metric-badge-dash badge-success-dash">Aktif</div>
            <div class="metric-icon icon-people">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>

        <!-- Iuran Belum Lunas -->
        <div class="metric-card-dashboard">
            <div class="metric-title-dash">Iuran Belum Lunas</div>
            <div class="metric-value-dash"><?= format_rupiah($dashboard_data['iuran_belum_lunas']) ?></div>
            <div class="metric-badge-dash badge-warning-dash">Perlu Tindakan</div>
            <div class="metric-icon icon-warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
        </div>

        <!-- Pengeluaran Terakhir -->
        <div class="metric-card-dashboard">
            <div class="metric-title-dash">Pengeluaran Terakhir</div>
            <div class="metric-value-dash"><?= format_rupiah($dashboard_data['pengeluaran_terakhir']) ?></div>
            <div class="metric-icon icon-report">
                <i class="bi bi-file-text"></i>
            </div>
        </div>
    </div>

    <!-- CHART TREN SALDO TAHUNAN - Sesuai gambar pertama -->
    <div class="chart-full-width">
        <div class="chart-header">
            <div class="chart-title">Tren Saldo Tahunan <?php echo $current_year; ?></div>
            <div class="chart-subtitle">Perkembangan saldo dari bulan ke bulan</div>
        </div>
        <div class="chart-body-full">
            <div class="chart-container-wide">
                <canvas id="trenTahunanChart"></canvas>
            </div>
            <div class="chart-legend-wide">
                <div class="legend-item-wide">
                    <div class="legend-color-wide color-saldo"></div>
                    <div class="legend-text-wide"><?php echo date('M'); ?> - Saldo : <?php echo format_rupiah($dashboard_data['saldo_kas']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECONDARY GRID: BREAKDOWN DAN RINGKASAN - Sesuai gambar kedua -->
    <div class="secondary-grid">
        <!-- Breakdown per RT -->
        <div class="breakdown-section">
            <div class="breakdown-header">
                <div class="breakdown-title">Breakdown per RT</div>
            </div>
            <div class="breakdown-grid">
                <?php foreach($rt_breakdown as $rt_data) { ?>
                <div class="rt-card">
                    <a href="iuranrt.php?rt=<?= urlencode($rt_data['nama_rt']) ?>" class="rt-title-link">
                        <div class="rt-title"><?= $rt_data['nama_rt'] ?></div>
                    </a>
                    <div class="rt-stats">
                        <div class="stat-item">
                            <div class="stat-label">Iuran Lunas (Semua Bulan)</div>
                            <a href="iuranrt.php?rt=<?= urlencode($rt_data['nama_rt']) ?>&status=Lunas" class="stat-value-link">
                                <div class="stat-value success"><?= $rt_data['lunas'] ?> warga</div>
                            </a>
                            <?php if (!empty($rt_data['nama_lunas'])): ?>
                            <div class="stat-names success-names">
                                <small><?= htmlspecialchars($rt_data['nama_lunas']) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Belum Lunas</div>
                            <a href="iuranrt.php?rt=<?= urlencode($rt_data['nama_rt']) ?>&status=Belum%20Lunas" class="stat-value-link">
                                <div class="stat-value warning"><?= $rt_data['belum_lunas'] ?> warga</div>
                            </a>
                            <?php if (!empty($rt_data['nama_belum_lunas'])): ?>
                            <div class="stat-names warning-names">
                                <small><?= htmlspecialchars($rt_data['nama_belum_lunas']) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Ringkasan Keuangan -->
        <div class="summary-section">
            <div class="summary-title">Ringkasan Keuangan</div>
            <div class="summary-item">
                <div class="summary-label">Total Pemasukan <?php echo $current_year; ?></div>
                <div class="summary-value positive"><?php echo format_rupiah($dashboard_data['pemasukan_tahun']); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Pengeluaran <?php echo $current_year; ?></div>
                <div class="summary-value negative"><?php echo format_rupiah($dashboard_data['pengeluaran_tahun']); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Saldo Kas Saat Ini</div>
                <div class="summary-value<?php echo $dashboard_data['saldo_kas'] < 0 ? ' negative' : ' positive'; ?>"><?php echo format_rupiah($dashboard_data['saldo_kas']); ?></div>
            </div>
        </div>
    </div>

    <!-- ACTIONS GRID - Sesuai gambar ketiga (paling bawah) -->
    <div class="actions-bottom-grid">
        <a href="iuranrt.php" class="action-bottom-card">
            <div class="action-bottom-icon icon-iuran">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="action-bottom-title">Iuran</div>
            <div class="action-bottom-desc">Lihat pembayaran</div>
        </a>
        
        <a href="pengeluaranrt.php" class="action-bottom-card">
            <div class="action-bottom-icon icon-pengeluaran">
                <i class="bi bi-bar-chart"></i>
            </div>
            <div class="action-bottom-title">Lihat Pengeluaran</div>
            <div class="action-bottom-desc">Monitoring & Approval</div>
        </a>
        
        <a href="warga.php" class="action-bottom-card">
            <div class="action-bottom-icon icon-warga">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="action-bottom-title">Manage Warga</div>
            <div class="action-bottom-desc">Data Warga RT/RW</div>
        </a>
    </div>

    <!-- ALERT IURAN belum lunas - Sesuai gambar ketiga (paling bawah) -->
    <div class="alert-bottom-section">
        <div class="alert-bottom-content">
            <div class="alert-bottom-title">Perhatian: Iuran Belum Lunas</div>
            <p class="alert-bottom-text">
                Terdapat iuran belum lunas senilai <?php echo format_rupiah($dashboard_data['iuran_belum_lunas']); ?> dari beberapa warga.
                Segera lakukan follow up untuk kelancaran keuangan RT.
            </p>
        </div>
        <a href="iuranrt.php?status=Belum%20Lunas" class="alert-bottom-btn">Lihat Detail</a>
    </div>

</div>

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
        updateCharts(bulanLabels, pemasukanBulanan, pengeluaranBulanan, saldoBulanan);
    }
});
</script>

</body>
</html>