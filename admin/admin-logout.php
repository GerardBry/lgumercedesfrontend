<?php
/**
 * Admin Logout Handler
 */

session_start();

require_once '../config/db_connect.php';

// Log the logout action
if (isset($_SESSION['user_id'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $log_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'Admin Logout', 'Super Admin logged out', ?)";
    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $log_stmt->bind_param("is", $_SESSION['user_id'], $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

$conn->close();

// Destroy session
session_destroy();

// Redirect to admin login
header('Location: admin-login.php');
exit;
?>
