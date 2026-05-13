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
    // Some deployments may not have returned_at yet.
    $hasReturnedAt = false;
    $checkReturnedAtSql = "SHOW COLUMNS FROM document_assignments LIKE 'returned_at'";
    $checkReturnedAtStmt = $conn->prepare($checkReturnedAtSql);
    if ($checkReturnedAtStmt) {
        if ($checkReturnedAtStmt->execute()) {
            $checkReturnedAtResult = $checkReturnedAtStmt->get_result();
            $hasReturnedAt = ($checkReturnedAtResult && $checkReturnedAtResult->num_rows > 0);
        }
        $checkReturnedAtStmt->close();
    }

    // Get a canonical document row for this tracking code (latest activity)
    $sql_doc = "SELECT d.id, d.tracking_number, d.title, d.description, d.sender_name, d.date_received, d.classification, d.sub_classification, d.priority, d.document_type, d.status, d.date_sent, d.created_by,
                        sender.first_name as sender_first_name,
                        sender.last_name as sender_last_name,
                        sender.position as sender_position
                FROM documents d
                LEFT JOIN users sender ON d.sender_id = sender.id
                WHERE d.tracking_number = ?
                ORDER BY d.date_sent DESC, d.id DESC
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
    $sql_creator = "SELECT first_name, last_name, position, role FROM users WHERE id = ? LIMIT 1";
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
            (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned') as rejection_at,
            " . ($hasReturnedAt ? "da.returned_at," : "NULL as returned_at,") . "
            
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

    $travel_requests = [];
    // Query travel requests linked via parent_document_id or parent_tracking_code
    $document_id_text = (string)$document_id;
    $sql_travel_requests = "SELECT
            d.id,
            d.title,
            d.description,
            d.tracking_number,
            d.created_at,
            d.date_sent,
            d.created_by,
            d.notes,
            d.sender_name,
            u.first_name,
            u.last_name,
            u.position
        FROM documents d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.document_type = 'Travel Request'
          AND EXISTS (
            SELECT 1
            FROM documents parent
            WHERE parent.id = ?
              AND (
                JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_document_id')) = CAST(parent.id AS CHAR)
                OR JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_document_id')) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_tracking_code')) = parent.tracking_number
              )
          )
        ORDER BY d.date_sent ASC, d.id ASC";

    $stmt_travel_requests = $conn->prepare($sql_travel_requests);
    if ($stmt_travel_requests) {
        $stmt_travel_requests->bind_param('is', $document_id, $document_id_text);
        $stmt_travel_requests->execute();
        $result_travel_requests = $stmt_travel_requests->get_result();
        while ($row = $result_travel_requests->fetch_assoc()) {
            $travel_requests[] = $row;
        }
        $stmt_travel_requests->close();
    }

    $uploads = [];
    $check_uploads_table = $conn->query("SHOW TABLES LIKE 'document_uploads'");
    if ($check_uploads_table && $check_uploads_table->num_rows > 0) {
        $sql_uploads = "SELECT
                du.id,
                du.assignment_id,
                du.file_path,
                du.notes,
                du.uploaded_by,
                du.uploaded_at,
                da.assigned_by,
                da.assigned_to,
                da.status as assignment_status
            FROM document_uploads du
            JOIN document_assignments da ON du.assignment_id = da.id
            JOIN documents d ON da.document_id = d.id
            WHERE d.tracking_number = ?
            ORDER BY du.uploaded_at ASC, du.id ASC";

        $stmt_uploads = $conn->prepare($sql_uploads);
        if ($stmt_uploads) {
            $stmt_uploads->bind_param('s', $tracking_code);
            $stmt_uploads->execute();
            $result_uploads = $stmt_uploads->get_result();
            while ($row = $result_uploads->fetch_assoc()) {
                $uploads[] = $row;
            }
            $stmt_uploads->close();
        }
    }
    
    // Build audit trail timeline
    $timeline = [];

    $document_creator = trim(($creator['first_name'] ?? '') . ' ' . ($creator['last_name'] ?? ''));
    if ($document_creator === '') {
        $document_creator = trim((string)($document['sender_name'] ?? ''));
    }
    if ($document_creator === '') {
        $document_creator = 'Unknown';
    }

    $document_status = trim((string)($document['status'] ?? ''));
    $document_status_lower = strtolower($document_status);
    
    // Initial creation event
    $timeline[] = [
        'event_type' => 'created',
        'sequence' => 2,
        'title' => 'Document Submitted',
        'description' => 'Document was submitted and registered in the tracking system',
        'who' => $document_creator,
        'who_position' => $creator['position'] ?? 'Sender',
        'timestamp' => $document['date_sent'],
        'status' => 'Submitted'
    ];
    
    // Process each assignment as an event
    foreach ($assignments as $index => $assignment) {
        $assigner_name = ($assignment['assigner_first_name'] ?? 'Unknown') . ' ' . ($assignment['assigner_last_name'] ?? '');
        $recipient_name = ($assignment['recipient_first_name'] ?? 'Unknown') . ' ' . ($assignment['recipient_last_name'] ?? '');
        $assignment_status = trim((string)($assignment['assignment_status'] ?? ''));

        if (!empty($assignment['assigned_at'])) {
            $timeline[] = [
                'event_type' => 'routed',
                'sequence' => 1,
                'title' => 'Routed to Department Staff',
                'description' => "Document routed to <strong>{$recipient_name}</strong>" . (!empty($assignment['office_department']) ? " in <strong>{$assignment['office_department']}</strong>" : ''),
                'who' => $assigner_name,
                'who_position' => $assignment['assigner_position'] ?? 'Administrative Assistant',
                'timestamp' => $assignment['assigned_at'],
                'status' => 'Routed',
                'recipient' => $recipient_name,
                'recipient_role' => $assignment['recipient_role'],
                'notes' => $assignment['notes']
            ];
        }

        if (!empty($assignment['received_at'])) {
            $timeline[] = [
                'event_type' => 'received',
                'sequence' => 3,
                'title' => 'Administrative Received',
                'description' => 'Administrative Head acknowledged receipt and started processing the document',
                'who' => $recipient_name,
                'who_position' => $assignment['recipient_position'] ?? 'Administrative Assistant',
                'timestamp' => $assignment['received_at'],
                'status' => 'Received'
            ];
        }

        if ($assignment_status === 'Returned' && (!empty($assignment['rejection_at']) || !empty($assignment['returned_at']) || !empty($assignment['updated_at']))) {
            $returned_timestamp = $assignment['rejection_at'] ?: $assignment['returned_at'] ?: $assignment['updated_at'];
            $returned_reason = trim((string)($assignment['notes'] ?? ''));

            $timeline[] = [
                'event_type' => 'returned',
                'sequence' => 3.5,
                'title' => 'Returned to Department Staff',
                'description' => !empty($assignment['office_department'])
                    ? "Document returned to <strong>{$recipient_name}</strong> in <strong>{$assignment['office_department']}</strong>"
                    : "Document returned to <strong>{$recipient_name}</strong>",
                'who' => $assigner_name,
                'who_position' => $assignment['assigner_position'] ?? 'Administrative Assistant',
                'timestamp' => $returned_timestamp,
                'status' => 'Returned',
                'recipient' => $recipient_name,
                'recipient_role' => $assignment['recipient_role'],
                'notes' => $returned_reason
            ];
        }
    }

    if ($document_status_lower === 'approved' || $document_status_lower === 'completed') {
        $approval_timestamp = $document['date_received'] ?: $document['date_sent'];
        if (!empty($assignments)) {
            $latest_assignment = $assignments[count($assignments) - 1];
            $approval_timestamp = $latest_assignment['updated_at'] ?: $latest_assignment['received_at'] ?: $latest_assignment['assigned_at'] ?: $approval_timestamp;
        }

        $timeline[] = [
            'event_type' => 'approved',
            'sequence' => 4,
            'title' => 'Approved by Administrative',
            'description' => 'Administrative staff reviewed and approved the routed document',
            'who' => 'Administrative',
            'who_position' => 'Administrative Assistant',
            'timestamp' => $approval_timestamp,
            'status' => 'Approved'
        ];
    }

    foreach ($travel_requests as $travel_request) {
        $travel_request_creator = trim(($travel_request['first_name'] ?? '') . ' ' . ($travel_request['last_name'] ?? ''));
        if ($travel_request_creator === '') {
            $travel_request_creator = $travel_request['sender_name'] ?? 'Department Staff';
        }

        $timeline[] = [
            'event_type' => 'travel_request',
            'sequence' => 5,
            'title' => 'Travel Request Submitted',
            'description' => 'Department staff submitted a travel request linked to this document',
            'who' => $travel_request_creator,
            'who_position' => $travel_request['position'] ?? 'Department Staff',
            'timestamp' => $travel_request['created_at'] ?: $travel_request['date_sent'],
            'status' => 'Travel Request',
            'notes' => $travel_request['description'] ?: ($travel_request['notes'] ?? '')
        ];
    }

    foreach ($uploads as $upload) {
        $uploaded_by = trim((string)($upload['uploaded_by'] ?? ''));
        if ($uploaded_by === '') {
            $uploaded_by = 'Administrative';
        }

        $timeline[] = [
            'event_type' => 'uploaded',
            'sequence' => 6,
            'title' => 'Administrative Uploads',
            'description' => 'Supporting files were uploaded to the document record',
            'who' => $uploaded_by,
            'who_position' => 'Administrative Staff',
            'timestamp' => $upload['uploaded_at'],
            'status' => 'Uploaded',
            'notes' => $upload['notes'] ?? ''
        ];
    }

    if ($document_status_lower === 'completed' || !empty(array_filter($assignments, function ($assignment) {
        return trim((string)($assignment['assignment_status'] ?? '')) === 'Completed';
    }))) {
        $completed_timestamp = $document['date_received'] ?: $document['date_sent'];
        foreach (array_reverse($assignments) as $assignment) {
            if (!empty($assignment['completed_at'])) {
                $completed_timestamp = $assignment['completed_at'];
                break;
            }
        }

        $timeline[] = [
            'event_type' => 'completed',
            'sequence' => 7,
            'title' => 'Completed',
            'description' => 'Document workflow was marked complete after upload and review',
            'who' => 'Administrative',
            'who_position' => 'Administrative Assistant',
            'timestamp' => $completed_timestamp,
            'status' => 'Completed'
        ];
    }

    usort($timeline, function ($left, $right) {
        $leftSequence = $left['sequence'] ?? 999;
        $rightSequence = $right['sequence'] ?? 999;

        if ($leftSequence !== $rightSequence) {
            return $leftSequence <=> $rightSequence;
        }

        $leftTime = !empty($left['timestamp']) ? strtotime($left['timestamp']) : 0;
        $rightTime = !empty($right['timestamp']) ? strtotime($right['timestamp']) : 0;
        return $leftTime <=> $rightTime;
    });

    // Normalize document status based on creator role
    $creator_role = trim((string)($creator['role'] ?? ''));
    $is_admin_origin = $creator_role === 'Administrative Assistant';
    
    // If status is empty, derive from timeline
    if (empty($document_status) || trim($document_status) === '') {
        // Find the latest meaningful status from timeline
        $timelineReverse = array_reverse($timeline);
        foreach ($timelineReverse as $event) {
            if (!empty($event['status']) && $event['status'] !== 'Submitted') {
                $document_status = $event['status'];
                break;
            }
        }
    }
    
    // Normalize status based on document origin
    // Administrative staff documents should show "Approved"
    // Department staff documents should show "Completed"
    if ($is_admin_origin) {
        // Administrative origin: show Approved (unless specifically Returned)
        if ($document_status !== 'Returned' && !empty($document_status)) {
            $document_status = 'Approved';
        } elseif (empty($document_status)) {
            $document_status = 'Approved';
        }
    } else {
        // Department staff origin: show Completed (unless specifically Returned)
        if ($document_status !== 'Returned' && !empty($document_status)) {
            $document_status = 'Completed';
        } elseif (empty($document_status)) {
            $document_status = 'Completed';
        }
    }

    echo json_encode([
        'success' => true,
        'document' => [
            'tracking_number' => $document['tracking_number'],
            'title' => $document['title'],
            'description' => $document['description'],
            'sender_name' => $document_creator,
            'date_sent' => $document['date_sent'],
            'date_received' => $document['date_received'],
            'classification' => $document['classification'],
            'sub_classification' => $document['sub_classification'],
            'priority' => $document['priority'],
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
