<?php
/**
 * Received Document Handler - Administrative Staff
 * Fetch document details and update workflow status for received documents
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

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';
$user_id = intval($_SESSION['user_id']);

function isTravelOrderType($value) {
    return $value === 'Travel Order' || $value === 'Travel Request';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['action']) || $_GET['action'] !== 'view') {
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
            da.completed_at,
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
            sender.role as sender_role,
            assigner.first_name as assigner_first_name,
            assigner.last_name as assigner_last_name
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        LEFT JOIN users assigner ON da.assigned_by = assigner.id
        WHERE da.id = ? AND da.assigned_to = ? AND da.status IN ('Received', 'Checking Documents', 'Waiting For Approval by Mayor')
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        $conn->close();
        exit;
    }

    $stmt->bind_param('ii', $assignment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $assignment = $result->fetch_assoc();
        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a file upload request or JSON request
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'multipart/form-data') !== false) {
        // Handle file upload for completion
        $assignment_id = intval($_POST['id'] ?? 0);
        $next_status = trim($_POST['status'] ?? '');
        $action = $_POST['action'] ?? '';
        
        if ($action !== 'update_status_with_file' || $assignment_id <= 0 || $next_status !== 'Completed') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            $conn->close();
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            $conn->close();
            exit;
        }

        $file = $_FILES['file'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($file['type'], $allowed_types)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            $conn->close();
            exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            $conn->close();
            exit;
        }

        // Create uploads directory if it doesn't exist
        $uploads_dir = dirname(__DIR__) . '/uploads/completions';
        if (!is_dir($uploads_dir)) {
            if (!mkdir($uploads_dir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create uploads directory']);
                $conn->close();
                exit;
            }
        }

        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'completion_' . $assignment_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        $file_path = $uploads_dir . '/' . $unique_filename;

        // Save file to disk
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
            $conn->close();
            exit;
        }

        // Store just the metadata and relative path in database (not the entire file)
        $relative_path = 'uploads/completions/' . $unique_filename;
        $completion_payload = json_encode([
            'name' => $file['name'],
            'type' => $file['type'],
            'path' => $relative_path,
            'size' => $file['size'],
            'uploaded_at' => date('Y-m-d H:i:s')
        ]);

        if ($completion_payload === false) {
            http_response_code(500);
            // Clean up uploaded file on failure
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            echo json_encode(['success' => false, 'message' => 'Failed to prepare uploaded file metadata']);
            $conn->close();
            exit;
        }

        // Verify assignment and permissions
        $sql_current = "SELECT da.status, da.assigned_to, d.document_type, d.id as document_id, d.tracking_number, sender.role as sender_role
            FROM document_assignments da
            JOIN documents d ON da.document_id = d.id
            LEFT JOIN users sender ON d.sender_id = sender.id
            WHERE da.id = ? AND da.assigned_to = ? LIMIT 1";

        $stmt_current = $conn->prepare($sql_current);
        if (!$stmt_current) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }

        $stmt_current->bind_param('ii', $assignment_id, $user_id);
        $stmt_current->execute();
        $current_result = $stmt_current->get_result();

        if ($current_result->num_rows === 0) {
            $stmt_current->close();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
            $conn->close();
            exit;
        }

        $current = $current_result->fetch_assoc();
        $stmt_current->close();

        if (!isTravelOrderType($current['document_type']) || ($current['sender_role'] ?? '') !== 'Department Staff') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Status update is only allowed for Travel Order documents from Department Staff']);
            $conn->close();
            exit;
        }

        $current_status = $current['status'];
        $assigned_to = intval($current['assigned_to']);
        $document_id = intval($current['document_id']);
        $tracking_number = $current['tracking_number'] ?? '';
        $allowed_progression = [
            'Received' => 'Checking Documents',
            'Checking Documents' => 'Waiting For Approval by Mayor',
            'Waiting For Approval by Mayor' => 'Completed'
        ];

        if (!isset($allowed_progression[$current_status]) || $allowed_progression[$current_status] !== $next_status) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid workflow transition']);
            $conn->close();
            exit;
        }

        // Get the assigned_by (the person who assigned this to Administrative)
        $sql_assigner = "SELECT da.assigned_by, d.tracking_number 
                         FROM document_assignments da 
                         JOIN documents d ON da.document_id = d.id 
                         WHERE da.id = ? LIMIT 1";
        $stmt_assigner = $conn->prepare($sql_assigner);
        $stmt_assigner->bind_param('i', $assignment_id);
        $stmt_assigner->execute();
        $result_assigner = $stmt_assigner->get_result();
        $assigner_data = $result_assigner->fetch_assoc();
        $stmt_assigner->close();
        
        $notify_user_id = intval($assigner_data['assigned_by']);
        $tracking_number = $assigner_data['tracking_number'] ?? '';

        // Update status with database-stored file payload
        $conn->begin_transaction();
        try {
            $sql_update = "UPDATE document_assignments
                SET status = ?,
                    completed_at = NOW(),
                    completion_file = ?,
                    updated_at = NOW()
                WHERE id = ? AND assigned_to = ?";

            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception('Database error');
            }

            $stmt_update->bind_param('ssii', $next_status, $completion_payload, $assignment_id, $user_id);
            if (!$stmt_update->execute()) {
                throw new Exception('Failed to update workflow status: ' . $stmt_update->error);
            }
            $stmt_update->close();

            // Create notification for Department Staff (assigned_by) about status update
            createCustomNotification(
                $conn,
                $notify_user_id,
                $document_id,
                $assignment_id,
                $tracking_number,
                "Administrative has updated your document status to: $next_status",
                'status_update',
                $current_status,
                $next_status
            );

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Document marked as completed successfully', 'status' => $next_status]);
        } catch (Exception $e) {
            $conn->rollback();
            // Clean up uploaded file on failure
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $conn->close();
        exit;
    } else {
        // Handle JSON request for regular status update
        $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || ($data['action'] ?? '') !== 'update_status') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
        $conn->close();
        exit;
    }

    $assignment_id = intval($data['id'] ?? 0);
    $next_status = trim($data['status'] ?? '');
    if ($assignment_id <= 0 || $next_status === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing assignment ID or status']);
        $conn->close();
        exit;
    }

    $allowed_progression = [
        'Received' => 'Checking Documents',
        'Checking Documents' => 'Waiting For Approval by Mayor',
        'Waiting For Approval by Mayor' => 'Completed'
    ];

    $sql_current = "SELECT da.status, da.assigned_to, d.document_type, d.id as document_id, d.tracking_number, sender.role as sender_role
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        WHERE da.id = ? AND da.assigned_to = ? LIMIT 1";

    $stmt_current = $conn->prepare($sql_current);
    if (!$stmt_current) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        $conn->close();
        exit;
    }

    $stmt_current->bind_param('ii', $assignment_id, $user_id);
    $stmt_current->execute();
    $current_result = $stmt_current->get_result();

    if ($current_result->num_rows === 0) {
        $stmt_current->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
        $conn->close();
        exit;
    }

    $current = $current_result->fetch_assoc();
    $stmt_current->close();

    if (!isTravelOrderType($current['document_type']) || ($current['sender_role'] ?? '') !== 'Department Staff') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Status update is only allowed for Travel Order documents from Department Staff']);
        $conn->close();
        exit;
    }

    $current_status = $current['status'];
    $document_id = intval($current['document_id']);
    
    // Get the assigned_by (the person who assigned this to Administrative)
    $sql_assigner = "SELECT da.assigned_by, d.tracking_number 
                     FROM document_assignments da 
                     JOIN documents d ON da.document_id = d.id 
                     WHERE da.id = ? LIMIT 1";
    $stmt_assigner = $conn->prepare($sql_assigner);
    $stmt_assigner->bind_param('i', $assignment_id);
    $stmt_assigner->execute();
    $result_assigner = $stmt_assigner->get_result();
    $assigner_data = $result_assigner->fetch_assoc();
    $stmt_assigner->close();
    
    $notify_user_id = intval($assigner_data['assigned_by']);
    $tracking_number = $assigner_data['tracking_number'] ?? '';
    
    if (!isset($allowed_progression[$current_status]) || $allowed_progression[$current_status] !== $next_status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid workflow transition']);
        $conn->close();
        exit;
    }

    $conn->begin_transaction();
    try {
        $sql_update = "UPDATE document_assignments
            SET status = ?,
                completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE completed_at END,
                updated_at = NOW()
            WHERE id = ? AND assigned_to = ?";

        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception('Database error');
        }

        $stmt_update->bind_param('ssii', $next_status, $next_status, $assignment_id, $user_id);
        if (!$stmt_update->execute()) {
            throw new Exception('Failed to update workflow status');
        }
        $stmt_update->close();

        // Create notification for Department Staff (assigned_by) about status update
        createCustomNotification(
            $conn,
            $notify_user_id,
            $document_id,
            $assignment_id,
            $tracking_number,
            "Administrative has updated your document status to: $next_status",
            'status_update',
            $current_status,
            $next_status
        );

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'status' => $next_status]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
    exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
$conn->close();
