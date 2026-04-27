<?php
/**
 * User Logout Handler
 */

session_start();

require_once 'config/db_connect.php';

// Log the logout action
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $log_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'User Logout', 'User logged out', ?)";
    $log_stmt = $conn->prepare($log_sql);
    
    if ($log_stmt) {
        $log_stmt->bind_param("is", $user_id, $ip);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

$conn->close();

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
