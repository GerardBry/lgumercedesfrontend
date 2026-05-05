<?php
/**
 * Get Assignment Details
 * Returns assignment details including completion file for modal viewing
 * Used by staff in finished.php
 */
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$assignment_id = intval($_GET['assignment_id'] ?? 0);
if ($assignment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
require_once 'config/db_connect.php';

// Fetch assignment details - only for documents created by the current user
$sql = "SELECT 
        da.id as assignment_id,
        da.document_id,
        da.assigned_by,
        da.assigned_to,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.completed_at,
        da.completion_file,
        d.id as document_id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        d.status,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    WHERE da.id = ? AND d.created_by = ? AND da.status = 'Completed' LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    $conn->close();
    exit;
}

$stmt->bind_param('ii', $assignment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
    exit;
}

$assignment = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'assignment' => $assignment
]);
?>
