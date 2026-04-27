<?php
/**
 * DEBUG: Inspect Specific Assignment #24
 */
session_start();

require_once 'config/db_connect.php';

echo "<h1>DEBUG - Deep Inspection of Assignment #24</h1>";

$assignment_id = 24;

// Get RAW assignment data
$sql = "SELECT * FROM document_assignments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    echo "<h2>Raw Assignment Data</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    foreach ($assignment as $key => $value) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        echo "<td>" . (is_null($value) ? '<em>NULL</em>' : htmlspecialchars($value)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Also get the related document
    echo "<h2>Related Document Data</h2>";
    $doc_id = $assignment['document_id'];
    $sql_doc = "SELECT * FROM documents WHERE id = ?";
    $stmt_doc = $conn->prepare($sql_doc);
    $stmt_doc->bind_param("i", $doc_id);
    $stmt_doc->execute();
    $result_doc = $stmt_doc->get_result();
    
    if ($result_doc->num_rows > 0) {
        $document = $result_doc->fetch_assoc();
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        foreach ($document as $key => $value) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
            echo "<td>" . (is_null($value) ? '<em>NULL</em>' : htmlspecialchars(substr($value, 0, 100))) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Get related users
    echo "<h2>Related Users</h2>";
    $assigned_by = $assignment['assigned_by'];
    $assigned_to = $assignment['assigned_to'];
    
    $sql_users = "SELECT id, first_name, last_name, role FROM users WHERE id IN (?, ?)";
    $stmt_users = $conn->prepare($sql_users);
    $stmt_users->bind_param("ii", $assigned_by, $assigned_to);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Role</th></tr>";
    while ($user = $result_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['first_name'] . " " . $user['last_name'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Assignment #24 not found!</p>";
}

// Also show recent assignments created
echo "<h2>5 Most Recent Assignments (any status)</h2>";
$sql_recent = "SELECT id, document_id, assigned_by, assigned_to, status, assigned_at FROM document_assignments ORDER BY assigned_at DESC LIMIT 5";
$result_recent = $conn->query($sql_recent);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Doc ID</th><th>By</th><th>To</th><th>Status</th><th>Created At</th></tr>";
while ($row = $result_recent->fetch_assoc()) {
    $highlight = ($row['id'] == 24) ? " style='background-color: yellow;'" : "";
    echo "<tr$highlight>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['document_id'] . "</td>";
    echo "<td>" . $row['assigned_by'] . "</td>";
    echo "<td>" . $row['assigned_to'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . substr($row['assigned_at'], 0, 16) . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
