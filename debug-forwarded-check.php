<?php
/**
 * DEBUG: Check if Forwarded Assignment was Inserted
 */
session_start();

if (empty($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];

echo "<h1>DEBUG - Check for Forwarded Assignments</h1>";
echo "<p><strong>Your User ID:</strong> " . $user_id . "</p>";

// Check ALL document_assignments where assigned_by = staff (user sent it)
echo "<h2>ALL Assignments You Created (assigned_by = " . $user_id . ")</h2>";
$sql = "SELECT 
    da.id,
    da.document_id,
    da.assigned_by,
    da.assigned_to,
    da.status,
    da.assigned_at,
    d.title,
    d.tracking_number,
    u_recipient.first_name as recipient_name,
    u_recipient.role as recipient_role
FROM document_assignments da
LEFT JOIN documents d ON da.document_id = d.id
LEFT JOIN users u_recipient ON da.assigned_to = u_recipient.id
WHERE da.assigned_by = ?
ORDER BY da.assigned_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Assignment ID</th><th>Doc ID</th><th>Title</th><th>Tracking</th><th>Status</th><th>Assigned To (Recipient)</th><th>Recipient Role</th><th>Created At</th></tr>";

$found_forwarded = false;
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['document_id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . $row['tracking_number'] . "</td>";
    if ($row['status'] === 'Forwarded') {
        echo "<td style='background-color: lightgreen;'><strong>" . $row['status'] . "</strong></td>";
        $found_forwarded = true;
    } else {
        echo "<td>" . $row['status'] . "</td>";
    }
    echo "<td>" . $row['recipient_name'] . "</td>";
    echo "<td>" . $row['recipient_role'] . "</td>";
    echo "<td>" . substr($row['assigned_at'], 0, 16) . "</td>";
    echo "</tr>";
}
echo "</table>";

if (!$found_forwarded) {
    echo "<p style='color: red;'><strong>✗ NO 'Forwarded' assignments found!</strong></p>";
    echo "<p>This means when you tried to forward, the INSERT may have failed or not executed.</p>";
} else {
    echo "<p style='color: green;'><strong>✓ Found Forwarded assignments!</strong></p>";
}

// Now check from Admin's perspective
echo "<h2>From Admin Side: Check for Forwarded Assignments Assigned TO Admin</h2>";
echo "<p><em>Get list of admins first...</em></p>";

$sql_admins = "SELECT id, first_name, last_name, role FROM users WHERE role = 'Administrative Assistant' LIMIT 5";
$result_admins = $conn->query($sql_admins);

while ($admin = $result_admins->fetch_assoc()) {
    echo "<h3>Admin: " . $admin['first_name'] . " " . $admin['last_name'] . " (ID: " . $admin['id'] . ")</h3>";
    
    $admin_id = $admin['id'];
    $sql_admin_assignments = "SELECT 
        da.id,
        da.status,
        d.title,
        d.tracking_number,
        u_sender.first_name as sender_name,
        u_sender.role as sender_role
    FROM document_assignments da
    LEFT JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON da.assigned_by = u_sender.id
    WHERE da.assigned_to = ? AND da.status = 'Forwarded'
    ORDER BY da.assigned_at DESC";
    
    $stmt_admin = $conn->prepare($sql_admin_assignments);
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();
    
    $count = $result_admin->num_rows;
    echo "<p><strong>Forwarded assignments to this admin:</strong> " . $count . "</p>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Assignment ID</th><th>Title</th><th>Tracking</th><th>Status</th><th>From (Sender)</th><th>Sender Role</th></tr>";
        while ($row = $result_admin->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['title'] . "</td>";
            echo "<td>" . $row['tracking_number'] . "</td>";
            echo "<td style='background-color: lightgreen;'>" . $row['status'] . "</td>";
            echo "<td>" . $row['sender_name'] . "</td>";
            echo "<td>" . $row['sender_role'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();
?>
