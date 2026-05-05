<?php
/**
 * Administrative Document Entry Handler
 * Saves administrative drafts from Document Entry.
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

$action = $data['action'] ?? '';
if ($action !== 'save_draft' && $action !== 'update_draft') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$title = trim($data['title'] ?? '');
$document_type = trim($data['document_type'] ?? 'Travel Request');
$description = trim($data['description'] ?? '');
$content = $data['content'] ?? [];

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

require_once '../config/db_connect.php';

function generateTrackingNumber($conn) {
    while (true) {
        $random_part = str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT);
        $tracking_number = 'LGU-' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . $random_part;

        $check_sql = 'SELECT id FROM documents WHERE tracking_number = ? LIMIT 1';
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception('Database error while validating tracking number');
        }

        $check_stmt->bind_param('s', $tracking_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();

        if ($result->num_rows === 0) {
            return $tracking_number;
        }
    }
}

try {
    $conn->begin_transaction();

    if ($action === 'update_draft') {
        // Handle update draft
        $document_id = intval($data['document_id'] ?? 0);
        
        if ($document_id <= 0) {
            throw new Exception('Document ID is required for updates');
        }

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
    } else {
        // Handle save draft
        $tracking_number = generateTrackingNumber($conn);
        $date_sent = date('Y-m-d H:i:s');
        $content_json = json_encode($content, JSON_UNESCAPED_UNICODE);

        $sql_insert = "INSERT INTO documents
            (title, description, tracking_number, document_type, sender_id, notes, created_by, date_sent, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt_insert->bind_param(
            'ssssisis',
            $title,
            $description,
            $tracking_number,
            $document_type,
            $user_id,
            $content_json,
            $user_id,
            $date_sent
        );
        $stmt_insert->execute();
        $document_id = $conn->insert_id;
        $stmt_insert->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Document saved to Document Entry.',
            'tracking_code' => $tracking_number,
            'document_id' => $document_id
        ]);
    }
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