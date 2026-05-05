<?php
/**
 * Update Assignment Status Handler - Administrative Staff
 * Updates the status of document assignments and creates notifications
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
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['assignment_id']) || !isset($data['new_status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$assignment_id = intval($data['assignment_id']);
$new_status = trim($data['new_status']);
$notes = isset($data['notes']) ? trim($data['notes']) : null;
$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current assignment details to get old status and recipient
    $sql_get = "SELECT 
                da.status as old_status,
                da.assigned_to,
                da.document_id,
                d.tracking_number,
                d.title
                FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.id = ? AND da.assigned_by = ?";
    
    $stmt_get = $conn->prepare($sql_get);
    if (!$stmt_get) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_get->bind_param('ii', $assignment_id, $user_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Assignment not found or access denied');
    }
    
    $assignment = $result->fetch_assoc();
    $old_status = $assignment['old_status'];
    $assigned_to = $assignment['assigned_to'];
    $document_id = $assignment['document_id'];
    $tracking_number = $assignment['tracking_number'];
    $title = $assignment['title'];
    
    $stmt_get->close();

    // Update assignment status
    $sql_update = "UPDATE document_assignments 
                   SET status = ? 
                   WHERE id = ? AND assigned_by = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_update->bind_param('sii', $new_status, $assignment_id, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    // Create notification for status update
    createStatusUpdateNotification($conn, $assigned_to, $document_id, $assignment_id, $tracking_number, $old_status, $new_status);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'old_status' => $old_status,
        'new_status' => $new_status,
        'tracking_number' => $tracking_number,
        'title' => $title
    ]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
