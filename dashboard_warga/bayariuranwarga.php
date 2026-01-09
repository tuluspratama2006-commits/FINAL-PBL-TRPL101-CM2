<?php
// DEBUG MODE - UNTUK MELIHAT ERROR
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'midtrans_debug.log');

session_start();

// Debug mode - enable untuk testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Include koneksi database
require_once '../config/koneksi.php';

// Include Midtrans library manual - PERIKSA PATH INI
$midtrans_path = '../midtrans/Midtrans.php';
if (!file_exists($midtrans_path)) {
    die("File Midtrans tidak ditemukan di: $midtrans_path");
}
require_once $midtrans_path;

use Midtrans\Config;
use Midtrans\Snap;

// Konfigurasi Midtrans - GANTI DENGAN KEY ANDA
Config::$serverKey = 'Mid-server-t7UvdYAqvbKZNB-irrrZ9Ruz';
Config::$clientKey = 'Mid-client-AkQ7Ch2NAQFP8kri';
Config::$isProduction = false; // Set true untuk production
Config::$isSanitized = true;
Config::$is3ds = true;

// Ambil data user dari database berdasarkan nik
$query_user = "SELECT u.id_user, u.nik, u.rt_number, w.id_warga, w.nama_lengkap, w.no_telepon
               FROM user u
               LEFT JOIN warga w ON u.id_warga = w.id_warga
               WHERE u.nik = '" . mysqli_real_escape_string($koneksi, $_SESSION['nik']) . "'";

$result_user = mysqli_query($koneksi, $query_user);
$user_data = mysqli_fetch_assoc($result_user);

if (!$user_data) {
    header("Location: ../auth/login.php");
    exit();
}

// Set session data dari database
$_SESSION['nama'] = $user_data['nama_lengkap'];
$_SESSION['rt'] = $user_data['rt_number'];
$_SESSION['no_telepon'] = $user_data['no_telepon'] ?: '081234567890';
$id_warga = $user_data['id_warga'];

// Array bulan Indonesia
$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Query untuk mengambil tagihan belum lunas dari database
$query_tagihan = "SELECT bulan, tahun, jumlah_iuran, status_pembayaran
                  FROM iuran_rutin
                  WHERE id_warga = '" . mysqli_real_escape_string($koneksi, $id_warga) . "'
                  AND status_pembayaran = 'Belum Lunas'
                  ORDER BY tahun DESC, bulan DESC";

$result_tagihan = mysqli_query($koneksi, $query_tagihan);

// Buat array tagihan bulanan dari database
$tagihan_bulanan = [];
if ($result_tagihan && mysqli_num_rows($result_tagihan) > 0) {
    while ($row = mysqli_fetch_assoc($result_tagihan)) {
        $tagihan_bulanan[] = [
            'bulan' => $bulan_indo[$row['bulan']-1] . ' ' . $row['tahun'],
            'jenis' => 'iuran bulanan RT/RW',
            'jumlah' => (int)$row['jumlah_iuran'],
            'status' => 'belum',
            'bulan_num' => $row['bulan'],
            'tahun_num' => $row['tahun']
        ];
    }
}

// Proses step pembayaran
$step = isset($_GET['step']) ? $_GET['step'] : 'pilih-tagihan';

