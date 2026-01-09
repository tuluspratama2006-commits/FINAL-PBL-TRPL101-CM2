<?php
/**
 * Export Monthly Report to Excel
 * File: backend/laporan/export_laporan_excel.php
 */

date_default_timezone_set('Asia/Jakarta');
session_start();
require_once '../../config/koneksi.php';

// Check login and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
    header("Location: ../../auth/login.php");
    exit();
}

// Get and validate parameters
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validate parameters
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}
if ($tahun < 2000 || $tahun > date('Y') + 10) {
    $tahun = (int)date('Y');
}

// Month names array
$bulan_nama_array = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$bulan_nama = $bulan_nama_array[$bulan] ?? '';

// Query untuk total pemasukan (PERBAIKAN)
$query_pemasukan = "
    SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
    FROM iuran_rutin
    WHERE bulan = '$bulan'  -- Gunakan kolom bulan, bukan MONTH(tanggal_pembayaran)
    AND tahun = '$tahun' 
    AND status_pembayaran = 'Lunas'
";
$result_pemasukan = mysqli_query($koneksi, $query_pemasukan);
$total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];

// Query untuk total pengeluaran
$query_pengeluaran = "
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan
    WHERE MONTH(tanggal_pengeluaran) = '$bulan' 
    AND YEAR(tanggal_pengeluaran) = '$tahun'
";
$result_pengeluaran = mysqli_query($koneksi, $query_pengeluaran);
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];

// Saldo
$saldo = $total_pemasukan - $total_pengeluaran;

// Query untuk data transaksi (PERBAIKAN)
$query_transaksi = "
    SELECT
        'iuran' as tipe,
        i.tanggal_pembayaran as tanggal,
        CONCAT('Iuran ', w.nama_lengkap, ' - ', 
               CASE i.bulan 
                   WHEN 1 THEN 'Januari' WHEN 2 THEN 'Februari' WHEN 3 THEN 'Maret' 
                   WHEN 4 THEN 'April' WHEN 5 THEN 'Mei' WHEN 6 THEN 'Juni'
                   WHEN 7 THEN 'Juli' WHEN 8 THEN 'Agustus' WHEN 9 THEN 'September'
                   WHEN 10 THEN 'Oktober' WHEN 11 THEN 'November' WHEN 12 THEN 'Desember'
               END, ' ', i.tahun) as deskripsi,
        'Iuran' as kategori,
        i.jumlah_iuran as pemasukan,
        0 as pengeluaran,
        w.nama_lengkap
    FROM iuran_rutin i
    JOIN warga w ON i.id_warga = w.id_warga
    WHERE i.bulan = '$bulan' 
    AND i.tahun = '$tahun' 
    AND i.status_pembayaran = 'Lunas'

    UNION ALL

    SELECT
        'pengeluaran' as tipe,
        p.tanggal_pengeluaran as tanggal,
        p.deskripsi as deskripsi,
        p.kategori as kategori,
        0 as pemasukan,
        p.jumlah_pengeluaran as pengeluaran,
        u.username as nama_lengkap
    FROM pengeluaran_kegiatan p
    LEFT JOIN user u ON p.id_user = u.id_user
    WHERE MONTH(p.tanggal_pengeluaran) = '$bulan'
    AND YEAR(p.tanggal_pengeluaran) = '$tahun'

    ORDER BY tanggal DESC
";
$result_transaksi = mysqli_query($koneksi, $query_transaksi);

// Format Rupiah
function format_rupiah($n) {
    $s = $n < 0 ? '-' : '';
    return 'Rp ' . $s . number_format(abs($n), 0, ',', '.');
}

// Header untuk Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"Laporan_Kas_{$bulan_nama}_{$tahun}_" . date('d-m-Y') . ".xls\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .title { font-size: 18px; font-weight: bold; text-align: center; }
        .subtitle { font-size: 14px; text-align: center; margin-bottom: 20px; }
        .summary { background-color: #f2f2f2; padding: 10px; margin-bottom: 20px; }
        .summary td { padding: 5px 10px; }
        .table { border-collapse: collapse; width: 100%; }
        .table th { background-color: #4CAF50; color: white; padding: 8px; }
        .table td { border: 1px solid #ddd; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total { font-weight: bold; background-color: #e8f5e9; }
    </style>
</head>
<body>
    <div class="title">LAPORAN KAS RT</div>
    <div class="subtitle">Periode: <?php echo $bulan_nama . ' ' . $tahun; ?></div>
    
    <table class="summary" width="100%">
        <tr>
            <td width="33%"><strong>Total Pemasukan:</strong></td>
            <td width="33%"><strong>Total Pengeluaran:</strong></td>
            <td width="33%"><strong>Saldo Akhir:</strong></td>
        </tr>
        <tr>
            <td><?php echo format_rupiah($total_pemasukan); ?></td>
            <td><?php echo format_rupiah($total_pengeluaran); ?></td>
            <td><?php echo format_rupiah($saldo); ?></td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Deskripsi</th>
                <th>Kategori</th>
                <th>Nama</th>
                <th>Pemasukan</th>
                <th>Pengeluaran</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            if (mysqli_num_rows($result_transaksi) > 0) {
                while ($row = mysqli_fetch_assoc($result_transaksi)) {
                    echo "<tr>";
                    echo "<td class='text-center'>{$no}</td>";
                    echo "<td>" . (!empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '-') . "</td>";
                    echo "<td>{$row['deskripsi']}</td>";
                    echo "<td>{$row['kategori']}</td>";
                    echo "<td>{$row['nama_lengkap']}</td>";
                    echo "<td class='text-right'>" . ($row['pemasukan'] > 0 ? format_rupiah($row['pemasukan']) : '-') . "</td>";
                    echo "<td class='text-right'>" . ($row['pengeluaran'] > 0 ? format_rupiah($row['pengeluaran']) : '-') . "</td>";
                    echo "</tr>";
                    $no++;
                }
            } else {
                echo "<tr><td colspan='7' class='text-center'>Tidak ada data transaksi</td></tr>";
            }
            ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="5" class="text-right"><strong>TOTAL:</strong></td>
                <td class="text-right"><?php echo format_rupiah($total_pemasukan); ?></td>
                <td class="text-right"><?php echo format_rupiah($total_pengeluaran); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>