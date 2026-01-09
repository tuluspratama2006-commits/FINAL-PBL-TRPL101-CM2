<?php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKURAD.APP - Solusi Digital Administrasi Keuangan RT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bs-primary: #0d47a1;
            --bs-secondary: #1a237e;
            --bs-success: #4caf50;
            --bs-info: #2196f3;
            --bs-warning: #ffc107;
        }
        
        .bg-primary { background-color: var(--bs-primary) !important; }
        
        .navbar-brand .logo-icon { font-size: 1.5rem; margin-right: 5px; }
        
        .custom-divider {
            width: 50px;
            height: 3px;
            background-color: #ffc107; 
            margin: 2rem auto;
        }

        .feature-box {
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            min-height: 280px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s;
        }
        
        .feature-box:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .feature-icon-container {
            display: inline-block;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            background-color: rgba(13, 71, 161, 0.1);
        }
        
        .feature-icon-container i, .feature-icon-container svg {
            font-size: 2rem;
        }
        
        .feature-list {
            list-style: none;
            padding-left: 0;
            margin-top: 15px;
        }
        
        .feature-list li {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 5px;
        }
        
        .feature-list li::before {
            content: "\2713";
            color: var(--bs-primary);
            font-weight: bold;
            display: inline-block; 
            width: 1em;
            margin-left: -1em;
        }
    </style>
