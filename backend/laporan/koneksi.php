<?php
/**
 * Database Connection Configuration
 * File: backend/laporan/koneksi.php
 */

// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "akurad1";

// Establish database connection
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset to UTF-8 for proper encoding
mysqli_set_charset($koneksi, "utf8");
?>
