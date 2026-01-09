<?php
session_start();
require_once '../../config/koneksi.php';

if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_rt/pengeluaranrt.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_rt/pengeluaranrt.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_rt/pengeluaranrt.php';

// Ambil data dari form
$id_pengeluaran = mysqli_real_escape_string($koneksi, $_POST['id_pengeluaran']);

// Validasi
if (empty($id_pengeluaran)) {
    $_SESSION['error_message'] = 'ID pengeluaran harus diisi.';
    header("Location: $redirect_location");
    exit();
}

// Cek apakah pengeluaran ada
$query_check = "SELECT pk.*, u.rt_number FROM pengeluaran_kegiatan pk
                LEFT JOIN user u ON pk.id_user = u.id_user
                WHERE pk.id_pengeluaran = '$id_pengeluaran'";
$result_check = mysqli_query($koneksi, $query_check);

if (!$result_check || mysqli_num_rows($result_check) == 0) {
    $_SESSION['error_message'] = 'Data pengeluaran tidak ditemukan.';
    header("Location: $redirect_location");
    exit();
}

$row = mysqli_fetch_assoc($result_check);
$jumlah_pengeluaran = $row['jumlah_pengeluaran'];
$tanggal_pengeluaran = $row['tanggal_pengeluaran'];

// Hapus file bukti jika ada
if ($row['bukti'] && file_exists('../../' . $row['bukti'])) {
    unlink('../../' . $row['bukti']);
}

// Hapus data pengeluaran
$query_delete = "DELETE FROM pengeluaran_kegiatan WHERE id_pengeluaran = '$id_pengeluaran'";

if (mysqli_query($koneksi, $query_delete)) {
    $_SESSION['success_message'] = 'Pengeluaran berhasil dihapus.';

    // Update laporan_kas
    $periode = date('n-Y', strtotime($tanggal_pengeluaran));
    $query_update_laporan = "UPDATE laporan_kas
                            SET Total_pengeluaran = Total_pengeluaran - $jumlah_pengeluaran,
                                Saldo_akhir = Saldo_akhir + $jumlah_pengeluaran
                            WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
    mysqli_query($koneksi, $query_update_laporan);

} else {
    $_SESSION['error_message'] = 'Gagal menghapus pengeluaran: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>
