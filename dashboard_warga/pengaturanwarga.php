<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

// Cek apakah role adalah warga
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit();
}

// Proses form update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama_baru = $_POST['nama_lengkap'] ?? '';
    $email_baru = $_POST['email'] ?? '';
    $no_telepon_baru = $_POST['no_telepon'] ?? '';

    // Include database connection
    include '../config/koneksi.php';

    // Get user ID from session
    $id_user = $_SESSION['id_user'] ?? null;

    if ($id_user) {
        // Start transaction (using autocommit for compatibility)
        mysqli_autocommit($koneksi, false);

        try {
            // First, get the id_warga from user table
            $query_get_warga = "SELECT id_warga FROM user WHERE id_user = '$id_user'";
            $result = mysqli_query($koneksi, $query_get_warga);
            if (!$result) {
                throw new Exception("Gagal mendapatkan id_warga: " . mysqli_error($koneksi));
            }

            $row = mysqli_fetch_assoc($result);
            $id_warga = $row['id_warga'] ?? null;

            if (!$id_warga) {
                throw new Exception("ID warga tidak ditemukan untuk user ini");
            }

            // Update user table for email
            $query_update_user = "UPDATE user SET email = '$email_baru' WHERE id_user = '$id_user'";
            if (!mysqli_query($koneksi, $query_update_user)) {
                throw new Exception("Gagal update email: " . mysqli_error($koneksi));
            }

            // Update warga table for nama_lengkap and no_telepon
            $query_update_warga = "UPDATE warga SET
                                  nama_lengkap = '$nama_baru',
                                  no_telepon = '$no_telepon_baru',
                                  updated_at = NOW()
                                  WHERE id_warga = '$id_warga'";
            if (!mysqli_query($koneksi, $query_update_warga)) {
                throw new Exception("Gagal update profil warga: " . mysqli_error($koneksi));
            }

            // Commit transaction
            mysqli_commit($koneksi);
            mysqli_autocommit($koneksi, true);

            // Update session
            $_SESSION['nama'] = $nama_baru;
            $_SESSION['email'] = $email_baru;
            $_SESSION['no_telepon'] = $no_telepon_baru;

            $pesan_sukses = "Profil berhasil diperbarui!";
            // Redirect to refresh the page and update sidebar
            header("Location: pengaturanwarga.php?success=1");
            exit();

        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($koneksi);
            mysqli_autocommit($koneksi, true);
            $error_profil = "Gagal memperbarui profil: " . $e->getMessage();
        }
    } else {
        $error_profil = "ID user tidak ditemukan dalam session!";
    }
}

// Proses form update password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // Include database connection
    include '../config/koneksi.php';

    // Get user ID from session
    $id_user = $_SESSION['id_user'] ?? null;

    if (!$id_user) {
        $error_password = "Session tidak valid!";
    } elseif (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error_password = "Semua field password harus diisi!";
    } elseif (strlen($password_baru) < 8) {
        $error_password = "Password baru minimal 8 karakter!";
    } elseif ($password_baru !== $konfirmasi_password) {
        $error_password = "Password baru dan konfirmasi password tidak cocok!";
    } else {
        // Get current password from database
        $query_get_password = "SELECT password FROM user WHERE id_user = '$id_user'";
        $result = mysqli_query($koneksi, $query_get_password);

        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            $current_password_hash = $user_data['password'];

            // Verify old password
            if (!password_verify($password_lama, $current_password_hash)) {
                $error_password = "Password lama tidak sesuai!";
            } else {
                // Hash new password
                $new_password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

                // Update password in database
                $query_update_password = "UPDATE user SET password = '$new_password_hash' WHERE id_user = '$id_user'";
                if (mysqli_query($koneksi, $query_update_password)) {
                    $pesan_sukses_password = "Password berhasil diperbarui!";
                } else {
                    $error_password = "Gagal memperbarui password: " . mysqli_error($koneksi);
                }
            }
        } else {
            $error_password = "User tidak ditemukan!";
        }
    }
}

