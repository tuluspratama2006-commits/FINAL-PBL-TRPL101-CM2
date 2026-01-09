<?php
require_once '../../config/koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES user(id_user)
)";

if (mysqli_query($koneksi, $sql)) {
    echo "Table notifications created successfully";
} else {
    echo "Error creating table: " . mysqli_error($koneksi);
}
?>
