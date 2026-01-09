<?php
session_start();
require_once '../../config/koneksi.php';

if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_rt/iuranrt.php';

// Ambil data dari form
$id_iuran = mysqli_real_escape_string($koneksi, $_POST['id_iuran']);

// Validasi
if (empty($id_iuran)) {
    $_SESSION['error_message'] = 'ID iuran harus diisi.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah iuran ada
$query_check = "SELECT ir.*, u.rt_number FROM iuran_rutin ir
                LEFT JOIN warga w ON ir.id_warga = w.id_warga
                LEFT JOIN user u ON w.id_warga = u.id_warga
                WHERE ir.id_iuran = '$id_iuran'";
$result_check = mysqli_query($koneksi, $query_check);

if (!$result_check || mysqli_num_rows($result_check) == 0) {
    $_SESSION['error_message'] = 'Data iuran tidak ditemukan.';
    header("Location: $redirect_location");
    exit();
}

$row = mysqli_fetch_assoc($result_check);
$jumlah_iuran = $row['jumlah_iuran'];
$status = $row['status_pembayaran'];
$bulan = $row['bulan'];
$tahun = $row['tahun'];

// Hapus data iuran
$query_delete = "DELETE FROM iuran_rutin WHERE id_iuran = '$id_iuran'";

if (mysqli_query($koneksi, $query_delete)) {
    $_SESSION['success_message'] = 'Iuran berhasil dihapus.';

    // Update laporan_kas jika iuran lunas
    if ($status == 'Lunas') {
        $periode = $bulan . '-' . $tahun;
        $query_update_laporan = "UPDATE laporan_kas
                                SET Total_pemasukan = Total_pemasukan - $jumlah_iuran,
                                    Saldo_akhir = Saldo_akhir - $jumlah_iuran
                                WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
        mysqli_query($koneksi, $query_update_laporan);
    }

} else {
    $_SESSION['error_message'] = 'Gagal menghapus iuran: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>
