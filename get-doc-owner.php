<?php
require_once 'config/db_connect.php';

// Get document 102 info
$sql = "SELECT id, title, created_by, file_path FROM documents WHERE id = 102";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    echo "Document 102:\n";
    echo "- Title: " . ($doc['title'] ?? 'NULL') . "\n";
    echo "- Created By: " . ($doc['created_by'] ?? 'NULL') . "\n";
    echo "- File Path: " . ($doc['file_path'] ?? 'NULL') . "\n";
} else {
    echo "Document not found or error: " . ($conn->error ?? 'Unknown error');
}

$conn->close();
?>