// Fungsi untuk membuat pembayaran Midtrans
function createMidtransPayment($id_warga, $nama_warga, $no_telepon, $tagihan_dipilih, $total_bayar, $koneksi) {
    global $bulan_indo;
    
    // Generate unique order ID
    $order_id = 'AKURAD-' . date('YmdHis') . '-' . $id_warga . '-' . rand(1000, 9999);
    
    // Simpan data pembayaran awal ke database
    $query_insert = "INSERT INTO pembayaran (
        order_id, 
        id_warga, 
        nominal, 
        payment_status,
        status,
        created_at
    ) VALUES (
        '" . mysqli_real_escape_string($koneksi, $order_id) . "',
        '" . mysqli_real_escape_string($koneksi, $id_warga) . "',
        '" . mysqli_real_escape_string($koneksi, $total_bayar) . "',
        'pending',
        'Menunggu Pembayaran',
        NOW()
    )";
    
    if (!mysqli_query($koneksi, $query_insert)) {
        $error = mysqli_error($koneksi);
        return ['success' => false, 'message' => 'Gagal menyimpan data pembayaran: ' . $error];
    }
    
    // Simpan order_id ke masing-masing tagihan
    foreach ($tagihan_dipilih as $tagihan) {
        $query_update = "UPDATE iuran_rutin 
                        SET payment_order_id = '" . mysqli_real_escape_string($koneksi, $order_id) . "',
                            payment_status = 'pending'
                        WHERE id_warga = '" . mysqli_real_escape_string($koneksi, $id_warga) . "'
                        AND bulan = '" . mysqli_real_escape_string($koneksi, $tagihan['bulan_num']) . "'
                        AND tahun = '" . mysqli_real_escape_string($koneksi, $tagihan['tahun_num']) . "'";
        mysqli_query($koneksi, $query_update);
    }
    
    // Siapkan data untuk Midtrans
    $transaction_details = array(
        'order_id' => $order_id,
        'gross_amount' => $total_bayar
    );
    
    // Item details
    $item_details = [];
    foreach ($tagihan_dipilih as $tagihan) {
        $item_details[] = array(
            'id' => 'luran-' . $tagihan['bulan_num'] . '-' . $tagihan['tahun_num'],
            'price' => $tagihan['jumlah'],
            'quantity' => 1,
            'name' => 'Iuran RT/RW ' . $bulan_indo[$tagihan['bulan_num']-1] . ' ' . $tagihan['tahun_num']
        );
    }
    
    // Customer details
    $customer_details = array(
        'first_name' => substr($nama_warga, 0, 50),
        'phone' => $no_telepon ?: '081234567890'
    );
    
    // PERBAIKAN: Buat callback URL yang benar
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_path = dirname($_SERVER['PHP_SELF']);
    $callback_url = $protocol . '://' . $host . $script_path . '/bayariuranwarga.php?step=hasil&order_id=' . $order_id;
    
    // Transaction data
    $transaction_data = array(
        'transaction_details' => $transaction_details,
        'item_details' => $item_details,
        'customer_details' => $customer_details,
        'callbacks' => array(
            'finish' => $callback_url
        )
    );
    
    try {
        // Get Snap Token dari Midtrans
        $snapToken = Snap::getSnapToken($transaction_data);
        
        if (!$snapToken) {
            return [
                'success' => false,
                'message' => 'Gagal mendapatkan token pembayaran dari Midtrans'
            ];
        }
        
        // Update database dengan snap token
        $query_update_token = "UPDATE pembayaran 
                              SET snap_token = '" . mysqli_real_escape_string($koneksi, $snapToken) . "'
                              WHERE order_id = '" . mysqli_real_escape_string($koneksi, $order_id) . "'";
        mysqli_query($koneksi, $query_update_token);
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'snap_token' => $snapToken,
            'total_bayar' => $total_bayar
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Midtrans Error: ' . $e->getMessage()
        ];
    }
}

