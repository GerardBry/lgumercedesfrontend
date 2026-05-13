<?php
require_once 'config/db_connect.php';

$sql = "SELECT id, title, tracking_number, sender_name, file_path, created_at FROM documents ORDER BY id DESC LIMIT 10";
$result = $conn->query($sql);

echo "<h2>Recent Documents - File Path Check</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Title</th><th>Tracking</th><th>Sender</th><th>File Path</th><th>Created</th></tr>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tracking_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sender_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['file_path']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>No documents found</td></tr>";
}

echo "</table>";

$conn->close();
?>
