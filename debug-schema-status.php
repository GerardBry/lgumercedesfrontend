<?php
require_once 'config/db_connect.php';

echo "<h2>Document Assignments Table Schema</h2>";
$result = $conn->query("DESCRIBE document_assignments");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
