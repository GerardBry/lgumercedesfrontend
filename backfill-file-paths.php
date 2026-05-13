<?php
/**
 * Backfill file paths for old documents
 * Documents created before file upload handler implementation need file_path backfill
 */
require_once 'config/db_connect.php';

$upload_dir = 'uploads/documents/';

// Check if upload directory exists
if (!is_dir($upload_dir)) {
    echo json_encode(['error' => 'Upload directory does not exist']);
    exit;
}

// Get list of files in uploads/documents directory
$files = array_diff(scandir($upload_dir), ['.', '..']);
$file_mapping = [];

foreach ($files as $file) {
    if (is_file($upload_dir . $file) && $file !== 'debug.log') {
        $file_mapping[$file] = $upload_dir . $file;
    }
}

echo "<h2>File Path Backfill Report</h2>";
echo "<p>Found " . count($file_mapping) . " files in uploads directory</p>";

// For each document with empty file_path, try to find matching file
$docs = $conn->query("SELECT id, tracking_number, title, file_path FROM documents WHERE (file_path IS NULL OR file_path = '') ORDER BY id DESC LIMIT 20");

$backfilled = 0;
$not_found = 0;

if ($docs->num_rows > 0) {
    echo "<h3>Documents to Backfill:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th></tr>";
    
    while ($doc = $docs->fetch_assoc()) {
        $doc_id = $doc['id'];
        $found_file = null;
        
        // Try to find matching file
        foreach ($file_mapping as $filename => $filepath) {
            // Check if filename contains doc ID or sequence number
            if (strpos($filename, 'DOC-') === 0) {
                // Try pattern matching
                if (strpos($filename, str_pad($doc_id, 4, '0', STR_PAD_LEFT)) !== false) {
                    $found_file = $filepath;
                    break;
                }
            }
        }
        
        if ($found_file) {
            // Update document with found file path
            $stmt = $conn->prepare("UPDATE documents SET file_path = ? WHERE id = ?");
            $stmt->bind_param("si", $found_file, $doc_id);
            $stmt->execute();
            $stmt->close();
            
            echo "<tr><td>" . htmlspecialchars($doc_id) . "</td><td>" . htmlspecialchars($doc['title']) . "</td><td><span style='color:green;'>✓ BACKFILLED</span></td></tr>";
            $backfilled++;
        } else {
            echo "<tr><td>" . htmlspecialchars($doc_id) . "</td><td>" . htmlspecialchars($doc['title']) . "</td><td><span style='color:red;'>✗ NO FILE FOUND</span></td></tr>";
            $not_found++;
        }
    }
    echo "</table>";
    
    echo "<h3>Summary</h3>";
    echo "<p>Backfilled: $backfilled documents</p>";
    echo "<p>Not found: $not_found documents</p>";
} else {
    echo "<p>All documents have file_path populated!</p>";
}

$conn->close();
?>
