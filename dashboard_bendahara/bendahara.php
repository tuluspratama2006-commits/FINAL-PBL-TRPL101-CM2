<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    header("Location: ../auth/login.php");
    exit();
}
// Array bulan Indonesia
$bulan_indo = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
];

// Fungsi format rupiah
function format_rupiah($n){
    $s = $n < 0 ? '-' : '';
    return 'Rp '.$s.number_format(abs($n),0,',','.');
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Query untuk total warga
$query_total_warga = "SELECT COUNT(*) as total FROM warga";
$result_total_warga = mysqli_query($koneksi, $query_total_warga);
$total_warga = mysqli_fetch_assoc($result_total_warga)['total'];

// Query untuk iuran bulan ini
$query_iuran_bulan_ini = "
    SELECT
        SUM(CASE WHEN status_pembayaran = 'Lunas' THEN jumlah_iuran ELSE 0 END) as lunas,
        SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN jumlah_iuran ELSE 0 END) as tertunggak,
        COUNT(*) as total_iuran,
        SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as jumlah_lunas
    FROM iuran_rutin
    WHERE bulan = '$current_month' AND tahun = '$current_year'
";
$result_iuran = mysqli_query($koneksi, $query_iuran_bulan_ini);
$iuran_data = mysqli_fetch_assoc($result_iuran);

$iuran_lunas = $iuran_data['jumlah_lunas'] . "/" . $iuran_data['total_iuran'];
$iuran_tertunggak = $iuran_data['tertunggak'] ?? 0;

// Query untuk pemasukan bulan ini (iuran yang lunas)
$pemasukan_bulan_ini = $iuran_data['lunas'] ?? 0;

// Query untuk pengeluaran bulan ini
$query_pengeluaran_bulan_ini = "
    SELECT SUM(jumlah_pengeluaran) as total
    FROM pengeluaran_kegiatan
    WHERE MONTH(tanggal_pengeluaran) = '$current_month'
    AND YEAR(tanggal_pengeluaran) = '$current_year'
";
$result_pengeluaran = mysqli_query($koneksi, $query_pengeluaran_bulan_ini);
$pengeluaran_bulan_ini = mysqli_fetch_assoc($result_pengeluaran)['total'] ?? 0;

// Query untuk saldo kas (total pemasukan - total pengeluaran)
$query_saldo = "
    SELECT
        (SELECT COALESCE(SUM(jumlah_iuran), 0) FROM iuran_rutin WHERE status_pembayaran = 'Lunas') -
        (SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM pengeluaran_kegiatan) as saldo
";
$result_saldo = mysqli_query($koneksi, $query_saldo);
$saldo_kas = mysqli_fetch_assoc($result_saldo)['saldo'] ?? 0;

// Hitung persentase perubahan (dummy untuk sekarang, bisa dihitung dari bulan sebelumnya)
$persentase_perubahan = 12.5;

// Query untuk data grafik (12 bulan terakhir)
$labels = [];
$pemasukan = [];
$pengeluaran = [];
$saldo = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));

    $labels[] = $month_name;

    // Pemasukan per bulan
    $query_pemasukan_bulanan = "
        SELECT COALESCE(SUM(jumlah_iuran), 0) as total
        FROM iuran_rutin
        WHERE bulan = '$month' AND tahun = '$year' AND status_pembayaran = 'Lunas'
    ";
    $result_pemasukan = mysqli_query($koneksi, $query_pemasukan_bulanan);
    $pemasukan_val = mysqli_fetch_assoc($result_pemasukan)['total'];
    $pemasukan[] = $pemasukan_val;

    // Pengeluaran per bulan
    $query_pengeluaran_bulanan = "
        SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
        FROM pengeluaran_kegiatan
        WHERE MONTH(tanggal_pengeluaran) = '$month' AND YEAR(tanggal_pengeluaran) = '$year'
    ";
    $result_pengeluaran = mysqli_query($koneksi, $query_pengeluaran_bulanan);
    $pengeluaran_val = mysqli_fetch_assoc($result_pengeluaran)['total'];
    $pengeluaran[] = $pengeluaran_val;

    // Saldo per bulan (kumulatif)
    $saldo_val = $pemasukan_val - $pengeluaran_val;
    $saldo[] = $saldo_val;
}

