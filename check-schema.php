<?php
require_once 'config/db_connect.php';

echo "=== Documents Table Schema ===\n\n";

$result = $conn->query('SHOW COLUMNS FROM documents');
echo "Columns in documents table:\n";
while ($col = $result->fetch_assoc()) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ") " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
}

echo "\n=== Documents Table Sample Data ===\n";
$result = $conn->query('SELECT id, title, created_by, office_department FROM documents LIMIT 2');
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Title: " . $row['title'] . ", CreatedBy: " . $row['created_by'] . ", Dept: " . ($row['office_department'] ?? 'NULL') . "\n";
}

$conn->close();
?>
