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

// Include koneksi database
require_once '../config/koneksi.php';

// Array bulan Indonesia
$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Fungsi format rupiah
function format_rupiah($n){
    $s = $n < 0 ? '-' : '';
    return 'Rp '.$s.number_format(abs($n),0,',','.');
}

// Ambil RT warga dari session
$rt_warga = $_SESSION['rt_number'] ?? '';

// Validasi koneksi database
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

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

// Query untuk mengambil data iuran berdasarkan id_warga
$query = "SELECT i.*, w.nama_lengkap, u.nik, u.rt_number
          FROM iuran_rutin i
          LEFT JOIN warga w ON i.id_warga = w.id_warga
          LEFT JOIN user u ON w.id_warga = u.id_warga
          WHERE i.id_warga = '" . mysqli_real_escape_string($koneksi, $id_warga) . "'
          ORDER BY i.tahun DESC, i.bulan DESC, i.created_at DESC";

$result = mysqli_query($koneksi, $query);

// Cek jika query gagal
if (!$result) {
    die("Query gagal: " . mysqli_error($koneksi));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iuran - Warga - AKURAD.APP</title>

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

/* Style untuk halaman dashboard */
.card-box{
    background:#fff;border-radius:10px;padding:15px;
    box-shadow:0 2px 6px rgba(0,0,0,.08)
}

.info-value{font-size:1.3rem;font-weight:700}
.badge-up{background:#dcfce7;color:#166534}

.rt-item{border-bottom:1px dashed #e5e7eb;padding:10px 0}
.rt-item:last-child{border-bottom:none}

.quick-btn{
    height:90px;font-weight:600;
    display:flex;flex-direction:column;
    justify-content:center;align-items:center
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
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-open .topbar {
        margin-left: 0;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 20px;
        margin-top: 60px;
    }

    /* Topbar untuk mobile sudah ada di sidebar.php */
}

/* Style untuk filter controls */
.filter-controls {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

/* Style untuk badge status */
.badge-lunas {
    background-color: #d1e7dd;
    color: #0f5132;
    padding: 5px 10px;
    border-radius: 20px;
}

.badge-belum {
    background-color: #f8d7da;
    color: #842029;
    padding: 5px 10px;
    border-radius: 20px;
}

/* Responsive untuk filter controls */
@media (max-width: 992px) {
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-controls input,
    .filter-controls select {
        width: 100% !important;
    }

    .filter-controls .ms-auto {
        margin-left: 0 !important;
        margin-top: 10px;
        width: 100%;
    }

    .filter-controls .ms-auto .d-flex {
        flex-wrap: wrap;
    }
}

/* Page Header */
.header-section {
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

.btn-export {
    background-color: white;
    color: #495057;
    font-weight: 500;
    padding: 10px 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

/* Topbar hanya untuk desktop */
.topbar {
    display: none;
}

@media (min-width: 769px) {
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        transition: margin-left 0.3s ease;
    }

    .main-content.sidebar-open .topbar {
        margin-left: 0;
    }
}

/* Style untuk hidden row */
.hidden-row {
    display: none !important;
}

</style>
</head>
<body>
<!-- Tampilkan pesan sukses/error -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index: 9999;" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index: 9999;" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <!-- Topbar hanya untuk desktop -->
    <div class="topbar d-none d-md-flex mb-5">
        <div class="top-bar-left">
            <div class="d-flex flex-column">
                <h2 class="h3 mb-1">Iuran <?php echo $rt_warga; ?></h2>
                <p class="text-muted mb-0">Data iuran warga <?php echo $rt_warga; ?></p>
            </div>
        </div>
        <div class="top-bar-right ms-auto">
            <div class="d-flex gap-3">
                <button class="btn btn-export" onclick="exportData()" style="width: 200px;">
                    <i class="bi bi-download me-2"></i> Export Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls d-flex flex-nowrap gap-2 align-items-center">
        <div class="input-group" style="width: 550px;">
            <button class="btn btn-outline-secondary" type="button" id="btnSearch">
                <i class="bi bi-search"></i>
            </button>
            <input type="text" id="filterCari" class="form-control" placeholder="Cari Nama atau NIK...">
        </div>
        <select id="filterTahun" class="form-select" style="width: 200px;">
            <option value="" selected>Semua Tahun</option>
            <?php
            $current_year = date('Y');
            for ($year = $current_year - 2; $year <= $current_year + 2; $year++) {
                echo "<option value=\"$year\">$year</option>";
            }
            ?>
        </select>
        <select id="filterBulan" class="form-select" style="width: 200px;">
            <option value="">Semua Bulan</option>
            <?php foreach ($bulan_indo as $bulan): ?>
            <option value="<?php echo $bulan; ?>"><?php echo $bulan; ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterStatus" class="form-select" style="width: 200px;">
            <option value="">Semua Status</option>
            <option value="Lunas">Lunas</option>
            <option value="Belum Lunas">Belum Lunas</option>
        </select>
        <button id="btnReset" class="btn btn-secondary">
            Reset
        </button>
    </div>

    <!-- Info Jumlah Data -->
    <p class="text-muted mt-3 mb-3">
        Data Iuran <?php echo $rt_warga; ?> (<?= mysqli_num_rows($result); ?> data)
    </p>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover" id="tabelIuran">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>RT</th>
                    <th>Bulan</th>
                    <th>Jumlah</th>
                    <th>Tanggal Bayar</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= htmlspecialchars($row['nik'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td><?= htmlspecialchars($row['rt_number'] ?? '-'); ?></td>
                    <td><?= $bulan_indo[$row['bulan']-1] . ' ' . $row['tahun']; ?></td>
                    <td>Rp <?= number_format($row['jumlah_iuran'],0,',','.'); ?></td>
                    <td><?= $row['tanggal_pembayaran'] ? date('d/m/Y', strtotime($row['tanggal_pembayaran'])) : '-'; ?></td>
                    <td>
                        <span class="badge <?= $row['status_pembayaran']==='Lunas'?'badge-lunas':'badge-belum'; ?>">
                            <?= $row['status_pembayaran'] === 'Belum Bayar' ? 'Belum Lunas' : $row['status_pembayaran']; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        Belum ada data iuran untuk <?php echo $rt_warga; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi untuk export data
function exportData() {
    // Ambil nilai filter saat ini
    const filterCari = document.getElementById('filterCari').value;
    const filterTahun = document.getElementById('filterTahun').value;
    const filterBulan = document.getElementById('filterBulan').value;
    const filterStatus = document.getElementById('filterStatus').value;

    // Buat URL dengan parameter filter
    let url = '../backend/iuran/export_warga.php?';
    const params = [];

    if (filterCari) params.push('cari=' + encodeURIComponent(filterCari));
    if (filterTahun) params.push('tahun=' + encodeURIComponent(filterTahun));
    if (filterBulan) params.push('bulan=' + encodeURIComponent(filterBulan));
    if (filterStatus) params.push('status=' + encodeURIComponent(filterStatus));

    url += params.join('&');

    // Redirect ke URL export
    window.location.href = url;
}

// Fungsi untuk mendapatkan parameter URL
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Event listener untuk status radio button
document.addEventListener('DOMContentLoaded', function() {
    // Filtering tabel
    const filterCari = document.getElementById('filterCari');
    const filterTahun = document.getElementById('filterTahun');
    const filterBulan = document.getElementById('filterBulan');
    const filterStatus = document.getElementById('filterStatus');
    const btnSearch = document.getElementById('btnSearch');
    const btnReset = document.getElementById('btnReset');
    const tabel = document.getElementById('tabelIuran');
    const rows = tabel.querySelectorAll('tbody tr');

    function applyFilter() {
        const cariValue = filterCari.value.toLowerCase().trim();
        const tahunValue = document.getElementById('filterTahun').value;
        const bulanValue = filterBulan.value;
        const statusValue = filterStatus.value;

        console.log('Applying filter:', { cariValue, tahunValue, bulanValue, statusValue });

        rows.forEach(row => {
            // Skip rows that don't have enough cells (like "no data" rows)
            if (row.cells.length < 8) {
                row.classList.remove('hidden-row');
                row.style.display = '';
                return;
            }

            const nik = row.cells[1].textContent.toLowerCase().trim();
            const nama = row.cells[2].textContent.toLowerCase().trim();
            const bulanTahun = row.cells[4].textContent.trim();
            const status = row.cells[7].querySelector('.badge') ? row.cells[7].querySelector('.badge').textContent.trim().toLowerCase() : '';

            console.log('Row data:', { nik, nama, bulanTahun, status });

            const matchCari = cariValue === '' || nik.includes(cariValue) || nama.includes(cariValue);
            const matchTahun = tahunValue === '' || bulanTahun.includes(tahunValue);
            const matchBulan = bulanValue === '' || bulanTahun.includes(bulanValue);
            const matchStatus = statusValue === '' || status === statusValue.toLowerCase();

            console.log('Matches:', { matchCari, matchTahun, matchBulan, matchStatus });

            if (matchCari && matchTahun && matchBulan && matchStatus) {
                row.classList.remove('hidden-row');
                row.style.display = '';
            } else {
                row.classList.add('hidden-row');
                row.style.display = 'none';
            }
        });
    }

    // Set filter values from URL parameters
    const urlStatus = getUrlParameter('status');
    const urlBulan = getUrlParameter('bulan');
    const urlTahun = getUrlParameter('tahun');
    if (urlStatus) {
        filterStatus.value = urlStatus;
    }
    if (urlBulan) {
        filterBulan.value = urlBulan;
    }
    if (urlTahun) {
        filterTahun.value = urlTahun;
    }
    if (urlStatus || urlBulan || urlTahun) {
        // Apply filter immediately
        applyFilter();
    }

    // Add event listeners for immediate filtering
    filterCari.addEventListener('input', applyFilter);
    filterTahun.addEventListener('change', applyFilter);
    filterBulan.addEventListener('change', applyFilter);
    filterStatus.addEventListener('change', applyFilter);
    btnSearch.addEventListener('click', applyFilter);
    btnReset.addEventListener('click', function() {
        filterCari.value = '';
        filterTahun.value = '';
        filterBulan.value = '';
        filterStatus.value = '';
        applyFilter();
        alert('Filter telah direset. Semua data ditampilkan.');
    });
});

</script>
</body>
</html>
