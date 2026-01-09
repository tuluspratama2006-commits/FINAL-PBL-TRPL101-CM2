<?php
session_start();
require_once '../../config/koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit();
}

$email_notifications = isset($_POST['email_notifikasi']) ? 1 : 0;
$tagihan_notifications = isset($_POST['notifikasi_tagihan']) ? 1 : 0;
$laporan_bulanan = isset($_POST['laporan_bulanan']) ? 1 : 0;

// Check if user already has preferences
$query_check = "SELECT id_preference FROM user_notification_preferences WHERE id_user = '$id_user'";
$result_check = mysqli_query($koneksi, $query_check);

if (mysqli_num_rows($result_check) > 0) {
    // Update existing preferences
    $query = "UPDATE user_notification_preferences SET
              email_notifications = '$email_notifications',
              tagihan_notifications = '$tagihan_notifications',
              laporan_bulanan = '$laporan_bulanan',
              updated_at = NOW()
              WHERE id_user = '$id_user'";
} else {
    // Insert new preferences
    $query = "INSERT INTO user_notification_preferences
              (id_user, email_notifications, tagihan_notifications, laporan_bulanan)
              VALUES ('$id_user', '$email_notifications', '$tagihan_notifications', '$laporan_bulanan')";
}

if (mysqli_query($koneksi, $query)) {
    echo json_encode(['success' => true, 'message' => 'Notification preferences saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save preferences: ' . mysqli_error($koneksi)]);
}
?>
