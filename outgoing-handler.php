<?php
/**
 * Outgoing Document Handler - Department Staff
 * Fetch document details for viewing outgoing documents
 */
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Block Super Admin and Administrative Assistant
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Super Admin' || $_SESSION['role'] === 'Administrative Assistant') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

// Only accept GET requests for viewing documents
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['action']) || $_GET['action'] !== 'view') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing document ID']);
    exit;
}

$document_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

require_once 'config/db_connect.php';

// Fetch document details - verify user is the creator/sender
$sql = "SELECT 
        d.id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes,
        d.status,
        d.created_by,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name
    FROM documents d
    LEFT JOIN users sender ON d.sender_id = sender.id
    WHERE d.id = ? AND (d.created_by = ? OR d.sender_id = ?) LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('iii', $document_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $document = $result->fetch_assoc();
    echo json_encode(['success' => true, 'document' => $document]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
}

$stmt->close();
$conn->close();
?>