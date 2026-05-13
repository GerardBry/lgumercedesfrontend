<?php
require_once 'config/db_connect.php';

echo "=== Dashboard Query Test ===\n\n";

// Total documents (dashboard query)
$doc_stats = [
    'total_documents' => 0,
    'incoming' => 0,
    'outgoing' => 0,
    'received' => 0,
    'finished' => 0,
    'archive' => 0
];

// Total documents
$result = $conn->query("SELECT COUNT(*) as count FROM documents");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['total_documents'] = $row['count'];
    echo "Total Documents Query Result: " . $doc_stats['total_documents'] . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

// Incoming assignments (pending or forwarded)
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status IN ('Pending','Forwarded')");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['incoming'] = $row['count'];
    echo "Incoming Query Result: " . $doc_stats['incoming'] . "\n";
}

// Outgoing assignments
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE assigned_by IS NOT NULL AND status NOT IN ('Completed','Archived','Returned')");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['outgoing'] = $row['count'];
    echo "Outgoing Query Result: " . $doc_stats['outgoing'] . "\n";
}

// Received
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Received'");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['received'] = $row['count'];
    echo "Received Query Result: " . $doc_stats['received'] . "\n";
}

// Finished
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Completed'");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['finished'] = $row['count'];
    echo "Finished Query Result: " . $doc_stats['finished'] . "\n";
}

// Archive
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Archived'");
if ($result) {
    $row = $result->fetch_assoc();
    $doc_stats['archive'] = $row['count'];
    echo "Archive Query Result: " . $doc_stats['archive'] . "\n";
}

echo "\n=== Final Stats Array ===\n";
print_r($doc_stats);

$conn->close();
?>
