<?php
// Load file koneksi.php
include "koneksi.php";

// Tangkap data dari form
$nama = $_POST['nama'];
$alamat = $_POST['alamat'];
$email = $_POST['email'];
$biaya = 100000;
$order_id = rand();
$transaction_status = 1;

// menginput data ke database
// PERBAIKAN: mysqli_query (bukan mysali_query) dan perbaiki sintaks SQL
mysqli_query($koneksi, "INSERT INTO klien (nama, alamat, biaya, order_id, transaction_status, email) 
                         VALUES ('$nama', '$alamat', '$biaya', '$order_id', '$transaction_status', '$email')");

// mengalihkan halaman kembali ke index.php
header('location:./midtrans/examples/snap/checkout-process.php?order_id=');
?>