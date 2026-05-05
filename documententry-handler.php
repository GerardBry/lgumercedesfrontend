<?php
/**
 * Document Entry Handler - Department Staff
 * Creates a reply/request from an existing incoming assignment
 * while preserving the same tracking code and linked transaction flow.
 * Also handles saving documents as drafts.
 */
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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

$action = $data['action'] ?? '';

// Handle save_draft action
if ($action === 'save_draft') {
    handleSaveDraft($data);
    exit;
}

// Handle update_draft action
if ($action === 'update_draft') {
    handleUpdateDraft($data);
    exit;
}

// Handle reply_from_incoming action (existing)
if (($data['action'] ?? '') !== 'reply_from_incoming') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit;
}

$assignment_id = intval($data['assignment_id'] ?? 0);
$title = trim($data['title'] ?? '');
$document_type = trim($data['document_type'] ?? 'Travel Request');
$description = trim($data['description'] ?? '');

if ($assignment_id <= 0 || $title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
require_once 'config/db_connect.php';

$conn->begin_transaction();

try {
    // Get tracking_code from payload if provided
    $input_tracking_code = isset($_POST['tracking_code']) ? trim($_POST['tracking_code']) : '';

    // Load source assignment and ensure current staff owns it and already received it.
    $sql_source = "SELECT
            da.id,
            da.document_id,
            da.assigned_by,
            da.assigned_to,
            da.office_department,
            da.status,
            d.tracking_number,
            d.document_type
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        WHERE da.id = ? AND da.assigned_to = ?
        LIMIT 1";

    $stmt_source = $conn->prepare($sql_source);
    if (!$stmt_source) {
        throw new Exception('Database error');
    }

    $stmt_source->bind_param('ii', $assignment_id, $user_id);
    $stmt_source->execute();
    $result_source = $stmt_source->get_result();

    if ($result_source->num_rows === 0) {
        $stmt_source->close();
        throw new Exception('Source assignment not found or access denied');
    }

    $source = $result_source->fetch_assoc();
    $stmt_source->close();

    if ($source['status'] !== 'Received') {
        throw new Exception('Please receive the assignment first before creating a request');
    }

    // Validate tracking code matches source assignment
    $tracking_number = $source['tracking_number'];
    if (!empty($input_tracking_code) && $input_tracking_code !== $tracking_number) {
        throw new Exception('Tracking code mismatch. The tracking code must match the Administrative assignment.');
    }

    // Enforce request type from Administrative assignment - cannot be changed
    $source_document_type = $source['document_type'];
    if (!empty($document_type) && $document_type !== $source_document_type) {
        throw new Exception('Request type cannot be changed. It must match the Administrative assignment: ' . htmlspecialchars($source_document_type));
    }

    // Use the source document type (locked from Administrative)
    $document_type = $source_document_type;

    $admin_user_id = intval($source['assigned_by']);
    $office_department = $source['office_department'] ?? '';

    // Persist the department staff reply/request with same tracking code.
    $date_sent = date('Y-m-d H:i:s');
    $notes = 'Reply to assignment #' . $assignment_id;

    $sql_insert_doc = "INSERT INTO documents
            (title, description, tracking_number, document_type, sender_id, notes, created_by, date_sent, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

    $stmt_insert_doc = $conn->prepare($sql_insert_doc);
    if (!$stmt_insert_doc) {
        throw new Exception('Database error');
    }

    $stmt_insert_doc->bind_param(
        'ssssisis',
        $title,
        $description,
        $tracking_number,
        $document_type,
        $user_id,
        $notes,
        $user_id,
        $date_sent
    );
    $stmt_insert_doc->execute();
    $new_document_id = $conn->insert_id;
    $stmt_insert_doc->close();

    // Move source instruction to in-progress; final completion is handled by Administrative after review.
    $sql_complete_source = "UPDATE document_assignments
        SET status = 'Checking Documents', completed_at = NULL
        WHERE id = ? AND assigned_to = ? AND status = 'Received'";

    $stmt_complete_source = $conn->prepare($sql_complete_source);
    if (!$stmt_complete_source) {
        throw new Exception('Database error');
    }

    $stmt_complete_source->bind_param('ii', $assignment_id, $user_id);
    $stmt_complete_source->execute();
    $stmt_complete_source->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document created and saved to your Document Entry. It is not automatically sent.',
        'tracking_code' => $tracking_number,
        'document_id' => $new_document_id
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
exit;

// ==========================================
// HANDLE SAVE DRAFT
// ==========================================
function handleSaveDraft($data) {
    $user_id = intval($_SESSION['user_id']);
    $title = trim($data['title'] ?? '');
    $document_type = trim($data['document_type'] ?? 'Travel Request');
    $description = trim($data['description'] ?? '');
    $doc_code = trim($data['doc_code'] ?? '');
    $content = $data['content'] ?? array();

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        exit;
    }

    require_once 'config/db_connect.php';

    try {
        $conn->begin_transaction();

        // Generate tracking number
        $tracking_number = generateTrackingNumber($conn);

        // Store content as JSON
        $content_json = json_encode($content, JSON_UNESCAPED_UNICODE);

        // Save to documents table with 'Pending' status (saved but not yet submitted)
        $date_sent = date('Y-m-d H:i:s');
        $sql_insert = "INSERT INTO documents
            (title, description, tracking_number, document_type, sender_id, notes, created_by, date_sent, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $notes = $content_json; // Store full content in notes field for now

        $stmt_insert->bind_param(
            'ssssisis',
            $title,
            $description,
            $tracking_number,
            $document_type,
            $user_id,
            $notes,
            $user_id,
            $date_sent
        );
        $stmt_insert->execute();
        $document_id = $conn->insert_id;
        $stmt_insert->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Document saved to Document Entry. You can view and submit it from there.',
            'tracking_code' => $tracking_number,
            'document_id' => $document_id
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
    exit;
}

function handleUpdateDraft($data) {
    $user_id = intval($_SESSION['user_id']);
    $document_id = intval($data['document_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $document_type = trim($data['document_type'] ?? 'Travel Request');
    $description = trim($data['description'] ?? '');
    $content = $data['content'] ?? array();

    if ($document_id <= 0 || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document ID and Title are required']);
        exit;
    }

    require_once 'config/db_connect.php';

    try {
        $conn->begin_transaction();

        // Verify that the current user is the creator of this document
        $verify_sql = "SELECT id, created_by FROM documents WHERE id = ? LIMIT 1";
        $verify_stmt = $conn->prepare($verify_sql);
        if (!$verify_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $verify_stmt->bind_param('i', $document_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            throw new Exception('Document not found');
        }

        $doc = $verify_result->fetch_assoc();
        $verify_stmt->close();

        if ($doc['created_by'] != $user_id) {
            throw new Exception('Access denied: You can only edit your own documents');
        }

        // Update the document
        $content_json = json_encode($content, JSON_UNESCAPED_UNICODE);
        $date_updated = date('Y-m-d H:i:s');

        $update_sql = "UPDATE documents
            SET title = ?, description = ?, document_type = ?, notes = ?
            WHERE id = ? AND created_by = ?";

        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $update_stmt->bind_param(
            'ssssii',
            $title,
            $description,
            $document_type,
            $content_json,
            $document_id,
            $user_id
        );
        $update_stmt->execute();
        $update_stmt->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Document updated successfully',
            'document_id' => $document_id
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
    exit;
}

// Helper function to generate tracking number
function generateTrackingNumber($conn) {
    $date_part = date('Ymd');
    $random_part = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    $tracking_number = 'LGU-' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . $random_part;
    
    // Check if this tracking number already exists
    $check_sql = "SELECT id FROM documents WHERE tracking_number = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    
    while (true) {
        $check_stmt->bind_param("s", $tracking_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $check_stmt->close();
            return $tracking_number;
        }
        
        // Generate new number if it exists
        $random_part = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $tracking_number = 'LGU-' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . $random_part;
    }
}
?>