// Handle POST data untuk step lanjutan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['tagihan_dipilih']) && isset($_POST['metode_pembayaran'])) {
        $tagihan_dipilih_json = $_POST['tagihan_dipilih'];
        $tagihan_dipilih = json_decode($tagihan_dipilih_json, true);
        $metode_pembayaran = $_POST['metode_pembayaran'];
        
        $response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak diketahui'];

        if (!$tagihan_dipilih || !is_array($tagihan_dipilih)) {
            $response['message'] = 'Data tagihan tidak valid';
        } else {
            $_SESSION['tagihan_dipilih'] = $tagihan_dipilih;
            $_SESSION['metode_pembayaran'] = $metode_pembayaran;
            
            if ($metode_pembayaran === 'midtrans') {
                $total_bayar = 0;
                foreach ($tagihan_dipilih as $tagihan) {
                    $total_bayar += intval($tagihan['jumlah']);
                }
                
                if ($total_bayar < 10000) {
                    $response['message'] = 'Minimal pembayaran melalui Midtrans adalah Rp 10.000';
                } else {
                    $payment_result = createMidtransPayment(
                        $id_warga,
                        $_SESSION['nama'],
                        $_SESSION['no_telepon'],
                        $tagihan_dipilih,
                        $total_bayar,
                        $koneksi
                    );
                    
                    if ($payment_result['success']) {
                        $_SESSION['midtrans_order_id'] = $payment_result['order_id'];
                        $_SESSION['midtrans_snap_token'] = $payment_result['snap_token'];
                        $_SESSION['total_bayar'] = $total_bayar;
                        
                        $response = [
                            'status' => 'success',
                            'redirect_url' => 'bayariuranwarga.php?step=midtrans-payment'
                        ];
                    } else {
                        $response['message'] = $payment_result['message'];
                    }
                }
            } elseif ($metode_pembayaran === 'transfer') {
                $response = [
                    'status' => 'success',
                    'redirect_url' => 'bayariuranwarga.php?step=instruksi-transfer'
                ];
            }
        }
        
        echo json_encode($response);
        exit();
    }
}

// Handle hasil callback dari Midtrans
if ($step === 'hasil' && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $status = $_GET['status'] ?? '';
    
    // Ambil data pembayaran dari database
    $query_pembayaran = "SELECT * FROM pembayaran WHERE order_id = '" . mysqli_real_escape_string($koneksi, $order_id) . "'";
    $result_pembayaran = mysqli_query($koneksi, $query_pembayaran);
    $pembayaran = mysqli_fetch_assoc($result_pembayaran);
    
    if ($pembayaran) {
        $_SESSION['last_payment'] = $pembayaran;
        
        // Update status iuran jika pembayaran berhasil
        if ($status === 'success' || $pembayaran['payment_status'] === 'settlement') {
            $query_update_iuran = "UPDATE iuran_rutin 
                                  SET status_pembayaran = 'Lunas'  
                                  WHERE payment_order_id = '" . mysqli_real_escape_string($koneksi, $order_id) . "'";
            mysqli_query($koneksi, $query_update_iuran);
            
            // Update status pembayaran
            $query_update_payment = "UPDATE pembayaran 
                                   SET payment_status = 'settlement',
                                       status = 'Lunas'
                                   WHERE order_id = '" . mysqli_real_escape_string($koneksi, $order_id) . "'";
            mysqli_query($koneksi, $query_update_payment);
        }
    }
    
    // Hapus session data
    unset($_SESSION['tagihan_dipilih']);
    unset($_SESSION['metode_pembayaran']);
    unset($_SESSION['midtrans_order_id']);
    unset($_SESSION['midtrans_snap_token']);
    unset($_SESSION['total_bayar']);
}

// Fungsi format Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar Iuran - Ankara Web Keuangan RT/RW</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Midtrans Snap JS (hanya load jika diperlukan) -->
    <?php if ($step === 'midtrans-payment' && isset($_SESSION['midtrans_snap_token'])): ?>
    <script type="text/javascript"
        src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="<?php echo Config::$clientKey; ?>"></script>
    <?php endif; ?>
    
    <style>
