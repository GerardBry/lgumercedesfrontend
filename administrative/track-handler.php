<?php
/**
 * Track Handler - Fetch detailed audit trail for document tracking
 * Shows complete journey: who assigned, when received, all updates, until completion
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Allow any authenticated user to access the API
// Actual permission checks will be done on data level
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? null;

$tracking_code = isset($_GET['tracking_code']) ? trim($_GET['tracking_code']) : '';

if (empty($tracking_code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tracking code is required']);
    exit;
}

require_once '../config/db_connect.php';

try {
    // Get a canonical document row for this tracking code (latest activity)
    $sql_doc = "SELECT id, tracking_number, title, description, document_type, status, date_sent, created_by
                FROM documents
                WHERE tracking_number = ?
                ORDER BY date_sent DESC, id DESC
                LIMIT 1";
    
    $stmt_doc = $conn->prepare($sql_doc);
    $stmt_doc->bind_param("s", $tracking_code);
    $stmt_doc->execute();
    $result_doc = $stmt_doc->get_result();
    
    if ($result_doc->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    $document = $result_doc->fetch_assoc();
    $document_id = $document['id'];
    $stmt_doc->close();
    
    // PERMISSION CHECK: Ensure user can view this tracking code
    // Admin/Administrative Assistant can view all documents
    // Regular staff can only view documents assigned to them
    if ($user_role !== 'Administrative Assistant' && $user_role !== 'Super Admin') {
        // Check if this staff member is involved in this document
        $sql_check = "SELECT COUNT(*) as count
                 FROM document_assignments da
                 JOIN documents d ON d.id = da.document_id
                 WHERE d.tracking_number = ? AND (da.assigned_by = ? OR da.assigned_to = ?)";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("sii", $tracking_code, $user_id, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $check = $result_check->fetch_assoc();
        $stmt_check->close();
        
        if ($check['count'] == 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have access to this document']);
            exit;
        }
    }
    
    // Get creator info
    $sql_creator = "SELECT first_name, last_name, position FROM users WHERE id = ? LIMIT 1";
    $stmt_creator = $conn->prepare($sql_creator);
    $stmt_creator->bind_param("i", $document['created_by']);
    $stmt_creator->execute();
    $result_creator = $stmt_creator->get_result();
    $creator = $result_creator->fetch_assoc();
    $stmt_creator->close();
    
    // Get all assignments across all document rows sharing the same tracking code
    $sql_assignments = "SELECT 
            da.id as assignment_id,
            da.document_id,
            da.assigned_by,
            da.assigned_to,
            da.office_department,
            da.notes,
            da.status as assignment_status,
            da.assigned_at,
            da.received_at,
            da.completed_at,
            da.updated_at,
            
            -- Assigned by info
            assigner.first_name as assigner_first_name,
            assigner.last_name as assigner_last_name,
            assigner.position as assigner_position,
            
            -- Assigned to info
            recipient.first_name as recipient_first_name,
            recipient.last_name as recipient_last_name,
            recipient.position as recipient_position,
            recipient.role as recipient_role
            
        FROM document_assignments da
        JOIN documents d ON d.id = da.document_id
        LEFT JOIN users assigner ON da.assigned_by = assigner.id
        LEFT JOIN users recipient ON da.assigned_to = recipient.id
        WHERE d.tracking_number = ?
        ORDER BY da.assigned_at ASC, da.id ASC";
    
    $stmt_assignments = $conn->prepare($sql_assignments);
    $stmt_assignments->bind_param("s", $tracking_code);
    $stmt_assignments->execute();
    $result_assignments = $stmt_assignments->get_result();
    
    $assignments = [];
    while ($row = $result_assignments->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt_assignments->close();
    
    // Build audit trail timeline
    $timeline = [];
    
    // Initial creation event
    $timeline[] = [
        'event_type' => 'created',
        'title' => 'Document Created',
        'description' => 'Document was created and assigned a tracking code',
        'who' => ($creator['first_name'] ?? 'Unknown') . ' ' . ($creator['last_name'] ?? ''),
        'who_position' => $creator['position'] ?? 'System',
        'timestamp' => $document['date_sent'],
        'status' => 'Created'
    ];
    
    // Process each assignment as an event
    foreach ($assignments as $index => $assignment) {
        $assigner_name = ($assignment['assigner_first_name'] ?? 'Unknown') . ' ' . ($assignment['assigner_last_name'] ?? '');
        $recipient_name = ($assignment['recipient_first_name'] ?? 'Unknown') . ' ' . ($assignment['recipient_last_name'] ?? '');
        $assignment_status = trim((string)($assignment['assignment_status'] ?? ''));
        
        // Check if previous assignment was "Forwarded" - if so, treat this as "Received" instead of "Assigned"
        $previous_was_forwarded = ($index > 0 && trim((string)($assignments[$index - 1]['assignment_status'] ?? '')) === 'Forwarded');
        
        // Only show "Assigned" event if it's not following a "Forwarded" assignment
        if (!$previous_was_forwarded) {
            $timeline[] = [
                'event_type' => 'assigned',
                'title' => 'Document Assigned',
                'description' => "Document assigned to <strong>{$recipient_name}</strong> ({$assignment['recipient_role']}) in {$assignment['office_department']}",
                'who' => $assigner_name,
                'who_position' => $assignment['assigner_position'] ?? 'Administrative Assistant',
                'timestamp' => $assignment['assigned_at'],
                'status' => 'Pending',
                'recipient' => $recipient_name,
                'recipient_role' => $assignment['recipient_role'],
                'notes' => $assignment['notes']
            ];
        }
        
        // Received event
        if ($assignment['received_at'] || $previous_was_forwarded) {
            $received_timestamp = $assignment['received_at'] ?? $assignment['assigned_at'];
            $timeline[] = [
                'event_type' => 'received',
                'title' => 'Document Received',
                'description' => "<strong>{$recipient_name}</strong> ({$assignment['recipient_role']}) received the document",
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Staff',
                'timestamp' => $received_timestamp,
                'status' => 'Received'
            ];
        }
        
        // Workflow progress events beyond Received
        // Show all intermediate steps when document is Completed
        if ($assignment_status === 'Checking Documents' || $assignment_status === 'Waiting For Approval by Mayor' || $assignment_status === 'Completed') {
            // Add Checking Documents event
            $timeline[] = [
                'event_type' => 'checking_documents',
                'title' => 'Checking Documents',
                'description' => "<strong>{$recipient_name}</strong> is validating and checking the submitted requirements",
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Staff',
                'timestamp' => $assignment['updated_at'] ?: $assignment['received_at'] ?: $assignment['assigned_at'],
                'status' => 'Checking Documents'
            ];
        }
        
        if ($assignment_status === 'Waiting For Approval by Mayor' || $assignment_status === 'Completed') {
            // Add Waiting For Approval event (always comes after Checking Documents)
            $timeline[] = [
                'event_type' => 'waiting_approval',
                'title' => 'Waiting For Approval by Mayor',
                'description' => "Document is awaiting final mayoral approval",
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Staff',
                'timestamp' => $assignment['updated_at'] ?: $assignment['received_at'] ?: $assignment['assigned_at'],
                'status' => 'Waiting For Approval by Mayor'
            ];
        }
        
        if ($assignment_status === 'Completed') {
            // Add Completed event (final step)
            $timeline[] = [
                'event_type' => 'completed',
                'title' => 'Document Processing Completed',
                'description' => "Document processing and review completed by {$recipient_name}",
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Staff',
                'timestamp' => $assignment['completed_at'] ?: $assignment['updated_at'] ?: $assignment['assigned_at'],
                'status' => 'Completed'
            ];
        }
        
        if ($assignment_status === 'Forwarded') {
            $timeline[] = [
                'event_type' => 'forwarded',
                'title' => 'Document Forwarded',
                'description' => "{$recipient_name} forwarded the document back to administration",
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Staff',
                'timestamp' => $assignment['completed_at'] ?: $assignment['updated_at'] ?: $assignment['assigned_at'],
                'status' => 'Forwarded'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'document' => [
            'tracking_number' => $document['tracking_number'],
            'title' => $document['title'],
            'description' => $document['description'],
            'document_type' => $document['document_type'],
            'status' => $document['status'],
            'created_by' => ($creator['first_name'] ?? '') . ' ' . ($creator['last_name'] ?? ''),
            'created_at' => $document['date_sent']
        ],
        'assignments' => $assignments,
        'timeline' => $timeline
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
