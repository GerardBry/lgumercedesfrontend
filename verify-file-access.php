<?php
/**
 * Verification test for file viewing access
 */
require_once 'config/db_connect.php';

echo "<h2>File Access Verification Report</h2>";

// Get a sample new document (should have file_path in database column)
$sql = "SELECT d.id, d.title, d.created_by, d.file_path, d.notes 
        FROM documents d 
        WHERE d.file_path IS NOT NULL AND d.file_path != ''
        LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    $doc_id = $doc['id'];
    $created_by = $doc['created_by'];
    $file_path = $doc['file_path'];
    
    echo "<h3>Test Document: ID {$doc_id}</h3>";
    echo "<p><strong>File Path (from database):</strong> $file_path</p>";
    echo "<p><strong>Created By (User ID):</strong> $created_by</p>";
    
    // Verify the fix: Check if we can find this file for the user who created it
    $test_sql = "SELECT d.id, d.file_path, d.notes FROM documents d
                WHERE d.created_by = ? AND d.id = ?";
    $stmt = $conn->prepare($test_sql);
    $stmt->bind_param('ii', $created_by, $doc_id);
    $stmt->execute();
    $test_result = $stmt->get_result();
    
    if ($test_result->num_rows > 0) {
        $row = $test_result->fetch_assoc();
        $stored_path = str_replace(['../', '..\\'], '', $row['file_path'] ?? '');
        $stored_path = ltrim($stored_path, '/\\');
        
        echo "<h3>Permission Check Result:</h3>";
        echo "<p><strong>Database file_path:</strong> $stored_path</p>";
        echo "<p><strong>Requested path:</strong> $file_path</p>";
        
        // Sanitize the requested path
        $requested_sanitized = str_replace(['../', '..\\'], '', $file_path);
        $requested_sanitized = ltrim($requested_sanitized, '/\\');
        
        echo "<p><strong>Requested path (sanitized):</strong> $requested_sanitized</p>";
        
        if ($stored_path === $requested_sanitized) {
            echo "<p style='color:green; font-weight:bold;'>✓ FILE ACCESS ALLOWED - Paths match!</p>";
            
            // Check if file exists
            if (file_exists($file_path)) {
                echo "<p style='color:green;'>✓ File exists on disk</p>";
                echo "<p>File size: " . filesize($file_path) . " bytes</p>";
            } else {
                echo "<p style='color:red;'>✗ File does not exist on disk: $file_path</p>";
            }
        } else {
            echo "<p style='color:red; font-weight:bold;'>✗ FILE ACCESS DENIED - Paths don't match!</p>";
            echo "<p>Database: '$stored_path'</p>";
            echo "<p>Requested: '$requested_sanitized'</p>";
        }
    }
    
    $stmt->close();
} else {
    echo "<p>No documents with files found in database</p>";
}

$conn->close();
?>
