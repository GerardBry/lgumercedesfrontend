<?php
require_once 'config/db_connect.php';

// Fix existing NULL status assignments
$update = "UPDATE document_assignments SET status = 'Submitted to Administrative Office' WHERE status IS NULL OR status = ''";
if ($conn->query($update)) {
    $affected = $conn->affected_rows;
    echo "SUCCESS: Fixed $affected assignments with NULL/empty status\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>
