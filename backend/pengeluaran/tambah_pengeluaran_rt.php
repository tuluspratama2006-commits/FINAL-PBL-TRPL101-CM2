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
$tanggal_pengeluaran = mysqli_real_escape_string($koneksi, $_POST['tanggal_pengeluaran']);
$kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);
$deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
$jumlah_pengeluaran = mysqli_real_escape_string($koneksi, $_POST['jumlah_pengeluaran']);
$id_user = $_SESSION['id_user'];

// Validasi
if (empty($tanggal_pengeluaran)) {
    $_SESSION['error_message'] = 'Tanggal pengeluaran harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($kategori)) {
    $_SESSION['error_message'] = 'Kategori harus dipilih.';
    header("Location: $redirect_location");
    exit();
}

if (empty($deskripsi)) {
    $_SESSION['error_message'] = 'Deskripsi harus diisi.';
    header("Location: $redirect_location");
    exit();
}

if (empty($jumlah_pengeluaran) || !is_numeric($jumlah_pengeluaran) || $jumlah_pengeluaran <= 0) {
    $_SESSION['error_message'] = 'Jumlah pengeluaran harus berupa angka positif.';
    header("Location: $redirect_location");
    exit();
}

// Handle file upload
$bukti_path = null;
if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['bukti']['type'], $allowed_types)) {
        $_SESSION['error_message'] = 'Tipe file bukti tidak didukung. Gunakan JPG, PNG, atau PDF.';
        header("Location: $redirect_location");
        exit();
    }

    if ($_FILES['bukti']['size'] > $max_size) {
        $_SESSION['error_message'] = 'Ukuran file bukti maksimal 5MB.';
        header("Location: $redirect_location");
        exit();
    }

    $upload_dir = '../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION);
    $file_name = 'pengeluaran_' . time() . '_' . uniqid() . '.' . $file_extension;
    $bukti_path = 'uploads/' . $file_name;

    if (!move_uploaded_file($_FILES['bukti']['tmp_name'], $upload_dir . $file_name)) {
        $_SESSION['error_message'] = 'Gagal mengupload file bukti.';
        header("Location: $redirect_location");
        exit();
    }
}

// Insert data pengeluaran
$query = "INSERT INTO pengeluaran_kegiatan (
            tanggal_pengeluaran,
            kategori,
            deskripsi,
            jumlah_pengeluaran,
            bukti,
            id_user,
            created_at
            ) VALUES (
            '$tanggal_pengeluaran',
            '$kategori',
            '$deskripsi',
            '$jumlah_pengeluaran',
            " . ($bukti_path ? "'$bukti_path'" : "NULL") . ",
            '$id_user',
            NOW()
            )";

if (mysqli_query($koneksi, $query)) {
    $_SESSION['success_message'] = 'Pengeluaran berhasil ditambahkan.';

    // Update laporan_kas jika diperlukan
    $periode = date('n-Y', strtotime($tanggal_pengeluaran));

    // Cek apakah laporan sudah ada
    $query_check = "SELECT Id_laporan FROM laporan_kas WHERE Periode = '$periode' AND Jenis_laporan = 'Bulanan'";
    $result_check = mysqli_query($koneksi, $query_check);

    if(mysqli_num_rows($result_check) > 0) {
        // Update laporan yang ada
        $row = mysqli_fetch_assoc($result_check);
        $id_laporan = $row['Id_laporan'];

        $query_update_laporan = "UPDATE laporan_kas
                                SET Total_pengeluaran = Total_pengeluaran + $jumlah_pengeluaran,
                                    Saldo_akhir = Saldo_akhir - $jumlah_pengeluaran
                                WHERE Id_laporan = $id_laporan";
        mysqli_query($koneksi, $query_update_laporan);
    } else {
        // Buat laporan baru
        $total_pemasukan = 0;

        // Hitung pemasukan untuk periode ini
        $query_pemasukan = "SELECT COALESCE(SUM(jumlah_iuran), 0) as total
                           FROM iuran_rutin
                           WHERE bulan = MONTH('$tanggal_pengeluaran')
                           AND tahun = YEAR('$tanggal_pengeluaran')
                           AND status_pembayaran = 'Lunas'";
        $result_peng = mysqli_query($koneksi, $query_pemasukan);
        if($row_peng = mysqli_fetch_assoc($result_peng)) {
            $total_pemasukan = $row_peng['total'];
        }

        $saldo = $total_pemasukan - $jumlah_pengeluaran;

        $query_insert_laporan = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                                VALUES ('Bulanan', '$periode', $total_pemasukan, $jumlah_pengeluaran, $saldo)";
        mysqli_query($koneksi, $query_insert_laporan);
    }

} else {
    $_SESSION['error_message'] = 'Gagal menambahkan pengeluaran: ' . mysqli_error($koneksi);
}

header("Location: $redirect_location");
exit();
?>
