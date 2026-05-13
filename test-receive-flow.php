<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Manila');

session_start();
$_SESSION['user_id'] = 3;  // Test department staff user ID
$_SESSION['role'] = 'Department Staff';

header('Content-Type: application/json');

require_once 'config/db_connect.php';
require_once 'config/notification_helpers.php';

echo "=== RECEIVE HANDLER TEST ===\n\n";

// Step 1: Get assignment
echo "[1] Finding assignment...\n";
$sql_check = "SELECT da.id, da.document_id, da.status, da.assigned_by,
                        da.office_department,
                        d.tracking_number
                FROM document_assignments da
                JOIN documents d ON d.id = da.document_id
                WHERE da.id = 117
                AND da.status IN ('Pending', 'Forwarded')
                LIMIT 1";

$result_check = $conn->query($sql_check);
if (!$result_check) {
    echo "ERROR: Query failed - " . $conn->error . "\n";
    $conn->close();
    exit;
}

if ($result_check->num_rows === 0) {
    echo "ERROR: No assignment found\n";
    $conn->close();
    exit;
}

$row = $result_check->fetch_assoc();
echo "✓ Found assignment 117 - Status: {$row['status']}, Document ID: {$row['document_id']}\n\n";

// Step 2: Update assignment
echo "[2] Updating assignment to Received...\n";
$conn->begin_transaction();
try {
    $sql_update = "UPDATE document_assignments
                    SET status = 'Received', received_at = NOW()
                    WHERE id = 117 AND status IN ('Pending', 'Forwarded')";
    $result = $conn->query($sql_update);
    if (!$result) {
        throw new Exception("Update failed: " . $conn->error);
    }
    echo "✓ Assignment updated, affected rows: " . $conn->affected_rows . "\n\n";

    // Step 3: Update document
    echo "[3] Updating document to Received...\n";
    $sql_doc = "UPDATE documents SET status = 'Received' WHERE id = " . $row['document_id'];
    if (!$conn->query($sql_doc)) {
        throw new Exception("Document update failed: " . $conn->error);
    }
    echo "✓ Document updated\n\n";

    // Step 4: Create notification
    echo "[4] Creating notification...\n";
    $tracking_number = trim($row['tracking_number'] ?? '');
    $result_notif = createCustomNotification(
        $conn,
        intval($row['assigned_by']),
        intval($row['document_id']),
        117,
        $tracking_number,
        "Document (Tracking: " . $tracking_number . ") has been received",
        'status_update',
        $row['status'],
        'Received'
    );
    
    if ($result_notif) {
        echo "✓ Notification created\n\n";
    } else {
        echo "⚠ Notification creation failed (non-fatal)\n\n";
    }

    $conn->commit();
    echo "✅ ALL TESTS PASSED - Transaction committed\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

$conn->close();
?>
