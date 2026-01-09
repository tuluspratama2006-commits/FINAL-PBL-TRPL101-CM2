<?php
session_start();
include '../config/koneksi.php';
$message = '';

// Inisialisasi semua variabel untuk form
$username = $nama_lengkap = $nik = $rt_number = $role = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $nama_lengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $nik = isset($_POST['nik']) ? trim($_POST['nik']) : '';
    $rt_number = isset($_POST['rt_number']) ? trim($_POST['rt_number']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validasi
    if (empty($username) || empty($nama_lengkap) || empty($nik) || empty($rt_number) || empty($role) || empty($password)) {
        $message = '<div class="alert alert-danger">Semua field wajib harus diisi!</div>';
    } elseif ($password !== $confirm_password) {
        $message = '<div class="alert alert-danger">Konfirmasi password tidak cocok!</div>';
    } elseif (strlen($nik) !== 16 || !is_numeric($nik)) {
        $message = '<div class="alert alert-danger">NIK harus 16 digit angka!</div>';
    } elseif (strlen($password) < 8) {
        $message = '<div class="alert alert-danger">Password minimal 8 karakter!</div>';
    } else {
        // Mulai transaksi
        mysqli_begin_transaction($koneksi);
        
        try {
            // Cek apakah NIK sudah terdaftar di user
            $check_nik = mysqli_prepare($koneksi, "SELECT id_user FROM user WHERE nik = ?");
            mysqli_stmt_bind_param($check_nik, "s", $nik);
            mysqli_stmt_execute($check_nik);
            mysqli_stmt_store_result($check_nik);
            
            if (mysqli_stmt_num_rows($check_nik) > 0) {
                throw new Exception("NIK sudah terdaftar!");
            }
            mysqli_stmt_close($check_nik);
            
            // Cek apakah username sudah terdaftar
            $check_username = mysqli_prepare($koneksi, "SELECT id_user FROM user WHERE username = ?");
            mysqli_stmt_bind_param($check_username, "s", $username);
            mysqli_stmt_execute($check_username);
            mysqli_stmt_store_result($check_username);
            
            if (mysqli_stmt_num_rows($check_username) > 0) {
                throw new Exception("Username sudah terdaftar!");
            }
            mysqli_stmt_close($check_username);
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            
            // **STEP 1: Insert data ke tabel warga terlebih dahulu**
            $stmt_warga = mysqli_prepare($koneksi, "INSERT INTO warga (nama_lengkap, alamat, no_telepon, created_at, updated_at) VALUES (?, '', '', ?, ?)");
            
            mysqli_stmt_bind_param($stmt_warga, "sss", $nama_lengkap, $created_at, $created_at);
            
            if (!mysqli_stmt_execute($stmt_warga)) {
                throw new Exception("Gagal menyimpan data warga: " . mysqli_error($koneksi));
            }
            
            // Dapatkan ID warga yang baru dibuat
            $id_warga = mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmt_warga);
            
            // **STEP 2: Insert data ke tabel user dengan id_warga yang valid**
            $stmt_user = mysqli_prepare($koneksi, "INSERT INTO user (username, nik, rt_number, password, role, id_warga, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_user, "sssssis", $username, $nik, $rt_number, $password_hash, $role, $id_warga, $created_at);
            
            if (!mysqli_stmt_execute($stmt_user)) {
                throw new Exception("Gagal menyimpan data user: " . mysqli_error($koneksi));
            }
            
            mysqli_stmt_close($stmt_user);
            
            // Commit transaksi
            mysqli_commit($koneksi);
            
            $_SESSION['success_message'] = "Registrasi Berhasil! Silakan login.";
            header("Location: login.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            mysqli_rollback($koneksi);
            $message = '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun Baru - Keuangan RT/RW Digital</title>
    <style>
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
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            padding: 0 20px;
        }
        
        .page-title {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
        }
        
        .page-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
            margin: 0 auto;
        }
        
        .card-body {
            padding: 40px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
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
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-kembali {
            background: #f7fafc;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-kembali:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-daftar {
            background: #00256B;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-daftar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            color: white;
            font-size: 1rem;
        }
        
        .login-link a {
            color: white;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 2px;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            border-bottom-color: white;
        }
        
        .password-hint {
            display: block;
            margin-top: 5px;
            color: #718096;
            font-size: 0.85rem;
        }
        
        .form-note {
            font-size: 0.85rem;
            color: #718096;
            font-style: italic;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Halaman -->
        <div class="page-header">
            <img src="../img/akurad1.png" alt="#" width="100" height="auto">
            <h1 class="page-title">Buat Akun Baru</h1>
            <p class="page-subtitle">Daftar untuk mengakses sistem keuangan RT/RW Digital</p>
        </div>
        
        <!-- Card Form Registrasi -->
        <div class="register-card">
            <div class="card-body">
                <!-- Menampilkan pesan -->
                <?php 
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
                    unset($_SESSION['success_message']);
                }
                
                if (!empty($message)) {
                    echo $message;
                }
                ?>
                
                <form method="POST" action="">
                    <!-- Username dan Nama Lengkap -->
                    <div class="row">
                        <div class="form-group">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   placeholder="Masukkan username" required
                                   value="<?php echo htmlspecialchars($username); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap *</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" 
                                   placeholder="Masukkan nama lengkap" required
                                   value="<?php echo htmlspecialchars($nama_lengkap); ?>">
                        </div>
                    </div>
                    
                    <!-- NIK dan RT -->
                    <div class="row">
                        <div class="form-group">
                            <label for="nik" class="form-label">NIK (16 digit) *</label>
                            <input type="text" id="nik" name="nik" class="form-control" 
                                   placeholder="Masukkan 16 digit NIK" required maxlength="16"
                                   value="<?php echo htmlspecialchars($nik); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="rt_number" class="form-label">Nomor RT *</label>
                            <select id="rt_number" name="rt_number" class="form-select" required>
                                <option value="" disabled selected>-- Pilih RT --</option>
                                <option value="001" <?php echo ($rt_number == '001') ? 'selected' : ''; ?>>RT 001</option>
                                <option value="002" <?php echo ($rt_number == '002') ? 'selected' : ''; ?>>RT 002</option>
                                <option value="003" <?php echo ($rt_number == '003') ? 'selected' : ''; ?>>RT 003</option>
                                <option value="004" <?php echo ($rt_number == '004') ? 'selected' : ''; ?>>RT 004</option>
                                <option value="005" <?php echo ($rt_number == '005') ? 'selected' : ''; ?>>RT 005</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Role -->
                    <div class="form-group">
                        <label for="role" class="form-label">Role / Peran *</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Peran --</option>
                            <option value="Warga" <?php echo ($role == 'Warga') ? 'selected' : ''; ?>>Warga</option>
                            <option value="Bendahara" <?php echo ($role == 'Bendahara') ? 'selected' : ''; ?>>Bendahara</option>
                            <option value="RT" <?php echo ($role == 'RT') ? 'selected' : ''; ?>>Ketua RT</option>
                        </select>
                    </div>
                    
                    <!-- Password -->
                    <div class="row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Minimal 8 karakter" required minlength="8">
                            <span class="password-hint">Gunakan kombinasi huruf, angka, dan simbol</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Konfirmasi Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Ketik ulang password" required>
                        </div>
                    </div>
                    
                    <p class="form-note">* Menandakan field wajib diisi</p>
                    
                    <!-- Tombol -->
                    <div class="btn-group">
                        <a href="login.php" class="btn btn-kembali">Kembali ke Login</a>
                        <button type="submit" class="btn btn-daftar" id="submitBtn">Buat Akun Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Link ke Halaman Login -->
        <div class="login-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.querySelector('form');
            const submitBtn = document.getElementById('submitBtn');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Password tidak cocok');
                    confirmPassword.style.borderColor = '#fc8181';
                    return false;
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.style.borderColor = '#e1e5e9';
                    return true;
                }
            }
            
            password.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
            
            // Validasi NIK hanya angka
            const nikInput = document.getElementById('nik');
            nikInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length > 16) {
                    this.value = this.value.slice(0, 16);
                }
            });
            
            // Animasi submit button
            form.addEventListener('submit', function(e) {
                const originalText = submitBtn.innerHTML;
                
                // Validasi
                const nik = nikInput.value;
                if (nik.length !== 16) {
                    e.preventDefault();
                    alert('NIK harus 16 digit!');
                    nikInput.focus();
                    return;
                }
                
                if (!validatePassword()) {
                    e.preventDefault();
                    alert('Password dan konfirmasi password tidak cocok!');
                    password.focus();
                    return;
                }
                
                // Tampilkan loading
                submitBtn.innerHTML = 'Mendaftarkan...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>