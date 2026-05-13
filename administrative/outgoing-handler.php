<?php
/**
 * Outgoing Document Handler - Administrative Staff
 * Handle document viewing, file uploads, and status updates
 */
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow Administrative Assistant
if ($_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle GET requests (viewing document)
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
        exit;
    }

    $assignment_id = intval($_GET['id']);

    // Fetch assignment details - verify user is the assigned_by (sender)
    $sql = "SELECT 
            da.id,
            da.document_id,
            da.assigned_by,
            da.assigned_to,
            da.office_department,
            da.notes as assignment_notes,
            da.status as assignment_status,
            da.assigned_at,
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
            d.notes as doc_notes,
            d.status,
            sender.first_name as sender_first_name,
            sender.last_name as sender_last_name,
            recipient.first_name as recipient_first_name,
            recipient.last_name as recipient_last_name,
            recipient.position as recipient_position
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        JOIN users recipient ON da.assigned_to = recipient.id
        WHERE da.id = ? AND da.assigned_by = ? LIMIT 1";

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
        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle POST requests (file upload or status update)
if ($method === 'POST') {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Handle multipart form data (file uploads)
    if (strpos($content_type, 'multipart/form-data') !== false) {
        $action = $_POST['action'] ?? '';
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        
        if ($assignment_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
            $conn->close();
            exit;
        }
        
        // Verify user is the assigned_by (admin who sent the document)
        $verify_sql = "SELECT da.id FROM document_assignments da WHERE da.id = ? AND da.assigned_by = ? LIMIT 1";
        $verify_stmt = $conn->prepare($verify_sql);
        if (!$verify_stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            $conn->close();
            exit;
        }
        
        $verify_stmt->bind_param('ii', $assignment_id, $user_id);
        $verify_stmt->execute();
        if ($verify_stmt->get_result()->num_rows === 0) {
            $verify_stmt->close();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            $conn->close();
            exit;
        }
        $verify_stmt->close();
        
        if ($action === 'upload_file') {
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
            
            if ($file['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
                $conn->close();
                exit;
            }
            
            // Create uploads directory if it doesn't exist
            $uploads_dir = dirname(__DIR__) . '/uploads/documents';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('upload_' . time() . '_') . '.' . $file_ext;
            $file_path = $uploads_dir . '/' . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save file']);
                $conn->close();
                exit;
            }
            
            // Store in database
            $notes = trim($_POST['notes'] ?? '');
            $relative_path = 'uploads/documents/' . $file_name;
            $uploaded_by = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
            
            // Ensure table exists
            $create_table_sql = "CREATE TABLE IF NOT EXISTS document_uploads (
                id INT PRIMARY KEY AUTO_INCREMENT,
                assignment_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                notes TEXT,
                uploaded_by VARCHAR(255),
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY (assignment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if (!$conn->query($create_table_sql)) {
                unlink($file_path);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                $conn->close();
                exit;
            }
            
            $sql = "INSERT INTO document_uploads (assignment_id, file_path, notes, uploaded_by, uploaded_at)
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                unlink($file_path);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                $conn->close();
                exit;
            }
            
            $stmt->bind_param('isss', $assignment_id, $relative_path, $notes, $uploaded_by);
            if (!$stmt->execute()) {
                unlink($file_path);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save upload record']);
                $stmt->close();
                $conn->close();
                exit;
            }
            
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'File uploaded successfully', 'uploader_name' => $uploaded_by]);
            exit;
        }
    } else {
        // Handle JSON request (status updates)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (is_array($data) && ($data['action'] ?? '') === 'mark_completed') {
            $assignment_id = intval($data['assignment_id'] ?? 0);
            
            if ($assignment_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
                $conn->close();
                exit;
            }
            
            // Verify user is the assigned_by (admin)
            $sql_verify = "SELECT da.id, da.status, d.tracking_number, d.id as document_id, da.assigned_to
                           FROM document_assignments da
                           JOIN documents d ON da.document_id = d.id
                           WHERE da.id = ? AND da.assigned_by = ?
                           LIMIT 1";
            
            $stmt_verify = $conn->prepare($sql_verify);
            if (!$stmt_verify) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                $conn->close();
                exit;
            }
            
            $stmt_verify->bind_param('ii', $assignment_id, $user_id);
            $stmt_verify->execute();
            $result_verify = $stmt_verify->get_result();
            
            if ($result_verify->num_rows === 0) {
                $stmt_verify->close();
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
                $conn->close();
                exit;
            }
            
            $assignment = $result_verify->fetch_assoc();
            $stmt_verify->close();
            
            // Update status to Completed
            $sql_update = "UPDATE document_assignments
                           SET status = 'Completed',
                               completed_at = NOW(),
                               updated_at = NOW()
                           WHERE id = ? AND assigned_by = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                $conn->close();
                exit;
            }
            
            $stmt_update->bind_param('ii', $assignment_id, $user_id);
            if (!$stmt_update->execute()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                $stmt_update->close();
                $conn->close();
                exit;
            }
            $stmt_update->close();
            
            // Create notification
            $notify_user_id = intval($assignment['assigned_to']);
            $document_id = intval($assignment['document_id']);
            $tracking_number = $assignment['tracking_number'] ?? '';
            
            $notification_created = createCustomNotification(
                $conn,
                $notify_user_id,
                $document_id,
                $assignment_id,
                $tracking_number,
                "Administrative has marked your assigned document as Completed",
                'status_update',
                $assignment['status'],
                'Completed'
            );
            
            if (!$notification_created) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create completion notification']);
                $conn->close();
                exit;
            }
            
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'Document marked as Completed successfully']);
            exit;
        }
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
$conn->close();
?>