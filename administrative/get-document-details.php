<?php
/**
 * Get Administrative Document Details
 */
session_start();
header('Content-Type: application/json');

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
require_once '../config/db_connect.php';

// Debug: Log the request
error_log("DEBUG: get-document-details.php called - doc_id: $doc_id, user_id: $user_id");

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
    WHERE d.id = ? AND (d.created_by = ? OR d.id IN (
        SELECT DISTINCT da.document_id FROM document_assignments da WHERE da.assigned_to = ? AND da.document_id IS NOT NULL
    ) OR d.id IN (
        SELECT DISTINCT da.document_id FROM document_assignments da WHERE da.assigned_by = ?
    ))";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    $conn->close();
    exit;
}

$stmt->bind_param('iiii', $doc_id, $user_id, $user_id, $user_id);
if (!$stmt->execute()) {
    error_log("DEBUG: Query execute failed - " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$result = $stmt->get_result();
error_log("DEBUG: Query returned " . $result->num_rows . " rows");

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