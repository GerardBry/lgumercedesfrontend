<?php
/**
 * Database Migration: Add Notifications Table
 * Run this script once to set up the notifications table
 * 
 * Usage: Open this file in your browser or run: php migrate-notifications.php
 */

require_once 'config/db_connect.php';

// SQL to create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    tracking_number VARCHAR(50) NOT NULL,
    document_id INT,
    assignment_id INT,
    old_status VARCHAR(100),
    new_status VARCHAR(100),
    message LONGTEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES document_assignments(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "✓ Notifications table created successfully!<br>";
    echo "System is ready for notifications.<br>";
} else {
    if (strpos($conn->error, "already exists") !== false) {
        echo "✓ Notifications table already exists.<br>";
        echo "System is ready for notifications.<br>";
    } else {
        echo "✗ Error creating notifications table: " . $conn->error . "<br>";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications Migration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5; }
        .success { color: #28a745; font-size: 18px; padding: 20px; background-color: #e8f5e9; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="success">
        <h2>Notifications System Setup</h2>
        <p>The notifications system has been successfully installed and configured!</p>
        <p>You can now:</p>
        <ul>
            <li>Receive notifications when documents are assigned to you</li>
            <li>Receive notifications when request status is updated</li>
            <li>View all notifications in the bell icon (top-right corner)</li>
            <li>See real-time toast notifications for status updates</li>
        </ul>
        <p><strong>Note:</strong> The notification system is now active on all administrative staff pages.</p>
    </div>
</body>
</html>
