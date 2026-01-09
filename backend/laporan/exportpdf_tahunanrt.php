<?php
if (ob_get_level()) {
    ob_end_clean();
}
// Mulai output buffering untuk menangkap error
session_start();

// Cek login dan role - MODIFIKASI: Tambahkan role RT
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Bendahara', 'RT'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Validation Phase - Check file dependencies
$required_files = [
    '../../config/koneksi.php',
    '../../fpdf/fpdf.php',
    '../../img/akurad2.png'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    header('Content-Type: text/plain');
    echo 'Error: Missing required files: ' . implode(', ', $missing_files);
    exit;
}

// Load file konfigurasi dan library dari direktori lokal
require_once('../../config/koneksi.php');
require_once('../../fpdf/fpdf.php');

// Database Connection
try {
    $test_query = "SELECT COUNT(*) as total FROM iuran_rutin WHERE status_pembayaran = 'Lunas'";
    $test_result = mysqli_query($koneksi, $test_query);
    if (!$test_result) {
        throw new Exception('Database connection test failed: ' . mysqli_error($koneksi));
    }
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo 'Database Error: ' . $e->getMessage();
    exit;
}

// FPDF Library Test
try {
    $test_pdf = new FPDF();
    $test_pdf->AddPage();
    $test_pdf->SetFont('Arial','B',16);
    $test_pdf->Cell(40,10,'Test PDF Generation');
    $test_pdf->Output('F', 'test_temp.pdf');

    if (!file_exists('test_temp.pdf')) {
        throw new Exception('FPDF library test failed - cannot generate test PDF');
    }
    unlink('test_temp.pdf');
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo 'FPDF Error: ' . $e->getMessage();
    exit;
}

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

// Validate parameters
if ($tahun < 2000 || $tahun > date('Y') + 10) {
    $tahun = (int)date('Y');
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
    $bulan_index = $row['bulan'] - 1;
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

try {
    // Bersihkan output buffer sebelum set header
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Define page dimensions
    $page_width = 210;

    // Set header untuk download PDF dengan force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Laporan_Kas_Tahunan_' . $tahun . '_' . date('d-m-Y') . '.pdf"');
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Create PDF
    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();

    // Logo
    $pdf->Image('../../img/akurad2.png', 10, 10, 25);

    // Header laporan
    $pdf->SetFont('times','',18);
    $pdf->Cell(190,15,'LAPORAN KAS TAHUNAN',0,1,'C');
    $pdf->SetFont('times','',12);
    $pdf->Cell(190,8,'Tahun: ' . $tahun,0,1,'C');
    $pdf->Ln(10);

    // Garis pemisah header
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(8);

    // Summary Table
    $pdf->SetFont('times','',12);
    $pdf->Cell(0, 10, 'RINGKASAN TAHUNAN', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('times', '', 10);
    $summary_width = 60;
    $summary_table_width = $summary_width * 2;
    $summary_x = ($page_width - $summary_table_width) / 2;

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Total Pemasukan Tahunan:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($total_pemasukan_tahunan), 1, 1, 'R');

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Total Pengeluaran Tahunan:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($total_pengeluaran_tahunan), 1, 1, 'R');

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Saldo Akhir Tahun:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($saldo_akhir), 1, 1, 'R');

    $pdf->Ln(10);

    // Monthly Details Table
    $pdf->SetFont('times', '', 12);
    $pdf->Cell(0, 10, 'RINCIAN PER BULAN', 0, 1, 'C');
    $pdf->Ln(5);

    // Table Header
    $pdf->SetFont('times','',11);
    $pdf->SetFillColor(240, 240, 240);
    $col_widths = [40, 35, 35, 35];
    $table_width = array_sum($col_widths);
    $page_width = 210;
    $table_x = ($page_width - $table_width) / 2;

    $pdf->SetX($table_x);
    $pdf->Cell($col_widths[0], 12, 'Bulan', 1, 0, 'C', true);
    $pdf->Cell($col_widths[1], 12, 'Pemasukan', 1, 0, 'C', true);
    $pdf->Cell($col_widths[2], 12, 'Pengeluaran', 1, 0, 'C', true);
    $pdf->Cell($col_widths[3], 12, 'Saldo', 1, 1, 'C', true);

    // Table Data
    $pdf->SetFont('times', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    $total_pemasukan = 0;
    $total_pengeluaran = 0;
    $total_saldo = 0;
    $fill = false;

    if (!empty($table_data)) {
        foreach ($table_data as $row) {
            $pdf->SetX($table_x);
            $pdf->Cell($col_widths[0], 10, $row['bulan'], 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[1], 10, format_rupiah($row['pemasukan']), 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[2], 10, format_rupiah($row['pengeluaran']), 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[3], 10, format_rupiah($row['saldo']), 1, 1, 'C', $fill);

            $fill = !$fill;
            $total_pemasukan += $row['pemasukan'];
            $total_pengeluaran += $row['pengeluaran'];
            $total_saldo += $row['saldo'];
        }

        // Table Footer
        $pdf->SetFont('times', '', 11);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetX($table_x);
        $pdf->Cell($col_widths[0], 12, 'TOTAL TAHUNAN', 1, 0, 'C', true);
        $pdf->Cell($col_widths[1], 12, format_rupiah($total_pemasukan), 1, 0, 'C', true);
        $pdf->Cell($col_widths[2], 12, format_rupiah($total_pengeluaran), 1, 0, 'C', true);
        $pdf->Cell($col_widths[3], 12, format_rupiah($total_saldo), 1, 1, 'C', true);
    } else {
        $pdf->SetFont('times','',10);
        $pdf->Cell(190,15,'Tidak ada data transaksi untuk tahun ini',1,1,'C');
    }

    // Output PDF langsung ke browser
    $pdf->Output();

} catch (Exception $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/plain');
    echo 'Error generating PDF: ' . $e->getMessage();
}

exit;