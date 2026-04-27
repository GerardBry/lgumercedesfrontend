<?php
/**
 * DEBUG: Check Incoming Documents for Admin
 */
session_start();

if (empty($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

require_once '../config/db_connect.php';

$user_id = $_SESSION['user_id'];

echo "<h1>DEBUG - Administrative Incoming</h1>";
echo "<p><strong>Your User ID:</strong> " . $user_id . "</p>";
echo "<p><strong>Your Role:</strong> " . ($_SESSION['role'] ?? 'Unknown') . "</p>";

// Check ALL document assignments assigned to this user
echo "<h2>ALL Document Assignments Assigned TO You (assigned_to = " . $user_id . ")</h2>";
$sql = "SELECT 
    da.id, 
    da.document_id, 
    da.assigned_by, 
    da.assigned_to, 
    da.status, 
    d.title,
    d.tracking_number,
    u_sender.first_name as sender_name,
    u_assigner.first_name as assigner_name
FROM document_assignments da
LEFT JOIN documents d ON da.document_id = d.id
LEFT JOIN users u_sender ON d.sender_id = u_sender.id
LEFT JOIN users u_assigner ON da.assigned_by = u_assigner.id
WHERE da.assigned_to = ?
ORDER BY da.assigned_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Doc ID</th><th>Title</th><th>Tracking</th><th>Status</th><th>Assigned By</th><th>Assigned To</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['document_id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['tracking_number'] . "</td>";
    echo "<td><strong>" . $row['status'] . "</strong></td>";
    echo "<td>" . $row['assigner_name'] . " (ID: " . $row['assigned_by'] . ")</td>";
    echo "<td>" . $row['assigned_to'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check specifically for Forwarded status
echo "<h2>Documents with Status = 'Forwarded' Assigned TO You</h2>";
$sql2 = "SELECT 
    da.id, 
    da.status, 
    d.title,
    da.assigned_by,
    da.assigned_to
FROM document_assignments da
LEFT JOIN documents d ON da.document_id = d.id
WHERE da.assigned_to = ? AND da.status = 'Forwarded'";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$count = $result2->num_rows;

echo "<p><strong>Count:</strong> " . $count . " documents</p>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Assigned By</th><th>Assigned To</th></tr>";
while ($row = $result2->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['assigned_by'] . "</td>";
    echo "<td>" . $row['assigned_to'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