// Proses form notifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifikasi'])) {
    $email_notifikasi = isset($_POST['email_notifikasi']) ? 1 : 0;
    $notifikasi_tagihan = isset($_POST['notifikasi_tagihan']) ? 1 : 0;
    $laporan_bulanan = isset($_POST['laporan_bulanan']) ? 1 : 0;

    // Include database connection
    include '../config/koneksi.php';

    // Get user ID from session
    $id_user = $_SESSION['id_user'] ?? null;

    if ($id_user) {
        // Check if preferences exist
        $query_check = "SELECT id_user FROM user_notification_preferences WHERE id_user = '$id_user'";
        $result_check = mysqli_query($koneksi, $query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // Update existing preferences
            $query_update = "UPDATE user_notification_preferences SET
                            email_notifications = '$email_notifikasi',
                            tagihan_notifications = '$notifikasi_tagihan',
                            laporan_bulanan = '$laporan_bulanan',
                            updated_at = NOW()
                            WHERE id_user = '$id_user'";
            $result = mysqli_query($koneksi, $query_update);
        } else {
            // Insert new preferences
            $query_insert = "INSERT INTO user_notification_preferences
                            (id_user, email_notifications, tagihan_notifications, laporan_bulanan, created_at, updated_at)
                            VALUES ('$id_user', '$email_notifikasi', '$notifikasi_tagihan', '$laporan_bulanan', NOW(), NOW())";
            $result = mysqli_query($koneksi, $query_insert);
        }

        if ($result) {
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // Return JSON response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Preferensi notifikasi berhasil diperbarui!']);
                exit();
            } else {
                $pesan_sukses_notifikasi = "Preferensi notifikasi berhasil diperbarui!";
            }
        } else {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui preferensi notifikasi!']);
                exit();
            } else {
                $error_notifikasi = "Gagal memperbarui preferensi notifikasi!";
            }
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session tidak valid!']);
            exit();
        } else {
            $error_notifikasi = "Session tidak valid!";
        }
    }
}

// Include database connection for notification preferences
include '../config/koneksi.php';

// Load notification preferences from database
$preferensi = [
    'email_notif' => true,
    'tagihan_notif' => true,
    'laporan_notif' => false
];

$id_user = $_SESSION['id_user'] ?? null;
if ($id_user) {
    $query_prefs = "SELECT email_notifications, tagihan_notifications, laporan_bulanan
                    FROM user_notification_preferences
                    WHERE id_user = '$id_user'";
    $result_prefs = mysqli_query($koneksi, $query_prefs);
    if ($result_prefs && mysqli_num_rows($result_prefs) > 0) {
        $row_prefs = mysqli_fetch_assoc($result_prefs);
        $preferensi = [
            'email_notif' => (bool)$row_prefs['email_notifications'],
            'tagihan_notif' => (bool)$row_prefs['tagihan_notifications'],
            'laporan_notif' => (bool)$row_prefs['laporan_bulanan']
        ];
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $pesan_sukses = "Profil berhasil diperbarui!";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Warga - Aplikasi Web Keuangan RT/RW Digital</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
    /* Reset margin main content untuk sidebar */
    body{background:#f5f7fb}

    /* Main content adjustment */
    .main-content {
        margin-left: 0; /* Default: sidebar hidden */
        margin-top: 60px; /* Space untuk top bar */
        padding: 28px;
        min-height: calc(100vh - 60px);
        transition: margin-left 0.3s ease;
    }

    /* Ketika sidebar terbuka di desktop */
    @media (min-width: 769px) {
        .main-content.sidebar-open {
            margin-left: 260px;
        }
    }

    /* Style untuk halaman dashboard */
    .card-box{
        background:#fff;border-radius:10px;padding:15px;
        box-shadow:0 2px 6px rgba(0,0,0,.08)
    }

    .info-value{font-size:1.3rem;font-weight:700}
    .badge-up{background:#dcfce7;color:#166534}

    .rt-item{border-bottom:1px dashed #e5e7eb;padding:10px 0}
    .rt-item:last-child{border-bottom:none}

    .quick-btn{
        height:90px;font-weight:600;
        display:flex;flex-direction:column;
        justify-content:center;align-items:center
    }

    /* Topbar (untuk desktop) */
    .topbar {
        display: none;
    }

    /* Responsive */
    @media (min-width: 769px) {
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.sidebar-open .topbar {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 20px;
            margin-top: 60px;
        }
    }
    
    /* Container utama */
    .settings-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 30px;
    }

    .page-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
    }

    .page-header h1 {
        color: #1a1a1a;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .subtitle {
        font-size: 1rem;
        color: #666;
        margin-bottom: 5px;
    }

    /* Card pengaturan */
    .setting-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }

    .setting-card h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4f46e5;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .setting-card h2 i {
        color: #4f46e5;
    }

    /* Form groups */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #444;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        transition: border 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    }

    .readonly-field {
        background-color: #f8f9fa;
        color: #6c757d;
        cursor: not-allowed;
    }

    /* Switch toggle */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
        margin-right: 10px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #28a745;
    }

    input:focus + .slider {
        box-shadow: 0 0 1px #28a745;
    }

    input:checked + .slider:before {
        transform: translateX(30px);
    }

    /* Notification item */
    .notification-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-text {
        flex: 1;
    }

    .notification-text h4 {
        font-weight: 500;
        margin-bottom: 5px;
    }

    .notification-text p {
        font-size: 14px;
        color: #666;
    }

    /* Buttons */
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background-color: #072f66;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0b3f77;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(7, 47, 102, 0.2);
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
        transform: translateY(-2px);
    }

    /* Alert messages */
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Responsive untuk pengaturan */
    @media (max-width: 576px) {
        .form-group {
            margin-bottom: 15px;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }

        .notification-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .main-content {
            padding: 20px 15px;
        }
    }
