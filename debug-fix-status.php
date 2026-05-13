<?php
require_once 'config/db_connect.php';

echo "<h2>Fixing NULL Status Values</h2>";

// Update any NULL status assignments to 'Pending'
$result = $conn->query("UPDATE document_assignments SET status = 'Pending' WHERE status IS NULL");

echo "Rows updated: " . $conn->affected_rows . "<br>";
echo "<hr>";

// Show the updated assignments
echo "<h3>Updated Assignments for User 8:</h3>";
$result = $conn->query("
    SELECT 
        da.id,
        da.document_id,
        da.assigned_to,
        da.status,
        d.title
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    WHERE da.assigned_to = 8
    ORDER BY da.id DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>AssignID</th><th>DocID</th><th>Title</th><th>AssignedTo</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['document_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . htmlspecialchars($row['assigned_to']) . "</td>";
    echo "<td><strong>" . htmlspecialchars($row['status']) . "</strong></td>";
    echo "</tr>";
}
echo "</table>";
echo "<br><p>Now refresh your Incoming page to see the documents!</p>";

$conn->close();
?>
