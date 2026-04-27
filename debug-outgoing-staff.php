<?php
/**
 * DEBUG: Check Document Assignments Created BY Staff  
 */
session_start();

if (empty($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];

echo "<h1>DEBUG - Your Outgoing Documents (Staff)</h1>";
echo "<p><strong>Your User ID:</strong> " . $user_id . "</p>";
echo "<p><strong>Your Role:</strong> " . ($_SESSION['role'] ?? 'Unknown') . "</p>";

// Check ALL documents created by this user
echo "<h2>ALL Documents You Created (created_by = " . $user_id . ")</h2>";
$sql = "SELECT 
    d.id,
    d.title,
    d.tracking_number,
    d.status,
    d.created_by,
    d.sender_id,
    d.date_sent
FROM documents d
WHERE d.created_by = ?
ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Doc ID</th><th>Title</th><th>Tracking</th><th>Status</th><th>Sender ID</th><th>Date Sent</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['tracking_number'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['sender_id'] . "</td>";
    echo "<td>" . $row['date_sent'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check ALL document assignments created BY you (assigned_by = you)
echo "<h2>ALL Assignments You Created (assigned_by = " . $user_id . ")</h2>";
$sql2 = "SELECT 
    da.id,
    da.document_id,
    da.assigned_by,
    da.assigned_to,
    da.status,
    d.title,
    u.first_name as recipient_name
FROM document_assignments da
LEFT JOIN documents d ON da.document_id = d.id
LEFT JOIN users u ON da.assigned_to = u.id
WHERE da.assigned_by = ?
ORDER BY da.assigned_at DESC";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Assignment ID</th><th>Doc ID</th><th>Title</th><th>Status</th><th>Assigned By</th><th>Assigned To (Recipient)</th></tr>";
while ($row = $result2->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['document_id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['assigned_by'] . "</td>";
    echo "<td>" . $row['assigned_to'] . " (" . $row['recipient_name'] . ")</td>";
    echo "</tr>";
}
echo "</table>";

// Specifically look for Forwarded documents
echo "<h2>Assignments With Status = 'Forwarded' Created BY You</h2>";
$sql3 = "SELECT 
    da.id,
    da.status,
    d.title,
    da.assigned_to
FROM document_assignments da
LEFT JOIN documents d ON da.document_id = d.id
WHERE da.assigned_by = ? AND da.status = 'Forwarded'";

$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$result3 = $stmt3->get_result();
$count = $result3->num_rows;

echo "<p><strong>Count:</strong> " . $count . " documents</p>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Assigned To</th></tr>";
while ($row = $result3->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['assigned_to'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