// Query untuk 10 transaksi terakhir
$query_transaksi_terakhir = "
    SELECT
        i.tanggal_pembayaran as tanggal,
        w.nama_lengkap as nama,
        CONCAT(b.bulan_nama, ' ', i.tahun) as bulan,
        i.jumlah_iuran as jumlah,
        i.status_pembayaran as status
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
    WHERE i.tanggal_pembayaran IS NOT NULL
    ORDER BY i.tanggal_pembayaran DESC
    LIMIT 10
";

$result_transaksi = mysqli_query($koneksi, $query_transaksi_terakhir);
$transaksi_terakhir = [];

while ($row = mysqli_fetch_assoc($result_transaksi)) {
    $transaksi_terakhir[] = $row;
}

$check_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
$result_monitoring = mysqli_query($koneksi, $check_monitoring);

if(mysqli_num_rows($result_monitoring) == 0) {
    // Hitung saldo awal
    $query_saldo_awal = "
        SELECT 
            (SELECT COALESCE(SUM(jumlah_iuran), 0) FROM iuran_rutin WHERE status_pembayaran = 'Lunas') -
            (SELECT COALESCE(SUM(jumlah_pengeluaran), 0) FROM pengeluaran_kegiatan) as saldo
    ";
    $result_saldo_awal = mysqli_query($koneksi, $query_saldo_awal);
    $saldo_awal = mysqli_fetch_assoc($result_saldo_awal)['saldo'];
    
    // Buat laporan bulan ini jika belum ada
    $bulan_ini = date('m');
    $tahun_ini = date('Y');
    $periode_ini = $bulan_ini . '-' . $tahun_ini;
    
    $check_laporan = "SELECT * FROM laporan_kas WHERE Periode = '$periode_ini' AND Jenis_laporan = 'Bulanan'";
    $result_laporan = mysqli_query($koneksi, $check_laporan);
    
    if(mysqli_num_rows($result_laporan) == 0) {
        // Hitung total untuk laporan
        $total_pemasukan = 0;
        $total_pengeluaran = 0;
        
        // Hitung pemasukan bulan ini
        $query_pemasukan = "SELECT COALESCE(SUM(jumlah_iuran), 0) as total 
                           FROM iuran_rutin 
                           WHERE bulan = $bulan_ini AND tahun = $tahun_ini 
                           AND status_pembayaran = 'Lunas'";
        $result_pemasukan = mysqli_query($koneksi, $query_pemasukan);
        if($row = mysqli_fetch_assoc($result_pemasukan)) {
            $total_pemasukan = $row['total'];
        }
        
        // Hitung pengeluaran bulan ini
        $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total 
                             FROM pengeluaran_kegiatan 
                             WHERE MONTH(tanggal_pengeluaran) = $bulan_ini 
                             AND YEAR(tanggal_pengeluaran) = $tahun_ini";
        $result_pengeluaran = mysqli_query($koneksi, $query_pengeluaran);
        if($row = mysqli_fetch_assoc($result_pengeluaran)) {
            $total_pengeluaran = $row['total'];
        }
        
        $saldo_laporan = $total_pemasukan - $total_pengeluaran;
        
        // Insert laporan
        $insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir) 
                          VALUES ('Bulanan', '$periode_ini', $total_pemasukan, $total_pengeluaran, $saldo_laporan)";
        mysqli_query($koneksi, $insert_laporan);
        $id_laporan = mysqli_insert_id($koneksi);
    } else {
        $row_laporan = mysqli_fetch_assoc($result_laporan);
        $id_laporan = $row_laporan['Id_laporan'];
        $saldo_awal = $row_laporan['Saldo_akhir'];
    }
    
    // Insert monitoring
    $insert_monitoring = "INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring, created_at) 
                         VALUES ($id_laporan, $saldo_awal, CURDATE(), NOW())";
    mysqli_query($koneksi, $insert_monitoring);
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara</title>
    
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
    }
    *{box-sizing:border-box}
    body{
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
        background:var(--page-bg);
        margin:0;
        -webkit-font-smoothing:antialiased;
    }

    /* Main content adjustment untuk sidebar yang sudah ada */
    .main-content {
        margin-left: 0; /* Default: sidebar hidden */
        margin-top: 60px; /* Space untuk top bar */
        padding: 28px;
        min-height: calc(100vh - 60px);
        transition: margin-left 0.3s ease;
    }

    /* Page Header */
    .page-header {
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--gray-lighter);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title h1 {
        color: #1a1a1a;
        font-weight: 600;
        font-size: 28px;
        margin-bottom: 5px;
    }

    .page-subtitle {
        color: #666;
        font-size: 1.1rem;
        font-weight: 400;
    }

    /* ACTIONS */
    .actions{display:flex; justify-content:flex-end; gap:12px}
    .btn-primary.custom{background:var(--sidebar-bg); border:none}
    .quick-actions-container{
        background:#072f66;
        border-radius:14px;
        padding:22px;
    }
    .qa-card{
        display:block;
        background:white;
        padding:16px 18px;
        border-radius:14px;
        text-decoration:none;
        border:1px solid #e5e7eb;
        box-shadow:0 2px 5px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .qa-card:hover{
        transform: translateY(-2px);
        box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }
    .qa-card:active{
        transform: translateY(0);
        box-shadow:0 2px 5px rgba(0,0,0,0.08);
    }
    .qa-title{
        font-size:16px;
        font-weight:700;
        color:#000;
    }
    .qa-sub{
        font-size:14px;
        color:#6b7280;
        margin-top:4px;
    }

    /* METRICS */
    .metric-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top: 20px;}
    .metric-card{background:#fff; border:1px solid var(--card-border); border-radius:14px; padding:18px; position:relative; min-height:118px; box-shadow:0 2px 6px rgba(0,0,0,0.03)}
    .metric-title{font-size:13px; color:#222}
    .metric-value{font-size:22px; font-weight:700; margin-top:6px}
    .metric-badge{display:inline-block; margin-top:10px; padding:6px 10px; border-radius:10px; font-weight:600; font-size:13px; background:#d9fbe7; color:#0b7a3a}
    .icon-wrapper{position:absolute; top:16px; right:16px; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#E4E8F0}
    .icon-wrapper i,.icon-wrapper span{font-size:22px; color:#0A2A66}

    /* CHART CARD */
    .chart-card-custom{
        background: #ffffff;
        padding: 24px 26px;
        border-radius: 16px;
        border: 1px solid #e6e9ee;
        box-shadow: 0px 3px 18px rgba(0, 0, 0, 0.06);
        margin-top: 10px;
    }
    .chart-title{
        font-weight: 700;
        font-size: 16px;
        margin-bottom: 18px;
        color: #222;
    }

    /* Make chart container wider */
    .chart-container-large {
        margin-left: -28px;
        margin-right: -28px;
        width: calc(100% + 56px);
        padding-left: 28px;
        padding-right: 28px;
    }

    /* Table and quick actions */
    .row .card-table{background: #fff; border:1px solid var(--card-border); border-radius:14px; padding:20px}
    
    /* table tweaks */
    .table thead th{border-bottom:0; font-weight:700}
    .badge-success{background:#d1f2df; color:#0b7a3a}

    /* responsive */
    @media(max-width:992px){
        .metric-grid{grid-template-columns:repeat(2,1fr)}
    }
    @media(max-width:768px){
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        .metric-grid{grid-template-columns:1fr}
    }

    /* make the big chart area visually similar to screenshot */
    .big-chart-outer{
        background: #f8fbfd;
        border-radius: 12px;
        padding: 18px;
        border: 1px solid #eef3f8;
    }

    /* center legend above canvas like in screenshot */
    .chart-legend-center{
        display:flex;
        justify-content:center;
        margin-bottom:6px;
        gap:18px;
        align-items:center;
    }
    .legend-pill{
        display:flex; align-items:center; gap:8px;
        padding:6px 12px; border-radius:8px; font-weight:600;
        background: rgba(255,255,255,0.9);
    }
    .legend-pill .swatch{width:22px;height:14px;border-radius:4px; display:inline-block; box-shadow: inset 0 0 0 3px rgba(0,0,0,0.03);}

    /* TOPBAR */
    .topbar{
        display:flex;
        align-items:center;
        gap:16px;
        padding:12px;
        background:#fff;
        border:1px solid var(--card-border);
        border-radius:12px;
        margin-bottom: 20px;
    }
    .topbar .brand{
        background:var(--sidebar-bg);
        color:#fff;
        padding:8px 14px;
        border-radius:8px;
        font-weight:700;
        font-size: 14px;
    }
    .topbar .search{width:350px}
    .topbar .user{display:flex; align-items:center; gap:12px}

    /* Chart container fixes */
    #chartLine {
        max-width: 100% !important;
        height: 400px !important;
        width: 100% !important;
    }

    /* Modern Chart Styles */
    .chart-container-modern {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 20px;
        border: 1px solid #e9ecef;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-top: 20px;
    }

    .chart-header-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px 28px;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-bottom: 1px solid #e9ecef;
    }

    .chart-title-section {
        display: flex;
        align-items: center;
    }

    .chart-title-modern {
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
        margin-bottom: 4px;
    }

    .chart-subtitle-modern {
        font-size: 14px;
        color: #718096;
        margin: 0;
        font-weight: 500;
    }

    .chart-stats {
        display: flex;
        gap: 24px;
    }

    .stat-item {
        text-align: right;
    }

    .stat-label {
        font-size: 12px;
        color: #a0aec0;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 700;
        display: block;
    }

    .chart-body-modern {
        padding: 28px;
        background: white;
    }

    .chart-canvas-wrapper {
        position: relative;
        height: 650px;
        width: 100%;
    }

    .chart-legend-modern {
        display: flex;
        justify-content: center;
        gap: 40px;
        padding: 20px 28px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .legend-item-modern {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .legend-color-modern {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .income-bg {
        background: linear-gradient(135deg, #48bb78, #38a169);
    }

    .expense-bg {
        background: linear-gradient(135deg, #f56565, #e53e3e);
    }

    .legend-text-modern {
        display: flex;
        flex-direction: column;
    }

    .legend-title {
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 2px;
    }

    .legend-desc {
        font-size: 12px;
        color: #718096;
        font-weight: 500;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .chart-header-modern {
            flex-direction: column;
            gap: 16px;
            text-align: center;
        }

        .chart-stats {
            justify-content: center;
        }

        .chart-legend-modern {
            flex-direction: column;
            gap: 16px;
        }

        .chart-body-modern {
            padding: 20px;
        }
    }
    </style>
</head>
<body>

<!-- Include Sidebar (yang sudah ada) -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
            <div class="page-title">
                <h1>Dashboard</h1>
                <div class="page-subtitle">Ringkasan keuangan RT/RW</div>
            </div>
        <div class="page-header-right">
            <div class="actions">
                <a href="iuranbendahara.php" class="btn btn-primary custom">Tambah Iuran</a>
                <a href="pengeluaranbendahara.php" class="btn btn-outline-secondary">Input Pengeluaran</a>
            </div>
        </div>
    </div>

    <!-- METRICS -->
    <div class="metric-grid">
        <a href="laporanbulanan.php" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Saldo Kas Real-time</div>
            <div class="metric-value"><?= format_rupiah($saldo_kas) ?></div>
            <div class="metric-badge">+<?= $persentase_perubahan ?>% vs bulan lalu</div>
            <div class="icon-wrapper"><span>$</span></div>
        </a>

        <a href="iuranbendahara.php" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Pemasukan Bulan Ini</div>
            <div class="metric-value"><?= format_rupiah($pemasukan_bulan_ini) ?></div>
            <div class="icon-wrapper"><i class="bi bi-graph-up-arrow"></i></div>
        </a>

        <a href="pengeluaranbendahara.php" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Pengeluaran Bulan Ini</div>
            <div class="metric-value"><?= format_rupiah($pengeluaran_bulan_ini) ?></div>
            <div class="icon-wrapper"><i class="bi bi-graph-down"></i></div>
        </a>

        <a href="warga.php" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Total Warga</div>
            <div class="metric-value"><?= $total_warga ?></div>
            <div class="icon-wrapper"><i class="bi bi-people"></i></div>
        </a>

        <a href="iuranbendahara.php?status=Lunas&bulan=<?= $bulan_indo[date('n')-1] ?>" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Iuran Lunas (<?= $bulan_indo[date('n')-1] ?>)</div>
            <div class="metric-value"><?= $iuran_lunas ?></div>
            <div class="icon-wrapper"><i class="bi bi-card-checklist"></i></div>
        </a>

        <a href="iuranbendahara.php?status=Belum%20Lunas&bulan=<?= $bulan_indo[date('n')-1] ?>" class="metric-card text-decoration-none text-dark">
            <div class="metric-title">Iuran Belum Lunas (<?= $bulan_indo[date('n')-1] ?>)</div>
            <div class="metric-value"><?= format_rupiah($iuran_tertunggak) ?></div>
            <div class="icon-wrapper"><i class="bi bi-exclamation-triangle"></i></div>
        </a>
    </div>

    <br>

    <!-- Pemasukan VS Pengeluaran -->
    <div class="chart-container-large">
        <div class="chart-title">
            <i class="bi bi-graph-up"></i> Pemasukan vs Pengeluaran <?= $current_year ?>
        </div>
        <div class="chart-box-large">
            <canvas id="trenTahunanChart" height="300"></canvas>
        </div>
    </div>
    <br><br>

    <div class="row d-flex align-items-stretch">
        <div class="col-lg-8">
            <div class="card-table mt-3 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">10 Transaksi Terakhir</h5>
                    <a href="pengeluaranbendahara.php" class="btn btn-outline-secondary btn-sm">Input Pengeluaran</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama</th>
                                <th>Bulan</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transaksi_terakhir as $t) { ?>
                            <tr>
                                <td><?= $t['tanggal'] ?></td>
                                <td><?= $t['nama'] ?></td>
                                <td><?= $t['bulan'] ?></td>
                                <td><?= format_rupiah($t['jumlah']) ?></td>
                                <td><span class="badge badge-success"><?= $t['status'] ?></span></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> 
        <!-- QUICK ACTIONS -->
        <div class="col-lg-4">
            <div class="quick-actions-container mt-3 h-100 d-flex flex-column">
                <h6 class="fw-bold text-white mb-3">Quick Actions</h6>
                <a href="warga.php?role=warga" class="qa-card">
                    <div class="qa-title">Registrasi Warga Baru</div>
                    <div class="qa-sub">Tambah warga ke sistem</div>
                </a>
                <a href="laporanbulanan.php?action=export" class="qa-card mt-3">
                    <div class="qa-title">Export Laporan</div>
                    <div class="qa-sub">Download laporan bulan ini</div>
                </a>
            </div>
        </div>
    </div> 

</section>

<script>
// Global variables for charts
let trenTahunanChart;

// Function to format rupiah
function formatRupiah(n) {
    const s = n < 0 ? '-' : '';
    return 'Rp ' + s + Math.abs(n).toLocaleString('id-ID');
}

// Function to update charts
function updateCharts(bulanLabels, pemasukanBulanan, pengeluaranBulanan) {
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

// Function to update the chart title with current year
function updateChartTitle() {
    const currentYear = new Date().getFullYear();
    const chartTitle = document.querySelector('.chart-title');
    if (chartTitle) {
        chartTitle.innerHTML = '<i class="bi bi-graph-up"></i> Pemasukan vs Pengeluaran ' + currentYear;
    }
}

// Function to update metric titles with current month
function updateMetricTitles() {
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const currentMonthIndex = new Date().getMonth();
    const currentMonthName = months[currentMonthIndex];

    // Update all metric titles that contain 'Iuran Lunas' or 'Iuran Belum Lunas'
    const metricTitles = document.querySelectorAll('.metric-title');
    metricTitles.forEach(title => {
        if (title.textContent.includes('Iuran Lunas')) {
            title.textContent = 'Iuran Lunas (' + currentMonthName + ')';
        } else if (title.textContent.includes('Iuran Belum Lunas')) {
            title.textContent = 'Iuran Belum Lunas (' + currentMonthName + ')';
        }
    });
}

// Function to schedule the next year update
function scheduleYearUpdate() {
    const now = new Date();
    const nextYear = new Date(now.getFullYear() + 1, 0, 1, 0, 0, 0, 0); // January 1st of next year
    const timeUntilNextYear = nextYear - now;
    setTimeout(function() {
        updateChartTitle();
        scheduleYearUpdate(); // Schedule the next update
    }, timeUntilNextYear);
}

// Function to schedule the next month update
function scheduleMonthUpdate() {
    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1, 0, 0, 0, 0); // 1st of next month
    const timeUntilNextMonth = nextMonth - now;
    setTimeout(function() {
        updateMetricTitles();
        scheduleMonthUpdate(); // Schedule the next update
    }, timeUntilNextMonth);
}

// Initialize chart on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCharts(
        <?php echo json_encode($labels); ?>,
        <?php echo json_encode($pemasukan); ?>,
        <?php echo json_encode($pengeluaran); ?>,
        <?php echo json_encode($saldo); ?>
    );
    updateChartTitle();
    updateMetricTitles();
    scheduleYearUpdate(); // Schedule the first year update
    scheduleMonthUpdate(); // Schedule the first month update
});
</script>

</body>
</html>
