<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Bendahara', 'RT'])) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Ambil filter dari request
$filter_deskripsi = isset($_GET['deskripsi']) ? mysqli_real_escape_string($koneksi, $_GET['deskripsi']) : '';
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';

// Query data pengeluaran dengan filter
$query = "SELECT
            pk.id_pengeluaran,
            pk.tanggal_pengeluaran,
            pk.kategori,
            pk.deskripsi,
            pk.jumlah_pengeluaran,
            pk.bukti,
            pk.created_at,
            u.username as diajukan_oleh
            FROM pengeluaran_kegiatan pk
            LEFT JOIN user u ON pk.id_user = u.id_user
            WHERE 1=1";

// Tambahkan filter
if (!empty($filter_deskripsi)) {
    $query .= " AND pk.deskripsi LIKE '%$filter_deskripsi%'";
}

if (!empty($filter_kategori)) {
    $query .= " AND pk.kategori = '$filter_kategori'";
}

$query .= " ORDER BY pk.tanggal_pengeluaran DESC, pk.created_at DESC";

$result = mysqli_query($koneksi, $query);

// Set header untuk file Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="data_pengeluaran_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<h2>Data Pengeluaran RT/RW</h2>';

// Filter yang digunakan
$filter_info = [];
if (!empty($filter_deskripsi)) $filter_info[] = "Deskripsi: $filter_deskripsi";
if (!empty($filter_kategori)) $filter_info[] = "Kategori: $filter_kategori";

if (!empty($filter_info)) {
    echo '<p><strong>Filter:</strong> ' . implode(', ', $filter_info) . '</p>';
}

echo '<br>';

// Tabel data
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th class="text-center">No</th>';
echo '<th class="text-center">Tanggal</th>';
echo '<th class="text-center">Kategori</th>';
echo '<th>Deskripsi</th>';
echo '<th class="text-right">Jumlah (Rp)</th>';
echo '<th>Diajukan Oleh</th>';
echo '<th class="text-center">Bukti</th>';
echo '<th class="text-center">Tanggal Input</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
$total_pengeluaran = 0;
while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td class="text-center">' . $no++ . '</td>';
    echo '<td class="text-center">' . date('d/m/Y', strtotime($row['tanggal_pengeluaran'])) . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($row['kategori']) . '</td>';
    echo '<td>' . htmlspecialchars($row['deskripsi']) . '</td>';
    echo '<td class="text-right">' . number_format($row['jumlah_pengeluaran'], 0, ',', '.') . '</td>';
    echo '<td>' . htmlspecialchars($row['diajukan_oleh'] ?: 'Tidak diketahui') . '</td>';
    echo '<td class="text-center">' . ($row['bukti'] ? 'Ada' : 'Tidak Ada') . '</td>';
    echo '<td class="text-center">' . date('d/m/Y H:i:s', strtotime($row['created_at'])) . '</td>';
    echo '</tr>';

    $total_pengeluaran += $row['jumlah_pengeluaran'];
}

// Tambahkan baris total
echo '<tr>';
echo '<td colspan="4" class="text-right"><strong>Total:</strong></td>';
echo '<td class="text-right"><strong>' . number_format($total_pengeluaran, 0, ',', '.') . '</strong></td>';
echo '<td colspan="3"></td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';

echo '</body>';
echo '</html>';
exit();
?>
