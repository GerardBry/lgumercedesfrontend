<?php
session_start();
require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

echo "<h2>DEBUG INFO</h2>";
echo "Current User ID: " . htmlspecialchars($user_id) . "<br>";
echo "Current Role: " . htmlspecialchars($role) . "<br>";
echo "<hr>";

if ($user_id) {
    // Check user details
    $stmt = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo "<h3>User Details:</h3>";
        echo "ID: " . htmlspecialchars($row['id']) . "<br>";
        echo "Name: " . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "<br>";
        echo "Role: " . htmlspecialchars($row['role']) . "<br>";
    }
    $stmt->close();
    echo "<hr>";
    
    // Check all administrative staff
    $stmt = $conn->prepare("SELECT id, first_name, last_name, role FROM users WHERE role = ?");
    $admin_role = 'Administrative Assistant';
    $stmt->bind_param("s", $admin_role);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<h3>All Administrative Staff:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . htmlspecialchars($row['id']) . " - " . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "<br>";
    }
    $stmt->close();
    echo "<hr>";
    
    // Check documents
    echo "<h3>All Documents:</h3>";
    $stmt = $conn->prepare("SELECT id, tracking_number, title, created_by, status FROM documents ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . htmlspecialchars($row['id']) . " | Tracking: " . htmlspecialchars($row['tracking_number']) . " | Title: " . htmlspecialchars($row['title']) . " | CreatedBy: " . htmlspecialchars($row['created_by']) . " | Status: " . htmlspecialchars($row['status']) . "<br>";
    }
    $stmt->close();
    echo "<hr>";
    
    // Check document assignments for current user
    echo "<h3>Document Assignments for Current User (ID: " . htmlspecialchars($user_id) . "):</h3>";
    $stmt = $conn->prepare("
        SELECT 
            da.id,
            da.document_id,
            da.assigned_to,
            da.status,
            d.tracking_number,
            d.title
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        WHERE da.assigned_to = ?
        ORDER BY da.assigned_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Found: " . $result->num_rows . " assignments<br>";
    while ($row = $result->fetch_assoc()) {
        echo "AssignID: " . htmlspecialchars($row['id']) . " | DocID: " . htmlspecialchars($row['document_id']) . " | Tracking: " . htmlspecialchars($row['tracking_number']) . " | Status: " . htmlspecialchars($row['status']) . " | Title: " . htmlspecialchars($row['title']) . "<br>";
    }
    $stmt->close();
    echo "<hr>";
    
    // Check all document assignments (for debugging)
    echo "<h3>All Document Assignments (LAST 30):</h3>";
    $stmt = $conn->prepare("
        SELECT 
            da.id,
            da.document_id,
            da.assigned_to,
            da.assigned_by,
            da.status,
            da.assigned_at,
            d.tracking_number,
            d.title,
            u.first_name as admin_name
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users u ON da.assigned_to = u.id
        ORDER BY da.id DESC
        LIMIT 30
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>AssignID</th><th>DocID</th><th>Title</th><th>AssignedTo</th><th>AdminName</th><th>AssignedBy</th><th>Status</th><th>AssignedAt</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['document_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_to']) . "</td>";
        echo "<td>" . htmlspecialchars($row['admin_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_by']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['assigned_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    $stmt->close();
}

$conn->close();
?>
