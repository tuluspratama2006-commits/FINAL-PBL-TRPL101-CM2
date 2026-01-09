<?php
session_start();
require_once '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Bendahara') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai Bendahara.';
    header("Location: ../../auth/login.php");
    exit();
}

$id_iuran = mysqli_real_escape_string($koneksi, $_POST['id']);
$tanggal_bayar = mysqli_real_escape_string($koneksi, $_POST['tanggal_bayar']);

if (empty($tanggal_bayar)) {
    $_SESSION['error_message'] = 'Tanggal pembayaran harus diisi.';
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
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
        $message = "Halo {$nama_warga}, pembayaran iuran Anda untuk bulan {$nama_bulan} {$tahun} telah dikonfirmasi oleh Bendahara pada tanggal " . date('d/m/Y', strtotime($tanggal_bayar)) . ".";

        $notification_query = "INSERT INTO notifications (id_user, title, message, type, created_at)
                              VALUES ('$id_user_warga', '$title', '$message', 'success', NOW())";

        mysqli_query($koneksi, $notification_query);
    }

    $_SESSION['success_message'] = 'Pembayaran berhasil dikonfirmasi.';
} else {
    $_SESSION['error_message'] = 'Gagal mengkonfirmasi pembayaran: ' . mysqli_error($koneksi);
}

header("Location: ../../dashboard_bendahara/iuranbendahara.php");
exit();
?>