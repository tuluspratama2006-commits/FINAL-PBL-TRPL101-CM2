<?php
session_start();
require_once '../../config/koneksi.php';

// Debug: Check database connection
if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login terlebih dahulu.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_bendahara/iuranbendahara.php';

$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Ambil data dari form
$id_iuran = mysqli_real_escape_string($koneksi, $_POST['id']);
$bulan_nama = mysqli_real_escape_string($koneksi, $_POST['bulan']);
$tahun = mysqli_real_escape_string($koneksi, $_POST['tahun']);
$jumlah = mysqli_real_escape_string($koneksi, $_POST['jumlah']);
$status = mysqli_real_escape_string($koneksi, $_POST['status']);
$tanggal_bayar = !empty($_POST['tanggal_bayar']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal_bayar']) : null;
$keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

// Validasi input
if (empty($id_iuran)) {
    $_SESSION['error_message'] = 'ID iuran tidak valid.';
    header("Location: $redirect_location");
    exit();
}

// Konversi bulan dari nama ke angka
$bulan = array_search($bulan_nama, $bulan_indo) + 1;
if ($bulan === false || $bulan < 1 || $bulan > 12) {
    $_SESSION['error_message'] = 'Bulan tidak valid.';
    header("Location: $redirect_location");
    exit();
}

// Ambil data iuran sebelum update untuk perbandingan
$query_select = "SELECT bulan, tahun, jumlah_iuran, status_pembayaran FROM iuran_rutin WHERE id_iuran = '$id_iuran'";
$result_select = mysqli_query($koneksi, $query_select);

if (!$result_select || mysqli_num_rows($result_select) === 0) {
    $_SESSION['error_message'] = 'Data iuran tidak ditemukan.';
    header("Location: $redirect_location");
    exit();
}

$data_lama = mysqli_fetch_assoc($result_select);
$bulan_lama = $data_lama['bulan'];
$tahun_lama = $data_lama['tahun'];
$jumlah_lama = $data_lama['jumlah_iuran'];
$status_lama = $data_lama['status_pembayaran'];

// Update data
$query = "UPDATE iuran_rutin
            SET bulan = '$bulan',
                tahun = '$tahun',
                jumlah_iuran = '$jumlah',
                status_pembayaran = '$status',
                tanggal_pembayaran = " . ($tanggal_bayar ? "'$tanggal_bayar'" : "NULL") . ",
                keterangan = '$keterangan'
            WHERE id_iuran = '$id_iuran'";

if (mysqli_query($koneksi, $query)) {
    // Create notification for warga if status changed to Lunas or payment was updated
    if ($status_lama !== $status || $bulan_lama !== $bulan || $tahun_lama !== $tahun || $jumlah_lama !== $jumlah) {
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

            $nama_bulan = $bulan_indo[$bulan - 1];

            // Create appropriate notification message
            if ($status_lama !== $status && $status === 'Lunas') {
                $title = "Pembayaran Iuran Dikonfirmasi";
                $message = "Halo {$nama_warga}, pembayaran iuran Anda untuk bulan {$nama_bulan} {$tahun} telah dikonfirmasi oleh Bendahara.";
            } elseif ($status_lama !== $status && $status === 'Belum Lunas') {
                $title = "Status Pembayaran Diubah";
                $message = "Halo {$nama_warga}, status pembayaran iuran Anda untuk bulan {$nama_bulan} {$tahun} telah diubah menjadi 'Belum Lunas'.";
            } else {
                $title = "Data Iuran Diperbarui";
                $message = "Halo {$nama_warga}, data iuran Anda untuk bulan {$nama_bulan} {$tahun} telah diperbarui oleh Bendahara.";
            }

            $notification_query = "INSERT INTO notifications (id_user, title, message, type, created_at)
                                  VALUES ('$id_user_warga', '$title', '$message', 'info', NOW())";

            mysqli_query($koneksi, $notification_query);
        }
    }

    // Update laporan_kas dan monitoring_kas jika diperlukan
    $periode_lama = $bulan_lama . '-' . $tahun_lama;
    $periode_baru = $bulan . '-' . $tahun;

    // Jika status berubah dari Belum ke Lunas
    if ($status_lama === 'Belum' && $status === 'Lunas') {
        // Tambah ke laporan_kas periode baru
        $query_check = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode_baru' AND Jenis_laporan = 'Bulanan'";
        $result_check = mysqli_query($koneksi, $query_check);

        if (!$result_check) {
            error_log("Error checking laporan_kas: " . mysqli_error($koneksi));
        } elseif (mysqli_num_rows($result_check) > 0) {
            // Update laporan yang ada
            $row = mysqli_fetch_assoc($result_check);
            $id_laporan = $row['Id_laporan'];

            $query_update_laporan = "UPDATE laporan_kas
                                    SET Total_pemasukan = Total_pemasukan + $jumlah,
                                        Saldo_akhir = Saldo_akhir + $jumlah
                                    WHERE Id_laporan = $id_laporan";
            if (!mysqli_query($koneksi, $query_update_laporan)) {
                error_log("Error updating laporan_kas: " . mysqli_error($koneksi));
            }
        } else {
            // Buat laporan baru
            $total_pengeluaran = 0;

            // Hitung pengeluaran untuk periode ini
            $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
                                 FROM pengeluaran_kegiatan
                                 WHERE MONTH(tanggal_pengeluaran) = $bulan
                                 AND YEAR(tanggal_pengeluaran) = $tahun";
            $result_peng = mysqli_query($koneksi, $query_pengeluaran);
            if ($result_peng && $row_peng = mysqli_fetch_assoc($result_peng)) {
                $total_pengeluaran = $row_peng['total'];
            }

            $saldo = $jumlah - $total_pengeluaran;

            $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                    VALUES ('Bulanan', '$periode_baru', $jumlah, $total_pengeluaran, $saldo)";
            if (mysqli_query($koneksi, $query_insert_laporan)) {
                $id_laporan = mysqli_insert_id($koneksi);
            } else {
                error_log("Error inserting laporan_kas: " . mysqli_error($koneksi));
                $id_laporan = 0;
            }
        }

        // Update monitoring_kas untuk hari ini
        $query_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
        $result_monitoring = mysqli_query($koneksi, $query_monitoring);

        if ($result_monitoring && mysqli_num_rows($result_monitoring) > 0) {
            // Update existing
            $query_update_monitoring = "UPDATE monitoring_kas
                                       SET saldo_kas = saldo_kas + $jumlah,
                                           Id_laporan = $id_laporan
                                       WHERE tanggal_monitoring = CURDATE()";
        } else {
            // Insert new
            $query_update_monitoring = "INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring, created_at)
                                       VALUES ($id_laporan, $jumlah, CURDATE(), NOW())";
        }

        if (!mysqli_query($koneksi, $query_update_monitoring)) {
            error_log("Error updating monitoring_kas: " . mysqli_error($koneksi));
        }
    }
    // Jika status berubah dari Lunas ke Belum
    elseif ($status_lama === 'Lunas' && $status === 'Belum') {
        // Kurangi dari laporan_kas periode lama
        $query_check = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode_lama' AND Jenis_laporan = 'Bulanan'";
        $result_check = mysqli_query($koneksi, $query_check);

        if ($result_check && mysqli_num_rows($result_check) > 0) {
            $row = mysqli_fetch_assoc($result_check);
            $id_laporan = $row['Id_laporan'];

            $query_update_laporan = "UPDATE laporan_kas
                                    SET Total_pemasukan = Total_pemasukan - $jumlah_lama,
                                        Saldo_akhir = Saldo_akhir - $jumlah_lama
                                    WHERE Id_laporan = $id_laporan";
            if (!mysqli_query($koneksi, $query_update_laporan)) {
                error_log("Error updating laporan_kas: " . mysqli_error($koneksi));
            }
        }

        // Kurangi dari monitoring_kas untuk hari ini
        $query_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
        $result_monitoring = mysqli_query($koneksi, $query_monitoring);

        if ($result_monitoring && mysqli_num_rows($result_monitoring) > 0) {
            $query_update_monitoring = "UPDATE monitoring_kas
                                       SET saldo_kas = saldo_kas - $jumlah_lama
                                       WHERE tanggal_monitoring = CURDATE()";
            if (!mysqli_query($koneksi, $query_update_monitoring)) {
                error_log("Error updating monitoring_kas: " . mysqli_error($koneksi));
            }
        }
    }
    // Jika periode atau jumlah berubah dan status masih Lunas
    elseif ($status === 'Lunas' && ($periode_lama !== $periode_baru || $jumlah_lama != $jumlah)) {
        // Kurangi dari periode lama
        if ($periode_lama !== $periode_baru) {
            $query_check_lama = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode_lama' AND Jenis_laporan = 'Bulanan'";
            $result_check_lama = mysqli_query($koneksi, $query_check_lama);

            if ($result_check_lama && mysqli_num_rows($result_check_lama) > 0) {
                $row = mysqli_fetch_assoc($result_check_lama);
                $id_laporan = $row['Id_laporan'];

                $query_update_laporan = "UPDATE laporan_kas
                                        SET Total_pemasukan = Total_pemasukan - $jumlah_lama,
                                            Saldo_akhir = Saldo_akhir - $jumlah_lama
                                        WHERE Id_laporan = $id_laporan";
                if (!mysqli_query($koneksi, $query_update_laporan)) {
                    error_log("Error updating laporan_kas: " . mysqli_error($koneksi));
                }
            }
        }

        // Tambah ke periode baru
        $query_check_baru = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode_baru' AND Jenis_laporan = 'Bulanan'";
        $result_check_baru = mysqli_query($koneksi, $query_check_baru);

        if ($result_check_baru && mysqli_num_rows($result_check_baru) > 0) {
            // Update laporan yang ada
            $row = mysqli_fetch_assoc($result_check_baru);
            $id_laporan = $row['Id_laporan'];

            $query_update_laporan = "UPDATE laporan_kas
                                    SET Total_pemasukan = Total_pemasukan + $jumlah,
                                        Saldo_akhir = Saldo_akhir + $jumlah
                                    WHERE Id_laporan = $id_laporan";
            if (!mysqli_query($koneksi, $query_update_laporan)) {
                error_log("Error updating laporan_kas: " . mysqli_error($koneksi));
            }
        } else {
            // Buat laporan baru
            $total_pengeluaran = 0;

            $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
                                 FROM pengeluaran_kegiatan
                                 WHERE MONTH(tanggal_pengeluaran) = $bulan
                                 AND YEAR(tanggal_pengeluaran) = $tahun";
            $result_peng = mysqli_query($koneksi, $query_pengeluaran);
            if ($result_peng && $row_peng = mysqli_fetch_assoc($result_peng)) {
                $total_pengeluaran = $row_peng['total'];
            }

            $saldo = $jumlah - $total_pengeluaran;

            $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                    VALUES ('Bulanan', '$periode_baru', $jumlah, $total_pengeluaran, $saldo)";
            if (mysqli_query($koneksi, $query_insert_laporan)) {
                $id_laporan = mysqli_insert_id($koneksi);
            } else {
                error_log("Error inserting laporan_kas: " . mysqli_error($koneksi));
                $id_laporan = 0;
            }
        }

        // Update monitoring_kas
        $query_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
        $result_monitoring = mysqli_query($koneksi, $query_monitoring);

        if ($result_monitoring && mysqli_num_rows($result_monitoring) > 0) {
            $query_update_monitoring = "UPDATE monitoring_kas
                                       SET saldo_kas = saldo_kas - $jumlah_lama + $jumlah,
                                           Id_laporan = $id_laporan
                                       WHERE tanggal_monitoring = CURDATE()";
        } else {
            $query_update_monitoring = "INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring, created_at)
                                       VALUES ($id_laporan, $jumlah, CURDATE(), NOW())";
        }

        if (!mysqli_query($koneksi, $query_update_monitoring)) {
            error_log("Error updating monitoring_kas: " . mysqli_error($koneksi));
        }
    }

    $_SESSION['success_message'] = 'Data iuran berhasil diperbarui. Status: ' . $status;
} else {
    $_SESSION['error_message'] = 'Gagal memperbarui data: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>