<?php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - AKURAD.APP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --bs-primary: #0d47a1;
            --bs-secondary: #1a237e;
            --bs-success: #4caf50;
        }
        
        .bg-primary { background-color: var(--bs-primary) !important; }
        .text-primary { color: var(--bs-primary) !important; }
        
        .navbar-brand .logo-icon { font-size: 1.5rem; margin-right: 5px; }
        
        .custom-divider {
            width: 50px;
            height: 3px;
            background-color: #ffc107;
            margin: 2rem auto;
        }

        .feature-grid-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
            min-height: 250px; 
        }

        .feature-grid-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        .feature-grid-card i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary px-lg-5">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="home.php">
                    <span class="logo-icon"><img src="../img/akurad1.png" alt="#" width="100" height="auto"></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">Home</a> 
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="about_us.php">About Us</a>
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
    
    <section class="container text-center py-5">
        <h1 class="display-5 fw-bold" style="color: var(--bs-secondary);">Kenalan dengan AKURAD</h1>
        <p class="lead text-muted">Solusi digital untuk pengelolaan keuangan RT yang lebih modern dan efisien</p>
    </section>

    <section class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="fw-bold mb-4">Apa Itu AKURAD?</h2>
                <p class="lead text-muted">AKURAD adalah aplikasi berbasis web untuk mencatat iuran warga, mengelola pengeluaran RT, dan menyajikan laporan kas secara transparan.</p>
                <p class="text-muted">Dibuat untuk membantu bendahara mengurangi kesalahan pencatatan dan meningkatkan kepercayaan warga melalui sistem yang terorganisir dan mudah diakses.</p>
            </div>
            <div class="col-lg-6 text-center">
                <img src="../img/iuran.png" class="img-fluid rounded shadow" alt="Visual Iuran Warga">
            </div>
        </div>
    </section>

    <section class="bg-light py-5">
        <div class="container text-center">
            <h2 class="mb-5" style="color: var(--bs-secondary);">Keunggulan AKURAD</h2>
            <p class="lead text-muted mb-5">Mengapa AKURAD menjadi pilihan terbaik untuk RT Anda</p>
            
            <div class="row justify-content-center g-4">
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <h5 class="mt-2 fw-bold">Mudah Digunakan</h5>
                        <p class="text-muted">Interface yang intuitif dan user-friendly, tidak perlu training khusus untuk menggunakannya</p>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-graph-up text-warning"></i>
                        <h5 class="mt-2 fw-bold">Transparan & Real-time</h5>
                        <p class="text-muted">Data keuangan dapat diakses kapan saja dengan update real-time untuk transparansi penuh</p>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-people-fill text-info"></i>
                        <h5 class="mt-2 fw-bold">Multi-level Access</h5>
                        <p class="text-muted">Sistem hak akses yang berbeda untuk bendahara, ketua RT, dan warga sesuai kebutuhan</p>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-shield-lock-fill text-primary"></i>
                        <h5 class="mt-2 fw-bold">Data Aman di Cloud</h5>
                        <p class="text-muted">Sistem keamanan berlapis dan backup otomatis untuk melindungi data keuangan RT Anda</p>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-cloud-check-fill text-success"></i>
                        <h5 class="mt-2 fw-bold">Tidak Perlu Instalasi</h5>
                        <p class="text-muted">Berbasis web, cukup buka browser dan akses dari perangkat apa saja</p>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-6">
                    <div class="card feature-grid-card p-4 h-100 shadow-sm">
                        <i class="bi bi-phone-fill" style="color: orange;"></i>
                        <h5 class="mt-2 fw-bold">Responsive Design</h5>
                        <p class="text-muted">Tampilan yang optimal di desktop, tablet maupun smartphone</p>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <footer class="bg-primary text-light py-4 text-center">
        <div class="container">
            <div class="d-flex align-items-center justify-content-center mb-2">
                <span class="logo-icon me-2" style="font-size: 1.5rem;"><img src="../img/akurad1.png" alt="#" width="100" height="auto"></span>
                <h5 class="mb-0 fw-bold"></h5>
            </div>
            <p class="mb-1">Solusi digital untuk administrasi keuangan RT yang lebih mudah dan transparan.</p>
            <div class="custom-divider bg-warning"></div>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> AKURAD. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>