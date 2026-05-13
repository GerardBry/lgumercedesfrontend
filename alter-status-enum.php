<?php
require_once 'config/db_connect.php';

$alter = "ALTER TABLE document_assignments 
MODIFY status enum(
    'Pending',
    'Received',
    'Checking Documents',
    'Waiting For Approval by Mayor',
    'Completed',
    'Returned',
    'In Progress',
    'Forwarded',
    'Submitted to Administrative Office'
) DEFAULT 'Pending'";

if ($conn->query($alter)) {
    echo "SUCCESS: status enum updated\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
