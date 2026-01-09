<?php
if (ob_get_level()) {
    ob_end_clean();
}
// Mulai output buffering untuk menangkap error
session_start();

// Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Bendahara') {
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
    unlink('test_temp.pdf'); // Clean up test file
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo 'FPDF Error: ' . $e->getMessage();
    exit;
}

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Month names array
$bulan_nama_array = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$bulan_nama = $bulan_nama_array[$bulan] ?? '';

// Query for total income
$query_pemasukan = "
    SELECT COALESCE(SUM(jumlah_iuran), 0) as total_pemasukan
    FROM iuran_rutin
    WHERE bulan = '$bulan' AND tahun = '$tahun' AND status_pembayaran = 'Lunas'
";
$result_pemasukan = mysqli_query($koneksi, $query_pemasukan);
if (!$result_pemasukan) {
    throw new Exception('Error calculating income: ' . mysqli_error($koneksi));
}
$total_pemasukan = mysqli_fetch_assoc($result_pemasukan)['total_pemasukan'];

// Query for total expenses
$query_pengeluaran = "
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total_pengeluaran
    FROM pengeluaran_kegiatan
    WHERE MONTH(tanggal_pengeluaran) = '$bulan' AND YEAR(tanggal_pengeluaran) = '$tahun'
";
$result_pengeluaran = mysqli_query($koneksi, $query_pengeluaran);
if (!$result_pengeluaran) {
    throw new Exception('Error calculating expenses: ' . mysqli_error($koneksi));
}
$total_pengeluaran = mysqli_fetch_assoc($result_pengeluaran)['total_pengeluaran'];

// Calculate balance
$saldo = $total_pemasukan - $total_pengeluaran;

// Query untuk data transaksi bulan ini
$query_transaksi = "
    SELECT
        'iuran' as tipe,
        i.tanggal_pembayaran as tanggal,
        CONCAT('Iuran ', w.nama_lengkap, ' - ', b.bulan_nama, ' ', i.tahun) as deskripsi,
        'Iuran' as kategori,
        i.jumlah_iuran as pemasukan,
        0 as pengeluaran
    FROM iuran_rutin i
    JOIN warga w ON i.id_warga = w.id_warga
    JOIN (
        SELECT 1 as bulan, 'Januari' as bulan_nama UNION ALL
        SELECT 2, 'Februari' UNION ALL
        SELECT 3, 'Maret' UNION ALL
        SELECT 4, 'April' UNION ALL
        SELECT 5, 'Mei' UNION ALL
        SELECT 6, 'Juni' UNION ALL
        SELECT 7, 'Juli' UNION ALL
        SELECT 8, 'Agustus' UNION ALL
        SELECT 9, 'September' UNION ALL
        SELECT 10, 'Oktober' UNION ALL
        SELECT 11, 'November' UNION ALL
        SELECT 12, 'Desember'
    ) b ON i.bulan = b.bulan
    WHERE CAST(i.bulan AS UNSIGNED) = '$bulan' AND i.tahun = '$tahun' AND i.status_pembayaran = 'Lunas'

    UNION ALL

    SELECT
        'pengeluaran' as tipe,
        p.tanggal_pengeluaran as tanggal,
        p.deskripsi as deskripsi,
        p.kategori as kategori,
        0 as pemasukan,
        p.jumlah_pengeluaran as pengeluaran
    FROM pengeluaran_kegiatan p
    WHERE MONTH(p.tanggal_pengeluaran) = '$bulan' AND YEAR(p.tanggal_pengeluaran) = '$tahun'

    ORDER BY tanggal DESC
";
$result_transaksi = mysqli_query($koneksi, $query_transaksi);

// Prepare data
$table_data = [];
if (mysqli_num_rows($result_transaksi) > 0) {
    while ($row = mysqli_fetch_assoc($result_transaksi)) {
        $table_data[] = [
            'tanggal' => date('d/m/Y', strtotime($row['tanggal'])),
            'deskripsi' => $row['deskripsi'],
            'kategori' => $row['kategori'],
            'pemasukan' => $row['pemasukan'],
            'pengeluaran' => $row['pengeluaran']
        ];
    }
}

