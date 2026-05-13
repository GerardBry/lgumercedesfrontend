<?php
/**
 * Add Document Handler
 * Processes form submission and saves document to database
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../config/db_connect.php';

try {
    // Validate required fields
    $required_fields = ['title', 'sender', 'date_received', 'classification', 'sub_classification', 'priority'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception('Missing required field: ' . $field);
        }
    }

    // Validate file upload
    if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please upload a valid document file');
    }

    $user_id = intval($_SESSION['user_id']);
    $title = trim($_POST['title'] ?? '');
    $sender = trim($_POST['sender'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_received = trim($_POST['date_received'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $classification = trim($_POST['classification'] ?? '');
    $sub_classification = trim($_POST['sub_classification'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Normal');
    $doc_sequence_number = intval($_POST['doc_sequence_number'] ?? 0);

    // Get user's department
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

    // Validate dates
    if (!strtotime($date_received)) {
        throw new Exception('Invalid date received');
    }

    if (!empty($deadline) && !strtotime($deadline)) {
        throw new Exception('Invalid deadline date');
    }

    // Validate classification and sub_classification
    $valid_classifications = ['Letter', 'Invitation', 'Travel-Related Communication', 'Indorsement'];
    if (!in_array($classification, $valid_classifications)) {
        throw new Exception('Invalid classification');
    }

    $valid_sub_classifications = [
        'Letter' => ['Request Letter'],
        'Invitation' => ['Seminar/Training Invitation', 'Meeting Invitation', 'Conference/Event Invitation'],
        'Travel-Related Communication' => ['Official Travel Notice', 'Field Visit/Inspection', 'Meeting Assignment'],
        'Indorsement' => ['For Information', 'For Action']
    ];

    if (!isset($valid_sub_classifications[$classification]) || 
        !in_array($sub_classification, $valid_sub_classifications[$classification])) {
        throw new Exception('Invalid sub-classification');
    }

    // Handle file upload
    $file = $_FILES['document_file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];

    // Validate file size (10MB max)
    $max_size = 10 * 1024 * 1024;
    if ($file_size > $max_size) {
        throw new Exception('File size exceeds 10MB limit');
    }

    // Validate file type
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
    $file_type = mime_content_type($file_tmp);
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only PDF and image files are allowed');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/documents/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $unique_filename = 'DOC-' . str_pad($doc_sequence_number, 4, '0', STR_PAD_LEFT) . '-' . time() . '-' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $file_path)) {
        throw new Exception('Failed to save file');
    }

    // Generate tracking number
    $tracking_number = 'LGU-' . date('Y-m-d') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    for ($i = 0; $i < 10; $i++) {
        $check_sql = 'SELECT id FROM documents WHERE tracking_number = ? LIMIT 1';
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception('Database error');
        }

        $check_stmt->bind_param('s', $tracking_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();

        if ($result->num_rows === 0) {
            break;
        }
        $tracking_number = 'LGU-' . date('Y-m-d') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    // Prepare additional data as JSON
    $additional_data = [
        'sender' => $sender,
        'date_received' => $date_received,
        'deadline' => $deadline,
        'classification' => $classification,
        'sub_classification' => $sub_classification,
        'priority' => $priority,
        'doc_sequence_number' => $doc_sequence_number,
        'file_path' => $file_path
    ];
    $notes_json = json_encode($additional_data);

    // Insert document using existing columns
    $sql = "INSERT INTO documents (
        title,
        description,
        tracking_number,
        document_type,
        date_sent,
        notes,
        status,
        created_by,
        created_at,
        doc_sequence_number,
        office_department,
        sender_name,
        date_received,
        deadline,
        file_path,
        classification,
        sub_classification,
        priority
    ) VALUES (?, ?, ?, ?, NOW(), ?, 'Active', ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    // Use classification as document_type
    $document_type = $classification;
    
    // Convert dates to proper timestamp format
    $date_received_ts = date('Y-m-d H:i:s', strtotime($date_received));
    $deadline_ts = !empty($deadline) ? date('Y-m-d H:i:s', strtotime($deadline)) : null;

    if (!$stmt->bind_param(
        'sssssisssisssss',
        $title,
        $description,
        $tracking_number,
        $document_type,
        $notes_json,
        $user_id,
        $doc_sequence_number,
        $user_dept,
        $sender,
        $date_received_ts,
        $deadline_ts,
        $file_path,
        $classification,
        $sub_classification,
        $priority
    )) {
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        throw new Exception('Bind parameters error: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        throw new Exception('Execute error: ' . $stmt->error);
    }

    $document_id = $conn->insert_id;
    $stmt->close();
    $conn->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Document added successfully',
        'document_id' => $document_id,
        'tracking_number' => $tracking_number,
        'doc_id' => 'DOC-' . str_pad($doc_sequence_number, 4, '0', STR_PAD_LEFT)
    ]);

} catch (Exception $e) {
    // Cleanup
    if (isset($file_path) && file_exists($file_path)) {
        @unlink($file_path);
    }

    if (isset($conn)) {
        $conn->close();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
