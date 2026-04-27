<?php
/**
 * DEBUG: Trace the Forward Process
 */
session_start();

if (empty($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Unknown';

echo "<h1>DEBUG - Forward Process Trace</h1>";
echo "<p><strong>Your User ID:</strong> " . $user_id . "</p>";
echo "<p><strong>Your Role:</strong> " . $role . "</p>";

// STEP 1: Check if you are a Department Staff
echo "<h2>STEP 1: Verify Your Role</h2>";
$sql_check_role = "SELECT id, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql_check_role);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
echo "<p><strong>Database Role:</strong> " . $user['role'] . "</p>";
echo "<p><strong>Is Department Staff?</strong> " . ($user['role'] === 'Department Staff' ? 'YES ✓' : 'NO ✗') . "</p>";

// STEP 2: Check documents you created that are DRAFTS (waiting to forward)
echo "<h2>STEP 2: Documents You Created (Draft Status)</h2>";
$sql_docs = "SELECT id, title, tracking_number, status, date_sent FROM documents WHERE created_by = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql_docs);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Doc ID</th><th>Title</th><th>Tracking #</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['tracking_number'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// STEP 3: For each tracking number, check if there's a source assignment
echo "<h2>STEP 3: Check Source Assignments (Documents Sent TO You)</h2>";
$sql_incoming = "SELECT 
    da.id as assignment_id,
    da.status,
    da.assigned_by,
    d.tracking_number,
    d.title,
    u_admin.first_name as admin_name,
    u_admin.role as admin_role
FROM document_assignments da
JOIN documents d ON d.id = da.document_id
JOIN users u_admin ON u_admin.id = da.assigned_by
WHERE da.assigned_to = ?
ORDER BY da.assigned_at DESC
LIMIT 10";

$stmt = $conn->prepare($sql_incoming);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Assignment ID</th><th>Doc Title</th><th>Tracking #</th><th>Status</th><th>From Admin</th><th>Admin Role</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['assignment_id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['tracking_number'] . "</td>";
    echo "<td><strong>" . $row['status'] . "</strong></td>";
    echo "<td>" . $row['admin_name'] . " (ID: " . $row['assigned_by'] . ")</td>";
    echo "<td>" . $row['admin_role'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// STEP 4: Simulate the query that forward-document-handler uses
echo "<h2>STEP 4: Simulate Forward Query (What happens when you forward?)</h2>";
echo "<p><em>This checks what admin would be found if you tried to forward with tracking code...</em></p>";

// Get a tracking number from your received documents
$sql_get_tracking = "SELECT d.tracking_number FROM documents d 
    WHERE created_by = ? AND tracking_number IS NOT NULL LIMIT 1";
$stmt = $conn->prepare($sql_get_tracking);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    $test_tracking = $doc['tracking_number'];
    echo "<p><strong>Testing with tracking code:</strong> " . $test_tracking . "</p>";
    
    // Run the EXACT query from forward-document-handler.php
    $sql_test = "SELECT
            da.id,
            da.status,
            da.assigned_by,
            da.office_department,
            admin.first_name,
            admin.last_name,
            admin.role
        FROM document_assignments da
        JOIN documents d ON d.id = da.document_id
        JOIN users admin ON admin.id = da.assigned_by
        WHERE da.assigned_to = ?
          AND d.tracking_number = ?
          AND admin.role = 'Administrative Assistant'
        ORDER BY (da.status = 'Received') DESC, da.assigned_at DESC
        LIMIT 1";
    
    $stmt_test = $conn->prepare($sql_test);
    $stmt_test->bind_param('is', $user_id, $test_tracking);
    $stmt_test->execute();
    $result_test = $stmt_test->get_result();
    
    if ($result_test->num_rows > 0) {
        $admin_found = $result_test->fetch_assoc();
        echo "<p style='color: green;'><strong>✓ FOUND Admin to forward to:</strong></p>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Assignment ID</th><th>Status</th><th>Admin ID</th><th>Admin Name</th><th>Admin Role</th></tr>";
        echo "<tr>";
        echo "<td>" . $admin_found['id'] . "</td>";
        echo "<td>" . $admin_found['status'] . "</td>";
        echo "<td>" . $admin_found['assigned_by'] . "</td>";
        echo "<td>" . $admin_found['first_name'] . " " . $admin_found['last_name'] . "</td>";
        echo "<td>" . $admin_found['role'] . "</td>";
        echo "</tr>";
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>✗ NO Admin Found!</strong></p>";
        echo "<p>This is the problem! The forward query isn't finding any admin.</p>";
        
        // Debug why
        echo "<h3>Why not found?</h3>";
        echo "<p>Checking component queries:</p>";
        
        // Check if there's ANY assignment with this tracking number assigned to you
        $sql_debug1 = "SELECT da.id, da.assigned_to, da.status, d.tracking_number FROM document_assignments da JOIN documents d ON d.id = da.document_id WHERE d.tracking_number = ? AND da.assigned_to = ?";
        $stmt_d1 = $conn->prepare($sql_debug1);
        $stmt_d1->bind_param('si', $test_tracking, $user_id);
        $stmt_d1->execute();
        $result_d1 = $stmt_d1->get_result();
        echo "<p><strong>Assignments with this tracking # assigned to you:</strong> " . $result_d1->num_rows . " rows</p>";
        
        if ($result_d1->num_rows > 0) {
            while ($row = $result_d1->fetch_assoc()) {
                echo "<p>  - Assignment #" . $row['id'] . ", Status: " . $row['status'] . ", Assigned To: " . $row['assigned_to'] . "</p>";
            }
        }
        
        // Check if admin has role 'Administrative Assistant'
        $sql_debug2 = "SELECT da.id, u.id, u.role FROM document_assignments da JOIN users u ON u.id = da.assigned_by WHERE da.assigned_to = ? LIMIT 1";
        $stmt_d2 = $conn->prepare($sql_debug2);
        $stmt_d2->bind_param('i', $user_id);
        $stmt_d2->execute();
        $result_d2 = $stmt_d2->get_result();
        echo "<p><strong>Your incoming assignments - Admin roles:</strong></p>";
        while ($row = $result_d2->fetch_assoc()) {
            echo "<p>  - Admin ID: " . $row['id'] . ", Role: " . $row['role'] . "</p>";
        }
    }
} else {
    echo "<p style='color: orange;'>⚠ You don't have any documents with tracking codes yet</p>";
}

$conn->close();
?>
