<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit();
}

// Include koneksi database
require_once '../config/koneksi.php';



// Ambil id_warga dari database berdasarkan nik
$query_warga = "SELECT u.id_warga, w.nama_lengkap FROM user u JOIN warga w ON u.id_warga = w.id_warga WHERE u.nik = '" . mysqli_real_escape_string($koneksi, $_SESSION['nik']) . "'";
$result_warga = mysqli_query($koneksi, $query_warga);
$warga_data = mysqli_fetch_assoc($result_warga);
$id_warga = $warga_data['id_warga'] ?? null;

// Jika tidak ada id_warga, redirect
if (!$id_warga) {
    header("Location: ../auth/login.php");
    exit();
}

// Query total iuran dibayar (lunas)
$query_total_dibayar = "SELECT COALESCE(SUM(jumlah_iuran), 0) as total FROM iuran_rutin WHERE id_warga = '$id_warga' AND status_pembayaran = 'Lunas'";
$result_total_dibayar = mysqli_query($koneksi, $query_total_dibayar);
$total_iuran_dibayar = mysqli_fetch_assoc($result_total_dibayar)['total'];

// Query jumlah bulan iuran lunas
$query_lunas = "SELECT COUNT(*) as total_lunas FROM iuran_rutin WHERE id_warga = '$id_warga' AND status_pembayaran = 'Lunas'";
$result_lunas = mysqli_query($koneksi, $query_lunas);
$total_lunas = mysqli_fetch_assoc($result_lunas)['total_lunas'];
$iuran_lunas = $total_lunas . "/12 bulan";

// Query total belum lunas
$query_belum_lunas = "SELECT COALESCE(SUM(jumlah_iuran), 0) as total FROM iuran_rutin WHERE id_warga = '$id_warga' AND status_pembayaran = 'Belum Lunas'";
$result_belum_lunas = mysqli_query($koneksi, $query_belum_lunas);
$total_belum_lunas = mysqli_fetch_assoc($result_belum_lunas)['total'];

// Daftar bulan Indonesia
$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Query status iuran untuk tahun ini
$current_year = date('Y');
$status_iuran = [];
for ($i = 1; $i <= 12; $i++) {
    $query_status = "SELECT jumlah_iuran, status_pembayaran FROM iuran_rutin WHERE id_warga = '$id_warga' AND bulan = '$i' AND tahun = '$current_year'";
    $result_status = mysqli_query($koneksi, $query_status);
    $row = mysqli_fetch_assoc($result_status);

    $status = $row ? ($row['status_pembayaran'] == 'Lunas' ? 'Lunas' : 'Belum Lunas') : 'Belum Lunas';
    $jumlah = $row ? $row['jumlah_iuran'] : 0; // 0 jika belum ada data

    $status_iuran[] = [$bulan_indo[$i-1], $status, $jumlah];
}

// Query riwayat pembayaran (5 terakhir)
$query_riwayat = "SELECT tanggal_pembayaran, bulan, tahun, jumlah_iuran, status_pembayaran, keterangan
                  FROM iuran_rutin
                  WHERE id_warga = '$id_warga' AND status_pembayaran = 'Lunas'
                  ORDER BY tanggal_pembayaran DESC
                  LIMIT 5";
$result_riwayat = mysqli_query($koneksi, $query_riwayat);
$riwayat_pembayaran = [];
while ($row = mysqli_fetch_assoc($result_riwayat)) {
    $tanggal = $row['tanggal_pembayaran'] ? date('d F Y', strtotime($row['tanggal_pembayaran'])) : '-';
    $bulan_tahun = $bulan_indo[$row['bulan']-1] . ' ' . $row['tahun'];
    $riwayat_pembayaran[] = [
        $tanggal,
        $bulan_tahun,
        $row['jumlah_iuran'],
        $row['status_pembayaran'],
        $row['keterangan'] ?: '-'
    ];
}

// Hitung total iuran belum dibayar
$iuran_belum_bayar = 0;
foreach ($status_iuran as $iuran) {
    if ($iuran[1] === 'Belum Lunas') {
        $iuran_belum_bayar += $iuran[2];
    }
}

