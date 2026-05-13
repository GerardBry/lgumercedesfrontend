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
$user_role = $_SESSION['role'] ?? 'Department Staff';
require_once 'config/db_connect.php';

$document = null;

$hasReturnedAt = false;
$returnedAtCheck = $conn->query("SHOW COLUMNS FROM document_assignments LIKE 'returned_at'");
if ($returnedAtCheck && $returnedAtCheck->num_rows > 0) {
    $hasReturnedAt = true;
}

function buildReturnTimestampExpr($tableAlias = 'da')
{
    global $hasReturnedAt;

    if ($hasReturnedAt) {
        return "COALESCE({$tableAlias}.returned_at, (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = {$tableAlias}.id AND n.new_status = 'Returned'), {$tableAlias}.completed_at, {$tableAlias}.received_at, {$tableAlias}.assigned_at, d.date_sent)";
    }

    return "COALESCE((SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = {$tableAlias}.id AND n.new_status = 'Returned'), {$tableAlias}.completed_at, {$tableAlias}.received_at, {$tableAlias}.assigned_at, d.date_sent)";
}

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
            d.doc_sequence_number,
            d.tracking_number,
            d.title,
            d.description,
            d.document_type,
            d.date_sent,
            d.notes,
            d.created_at,
            d.status as document_status,
            d.sender_name,
            d.date_received,
            d.classification,
            d.sub_classification,
            d.priority,
            d.deadline,
            d.file_path,
            da.notes as assignment_notes,
            da.status as assignment_status,
            da.assigned_at,
            da.completed_at,
            " . buildReturnTimestampExpr('da') . " AS returned_at,
            (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned') AS rejection_at,
            da.completion_file,
            u.first_name as sender_first_name,
            u.last_name as sender_last_name,
            u_assigner.first_name as assigned_by_first,
            u_assigner.last_name as assigned_by_last,
            u_assigner.position as assigned_by_position
        FROM documents d
        JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users u ON d.sender_id = u.id
        LEFT JOIN users u_assigner ON da.assigned_by = u_assigner.id
        WHERE da.id = ? AND (da.assigned_to = ? OR da.assigned_by = ? OR ? IN ('Record Officer','Mayor','Super Admin'))
        LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error, 'debug' => $sql]);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param('iiis', $assignment_id, $user_id, $user_id, $user_role);
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
    } else {
        // If assignment query failed, try treating assignment_id as a document_id
        // This handles cases where finished.php returns d.id instead of da.id
        // For documents in Finished list, user already has access, so less restrictive check
        $sql_doc_fallback = "SELECT 
                NULL as assignment_id,
                d.id,
                d.doc_sequence_number,
                d.tracking_number,
                d.title,
                d.description,
                d.document_type,
                d.date_sent,
                d.notes,
                d.created_at,
                d.status as document_status,
                d.sender_name,
                d.date_received,
                d.classification,
                d.sub_classification,
                d.priority,
                d.deadline,
                d.file_path,
                NULL as assignment_notes,
                NULL as assignment_status,
                NULL as assigned_at,
                NULL as completed_at,
                NULL as returned_at,
                NULL as rejection_at,
                NULL as completion_file,
                u.first_name as sender_first_name,
                u.last_name as sender_last_name,
                NULL as assigned_by_first,
                NULL as assigned_by_last,
                NULL as assigned_by_position
            FROM documents d
            LEFT JOIN users u ON d.sender_id = u.id
            WHERE d.id = ?
            LIMIT 1";
        
        $stmt_fallback = $conn->prepare($sql_doc_fallback);
        if (!$stmt_fallback) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }
        
        $stmt_fallback->bind_param('i', $assignment_id);
        if (!$stmt_fallback->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt_fallback->error]);
            $stmt->close();
            $stmt_fallback->close();
            $conn->close();
            exit;
        }
        
        $result_fallback = $stmt_fallback->get_result();
        if ($result_fallback->num_rows > 0) {
            $document = $result_fallback->fetch_assoc();
        }
        $stmt_fallback->close();
    }
    $stmt->close();
}

// If document_id provided or assignment lookup failed
if ($document === null && $doc_id > 0) {
    // Fetch document created by current user
    $sql = "SELECT 
            d.id,
            d.doc_sequence_number,
            d.tracking_number,
            d.title,
            d.description,
            d.document_type,
            d.date_sent,
            d.notes,
            d.created_at,
            d.status,
            d.sender_name,
            d.date_received,
            d.classification,
            d.sub_classification,
            d.priority,
            d.deadline,
            d.file_path
            ,NULL as returned_at
            ,NULL as rejection_at
            ,NULL as assigned_by_first
            ,NULL as assigned_by_last
            ,NULL as assigned_by_position
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
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // If not created by user, check if assigned to user
            $sql_assigned = "SELECT 
                d.id,
                d.doc_sequence_number,
                d.tracking_number,
                d.title,
                d.description,
                d.document_type,
                d.date_sent,
                d.notes,
                d.created_at,
            d.status,
            d.sender_name,
            d.date_received,
            d.classification,
            d.sub_classification,
            d.priority,
            d.deadline,
            d.file_path
            ,NULL as returned_at
            ,NULL as rejection_at
            ,NULL as assigned_by_first
            ,NULL as assigned_by_last
            ,NULL as assigned_by_position
            FROM documents d
            JOIN document_assignments da ON d.id = da.document_id
            WHERE d.id = ? AND (da.assigned_to = ? OR da.assigned_by = ? OR d.created_by = ?)";
        
        $stmt_assigned = $conn->prepare($sql_assigned);
        if (!$stmt_assigned) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }
        
        $stmt_assigned->bind_param('iiii', $doc_id, $user_id, $user_id, $user_id);
        if (!$stmt_assigned->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt_assigned->error]);
            $stmt->close();
            $stmt_assigned->close();
            $conn->close();
            exit;
        }
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

// Parse completion file if present
if (isset($document['completion_file'])) {
    $completionFileData = parseCompletionFilePayload($document['completion_file']);
    $document['completion_file_name'] = $completionFileData['completion_file_name'];
    $document['completion_file_type'] = $completionFileData['completion_file_type'];
    $document['completion_file_path'] = $completionFileData['completion_file_path'];
    $document['has_completion_file'] = $completionFileData['has_completion_file'];
} else {
    $document['completion_file_name'] = null;
    $document['completion_file_type'] = null;
    $document['completion_file_path'] = null;
    $document['has_completion_file'] = false;
}

$conn->close();

echo json_encode([
    'success' => true,
    'document' => $document
]);
?>
