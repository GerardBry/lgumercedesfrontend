<?php
/**
 * Test file access permissions
 */
session_start();
$_SESSION['user_id'] = 14; // Assuming user 14 uploaded the files

require_once 'config/db_connect.php';

$file_path = 'uploads/documents/DOC-0014-1778408407-6a005bd7c2c87.pdf';
$user_id = 14;

echo "<h2>File Access Permission Test</h2>";
echo "<p>Testing file: $file_path</p>";
echo "<p>User ID: $user_id</p>";

// Check documents that belong to this user
$sql = "SELECT d.id, d.file_path, d.created_by FROM documents WHERE created_by = ? OR id IN (SELECT document_id FROM document_assignments WHERE assigned_to = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>User's Documents:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Doc ID</th><th>File Path</th><th>Created By</th><th>Match?</th></tr>";

$file_found = false;
while ($row = $result->fetch_assoc()) {
    $stored_path = str_replace(['../', '..\\'], '', $row['file_path'] ?? '');
    $stored_path = ltrim($stored_path, '/\\');
    
    $match = ($stored_path === $file_path) ? '<span style="color:green;">✓ YES</span>' : 'NO';
    if ($stored_path === $file_path) {
        $file_found = true;
    }
    
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($stored_path) . "</td>";
    echo "<td>" . $row['created_by'] . "</td>";
    echo "<td>" . $match . "</td>";
    echo "</tr>";
}
echo "</table>";

if ($file_found) {
    echo "<p style='color:green;'><strong>✓ File access ALLOWED - File found in user's documents</strong></p>";
} else {
    echo "<p style='color:red;'><strong>✗ File access DENIED - File not found in user's documents</strong></p>";
}

$stmt->close();
$conn->close();
?>
