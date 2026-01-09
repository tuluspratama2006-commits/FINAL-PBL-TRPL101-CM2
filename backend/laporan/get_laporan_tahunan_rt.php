<?php
session_start();
require_once '../../config/koneksi.php';

header('Content-Type: application/json');

// Check if user is logged in and is RT
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'RT') {
    echo json_encode(['error' => 'Akses ditolak. Silakan login sebagai RT.']);
    exit();
}

$rt_number = $_SESSION['rt_number'] ?? '';

if (empty($rt_number)) {
    echo json_encode(['error' => 'RT number tidak ditemukan dalam session.']);
    exit();
}

try {
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;

    if ($tahun === null) {
        echo json_encode(['error' => 'Tahun harus dispecify.']);
        exit();
    }

    $bulan_indo = [
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];

    // Query untuk data per bulan
    $query_bulanan = "
        SELECT
            bulan,
            COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END), 0) as pemasukan,
            COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as pengeluaran,
            COALESCE(SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as saldo
        FROM (
            SELECT bulan, jumlah_iuran as jumlah, 'pemasukan' as tipe FROM iuran_rutin ir
            LEFT JOIN warga w ON ir.id_warga = w.id_warga
            LEFT JOIN user u ON w.id_warga = u.id_warga
            WHERE u.rt_number = '$rt_number' AND ir.tahun = ? AND ir.status_pembayaran = 'Lunas'
            UNION ALL
            SELECT MONTH(tanggal_pengeluaran) as bulan, jumlah_pengeluaran as jumlah, 'pengeluaran' as tipe FROM pengeluaran_kegiatan pk
            LEFT JOIN user u ON pk.id_user = u.id_user
            WHERE u.rt_number = '$rt_number' AND YEAR(pk.tanggal_pengeluaran) = ?
        ) as combined
        GROUP BY bulan
        ORDER BY bulan
    ";

    $stmt_bulanan = mysqli_prepare($koneksi, $query_bulanan);
    mysqli_stmt_bind_param($stmt_bulanan, 'ii', $tahun, $tahun);
    mysqli_stmt_execute($stmt_bulanan);
    $result_bulanan = mysqli_stmt_get_result($stmt_bulanan);

    $table_data = [];
    $bulan_labels = [];
    $pemasukan_bulanan = [];
    $pengeluaran_bulanan = [];
    $saldo_bulanan = [];

    while ($row = mysqli_fetch_assoc($result_bulanan)) {
        $table_data[] = [
            'bulan' => $bulan_indo[$row['bulan'] - 1],
            'pemasukan' => (int)$row['pemasukan'],
            'pengeluaran' => (int)$row['pengeluaran'],
            'saldo' => (int)$row['saldo']
        ];
        $bulan_labels[] = $bulan_indo[$row['bulan'] - 1];
        $pemasukan_bulanan[] = (int)$row['pemasukan'];
        $pengeluaran_bulanan[] = (int)$row['pengeluaran'];
        $saldo_bulanan[] = (int)$row['saldo'];
    }
    mysqli_stmt_close($stmt_bulanan);

    // Query untuk total pemasukan tahunan
    $query_pemasukan = "
        SELECT COALESCE(SUM(ir.jumlah_iuran), 0) as total_pemasukan
        FROM iuran_rutin ir
        LEFT JOIN warga w ON ir.id_warga = w.id_warga
        LEFT JOIN user u ON w.id_warga = u.id_warga
        WHERE u.rt_number = '$rt_number' AND ir.tahun = ? AND ir.status_pembayaran = 'Lunas'
    ";
    $stmt_pemasukan = mysqli_prepare($koneksi, $query_pemasukan);
    mysqli_stmt_bind_param($stmt_pemasukan, 'i', $tahun);
    mysqli_stmt_execute($stmt_pemasukan);
    $result_pemasukan = mysqli_stmt_get_result($stmt_pemasukan);
    $total_pemasukan = (int)mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];
    mysqli_stmt_close($stmt_pemasukan);

    // Query untuk total pengeluaran tahunan
    $query_pengeluaran = "
        SELECT COALESCE(SUM(pk.jumlah_pengeluaran), 0) as total_pengeluaran
        FROM pengeluaran_kegiatan pk
        LEFT JOIN user u ON pk.id_user = u.id_user
        WHERE u.rt_number = '$rt_number' AND YEAR(pk.tanggal_pengeluaran) = ?
    ";
    $stmt_pengeluaran = mysqli_prepare($koneksi, $query_pengeluaran);
    mysqli_stmt_bind_param($stmt_pengeluaran, 'i', $tahun);
    mysqli_stmt_execute($stmt_pengeluaran);
    $result_pengeluaran = mysqli_stmt_get_result($stmt_pengeluaran);
    $total_pengeluaran = (int)mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];
    mysqli_stmt_close($stmt_pengeluaran);

    // Hitung saldo akhir
    $saldo_akhir = $total_pemasukan - $total_pengeluaran;

    echo json_encode([
        'success' => true,
        'data' => [
            'tahun' => $tahun,
            'total_pemasukan' => $total_pemasukan,
            'total_pengeluaran' => $total_pengeluaran,
            'saldo_akhir' => $saldo_akhir,
            'bulan_labels' => $bulan_labels,
            'pemasukan_bulanan' => $pemasukan_bulanan,
            'pengeluaran_bulanan' => $pengeluaran_bulanan,
            'saldo_bulanan' => $saldo_bulanan,
            'table_data' => $table_data
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
