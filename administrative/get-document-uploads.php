<?php
/**
 * Get Document Uploads - Retrieve uploaded files for a document assignment
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db_connect.php';

$assignment_id = intval($_GET['assignment_id'] ?? 0);

if ($assignment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    $conn->close();
    exit;
}

// Check if table exists
$check_table = "SHOW TABLES LIKE 'document_uploads'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE IF NOT EXISTS document_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        notes TEXT,
        uploaded_by VARCHAR(255),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES document_assignments(id) ON DELETE CASCADE,
        INDEX idx_assignment (assignment_id)
    )";
    
    if (!$conn->query($create_table)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        $conn->close();
        exit;
    }
}

// Fetch uploads for this assignment
$sql = "SELECT id, assignment_id, file_path, notes, uploaded_by, uploaded_at
        FROM document_uploads
        WHERE assignment_id = ?
        ORDER BY uploaded_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

$uploads = [];
while ($row = $result->fetch_assoc()) {
    $uploads[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
exit;
?>
