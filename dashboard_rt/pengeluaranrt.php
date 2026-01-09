<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}
// Cek apakah role adalah Bendahara
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/koneksi.php';

// Fungsi format Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Query untuk mengambil data pengeluaran - DIBATASI OLEH RT USER
$query = "SELECT pk.*, u.username as diajukan_oleh
          FROM pengeluaran_kegiatan pk
          LEFT JOIN user u ON pk.id_user = u.id_user
          WHERE u.rt_number = '{$_SESSION['rt_number']}'
          ORDER BY pk.tanggal_pengeluaran DESC, pk.created_at DESC";

$result = mysqli_query($koneksi, $query);

// Inisialisasi array expenses
$expenses = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = [
            'id_pengeluaran' => $row['id_pengeluaran'],
            'tanggal' => $row['tanggal_pengeluaran'],
            'kategori' => $row['kategori'],
            'deskripsi' => $row['deskripsi'],
            'jumlah' => $row['jumlah_pengeluaran'],
            'diajukan_oleh' => $row['diajukan_oleh'] ?: 'Tidak diketahui',
            'bukti' => $row['bukti']
        ];
    }
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengeluaran - RT - AKURAD.APP</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
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

/* Header Section */
.header-section {
    background-color: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border-left: 5px solid #0d6efd;
}

.page-title {
    color: #1a1a1a;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 28px;
}

.subtitle {
    color: #666;
    font-size: 1.1rem;
}

.data-count {
    color: #6c757d;
    font-size: 0.95rem;
}

