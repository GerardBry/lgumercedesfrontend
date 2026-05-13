<?php
require_once 'config/db_connect.php';

echo "=== Document Count Check ===\n\n";

$result = $conn->query('SELECT COUNT(*) as count FROM documents');
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total documents in DB: " . $row['count'] . "\n";
}

echo "\nSample documents:\n";
$result2 = $conn->query('SELECT id, title, status, created_by FROM documents LIMIT 5');
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        echo "  ID: " . $row['id'] . ", Title: " . $row['title'] . ", Status: " . $row['status'] . ", Created By: " . $row['created_by'] . "\n";
    }
}

$conn->close();
?>
