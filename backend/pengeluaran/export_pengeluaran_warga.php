<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'warga') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Ambil RT warga dari session
$rt_warga = $_SESSION['rt_number'] ?? '';

// Ambil filter dari request
$filter_deskripsi = isset($_GET['deskripsi']) ? mysqli_real_escape_string($koneksi, $_GET['deskripsi']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';

// Query data pengeluaran dengan filter berdasarkan RT warga
$query = "SELECT
            pk.id_pengeluaran,
            pk.tanggal_pengeluaran,
            pk.kategori,
            pk.deskripsi,
            pk.jumlah_pengeluaran,
            pk.bukti,
            pk.created_at,
            u.username as diajukan_oleh,
            u.rt_number
            FROM pengeluaran_kegiatan pk
            LEFT JOIN user u ON pk.id_user = u.id_user
            WHERE u.rt_number = '" . mysqli_real_escape_string($koneksi, $rt_warga) . "'";

// Tambahkan filter
if (!empty($filter_deskripsi)) {
    $query .= " AND pk.deskripsi LIKE '%$filter_deskripsi%'";
}

if (!empty($filter_kategori)) {
    $query .= " AND pk.kategori = '$filter_kategori'";
}

$query .= " ORDER BY pk.tanggal_pengeluaran DESC, pk.created_at DESC";

$result = mysqli_query($koneksi, $query);

// Set header untuk file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data_pengeluaran_rt_' . $rt_warga . '_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Buat output CSV
$output = fopen('php://output', 'w');

// Header informasi sebagai komentar
fwrite($output, "# Data Pengeluaran RT $rt_warga\n");

// Filter yang digunakan
$filter_info = [];
if (!empty($filter_deskripsi)) $filter_info[] = "Deskripsi: $filter_deskripsi";
if (!empty($filter_kategori)) $filter_info[] = "Kategori: $filter_kategori";

if (!empty($filter_info)) {
    fwrite($output, "# Filter: " . implode(', ', $filter_info) . "\n");
}

fwrite($output, "\n");

// Header kolom
fputcsv($output, ['No', 'Tanggal', 'Kategori', 'Deskripsi', 'Jumlah (Rp)', 'Diajukan Oleh', 'Bukti', 'Tanggal Input']);

$no = 1;
$total_pengeluaran = 0;
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $no++,
        date('d/m/Y', strtotime($row['tanggal_pengeluaran'])),
        $row['kategori'],
        $row['deskripsi'],
        number_format($row['jumlah_pengeluaran'], 0, ',', '.'),
        $row['diajukan_oleh'] ?: 'Tidak diketahui',
        $row['bukti'] ? 'Ada' : 'Tidak Ada',
        date('d/m/Y H:i:s', strtotime($row['created_at']))
    ]);

    $total_pengeluaran += $row['jumlah_pengeluaran'];
}

// Tambahkan baris total
fputcsv($output, ['', '', '', 'Total:', number_format($total_pengeluaran, 0, ',', '.'), '', '', '']);

fclose($output);
exit();
?>
