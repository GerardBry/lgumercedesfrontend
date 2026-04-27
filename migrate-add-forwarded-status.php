<?php
/**
 * Migration: Add 'Forwarded' status to document_assignments enum
 */

require_once 'config/db_connect.php';

echo "<h1>Database Migration: Add 'Forwarded' Status</h1>";

// Check current schema
$result = $conn->query("SHOW COLUMNS FROM document_assignments WHERE Field = 'status'");
$column = $result->fetch_assoc();
echo "<h2>Current Status Column:</h2>";
echo "<p><strong>Type:</strong> " . $column['Type'] . "</p>";

// Perform migration
echo "<h2>Executing Migration...</h2>";

$migration_sql = "ALTER TABLE document_assignments MODIFY COLUMN status ENUM('Pending','Received','Checking Documents','Waiting For Approval by Mayor','Completed','Returned','In Progress','Forwarded') DEFAULT 'Pending'";

if ($conn->query($migration_sql)) {
    echo "<p style='color: green;'><strong>✓ Migration successful!</strong></p>";
    
    // Verify the change
    $result_after = $conn->query("SHOW COLUMNS FROM document_assignments WHERE Field = 'status'");
    $column_after = $result_after->fetch_assoc();
    echo "<h2>Updated Status Column:</h2>";
    echo "<p><strong>Type:</strong> " . $column_after['Type'] . "</p>";
} else {
    echo "<p style='color: red;'><strong>✗ Migration failed!</strong></p>";
    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
}

$conn->close();
?>