</style>
</head>
<body>

<!-- Include Sidebar -->
<?php include 'sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Top Bar -->
    <div class="topbar">
        <div>
            <h5 class="fw-bold mb-0">Pengaturan Warga</h5>
            <small class="text-muted">Kelola profil dan preferensi Anda</small>
        </div>
    </div>
    <!-- Main Container -->
    <div class="settings-container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="bi bi-gear"></i> Pengaturan</h1>
            <div class="subtitle">
                Kelola preferensi dan pengaturan akun Anda
            </div>
        </div>
        
        <!-- Card Profil -->
        <div class="setting-card">
            <h2><i class="bi bi-person-circle"></i> Profil</h2>
            <p class="mb-4">Update informasi profil Anda</p>
            
            <?php if (isset($pesan_sukses)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $pesan_sukses; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_profil)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?php echo $error_profil; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control"
                           value="<?php echo htmlspecialchars($_SESSION['nama'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="no_telepon">No. Telepon</label>
                    <input type="text" id="no_telepon" name="no_telepon" class="form-control"
                           value="<?php echo htmlspecialchars($_SESSION['no_telepon'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="nik">NIK</label>
                    <input type="text" id="nik" name="nik" class="form-control readonly-field"
                           value="<?php echo htmlspecialchars($_SESSION['nik'] ?? ''); ?>" readonly>
                    <small style="color: #666; display: block; margin-top: 5px;">NIK tidak dapat diubah</small>
                </div>

                <div class="form-group">
                    <label for="rt">RT/RW</label>
                    <input type="text" id="rt" name="rt" class="form-control readonly-field"
                           value="<?php echo htmlspecialchars($_SESSION['rt'] ?? ''); ?>" readonly>
                    <small style="color: #666; display: block; margin-top: 5px;">RT/RW tidak dapat diubah</small>
                </div>
                
                <button type="submit" name="update_profil" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        
        <!-- Card Notifikasi -->
        <div class="setting-card">
            <h2><i class="bi bi-bell"></i> Notifikasi</h2>
            <p class="mb-4">Atur preferensi notifikasi</p>

            <!-- Alert div for AJAX feedback -->
            <div id="notification-alert" class="alert" style="display: none;"></div>

            <form id="notificationForm" method="POST" action="">
                <div class="notification-item">
                    <div class="notification-text">
                        <h4>Email Notifikasi</h4>
                        <p>Terima email untuk update penting</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="email_notifikasi"
                               <?php echo $preferensi['email_notif'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="notification-item">
                    <div class="notification-text">
                        <h4>Notifikasi Tagihan</h4>
                        <p>Pengingat iuran yang belum dibayar</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="notifikasi_tagihan"
                               <?php echo $preferensi['tagihan_notif'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="notification-item">
                    <div class="notification-text">
                        <h4>Laporan Bulanan</h4>
                        <p>Ringkasan keuangan setiap bulan</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="laporan_bulanan"
                               <?php echo $preferensi['laporan_notif'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" name="update_notifikasi" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Preferensi Notifikasi
                </button>
            </form>
        </div>
        
        <!-- Card Keamanan -->
        <div class="setting-card">
            <h2><i class="bi bi-shield-lock"></i> Keamanan</h2>
            <p class="mb-4">Kelola password dan keamanan akun</p>
            
            <?php if (isset($pesan_sukses_password)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $pesan_sukses_password; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_password)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?php echo $error_password; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password_lama">Password Lama</label>
                    <input type="password" id="password_lama" name="password_lama" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password_baru">Password Baru</label>
                    <input type="password" id="password_baru" name="password_baru" class="form-control" required>
                    <small style="color: #666; display: block; margin-top: 5px;">Minimal 8 karakter</small>
                </div>
                
                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" required>
                </div>
                
                <button type="submit" name="update_password" class="btn btn-success">
                    <i class="bi bi-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById('notificationForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default form submission

    const formData = new FormData(this);
    formData.append('update_notifikasi', '1'); // Add the submit button value

    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
    submitBtn.disabled = true;

    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        const alertDiv = document.getElementById('notification-alert');

        if (data.success) {
            alertDiv.className = 'alert alert-success';
            alertDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + data.message;
        }

        alertDiv.style.display = 'block';

        // Hide alert after 5 seconds
        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    })
    .catch(error => {
        console.error('Error:', error);
        const alertDiv = document.getElementById('notification-alert');
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> Terjadi kesalahan saat menyimpan data!';
        alertDiv.style.display = 'block';

        setTimeout(() => {
            alertDiv.style.display = 'none';
        }, 5000);
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});
</script>
</body>
</html>