</head>
<body>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary px-lg-5">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="home.php"><span class="logo-icon"><img src="../img/akurad1.png" alt="#" width="100" height="auto"></span>
                    
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="home.php">Home</a> 
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about_us.php">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fitur.php">Main Features</a> 
                        </li>
                    </ul>
                    <a href="../auth/login.php" class="btn btn-light fw-bold">Login</a>
                </div>
            </div>
        </nav>
    </header>

    <section class="container py-5">
        <div class="row align-items-center">
            <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-5 fw-bold mb-3" style="color: var(--bs-secondary);">selamat datang di AKURAD</h1>
                <p class="lead text-muted mb-4">Aplikasi keuangan RT berbasis web yang membantu pengelolaan iuran dan kas warga jadi lebih rapi, mudah, dan transparan.</p>
                <a href="../auth/login.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Mulai Sekarang
                </a>
            </div>
            <div class="col-12 col-lg-6">
                <img src="../img/dashboard.png" class="img-fluid rounded shadow" alt="Dashboard AKURAD">
            </div>
        </div>
    </section>

    <section class="container text-center py-5 bg-light">
        <h2 class="mb-3 mb-md-4" style="color: var(--bs-secondary);">Kenapa AKURAD Dibuat?</h2>
        <p class="mb-4 mb-md-5 px-2 px-md-0">AKURAD membantu bendahara mencatat iuran warga, mengelola pengeluaran, dan menyajikan laporan kas secara otomatis.</p>
        
        <div class="row justify-content-center g-4 mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-container" style="background-color: rgba(4, 180, 49, 0.1);">
                        <i class="bi bi-check-circle-fill" style="color: #4CAF50;"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--bs-secondary);">Mudah Digunakan</h4>
                    <p class="text-muted">Antarmuka sederhana dan intuitif untuk semua usia</p>
                    <ul class="feature-list">
                        <li>Navigasi yang jelas</li>
                        <li>Tampilan yang user-friendly</li>
                        <li>Tutorial step-by-step</li>
                    </ul>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-container" style="background-color: rgba(255, 193, 7, 0.1);">
                        <i class="bi bi-graph-up" style="color: var(--bs-warning);"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--bs-secondary);">Transparan Real-time</h4>
                    <p class="text-muted">Data keuangan dapat diakses warga kapan saja</p>
                    <ul class="feature-list">
                        <li>Update data otomatis</li>
                        <li>Dashboard real-time</li>
                        <li>Akses dari berbagai device</li>
                    </ul>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-container" style="background-color: rgba(13, 71, 161, 0.1);">
                        <i class="bi bi-shield-lock-fill" style="color: var(--bs-primary);"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--bs-secondary);">Data Aman</h4>
                    <p class="text-muted">Sistem keamanan terenkripsi untuk data sensitif</p>
                    <ul class="feature-list">
                        <li>Enkripsi data</li>
                        <li>Backup otomatis</li>
                        <li>Keamanan berlapis</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold" style="color: var(--bs-secondary);">Fitur Utama AKURAD</h2>
                <p class="lead text-muted mb-0">Kelola keuangan RT Anda dengan fitur lengkap yang dirancang untuk kemudahan dan efisiensi</p>
                <div class="custom-divider"></div>
            </div>
            
            <div class="row align-items-center">
                <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                    <img src="../img/fitur.png" class="img-fluid rounded shadow" alt="Fitur AKURAD">
                </div>
                <div class="col-12 col-lg-6">
                    <div class="list-group">
                        <div class="list-group-item d-flex align-items-center mb-3 rounded bg-light">
                            <i class="bi bi-credit-card-fill text-primary me-3 fs-4"></i>
                            <div>
                                <h4 class="mb-1 text-primary">Iuran Bulanan & Status Pembayaran</h4>
                                <p class="mb-0 text-muted">Pantau status pembayaran iuran warga secara real-time dengan sistem yang terorganisir</p>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center mb-3 rounded bg-light">
                            <i class="bi bi-cart-fill text-primary me-3 fs-4"></i>
                            <div>
                                <h4 class="mb-1 text-primary">Pencatatan Pengeluaran</h4>
                                <p class="mb-0 text-muted">Catat setiap pengeluaran RT dengan detail lengkap dan kategori yang jelas</p>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center mb-3 rounded bg-light">
                            <i class="bi bi-bar-chart-fill text-primary me-3 fs-4"></i>
                            <div>
                                <h4 class="mb-1 text-primary">Laporan Kas Otomatis</h4>
                                <p class="mb-0 text-muted">Generate laporan kas bulanan dan tahunan secara otomatis dengan format yang rapi</p>
                            </div>
                        </div>
                    </div> 
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="fitur.php" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-list-check me-2"></i>Lihat Semua Fitur
                </a>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="feature-box">
                    <div class="feature-icon-container" style="background-color: rgba(33, 150, 243, 0.1);">
                        <i class="bi bi-cash-stack" style="color: var(--bs-info);"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--bs-secondary);">Pencatatan Pengeluaran Kegiatan</h4>
                    <p class="text-muted">Catat setiap aktivitas dan biaya RT secara rapi dengan kategori yang terorganisir dan detail lengkap.</p>
                    <ul class="feature-list">
                        <li>Kategori pengeluaran yang fleksibel</li>
                        <li>Upload bukti transaksi</li>
                        <li>Catatan detail untuk setiap transaksi</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="feature-box">
                    <div class="feature-icon-container" style="background-color: rgba(24, 118, 210, 0.1);">
                        <i class="bi bi-people-fill" style="color: #1876d2;"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--bs-secondary);">Multi-Role User</h4>
                    <p class="text-muted">Bendahara, ketua RT, dan warga punya akses berbeda sesuai kebutuhan dan tanggung jawab masing-masing.</p>
                    <ul class="feature-list">
                        <li>Akses bendahara: full control</li>
                        <li>Akses ketua RT: monitoring & approval</li>
                        <li>Akses warga: view status & laporan</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    
    <section class="py-5 text-center text-light" style="background-color: var(--bs-secondary);">
        <h2 class="mb-2">Mulai Gunakan AKURAD untuk RT Anda</h2>
        <p class="lead mb-4">Bergabunglah dengan puluhan RT yang telah menggunakan AKURAD untuk mengelola keuangan mereka</p>
        <a href="../auth/login.php" class="btn btn-light btn-lg px-4">
            <i class="bi bi-rocket-takeoff me-2"></i>Mulai Sekarang
        </a>
    </section>

    <footer class="bg-primary text-light py-4 text-center">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center mb-2">
                <span class="logo-icon me-2" style="font-size: 1.5rem;"><img src="../img/akurad1.png" alt="#" width="100" height="auto"></span>
                <h5 class="mb-0 fw-bold">AKURAD.APP</h5>
            </div>
            <p class="mb-1">Solusi digital untuk administrasi keuangan RT yang lebih mudah dan transparan.</p>
            <div class="custom-divider bg-warning"></div>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> AKURAD. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
            
    