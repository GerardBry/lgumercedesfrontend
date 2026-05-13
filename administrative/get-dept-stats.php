<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';

$admin_id = $_SESSION['user_id'];

// Get admin's department/office
$admin_details = [];
$sql = "SELECT office_department FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $admin_details = $result->fetch_assoc();
    }
    $stmt->close();
}

$department = $admin_details['office_department'] ?? '';
if ($department === '') {
    echo json_encode(['error' => 'no_department']);
    $conn->close();
    exit;
}

$escaped_dept = $conn->real_escape_string($department);

$stats = [
    'total_documents' => 0,
    'incoming' => 0,
    'outgoing' => 0,
    'received' => 0,
    'finished' => 0
];

$result = $conn->query("SELECT COUNT(DISTINCT d.id) as count FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.office_department = '$escaped_dept' OR u.office_department = '$escaped_dept'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_documents'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status IN ('Pending','Forwarded')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['incoming'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments da JOIN users u ON da.assigned_by = u.id WHERE u.office_department = '$escaped_dept' AND da.status NOT IN ('Completed','Archived','Returned')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['outgoing'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Approved'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['received'] = (int)$row['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Completed'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['finished'] = (int)$row['count'];
}


$response = [
    'department' => $department,
    'stats' => $stats
];

// Additional diagnostics for debugging
$diag = [];
$diag['escaped_dept'] = $escaped_dept;
$diag['dept_length'] = mb_strlen($department);

// Global counts (no department filter) to check DB content
$result = $conn->query("SELECT COUNT(*) as count FROM documents");
if ($result) {
    $row = $result->fetch_assoc();
    $diag['global_documents'] = (int)$row['count'];
} else {
    $diag['global_documents_error'] = $conn->error;
}

$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments");
if ($result) {
    $row = $result->fetch_assoc();
    $diag['global_assignments'] = (int)$row['count'];
} else {
    $diag['global_assignments_error'] = $conn->error;
}

// Global assignments breakdown by status
$diag['global_assignments_by_status'] = [];
$res_status = $conn->query("SELECT status, COUNT(*) as count FROM document_assignments GROUP BY status");
if ($res_status) {
    while ($r = $res_status->fetch_assoc()) {
        $diag['global_assignments_by_status'][$r['status']] = (int)$r['count'];
    }
}

// Sample rows matching department (limit 5)
$diag['sample_documents_for_dept'] = [];
$q = "SELECT id, title, office_department FROM documents WHERE office_department = '" . $escaped_dept . "' LIMIT 5";
$r = $conn->query($q);
if ($r) {
    while ($rr = $r->fetch_assoc()) {
        $diag['sample_documents_for_dept'][] = $rr;
    }
} else {
    $diag['sample_documents_error'] = $conn->error;
}

$diag['sample_assignments_for_dept'] = [];
$q2 = "SELECT id, title, office_department, status FROM document_assignments WHERE office_department = '" . $escaped_dept . "' LIMIT 5";
$r2 = $conn->query($q2);
if ($r2) {
    while ($rr2 = $r2->fetch_assoc()) {
        $diag['sample_assignments_for_dept'][] = $rr2;
    }
} else {
    $diag['sample_assignments_error'] = $conn->error;
}

$response['diag'] = $diag;

// Distinct department values for further debugging
$diag2 = [];
$diag2['distinct_doc_departments'] = [];
$res = $conn->query("SELECT DISTINCT office_department FROM documents WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $diag2['distinct_doc_departments'][] = $r['office_department'];
    }
}

$diag2['distinct_user_departments'] = [];
$res2 = $conn->query("SELECT DISTINCT office_department FROM users WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
if ($res2) {
    while ($r = $res2->fetch_assoc()) {
        $diag2['distinct_user_departments'][] = $r['office_department'];
    }
}

// Sample documents joined with creator's department
$diag2['sample_docs_with_creator'] = [];
$q3 = "SELECT d.id, d.title, d.office_department as doc_dept, d.created_by, u.office_department as creator_dept FROM documents d LEFT JOIN users u ON d.created_by = u.id ORDER BY d.id DESC LIMIT 5";
$r3 = $conn->query($q3);
if ($r3) {
    while ($row = $r3->fetch_assoc()) {
        $diag2['sample_docs_with_creator'][] = $row;
    }
}

$response['diag2'] = $diag2;

$conn->close();

echo json_encode($response);
exit;
?>
