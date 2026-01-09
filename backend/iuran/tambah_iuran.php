<?php
session_start();
require_once '../../config/koneksi.php';

if (!$koneksi) {
    $_SESSION['error_message'] = 'Koneksi database gagal: ' . mysqli_connect_error();
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai Bendahara.';
    header("Location: ../../auth/login.php");
    exit();
}

$redirect_location = '../../dashboard_bendahara/iuranbendahara.php';

$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Ambil data dari form
$id_warga = mysqli_real_escape_string($koneksi, $_POST['id_warga']);
$bulan_array = isset($_POST['bulan']) ? $_POST['bulan'] : [];
$jumlah_per_bulan = mysqli_real_escape_string($koneksi, $_POST['jumlah_per_bulan']);
$tahun = mysqli_real_escape_string($koneksi, $_POST['tahun']);
$tanggal_bayar = !empty($_POST['tanggal_bayar']) ? mysqli_real_escape_string($koneksi, $_POST['tanggal_bayar']) : null;

// Validasi
if (empty($id_warga)) {
    $_SESSION['error_message'] = 'Pilih warga terlebih dahulu.';
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}

if (empty($bulan_array)) {
    $_SESSION['error_message'] = 'Pilih minimal satu bulan.';
    header("Location: ../../dashboard_bendahara/iuranbendahara.php");
    exit();
}

// Konversi bulan dari nama ke angka
$bulan_angka = [];
foreach ($bulan_array as $bulan_nama) {
    $index = array_search($bulan_nama, $bulan_indo);
    if ($index !== false) {
        $bulan_angka[] = $index + 1; // +1 karena array dimulai dari 0
    }
}

// Cek apakah iuran sudah ada untuk bulan-bulan tersebut
foreach ($bulan_angka as $bulan) {
    $check_query = "SELECT * FROM iuran_rutin
                    WHERE id_warga = '$id_warga'
                    AND bulan = '$bulan'
                    AND tahun = '$tahun'";
    $check_result = mysqli_query($koneksi, $check_query);

    if (!$check_result) {
        $_SESSION['error_message'] = 'Error checking existing iuran: ' . mysqli_error($koneksi);
        header("Location: ../../dashboard_bendahara/iuranbendahara.php");
        exit();
    }

    if (mysqli_num_rows($check_result) > 0) {
        $bulan_nama = $bulan_indo[$bulan-1];
        $_SESSION['error_message'] = "Iuran untuk bulan $bulan_nama $tahun sudah ada.";
        header("Location: ../../dashboard_bendahara/iuranbendahara.php");
        exit();
    }
}

// Insert data iuran untuk setiap bulan
$success_count = 0;
$error_messages = [];

foreach ($bulan_angka as $bulan) {
    // Tentukan status berdasarkan tanggal bayar
    $status = $tanggal_bayar ? 'Lunas' : 'Belum Lunas';
    
    $query = "INSERT INTO iuran_rutin (
                id_warga, 
                bulan, 
                tahun, 
                jumlah_iuran, 
                tanggal_pembayaran, 
                status_pembayaran
                ) VALUES (
                '$id_warga',
                '$bulan',
                '$tahun',
                '$jumlah_per_bulan',
                " . ($tanggal_bayar ? "'$tanggal_bayar'" : "NULL") . ",
                '$status'
                )";
    
    if (mysqli_query($koneksi, $query)) {
        $success_count++;
    } else {
        $error_messages[] = "Error untuk bulan " . $bulan_indo[$bulan-1] . ": " . mysqli_error($koneksi);
    }
}

if ($success_count > 0) {
    $_SESSION['success_message'] = "Berhasil menambahkan $success_count data iuran.";

    // Update laporan_kas dan monitoring_kas jika ada iuran yang lunas
    if ($tanggal_bayar) {
        foreach ($bulan_angka as $bulan) {
            $periode = $bulan . '-' . $tahun;

            // Cek apakah laporan sudah ada
            $query_check = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
            $result_check = mysqli_query($koneksi, $query_check);

            if(mysqli_num_rows($result_check) > 0) {
                // Update laporan yang ada
                $row = mysqli_fetch_assoc($result_check);
                $id_laporan = $row['Id_laporan'];

                $query_update_laporan = "UPDATE laporan_kas
                                        SET Total_pemasukan = Total_pemasukan + $jumlah_per_bulan,
                                            Saldo_akhir = Saldo_akhir + $jumlah_per_bulan
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
                if($row_peng = mysqli_fetch_assoc($result_peng)) {
                    $total_pengeluaran = $row_peng['total'];
                }

                $saldo = $jumlah_per_bulan - $total_pengeluaran;

                $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                        VALUES ('Bulanan', '$periode', $jumlah_per_bulan, $total_pengeluaran, $saldo)";
                if (mysqli_query($koneksi, $query_insert_laporan)) {
                    $id_laporan = mysqli_insert_id($koneksi);
                } else {
                    error_log("Error inserting laporan_kas: " . mysqli_error($koneksi));
                    continue; // Skip monitoring update if laporan insert failed
                }
            }

            // Update monitoring_kas untuk hari ini
            $query_monitoring = "SELECT * FROM monitoring_kas WHERE tanggal_monitoring = CURDATE()";
            $result_monitoring = mysqli_query($koneksi, $query_monitoring);

            if(mysqli_num_rows($result_monitoring) > 0) {
                // Update existing
                $query_update_monitoring = "UPDATE monitoring_kas
                                           SET saldo_kas = saldo_kas + $jumlah_per_bulan,
                                               Id_laporan = $id_laporan
                                           WHERE tanggal_monitoring = CURDATE()";
            } else {
                // Insert new
                $query_update_monitoring = "INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring, created_at)
                                           VALUES ($id_laporan, $jumlah_per_bulan, CURDATE(), NOW())";
            }
            mysqli_query($koneksi, $query_update_monitoring);
        }
    }
} else {
    $_SESSION['error_message'] = "Gagal menambahkan iuran. " . implode(", ", $error_messages);
}

header("Location: ../../dashboard_bendahara/iuranbendahara.php");
exit();
?>