<?php
session_start();
require_once '../../config/koneksi.php';

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

$id_iuran = mysqli_real_escape_string($koneksi, $_POST['id']);
$tanggal_bayar = mysqli_real_escape_string($koneksi, $_POST['tanggal_bayar']);

if (empty($tanggal_bayar)) {
    $_SESSION['error_message'] = 'Tanggal pembayaran harus diisi.';
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

// Cek apakah iuran milik RT yang login
$query_check = "SELECT ir.*, u.rt_number FROM iuran_rutin ir
                LEFT JOIN warga w ON ir.id_warga = w.id_warga
                LEFT JOIN user u ON w.id_warga = u.id_warga
                WHERE ir.id_iuran = '$id_iuran'";
$result_check = mysqli_query($koneksi, $query_check);

if (!$result_check || mysqli_num_rows($result_check) == 0) {
    $_SESSION['error_message'] = 'Data iuran tidak ditemukan.';
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

$row = mysqli_fetch_assoc($result_check);
if ($row['rt_number'] !== $_SESSION['rt_number']) {
    $_SESSION['error_message'] = 'Akses ditolak. Iuran bukan milik RT Anda.';
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

$query = "UPDATE iuran_rutin
            SET status_pembayaran = 'Lunas',
                tanggal_pembayaran = '$tanggal_bayar',
                updated_at = NOW()
            WHERE id_iuran = '$id_iuran'";

if (mysqli_query($koneksi, $query)) {
    // Get warga information for notification
    $query_warga = "SELECT i.*, w.nama_lengkap, u.id_user
                    FROM iuran_rutin i
                    LEFT JOIN warga w ON i.id_warga = w.id_warga
                    LEFT JOIN user u ON w.id_warga = u.id_warga
                    WHERE i.id_iuran = '$id_iuran'";

    $result_warga = mysqli_query($koneksi, $query_warga);
    if ($result_warga && mysqli_num_rows($result_warga) > 0) {
        $warga_data = mysqli_fetch_assoc($result_warga);
        $id_user_warga = $warga_data['id_user'];
        $nama_warga = $warga_data['nama_lengkap'];
        $bulan = $warga_data['bulan'];
        $tahun = $warga_data['tahun'];

        // Array bulan Indonesia
        $bulan_indo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $nama_bulan = $bulan_indo[$bulan];

        // Create notification for warga
        $title = "Pembayaran Iuran Dikonfirmasi";
        $message = "Halo {$nama_warga}, pembayaran iuran Anda untuk bulan {$nama_bulan} {$tahun} telah dikonfirmasi oleh RT pada tanggal " . date('d/m/Y', strtotime($tanggal_bayar)) . ".";

        $notification_query = "INSERT INTO notifications (id_user, title, message, type, created_at)
                              VALUES ('$id_user_warga', '$title', '$message', 'success', NOW())";

        mysqli_query($koneksi, $notification_query);
    }

    // Update laporan_kas
    $jumlah_iuran = $row['jumlah_iuran'];
    $bulan = $row['bulan'];
    $tahun = $row['tahun'];
    $periode = $bulan . '-' . $tahun;

    $query_check_laporan = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
    $result_laporan = mysqli_query($koneksi, $query_check_laporan);

    if ($result_laporan && mysqli_num_rows($result_laporan) > 0) {
        $row_laporan = mysqli_fetch_assoc($result_laporan);
        $id_laporan = $row_laporan['Id_laporan'];

        $query_update_laporan = "UPDATE laporan_kas
                                SET Total_pemasukan = Total_pemasukan + $jumlah_iuran,
                                    Saldo_akhir = Saldo_akhir + $jumlah_iuran
                                WHERE Id_laporan = $id_laporan";
        mysqli_query($koneksi, $query_update_laporan);
    } else {
        // Hitung pengeluaran untuk periode ini
        $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
                             FROM pengeluaran_kegiatan
                             WHERE MONTH(tanggal_pengeluaran) = $bulan
                             AND YEAR(tanggal_pengeluaran) = $tahun";
        $result_peng = mysqli_query($koneksi, $query_pengeluaran);
        $total_pengeluaran = 0;
        if ($result_peng && $row_peng = mysqli_fetch_assoc($result_peng)) {
            $total_pengeluaran = $row_peng['total'];
        }

        $saldo = $jumlah_iuran - $total_pengeluaran;

        $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                VALUES ('Bulanan', '$periode', $jumlah_iuran, $total_pengeluaran, $saldo)";
        mysqli_query($koneksi, $query_insert_laporan);
    }

    $_SESSION['success_message'] = 'Pembayaran berhasil dikonfirmasi.';
} else {
    $_SESSION['error_message'] = 'Gagal mengkonfirmasi pembayaran: ' . mysqli_error($koneksi);
}

header("Location: ../../dashboard_rt/iuranrt.php");
exit();
?>
