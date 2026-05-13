<?php
/**
 * Document Entry Handler - Department Staff
 * Handles both:
 * 1. Form submissions (multipart/form-data) for new document creation with file uploads
 * 2. JSON requests for drafts and replies
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/uploads/documents/php_errors.log');
date_default_timezone_set('Asia/Manila');

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

// Check if this is a form submission (multipart/form-data) or JSON
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_form_submission = strpos($content_type, 'multipart/form-data') !== false;

if ($is_form_submission) {
    // Handle form submission with file upload
    handleFormSubmission();
    exit;
}

// Otherwise handle JSON requests (existing logic)
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit;
}

$action = $data['action'] ?? '';

// Handle add_travel_request action
if ($action === 'add_travel_request') {
    try {
        handleAddTravelRequest($data);
    } catch (Exception $e) {
        http_response_code(400);
        error_log('Travel Request Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

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

// ==========================================
// HANDLE FORM SUBMISSION (FILE UPLOAD)
// ==========================================
function handleFormSubmission() {
    $log_file = 'uploads/documents/debug.log';
    file_put_contents($log_file, "\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
    
    $user_id = intval($_SESSION['user_id']);
    require_once 'config/db_connect.php';
    
    // Get user's department from database
    $user_dept = '';
    $dept_stmt = $conn->prepare("SELECT office_department FROM users WHERE id = ?");
    if ($dept_stmt) {
        $dept_stmt->bind_param("i", $user_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        if ($dept_result->num_rows > 0) {
            $dept_row = $dept_result->fetch_assoc();
            $user_dept = $dept_row['office_department'] ?? '';
        }
        $dept_stmt->close();
    }
    
    // Get form fields
    $title = trim($_POST['title'] ?? '');
    $sender = trim($_POST['sender'] ?? '');
    $date_received = $_POST['date_received'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $classification = trim($_POST['classification'] ?? '');
    $sub_classification = trim($_POST['sub_classification'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    $deadline = $_POST['deadline'] ?? null;
    $doc_sequence_number = intval($_POST['doc_sequence_number'] ?? 0);
    
    file_put_contents($log_file, "Fields received:\n", FILE_APPEND);
    file_put_contents($log_file, "  title: " . var_export($title, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, "  sender: " . var_export($sender, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, "  classification: " . var_export($classification, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, "  sub_classification: " . var_export($sub_classification, true) . "\n", FILE_APPEND);
    file_put_contents($log_file, "  priority: " . var_export($priority, true) . "\n", FILE_APPEND);
    
    // Validate required fields
    if (!$title || !$sender || !$date_received || !$classification || !$sub_classification || !$priority) {
        $missing = [];
        if (!$title) $missing[] = 'title';
        if (!$sender) $missing[] = 'sender';
        if (!$date_received) $missing[] = 'date_received';
        if (!$classification) $missing[] = 'classification';
        if (!$sub_classification) $missing[] = 'sub_classification';
        if (!$priority) $missing[] = 'priority';
        
        file_put_contents($log_file, "VALIDATION FAILED: " . implode(', ', $missing) . "\n", FILE_APPEND);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
        exit;
    }
    
    file_put_contents($log_file, "Validation passed\n", FILE_APPEND);
    
    // Handle file upload
    $file_path = null;
    file_put_contents($log_file, "Checking file upload...\n", FILE_APPEND);
    if (isset($_FILES['document_file'])) {
        $file_error = $_FILES['document_file']['error'];
        file_put_contents($log_file, "File error code: $file_error\n", FILE_APPEND);
        
        if ($file_error !== UPLOAD_ERR_OK && $file_error !== UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            $error_msg = match($file_error) {
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds max_file_size',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Extension not allowed',
                default => 'Unknown upload error'
            };
            echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error_msg]);
            exit;
        }
        
        if ($file_error === UPLOAD_ERR_OK) {
            $file = $_FILES['document_file'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid file type: ' . $file['type'] . '. Allowed: PDF, JPG, PNG, GIF, WebP, BMP, TIFF']);
                exit;
            }
            
            if ($file['size'] > $max_size) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
                exit;
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = 'DOC-' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . time() . '-' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                exit;
            }
        }
    } else {
        // File is required for new documents
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document file is required']);
        exit;
    }
    
    // Generate tracking number
    $tracking_number = 'DOC-' . date('YmdHis') . '-' . uniqid();
    
    // Prepare notes JSON
    $notes_data = [
        'sender' => $sender,
        'date_received' => $date_received,
        'classification' => $classification,
        'sub_classification' => $sub_classification,
        'priority' => $priority,
        'deadline' => $deadline,
        'file_path' => $file_path,
        'doc_sequence_number' => $doc_sequence_number
    ];
    
    try {
        $conn->begin_transaction();
        
        $notes_json = json_encode($notes_data, JSON_UNESCAPED_UNICODE);
        $date_sent = date('Y-m-d H:i:s');
        
        file_put_contents($log_file, "About to insert document...\n", FILE_APPEND);
        file_put_contents($log_file, "  user_dept: $user_dept\n", FILE_APPEND);
        file_put_contents($log_file, "  file_path: $file_path\n", FILE_APPEND);
        
        // Insert document
        $sql = "INSERT INTO documents 
                (title, description, tracking_number, created_by, office_department, status, doc_sequence_number, notes, sender_name, date_received, deadline, file_path, classification, sub_classification, priority)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        // Convert date strings to timestamp format if provided
        $date_received_ts = $date_received ? date('Y-m-d H:i:s', strtotime($date_received)) : null;
        $deadline_ts = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
        
        $stmt->bind_param('ssisssisssssss', 
            $title, $description, $tracking_number, $user_id, $user_dept, 
            $doc_sequence_number, $notes_json, $sender, $date_received_ts, $deadline_ts, 
            $file_path, $classification, $sub_classification, $priority);
        $stmt->execute();
        $document_id = $conn->insert_id;
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Document created successfully',
            'tracking_code' => $tracking_number,
            'document_id' => $document_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        // Delete uploaded file if insert failed
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        
        file_put_contents($log_file, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    $conn->close();
    exit;
}

// ==========================================

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
require_once 'config/notification_helpers.php';

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
// HANDLE ADD TRAVEL REQUEST
// ==========================================
function handleAddTravelRequest($data) {
    $user_id = intval($_SESSION['user_id']);
    require_once 'config/db_connect.php';
    require_once 'config/notification_helpers.php';

    // Get user's department from database
    $user_dept = '';
    $dept_stmt = $conn->prepare("SELECT office_department FROM users WHERE id = ?");
    if ($dept_stmt) {
        $dept_stmt->bind_param("i", $user_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        if ($dept_result->num_rows > 0) {
            $dept_row = $dept_result->fetch_assoc();
            $user_dept = $dept_row['office_department'] ?? '';
        }
        $dept_stmt->close();
    }

    // Get form fields
    $title = trim($data['title'] ?? '');
    $sender = trim($data['sender'] ?? '');
    $date_received = $data['date_received'] ?? '';
    $description = trim($data['description'] ?? '');
    $classification = trim($data['classification'] ?? '');
    $sub_classification = trim($data['sub_classification'] ?? '');
    $priority = trim($data['priority'] ?? 'Normal');
    $document_type = trim($data['document_type'] ?? 'Travel Request');
    $notes = $data['notes'] ?? [];
    $parent_document_id = intval($data['parent_document_id'] ?? 0);
    $source_assignment_id = intval($data['assignment_id'] ?? 0);

    // Validate required fields
    if (!$title || !$sender || !$date_received || !$classification || !$sub_classification) {
        $missing = [];
        if (!$title) $missing[] = 'title';
        if (!$sender) $missing[] = 'sender';
        if (!$date_received) $missing[] = 'date_received';
        if (!$classification) $missing[] = 'classification';
        if (!$sub_classification) $missing[] = 'sub_classification';
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
        exit;
    }

    // Generate tracking number
    $tracking_number = 'DOC-' . date('YmdHis') . '-' . uniqid();

    // Prepare notes JSON
    $notes_data = is_array($notes) ? $notes : [];
    $notes_data = array_merge($notes_data, [
        'sender' => $sender,
        'date_received' => $date_received,
        'classification' => $classification,
        'sub_classification' => $sub_classification,
        'priority' => $priority,
        'document_type' => $document_type,
        'type' => 'Travel Request',
        'parent_document_id' => $parent_document_id
    ]);

    try {
        $conn->begin_transaction();

        $notes_json = json_encode($notes_data, JSON_UNESCAPED_UNICODE);
        if (!$notes_json) {
            throw new Exception('Failed to encode notes: ' . json_last_error_msg());
        }

        $date_sent = date('Y-m-d H:i:s');
        $date_received_ts = null;
        
        if (!empty($date_received)) {
            $timestamp = strtotime($date_received);
            if ($timestamp === false) {
                error_log('Date parsing failed for: ' . $date_received);
                $date_received_ts = date('Y-m-d H:i:s'); // Use current date as fallback
            } else {
                $date_received_ts = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Insert document
        $sql = "INSERT INTO documents 
                (title, description, tracking_number, created_by, office_department, status, notes, sender_name, date_received, file_path, classification, sub_classification, priority, document_type, date_sent)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, NULL, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $status = 'Pending';
        $stmt->bind_param('sssisssssssss', 
            $title, $description, $tracking_number, $user_id, $user_dept, 
            $notes_json, $sender, $date_received_ts, 
            $classification, $sub_classification, $priority, $document_type, $date_sent);
        $stmt->execute();
        $document_id = $conn->insert_id;
        $stmt->close();

        $admin_sql = "SELECT id FROM users WHERE role = 'Administrative Assistant' ORDER BY id ASC";
        $admin_result = $conn->query($admin_sql);
        if ($admin_result && $admin_result->num_rows > 0) {
            while ($admin_row = $admin_result->fetch_assoc()) {
                $notification_created = createCustomNotification(
                    $conn,
                    intval($admin_row['id']),
                    $document_id,
                    $source_assignment_id,
                    $tracking_number,
                    "Department staff submitted a travel request ($tracking_number)",
                    'assignment',
                    null,
                    'Pending'
                );

                if (!$notification_created) {
                    throw new Exception('Failed to create travel request notification');
                }
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Travel Request created successfully',
            'tracking_code' => $tracking_number,
            'document_id' => $document_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
    exit;
}

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