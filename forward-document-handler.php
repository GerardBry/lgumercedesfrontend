<?php
/**
 * Forward Saved Document Handler
 * Forwards a saved document to Administrative using an existing tracking code
 * to keep one connected transaction flow.
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only regular department staff should use this endpoint.
if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Super Admin' || $_SESSION['role'] === 'Administrative Assistant')) {
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
$incoming_tracking_code = strtoupper(trim($data['incoming_tracking_code'] ?? ''));
$user_id = intval($_SESSION['user_id']);

if ($document_id <= 0 || $incoming_tracking_code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Document ID and tracking code are required']);
    exit;
}

require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

$conn->begin_transaction();

try {
    // Ensure this document belongs to the logged-in user.
    $sql_doc = "SELECT id, tracking_number FROM documents WHERE id = ? AND created_by = ? LIMIT 1";
    $stmt_doc = $conn->prepare($sql_doc);
    if (!$stmt_doc) {
        throw new Exception('Database error while loading document');
    }

    $stmt_doc->bind_param('ii', $document_id, $user_id);
    if (!$stmt_doc->execute()) {
        throw new Exception('Failed to load document');
    }
    $result_doc = $stmt_doc->get_result();

    if ($result_doc->num_rows === 0) {
        $stmt_doc->close();
        throw new Exception('Document not found or access denied');
    }

    $document = $result_doc->fetch_assoc();
    $stmt_doc->close();

    // Prevent forwarding the same document multiple times.
    $sql_existing_assignment = "SELECT id FROM document_assignments WHERE document_id = ? LIMIT 1";
    $stmt_existing_assignment = $conn->prepare($sql_existing_assignment);
    if (!$stmt_existing_assignment) {
        throw new Exception('Database error while checking existing assignment');
    }

    $stmt_existing_assignment->bind_param('i', $document_id);
    if (!$stmt_existing_assignment->execute()) {
        throw new Exception('Failed to check existing assignment');
    }
    $existing_assignment_result = $stmt_existing_assignment->get_result();

    if ($existing_assignment_result->num_rows > 0) {
        $stmt_existing_assignment->close();
        throw new Exception('This document is already forwarded or assigned');
    }

    $stmt_existing_assignment->close();

    // Resolve the admin recipient from the original assignment using the same tracking code.
    $sql_source_assignment = "SELECT
            da.id,
            da.status,
            da.assigned_by,
            da.office_department,
            admin.first_name,
            admin.last_name
        FROM document_assignments da
        JOIN documents d ON d.id = da.document_id
        JOIN users admin ON admin.id = da.assigned_by
        WHERE da.assigned_to = ?
          AND d.tracking_number = ?
          AND admin.role = 'Administrative Assistant'
                ORDER BY (da.status = 'Received') DESC, da.assigned_at DESC
        LIMIT 1";

    $stmt_source_assignment = $conn->prepare($sql_source_assignment);
    if (!$stmt_source_assignment) {
        throw new Exception('Database error while validating tracking code');
    }

    $stmt_source_assignment->bind_param('is', $user_id, $incoming_tracking_code);
    if (!$stmt_source_assignment->execute()) {
        throw new Exception('Failed to validate tracking code');
    }
    $source_assignment_result = $stmt_source_assignment->get_result();

    if ($source_assignment_result->num_rows === 0) {
        $stmt_source_assignment->close();
        throw new Exception('Tracking code not found in your Administrative instructions');
    }

    $source_assignment = $source_assignment_result->fetch_assoc();
    $stmt_source_assignment->close();

    $source_assignment_id = intval($source_assignment['id']);
    $source_assignment_status = trim($source_assignment['status'] ?? '');
    $administrative_user_id = intval($source_assignment['assigned_by']);
    $office_department = trim($source_assignment['office_department'] ?? '');

    $sql_staff = "SELECT first_name, last_name, office_department FROM users WHERE id = ? LIMIT 1";
    $stmt_staff = $conn->prepare($sql_staff);
    if (!$stmt_staff) {
        throw new Exception('Database error while loading sender details');
    }
    $stmt_staff->bind_param('i', $user_id);
    if (!$stmt_staff->execute()) {
        throw new Exception('Failed to load sender details');
    }
    $result_staff = $stmt_staff->get_result();
    $staff_details = $result_staff->fetch_assoc() ?: [];
    $stmt_staff->close();

    // Align document tracking code to keep one connected transaction.
    $sql_update_document = "UPDATE documents
        SET tracking_number = ?, sender_id = ?, date_sent = NOW(), status = 'Pending'
        WHERE id = ? AND created_by = ?";

    $stmt_update_document = $conn->prepare($sql_update_document);
    if (!$stmt_update_document) {
        throw new Exception('Database error while updating document');
    }

    $stmt_update_document->bind_param('siii', $incoming_tracking_code, $user_id, $document_id, $user_id);
    if (!$stmt_update_document->execute()) {
        if ($conn->errno === 1062) {
            // Database still has UNIQUE constraint on tracking_number.
            // Allow shared tracking code for connected transaction flow, then retry once.
            $stmt_update_document->close();

            $sql_unique_index = "SELECT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'documents'
                  AND COLUMN_NAME = 'tracking_number'
                  AND NON_UNIQUE = 0
                LIMIT 1";
            $result_unique_index = $conn->query($sql_unique_index);

            if ($result_unique_index && $result_unique_index->num_rows > 0) {
                $unique_index = $result_unique_index->fetch_assoc();
                $index_name = $unique_index['INDEX_NAME'];

                if (!$conn->query("ALTER TABLE documents DROP INDEX `" . $conn->real_escape_string($index_name) . "`") ) {
                    throw new Exception('Unable to remove unique tracking restriction. Please contact administrator.');
                }

                // Keep fast lookup by tracking code after dropping uniqueness.
                $conn->query("ALTER TABLE documents ADD INDEX idx_tracking_number (tracking_number)");
            } else {
                throw new Exception('Unable to locate unique tracking restriction in database.');
            }

            $stmt_update_document = $conn->prepare($sql_update_document);
            if (!$stmt_update_document) {
                throw new Exception('Database error while retrying document update');
            }

            $stmt_update_document->bind_param('siii', $incoming_tracking_code, $user_id, $document_id, $user_id);
            if (!$stmt_update_document->execute()) {
                throw new Exception('Failed to update document with Administrative tracking code');
            }
        } else {
            throw new Exception('Failed to update document before forwarding');
        }
    }
    $stmt_update_document->close();

    // Create the outgoing assignment back to Administrative.
    // Include document description in notes so admin sees the actual content
    $doc_description = isset($document['description']) ? trim($document['description']) : '';
    $forward_notes = $doc_description !== '' ? $doc_description : 'Response to Administrative assignment';
    
    // DEBUG: Log what we're about to insert
    error_log("DEBUG forward-document-handler.php - About to INSERT assignment with:");
    error_log("  document_id: " . $document_id);
    error_log("  assigned_by (staff): " . $user_id);
    error_log("  assigned_to (admin): " . $administrative_user_id);
    error_log("  status: 'Forwarded'");
    
    $sql_insert_assignment = "INSERT INTO document_assignments
        (document_id, assigned_by, assigned_to, office_department, notes, status)
        VALUES (?, ?, ?, ?, ?, 'Forwarded')";

    $stmt_insert_assignment = $conn->prepare($sql_insert_assignment);
    if (!$stmt_insert_assignment) {
        throw new Exception('Database error while creating assignment');
    }

    $stmt_insert_assignment->bind_param('iiiss', $document_id, $user_id, $administrative_user_id, $office_department, $forward_notes);
    if (!$stmt_insert_assignment->execute()) {
        throw new Exception('Failed to create forwarding assignment');
    }
    $new_assignment_id = $conn->insert_id;
    error_log("DEBUG forward-document-handler.php - Successfully inserted assignment ID: " . $new_assignment_id);
    $stmt_insert_assignment->close();

        // Once forwarded, mark the source administrative instruction as forwarded.
        // Final completion is done by Administrative after status updates/review.
    $sql_complete_source = "UPDATE document_assignments da
        JOIN documents d ON d.id = da.document_id
                SET da.status = 'Forwarded', da.completed_at = NULL
        WHERE da.assigned_to = ?
                    AND da.status IN ('Received', 'Checking Documents')
          AND da.assigned_by = ?
          AND d.tracking_number = ?";

    $stmt_complete_source = $conn->prepare($sql_complete_source);
    if (!$stmt_complete_source) {
        throw new Exception('Failed to update source assignment status');
    }

    $stmt_complete_source->bind_param('iis', $user_id, $administrative_user_id, $incoming_tracking_code);
    if (!$stmt_complete_source->execute()) {
        throw new Exception('Failed to finalize source assignment status');
    }
    $stmt_complete_source->close();

    // Notify Administrative assignee that the department staff has forwarded the document.
    $staff_name = trim(($staff_details['first_name'] ?? '') . ' ' . ($staff_details['last_name'] ?? ''));
    if ($staff_name === '') {
        $staff_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    }
    if ($staff_name === '') {
        $staff_name = 'Assigned staff';
    }

    $staff_office = trim($staff_details['office_department'] ?? $office_department);
    if ($staff_office === '') {
        $staff_office = 'Office';
    }

    $forward_message = "$staff_office - $staff_name with tracking number: $incoming_tracking_code forwarded a document";

    createCustomNotification(
        $conn,
        $administrative_user_id,
        $document_id,
        $new_assignment_id,
        $incoming_tracking_code,
        $forward_message,
        'status_update',
        'Received',
        'Forwarded'
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document forwarded successfully. It is now outgoing from your side and incoming to Administrative.',
        'tracking_code' => $incoming_tracking_code,
        'assignment_id' => $new_assignment_id,
        'assigned_to' => trim(($source_assignment['first_name'] ?? '') . ' ' . ($source_assignment['last_name'] ?? ''))
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
