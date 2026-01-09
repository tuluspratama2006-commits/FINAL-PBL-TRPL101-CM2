<?php
session_start();
require_once '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../dashboard_rt/iuranrt.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login terlebih dahulu.';
    header("Location: ../../auth/login.php");
    exit();
}
$redirect_location = '../../dashboard_rt/iuranrt.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    $_SESSION['error_message'] = 'Akses ditolak. Silakan login sebagai RT.';
    header("Location: ../../auth/login.php");
    exit();
}

$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Cek apakah file diupload
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = 'Silakan pilih file CSV untuk diupload.';
    header("Location: $redirect_location");
    exit();
}

// Validasi ekstensi file
$file_name = $_FILES['csv_file']['name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if ($file_ext !== 'csv') {
    $_SESSION['error_message'] = 'Hanya file CSV yang diperbolehkan.';
    header("Location: $redirect_location");
    exit();
}

// Buka file CSV
$file = fopen($_FILES['csv_file']['tmp_name'], 'r');

// Skip header jika ada
fgetcsv($file);

$success_count = 0;
$error_count = 0;
$error_messages = [];

while (($row = fgetcsv($file)) !== FALSE) {
    // Format CSV: NIK, Nama, RT, Bulan, Tahun, Jumlah, Status, Tanggal Bayar
    if (count($row) >= 8) {
        $nik = mysqli_real_escape_string($koneksi, $row[0]);
        $nama = mysqli_real_escape_string($koneksi, $row[1]);
        $rt = mysqli_real_escape_string($koneksi, $row[2]);
        $bulan_nama = mysqli_real_escape_string($koneksi, $row[3]);
        $tahun = mysqli_real_escape_string($koneksi, $row[4]);
        $jumlah = mysqli_real_escape_string($koneksi, $row[5]);
        $status = mysqli_real_escape_string($koneksi, $row[6]);
        $tanggal_bayar = !empty($row[7]) ? mysqli_real_escape_string($koneksi, $row[7]) : null;
        
        // Cari id_warga berdasarkan NIK
        $query_warga = "SELECT w.id_warga 
                        FROM warga w 
                        JOIN user u ON w.id_warga = u.id_warga 
                        WHERE u.nik = '$nik' AND u.rt_number = '$rt'";
        $result_warga = mysqli_query($koneksi, $query_warga);
        
        if (mysqli_num_rows($result_warga) > 0) {
            $warga = mysqli_fetch_assoc($result_warga);
            $id_warga = $warga['id_warga'];
            
            // Konversi bulan dari nama ke angka
            $bulan_index = array_search($bulan_nama, $bulan_indo);
            if ($bulan_index !== false) {
                $bulan = $bulan_index + 1;
                
                // Cek apakah iuran sudah ada
                $check_query = "SELECT * FROM iuran_rutin 
                                WHERE id_warga = '$id_warga' 
                                AND bulan = '$bulan' 
                                AND tahun = '$tahun'";
                $check_result = mysqli_query($koneksi, $check_query);
                
                if (mysqli_num_rows($check_result) === 0) {
                    // Insert data baru
                    $insert_query = "INSERT INTO iuran_rutin (
                                        id_warga, 
                                        bulan, 
                                        tahun, 
                                        jumlah_iuran, 
                                        status_pembayaran, 
                                        tanggal_pembayaran
                                        ) VALUES (
                                        '$id_warga',
                                        '$bulan',
                                        '$tahun',
                                        '$jumlah',
                                        '$status',
                                        " . ($tanggal_bayar ? "'$tanggal_bayar'" : "NULL") . "
                                        )";
                    
                    if (mysqli_query($koneksi, $insert_query)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $error_messages[] = "Error untuk $nama (NIK: $nik): " . mysqli_error($koneksi);
                    }
                } else {
                    $error_count++;
                    $error_messages[] = "Iuran untuk $nama (NIK: $nik) bulan $bulan_nama $tahun sudah ada";
                }
            } else {
                $error_count++;
                $error_messages[] = "Bulan '$bulan_nama' tidak valid untuk $nama (NIK: $nik)";
            }
        } else {
            $error_count++;
            $error_messages[] = "Warga dengan NIK '$nik' di RT '$rt' tidak ditemukan";
        }
    }
}

fclose($file);

// Tampilkan pesan hasil
if ($success_count > 0) {
    $_SESSION['success_message'] = "Berhasil mengimport $success_count data iuran.";
    
    if ($error_count > 0) {
        $_SESSION['success_message'] .= " ($error_count data gagal)";
    }
} else {
    $_SESSION['error_message'] = "Gagal mengimport data. ";
    if (count($error_messages) > 0) {
        $_SESSION['error_message'] .= " Error: " . implode(", ", array_slice($error_messages, 0, 5));
        if (count($error_messages) > 5) {
            $_SESSION['error_message'] .= " dan " . (count($error_messages) - 5) . " error lainnya.";
        }
    }
}

header("Location: $redirect_location");
exit();
?>