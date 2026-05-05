<?php
/**
 * Administrative Forward Document Handler
 * Forwards saved administrative documents to department staff.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit;
}

if (($data['action'] ?? '') !== 'forward_document') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$document_id = intval($data['document_id'] ?? 0);
$recipient_id = intval($data['recipient_id'] ?? 0);
$office = trim($data['office'] ?? '');
$notes = trim($data['notes'] ?? '');
$user_id = intval($_SESSION['user_id']);

if ($document_id <= 0 || $recipient_id <= 0 || $office === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document, office, and recipient are required']);
    exit;
}

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';

$conn->begin_transaction();

try {
    $sql_doc = 'SELECT id, tracking_number FROM documents WHERE id = ? AND created_by = ? LIMIT 1';
    $stmt_doc = $conn->prepare($sql_doc);
    if (!$stmt_doc) {
        throw new Exception('Database error while loading document');
    }

    $stmt_doc->bind_param('ii', $document_id, $user_id);
    $stmt_doc->execute();
    $doc_result = $stmt_doc->get_result();
    if ($doc_result->num_rows === 0) {
        $stmt_doc->close();
        throw new Exception('Document not found or access denied');
    }
    
    $doc_data = $doc_result->fetch_assoc();
    $tracking_number = $doc_data['tracking_number'] ?? '';
    $stmt_doc->close();

    $sql_recipient = "SELECT id FROM users
        WHERE id = ? AND role = 'Department Staff' AND status = 'Active' LIMIT 1";
    $stmt_recipient = $conn->prepare($sql_recipient);
    if (!$stmt_recipient) {
        throw new Exception('Database error while validating recipient');
    }

    $stmt_recipient->bind_param('i', $recipient_id);
    $stmt_recipient->execute();
    $recipient_result = $stmt_recipient->get_result();
    if ($recipient_result->num_rows === 0) {
        $stmt_recipient->close();
        throw new Exception('Selected recipient is invalid or inactive');
    }
    $stmt_recipient->close();

    $sql_existing = 'SELECT id FROM document_assignments WHERE document_id = ? LIMIT 1';
    $stmt_existing = $conn->prepare($sql_existing);
    if (!$stmt_existing) {
        throw new Exception('Database error while checking document assignment');
    }

    $stmt_existing->bind_param('i', $document_id);
    $stmt_existing->execute();
    $existing_result = $stmt_existing->get_result();
    if ($existing_result->num_rows > 0) {
        $stmt_existing->close();
        throw new Exception('This document is already forwarded or assigned');
    }
    $stmt_existing->close();

    $sql_update_doc = "UPDATE documents
        SET sender_id = ?, date_sent = NOW(), status = 'Pending'
        WHERE id = ? AND created_by = ?";
    $stmt_update_doc = $conn->prepare($sql_update_doc);
    if (!$stmt_update_doc) {
        throw new Exception('Database error while updating document');
    }

    $stmt_update_doc->bind_param('iii', $user_id, $document_id, $user_id);
    $stmt_update_doc->execute();
    $stmt_update_doc->close();

    $assignment_notes = $notes !== '' ? $notes : 'Forwarded from Administrative Document Entry';
    $sql_assign = "INSERT INTO document_assignments
        (document_id, assigned_by, assigned_to, office_department, notes, status)
        VALUES (?, ?, ?, ?, ?, 'Pending')";

    $stmt_assign = $conn->prepare($sql_assign);
    if (!$stmt_assign) {
        throw new Exception('Database error while creating assignment');
    }

    $stmt_assign->bind_param('iiiss', $document_id, $user_id, $recipient_id, $office, $assignment_notes);
    $stmt_assign->execute();
    $assignment_id = $conn->insert_id;
    $stmt_assign->close();

    // Create notification for Department Staff about forwarded document
    $admin_name = $_SESSION['first_name'] ?? 'Administrative';
    $forward_message = "Administrative - $admin_name with tracking number: $tracking_number forwarded a document";
    
    createCustomNotification(
        $conn,
        $recipient_id,
        $document_id,
        $assignment_id,
        $tracking_number,
        $forward_message,
        'status_update',
        null,
        'Pending'
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document forwarded successfully.',
        'assignment_id' => $assignment_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>