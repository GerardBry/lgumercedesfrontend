<?php
/**
 * Delete Document Upload - Administrative Staff
 * Remove uploaded pictures/files from a document
 */
session_start();
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$upload_id = intval($data['upload_id'] ?? 0);
$assignment_id = intval($data['assignment_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);

if ($upload_id <= 0 || $assignment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid upload ID or assignment ID']);
    exit;
}

// Verify access: either user is assigned to the document OR user uploaded the file
$sql_verify = "SELECT da.assigned_to, du.uploaded_by FROM document_assignments da 
               JOIN document_uploads du ON du.assignment_id = da.id
               WHERE da.id = ? AND du.id = ? LIMIT 1";
$stmt_verify = $conn->prepare($sql_verify);
if (!$stmt_verify) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt_verify->bind_param('ii', $assignment_id, $upload_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

if ($result_verify->num_rows === 0) {
    $stmt_verify->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment or upload not found']);
    exit;
}

$verify_data = $result_verify->fetch_assoc();
$stmt_verify->close();

// Check if user is either: (1) assigned to document, or (2) uploaded the file
$assigned_to = intval($verify_data['assigned_to']);
$uploaded_by = $verify_data['uploaded_by']; // This is the user's full name (first + last)
$current_user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

if ($assigned_to != $user_id && $uploaded_by !== $current_user_name) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get file path before deletion
$sql_get_file = "SELECT file_path FROM document_uploads WHERE id = ? AND assignment_id = ? LIMIT 1";
$stmt_get = $conn->prepare($sql_get_file);
if (!$stmt_get) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt_get->bind_param('ii', $upload_id, $assignment_id);
$stmt_get->execute();
$result_get = $stmt_get->get_result();

if ($result_get->num_rows === 0) {
    $stmt_get->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Upload not found']);
    exit;
}

$upload = $result_get->fetch_assoc();
$file_path = $upload['file_path'];
$stmt_get->close();

// Delete from database
$sql_delete = "DELETE FROM document_uploads WHERE id = ? AND assignment_id = ?";
$stmt_delete = $conn->prepare($sql_delete);
if (!$stmt_delete) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt_delete->bind_param('ii', $upload_id, $assignment_id);
if (!$stmt_delete->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
    $stmt_delete->close();
    exit;
}

$stmt_delete->close();

// Delete physical file
$full_path = dirname(dirname(__FILE__)) . '/' . $file_path;
if (file_exists($full_path)) {
    if (!unlink($full_path)) {
        // Log warning but still return success since DB record is deleted
        error_log('Warning: Could not delete file: ' . $full_path);
    }
}

$conn->close();
echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
exit;
