<?php
/**
 * Get Document Details
 * Returns document details as JSON
 */
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doc_id = intval($_GET['id'] ?? 0);
if ($doc_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
require_once 'config/db_connect.php';

// Fetch document created by current user
$sql = "SELECT 
        d.id,
        d.tracking_number,
        d.title,
        d.description,
        d.document_type,
        d.date_sent,
        d.notes,
        d.created_at,
        d.status
    FROM documents d
    WHERE d.id = ? AND d.created_by = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    $conn->close();
    exit;
}

$stmt->bind_param('ii', $doc_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$document = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'document' => $document
]);
?>
