-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 07 Jan 2026 pada 07.50
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `akurad1`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateLaporanBulanan` (IN `p_bulan` INT, IN `p_tahun` YEAR)   BEGIN
    DECLARE v_periode VARCHAR(20);
    DECLARE v_total_pemasukan DECIMAL(12,2);
    DECLARE v_total_pengeluaran DECIMAL(12,2);
    DECLARE v_saldo_akhir DECIMAL(12,2);
    
    SET v_periode = CONCAT(p_tahun, '-', LPAD(p_bulan, 2, '0'));
    
    -- Hitung total pemasukan dari iuran
    SELECT COALESCE(SUM(jumlah_iuran), 0) INTO v_total_pemasukan
    FROM iuran_rutin 
    WHERE bulan = p_bulan AND tahun = p_tahun 
    AND status_pembayaran = 'Lunas';
    
    -- Hitung total pengeluaran
    SELECT COALESCE(SUM(jumlah_pengeluaran), 0) INTO v_total_pengeluaran
    FROM pengeluaran_kegiatan 
    WHERE MONTH(tanggal_pengeluaran) = p_bulan 
    AND YEAR(tanggal_pengeluaran) = p_tahun;
    
    -- Hitung saldo akhir
    SET v_saldo_akhir = v_total_pemasukan - v_total_pengeluaran;
    
    -- Update atau insert laporan
    INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
    VALUES ('Bulanan', v_periode, v_total_pemasukan, v_total_pengeluaran, v_saldo_akhir)
    ON DUPLICATE KEY UPDATE
        Total_pemasukan = v_total_pemasukan,
        Total_pengeluaran = v_total_pengeluaran,
        Saldo_akhir = v_saldo_akhir;
    
    SELECT 'Laporan berhasil digenerate' AS Status;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `iuran_rutin`
--

CREATE TABLE `iuran_rutin` (
  `id_iuran` int(11) NOT NULL,
  `id_warga` int(11) NOT NULL,
  `bulan` tinyint(2) NOT NULL CHECK (`bulan` between 1 and 12),
  `tahun` year(4) NOT NULL,
  `jumlah_iuran` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tanggal_pembayaran` date DEFAULT NULL,
  `status_pembayaran` enum('Lunas','Belum Lunas','Menunggu') DEFAULT 'Belum Lunas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `keterangan` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `iuran_rutin`
--

INSERT INTO `iuran_rutin` (`id_iuran`, `id_warga`, `bulan`, `tahun`, `jumlah_iuran`, `tanggal_pembayaran`, `status_pembayaran`, `created_at`, `keterangan`, `updated_at`) VALUES
(94, 22, 2, '2026', 150000.00, '2026-01-02', 'Lunas', '2026-01-01 17:41:33', '', '2026-01-01 17:41:33'),
(106, 22, 1, '2026', 150000.00, '2026-01-03', 'Lunas', '2026-01-02 17:10:58', '', '2026-01-02 17:10:58'),
(107, 22, 3, '2026', 150000.00, '2026-01-03', 'Lunas', '2026-01-03 04:32:55', '', '2026-01-03 04:32:55'),
(108, 22, 4, '2026', 150000.00, '2026-01-03', 'Lunas', '2026-01-03 04:32:55', '', '2026-01-03 04:32:55'),
(109, 22, 5, '2026', 150000.00, '2026-01-03', 'Lunas', '2026-01-03 04:32:55', '', '2026-01-03 04:32:55'),
(110, 22, 6, '2026', 150000.00, NULL, 'Belum Lunas', '2026-01-03 08:30:56', '', '2026-01-03 08:30:56'),
(111, 40, 1, '2026', 150000.00, NULL, 'Belum Lunas', '2026-01-03 17:15:07', '', '2026-01-03 17:15:07'),
(112, 22, 8, '2026', 150000.00, NULL, 'Belum Lunas', '2026-01-04 07:08:19', '', '2026-01-04 07:08:19'),
(113, 40, 3, '2026', 150000.00, NULL, 'Belum Lunas', '2026-01-06 08:22:59', '', '2026-01-06 08:22:59'),
(114, 22, 12, '2026', 150000.00, NULL, 'Belum Lunas', '2026-01-06 08:52:27', '', '2026-01-06 08:52:27');