/* Reset margin main content untuk sidebar */
body{background:#f5f7fb}

/* Main content adjustment */
.main-content {
    margin-left: 0;
    margin-top: 60px;
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
.payment-container {
    max-width: 500px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 30px;
}

.payment-header {
    text-align: center;
    margin-bottom: 30px;
}

.payment-header h1 {
    color: #1a1a1a;
    font-weight: 600;
    margin-bottom: 10px;
}

.subtitle {
    font-size: 1rem;
    color: #666;
}

/* Tagihan Card */
.tagihan-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.tagihan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.tagihan-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.tagihan-item:hover {
    border-color: #0d6efd;
    background: #f8f9ff;
}

.tagihan-item.selected {
    border-color: #0d6efd;
    background: #e8f4ff;
}

.tagihan-info h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a1a;
}

.tagihan-info p {
    margin: 5px 0 0;
    font-size: 0.9rem;
    color: #666;
}

.tagihan-harga {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0d6efd;
}

/* Ringkasan */
.ringkasan-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.ringkasan-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ringkasan-item:last-child {
    margin-bottom: 0;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 1.1rem;
}

/* Metode Pembayaran */
.metode-pembayaran {
    margin-bottom: 30px;
}

.metode-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.metode-item:hover {
    border-color: #0d6efd;
}

.metode-item.selected {
    border-color: #0d6efd;
    background: #e8f4ff;
}

.metode-icon {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.metode-info h6 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.metode-info p {
    margin: 5px 0 0;
    font-size: 0.85rem;
    color: #666;
}

/* Tombol Aksi */
.btn-action {
    width: 100%;
    padding: 12px;
    font-size: 1rem;
    font-weight: 600;
}

.btn-secondary {
    background: #6c757d;
    border: none;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Instruksi Pembayaran */
.instruksi-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.instruksi-item {
    margin-bottom: 20px;
}

.instruksi-item:last-child {
    margin-bottom: 0;
}

.instruksi-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

.instruksi-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1a1a1a;
}

.va-number {
    font-family: monospace;
    font-size: 1.2rem;
    letter-spacing: 1px;
    background: #e9ecef;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
}

.penting-notif {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    color: #856404;
    text-align: center;
    font-weight: 500;
}

/* Status Pembayaran */
.status-container {
    text-align: center;
    padding: 30px 0;
}

.status-icon {
    width: 80px;
    height: 80px;
    background: #d1e7dd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: #0f5132;
}

.status-badge {
    display: inline-block;
    background: #fff3cd;
    color: #856404;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 500;
    margin: 15px 0;
}

.detail-pembayaran {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.detail-item:last-child {
    margin-bottom: 0;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
    font-weight: 600;
}

/* Halaman Pembayaran Midtrans */
.midtrans-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.midtrans-payment-box {
    background: white;
    border-radius: 15px;
    padding: 30px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
}

.midtrans-header h3 {
    color: #333;
    font-weight: 600;
}

.order-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.info-item:last-child {
    margin-bottom: 0;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
    font-weight: bold;
}

.btn-pay {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: bold;
    width: 100%;
    cursor: pointer;
    transition: transform 0.3s;
}

.btn-pay:hover {
    transform: translateY(-2px);
}

.btn-back {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    width: 100%;
    margin-top: 10px;
    cursor: pointer;
}

.loading {
    display: none;
    text-align: center;
    margin-top: 20px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Hasil Pembayaran */
.result-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
}

.status-icon-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 40px;
}

.success-icon {
    background: #d1e7dd;
    color: #0f5132;
}

.pending-icon {
    background: #fff3cd;
    color: #856404;
}

.error-icon {
    background: #f8d7da;
    color: #721c24;
}

.info-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
    text-align: left;
}

/* Responsive */
@media (max-width: 576px) {
    .payment-container {
        padding: 20px;
    }
    
    .tagihan-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .tagihan-harga {
        margin-top: 10px;
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
            <h5 class="fw-bold mb-0">Bayar Iuran</h5>
            <small class="text-muted">Lakukan pembayaran iuran RT/RW</small>
        </div>
    </div>

    <?php if ($step === 'pilih-tagihan'): ?>
    <!-- Step 1: Pilih Tagihan -->
    <div class="payment-container">
        <div class="payment-header">
            <h1>Bayar Iuran</h1>
            <p class="subtitle">Pilih tagihan yang ingin dibayar</p>
        </div>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="tagihan-card">
            <div class="tagihan-header">
                <h6 class="mb-0 fw-bold">Tagihan Belum Dibayar</h6>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll" style="font-size: 0.9rem;">
                        Pilih Semua
                    </label>
                </div>
            </div>
            
            <?php if (empty($tagihan_bulanan)): ?>
            <div class="text-center py-4">
                <p class="text-muted">Tidak ada tagihan yang perlu dibayar</p>
            </div>
            <?php else: ?>
                <?php foreach ($tagihan_bulanan as $tagihan): ?>
                <div class="tagihan-item" onclick="toggleTagihan(this)">
                    <div class="tagihan-info">
                        <h5><?php echo $tagihan['bulan']; ?></h5>
                        <p><?php echo $tagihan['jenis']; ?></p>
                    </div>
                    <div class="tagihan-harga">
                        <?php echo formatRupiah($tagihan['jumlah']); ?>
                    </div>
                    <input type="checkbox" class="d-none tagihan-checkbox" 
                           value="<?php echo $tagihan['jumlah']; ?>" 
                           data-bulan="<?php echo $tagihan['bulan']; ?>" 
                           data-bulan-num="<?php echo $tagihan['bulan_num']; ?>" 
                           data-tahun-num="<?php echo $tagihan['tahun_num']; ?>">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($tagihan_bulanan)): ?>
        <div class="ringkasan-box">
            <div class="ringkasan-item">
                <span>Tagihan dipilih</span>
                <span id="jumlahBulan">0 bulan</span>
            </div>
            <div class="ringkasan-item">
                <span>Total Bayar</span>
                <span id="totalBayar">Rp 0</span>
            </div>
        </div>
        
        <div class="metode-pembayaran">
            <h6 class="mb-3">Metode Pembayaran</h6>
            
            <div class="metode-item" onclick="selectMetode(this, 'midtrans')">
                <div class="metode-icon">
                    <i class="bi bi-credit-card"></i>
                </div>
                <div class="metode-info">
                    <h6>Midtrans Gateway</h6>
                    <p>Credit Card, Bank Transfer, E-Wallet</p>
                </div>
                <input type="radio" name="metode" class="d-none" value="midtrans" checked>
            </div>
            
            <div class="metode-item" onclick="selectMetode(this, 'transfer')">
                <div class="metode-icon">
                    <i class="bi bi-bank"></i>
                </div>
                <div class="metode-info">
                    <h6>Transfer Manual</h6>
                    <p>Transfer ke rekening RT/RW</p>
                </div>
                <input type="radio" name="metode" class="d-none" value="transfer">
            </div>
        </div>
        
        <button class="btn btn-primary btn-action" onclick="lanjutkanPembayaran()" disabled id="btnLanjut">
            Lanjutkan Pembayaran 
            
        </button>
        <?php endif; ?>
        
        <a href="warga.php" class="btn btn-outline-secondary btn-action mt-2">
            Kembali ke Dashboard
        </a>
    </div>
    
    <?php elseif ($step === 'instruksi-transfer'): ?>
    <!-- Step 2: Instruksi Transfer Manual -->
    <div class="payment-container">
        <div class="payment-header">
            <h1>Instruksi Pembayaran</h1>
            <p class="subtitle">Transfer manual ke rekening RT/RW</p>
        </div>

        <div class="instruksi-box">
            <div class="instruksi-item">
                <div class="instruksi-label">Bank</div>
                <div class="instruksi-value">BCA</div>
            </div>

            <div class="instruksi-item">
                <div class="instruksi-label">Nomor Rekening</div>
                <div class="va-number">123-456-7890</div>
            </div>

            <div class="instruksi-item">
                <div class="instruksi-label">Atas Nama</div>
                <div class="instruksi-value">Bendahara RT 01</div>
            </div>

            <div class="instruksi-item">
                <div class="instruksi-label">Total Pembayaran</div>
                <div class="instruksi-value">
                    <?php
                    $total_instruksi = 0;
                    if (isset($_SESSION['tagihan_dipilih']) && is_array($_SESSION['tagihan_dipilih'])) {
                        $total_instruksi = array_sum(array_column($_SESSION['tagihan_dipilih'], 'jumlah'));
                    }
                    echo formatRupiah($total_instruksi);
                    ?>
                </div>
            </div>
        </div>
        
        <div class="penting-notif mb-4">
            <i class="bi bi-exclamation-triangle"></i> 
            Setelah transfer, harap konfirmasi ke bendahara RT/RW
        </div>
        
        <div class="d-flex gap-3">
            <a href="?step=pilih-tagihan" class="btn btn-secondary btn-action">
                Batalkan
            </a>
            <button onclick="konfirmasiTransferManual()" class="btn btn-primary btn-action">
                Sudah Transfer
            </button>
        </div>
    </div>
    
    <?php elseif ($step === 'midtrans-payment' && isset($_SESSION['midtrans_snap_token'])): ?>
    <!-- Step 2: Halaman Pembayaran Midtrans -->
    <div class="midtrans-container">
        <div class="midtrans-payment-box">
            <div class="midtrans-header">
                <h3>Pembayaran Iuran RT/RW</h3>
                <p class="text-muted">Lanjutkan pembayaran melalui Midtrans</p>
            </div>
            
            <div class="order-info">
                <div class="info-item">
                    <span>Order ID:</span>
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['midtrans_order_id'] ?? '-'); ?></span>
                </div>
                <div class="info-item">
                    <span>Nama:</span>
                    <span><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                </div>
                <div class="info-item">
                    <span>Total Pembayaran:</span>
                    <span class="fw-bold text-success">Rp <?php echo isset($_SESSION['total_bayar']) ? number_format($_SESSION['total_bayar'], 0, ',', '.') : '0'; ?></span>
                </div>
                <div class="info-item">
                    <span>Status:</span>
                    <span class="badge bg-warning">Menunggu Pembayaran</span>
                </div>
            </div>
            
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle"></i>
                Anda akan diarahkan ke halaman pembayaran Midtrans yang aman.
            </div>
            
            <button id="pay-button" class="btn-pay">
                <i class="bi bi-credit-card"></i> Bayar Sekarang
            </button>
            <a href="?step=pilih-tagihan" class="btn btn-back">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Mengarahkan ke halaman pembayaran...</p>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const payButton = document.getElementById('pay-button');
        const loading = document.getElementById('loading');
        
        if (payButton) {
            payButton.onclick = function() {
                // Show loading
                if (loading) loading.style.display = 'block';
                if (payButton) {
                    payButton.disabled = true;
                    payButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Memproses...';
                }
                
                // Pastikan snap.js sudah dimuat
                if (typeof snap === 'undefined') {
                    alert('Error: Midtrans payment gateway tidak dapat dimuat. Silakan refresh halaman.');
                    return;
                }
                
                snap.pay('<?php echo $_SESSION['midtrans_snap_token']; ?>', {
                    onSuccess: function(result){
                        console.log('Payment success:', result);
                        window.location.href = 'bayariuranwarga.php?step=hasil&order_id=' + result.order_id + '&status=success';
                    },
                    onPending: function(result){
                        console.log('Payment pending:', result);
                        window.location.href = 'bayariuranwarga.php?step=hasil&order_id=' + result.order_id + '&status=pending';
                    },
                    onError: function(result){
                        console.log('Payment error:', result);
                        window.location.href = 'bayariuranwarga.php?step=hasil&order_id=' + result.order_id + '&status=error';
                    },
                    onClose: function(){
                        console.log('Payment popup closed');
                        if (loading) loading.style.display = 'none';
                        if (payButton) {
                            payButton.disabled = false;
                            payButton.innerHTML = '<i class="bi bi-credit-card"></i> Bayar Sekarang';
                        }
                    }
                });
            };
        }
    });
    </script>
    
    <?php elseif ($step === 'midtrans-payment' && !isset($_SESSION['midtrans_snap_token'])): ?>
    <!-- Jika token tidak ditemukan -->
    <div class="payment-container">
        <div class="alert alert-danger">
            <h4>Token Pembayaran Tidak Ditemukan</h4>
            <p>Silakan kembali dan coba lagi.</p>
            <a href="?step=pilih-tagihan" class="btn btn-warning">Kembali ke Pilih Tagihan</a>
        </div>
    </div>
    
    <?php elseif ($step === 'hasil'): ?>
    <!-- Step 3: Hasil Pembayaran -->
    <div class="payment-container">
        <?php 
        $pembayaran = isset($_SESSION['last_payment']) ? $_SESSION['last_payment'] : null;
        $status = $_GET['status'] ?? '';
        
        if ($pembayaran): 
            if ($status === 'success' || $pembayaran['payment_status'] === 'settlement'):
        ?>
            <div class="status-container">
                <div class="status-icon-large success-icon">
                    <i class="bi bi-check-lg"></i>
                </div>
                
                <h2>Pembayaran Berhasil!</h2>
                <p>Terima kasih telah membayar iuran RT/RW</p>
                
                <div class="info-box">
                    <div class="detail-item">
                        <span>Order ID:</span>
                        <span><?php echo $pembayaran['order_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Nama:</span>
                        <span><?php echo $_SESSION['nama']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Tanggal:</span>
                        <span><?php echo date('d F Y H:i:s', strtotime($pembayaran['created_at'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Total:</span>
                        <span><?php echo formatRupiah($pembayaran['nominal']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Status:</span>
                        <span class="text-success fw-bold">LUNAS</span>
                    </div>
                </div>
                
                <p class="text-muted" style="font-size: 0.9rem;">
                    Pembayaran Anda telah berhasil diproses
                </p>
            </div>
            <?php elseif ($status === 'pending' || $pembayaran['payment_status'] === 'pending'): ?>
            <div class="status-container">
                <div class="status-icon-large pending-icon">
                    <i class="bi bi-clock"></i>
                </div>
                
                <h2>Pembayaran Pending</h2>
                <p>Pembayaran Anda sedang diproses</p>
                
                <div class="info-box">
                    <div class="detail-item">
                        <span>Order ID:</span>
                        <span><?php echo $pembayaran['order_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Status:</span>
                        <span class="text-warning fw-bold">MENUNGGU KONFIRMASI</span>
                    </div>
                </div>
                
                <p class="text-muted" style="font-size: 0.9rem;">
                    Silakan tunggu konfirmasi pembayaran dari sistem
                </p>
            </div>
            <?php else: ?>
            <div class="status-container">
                <div class="status-icon-large error-icon">
                    <i class="bi bi-x-lg"></i>
                </div>
                
                <h2>Pembayaran Gagal</h2>
                <p>Pembayaran tidak dapat diproses</p>
                
                <div class="info-box">
                    <div class="detail-item">
                        <span>Order ID:</span>
                        <span><?php echo $pembayaran['order_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span>Status:</span>
                        <span class="text-danger fw-bold"><?php echo strtoupper($pembayaran['payment_status'] ?: 'failed'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <a href="?step=pilih-tagihan" class="btn btn-primary btn-action">
                Bayar Lagi
            </a>
            <a href="warga.php" class="btn btn-outline-secondary btn-action mt-2">
                Kembali Ke Dashboard
            </a>
            
        <?php else: ?>
        <div class="status-container">
            <div class="status-icon-large error-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            
            <h2>Data Pembayaran Tidak Ditemukan</h2>
            <p>Silakan coba lagi</p>
            
            <a href="?step=pilih-tagihan" class="btn btn-primary btn-action">
                Kembali ke Pembayaran
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- Default: Kembali ke pilih tagihan -->
    <script>
        window.location.href = '?step=pilih-tagihan';
    </script>
    <?php endif; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Fungsi untuk memilih tagihan
function toggleTagihan(element) {
    const checkbox = element.querySelector('.tagihan-checkbox');
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        element.classList.add('selected');
    } else {
        element.classList.remove('selected');
    }
    
    updateRingkasan();
}

// Fungsi untuk memilih semua tagihan
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.tagihan-checkbox');
    const tagihanItems = document.querySelectorAll('.tagihan-item');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    
    tagihanItems.forEach(item => {
        if (this.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });
    
    updateRingkasan();
});

// Fungsi untuk memilih metode pembayaran
function selectMetode(element, value) {
    // Reset semua metode
    document.querySelectorAll('.metode-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('input[type="radio"]').checked = false;
    });
    
    // Pilih metode yang diklik
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
    
    checkButtonState();
}

// Fungsi update ringkasan pembayaran
function updateRingkasan() {
    const checkboxes = document.querySelectorAll('.tagihan-checkbox:checked');
    const jumlahBulan = checkboxes.length;
    let totalBayar = 0;
    
    checkboxes.forEach(checkbox => {
        totalBayar += parseInt(checkbox.value);
    });
    
    const jumlahBulanEl = document.getElementById('jumlahBulan');
    const totalBayarEl = document.getElementById('totalBayar');
    
    if (jumlahBulanEl) {
        jumlahBulanEl.textContent = jumlahBulan + ' bulan';
    }
    
    if (totalBayarEl) {
        totalBayarEl.textContent = 'Rp ' + totalBayar.toLocaleString('id-ID');
    }
    
    checkButtonState();
}

// Fungsi cek state tombol lanjut
function checkButtonState() {
    const tagihanDipilih = document.querySelectorAll('.tagihan-checkbox:checked').length > 0;
    const metodeDipilih = document.querySelector('input[name="metode"]:checked');
    const btnLanjut = document.getElementById('btnLanjut');
    
    if (btnLanjut && tagihanDipilih && metodeDipilih) {
        btnLanjut.disabled = false;
    } else if (btnLanjut) {
        btnLanjut.disabled = true;
    }
}

function lanjutkanPembayaran() {
    const checkboxes = document.querySelectorAll('.tagihan-checkbox:checked');
    const tagihanDipilih = Array.from(checkboxes).map(cb => ({
        bulan_num: parseInt(cb.getAttribute('data-bulan-num')),
        tahun_num: parseInt(cb.getAttribute('data-tahun-num')),
        jumlah: parseInt(cb.value),
        bulan: cb.getAttribute('data-bulan')
    }));
    
    const metode = document.querySelector('input[name="metode"]:checked').value;

    if (tagihanDipilih.length === 0) {
        alert('Pilih minimal 1 tagihan untuk dibayar');
        return;
    }

    const btnLanjut = document.getElementById('btnLanjut');
    const originalText = btnLanjut.innerHTML;
    btnLanjut.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
    btnLanjut.disabled = true;

    const formData = new FormData();
    formData.append('tagihan_dipilih', JSON.stringify(tagihanDipilih));
    formData.append('metode_pembayaran', metode);

    fetch('bayariuranwarga.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            window.location.href = data.redirect_url;
        } else {
            alert(data.message);
            btnLanjut.innerHTML = originalText;
            btnLanjut.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan sistem atau parse JSON gagal.');
        btnLanjut.innerHTML = originalText;
        btnLanjut.disabled = false;
    });
}

// Fungsi konfirmasi transfer manual
function konfirmasiTransferManual() {
    if (confirm('Apakah Anda sudah melakukan transfer pembayaran?')) {
        // Simpan ke database sebagai pembayaran manual
        const formData = new FormData();
        formData.append('konfirmasi_transfer', '1');
        
        fetch('bayariuranwarga.php?step=konfirmasi-transfer', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Konfirmasi berhasil. Pembayaran akan diverifikasi oleh bendahara.');
                window.location.href = 'warga.php';
            } else {
                alert('Terjadi kesalahan: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan dalam mengirim konfirmasi');
        });
    }
}

// Inisialisasi
document.addEventListener('DOMContentLoaded', function() {
    // Auto pilih tagihan pertama (jika ada)
    const firstTagihan = document.querySelector('.tagihan-item');
    if (firstTagihan) {
        toggleTagihan(firstTagihan);
    }
    
    // Auto pilih metode pertama
    const firstMetode = document.querySelector('.metode-item');
    if (firstMetode) {
        selectMetode(firstMetode, firstMetode.querySelector('input').value);
    }
    
    // Update ringkasan pertama kali
    updateRingkasan();
});
</script>
</body>
</html>