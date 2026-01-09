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

// Query untuk mengambil data iuran
$query = "SELECT i.*, w.nama_lengkap, u.nik, u.rt_number
          FROM iuran_rutin i
          LEFT JOIN warga w ON i.id_warga = w.id_warga
          LEFT JOIN user u ON w.id_warga = u.id_warga
          WHERE u.rt_number = '{$_SESSION['rt_number']}'
          ORDER BY i.tahun DESC, i.bulan DESC, i.created_at DESC";

$result = mysqli_query($koneksi, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iuran - RT - AKURAD.APP</title>

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

/* Style untuk tombol aksi */
.btn-action {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
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

/* Style untuk checkbox bulan */
.form-check {
    margin-bottom: 8px;
    min-width: 100px;
}

.form-check-input {
    margin-right: 8px;
}

.form-check-label {
    cursor: pointer;
    user-select: none;
}

/* Modal khusus */
.modal-lg {
    max-width: 700px;
}

/* Badge untuk status di modal */
.status-option {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    margin-right: 10px;
    margin-bottom: 10px;
    border: 2px solid transparent;
    transition: all 0.3s;
}

.status-option.active {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.1);
}

.status-lunas {
    background-color: #d1e7dd;
    color: #0f5132;
}

.status-belum {
    background-color: #f8d7da;
    color: #842029;
}

/* Style untuk radio buttons custom */
.status-radio {
    display: none;
}

.status-radio:checked + .status-option {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.1);
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

.btn-input {
    background-color: #072f66;
    color: white;
    font-weight: 500;
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
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
                <h2 class="h3 mb-1">Iuran</h2>
                <p class="text-muted mb-0">Kelola iuran seluruh RT/RW</p>
            </div>
        </div>
        <div class="top-bar-right ms-auto">
            <div class="d-flex gap-3">
                <button class="btn btn-export" data-bs-toggle="modal" data-bs-target="#modalUploadCSV" style="width: 200px;">
                    <i class="bi bi-upload me-2"></i> Upload CSV
                </button>
                <button class="btn btn-export" onclick="exportData()" style="width: 200px;">
                    Export
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
                <select id="filterRT" class="form-select" style="width: 200px;">
                    <option value="">Semua RT</option>
                    <option value="RT 001">RT 001</option>
                    <option value="RT 002">RT 002</option>
                    <option value="RT 003">RT 003</option>
                    <option value="RT 004">RT 004</option>
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
                Data Iuran (<?= mysqli_num_rows($result); ?> data)
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
                                Belum ada data iuran
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- MODAL EDIT IURAN -->
<div class="modal fade" id="modalEditIuran" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Data Iuran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../backend/iuran/edit_iuran_rt.php" method="POST">
                <div class="modal-body">
                    <p class="text-muted mb-4">Edit data iuran warga</p>

                    <input type="hidden" id="edit_id" name="id">

                    <!-- Data Warga (Readonly) -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Data Warga</label>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-control" id="edit_nik" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" id="edit_nama" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">RT</label>
                                <input type="text" class="form-control" id="edit_rt" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Bulan -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Bulan</label>
                        <select class="form-select" id="edit_bulan" name="bulan" required>
                            <?php foreach ($bulan_indo as $bulan): ?>
                            <option value="<?php echo $bulan; ?>"><?php echo $bulan; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tahun -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Tahun</label>
                        <div class="form-control-plaintext fw-bold" id="tahunEdit"></div>
                        <input type="hidden" name="tahun" id="tahunEditInput" required>
                        <small class="text-muted">Tahun otomatis berdasarkan tahun sekarang</small>
                    </div>

                    <!-- Jumlah -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Jumlah Iuran (Rp)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="edit_jumlah" name="jumlah" min="0" required>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Status Pembayaran</label>
                        <div>
                            <input type="radio" name="status" id="status_lunas" value="Lunas" class="status-radio" checked>
                            <label for="status_lunas" class="status-option status-lunas">Lunas</label>

                            <input type="radio" name="status" id="status_belum" value="Belum Lunas" class="status-radio">
                            <label for="status_belum" class="status-option status-belum">Belum Lunas</label>
                        </div>
                    </div>

                    <!-- Tanggal Pembayaran -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Tanggal Pembayaran</label>
                        <input type="date" class="form-control" id="edit_tanggal_bayar" name="tanggal_bayar">
                    </div>

                    <!-- Keterangan (Opsional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-2">Keterangan</label>
                        <textarea class="form-control" id="edit_keterangan" name="keterangan" rows="3" placeholder="Tambahkan keterangan jika perlu..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pembayaran -->
<div class="modal fade" id="modalKonfirmasiBayar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../backend/iuran/konfirmasi_rt.php">
                <div class="modal-body">
                    <p>Konfirmasi pembayaran iuran untuk:</p>
                    <p class="fw-bold" id="namaWarga"></p>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pembayaran</label>
                        <input type="date" class="form-control" name="tanggal_bayar" id="tanggalKonfirmasi" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <input type="hidden" id="konfirmasi_id" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalHapusIuran" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../backend/iuran/konfirmasi_hapus.php">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data iuran ini?</p>
                    <p class="text-muted small">Data yang sudah dihapus tidak dapat dikembalikan.</p>
                    <input type="hidden" id="hapus_id" name="id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload CSV -->
<div class="modal fade" id="modalUploadCSV" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Data Iuran via CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../backend/iuran/upload_csv_rt.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="text-muted mb-4">Upload file CSV untuk mengimport data iuran secara massal</p>

                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Pilih File CSV</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Format file: NIK, Nama, RT, Bulan, Tahun, Jumlah, Status, Tanggal Bayar</small>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Informasi:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Pastikan format CSV sesuai dengan contoh</li>
                            <li>Data yang sudah ada akan dilewati (tidak akan diupdate)</li>
                            <li>NIK dan RT harus sesuai dengan data warga yang terdaftar</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi untuk modal edit iuran
function editIuran(id, nik, nama, rt, bulan, tahun, jumlah, status) {
    // Isi data ke form edit
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nik').value = nik;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_rt').value = rt;
    document.getElementById('edit_bulan').value = bulan;
    document.getElementById('edit_tahun').value = tahun;
    document.getElementById('edit_jumlah').value = jumlah;

    // Set status pembayaran
    if (status === 'Lunas') {
        document.getElementById('status_lunas').checked = true;
        document.querySelector('.status-option.status-lunas').classList.add('active');
        document.querySelector('.status-option.status-belum').classList.remove('active');
    } else {
        document.getElementById('status_belum').checked = true;
        document.querySelector('.status-option.status-belum').classList.add('active');
        document.querySelector('.status-option.status-lunas').classList.remove('active');
    }

    // Tampilkan modal edit
    var modal = new bootstrap.Modal(document.getElementById('modalEditIuran'));
    modal.show();
}

// Fungsi untuk konfirmasi bayar
function konfirmasiBayar(id, nama) {
    document.getElementById('konfirmasi_id').value = id;
    document.getElementById('namaWarga').textContent = nama;
    var modal = new bootstrap.Modal(document.getElementById('modalKonfirmasiBayar'));
    modal.show();
}

// Fungsi untuk hapus iuran
function hapusIuran(id) {
    document.getElementById('hapus_id').value = id;
    var modal = new bootstrap.Modal(document.getElementById('modalHapusIuran'));
    modal.show();
}
// Fungsi untuk proses konfirmasi pembayaran
function prosesKonfirmasiBayar() {
    var id = document.getElementById('konfirmasi_id').value;
    var tanggal = document.getElementById('tanggalKonfirmasi').value;

    // Simulasi konfirmasi pembayaran
    alert('Pembayaran untuk ID ' + id + ' telah dikonfirmasi pada tanggal ' + tanggal);

    // Tutup modal
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalKonfirmasiBayar'));
    modal.hide();

    // Refresh halaman (simulasi)
    setTimeout(function() {
        location.reload();
    }, 1000);
}
// Fungsi konfirmasi hapus
function konfirmasiHapus() {
    var id = document.getElementById('hapus_id').value;

    // Simulasi hapus data
    alert('Data dengan ID ' + id + ' telah dihapus');

    // Tutup modal
    var modal = bootstrap.Modal.getInstance(document.getElementById('modalHapusIuran'));
    modal.hide();

    // Refresh halaman (simulasi)
    setTimeout(function() {
        location.reload();
    }, 1000);
}

// Fungsi untuk export data
function exportData() {
    // Ambil nilai filter saat ini
    const filterCari = document.getElementById('filterCari').value;
    const filterTahun = document.getElementById('filterTahun').value;
    const filterBulan = document.getElementById('filterBulan').value;
    const filterRT = document.getElementById('filterRT').value;
    const filterStatus = document.getElementById('filterStatus').value;

    // Buat URL dengan parameter filter
    let url = '../backend/iuran/export_rt.php?';
    const params = [];

    if (filterCari) params.push('cari=' + encodeURIComponent(filterCari));
    if (filterTahun) params.push('tahun=' + encodeURIComponent(filterTahun));
    if (filterBulan) params.push('bulan=' + encodeURIComponent(filterBulan));
    if (filterRT) params.push('rt=' + encodeURIComponent(filterRT));
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

// Auto-update functionality
let autoUpdateInterval;

function startAutoUpdate() {
    // Update every 30 seconds
    autoUpdateInterval = setInterval(function() {
        updateIuranData();
    }, 30000);
}

function stopAutoUpdate() {
    if (autoUpdateInterval) {
        clearInterval(autoUpdateInterval);
    }
}

function updateIuranData() {
    // Show loading indicator
    const tableBody = document.querySelector('#tabelIuran tbody');
    const loadingRow = document.createElement('tr');
    loadingRow.id = 'loading-row';
    loadingRow.innerHTML = '<td colspan="8" class="text-center"><i class="bi bi-arrow-repeat bi-spin me-2"></i>Memperbarui data...</td>';
    tableBody.insertBefore(loadingRow, tableBody.firstChild);

    fetch('../backend/iuran/get_iuran_rt.php')
        .then(response => response.json())
        .then(data => {
            // Remove loading row
            const loadingRow = document.getElementById('loading-row');
            if (loadingRow) loadingRow.remove();

            if (data.success) {
                updateTableWithData(data.data);
                updateDataCount(data.data.length);
            } else {
                console.error('Failed to fetch iuran data:', data.message);
            }
        })
        .catch(error => {
            // Remove loading row
            const loadingRow = document.getElementById('loading-row');
            if (loadingRow) loadingRow.remove();

            console.error('Error fetching iuran data:', error);
        });
}

function updateTableWithData(iuranData) {
    const tableBody = document.querySelector('#tabelIuran tbody');
    const bulanIndo = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    // Clear existing rows except loading row
    const rows = tableBody.querySelectorAll('tr:not(#loading-row)');
    rows.forEach(row => row.remove());

    if (iuranData.length > 0) {
        iuranData.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${row.nik || '-'}</td>
                <td>${row.nama_lengkap}</td>
                <td>${row.rt_number || '-'}</td>
                <td>${bulanIndo[row.bulan - 1]} ${row.tahun}</td>
                <td>Rp ${Number(row.jumlah_iuran).toLocaleString('id-ID')}</td>
                <td>${row.tanggal_pembayaran ? new Date(row.tanggal_pembayaran).toLocaleDateString('id-ID') : '-'}</td>
                <td>
                    <span class="badge ${row.status_pembayaran === 'Lunas' ? 'badge-lunas' : 'badge-belum'}">
                        ${row.status_pembayaran === 'Belum Bayar' ? 'Belum Lunas' : row.status_pembayaran}
                    </span>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    } else {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="8" class="text-center text-muted">Belum ada data iuran</td>';
        tableBody.appendChild(tr);
    }
}

function updateDataCount(count) {
    const countElement = document.querySelector('.text-muted.mt-3.mb-3');
    if (countElement) {
        countElement.textContent = `Data Iuran (${count} data)`;
    }
}

// Event listener untuk status radio button
document.addEventListener('DOMContentLoaded', function() {
    // Filtering tabel
    const filterCari = document.getElementById('filterCari');
    const filterTahun = document.getElementById('filterTahun');
    const filterBulan = document.getElementById('filterBulan');
    const filterRT = document.getElementById('filterRT');
    const filterStatus = document.getElementById('filterStatus');
    const btnSearch = document.getElementById('btnSearch');
    const btnReset = document.getElementById('btnReset');
    const tabel = document.getElementById('tabelIuran');
    const rows = tabel.querySelectorAll('tbody tr');

    function applyFilter() {
        const cariValue = filterCari.value.toLowerCase().trim();
        const tahunValue = document.getElementById('filterTahun').value;
        const bulanValue = filterBulan.value;
        const rtValue = filterRT.value;
        const statusValue = filterStatus.value;

        console.log('Applying filter:', { cariValue, tahunValue, bulanValue, rtValue, statusValue });

        rows.forEach(row => {
            // Skip rows that don't have enough cells (like "no data" rows)
            if (row.cells.length < 8) {
                row.classList.remove('hidden-row');
                row.style.display = '';
                return;
            }

            const nik = row.cells[1].textContent.toLowerCase().trim();
            const nama = row.cells[2].textContent.toLowerCase().trim();
            const rt = row.cells[3].textContent.trim();
            const bulanTahun = row.cells[4].textContent.trim();
            const status = row.cells[7].querySelector('.badge') ? row.cells[7].querySelector('.badge').textContent.trim().toLowerCase() : '';

            console.log('Row data:', { nik, nama, rt, bulanTahun, status });

            const matchCari = cariValue === '' || nik.includes(cariValue) || nama.includes(cariValue);
            const matchTahun = tahunValue === '' || bulanTahun.includes(tahunValue);
            const matchBulan = bulanValue === '' || bulanTahun.includes(bulanValue);
            const rtNum = parseInt(rt.replace(/[^\d]/g, ''));
            const rtValueNum = parseInt(rtValue);
            const matchRT = rtValue === '' || rt.includes(rtValue) || rtNum == rtValueNum;
            const matchStatus = statusValue === '' || status === statusValue.toLowerCase();

            console.log('Matches:', { matchCari, matchTahun, matchBulan, matchRT, matchStatus });

            if (matchCari && matchTahun && matchBulan && matchRT && matchStatus) {
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
    filterRT.addEventListener('change', applyFilter);
    filterStatus.addEventListener('change', applyFilter);
    btnSearch.addEventListener('click', applyFilter);
    btnReset.addEventListener('click', function() {
        filterCari.value = '';
        filterTahun.value = '';
        filterBulan.value = '';
        filterRT.value = '';
        filterStatus.value = '';
        applyFilter();
        alert('Filter telah direset. Semua data ditampilkan.');
    });

    // Set current year for modals
    const currentYear = new Date().getFullYear(); // Current year automatically

    // Set year for edit modal when opened
    document.getElementById('modalEditIuran').addEventListener('show.bs.modal', function() {
        document.getElementById('tahunEdit').textContent = currentYear;
        document.getElementById('tahunEditInput').value = currentYear;
    });

    // Start auto-update functionality
    startAutoUpdate();
});

</script>
</body>
</html>
