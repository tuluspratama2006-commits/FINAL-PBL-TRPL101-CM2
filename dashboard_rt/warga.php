r<?php
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

$user_role = $_SESSION['role'] ?? 'RT';
$user_nama = $_SESSION['nama'] ?? 'RT';
$user_rt = $_SESSION['rt_number'] ?? ''; // Get user's RT from session

// Handle success messages
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_GET['success'])) {
    if ($_GET['success'] === 'hapus') {
        $success_message = 'Warga berhasil dihapus!';
    } elseif ($_GET['success'] === 'edit') {
        $success_message = 'Data warga berhasil diperbarui!';
    }
}

// Query untuk mengambil data warga dengan join ke user - DIBATASI OLEH RT USER
$query = "
SELECT
    u.id_user,
    u.username,
    u.nik,
    u.rt_number,
    u.role,
    w.id_warga,
    w.nama_lengkap,
    w.alamat,
    w.no_telepon,
    w.status
FROM user u
LEFT JOIN warga w ON u.id_warga = w.id_warga
WHERE u.rt_number = '{$_SESSION['rt_number']}'
ORDER BY w.nama_lengkap ASC
";

$result_warga = mysqli_query($koneksi, $query);

if (!$result_warga) {
    die("Query error: " . mysqli_error($koneksi));
}

$total_warga = mysqli_num_rows($result_warga);

// Hitung jumlah warga saja (exclude RT dan Bendahara)
$query_warga = "SELECT COUNT(*) as count FROM user WHERE role = 'warga'";
$result_warga_count = mysqli_query($koneksi, $query_warga);
$warga_count = mysqli_fetch_assoc($result_warga_count)['count'] ?? 0;

// Hitung statistik berdasarkan RT dan role secara dinamis - DIBATASI OLEH RT USER
$query_stats = "SELECT rt_number, role, COUNT(*) as count FROM user WHERE rt_number = '{$_SESSION['rt_number']}' GROUP BY rt_number, role ORDER BY rt_number, role";

$stats_result = mysqli_query($koneksi, $query_stats);

$warga_stats = [];
$rt_role_stats = [];
$bendahara_stats = [];

while ($row = mysqli_fetch_assoc($stats_result)) {
    $rt = $row['rt_number'];
    $role = $row['role'];
    $count = $row['count'];

    if ($role === 'warga') {
        $warga_stats[$rt] = $count;
    } elseif ($role === 'RT') {
        $rt_role_stats[$rt] = $count;
    } elseif ($role === 'Bendahara') {
        $bendahara_stats[$rt] = $count;
    }
}

// Reset pointer untuk loop tabel
mysqli_data_seek($result_warga, 0);

