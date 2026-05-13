<?php
/**
 * Returned Document Handler - Administrative Staff
 * Fetch document details for viewing returned documents
 */
date_default_timezone_set('Asia/Manila');
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow Administrative Assistant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Only accept GET requests for viewing documents
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action']) || $_GET['action'] !== 'view') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing assignment ID']);
    exit;
}

$assignment_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once '../config/db_connect.php';

// Check if returned_at column exists
$hasReturnedAt = false;
$checkColResult = $conn->query("SHOW COLUMNS FROM document_assignments LIKE 'returned_at'");
if ($checkColResult && $checkColResult->num_rows > 0) {
    $hasReturnedAt = true;
}

// Fetch assignment details - verify user is the sender
$returnedAtSelect = $hasReturnedAt ? "IFNULL(da.returned_at, da.assigned_at) as returned_at" : "da.assigned_at as returned_at";
$sql = "SELECT 
        da.id,
        da.document_id,
        da.assigned_by,
        da.assigned_to,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        {$returnedAtSelect},
        (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned') as rejection_at,
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
        d.file_path,
        d.sender_name,
        d.notes as doc_notes,
        d.status,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        recipient.first_name as recipient_first_name,
        recipient.last_name as recipient_last_name,
        recipient.position as recipient_position,
        assigner.first_name as assigned_by_first,
        assigner.last_name as assigned_by_last,
        assigner.position as assigned_by_position
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    JOIN users recipient ON da.assigned_to = recipient.id
    WHERE da.id = ? AND da.assigned_to = ? AND da.status = 'Returned' LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ii', $assignment_id, $user_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query execution failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $assignment = $result->fetch_assoc();

    if (!empty($assignment['date_sent'])) {
        $assignment['date_sent_formatted'] = date('n/j/Y, g:i:s A', strtotime($assignment['date_sent']));
    } else {
        $assignment['date_sent_formatted'] = '-';
    }
    echo json_encode(['success' => true, 'assignment' => $assignment]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Assignment not found or access denied']);
}

$stmt->close();
$conn->close();
?>