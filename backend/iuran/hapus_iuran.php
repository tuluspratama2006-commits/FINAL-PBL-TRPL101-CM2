<?php
session_start();
require_once '../../config/koneksi.php';

$redirect_location = '../../dashboard_bendahara/iuranbendahara.php';

// Debug: Check database connection
if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: $redirect_location");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_location");
    exit();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai Bendahara.';
    header("Location: ../../auth/login.php");
    exit();
}

if (!isset($_SESSION['hapus_id'])) {
    $_SESSION['error_message'] = 'ID iuran tidak valid.';
    header("Location: $redirect_location");
    exit();
}

$id_iuran = $_SESSION['hapus_id'];

// Hapus data
$query = "DELETE FROM iuran_rutin WHERE id_iuran = '$id_iuran'";

if (mysqli_query($koneksi, $query)) {
    // Check if any row was actually deleted
    if (mysqli_affected_rows($koneksi) > 0) {
        $_SESSION['success_message'] = 'Data iuran berhasil dihapus.';

        // Jika iuran yang dihapus adalah lunas, update laporan_kas dan monitoring_kas
        if (isset($_SESSION['hapus_lunas']) && $_SESSION['hapus_lunas'] &&
            isset($_SESSION['hapus_bulan']) && isset($_SESSION['hapus_tahun']) && isset($_SESSION['hapus_jumlah'])) {
            $bulan = $_SESSION['hapus_bulan'];
            $tahun = $_SESSION['hapus_tahun'];
            $jumlah = $_SESSION['hapus_jumlah'];
            $periode = $bulan . '-' . $tahun;

            // Kurangi dari laporan_kas
            $query_check = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
            $result_check = mysqli_query($koneksi, $query_check);

            if (!$result_check) {
                error_log("Error checking laporan_kas: " . mysqli_error($koneksi));
            } elseif (mysqli_num_rows($result_check) > 0) {
                $row = mysqli_fetch_assoc($result_check);
                $id_laporan = $row['Id_laporan'];

                $query_update_laporan = "UPDATE laporan_kas
                                        SET Total_pemasukan = Total_pemasukan - $jumlah,
                                            Saldo_akhir = Saldo_akhir - $jumlah
                                        WHERE Id_laporan = $id_laporan";
                if (!mysqli_query($koneksi, $query_update_laporan)) {
                    error_log("Error updating laporan_kas: " . mysqli_error($koneksi));
                }
            }

            // Kurangi dari monitoring_kas untuk hari ini
            $query_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
            $result_monitoring = mysqli_query($koneksi, $query_monitoring);

            if (mysqli_num_rows($result_monitoring) > 0) {
                $query_update_monitoring = "UPDATE monitoring_kas
                                           SET saldo_kas = saldo_kas - $jumlah
                                           WHERE tanggal_monitoring = CURDATE()";
                if (!mysqli_query($koneksi, $query_update_monitoring)) {
                    error_log("Error updating monitoring_kas: " . mysqli_error($koneksi));
                }
            }
        }

        // Hapus session hapus data
        unset($_SESSION['hapus_data']);
        unset($_SESSION['hapus_id']);
        unset($_SESSION['hapus_lunas']);
        unset($_SESSION['hapus_jumlah']);
        unset($_SESSION['hapus_bulan']);
        unset($_SESSION['hapus_tahun']);
    } else {
        $_SESSION['error_message'] = 'Data iuran tidak ditemukan atau sudah dihapus.';
    }
} else {
    $_SESSION['error_message'] = 'Gagal menghapus data: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>