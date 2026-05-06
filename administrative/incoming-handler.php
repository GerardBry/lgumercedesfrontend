<?php
/**
 * Incoming Document Handler - Administrative Staff
 * Fetch document details for incoming assignments and mark as received
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Handle GET requests (view assignment details)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view') {

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit;
}

$assignment_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';


$sql = "SELECT
        da.id,
        da.document_id,
        da.assigned_by,
        da.assigned_to,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
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
        assigner.first_name as assigner_first_name,
        assigner.last_name as assigner_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    WHERE da.id = ? AND (da.assigned_to = ? OR da.assigned_by = ?) LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('iii', $assignment_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    echo json_encode(['success' => true, 'assignment' => $assignment]);
} else {
    // Debug: Log what went wrong
    error_log("Assignment View Error - ID: $assignment_id, User: $user_id");
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied', 'debug' => ['assignment_id' => $assignment_id, 'user_id' => $user_id]]);
}

$stmt->close();
$conn->close();
exit;
}

// Handle POST requests (mark as received)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'markReceived') {
    $assignment_id = intval($_POST['id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($assignment_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
        exit;
    }
    
    require_once '../config/db_connect.php';
    require_once '../config/notification_helpers.php';
    
    // Check if assignment exists and belongs to this admin
    $sql_check = "SELECT da.id, da.document_id, da.status 
                  FROM document_assignments da
                  WHERE da.id = ? AND da.assigned_to = ? 
                  LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('ii', $assignment_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        exit;
    }
    
    $row = $result_check->fetch_assoc();
    $stmt_check->close();
    
    // Allow both 'Pending' and 'Forwarded' status to be marked as received
    if ($row['status'] === 'Received') {
        echo json_encode(['success' => true, 'message' => 'Document already received']);
        $conn->close();
        exit;
    }
    
    if ($row['status'] !== 'Pending' && $row['status'] !== 'Forwarded') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending or forwarded documents can be received']);
        $conn->close();
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Get assignment details before updating
        $sql_get = "SELECT da.assigned_by, da.document_id, d.tracking_number 
                    FROM document_assignments da 
                    JOIN documents d ON da.document_id = d.id 
                    WHERE da.id = ?";
        $stmt_get = $conn->prepare($sql_get);
        if (!$stmt_get) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt_get->bind_param('i', $assignment_id);
        if (!$stmt_get->execute()) {
            throw new Exception("Execute failed: " . $stmt_get->error);
        }
        $result_get = $stmt_get->get_result();
        $assignment_data = $result_get->fetch_assoc();
        $stmt_get->close();
        
        if (!$assignment_data) {
            throw new Exception("Assignment data not found");
        }
        
        $assigned_by = intval($assignment_data['assigned_by']);
        $document_id = intval($assignment_data['document_id']);
        $tracking_number = $assignment_data['tracking_number'] ?? '';
        
        $sql_update_assignment = "UPDATE document_assignments
                                  SET status = 'Received', received_at = NOW()
                                  WHERE id = ? AND assigned_to = ?";
        $stmt_update_assignment = $conn->prepare($sql_update_assignment);
        if (!$stmt_update_assignment) {
            throw new Exception("Prepare assignment update failed: " . $conn->error);
        }
        $stmt_update_assignment->bind_param('ii', $assignment_id, $user_id);
        if (!$stmt_update_assignment->execute()) {
            throw new Exception("Execute assignment update failed: " . $stmt_update_assignment->error);
        }
        $stmt_update_assignment->close();
        
        $sql_update_document = "UPDATE documents
                               SET status = 'Received'
                               WHERE id = ?";
        $stmt_update_document = $conn->prepare($sql_update_document);
        if (!$stmt_update_document) {
            throw new Exception("Prepare document update failed: " . $conn->error);
        }
        $stmt_update_document->bind_param('i', $document_id);
        if (!$stmt_update_document->execute()) {
            throw new Exception("Execute document update failed: " . $stmt_update_document->error);
        }
        $stmt_update_document->close();
        
        // Create notification for Department Staff that Administrative received their document
        $admin_name = $_SESSION['first_name'] ?? 'Administrative';
        $notification_result = createCustomNotification(
            $conn,
            $assigned_by,
            $document_id,
            $assignment_id,
            $tracking_number,
            "Administrative - $admin_name has received your document with tracking number: $tracking_number",
            'status_update',
            'Pending',
            'Received'
        );
        
        if (!$notification_result) {
            throw new Exception("Failed to create notification");
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Document marked as received']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        // Return the actual error message for debugging
        echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
    }
    
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>