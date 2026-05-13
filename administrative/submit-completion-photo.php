<?php
/**
 * Handle completion photo upload for received documents
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is Administrative Assistant
if ($_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['assignment_id']) || !isset($data['photo'])) {
        throw new Exception('Missing required data');
    }
    
    $assignment_id = intval($data['assignment_id']);
    $photo_data = $data['photo'];
    $filename = $data['filename'] ?? 'photo.jpg';
    
    // Connect to database
    require_once '../config/db_connect.php';
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Decode base64 photo
    if (strpos($photo_data, 'data:image') === 0) {
        $photo_data = substr($photo_data, strpos($photo_data, ',') + 1);
    }
    $photo_binary = base64_decode($photo_data);
    
    if ($photo_binary === false) {
        throw new Exception('Invalid photo data');
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/completion_photos';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
    $photo_filename = 'completion_' . $assignment_id . '_' . time() . '.' . $ext;
    $photo_path = $upload_dir . '/' . $photo_filename;
    
    // Save photo
    if (!file_put_contents($photo_path, $photo_binary)) {
        throw new Exception('Failed to save photo file');
    }
    
    // Update database - mark assignment as completed
    $photo_path_db = 'uploads/completion_photos/' . $photo_filename;
    $user_id = $_SESSION['user_id'];
    $completed_at = date('Y-m-d H:i:s');
    $completion_payload = json_encode([
        'name' => $filename,
        'type' => $file_type ?? 'image/jpeg',
        'path' => $photo_path_db
    ]);

    // Store the uploaded photo using the same completion file field used elsewhere.
    $stmt = $conn->prepare("
        UPDATE document_assignments 
        SET status = 'Completed', 
            completed_at = ?,
            completion_file = ?
        WHERE id = ? AND assigned_to = ?
    ");

    if ($stmt) {
        $stmt->bind_param('ssii', $completed_at, $completion_payload, $assignment_id, $user_id);
    } else {
        // Fallback for older schemas that have completed_at but not completion_file.
        $stmt = $conn->prepare("
            UPDATE document_assignments 
            SET status = 'Completed', 
                completed_at = ?
            WHERE id = ? AND assigned_to = ?
        ");

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('sii', $completed_at, $assignment_id, $user_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Assignment not found or not assigned to you');
    }
    
    $stmt->close();
    
    // Log the action
    $log_stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, created_at)
        VALUES (?, 'complete_document', 'assignment', ?, ?, NOW())
    ");
    
    if ($log_stmt) {
        $details = 'Completed assignment with photo: ' . $photo_filename;
        $log_stmt->bind_param('iss', $user_id, $assignment_id, $details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Document completed successfully',
        'photo_path' => $photo_path_db,
        'uploader_name' => ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''),
        'upload_time' => $completed_at
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

