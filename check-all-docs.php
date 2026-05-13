<?php
require_once 'config/db_connect.php';

// Get all documents with their status
$result = $conn->query('SELECT id, title, sender_name, date_received, classification, priority, notes FROM documents ORDER BY id DESC LIMIT 10');
echo "=== ALL DOCUMENTS ===\n";
while ($doc = $result->fetch_assoc()) {
    echo "\nDoc ID: " . $doc['id'] . " | Title: " . $doc['title'] . "\n";
    echo "  sender_name: " . ($doc['sender_name'] ?? 'NULL') . "\n";
    echo "  date_received: " . ($doc['date_received'] ?? 'NULL') . "\n";
    echo "  classification: " . ($doc['classification'] ?? 'NULL') . "\n";
    echo "  priority: " . ($doc['priority'] ?? 'NULL') . "\n";
    echo "  notes: " . substr($doc['notes'] ?? 'NULL', 0, 100) . "\n";
}
?>
