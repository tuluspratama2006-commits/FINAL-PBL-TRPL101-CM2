<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    echo "Akses ditolak";
    exit();
}

// Cek apakah ada data yang akan dihapus
if (!isset($_SESSION['hapus_data'])) {
    $_SESSION['error_message'] = 'Tidak ada data yang dipilih untuk dihapus.';
    header("Location: iuranbendahara.php");
    exit();
}

$data = $_SESSION['hapus_data'];
$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

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
    <title>Konfirmasi Hapus - Bendahara - AKURAD.APP</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Konfirmasi Hapus Data Iuran
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <strong>PERHATIAN!</strong> Data yang sudah dihapus tidak dapat dikembalikan.
                            </div>
                            
                            <p>Apakah Anda yakin ingin menghapus data iuran berikut?</p>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="40%">Nama Warga</th>
                                        <td><?= htmlspecialchars($data['nama_lengkap']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Bulan</th>
                                        <td><?= $bulan_indo[$data['bulan'] - 1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tahun</th>
                                        <td><?= $data['tahun']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah Iuran</th>
                                        <td>Rp <?= number_format($data['jumlah_iuran'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge <?= $data['status_pembayaran'] === 'Lunas' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?= $data['status_pembayaran']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="iuranbendahara.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Batal
                                </a>
                                
                                <form method="POST" action="../backend/iuran/hapus_iuran.php" class="d-inline">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash me-2"></i>Ya, Hapus Data
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto close alert setelah 5 detik
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>