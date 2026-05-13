<?php
require_once 'config/db_connect.php';

echo "=== RECENT ASSIGNMENTS ===\n";
$result = $conn->query('SELECT da.id, da.document_id, da.assigned_to, da.status, da.assigned_at, d.tracking_number FROM document_assignments da LEFT JOIN documents d ON da.document_id = d.id ORDER BY da.id DESC LIMIT 15');
while($row = $result->fetch_assoc()) {
    $status = $row['status'] ?? 'NULL';
    echo "AssID: " . $row['id'] . " | DocID: " . $row['document_id'] . " | To: " . $row['assigned_to'] . " | Status: " . $status . " | Tracking: " . ($row['tracking_number'] ?? 'NULL') . "\n";
}

echo "\n=== ROUTED DOCUMENTS ===\n";
$result = $conn->query('SELECT id, tracking_number, title FROM documents WHERE tracking_number IS NOT NULL ORDER BY id DESC LIMIT 10');
while($row = $result->fetch_assoc()) {
    echo "DocID: " . $row['id'] . " | Tracking: " . $row['tracking_number'] . " | Title: " . substr($row['title'], 0, 40) . "\n";
}
?>
