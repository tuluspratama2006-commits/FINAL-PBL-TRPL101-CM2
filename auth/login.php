<?php
session_start();
include '../config/koneksi.php';
$message = '';

// Debug: Aktifkan untuk melihat error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek jika sudah login, redirect ke dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
        switch ($role) {
            case 'RT':
                header("Location: ../dashboard_rt/rt.php");
                exit();
            case 'Bendahara':
                header("Location: ../dashboard_bendahara/bendahara.php");
                exit();
            case 'warga':
                header("Location: ../dashboard_warga/warga.php");
                exit();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nik = isset($_POST['nik']) ? trim($_POST['nik']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    
    // Konversi role ke lowercase untuk konsistensi dengan database
    $role = strtolower($role);
    if ($role === 'ketua rt') {
        $role = 'RT';
    } elseif ($role === 'bendahara') {
        $role = 'Bendahara';
    } elseif ($role === 'warga') {
        $role = 'warga';
    }
    
    if (empty($nik) || empty($password) || empty($role)) {
        $message = '<div class="alert alert-danger">Semua field harus diisi!</div>';
    } elseif (strlen($nik) !== 16 || !is_numeric($nik)) {
        $message = '<div class="alert alert-danger">NIK harus 16 digit angka!</div>';
    } else {
        // Query sesuai tabel user dengan kolom rt_number
        $stmt = mysqli_prepare($koneksi, "SELECT id_user, username, nik, rt_number, password, role, id_warga FROM user WHERE nik = ? AND role = ? LIMIT 1");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $nik, $role);
            
            if (!mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-danger">Error query: ' . mysqli_error($koneksi) . '</div>';
            } else {
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 1) {
                    $user = mysqli_fetch_assoc($result);
                    
                    // Debug: Lihat data user
                    echo "<script>console.log('User Data:', " . json_encode($user) . ");</script>";
                    
                    if (password_verify($password, $user['password'])) {
                        // Set session
                        $_SESSION['id_user'] = $user['id_user'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nik'] = $user['nik'];
                        $_SESSION['rt_number'] = $user['rt_number'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['id_warga'] = $user['id_warga'];
                        $_SESSION['logged_in'] = true;
                        
                        // Debug: Cek session
                        echo "<script>console.log('Session Role:', '" . $user['role'] . "');</script>";
                        
                        // Pastikan session disimpan
                        session_write_close();
                        
                        // Redirect berdasarkan role - SESUAIKAN DENGAN STRUKTUR FOLDER ANDA
                        $redirect_url = "";
                        switch ($user['role']) {
                            case 'RT':
                                $redirect_url = '../dashboard_rt/rt.php';
                                break;
                            case 'Bendahara':
                                $redirect_url = '../dashboard_bendahara/bendahara.php';
                                break;
                            case 'warga':
                                $redirect_url = '../dashboard_warga/warga.php';
                                break;
                            default:
                                $redirect_url = '../index.php';
                        }
                        
                        // Debug: Tampilkan redirect URL
                        echo "<script>console.log('Redirect URL:', '" . $redirect_url . "');</script>";
                        
                        // Gunakan header redirect dulu
                        if (!headers_sent()) {
                            header("Location: " . $redirect_url);
                            exit();
                        } else {
                            echo "<script>
                                alert('Login berhasil!');
                                window.location.href = '" . $redirect_url . "';
                            </script>";
                            exit();
                        }
                        
                    } else {
                        $message = '<div class="alert alert-danger">Password salah!</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">NIK atau role tidak ditemukan! Cek kembali NIK dan role Anda.</div>';
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = '<div class="alert alert-danger">Error dalam query: ' . mysqli_error($koneksi) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Keuangan RT Digital</title>
    <style>
        /* CSS Anda tetap sama */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #00256B;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: #00256B;
            padding: 30px;
            text-align: center;
        }
        
        .logo {
            width: 80px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .card-header h1 {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .card-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #00256B;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 37, 107, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #00256B;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 37, 107, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #00256B;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: #001a4d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 37, 107, 0.3);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .register-link a {
            color: #00256B;
            font-weight: 600;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                <img src="../img/akurad1.png" alt="Logo" class="logo">
                <h1>Login System</h1>
                <p>Sistem Keuangan RT Digital</p>
            </div>
            
            <div class="card-body">
                <!-- Menampilkan pesan -->
                <?php 
                // Tampilkan pesan sukses dari registrasi
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
                    unset($_SESSION['success_message']);
                }
                
                // Tampilkan pesan error
                if (!empty($message)) {
                    echo $message;
                }
                ?>
                
                <form method="POST" action="" id="loginForm">
                    <!-- NIK -->
                    <div class="form-group">
                        <label for="nik" class="form-label">NIK (16 digit)</label>
                        <input type="text" id="nik" name="nik" class="form-control" 
                               placeholder="Masukkan 16 digit NIK" required maxlength="16"
                               value="<?php echo isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : ''; ?>">
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Masukkan password" required>
                    </div>
                    
                    <!-- Role - PERHATIKAN: value harus sama dengan database -->
                    <div class="form-group">
                        <label for="role" class="form-label">Role / Peran</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Role --</option>
                            <option value="warga" <?php echo (isset($_POST['role']) && $_POST['role'] == 'warga') ? 'selected' : ''; ?>>Warga</option>
                            <option value="Bendahara" <?php echo (isset($_POST['role']) && $_POST['role'] == 'Bendahara') ? 'selected' : ''; ?>>Bendahara</option>
                            <option value="RT" <?php echo (isset($_POST['role']) && $_POST['role'] == 'RT') ? 'selected' : ''; ?>>Ketua RT</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-login" id="submitBtn">
                        Login
                    </button>
                </form>
                
                <div class="register-link">
                    Belum punya akun? <a href="daftar.php">Daftar di sini</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const nikInput = document.getElementById('nik');
            const roleSelect = document.getElementById('role');
            
            // Validasi NIK hanya angka
            nikInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 16) {
                    this.value = this.value.slice(0, 16);
                }
            });
            
            // Tampilkan pesan jika ada di URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                alert('Login gagal: ' + urlParams.get('error'));
            }
            
            // Auto-focus pada input NIK
            nikInput.focus();
            
            // Debug info
            console.log('Login form loaded');
        });
    </script>
</body>
</html>