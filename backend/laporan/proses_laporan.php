<?php
/**
 * Laporan Processing Functions
 * File: backend/laporan/proses_laporan.php
 */

require_once '../config/koneksi.php';

/**
 * Generate monthly report
 * @param int $bulan Month (1-12)
 * @param int $tahun Year
 * @return int Report ID
 */
function generateLaporanBulanan($bulan, $tahun) {
    global $koneksi;

    // Validate input parameters
    if (!is_numeric($bulan) || $bulan < 1 || $bulan > 12) {
        throw new Exception("Invalid month: $bulan");
    }
    if (!is_numeric($tahun) || $tahun < 2000 || $tahun > date('Y') + 10) {
        throw new Exception("Invalid year: $tahun");
    }

    // Calculate total income
    $query_pemasukan = "SELECT COALESCE(SUM(jumlah_iuran), 0) as total
                       FROM iuran_rutin
                       WHERE bulan = $bulan AND tahun = $tahun
                       AND status_pembayaran = 'Lunas'";
    $result = mysqli_query($koneksi, $query_pemasukan);
    if (!$result) {
        throw new Exception("Error calculating income: " . mysqli_error($koneksi));
    }
    $total_pemasukan = mysqli_fetch_assoc($result)['total'];

    // Calculate total expenses
    $query_pengeluaran = "SELECT COALESCE(SUM(jumlah_pengeluaran), 0) as total
                         FROM pengeluaran_kegiatan
                         WHERE MONTH(tanggal_pengeluaran) = $bulan
                         AND YEAR(tanggal_pengeluaran) = $tahun";
    $result = mysqli_query($koneksi, $query_pengeluaran);
    if (!$result) {
        throw new Exception("Error calculating expenses: " . mysqli_error($koneksi));
    }
    $total_pengeluaran = mysqli_fetch_assoc($result)['total'];

    // Calculate final balance
    $saldo_akhir = $total_pemasukan - $total_pengeluaran;

    // Insert into laporan_kas table
    $query_insert = "INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
                    VALUES ('Bulanan', '$bulan-$tahun', $total_pemasukan, $total_pengeluaran, $saldo_akhir)";

    if (!mysqli_query($koneksi, $query_insert)) {
        throw new Exception("Error inserting report: " . mysqli_error($koneksi));
    }

    $id_laporan = mysqli_insert_id($koneksi);

    // Update monitoring_kas for today's date
    updateMonitoringKas($id_laporan, $saldo_akhir);

    return $id_laporan;
}

/**
 * Update or insert monitoring_kas record
 * @param int $id_laporan Report ID
 * @param float $saldo_kas Cash balance
 */
function updateMonitoringKas($id_laporan, $saldo_kas) {
    global $koneksi;

    $tanggal = date('Y-m-d');

    // Check if data already exists for today
    $query_check = "SELECT id_monitoring FROM monitoring_kas WHERE tanggal_monitoring = '$tanggal'";
    $result = mysqli_query($koneksi, $query_check);

    if (!$result) {
        throw new Exception("Error checking monitoring data: " . mysqli_error($koneksi));
    }

    if (mysqli_num_rows($result) > 0) {
        // Update existing record
        $row = mysqli_fetch_assoc($result);
        $query = "UPDATE monitoring_kas SET Id_laporan = $id_laporan, saldo_kas = $saldo_kas
                 WHERE id_monitoring = " . $row['id_monitoring'];
    } else {
        // Insert new record
        $query = "INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring)
                 VALUES ($id_laporan, $saldo_kas, '$tanggal')";
    }

    if (!mysqli_query($koneksi, $query)) {
        throw new Exception("Error updating monitoring data: " . mysqli_error($koneksi));
    }
}

/**
 * Process daily report (can be called from trigger or manually)
 */
function prosesLaporanHarian() {
    $bulan = date('m');
    $tahun = date('Y');

    generateLaporanBulanan($bulan, $tahun);
}
?>
