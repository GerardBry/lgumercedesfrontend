<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Manila');

session_start();
$_SESSION['user_id'] = 3; // Test department staff user ID
$_SESSION['role'] = 'Department Staff';

header('Content-Type: application/json');

require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

// Simulate POST request for receiving document
$user_id = $_SESSION['user_id'];

// Find a valid assignment to test with
$sql_check_assignment = "SELECT da.id FROM document_assignments da 
                        WHERE da.status IN ('Pending', 'Forwarded') 
                        LIMIT 1";
$result = $conn->query($sql_check_assignment);
if ($result && $result->num_rows > 0) {
    $assignment = $result->fetch_assoc();
    $assignment_id = $assignment['id'];
    echo json_encode(['success' => true, 'message' => "Found assignment: $assignment_id"]);
} else {
    echo json_encode(['success' => false, 'message' => 'No pending/forwarded assignments found']);
}

$conn->close();
?>
