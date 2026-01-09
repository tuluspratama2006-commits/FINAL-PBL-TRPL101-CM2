<?php
require_once '../../config/koneksi.php';

// Create user_notification_preferences table
$query = "CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id_preference INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    email_notifications BOOLEAN DEFAULT 1,
    tagihan_notifications BOOLEAN DEFAULT 1,
    laporan_bulanan BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (id_user)
)";

if (mysqli_query($koneksi, $query)) {
    echo "Table user_notification_preferences created successfully";
} else {
    echo "Error creating table: " . mysqli_error($koneksi);
}
?>
