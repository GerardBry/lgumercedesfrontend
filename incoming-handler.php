<?php
/**
 * Incoming Document Handler - Fetch document details for viewing
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/uploads/documents/incoming_errors.log');

session_start();
header('Content-Type: application/json');

// Register error handler to catch PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $errstr]);
    exit;
});

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

// Handle GET requests for viewing assignment details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
        exit;
    }

    $assignment_id = intval($_GET['id']);

    // Fetch assignment details - allow recipient access, allow department-staff inbox visibility,
    // and allow assigner to view returned items.
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
            (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned') as rejection_at,
            d.id as doc_id,
            d.title,
            d.description,
            d.tracking_number,
            d.document_type,
            d.date_sent,
            d.date_received,
            d.classification,
            d.sub_classification,
            d.priority,
            d.file_path,
            d.sender_name,
            d.notes,
            d.status,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            assigner.first_name as assigned_by_first,
            assigner.last_name as assigned_by_last,
            assigner.position as assigned_by_position
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        JOIN users recipient ON da.assigned_to = recipient.id
        JOIN users assigner ON da.assigned_by = assigner.id
                WHERE da.id = ?
                    AND (
                        da.assigned_to = ?
                        OR (da.assigned_by = ? AND da.status = 'Returned')
                    )
                LIMIT 1";

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

        if (($assignment['assignment_status'] ?? '') === 'Pending') {
            markAssignmentNotificationAsRead($conn, $user_id, $assignment_id);
        }

        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle POST request for receiving document
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['action']) || $data['action'] !== 'receive' || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
        exit;
    }

    $assignment_id = intval($data['id']);

    // Simple verification query
    $sql_check = "SELECT da.id, da.document_id, da.status, da.assigned_by,
                        da.office_department,
                        d.tracking_number
                FROM document_assignments da
                JOIN documents d ON d.id = da.document_id
                WHERE da.id = ?
                AND da.status IN ('Pending', 'Forwarded')
                LIMIT 1";
    
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        http_response_code(500);
        error_log("Prepare check error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database prepare error']);
        exit;
    }

    $stmt_check->bind_param('i', $assignment_id);
    if (!$stmt_check->execute()) {
        http_response_code(500);
        error_log("Execute check error: " . $stmt_check->error);
        echo json_encode(['success' => false, 'message' => 'Database query error']);
        $stmt_check->close();
        exit;
    }
    
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or already received']);
        exit;
    }

    $row = $result_check->fetch_assoc();
    $stmt_check->close();

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

    try {
        $conn->begin_transaction();

        $sql_update_assignment = "UPDATE document_assignments
                                SET status = 'Received', received_at = NOW()
                                WHERE id = ? AND status IN ('Pending', 'Forwarded')";
        $stmt_update_assignment = $conn->prepare($sql_update_assignment);
        if (!$stmt_update_assignment) {
            throw new Exception("Prepare assignment error: " . $conn->error);
        }
        $stmt_update_assignment->bind_param('i', $assignment_id);
        if (!$stmt_update_assignment->execute()) {
            throw new Exception("Execute assignment error: " . $stmt_update_assignment->error);
        }
        $affected_rows = $stmt_update_assignment->affected_rows;
        $stmt_update_assignment->close();

        if ($affected_rows === 0) {
            throw new Exception("No assignment updated - possible race condition");
        }

        $sql_update_document = "UPDATE documents
                               SET status = 'Received'
                               WHERE id = ?";
        $stmt_update_document = $conn->prepare($sql_update_document);
        if (!$stmt_update_document) {
            throw new Exception("Prepare document error: " . $conn->error);
        }
        $stmt_update_document->bind_param('i', $row['document_id']);
        if (!$stmt_update_document->execute()) {
            throw new Exception("Execute document error: " . $stmt_update_document->error);
        }
        $stmt_update_document->close();

        // Try to create notification but don't fail the transaction if it fails
        $tracking_number = trim($row['tracking_number'] ?? '');
        $received_message = "Document (Tracking: " . $tracking_number . ") has been received";

        @createCustomNotification(
            $conn,
            intval($row['assigned_by']),
            intval($row['document_id']),
            $assignment_id,
            $tracking_number,
            $received_message,
            'status_update',
            $row['status'],
            'Received'
        );

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Document marked as received']);

    } catch (Exception $e) {
        if ($conn->connect_error === null) { // Connection still valid
            $conn->rollback();
        }
        http_response_code(500);
        $error_msg = $e->getMessage();
        error_log('Receive error [Assignment ' . $assignment_id . ']: ' . $error_msg);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $error_msg]);
    }

    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
