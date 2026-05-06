<?php
/**
 * Finished Document Handler - Administrative Staff
 * Fetch document details for viewing finished documents
 */
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow Administrative Assistant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Only accept GET requests for viewing documents
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action']) || $_GET['action'] !== 'view') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit;
}

$assignment_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once '../config/db_connect.php';

function parseCompletionFilePayload($payload)
{
    if (empty($payload)) {
        return [
            'completion_file_name' => null,
            'completion_file_type' => null,
            'completion_file_path' => null,
            'has_completion_file' => false,
        ];
    }

    $decoded = json_decode($payload, true);
    if (is_array($decoded)) {
        // New format: store path reference
        if (isset($decoded['path'])) {
            return [
                'completion_file_name' => $decoded['name'] ?? 'completion-file',
                'completion_file_type' => $decoded['type'] ?? null,
                'completion_file_path' => $decoded['path'] ?? null,
                'has_completion_file' => !empty($decoded['path']),
            ];
        }
        // Legacy format: base64 encoded data (for backwards compatibility)
        if (isset($decoded['data'])) {
            return [
                'completion_file_name' => $decoded['name'] ?? 'completion-file',
                'completion_file_type' => $decoded['type'] ?? null,
                'completion_file_path' => null,
                'has_completion_file' => !empty($decoded['data']),
            ];
        }
    }

    return [
        'completion_file_name' => $payload,
        'completion_file_type' => null,
        'completion_file_path' => null,
        'has_completion_file' => true,
    ];
}

// Fetch assignment details - verify user is the recipient (assigned_to)
$sql = "SELECT 
        da.id,
        da.document_id,
        da.assigned_by,
        da.assigned_to,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.completed_at,
        da.completion_file,
        d.id as doc_id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        d.status,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        recipient.first_name as recipient_first_name,
        recipient.last_name as recipient_last_name,
        recipient.position as recipient_position
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    JOIN users recipient ON da.assigned_to = recipient.id
    WHERE da.id = ? AND da.assigned_to = ? AND da.status = 'Completed' LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('ii', $assignment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    // Parse completion file to get has_completion_file and completion_file_name
    $completion_info = parseCompletionFilePayload($assignment['completion_file'] ?? '');
    $assignment = array_merge($assignment, $completion_info);
    unset($assignment['completion_file']);
    echo json_encode(['success' => true, 'assignment' => $assignment]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
}

$stmt->close();
$conn->close();
?>