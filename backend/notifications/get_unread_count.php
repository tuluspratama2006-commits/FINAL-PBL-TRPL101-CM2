<?php
session_start();
include '../../config/koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$id_user = $_SESSION['id_user'];

// Count unread notifications
$query = "SELECT COUNT(*) as count FROM notifications WHERE id_user = '$id_user' AND is_read = 0";
$result = mysqli_query($koneksi, $query);

if ($result) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'count' => $data['count']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to get count']);
}
?>
