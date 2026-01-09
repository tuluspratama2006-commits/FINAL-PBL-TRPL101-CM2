<?php
session_start();
include '../../config/koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_notification'])) {
    $id_notification = $_POST['id_notification'];
    $id_user = $_SESSION['id_user'];

    // Update notification as read
    $query = "UPDATE notifications SET is_read = 1 WHERE id_notification = '$id_notification' AND id_user = '$id_user'";
    $result = mysqli_query($koneksi, $query);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
