<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Manila');

session_start();
$_SESSION['user_id'] = 3;  // Test user
$_SESSION['role'] = 'Department Staff';

require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

echo "=== RECEIVE DOCUMENT TEST ===\n\n";

// Step 1: Find a valid assignment
echo "Step 1: Finding valid assignment...\n";
$sql = "SELECT da.id, da.document_id, da.status, da.assigned_by 
        FROM document_assignments da 
        WHERE da.status IN ('Pending', 'Forwarded') 
        AND da.assigned_to IN (SELECT id FROM users WHERE office_department = 'Mayor Office')
        LIMIT 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "ERROR: No valid assignments found\n";
    $conn->close();
    exit;
}

$assignment = $result->fetch_assoc();
$assignment_id = $assignment['id'];
echo "Found Assignment ID: $assignment_id (Status: {$assignment['status']})\n\n";

// Step 2: Simulate the update
echo "Step 2: Attempting UPDATE...\n";
$sql_update = "UPDATE document_assignments 
               SET status = 'Received', received_at = NOW() 
               WHERE id = ? AND status IN ('Pending', 'Forwarded')";
$stmt = $conn->prepare($sql_update);
if (!$stmt) {
    echo "Prepare ERROR: " . $conn->error . "\n";
    $conn->close();
    exit;
}

$stmt->bind_param('i', $assignment_id);
if (!$stmt->execute()) {
    echo "Execute ERROR: " . $stmt->error . "\n";
    $conn->close();
    exit;
}

echo "UPDATE succeeded. Affected rows: " . $stmt->affected_rows . "\n";
$stmt->close();

// Step 3: Test notification creation
echo "\nStep 3: Testing notification creation...\n";
$result = createCustomNotification(
    $conn,
    2, // admin user
    $assignment['document_id'],
    $assignment_id,
    "DOC-TEST",
    "Test notification",
    'status_update',
    $assignment['status'],
    'Received'
);

echo "Notification result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

echo "\nTest completed.\n";
$conn->close();
?>
