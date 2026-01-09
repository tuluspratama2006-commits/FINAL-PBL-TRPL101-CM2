<?php
// File: backend/pengeluaran/tambah_pengeluaran.php
session_start();
require_once '../../config/koneksi.php';

// Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    header("Location: ../../auth/login.php");
    exit();
}

// Fungsi untuk upload file
function uploadFile($file, $target_dir = "../../uploads/") {
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($file["name"]);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $new_file_name = time() . '_' . uniqid() . '.' . $file_type;
    $target_file = $target_dir . $new_file_name;
    
    if ($file["size"] > 5000000) {
        $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 5MB.";
        return false;
    }
    
    $allowed_types = array('jpg', 'jpeg', 'png', 'pdf');
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Hanya file JPG, JPEG, PNG, dan PDF yang diizinkan.";
        return false;
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $new_file_name;
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat mengupload file.";
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan sanitasi data
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $jumlah = mysqli_real_escape_string($koneksi, $_POST['jumlah']);
    $diajukan_oleh = mysqli_real_escape_string($koneksi, $_POST['diajukan_oleh']);
    
    // ========== SOLUSI: Ambil id_user ==========
    // Cara 1: Cek session dulu
    if (isset($_SESSION['id_user']) && !empty($_SESSION['id_user'])) {
        $id_user = $_SESSION['id_user'];
    } 
    // Cara 2: Jika tidak ada di session, cari berdasarkan username
    else {
        // PERBAIKAN: Cari berdasarkan username saja (karena tidak ada kolom nama)
        $query_cari_user = "SELECT id_user FROM user WHERE username = ? LIMIT 1";
        $stmt_cari = mysqli_prepare($koneksi, $query_cari_user);
        
        // Bind parameter username saja
        mysqli_stmt_bind_param($stmt_cari, "s", $diajukan_oleh);
        mysqli_stmt_execute($stmt_cari);
        mysqli_stmt_bind_result($stmt_cari, $found_id);
        
        if (mysqli_stmt_fetch($stmt_cari)) {
            $id_user = $found_id;
            $_SESSION['id_user'] = $id_user;
        } else {
            // Cara 3: Ambil user bendahara pertama
            $query_bendahara = "SELECT id_user FROM user WHERE role = 'Bendahara' ORDER BY id_user LIMIT 1";
            $result_bend = mysqli_query($koneksi, $query_bendahara);
            if ($row = mysqli_fetch_assoc($result_bend)) {
                $id_user = $row['id_user'];
                $_SESSION['id_user'] = $id_user;
            } else {
                // Cara 4: Default ke user pertama
                $query_first = "SELECT id_user FROM user ORDER BY id_user LIMIT 1";
                $result_first = mysqli_query($koneksi, $query_first);
                if ($row = mysqli_fetch_assoc($result_first)) {
                    $id_user = $row['id_user'];
                } else {
                    $id_user = 1; // Fallback
                }
                $_SESSION['id_user'] = $id_user;
            }
        }
        mysqli_stmt_close($stmt_cari);
    }
    
    // Validasi id_user
    if (empty($id_user) || $id_user <= 0) {
        $_SESSION['error'] = "Error: ID user tidak valid. Silakan login ulang.";
        header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
        exit();
    }
    
    // Validasi input lainnya
    if (empty($tanggal) || empty($kategori) || empty($deskripsi) || empty($jumlah)) {
        $_SESSION['error'] = "Semua field wajib diisi!";
        header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
        exit();
    }
    
    if (!is_numeric($jumlah) || $jumlah <= 0) {
        $_SESSION['error'] = "Jumlah harus berupa angka positif!";
        header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
        exit();
    }
    
    // Handle upload bukti
    $bukti_file = null;
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
        $upload_result = uploadFile($_FILES['bukti']);
        if ($upload_result !== false) {
            $bukti_file = $upload_result;
        }
    }
    
    $query = "INSERT INTO pengeluaran_kegiatan (tanggal_pengeluaran, kategori, deskripsi, jumlah_pengeluaran, id_user, bukti, created_at, diajukan_oleh)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($koneksi, $query);

    mysqli_stmt_bind_param($stmt, "ssssiss",  
    $tanggal,
    $kategori,
    $deskripsi,
    $jumlah,
    $id_user,
    $bukti_file,
    $diajukan_oleh  
);
    
    // Eksekusi query
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Data pengeluaran berhasil ditambahkan!";
    } else {
        $error_msg = mysqli_error($koneksi);
        $_SESSION['error'] = "Gagal menambahkan data: " . $error_msg;
    }
    
    mysqli_stmt_close($stmt);
    
    // Redirect
    header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
    exit();
} else {
    header("Location: ../../dashboard_bendahara/bendahara.php?page=pengeluaran");
    exit();
}
?>