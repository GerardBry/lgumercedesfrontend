<?php
require_once 'config/db_connect.php';

echo "=== DOCUMENT_ASSIGNMENTS TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE document_assignments");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . " | Default: " . ($row['Default'] ?? 'NULL') . "\n";
}
?>
