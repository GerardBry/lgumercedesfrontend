<?php
/**
 * Get Document Details
 * Returns document details as JSON
 * Supports both 'id' (document ID) and 'assignment_id' (document assignment ID) parameters
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doc_id = intval($_GET['id'] ?? 0);
$assignment_id = intval($_GET['assignment_id'] ?? 0);

if ($doc_id <= 0 && $assignment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID or assignment ID']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
require_once 'config/db_connect.php';

$document = null;

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

// If assignment_id provided, fetch via assignment
if ($assignment_id > 0) {
    $sql = "SELECT 
            da.id as assignment_id,
            d.id,
            d.tracking_number,
            d.title,
            d.description,
            d.document_type,
            d.date_sent,
            d.notes,
            d.created_at,
            d.status as document_status,
            da.notes as assignment_notes,
            da.status as assignment_status,
            da.assigned_at,
            da.completed_at,
            da.completion_file,
            u.first_name as sender_first_name,
            u.last_name as sender_last_name
        FROM documents d
        JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users u ON d.sender_id = u.id
        WHERE da.id = ? AND (d.created_by = ? OR da.assigned_to = ?)
        LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error, 'debug' => $sql]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param('iii', $assignment_id, $user_id, $user_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Execution error: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        $document = array_merge($document, parseCompletionFilePayload($document['completion_file'] ?? ''));
        unset($document['completion_file']);
    }
    $stmt->close();
}

// If document_id provided or assignment lookup failed
if ($document === null && $doc_id > 0) {
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
        // If not created by user, check if assigned to user
        $sql_assigned = "SELECT 
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
            JOIN document_assignments da ON d.id = da.document_id
            WHERE d.id = ? AND da.assigned_to = ?";
        
        $stmt_assigned = $conn->prepare($sql_assigned);
        if (!$stmt_assigned) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }
        
        $stmt_assigned->bind_param('ii', $doc_id, $user_id);
        $stmt_assigned->execute();
        $result = $stmt_assigned->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $stmt_assigned->close();
            $conn->close();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Document not found']);
            exit;
        }
        
        $document = $result->fetch_assoc();
        $stmt_assigned->close();
    } else {
        $document = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($document === null) {
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$conn->close();

echo json_encode([
    'success' => true,
    'document' => $document
]);
?>
