<?php
/**
 * Update Document Handler
 * Processes document edit/update
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../config/db_connect.php';

// Debug: Log incoming request
error_log('Update handler called - Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST data: ' . json_encode($_POST));

try {
    // Validate required fields
    $document_id = intval($_POST['document_id'] ?? 0);
    if (!$document_id) {
        throw new Exception('Invalid document ID');
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

    // Log for debugging
    error_log('Validation - title: ' . $title . ', sender: ' . $sender . ', date_received: ' . $date_received);
    error_log('Validation - classification: ' . $classification . ', sub_classification: ' . $sub_classification);

    // Validate required fields
    if (!$title) throw new Exception('Missing title');
    if (!$sender) throw new Exception('Missing sender');
    if (!$date_received) throw new Exception('Missing date_received');
    if (!$classification) throw new Exception('Missing classification');
    if (!$sub_classification) throw new Exception('Missing sub_classification');
    if (!$priority) throw new Exception('Missing priority');

    // Validate dates
    if (!strtotime($date_received)) {
        throw new Exception('Invalid date_received format: ' . $date_received);
    }

    if (!empty($deadline) && !strtotime($deadline)) {
        throw new Exception('Invalid deadline format: ' . $deadline);
    }

    // Validate classification and sub_classification
    $valid_classifications = ['Letter', 'Invitation', 'Travel-Related Communication', 'Indorsement'];
    if (!in_array($classification, $valid_classifications)) {
        throw new Exception('Invalid classification: ' . $classification . '. Valid values: ' . implode(', ', $valid_classifications));
    }

    $valid_sub_classifications = [
        'Letter' => ['Request Letter'],
        'Invitation' => ['Seminar/Training Invitation', 'Meeting Invitation', 'Conference/Event Invitation'],
        'Travel-Related Communication' => ['Official Travel Notice', 'Field Visit/Inspection', 'Meeting Assignment'],
        'Indorsement' => ['For Information', 'For Action']
    ];

    if (!isset($valid_sub_classifications[$classification])) {
        throw new Exception('Unknown classification for sub_classification validation: ' . $classification);
    }

    if (!in_array($sub_classification, $valid_sub_classifications[$classification])) {
        $valid_subs = implode(', ', $valid_sub_classifications[$classification]);
        throw new Exception('Invalid sub_classification: "' . $sub_classification . '". Valid values for ' . $classification . ': ' . $valid_subs);
    }

    // Check if document exists and belongs to current user
    $check_sql = 'SELECT id, notes FROM documents WHERE id = ? AND (created_by = ? OR id IN (SELECT document_id FROM document_assignments WHERE assigned_to = ?)) LIMIT 1';
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception('Database error');
    }

    $check_stmt->bind_param('iii', $document_id, $user_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $check_stmt->close();
        throw new Exception('Document not found or access denied - you can only edit documents you created or are assigned to');
    }

    $existing_doc = $result->fetch_assoc();
    $check_stmt->close();

    // Get existing file path from notes
    $existing_notes = json_decode($existing_doc['notes'], true) ?? [];
    $existing_file_path = $existing_notes['file_path'] ?? '';

    // Handle optional file upload during edit
    $new_file_path = $existing_file_path;
    if (isset($_FILES['document_file']) && $_FILES['document_file']['size'] > 0) {
        $file = $_FILES['document_file'];
        
        // Validate file
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_extensions));
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('File size exceeds 10MB limit');
        }
        
        // Create unique filename
        $upload_dir = '../uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $doc_id_prefix = 'DOC-' . str_pad($document_id, 4, '0', STR_PAD_LEFT);
        $unique_id = uniqid() . '-' . substr(md5(time() . rand()), 0, 8);
        $new_filename = $doc_id_prefix . '-' . $unique_id . '.' . $file_ext;
        $new_file_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $new_file_path)) {
            throw new Exception('Failed to upload file');
        }
        
        error_log('File uploaded successfully: ' . $new_file_path);
    }

    // Prepare updated data as JSON
    $updated_data = [
        'sender' => $sender,
        'date_received' => $date_received,
        'deadline' => $deadline,
        'classification' => $classification,
        'sub_classification' => $sub_classification,
        'priority' => $priority,
        'doc_sequence_number' => $existing_notes['doc_sequence_number'] ?? 0,
        'file_path' => $new_file_path
    ];
    $notes_json = json_encode($updated_data);

    // Update document - allow both creator and assigned user to edit
    $sql = "UPDATE documents SET
        title = ?,
        description = ?,
        document_type = ?,
        notes = ?,
        updated_at = NOW()
        WHERE id = ? AND (created_by = ? OR id IN (SELECT document_id FROM document_assignments WHERE assigned_to = ?))";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $document_type = $classification;

    if (!$stmt->bind_param(
        'ssssiii',
        $title,
        $description,
        $document_type,
        $notes_json,
        $document_id,
        $user_id,
        $user_id
    )) {
        throw new Exception('Bind parameters error: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute error: ' . $stmt->error);
    }

    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    if ($affected_rows === 0) {
        throw new Exception('Failed to update document');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Document updated successfully',
        'document_id' => $document_id
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
