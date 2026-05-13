<?php
session_start();
$_SESSION['user_id'] = 8; // Use the user who created the documents

require_once 'config/db_connect.php';

$admin_id = $_SESSION['user_id'];

// Get admin's department
$admin_details = [];
$sql = "SELECT office_department FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_details = $result->fetch_assoc();
    }
    $stmt->close();
}

$department = $admin_details['office_department'] ?? 'Department';
$escaped_dept = $conn->real_escape_string($department);

echo "=== Staff Dashboard Query Test (UPDATED) ===\n";
echo "Testing for Department: $department (User ID: $admin_id)\n\n";

// UPDATED: Total documents query with legacy support
$result = $conn->query("SELECT COUNT(DISTINCT d.id) as count FROM documents d
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.office_department = '$escaped_dept' OR u.office_department = '$escaped_dept'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total Documents (WITH LEGACY SUPPORT): " . $row['count'] . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
?>
