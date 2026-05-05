<?php
/**
 * Assign Document Handler - Process document assignments with tracking
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

// Handle GET requests (view assignment details)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'view') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
        exit;
    }

    $assignment_id = intval($_GET['id']);
    $assigned_by = intval($_SESSION['user_id']);

    $sql = "SELECT 
            da.id,
            da.document_id,
            da.assigned_by,
            da.assigned_to,
            da.office_department,
            da.notes,
            da.status as assignment_status,
            da.assigned_at,
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
            recipient.first_name,
            recipient.last_name,
            recipient.position
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        JOIN users recipient ON da.assigned_to = recipient.id
        LEFT JOIN users sender ON d.sender_id = sender.id
        WHERE da.id = ? AND da.assigned_by = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $stmt->bind_param('ii', $assignment_id, $assigned_by);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $assignment = $result->fetch_assoc();
        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Only accept POST requests for creating assignments
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['trackingCode', 'documentType', 'title', 'recipientId'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize and validate inputs
$tracking_code = trim($data['trackingCode']);
$document_type = trim($data['documentType']);
$title = trim($data['title']);
$description = isset($data['description']) ? trim($data['description']) : '';
$recipient_id = intval($data['recipientId']);
$office = isset($data['office']) ? trim($data['office']) : '';
$notes = isset($data['notes']) ? trim($data['notes']) : '';

$assigned_by = $_SESSION['user_id'];
$sender_id = $assigned_by;
$created_by = $assigned_by;

try {
    // Start transaction
    $conn->begin_transaction();

    // Create document record with tracking information and current timestamp
    $date_sent = date('Y-m-d H:i:s');
    $sql_doc = "INSERT INTO documents (title, description, tracking_number, document_type, sender_id, notes, created_by, date_sent, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt_doc = $conn->prepare($sql_doc);
    if (!$stmt_doc) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_doc->bind_param('ssssisss', $title, $description, $tracking_code, $document_type, $sender_id, $notes, $created_by, $date_sent);
    $stmt_doc->execute();
    $document_id = $conn->insert_id;
    $stmt_doc->close();

    // Create assignment record
    $sql_assign = "INSERT INTO document_assignments (document_id, assigned_by, assigned_to, office_department, notes, status) 
                   VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt_assign = $conn->prepare($sql_assign);
    if (!$stmt_assign) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt_assign->bind_param('iisss', $document_id, $assigned_by, $recipient_id, $office, $notes);
    $stmt_assign->execute();
    $assignment_id = $conn->insert_id;
    $stmt_assign->close();

    // Create notification for the assigned staff member
    createAssignmentNotification($conn, $recipient_id, $document_id, $assignment_id, $tracking_code, $title);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document assigned successfully',
        'tracking_code' => $tracking_code,
        'document_id' => $document_id,
        'assignment_id' => $assignment_id
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