function rupiah($n){
    return "Rp " . number_format($n, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Warga - Aplikasi Web Keuangan RT/RW Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
    /* Reset margin main content untuk sidebar */
    body{background:#f8f9fa}

    /* Main content adjustment */
    .main-content {
        margin-left: 0; /* Default: sidebar hidden */
        margin-top: 60px; /* Space untuk top bar */
        padding: 20px;
        min-height: calc(100vh - 60px);
        transition: margin-left 0.3s ease;
    }

    /* Ketika sidebar terbuka di desktop */
    @media (min-width: 769px) {
        .main-content.sidebar-open {
            margin-left: 260px;
        }
    }

    /* Style untuk dashboard sesuai gambar */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .card-dashboard {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border: none;
    }
    
    .card-dashboard h3 {
        font-size: 1rem;
        color: #666;
        margin-bottom: 15px;
        font-weight: 500;
    }
    
    .card-dashboard .amount {
        font-size: 2rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 5px;
    }
    
    .card-dashboard .subtext {
        font-size: 0.9rem;
        color: #888;
    }
    
    .card-dashboard:nth-child(1) {
        border-left: 5px solid #4f46e5;
    }
    
    .card-dashboard:nth-child(2) {
        border-left: 5px solid #10b981;
    }
    
    .card-dashboard:nth-child(3) {
        border-left: 5px solid #ef4444;
    }
    
    /* Status Iuran Section */
    .status-iuran-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .status-iuran-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-bayar-title {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: background 0.3s;
    }

    .btn-bayar-title:hover {
        background: #d97706;
        color: white;
    }
    
    .status-iuran-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    
    .status-item {
        background: #f9f9f9;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        border: 1px solid #eee;
    }
    
    .bulan-nama {
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
        font-size: 1rem;
    }
    
    .bulan-jumlah {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 12px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-lunas {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .status-belum {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    /* Riwayat Pembayaran Section */
    .riwayat-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .riwayat-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .table-riwayat {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table-riwayat th {
        background-color: #f8f9fa;
        color: #555;
        font-weight: 600;
        padding: 15px;
        text-align: left;
        border-bottom: 2px solid #e9ecef;
        font-size: 0.9rem;
    }
    
    .table-riwayat td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        color: #555;
        vertical-align: middle;
    }
    
    .table-riwayat tr:hover {
        background-color: #f8fafc;
    }
    
    /* Pengingat Iuran Section */
    .pengingat-section {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-radius: 12px;
        padding: 25px;
        border-left: 5px solid #f59e0b;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .pengingat-content h4 {
        color: #92400e;
        font-weight: 600;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pengingat-content p {
        color: #92400e;
        margin: 0;
        font-size: 1rem;
    }
    
    .pengingat-amount {
        color: #b45309;
        font-weight: 700;
        font-size: 1.2rem;
    }
    
    .btn-bayar {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        transition: background 0.3s;
    }
    
    .btn-bayar:hover {
        background: #d97706;
        color: white;
    }
    
    /* Topbar (untuk desktop) */
    .topbar {
        display: none;
    }

    /* Responsive */
    @media (min-width: 769px) {
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: margin-left 0.3s ease;
        }
        
        .topbar h5 {
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        
        .search-box {
            width: 300px;
        }
    }

    @media (max-width: 1200px) {
        .status-iuran-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 992px) {
        .dashboard-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .status-iuran-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
            margin-top: 60px;
        }
        
        .dashboard-cards {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .card-dashboard {
            padding: 20px;
        }
        
        .card-dashboard .amount {
            font-size: 1.8rem;
        }
        
        .status-iuran-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .pengingat-section {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
        
        .btn-bayar {
            width: 100%;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table-riwayat {
            min-width: 700px;
        }
    }
    
    @media (max-width: 576px) {
        .status-iuran-grid {
            grid-template-columns: 1fr;
        }
        
        .status-item {
            padding: 15px;
        }
    }
    
    /* Link Lihat Laporan */
    .lihat-laporan {
        display: inline-block;
        margin-top: 30px;
        color: #4f46e5;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        transition: color 0.3s;
    }
    
    .lihat-laporan:hover {
        color: #3730a3;
        text-decoration: underline;
    }
    </style>
</head>
<body>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Top Bar -->
    <div class="topbar">
        <div>
            <h5 class="fw-bold mb-0">Dashboard Warga</h5>
            <small class="text-muted">Status iuran dan riwayat pembayaran</small>
        </div>
        <button class="btn btn-primary lihat-laporan-btn" onclick="window.location.href='laporanbulanan.php'">
            <i class="bi bi-file-text me-2"></i>Lihat Laporan Lengkap
        </button>
    </div>

    <!-- Dashboard Cards -->
    <div class="dashboard-cards">
        <div class="card-dashboard">
            <h3>Total Iuran Dibayar</h3>
            <div class="amount"><?= rupiah($total_iuran_dibayar) ?></div>
        </div>
        
        <div class="card-dashboard">
            <h3>Iuran Lunas</h3>
            <div class="amount"><?= $iuran_lunas ?></div>
            <div class="subtext">dari 12 bulan</div>
        </div>
        
        <div class="card-dashboard">
            <h3>Total Belum Lunas</h3>
            <div class="amount"><?= rupiah($total_belum_lunas) ?></div>
        </div>
    </div>

    <!-- Status Iuran -->
    <div class="status-iuran-section">
        <div class="status-iuran-title">
            <span>Status Iuran <?= $current_year ?></span>
            <button class="btn-bayar-title" onclick="window.location.href='bayariuranwarga.php'">Bayar Sekarang</button>
        </div>
        <div class="status-iuran-grid">
            <?php foreach ($status_iuran as $iuran): ?>
            <div class="status-item">
                <div class="bulan-nama"><?= $iuran[0] ?></div>
                <div class="bulan-jumlah"><?= rupiah($iuran[2]) ?></div>
                <span class="status-badge <?= $iuran[1] === 'Lunas' ? 'status-lunas' : 'status-belum' ?>">
                    <?= $iuran[1] ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Riwayat Pembayaran -->
    <div class="riwayat-section">
        <div class="riwayat-title">Riwayat Pembayaran</div>
        <div class="table-container">
            <table class="table-riwayat">
                <thead>
                    <tr>
                        <th>Tanggal Bayar</th>
                        <th>Bulan</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat_pembayaran as $riwayat): ?>
                    <tr>
                        <td><?= $riwayat[0] ?></td>
                        <td><?= $riwayat[1] ?></td>
                        <td><?= rupiah($riwayat[2]) ?></td>
                        <td>
                            <span class="status-badge status-lunas">
                                <?= $riwayat[3] ?>
                            </span>
                        </td>
                        <td><?= $riwayat[4] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pengingat Iuran -->
    <div class="pengingat-section">
        <div class="pengingat-content">
            <h4><i class="bi bi-exclamation-triangle-fill"></i> Pengingat Iuran</h4>
            <p>Anda memiliki 1 bulan iuran yang belum dibayar dengan total <span class="pengingat-amount"><?= rupiah($iuran_belum_bayar) ?></span></p>
        </div>
        <button class="btn-bayar" onclick="window.location.href='bayariuranwarga.php'">Bayar Sekarang</button>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>