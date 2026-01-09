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
$bulan = mysqli_real_escape_string($koneksi, $_POST['bulan']);
$tahun = mysqli_real_escape_string($koneksi, $_POST['tahun']);
$jumlah_iuran = mysqli_real_escape_string($koneksi, $_POST['jumlah_iuran']);
$tanggal_bayar = !empty($_POST['tanggal_bayar']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal_bayar']) : null;

// Validasi
if (empty($id_iuran)) {
    $_SESSION['error_message'] = 'ID iuran tidak ditemukan.';
    header("Location: $redirect_location");
    exit();
}

if (empty($bulan) || empty($tahun)) {
    $_SESSION['error_message'] = 'Bulan dan tahun harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($jumlah_iuran) || !is_numeric($jumlah_iuran) || $jumlah_iuran <= 0) {
    $_SESSION['error_message'] = 'Jumlah iuran harus berupa angka positif.';
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
$old_jumlah = $row['jumlah_iuran'];
$old_status = $row['status_pembayaran'];
$old_bulan = $row['bulan'];
$old_tahun = $row['tahun'];

// Tentukan status baru
$status_baru = $tanggal_bayar ? 'Lunas' : 'Belum Lunas';

// Update data iuran
$query = "UPDATE iuran_rutin SET
            bulan = '$bulan',
            tahun = '$tahun',
            jumlah_iuran = '$jumlah_iuran',
            tanggal_pembayaran = " . ($tanggal_bayar ? "'$tanggal_bayar'" : "NULL") . ",
            status_pembayaran = '$status_baru'
          WHERE id_iuran = '$id_iuran'";

if (mysqli_query($koneksi, $query)) {
    $_SESSION['success_message'] = 'Iuran berhasil diupdate.';

    // Update laporan_kas jika jumlah atau status berubah
    if ($old_jumlah != $jumlah_iuran || $old_status != $status_baru || $old_bulan != $bulan || $old_tahun != $tahun) {
        // Kurangi dari laporan lama
        if ($old_status == 'Lunas') {
            $periode_lama = $old_bulan . '-' . $old_tahun;
            $query_update_lama = "UPDATE laporan_kas
                                 SET Total_pemasukan = Total_pemasukan - $old_jumlah,
                                     Saldo_akhir = Saldo_akhir - $old_jumlah
                                 WHERE Periode = '$periode_lama' AND Jenis_laporan = 'Bulanan'";
            mysqli_query($koneksi, $query_update_lama);
        }

        // Tambahkan ke laporan baru jika status lunas
        if ($status_baru == 'Lunas') {
            $periode_baru = $bulan . '-' . $tahun;
            $query_check_baru = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode_baru' AND Jenis_laporan = 'Bulanan'";
            $result_check_baru = mysqli_query($koneksi, $query_check_baru);

            if(mysqli_num_rows($result_check_baru) > 0) {
                // Update laporan yang ada
                $row_baru = mysqli_fetch_assoc($result_check_baru);
                $id_laporan_baru = $row_baru['Id_laporan'];

                $query_update_baru = "UPDATE laporan_kas
                                     SET Total_pemasukan = Total_pemasukan + $jumlah_iuran,
                                         Saldo_akhir = Saldo_akhir + $jumlah_iuran
                                     WHERE Id_laporan = $id_laporan_baru";
                mysqli_query($koneksi, $query_update_baru);
            } else {
                // Buat laporan baru
                $total_pengeluaran = 0;

                // Hitung pengeluaran untuk periode ini
                $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
                                     FROM pengeluaran_kegiatan
                                     WHERE MONTH(tanggal_pengeluaran) = $bulan
                                     AND YEAR(tanggal_pengeluaran) = $tahun";
                $result_peng = mysqli_query($koneksi, $query_pengeluaran);
                if($row_peng = mysqli_fetch_assoc($result_peng)) {
                    $total_pengeluaran = $row_peng['total'];
                }

                $saldo = $jumlah_iuran - $total_pengeluaran;

                $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                        VALUES ('Bulanan', '$periode_baru', $jumlah_iuran, $total_pengeluaran, $saldo)";
                mysqli_query($koneksi, $query_insert_laporan);
            }
        }
    }

} else {
    $_SESSION['error_message'] = 'Gagal mengupdate iuran: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>
