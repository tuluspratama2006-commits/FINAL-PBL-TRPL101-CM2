<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Bendahara') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}
$bulan_indo = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Ambil filter dari request
$filter_cari = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, $_GET['cari']) : '';
$filter_bulan = isset($_GET['bulan']) ? mysqli_real_escape_string($koneksi, $_GET['bulan']) : '';
$filter_rt = isset($_GET['rt']) ? mysqli_real_escape_string($koneksi, $_GET['rt']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';

// Query data iuran dengan filter
$query = "SELECT
            i.id_iuran,
            i.id_warga,
            w.nama_lengkap,
            u.nik,
            u.rt_number,
            i.bulan,
            i.tahun,
            i.jumlah_iuran,
            i.tanggal_pembayaran,
            i.status_pembayaran,
            i.keterangan,
            i.created_at
            FROM iuran_rutin i
            JOIN warga w ON i.id_warga = w.id_warga
            JOIN user u ON w.id_warga = u.id_warga
            WHERE 1=1";

// Tambahkan filter
if (!empty($filter_cari)) {
    $query .= " AND (u.nik LIKE '%$filter_cari%' OR w.nama_lengkap LIKE '%$filter_cari%')";
}

if (!empty($filter_bulan)) {
    $bulan_angka = array_search($filter_bulan, $bulan_indo) + 1;
    $query .= " AND i.bulan = '$bulan_angka'";
}

if (!empty($filter_rt)) {
    $query .= " AND u.rt_number = '$filter_rt'";
}

if (!empty($filter_status)) {
    $query .= " AND i.status_pembayaran = '$filter_status'";
}

$query .= " ORDER BY i.tahun DESC, i.bulan DESC, w.nama_lengkap ASC";

$result = mysqli_query($koneksi, $query);

// Set header untuk file Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="data_iuran_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// Buat tabel HTML yang kompatibel dengan Excel
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<style>';
echo 'table { border-collapse: collapse; }';
echo 'th { background-color: #f8f9fa; font-weight: bold; border: 1px solid #dee2e6; padding: 8px; text-align: center; }';
echo 'td { border: 1px solid #dee2e6; padding: 8px; }';
echo '.text-center { text-align: center; }';
echo '.text-right { text-align: right; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Header informasi
echo '<h2>Data Iuran Warga</h2>';
echo '<p><strong>Tanggal Export:</strong> ' . date('d/m/Y H:i:s') . '</p>';

// Filter yang digunakan
$filter_info = [];
if (!empty($filter_cari)) $filter_info[] = "Pencarian: $filter_cari";
if (!empty($filter_bulan)) $filter_info[] = "Bulan: $filter_bulan";
if (!empty($filter_rt)) $filter_info[] = "RT: $filter_rt";
if (!empty($filter_status)) $filter_info[] = "Status: $filter_status";

if (!empty($filter_info)) {
    echo '<p><strong>Filter:</strong> ' . implode(', ', $filter_info) . '</p>';
}

echo '<br>';

// Tabel data
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th class="text-center">No</th>';
echo '<th class="text-center">NIK</th>';
echo '<th>Nama Lengkap</th>';
echo '<th class="text-center">RT</th>';
echo '<th class="text-center">Bulan</th>';
echo '<th class="text-center">Tahun</th>';
echo '<th class="text-right">Jumlah Iuran (Rp)</th>';
echo '<th class="text-center">Status Pembayaran</th>';
echo '<th class="text-center">Tanggal Pembayaran</th>';
echo '<th>Keterangan</th>';
echo '<th class="text-center">Tanggal Input</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
$total_iuran = 0;
while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td class="text-center">' . $no++ . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($row['nik']) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($row['rt_number']) . '</td>';
    echo '<td class="text-center">' . $bulan_indo[$row['bulan'] - 1] . '</td>';
    echo '<td class="text-center">' . $row['tahun'] . '</td>';
    echo '<td class="text-right">' . number_format($row['jumlah_iuran'], 0, ',', '.') . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($row['status_pembayaran']) . '</td>';
    echo '<td class="text-center">' . ($row['tanggal_pembayaran'] ? date('d/m/Y', strtotime($row['tanggal_pembayaran'])) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['keterangan'] ?: '-') . '</td>';
    echo '<td class="text-center">' . date('d/m/Y H:i:s', strtotime($row['created_at'])) . '</td>';
    echo '</tr>';

    $total_iuran += $row['jumlah_iuran'];
}

// Tambahkan baris total
echo '<tr>';
echo '<td colspan="6" class="text-right"><strong>Total:</strong></td>';
echo '<td class="text-right"><strong>' . number_format($total_iuran, 0, ',', '.') . '</strong></td>';
echo '<td colspan="5"></td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';

echo '</body>';
echo '</html>';
exit();
?>
