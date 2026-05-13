<?php
session_start();
$_SESSION['user_id'] = 8; // Use the user who created the documents

require_once 'config/db_connect.php';

$admin_id = $_SESSION['user_id'];

// Get admin's department
$admin_details = [];
$sql = "SELECT office_department FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_details = $result->fetch_assoc();
    }
    $stmt->close();
}

$department = $admin_details['office_department'] ?? 'Department';

echo "=== Staff Dashboard Query Test ===\n";
echo "Testing for Department: $department (User ID: $admin_id)\n\n";

// Department document statistics
$dept_doc_stats = [
    'total_documents' => 0,
    'incoming' => 0,
    'outgoing' => 0,
    'received' => 0,
    'finished' => 0,
    'archive' => 0
];

$escaped_dept = $conn->real_escape_string($department);

// Total documents
$result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE office_department = '$escaped_dept'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['total_documents'] = $row['count'];
    echo "Total Documents (dept-scoped): " . $dept_doc_stats['total_documents'] . "\n";
}

// Incoming
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status IN ('Pending','Forwarded')");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['incoming'] = $row['count'];
    echo "Incoming (dept-scoped): " . $dept_doc_stats['incoming'] . "\n";
}

// Outgoing
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments da JOIN users u ON da.assigned_by = u.id WHERE u.office_department = '$escaped_dept' AND da.status NOT IN ('Completed','Archived','Returned')");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['outgoing'] = $row['count'];
    echo "Outgoing (dept-scoped): " . $dept_doc_stats['outgoing'] . "\n";
}

// Received
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Received'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['received'] = $row['count'];
    echo "Received (dept-scoped): " . $dept_doc_stats['received'] . "\n";
}

// Finished
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Completed'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['finished'] = $row['count'];
    echo "Finished (dept-scoped): " . $dept_doc_stats['finished'] . "\n";
}

// Archive
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Archived'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['archive'] = $row['count'];
    echo "Archive (dept-scoped): " . $dept_doc_stats['archive'] . "\n";
}

echo "\n=== Final Department Stats ===\n";
print_r($dept_doc_stats);

$conn->close();
?>
