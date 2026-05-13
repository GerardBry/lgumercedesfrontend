<?php
/**
 * Route Document Handler
 * Processes document routing from Department Staff to Administrative
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

require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

try {
    // Get JSON payload
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action']) || $input['action'] !== 'route_document') {
        throw new Exception('Invalid request');
    }

    // Validate required fields
    $document_id = intval($input['document_id'] ?? 0);
    $tracking_code = trim($input['tracking_code'] ?? '');

    if (!$document_id || !$tracking_code) {
        throw new Exception('Missing required fields');
    }

    $user_id = intval($_SESSION['user_id']);

    // Get the original document
    $stmt = $conn->prepare('SELECT id, title, description, created_by FROM documents WHERE id = ? AND created_by = ?');
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('ii', $document_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc();
    $stmt->close();

    if (!$document) {
        throw new Exception('Document not found or unauthorized');
    }

    // Update the original document's tracking_number with the new tracking code
    // This marks it as routed and tracks it in the system
    $stmt = $conn->prepare('UPDATE documents SET tracking_number = ? WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('si', $tracking_code, $document_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update tracking number: ' . $stmt->error);
    }
    $stmt->close();

    // Create incoming record for all administrative staff
    $incoming_status = 'Submitted to Administrative Office';
    
    // Get all administrative staff users
    $admin_stmt = $conn->prepare('SELECT id FROM users WHERE role = ? ORDER BY id ASC');
    if (!$admin_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $admin_role = 'Administrative Assistant';
    $admin_stmt->bind_param('s', $admin_role);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin_users = [];
    while ($admin_row = $admin_result->fetch_assoc()) {
        $admin_users[] = $admin_row['id'];
    }
    $admin_stmt->close();
    
    if (empty($admin_users)) {
        throw new Exception('No administrative staff found');
    }
    
    // Create assignment for each administrative staff member
    $assigned_at = date('Y-m-d H:i:s');
    $routed_notes = 'Document routed from department';
    
    foreach ($admin_users as $admin_id) {
        $stmt = $conn->prepare('
            INSERT INTO document_assignments (document_id, assigned_by, assigned_to, assigned_at, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param('iiisss', $document_id, $user_id, $admin_id, $assigned_at, $incoming_status, $routed_notes);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create assignment record: ' . $stmt->error);
        }

        $assignment_id = $conn->insert_id;
        $stmt->close();

        $notification_created = createCustomNotification(
            $conn,
            $admin_id,
            $document_id,
            $assignment_id,
            $tracking_code,
            "Department staff routed document ($tracking_code) to Administrative",
            'assignment',
            null,
            'Pending'
        );

        if (!$notification_created) {
            throw new Exception('Failed to create administrative notification');
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Document routed successfully',
        'tracking_code' => $tracking_code
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