--
-- Trigger `iuran_rutin`
--
DELIMITER $$
CREATE TRIGGER `after_iuran_insert` AFTER INSERT ON `iuran_rutin` FOR EACH ROW BEGIN
    -- Update saldo kas di monitoring
    UPDATE monitoring_kas 
    SET saldo_kas = saldo_kas + NEW.jumlah_iuran
    WHERE tanggal_monitoring = CURDATE();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_laporan_setelah_iuran` AFTER INSERT ON `iuran_rutin` FOR EACH ROW BEGIN
    DECLARE v_periode VARCHAR(20);
    DECLARE v_laporan_id INT;
    
    -- Format periode: YYYY-MM
    SET v_periode = CONCAT(NEW.tahun, '-', LPAD(NEW.bulan, 2, '0'));
    
    -- Cek apakah laporan untuk periode ini sudah ada
    SELECT Id_laporan INTO v_laporan_id 
    FROM laporan_kas 
    WHERE Periode = v_periode AND Jenis_laporan = 'Bulanan';
    
    IF v_laporan_id IS NULL THEN
        -- Buat laporan baru jika belum ada
        INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
        VALUES ('Bulanan', v_periode, NEW.jumlah_iuran, 0, NEW.jumlah_iuran);
    ELSE
        -- Update laporan yang sudah ada
        UPDATE laporan_kas 
        SET Total_pemasukan = Total_pemasukan + NEW.jumlah_iuran,
            Saldo_akhir = Saldo_akhir + NEW.jumlah_iuran
        WHERE Id_laporan = v_laporan_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `laporan_kas`
--

CREATE TABLE `laporan_kas` (
  `Id_laporan` int(11) NOT NULL,
  `Jenis_laporan` enum('Bulanan','Tahunan') NOT NULL,
  `Periode` varchar(20) NOT NULL,
  `Total_pemasukan` decimal(12,2) DEFAULT 0.00,
  `Total_pengeluaran` decimal(12,2) DEFAULT 0.00,
  `Saldo_akhir` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `laporan_kas`
--

INSERT INTO `laporan_kas` (`Id_laporan`, `Jenis_laporan`, `Periode`, `Total_pemasukan`, `Total_pengeluaran`, `Saldo_akhir`) VALUES
(38, 'Bulanan', '2026-01', 11800000.00, 514998.00, 11285002.00),
(39, 'Bulanan', '1-2026', 11650000.00, 0.00, 11650000.00),
(40, 'Bulanan', '2026-02', 750000.00, 0.00, 750000.00),
(41, 'Bulanan', '2-2026', 450000.00, 0.00, 450000.00),
(42, 'Bulanan', '2026-03', 600000.00, 0.00, 600000.00),
(43, 'Bulanan', '3-2026', 300000.00, 0.00, 300000.00),
(44, 'Bulanan', '01-2026', 5450000.00, 120000.00, 5330000.00),
(45, 'Bulanan', '2026-04', 300000.00, 0.00, 300000.00),
(46, 'Bulanan', '2026-05', 300000.00, 0.00, 300000.00),
(47, 'Bulanan', '2026-06', 300000.00, 0.00, 300000.00),
(48, 'Bulanan', '4-2026', 150000.00, 0.00, 150000.00),
(49, 'Bulanan', '5-2026', 150000.00, 0.00, 150000.00),
(50, 'Bulanan', '6-2026', 150000.00, 0.00, 150000.00),
(51, 'Bulanan', '2026-08', 150000.00, 0.00, 150000.00),
(52, 'Bulanan', '2026-12', 150000.00, 0.00, 150000.00);

--
-- Trigger `laporan_kas`
--
DELIMITER $$
CREATE TRIGGER `update_monitoring_dari_laporan` AFTER UPDATE ON `laporan_kas` FOR EACH ROW BEGIN
    DECLARE v_monitoring_id INT;
    DECLARE v_tanggal DATE;
    
    -- Konversi periode ke tanggal (pertama bulan)
    SET v_tanggal = CONCAT(NEW.Periode, '-01');
    
    -- Cek apakah monitoring untuk tanggal ini sudah ada
    SELECT id_monitoring INTO v_monitoring_id 
    FROM monitoring_kas 
    WHERE tanggal_monitoring = v_tanggal;
    
    IF v_monitoring_id IS NULL THEN
        -- Buat monitoring baru
        INSERT INTO monitoring_kas (Id_laporan, saldo_kas, tanggal_monitoring)
        VALUES (NEW.Id_laporan, NEW.Saldo_akhir, v_tanggal);
    ELSE
        -- Update monitoring yang sudah ada
        UPDATE monitoring_kas 
        SET saldo_kas = NEW.Saldo_akhir,
            Id_laporan = NEW.Id_laporan
        WHERE id_monitoring = v_monitoring_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `monitoring_kas`
--

CREATE TABLE `monitoring_kas` (
  `id_monitoring` int(11) NOT NULL,
  `Id_laporan` int(11) DEFAULT NULL,
  `saldo_kas` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grafik_pemasukan` text DEFAULT NULL,
  `grafik_pengeluaran` text DEFAULT NULL,
  `tanggal_monitoring` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `monitoring_kas`
