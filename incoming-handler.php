<?php
/**
 * Incoming Document Handler - Fetch document details for viewing
 */
session_start();
header('Content-Type: application/json');

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

    // Fetch assignment details - verify user is the assigned recipient
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
            d.notes,
            d.status,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            assigner.first_name as assigned_by_first,
            assigner.last_name as assigned_by_last
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        JOIN users assigner ON da.assigned_by = assigner.id
        WHERE da.id = ? AND da.assigned_to = ? LIMIT 1";

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

    $sql_check = "SELECT da.id, da.document_id, da.status, da.assigned_by,
                         da.office_department,
                         d.tracking_number,
                         staff.first_name as staff_first_name,
                         staff.last_name as staff_last_name,
                         staff.office_department as staff_office
                  FROM document_assignments da
                  JOIN documents d ON d.id = da.document_id
                  LEFT JOIN users staff ON staff.id = da.assigned_to
                  WHERE da.id = ? AND da.assigned_to = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

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

    if ($row['status'] === 'Received') {
        echo json_encode(['success' => true, 'message' => 'Document already received']);
        $conn->close();
        exit;
    }

    if ($row['status'] !== 'Pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending documents can be received']);
        $conn->close();
        exit;
    }

    $conn->begin_transaction();
    try {
        $sql_update_assignment = "UPDATE document_assignments
                                  SET status = 'Received', received_at = NOW()
                                  WHERE id = ? AND assigned_to = ?";
        $stmt_update_assignment = $conn->prepare($sql_update_assignment);
        $stmt_update_assignment->bind_param('ii', $assignment_id, $user_id);
        $stmt_update_assignment->execute();
        $stmt_update_assignment->close();

        $sql_update_document = "UPDATE documents
                               SET status = 'Received'
                               WHERE id = ?";
        $stmt_update_document = $conn->prepare($sql_update_document);
        $stmt_update_document->bind_param('i', $row['document_id']);
        $stmt_update_document->execute();
        $stmt_update_document->close();

        // Notify Administrative assignee that the department staff has received the document.
        $staff_name = trim(($row['staff_first_name'] ?? '') . ' ' . ($row['staff_last_name'] ?? ''));
        if ($staff_name === '') {
            $staff_name = 'Assigned staff';
        }
        $staff_office = trim($row['staff_office'] ?? $row['office_department'] ?? 'Office');
        $tracking_number = trim($row['tracking_number'] ?? '');
        $received_message = "$staff_office - $staff_name with tracking number: $tracking_number has been received";

        createCustomNotification(
            $conn,
            intval($row['assigned_by']),
            intval($row['document_id']),
            $assignment_id,
            $tracking_number,
            $received_message,
            'status_update',
            'Pending',
            'Received'
        );

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Document marked as received']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }

    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