/* Filter Section - SESUAI GAMBAR */
.filter-section {
    background-color: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

/* Search Box */
.search-box {
    position: relative;
    margin-bottom: 0;
}

.search-box input {
    padding-left: 40px;
    border-radius: 8px;
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

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

.btn-reset {
    background-color: #6c757d;
    color: white;
    font-weight: 500;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
}

.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='black' stroke-width='2' viewBox='0 0 16 16'%3e%3cpath d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    border-radius: 8px;
}

.divider {
    border-top: 1px solid #dee2e6;
    margin: 25px 0;
}

/* Data Section */
.data-section {
    background-color: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.data-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 0;
    font-size: 1.2rem;
}

.table-custom {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.table-custom thead th {
    background-color: #f1f3f5;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 12px 15px;
    font-size: 0.9rem;
}

.table-custom tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
    font-size: 0.9rem;
}

.table-custom tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

/* Tombol aksi sederhana sesuai gambar */
.btn-action-simple {
    width: 24px;
    height: 24px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: #6c757d;
    cursor: pointer;
}

.btn-action-simple:hover {
    color: #0d6efd;
}

.btn-action-simple i {
    font-size: 16px;
}

/* Untuk mobile view */
@media (max-width: 768px) {
    .main-content {
        padding: 20px;
        margin-top: 60px;
    }
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }

    .search-box {
        max-width: 100%;
    }
}
</style>
</head>
<body>
<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
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
    <!-- Topbar hanya untuk desktop -->
    <div class="topbar d-none d-md-flex mb-5">
        <div class="top-bar-left">
            <div class="d-flex flex-column">
                <h2 class="h3 mb-1">Pengeluaran</h2>
                <p class="text-muted mb-0">Kelola pengeluaran RT/RW</p>
            </div>
        </div>
        <div class="top-bar-right ms-auto">
            <div class="d-flex gap-3">
                <button class="btn btn-export" style="width: 200px;">
                    Export
                </button>
            </div>
        </div>
    </div>

    <!-- Header Section untuk mobile -->
    <div class="header-section d-md-none">
        <h1 class="page-title">Pengeluaran</h1>
        <p class="subtitle">Kelola pengeluaran RT/RW</p>
    </div>

    <!-- Filter Section - SESUAI GAMBAR -->
    <div class="filter-section">
        <!-- Baris untuk pencarian, kategori, dan reset -->
        <div class="row g-3 align-items-center">
            <!-- Kiri: Pencarian Deskripsi -->
            <div class="col-md-5">
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="btnSearch">
                        <i class="bi bi-search"></i>
                    </button>
                    <input type="text" class="form-control" placeholder="Cari Deskripsi..." id="searchDeskripsi">
                </div>
            </div>
            <!-- Tengah: Kategori dan Reset -->
            <div class="col-md-7 d-flex gap-2">
                <select class="form-select" id="filterKategori" style="flex: 1;">
                    <option value="">Semua Kategori</option>
                    <option value="Kebersihan">Kebersihan</option>
                    <option value="Keamanan">Keamanan</option>
                    <option value="Administrasi">Administrasi</option>
                    <option value="Kegiatan">Kegiatan</option>
                    <option value="Perbaikan">Perbaikan</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
                <button class="btn btn-secondary" id="btnReset">
                    Reset
                </button>
            </div>
        </div>

        <!-- Tombol untuk mobile -->
        <div class="d-flex d-md-none gap-2 mt-3">
            <button class="btn btn-export flex-grow-1">
                Export
            </button>
        </div>
    </div>

    <!-- Data Section -->
    <div class="data-section">
        <!-- Data Count -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="data-title">
                Data Pengeluaran (<?php echo count($expenses); ?> data)
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover">
                <thead>
                    <tr>
                        <th scope="col" style="width: 50px;">No</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Deskripsi</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Diajukan Oleh</th>
                        <th scope="col">Bukti</th>
                        <th scope="col" style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada data pengeluaran</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($expenses as $index => $expense): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= date('d F Y', strtotime($expense['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($expense['kategori']) ?></td>
                        <td><?= htmlspecialchars($expense['deskripsi']) ?></td>
                        <td><strong><?= formatRupiah($expense['jumlah']) ?></strong></td>
                        <td><?= htmlspecialchars($expense['diajukan_oleh']) ?></td>
                        <td>
                            <?php if ($expense['bukti']) : ?>
                                <a href="../uploads/<?= $expense['bukti'] ?>" target="_blank">Lihat</a>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-action-simple" onclick="showDetail(<?= $expense['id_pengeluaran'] ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail Pengeluaran -->
<div class="modal fade" id="modalDetailPengeluaran" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pengeluaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tanggal</label>
                    <p id="detail-tanggal">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kategori</label>
                    <p id="detail-kategori">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Deskripsi</label>
                    <p id="detail-deskripsi">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jumlah</label>
                    <p id="detail-jumlah" class="fw-bold">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Diajukan Oleh</label>
                    <p id="detail-diajukan">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Bukti</label>
                    <p id="detail-bukti">-</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Data pengeluaran dari PHP
const expensesData = <?php echo json_encode($expenses); ?>;

function showDetail(id) {
    const expense = expensesData.find(e => e.id_pengeluaran == id);
    if (expense) {
        document.getElementById('detail-tanggal').textContent = new Date(expense.tanggal).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        document.getElementById('detail-kategori').textContent = expense.kategori;
        document.getElementById('detail-deskripsi').textContent = expense.deskripsi;
        document.getElementById('detail-jumlah').textContent = 'Rp ' + parseInt(expense.jumlah).toLocaleString('id-ID');
        document.getElementById('detail-diajukan').textContent = expense.diajukan_oleh;

        if (expense.bukti) {
            document.getElementById('detail-bukti').innerHTML = `<a href="../uploads/${expense.bukti}" target="_blank">Lihat Bukti</a>`;
        } else {
            document.getElementById('detail-bukti').textContent = '-';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalDetailPengeluaran'));
        modal.show();
    }
}

document.addEventListener('DOMContentLoaded', function() {

    // Export button functionality
    const exportBtn = document.querySelector('.btn-export');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            // Ambil nilai filter saat ini
            const filterDeskripsi = document.getElementById('searchDeskripsi').value;
            const filterKategori = document.getElementById('filterKategori').value;

            // Buat URL dengan parameter filter
            let url = '../backend/pengeluaran/export_pengeluaran.php?';
            const params = [];

            if (filterDeskripsi) params.push('deskripsi=' + encodeURIComponent(filterDeskripsi));
            if (filterKategori) params.push('kategori=' + encodeURIComponent(filterKategori));

            url += params.join('&');

            // Redirect ke URL export
            window.location.href = url;
        });
    }

    // Reset button functionality
    const resetBtn = document.getElementById('btnReset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            document.getElementById('searchDeskripsi').value = '';
            document.getElementById('filterKategori').value = '';

            // Tampilkan semua data
            document.querySelectorAll('.table-custom tbody tr').forEach(row => {
                row.style.display = '';
            });

            alert('Filter telah direset ke pengaturan awal.');
        });
    }

    // Search functionality
    const searchInput = document.getElementById('searchDeskripsi');
    const searchBtn = document.getElementById('btnSearch');
    const tableRows = document.querySelectorAll('.table-custom tbody tr');

    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase();
        const filterKategori = document.getElementById('filterKategori').value.toLowerCase();

        tableRows.forEach(row => {
            const deskripsi = row.cells[3].textContent.toLowerCase();
            const kategori = row.cells[2].textContent.toLowerCase();

            const matchSearch = searchTerm === '' || deskripsi.includes(searchTerm);
            const matchKategori = filterKategori === '' || kategori === filterKategori;

            row.style.display = matchSearch && matchKategori ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', performSearch);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }

    // Filter kategori functionality
    const filterKategori = document.getElementById('filterKategori');
    if (filterKategori) {
        filterKategori.addEventListener('change', function() {
            const searchTerm = document.getElementById('searchDeskripsi').value.toLowerCase();
            const selectedKategori = this.value.toLowerCase();

            tableRows.forEach(row => {
                if (row.style.display === 'none') return;

                const deskripsi = row.cells[3].textContent.toLowerCase();
                const kategori = row.cells[2].textContent.toLowerCase();

                const matchSearch = searchTerm === '' || deskripsi.includes(searchTerm);
                const matchKategori = selectedKategori === '' || kategori === selectedKategori;

                row.style.display = matchSearch && matchKategori ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>
