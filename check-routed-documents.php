<?php
require_once 'config/db_connect.php';

// Check documents with tracking numbers and assignments
$sql = "SELECT 
    d.id,
    d.title,
    d.tracking_number,
    d.sender_name,
    d.file_path,
    d.created_by,
    COUNT(da.id) as assignment_count
FROM documents d
LEFT JOIN document_assignments da ON d.id = da.document_id
WHERE d.tracking_number IS NOT NULL AND d.tracking_number != ''
GROUP BY d.id
ORDER BY d.id DESC
LIMIT 10";

$result = $conn->query($sql);

echo "<h2>Routed Documents - File Path Status</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Title</th><th>Tracking</th><th>Sender</th><th>File Path</th><th>Assignments</th></tr>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tracking_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sender_name']) . "</td>";
        echo "<td>" . (empty($row['file_path']) ? '<span style="color:red;">EMPTY</span>' : htmlspecialchars($row['file_path'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['assignment_count']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>No routed documents found</td></tr>";
}

echo "</table>";

$conn->close();
?>
