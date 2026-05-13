<?php
require_once 'config/db_connect.php';

echo "=== DOCUMENTS TABLE STRUCTURE ===\n";
$result = $conn->query("DESCRIBE documents");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | Null: " . $row['Null'] . " | Default: " . ($row['Default'] ?? 'NULL') . "\n";
}
?>
