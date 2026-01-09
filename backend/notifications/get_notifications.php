<?php
session_start();
include '../../config/koneksi.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$id_user = $_SESSION['id_user'];

// Get recent notifications (last 10)
$query = "SELECT id_notification, title, message, type, created_at, is_read
          FROM notifications
          WHERE id_user = '$id_user'
          ORDER BY created_at DESC
          LIMIT 10";

$result = mysqli_query($koneksi, $query);

$notifications = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'id' => $row['id_notification'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'created_at' => $row['created_at'],
            'is_read' => $row['is_read']
        ];
    }
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>