// Hitung total warga per RT untuk statistik
$query_total_rt1 = "SELECT COUNT(*) as total FROM user";
$result_total = mysqli_query($koneksi, $query_total_rt1);
$total_aktif = mysqli_fetch_assoc($result_total)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Warga</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #072f66;
            --secondary-color: #f8f9fa;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --gray-light: #6c757d;
            --gray-lighter: #dee2e6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
        }

        /* Layout dengan sidebar */
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin: 0;
        }
        
        /* Sidebar styling (pastikan ada di sidebar.php) */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--primary-color);
            color: white;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-collapsed .sidebar {
            width: 70px;
        }
        
        /* Reset margin main content untuk sidebar */
        body{background:#f5f7fb}

        /* Main content adjustment */
        .main-content {
            margin-left: 0; /* Default: sidebar hidden */
            margin-top: 60px; /* Space untuk top bar */
            padding: 28px;
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s ease;
        }

        /* Ketika sidebar terbuka di desktop */
        @media (min-width: 769px) {
            .main-content.sidebar-open {
                margin-left: 260px;
            }
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
            color: var(--primary-color);
            font-weight: 700;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: var(--gray-light);
            font-size: 15px;
            font-weight: 400;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 100px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .stat-card.total::before {
            background-color: var(--success-color);
        }
        
        .stat-card.rt::before {
            background-color: var(--info-color);
        }
        
        .stat-card.bendahara::before {
            background-color: var(--warning-color);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1.2;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray-light);
            font-weight: 500;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .search-container {
            position: relative;
            width: 550px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--gray-lighter);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #4dabf7;
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-light);
            font-size: 18px;
        }

        .filter-options {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            color: var(--gray-light);
            font-weight: 500;
            white-space: nowrap;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--gray-lighter);
            border-radius: 6px;
            background: white;
            font-size: 14px;
            width: 250px;
            color: #333;
            cursor: pointer;
        }

        .btn-reset {
            padding: 8px 20px;
            background: var(--gray-light);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-reset:hover {
            background: #5a6268;
        }
        
        /* Table Section */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-lighter);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-tambah {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-tambah:hover {
            background: #0b3f77;
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .data-table thead {
            background: #f8f9fa;
        }
        
        .data-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid var(--gray-lighter);
            font-size: 14px;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-lighter);
            vertical-align: middle;
            font-size: 14px;
        }
        
        .data-table tbody tr:hover {
            background: rgba(7, 47, 102, 0.02);
        }
        
        .warga-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .warga-nama {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .warga-email {
            font-size: 13px;
            color: var(--gray-light);
        }
        
        .status-lunas {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-belum {
            color: var(--danger-color);
            background: rgba(220, 53, 69, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-edit {
            background: var(--warning-color);
            color: #212529;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            color: #212529;
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            color: white;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease-out;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-lighter);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: white;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-description {
            color: var(--gray-light);
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray-lighter);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4dabf7;
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.1);
        }
        
        .select-dropdown {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3e%3cpolyline points="6,9 12,15 18,9"%3e%3c/polyline%3e%3c/svg%3e');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
            cursor: pointer;
        }
        
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .grid-3col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--gray-lighter);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        
        .btn-batal {
            padding: 10px 20px;
            background: var(--gray-light);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-batal:hover {
            background: #5a6268;
        }
        
        .btn-simpan, .btn-update {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-simpan:hover, .btn-update:hover {
            background: #0b3f77;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 70px;
                padding: 15px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-options {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .btn-reset {
                margin-left: 0;
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                padding: 15px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .grid-2col,
            .grid-3col {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-edit, .btn-delete {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-tambah {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                max-width: 95%;
                margin: 0 auto;
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-light);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--gray-lighter);
        }
        
        .empty-state p {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Data Warga</h1>
                <div class="page-subtitle">Kelola data warga RT/RW</div>
            </div>
        <?php if ($user_role === 'RT' || $user_role === 'Bendahara'): ?>
            <div class="page-header-right">
                <button class="btn-tambah" onclick="openTambahWarga()">
                    <i class="bi bi-person-plus"></i> Tambah Warga
                </button>
            </div>
        <?php endif; ?>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $warga_count; ?></div>
                <div class="stat-label">Total Warga</div>
            </div>
            <?php
            // Get all unique RTs from the stats arrays
            $all_rts = array_unique(array_merge(
                array_keys($warga_stats),
                array_keys($rt_role_stats),
                array_keys($bendahara_stats)
            ));
            sort($all_rts);

            foreach ($all_rts as $rt):
                $warga_in_rt = $warga_stats[$rt] ?? 0;
                $rt_in_rt = $rt_role_stats[$rt] ?? 0;
                $bendahara_in_rt = $bendahara_stats[$rt] ?? 0;

                // Build consolidated label
                $roles = [];
                if ($warga_in_rt > 0) $roles[] = "Warga ($warga_in_rt)";
                if ($rt_in_rt > 0) $roles[] = "RT ($rt_in_rt)";
                if ($bendahara_in_rt > 0) $roles[] = "Bendahara ($bendahara_in_rt)";
                $consolidated_label = implode(', ', $roles);
            ?>
            <div class="stat-card rt">
                <div class="stat-number"><?php echo $rt; ?></div>
                <div class="stat-label"><?php echo $consolidated_label; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-controls d-flex flex-nowrap gap-2 align-items-center">
                <div class="input-group" style="width: 550px;">
                    <button class="btn btn-outline-secondary" type="button" id="btnSearch">
                        <i class="bi bi-search"></i>
                    </button>
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari Nama atau NIK...">
                </div>

                <select id="filterRole" class="form-select" style="width: 350px;">
                    <option value="">Semua Role</option>
                    <option value="warga">Warga</option>
                    <option value="RT">RT</option>
                    <option value="Bendahara">Bendahara</option>
                </select>
                <button id="btnReset" class="btn btn-secondary" onclick="resetFilters()" style="width: 250px;">
                    Reset
                </button>
            </div>
        </div>
        
        <!-- Table Section -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">Daftar Warga (<?php echo $total_aktif; ?> data aktif)</div>
    </div>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Warga</th>
                    <th style="width: 20%;">NIK</th>
                    <th style="width: 15%;">RT/RW</th>
                    <th style="width: 15%;">Role</th>
                    <th style="width: 15%;">Status</th>
                    <?php if ($user_role === 'RT' || $user_role === 'Bendahara'): ?>
                    <th style="width: 10%;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($total_warga > 0): ?>
                <?php while ($w = mysqli_fetch_assoc($result_warga)): ?>
                    <tr>
                        <td>
                            <div class="warga-info">
                                <div class="warga-nama">
                                    <?= htmlspecialchars($w['nama_lengkap'] ?? 'Nama tidak tersedia'); ?>
                                </div>
                                <div class="warga-email">
                                    <?= htmlspecialchars($w['email'] ?? $w['username']); ?>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($w['nik'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($w['rt_number'] ?? ''); ?></td>
                        <td>
                            <span class="badge 
                                <?php echo $w['role'] === 'RT' ? 'bg-primary' : 
                                        ($w['role'] === 'Bendahara' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                <?= htmlspecialchars($w['role'] ?? 'warga'); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            // Query untuk status iuran - DIPERBAIKI
                            $id_warga = $w['id_warga'] ?? 0;
                            if ($id_warga > 0) {
                                $query_iuran = "SELECT 
                                    SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as lunas,
                                    SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN 1 ELSE 0 END) as belum
                                FROM iuran_rutin WHERE id_warga = $id_warga";
                                
                                $result_iuran = mysqli_query($koneksi, $query_iuran);
                                $iuran = mysqli_fetch_assoc($result_iuran);
                                $lunas = $iuran['lunas'] ?? 0;
                                $belum = $iuran['belum'] ?? 0;
                            } else {
                                $lunas = 0;
                                $belum = 0;
                            }
                            ?>
                            <?php if ($lunas > 0): ?>
                                <span class="status-lunas"><?= $lunas ?> Lunas</span>
                            <?php endif; ?>
                            <?php if ($belum > 0): ?>
                                <span class="status-belum"><?= $belum ?> Belum</span>
                            <?php endif; ?>
                            <?php if ($lunas == 0 && $belum == 0): ?>
                                <span class="text-muted">Belum ada iuran</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($user_role === 'RT' || $user_role === 'Bendahara'): ?>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-edit" onclick="openEditWarga(<?= $w['id_user']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form action="../backend/warga/hapus_warga_rt.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id_user" value="<?= $w['id_user']; ?>">
                                    <button type="submit" class="btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus warga ini?')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo ($user_role === 'RT' || $user_role === 'Bendahara') ? '6' : '5'; ?>">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p>Belum ada data warga aktif</p>
                            <small class="text-muted">Semua data saat ini tidak memiliki email/username yang valid</small>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
    <!-- Modal Tambah Warga - DIPERBAIKI -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Registrasi Warga Baru</div>
            <button class="modal-close" onclick="closeTambahWarga()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="../backend/warga/tambah_warga_rt.php" method="POST" id="formTambahWarga">
                <p class="modal-description">Tambah warga baru ke sistem RT/RW</p>



                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control"
                            placeholder="Nama lengkap" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control"
                            placeholder="email@gmail.com" required>
                    <small class="text-muted">Email akan digunakan sebagai username untuk login</small>
                </div>

                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" class="form-control"
                            placeholder="16 digit NIK"
                            maxlength="16"
                            pattern="\d{16}"
                            title="Harus 16 digit angka"
                            required>
                    <small class="text-muted">Contoh: 2175502867900034</small>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <div class="grid-2col">
                        <input type="hidden" name="rt_number" value="<?php echo $_SESSION['rt_number']; ?>">
                        <div>
                            <select name="role" class="form-control select-dropdown" required id="role_select">
                                <option value="" disabled selected>Pilih Role</option>
                                <?php if ($user_role === 'Bendahara'): ?>
                                    <option value="warga" selected>Warga</option>
                                <?php else: ?>
                                    <option value="warga">Warga</option>
                                    <option value="RT">RT</option>
                                    <option value="Bendahara">Bendahara</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-batal" onclick="closeTambahWarga()">Batal</button>
                    <button type="submit" class="btn-simpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <!-- Modal Edit Warga - DIPERBAIKI -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Edit Data Warga</div>
            <button class="modal-close" onclick="closeEditWarga()">&times;</button>
        </div>
        
        <div class="modal-body">
            <form action="../backend/warga/edit_warga_rt.php" method="POST" id="formEditWarga">
                <input type="hidden" name="id_user" id="edit_id_user">
                <input type="hidden" name="id_warga" id="edit_id_warga">
                
                <p class="modal-description">Edit data warga</p>
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Login & Kontak</label>
                    <div class="grid-2col">
                        <input type="text" name="username" id="edit_username" class="form-control" 
                                placeholder="Username" required>
                        <input type="email" name="email" id="edit_email" class="form-control" 
                                placeholder="Email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" id="edit_nik" class="form-control" 
                            maxlength="16" pattern="\d{16}" required>
                </div>
                
                <div class="form-group">
                    <input type="hidden" name="rt_number" id="edit_rt" value="<?php echo $_SESSION['rt_number']; ?>">
                    <div class="grid-2col">
                        <div>
                            <label>Role</label>
                            <select name="role" id="edit_role" class="form-control select-dropdown" required>
                                <option value="warga">Warga</option>
                                <option value="RT">RT</option>
                                <option value="Bendahara">Bendahara</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" name="password" id="edit_password" class="form-control" 
                            placeholder="Password baru">
                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah password</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-batal" onclick="closeEditWarga()">Batal</button>
                    <button type="submit" class="btn-update">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
    // ==================== MODAL FUNCTIONS ====================
    function openTambahWarga() {
        document.getElementById('modalTambah').classList.add('active');
        document.getElementById('formTambahWarga').reset();
        // Reset pesan error jika ada
        clearFormErrors('formTambahWarga');
    }

    function closeTambahWarga() {
        document.getElementById('modalTambah').classList.remove('active');
    }

    async function openEditWarga(id) {
        try {
            const response = await fetch(`../backend/warga/get_warga_rt.php?id_user=${id}`);
            const data = await response.json();

            if (data.error) {
                alert(data.error);
                return;
            }

            // Fill form with data
            document.getElementById('edit_id_user').value = data.id_user;
            document.getElementById('edit_id_warga').value = data.id_warga || '';
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap || '';
            document.getElementById('edit_username').value = data.username || '';
            document.getElementById('edit_email').value = data.email || data.username || '';
            document.getElementById('edit_nik').value = data.nik || '';
            document.getElementById('edit_rt').value = data.rt || '';
            document.getElementById('edit_role').value = data.role || 'warga';

            // Reset password field
            document.getElementById('edit_password').value = '';

            document.getElementById('modalEdit').classList.add('active');

        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'Gagal mengambil data warga. Periksa koneksi internet Anda.');
        }
    }

    function closeEditWarga() {
        document.getElementById('modalEdit').classList.remove('active');
    }

    // ==================== FILTER FUNCTIONS ====================
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterRole').value = '';

        filterTable();

        // Show notification
        showAlert('info', 'Filter telah direset');
    }

    // Real-time filtering
    document.getElementById('searchInput').addEventListener('input', debounce(filterTable, 300));
    document.getElementById('filterRole').addEventListener('change', filterTable);

    // Add event listener for search button
    document.getElementById('btnSearch')?.addEventListener('click', filterTable);

    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
        const selectedRole = document.getElementById('filterRole').value.toLowerCase();

        const rows = document.querySelectorAll('.data-table tbody tr');
        let visibleCount = 0;
        const totalRows = rows.length;

        rows.forEach(row => {
            if (row.classList.contains('empty-row')) return;

            const name = row.cells[0]?.querySelector('.warga-nama')?.textContent?.toLowerCase() || '';
            const email = row.cells[0]?.querySelector('.warga-email')?.textContent?.toLowerCase() || '';
            const nik = row.cells[1]?.textContent?.toLowerCase() || '';
            const role = row.cells[3]?.textContent?.trim().toLowerCase() || '';

            // Search in multiple fields
            const searchMatch = !searchTerm ||
                               name.includes(searchTerm) ||
                               email.includes(searchTerm) ||
                               nik.includes(searchTerm);

            const roleMatch = !selectedRole || role === selectedRole;

            if (searchMatch && roleMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update table title with filter count
        updateTableCount(visibleCount, totalRows);
    }

    function updateTableCount(visible, total) {
        const tableTitle = document.querySelector('.table-title');
        if (tableTitle) {
            if (visible === total) {
                tableTitle.textContent = `Daftar Warga (${total} data)`;
            } else {
                tableTitle.textContent = `Daftar Warga (${visible} dari ${total} data)`;
            }
        }
    }

    // ==================== FORM VALIDATION ====================
    document.getElementById('formTambahWarga')?.addEventListener('submit', function(e) {
        if (!validateTambahForm()) {
            e.preventDefault();
        }
    });

    document.getElementById('formEditWarga')?.addEventListener('submit', function(e) {
        if (!validateEditForm()) {
            e.preventDefault();
        }
    });

    function validateTambahForm() {
        const form = document.getElementById('formTambahWarga');
        let isValid = true;

        // Clear previous errors
        clearFormErrors('formTambahWarga');

        // Validate Nama Lengkap
        const namaLengkap = form.querySelector('input[name="nama_lengkap"]').value.trim();
        if (namaLengkap.length < 2) {
            showFieldError('nama_lengkap', 'Nama lengkap minimal 2 karakter');
            isValid = false;
        }

        // Validate Email
        const email = form.querySelector('input[name="email"]').value;
        if (!validateEmail(email)) {
            showFieldError('email', 'Format email tidak valid');
            isValid = false;
        }

        // Validate NIK
        const nik = form.querySelector('input[name="nik"]').value;
        if (nik.length !== 16 || !/^\d{16}$/.test(nik)) {
            showFieldError('nik', 'NIK harus 16 digit angka');
            isValid = false;
        }

        // Validate Role
        const role = form.querySelector('select[name="role"]').value;
        if (!role) {
            showFieldError('role', 'Pilih Role terlebih dahulu');
            isValid = false;
        }

        if (!isValid) {
            showAlert('error', 'Periksa kembali data yang dimasukkan');
        }

        return isValid;
    }

    function validateEditForm() {
        const form = document.getElementById('formEditWarga');
        let isValid = true;

        // Clear previous errors
        clearFormErrors('formEditWarga');

        // Validate NIK
        const nik = form.querySelector('input[name="nik"]').value;
        if (nik.length !== 16 || !/^\d{16}$/.test(nik)) {
            showFieldError('edit_nik', 'NIK harus 16 digit angka');
            isValid = false;
        }

        // Validate Username
        const username = form.querySelector('input[name="username"]').value;
        if (!username || !/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
            showFieldError('edit_username', 'Username 3-50 karakter (huruf, angka, underscore)');
            isValid = false;
        }

        // Validate Email
        const email = form.querySelector('input[name="email"]').value;
        if (!validateEmail(email)) {
            showFieldError('edit_email', 'Format email tidak valid');
            isValid = false;
        }

        // Validate Password (optional)
        const password = form.querySelector('input[name="password"]').value;
        if (password && password.length < 6) {
            showFieldError('edit_password', 'Password minimal 6 karakter (biarkan kosong jika tidak ingin mengubah)');
            isValid = false;
        }

        if (!isValid) {
            showAlert('error', 'Periksa kembali data yang dimasukkan');
        }

        return isValid;
    }

    // ==================== UTILITY FUNCTIONS ====================
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function showFieldError(fieldName, message) {
        const field = document.querySelector(`[name="${fieldName}"]`) || document.getElementById(fieldName);
        if (field) {
            // Add error class to field
            field.classList.add('is-invalid');

            // Create or update error message
            let errorElement = field.parentNode.querySelector('.invalid-feedback');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback';
                field.parentNode.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }
    }

    function clearFormErrors(formId) {
        const form = document.getElementById(formId);
        if (!form) return;

        // Remove error classes
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Remove error messages
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
    }

    function showAlert(type, message) {
        // Remove existing alerts
        const existingAlert = document.querySelector('.custom-alert');
        if (existingAlert) existingAlert.remove();

        // Create alert element
        const alert = document.createElement('div');
        alert.className = `custom-alert alert-${type}`;
        alert.innerHTML = `
            <div class="alert-content">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'error' ? 'bi-exclamation-circle' : 'bi-info-circle'}"></i>
                <span>${message}</span>
                <button class="alert-close">&times;</button>
            </div>
        `;

        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .custom-alert {
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 2000;
                min-width: 300px;
                max-width: 400px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                animation: slideIn 0.3s ease-out;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .alert-success { background: var(--success-color); }
            .alert-error { background: var(--danger-color); }
            .alert-info { background: var(--info-color); }
            .alert-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .alert-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                margin-left: auto;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background 0.3s;
            }
            .alert-close:hover {
                background: rgba(255,255,255,0.2);
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(alert);

        // Add close functionality
        alert.querySelector('.alert-close').addEventListener('click', () => {
            alert.style.animation = 'slideOut 0.3s ease-out';
            alert.style.transform = 'translateX(100%)';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideOut 0.3s ease-out';
                alert.style.transform = 'translateX(100%)';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }

    // ==================== EVENT LISTENERS ====================
    // Close modals with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTambahWarga();
            closeEditWarga();
        }
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Prevent form submission on Enter in search
    document.getElementById('searchInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            filterTable();
        }
    });

    // ==================== INITIALIZATION ====================
    // Initialize filter on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterTable();

        // Add CSS for validation
        const validationStyles = document.createElement('style');
        validationStyles.textContent = `
            .is-invalid {
                border-color: var(--danger-color) !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            }
            .is-invalid:focus {
                box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
            }
            .invalid-feedback {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: var(--danger-color);
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(validationStyles);
    });

    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        showAlert('error', 'Terjadi kesalahan pada aplikasi');
    });
</script>
</body>
</html>
