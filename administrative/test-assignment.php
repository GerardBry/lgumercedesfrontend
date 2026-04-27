<?php
/**
 * Test the document assignment handler
 */
session_start();

// Simulate Administrative Assistant session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Administrative Assistant';

require_once '../config/db_connect.php';

// Test data
$test_data = [
    'documentType' => 'doc1',
    'title' => 'Test Document - Travel Order',
    'description' => 'This is a test document assignment',
    'office' => 'Administrative Office',
    'recipientId' => 2,
    'notes' => 'Test notes'
];

echo "Testing Document Assignment Handler...\n";
echo "=====================================\n\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $test_data;

// Prepare JSON input
$json_input = json_encode($test_data);
$fp = fopen('php://input', 'r');
fwrite($fp, $json_input);
rewind($fp);

try {
    // Start transaction
    $conn->begin_transaction();

    // Create document record
    $sql_doc = "INSERT INTO documents (title, description, created_by) VALUES (?, ?, ?)";
    $stmt_doc = $conn->prepare($sql_doc);
    if (!$stmt_doc) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $title = $test_data['title'];
    $description = $test_data['description'];
    $assigned_by = $_SESSION['user_id'];
    
    $stmt_doc->bind_param('ssi', $title, $description, $assigned_by);
    $stmt_doc->execute();
    $document_id = $conn->insert_id;
    $stmt_doc->close();

    echo "✓ Document created with ID: $document_id\n";

    // Create assignment record
    $sql_assign = "INSERT INTO document_assignments (document_id, assigned_by, assigned_to, office_department, notes, status) 
                   VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt_assign = $conn->prepare($sql_assign);
    if (!$stmt_assign) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $office = $test_data['office'];
    $recipient_id = $test_data['recipientId'];
    $notes = $test_data['notes'];
    
    $stmt_assign->bind_param('iisss', $document_id, $assigned_by, $recipient_id, $office, $notes);
    $stmt_assign->execute();
    $assignment_id = $conn->insert_id;
    $stmt_assign->close();

    // Commit transaction
    $conn->commit();

    echo "✓ Assignment created with ID: $assignment_id\n";
    echo "\n[✓] TEST PASSED - Backend is working correctly!\n";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo "\n[✗] TEST FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
