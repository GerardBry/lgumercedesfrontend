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
        $upload_dir = '../uploads/completions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'completion_' . $assignment_id . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
            $conn->close();
            exit;
        }

        // Verify assignment and permissions
        $sql_current = "SELECT da.status, d.document_type, sender.role as sender_role
            FROM document_assignments da
            JOIN documents d ON da.document_id = d.id
            LEFT JOIN users sender ON d.sender_id = sender.id
            WHERE da.id = ? AND da.assigned_to = ? LIMIT 1";

        $stmt_current = $conn->prepare($sql_current);
        if (!$stmt_current) {
            unlink($file_path);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }

        $stmt_current->bind_param('ii', $assignment_id, $user_id);
        $stmt_current->execute();
        $current_result = $stmt_current->get_result();

        if ($current_result->num_rows === 0) {
            unlink($file_path);
            $stmt_current->close();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
            $conn->close();
            exit;
        }

        $current = $current_result->fetch_assoc();
        $stmt_current->close();

        if (!isTravelOrderType($current['document_type']) || ($current['sender_role'] ?? '') !== 'Department Staff') {
            unlink($file_path);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Status update is only allowed for Travel Order documents from Department Staff']);
            $conn->close();
            exit;
        }

        $current_status = $current['status'];
        $allowed_progression = [
            'Received' => 'Checking Documents',
            'Checking Documents' => 'Waiting For Approval by Mayor',
            'Waiting For Approval by Mayor' => 'Completed'
        ];

        if (!isset($allowed_progression[$current_status]) || $allowed_progression[$current_status] !== $next_status) {
            unlink($file_path);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid workflow transition']);
            $conn->close();
            exit;
        }

        // Update status with file reference
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

            $stmt_update->bind_param('ssii', $next_status, $file_name, $assignment_id, $user_id);
            if (!$stmt_update->execute()) {
                throw new Exception('Failed to update workflow status');
            }
            $stmt_update->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Document marked as completed successfully', 'status' => $next_status]);
        } catch (Exception $e) {
            $conn->rollback();
            unlink($file_path);
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

    $sql_current = "SELECT da.status, d.document_type, sender.role as sender_role
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
