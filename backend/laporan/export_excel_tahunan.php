<?php
// File: backend/laporan/export_excel_tahunan.php
session_start();
require_once '../../config/koneksi.php';

// Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Bendahara', 'warga'])) {
    header("Location: ../../auth/login.php");
    exit();
}

$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

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
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan
    WHERE YEAR(tanggal_pengeluaran) = '$tahun'
";
$result_pengeluaran_tahunan = mysqli_query($koneksi, $query_pengeluaran_tahunan);
$total_pengeluaran_tahunan = mysqli_fetch_assoc($result_pengeluaran_tahunan)['total_pengeluaran'];

// Saldo akhir
$saldo_akhir = $total_pemasukan_tahunan - $total_pengeluaran_tahunan;

// Query untuk data bulanan
$query_bulanan = "
    SELECT
        bulan,
        COALESCE(SUM(CASE WHEN tipe = 'iuran' THEN jumlah END), 0) as pemasukan,
        COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah END), 0) as pengeluaran,
        COALESCE(SUM(CASE WHEN tipe = 'iuran' THEN jumlah END), 0) - COALESCE(SUM(CASE WHEN tipe = 'pengeluaran' THEN jumlah END), 0) as saldo
    FROM (
        SELECT
            bulan,
            'iuran' as tipe,
            jumlah_iuran as jumlah
        FROM iuran_rutin
        WHERE tahun = '$tahun' AND status_pembayaran = 'Lunas'

        UNION ALL

        SELECT
            MONTH(tanggal_pengeluaran) as bulan,
            'pengeluaran' as tipe,
            jumlah_pengeluaran as jumlah
        FROM pengeluaran_kegiatan
        WHERE YEAR(tanggal_pengeluaran) = '$tahun'
    ) as combined
    GROUP BY bulan
    ORDER BY bulan
";
$result_bulanan = mysqli_query($koneksi, $query_bulanan);

// Prepare data
$bulan_nama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$table_data = [];

while ($row = mysqli_fetch_assoc($result_bulanan)) {
    $bulan_index = $row['bulan'] - 1; // bulan 1-12 to index 0-11
    $table_data[] = [
        'bulan' => $bulan_nama[$bulan_index],
        'pemasukan' => $row['pemasukan'],
        'pengeluaran' => $row['pengeluaran'],
        'saldo' => $row['saldo']
    ];
}

// Format Rupiah
function format_rupiah($n) {
    $s = $n < 0 ? '-' : '';
    return 'Rp ' . $s . number_format(abs($n), 0, ',', '.');
}

// Header untuk Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"Laporan_Kas_Tahunan_{$tahun}.xls\"");
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
    <div class="title">LAPORAN KAS TAHUNAN RT</div>
    <div class="subtitle">Tahun: <?php echo $tahun; ?></div>

    <table class="summary" width="100%">
        <tr>
            <td width="33%"><strong>Total Pemasukan Tahunan:</strong></td>
            <td width="33%"><strong>Total Pengeluaran Tahunan:</strong></td>
            <td width="33%"><strong>Saldo Akhir Tahun:</strong></td>
        </tr>
        <tr>
            <td><?php echo format_rupiah($total_pemasukan_tahunan); ?></td>
            <td><?php echo format_rupiah($total_pengeluaran_tahunan); ?></td>
            <td><?php echo format_rupiah($saldo_akhir); ?></td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Bulan</th>
                <th>Pemasukan</th>
                <th>Pengeluaran</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $total_pemasukan = 0;
            $total_pengeluaran = 0;
            $total_saldo = 0;

            if (!empty($table_data)) {
                foreach ($table_data as $row) {
                    echo "<tr>";
                    echo "<td class='text-center'>{$no}</td>";
                    echo "<td>{$row['bulan']}</td>";
                    echo "<td class='text-right'>" . format_rupiah($row['pemasukan']) . "</td>";
                    echo "<td class='text-right'>" . format_rupiah($row['pengeluaran']) . "</td>";
                    echo "<td class='text-right'>" . format_rupiah($row['saldo']) . "</td>";
                    echo "</tr>";
                    $no++;
                    $total_pemasukan += $row['pemasukan'];
                    $total_pengeluaran += $row['pengeluaran'];
                    $total_saldo += $row['saldo'];
                }
            } else {
                echo "<tr><td colspan='5' class='text-center'>Tidak ada data transaksi</td></tr>";
            }
            ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="2" class="text-right"><strong>TOTAL TAHUNAN:</strong></td>
                <td class="text-right"><?php echo format_rupiah($total_pemasukan); ?></td>
                <td class="text-right"><?php echo format_rupiah($total_pengeluaran); ?></td>
                <td class="text-right"><?php echo format_rupiah($total_saldo); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