--

INSERT INTO `monitoring_kas` (`id_monitoring`, `Id_laporan`, `saldo_kas`, `grafik_pemasukan`, `grafik_pengeluaran`, `tanggal_monitoring`, `created_at`) VALUES
(23, 38, 11285002.00, NULL, NULL, '2026-01-01', '2025-12-31 19:24:04'),
(24, 43, 300000.00, NULL, NULL, '0000-00-00', '2025-12-31 20:29:40'),
(25, 50, 18230000.00, NULL, NULL, '2026-01-02', '2026-01-01 17:11:09'),
(26, 40, 750000.00, NULL, NULL, '2026-02-01', '2026-01-01 17:41:33'),
(27, 42, 600000.00, NULL, NULL, '2026-03-01', '2026-01-02 10:04:24'),
(28, 44, 5930000.00, NULL, NULL, '2026-01-03', '2026-01-02 17:08:11'),
(29, 45, 300000.00, NULL, NULL, '2026-04-01', '2026-01-03 04:32:55'),
(30, 46, 300000.00, NULL, NULL, '2026-05-01', '2026-01-03 04:32:55'),
(31, 47, 300000.00, NULL, NULL, '2026-06-01', '2026-01-03 08:30:56'),
(32, 44, 5630000.00, NULL, NULL, '2026-01-04', '2026-01-03 17:04:48'),
(33, 44, 5620000.00, NULL, NULL, '2026-01-06', '2026-01-05 19:18:57'),
(34, 44, 5330000.00, NULL, NULL, '2026-01-07', '2026-01-06 17:09:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id_notification` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id_notification`, `id_user`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 21, 'Pembayaran Iuran Baru', 'Naufal Ruanda Razha telah melakukan pembayaran iuran. Silakan verifikasi pembayaran.', 'payment', 1, '2026-01-03 07:02:10'),
(2, 21, 'Pembayaran Iuran Baru', 'Naufal Ruanda Razha telah melakukan pembayaran iuran. Silakan verifikasi pembayaran.', 'payment', 1, '2026-01-03 07:02:23'),
(3, 21, 'Pembayaran Iuran Baru', 'Naufal Ruanda Razha telah melakukan pembayaran iuran. Silakan verifikasi pembayaran.', 'payment', 1, '2026-01-03 07:09:23'),
(4, 21, 'Pembayaran Iuran Baru', 'Naufal Ruanda Razha telah melakukan pembayaran iuran. Silakan verifikasi pembayaran.', 'payment', 1, '2026-01-03 07:14:01'),
(5, 22, 'Pembayaran Iuran Dikonfirmasi', 'Halo Naufal Ruanda Razha, pembayaran iuran Anda untuk bulan Mei 2026 telah dikonfirmasi oleh Bendahara.', 'info', 0, '2026-01-03 07:20:46'),
(6, 21, 'Pembayaran Iuran Baru', 'Naufal Ruanda Razha telah melakukan pembayaran iuran. Silakan verifikasi pembayaran.', 'payment', 1, '2026-01-03 08:31:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `nominal` int(11) DEFAULT NULL,
  `status` enum('PENDING','LUNAS','GAGAL') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `order_id`, `nominal`, `status`, `created_at`) VALUES
(1, 'IURAN-1767490145', 150000, 'PENDING', '2026-01-04 01:29:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran_kegiatan`
--

CREATE TABLE `pengeluaran_kegiatan` (
  `id_pengeluaran` int(11) NOT NULL,
  `tanggal_pengeluaran` date NOT NULL,
  `deskripsi` varchar(200) NOT NULL,
  `jumlah_pengeluaran` int(50) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kategori` varchar(50) NOT NULL,
  `diajukan_oleh` varchar(100) NOT NULL,
  `bukti` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengeluaran_kegiatan`
--

INSERT INTO `pengeluaran_kegiatan` (`id_pengeluaran`, `tanggal_pengeluaran`, `deskripsi`, `jumlah_pengeluaran`, `id_user`, `created_at`, `kategori`, `diajukan_oleh`, `bukti`) VALUES
(29, '2026-01-01', 'iuran sampah', 20000, 21, '2025-12-31 22:06:38', 'Kebersihan', 'Riri Cinta', NULL),
(30, '2026-01-01', 'SKCK', 100000, 21, '2026-01-01 16:08:53', 'Administrasi', 'Riri Cinta', NULL),
(31, '2026-01-02', 'sampah komplek', 100000, 21, '2026-01-02 10:07:23', 'Kebersihan', 'Riri Cinta', NULL),
(32, '2026-01-03', 'jalan raya', 150000, 21, '2026-01-03 06:33:03', 'Perbaikan', 'riri', '1767421983_6958b81f890fb.png'),
(33, '2026-01-06', 'jalan bolong', 10000, 21, '2026-01-06 08:53:07', 'Perbaikan', 'cinta', NULL);

--
-- Trigger `pengeluaran_kegiatan`
--
DELIMITER $$
CREATE TRIGGER `after_pengeluaran_insert` AFTER INSERT ON `pengeluaran_kegiatan` FOR EACH ROW BEGIN
    -- Update saldo kas di monitoring
    UPDATE monitoring_kas 
    SET saldo_kas = saldo_kas - NEW.jumlah_pengeluaran
    WHERE tanggal_monitoring = CURDATE();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_laporan_setelah_pengeluaran` AFTER INSERT ON `pengeluaran_kegiatan` FOR EACH ROW BEGIN
    DECLARE v_periode VARCHAR(20);
    DECLARE v_laporan_id INT;
    DECLARE v_bulan INT;
    DECLARE v_tahun YEAR;
    
    -- Ekstrak bulan dan tahun dari tanggal pengeluaran
    SET v_bulan = MONTH(NEW.tanggal_pengeluaran);
    SET v_tahun = YEAR(NEW.tanggal_pengeluaran);
    SET v_periode = CONCAT(v_tahun, '-', LPAD(v_bulan, 2, '0'));
    
    -- Cek apakah laporan untuk periode ini sudah ada
    SELECT Id_laporan INTO v_laporan_id 
    FROM laporan_kas 
    WHERE Periode = v_periode AND Jenis_laporan = 'Bulanan';
    
    IF v_laporan_id IS NULL THEN
        -- Buat laporan baru jika belum ada
        INSERT INTO laporan_kas (Jenis_laporan, Periode, Total_pemasukan, Total_pengeluaran, Saldo_akhir)
        VALUES ('Bulanan', v_periode, 0, NEW.jumlah_pengeluaran, -NEW.jumlah_pengeluaran);
    ELSE
        -- Update laporan yang sudah ada
        UPDATE laporan_kas 
        SET Total_pengeluaran = Total_pengeluaran + NEW.jumlah_pengeluaran,
            Saldo_akhir = Saldo_akhir - NEW.jumlah_pengeluaran
        WHERE Id_laporan = v_laporan_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nik` varchar(16) NOT NULL,
  `rt_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('RT','Bendahara','warga') NOT NULL,
  `id_warga` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`id_user`, `username`, `nik`, `rt_number`, `password`, `role`, `id_warga`, `created_at`, `email`) VALUES
(20, 'tulus', '2175502867900034', 'RT 001', '$2y$10$P.q65aZggJHBNnLvhAbi3.PvfVhKnzLijT.QZJoIZEwfwWH/Pj0Qi', 'RT', 20, '2025-12-26 03:13:21', 'tulus@gmail.com'),
(21, 'Amanda Cinta', '2175502867900036', 'RT 001', '$2y$10$5pY339Ac8D/Ek8PujYLwme0e29Zl.FtlWJBhvu78Tagoq5NVVc3SO', 'Bendahara', 21, '2025-12-26 03:14:28', 'cintaamandariri@gmail.com'),
(22, 'naufal_23', '2175502867900035', 'RT 001', '$2y$10$2dGdNmGOGXu2czo5a4QLoO8hhftt7tBH3O6.8QAUMXJ9TIge5j08O', 'warga', 22, '2025-12-26 03:15:38', 'naufal@gmail.com'),
(40, 'imanuel54', '9999999999999998', 'RT 001', '$2y$10$P9Or./NbrzjQzyabpQ2dOuevVLhLf.2ig2tYIkA1Z.rws2RHW8Z16', 'warga', 40, '2026-01-03 12:45:14', 'imanuel@gmail.com'),
(43, 'cia', '4444444444444444', 'RT 001', '$2y$10$l5a.Cn7PknHrR8Mh84ToNeGhZkBvZlcNO/wbV5wlzSbcXALxjm.H.', 'warga', 43, '2026-01-06 15:57:57', 'cia@gmail.com');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id_preference` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `tagihan_notifications` tinyint(1) DEFAULT 1,
  `laporan_bulanan` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_notification_preferences`
--

INSERT INTO `user_notification_preferences` (`id_preference`, `id_user`, `email_notifications`, `tagihan_notifications`, `laporan_bulanan`, `created_at`, `updated_at`) VALUES
(1, 21, 1, 1, 1, '2026-01-06 07:50:08', '2026-01-06 07:50:08'),
(2, 20, 1, 1, 1, '2026-01-06 15:39:57', '2026-01-06 15:39:57'),
(3, 22, 1, 1, 1, '2026-01-06 17:59:25', '2026-01-06 18:01:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `warga`
--

CREATE TABLE `warga` (
  `id_warga` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `status` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `alamat` varchar(255) NOT NULL,
  `no_telepon` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `warga`
--

INSERT INTO `warga` (`id_warga`, `nama_lengkap`, `status`, `created_at`, `updated_at`, `alamat`, `no_telepon`) VALUES
(20, 'tulus prat', 'Aktif', '2025-12-26 03:13:21', '2026-01-06 15:39:48', '', '089673854535'),
(21, 'cinta r', 'Aktif', '2025-12-26 03:14:28', '2026-01-06 15:27:39', '', '089673854535'),
(22, 'Naufal67', 'Aktif', '2025-12-26 03:15:38', '2026-01-06 18:01:49', '', '089673854532'),
(36, 'Muhammad Farhan', 'Aktif', '2026-01-03 02:55:37', '2026-01-03 02:55:37', '', ''),
(39, 'Sara Indah Manurung', 'Aktif', '2026-01-03 12:21:06', '2026-01-03 12:21:06', '', ''),
(40, 'imanuel', 'Aktif', '2026-01-03 12:45:14', '2026-01-03 12:45:14', '', ''),
(43, 'ciaa', 'Aktif', '2026-01-06 15:57:57', '2026-01-06 15:57:57', '', '');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `iuran_rutin`
--
ALTER TABLE `iuran_rutin`
  ADD PRIMARY KEY (`id_iuran`),
  ADD UNIQUE KEY `unique_iuran_periode` (`id_warga`,`bulan`,`tahun`),
  ADD KEY `idx_iuran_warga` (`id_warga`),
  ADD KEY `idx_iuran_periode` (`bulan`,`tahun`),
  ADD KEY `idx_iuran_tanggal` (`tanggal_pembayaran`),
  ADD KEY `idx_iuran_status` (`status_pembayaran`);

--
-- Indeks untuk tabel `laporan_kas`
--
ALTER TABLE `laporan_kas`
  ADD PRIMARY KEY (`Id_laporan`);

--
-- Indeks untuk tabel `monitoring_kas`
--
ALTER TABLE `monitoring_kas`
  ADD PRIMARY KEY (`id_monitoring`),
  ADD KEY `idx_monitoring_laporan` (`Id_laporan`),
  ADD KEY `idx_monitoring_tanggal` (`tanggal_monitoring`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id_notification`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengeluaran_kegiatan`
--
ALTER TABLE `pengeluaran_kegiatan`
  ADD PRIMARY KEY (`id_pengeluaran`),
  ADD KEY `idx_pengeluaran_tanggal` (`tanggal_pengeluaran`),
  ADD KEY `idx_pengeluaran_user` (`id_user`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_user_username` (`username`),
  ADD KEY `idx_user_role` (`role`),
  ADD KEY `idx_user_warga` (`id_warga`);

--
-- Indeks untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id_preference`),
  ADD UNIQUE KEY `unique_user_preference` (`id_user`);

--
-- Indeks untuk tabel `warga`
--
ALTER TABLE `warga`
  ADD PRIMARY KEY (`id_warga`),
  ADD KEY `idx_warga_nama` (`nama_lengkap`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `iuran_rutin`
--
ALTER TABLE `iuran_rutin`
  MODIFY `id_iuran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT untuk tabel `laporan_kas`
--
ALTER TABLE `laporan_kas`
  MODIFY `Id_laporan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT untuk tabel `monitoring_kas`
--
ALTER TABLE `monitoring_kas`
  MODIFY `id_monitoring` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id_notification` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran_kegiatan`
--
ALTER TABLE `pengeluaran_kegiatan`
  MODIFY `id_pengeluaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id_preference` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `warga`
--
ALTER TABLE `warga`
  MODIFY `id_warga` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `iuran_rutin`
--
ALTER TABLE `iuran_rutin`
  ADD CONSTRAINT `iuran_rutin_ibfk_1` FOREIGN KEY (`id_warga`) REFERENCES `warga` (`id_warga`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `monitoring_kas`
--
ALTER TABLE `monitoring_kas`
  ADD CONSTRAINT `fk_monitoring_laporan` FOREIGN KEY (`Id_laporan`) REFERENCES `laporan_kas` (`Id_laporan`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `pengeluaran_kegiatan`
--
ALTER TABLE `pengeluaran_kegiatan`
  ADD CONSTRAINT `pengeluaran_kegiatan_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`id_warga`) REFERENCES `warga` (`id_warga`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD CONSTRAINT `user_notification_preferences_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
