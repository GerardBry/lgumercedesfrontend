<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['user_id'] = 3;
$_SESSION['role'] = 'Department Staff';

// Simulate the incoming-handler POST request
header('Content-Type: application/json');

require_once 'config/db_connect.php';

// Get an assignment to test with
$sql = "SELECT da.id, da.document_id, da.status, da.assigned_by 
        FROM document_assignments da 
        WHERE da.status IN ('Pending', 'Forwarded') 
        LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Found assignment ID: " . $row['id'] . "\n";
    echo "Status: " . $row['status'] . "\n";
    echo "Document ID: " . $row['document_id'] . "\n";
    echo "Assigned by: " . $row['assigned_by'] . "\n";
} else {
    echo "No pending/forwarded assignments found\n";
}

$conn->close();
?>
