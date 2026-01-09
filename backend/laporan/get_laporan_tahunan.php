<?php
/**
 * Get Annual Report Data API
 * File: backend/laporan/get_laporan_tahunan.php
 */

session_start();
require_once '../../config/koneksi.php';

// Set JSON response header
header('Content-Type: application/json');

// Check database connection
if (!$koneksi) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
    exit();
}

// Check login and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['RT', 'Bendahara', 'warga'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get and validate year parameter
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validate year
if ($tahun < 2000 || $tahun > date('Y') + 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Tahun tidak valid']);
    exit();
}

// Query untuk total pemasukan tahunan
$query_pemasukan_tahunan = "
    SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
    FROM iuran_rutin
    WHERE tahun = '$tahun' AND status_pembayaran = 'Lunas'
";
$result_pemasukan_tahunan = mysqli_query($koneksi, $query_pemasukan_tahunan);
$total_pemasukan_tahunan = mysqli_fetch_assoc($result_pemasukan_tahunan)['total_pemasukan'];

// Query untuk total pengeluaran tahunan
$query_pengeluaran_tahunan = "
    SELECT COALESCE(SUM(pk.jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan pk
    LEFT JOIN user u ON pk.id_user = u.id_user
    WHERE YEAR(pk.tanggal_pengeluaran) = '$tahun' AND u.rt_number = '{$_SESSION['rt_number']}'
";
$result_pengeluaran_tahunan = mysqli_query($koneksi, $query_pengeluaran_tahunan);
$total_pengeluaran_tahunan = mysqli_fetch_assoc($result_pengeluaran_tahunan)['total_pengeluaran'];

// Saldo akhir
$saldo_akhir = $total_pemasukan_tahunan - $total_pengeluaran_tahunan;

// Query untuk data bulanan - DIBATASI OLEH RT USER
$query_bulanan = "
    SELECT
        bulan,
        COALESCE(SUM(CASE WHEN tipe = 'iuran' THEN jumlah END), 0) as pemasukan,
        COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah END), 0) as pengeluaran,
        COALESCE(SUM(CASE WHEN tipe = 'iuran' THEN jumlah END), 0) - COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah END), 0) as saldo
    FROM (
        SELECT
            i.bulan,
            'iuran' as tipe,
            i.jumlah_iuran as jumlah
        FROM iuran_rutin i
        LEFT JOIN warga w ON i.id_warga = w.id_warga
        LEFT JOIN user u ON w.id_warga = u.id_warga
        WHERE i.tahun = '$tahun' AND i.status_pembayaran = 'Lunas' AND u.rt_number = '{$_SESSION['rt_number']}'

        UNION ALL

        SELECT
            MONTH(p.tanggal_pengeluaran) as bulan,
            'pengeluaran' as tipe,
            p.jumlah_pengeluaran as jumlah
        FROM pengeluaran_kegiatan p
        LEFT JOIN user u ON p.id_user = u.id_user
        WHERE YEAR(p.tanggal_pengeluaran) = '$tahun' AND u.rt_number = '{$_SESSION['rt_number']}'
    ) as combined
    GROUP BY bulan
    ORDER BY bulan
";
$result_bulanan = mysqli_query($koneksi, $query_bulanan);

// Prepare data
$bulan_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$bulan_nama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$pemasukan_bulanan = array_fill(0, 12, 0);
$pengeluaran_bulanan = array_fill(0, 12, 0);
$saldo_bulanan = array_fill(0, 12, 0);
$table_data = [];

while ($row = mysqli_fetch_assoc($result_bulanan)) {
    $bulan_index = $row['bulan'] - 1; // bulan 1-12 to index 0-11
    $pemasukan_bulanan[$bulan_index] = $row['pemasukan'];
    $pengeluaran_bulanan[$bulan_index] = $row['pengeluaran'];
    $saldo_bulanan[$bulan_index] = $row['saldo'];
    $table_data[] = [
        'bulan' => $bulan_nama[$bulan_index],
        'pemasukan' => $row['pemasukan'],
        'pengeluaran' => $row['pengeluaran'],
        'saldo' => $row['saldo']
    ];
}

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'total_pemasukan' => $total_pemasukan_tahunan,
    'total_pengeluaran' => $total_pengeluaran_tahunan,
    'saldo_akhir' => $saldo_akhir,
    'bulan_labels' => $bulan_labels,
    'pemasukan_bulanan' => $pemasukan_bulanan,
    'pengeluaran_bulanan' => $pengeluaran_bulanan,
    'saldo_bulanan' => $saldo_bulanan,
    'table_data' => $table_data
]);
?>
