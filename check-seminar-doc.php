<?php
require_once 'config/db_connect.php';

echo "=== CHECK DOCUMENT DATA ===\n";
$result = $conn->query('SELECT id, title, tracking_number, sender_name, date_received, classification, priority, notes FROM documents WHERE title = "Seminar Workshop" LIMIT 1');
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "ID: " . $row['id'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "Tracking: " . $row['tracking_number'] . "\n";
    echo "Sender Name: " . ($row['sender_name'] ?? 'NULL') . "\n";
    echo "Date Received: " . ($row['date_received'] ?? 'NULL') . "\n";
    echo "Classification: " . ($row['classification'] ?? 'NULL') . "\n";
    echo "Priority: " . ($row['priority'] ?? 'NULL') . "\n";
    echo "Notes JSON:\n";
    echo $row['notes'] . "\n";
} else {
    echo "Document not found";
}
?>