// Enhanced Input Validation
if (!is_numeric($bulan) || $bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}
if (!is_numeric($tahun) || $tahun < 2000 || $tahun > date('Y') + 10) {
    $tahun = date('Y');
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
    $page_width = 210; // A4 page width in mm

    // Set header untuk download PDF dengan force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Laporan_Kas_' . $bulan_nama . '_' . $tahun . '_' . date('d-m-Y') . '.pdf"');
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
    $pdf->Cell(190,15,'LAPORAN KAS BULANAN',0,1,'C');
    $pdf->SetFont('times','',12);
    $pdf->Cell(190,8,$bulan_nama . ' ' . $tahun,0,1,'C');
    $pdf->Ln(10);

    // Garis pemisah header
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(8);

    // Summary Table
    $pdf->SetFont('times','',12);
    $pdf->Cell(0, 10, 'RINGKASAN BULANAN', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('times', '', 10);
    $summary_width = 60;
    $summary_table_width = $summary_width * 2; // Total width of summary table: 120mm
    $summary_x = ($page_width - $summary_table_width) / 2; // Center position for summary table

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Total Pemasukan Bulanan:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($total_pemasukan), 1, 1, 'R');

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Total Pengeluaran Bulanan:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($total_pengeluaran), 1, 1, 'R');

    $pdf->SetX($summary_x);
    $pdf->Cell($summary_width, 8, 'Saldo Akhir Bulan:', 1);
    $pdf->Cell($summary_width, 8, format_rupiah($saldo), 1, 1, 'R');

    $pdf->Ln(10);

    // Transaction Details Table
    $pdf->SetFont('times', '', 12);
    $pdf->Cell(0, 10, 'RINCIAN TRANSAKSI', 0, 1, 'C');
    $pdf->Ln(5);

    // Table Header
    $pdf->SetFont('times','',11);
    $pdf->SetFillColor(240, 240, 240); // Light gray background
    $col_widths = [25, 70, 30, 35, 35]; // Tanggal, Deskripsi, Kategori, Pemasukan, Pengeluaran
    $table_width = array_sum($col_widths); // Total table width: 195mm
    $table_x = ($page_width - $table_width) / 2; // Center position

    $pdf->SetX($table_x);
    $pdf->Cell($col_widths[0], 12, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($col_widths[1], 12, 'Deskripsi', 1, 0, 'C', true);
    $pdf->Cell($col_widths[2], 12, 'Kategori', 1, 0, 'C', true);
    $pdf->Cell($col_widths[3], 12, 'Pemasukan', 1, 0, 'C', true);
    $pdf->Cell($col_widths[4], 12, 'Pengeluaran', 1, 1, 'C', true);

    // Table Data
    $pdf->SetFont('times', '', 9);
    $pdf->SetFillColor(255, 255, 255); // White background for data rows
    $total_pemasukan_table = 0;
    $total_pengeluaran_table = 0;
    $fill = false;

    if (!empty($table_data)) {
        foreach ($table_data as $row) {
            // Handle long descriptions by wrapping text
            $deskripsi = $row['deskripsi'];
            $max_length = 35; // Maximum characters per line
            if (strlen($deskripsi) > $max_length) {
                $deskripsi = substr($deskripsi, 0, $max_length) . '...';
            }

            $pdf->SetX($table_x);
            $pdf->Cell($col_widths[0], 10, $row['tanggal'], 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[1], 10, $deskripsi, 1, 0, 'L', $fill);
            $pdf->Cell($col_widths[2], 10, $row['kategori'], 1, 0, 'C', $fill);
            $pdf->Cell($col_widths[3], 10, ($row['pemasukan'] > 0 ? format_rupiah($row['pemasukan']) : '-'), 1, 0, 'R', $fill);
            $pdf->Cell($col_widths[4], 10, ($row['pengeluaran'] > 0 ? format_rupiah($row['pengeluaran']) : '-'), 1, 1, 'R', $fill);

            $fill = !$fill; // Alternate row colors
            $total_pemasukan_table += $row['pemasukan'];
            $total_pengeluaran_table += $row['pengeluaran'];
        }

        // Table Footer
        $pdf->SetFont('times', '', 11);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetX($table_x);
        $pdf->Cell($col_widths[0] + $col_widths[1] + $col_widths[2], 12, 'TOTAL BULANAN', 1, 0, 'C', true);
        $pdf->Cell($col_widths[3], 12, format_rupiah($total_pemasukan_table), 1, 0, 'R', true);
        $pdf->Cell($col_widths[4], 12, format_rupiah($total_pengeluaran_table), 1, 1, 'R', true);
    } else {
        $pdf->SetFont('times','',10);
        $pdf->Cell(190,15,'Tidak ada data transaksi untuk bulan ini',1,1,'C');
    }

    // Output PDF langsung ke browser
    $pdf->Output();

} catch (Exception $e) {
    // Jika ada error, tampilkan pesan error
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/plain');
    echo 'Error generating PDF: ' . $e->getMessage();
}

exit;
