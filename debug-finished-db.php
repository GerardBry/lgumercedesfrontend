<?php
/**
 * Debug page to diagnose why finished.php shows no rows
 * Open in browser: http://localhost/<your-path>/debug-finished-db.php
 */

require_once 'config/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

if (!$conn) {
    echo "DB connection failed\n";
    exit;
}

function q1($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return ['error' => $conn->error];
    return $r->fetch_all(MYSQLI_ASSOC);
}

echo "Debug: Database checks for finished documents\n";
echo "========================================\n\n";

// 1) Count assignments with status = 'Completed'
$sql1 = "SELECT COUNT(*) AS c FROM document_assignments WHERE status = 'Completed'";
$r1 = q1($conn, $sql1);
if (isset($r1['error'])) {
    echo "Query1 error: " . $r1['error'] . "\n";
} else {
    echo "Assignments with status='Completed': " . ($r1[0]['c'] ?? 0) . "\n";
}

// 2) Count documents whose final_status per reports.php is 'Completed'
$latest_documents_sql = "SELECT tracking_number, MAX(id) AS latest_id FROM documents WHERE document_type <> 'Travel Request' GROUP BY tracking_number";
$final_status_expression = "CASE
    WHEN EXISTS (SELECT 1 FROM document_assignments da_returned WHERE da_returned.document_id = d_inner.id AND da_returned.status = 'Returned') THEN 'Returned'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_completed WHERE da_completed.document_id = d_inner.id AND da_completed.status = 'Completed') THEN 'Completed'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_approved WHERE da_approved.document_id = d_inner.id AND da_approved.status = 'Approved') THEN 'Approved'
    ELSE d_inner.status
END";

$final_sub_sql = "SELECT d_inner.id, d_inner.tracking_number, $final_status_expression AS final_status FROM documents d_inner INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d_inner.id";
$sql2 = "SELECT COUNT(*) AS c FROM ($final_sub_sql) t WHERE final_status = 'Completed'";
$r2 = q1($conn, $sql2);
if (isset($r2['error'])) {
    echo "Query2 error: " . $r2['error'] . "\n";
} else {
    echo "Documents with final_status='Completed': " . ($r2[0]['c'] ?? 0) . "\n";
}

// 3) Sample assignments marked Completed
$sql3 = "SELECT da.id, da.document_id, da.assigned_by, da.assigned_to, da.office_department, da.assigned_at, da.completed_at, da.status FROM document_assignments da WHERE da.status = 'Completed' ORDER BY da.completed_at DESC LIMIT 10";
$r3 = q1($conn, $sql3);
if (isset($r3['error'])) {
    echo "Query3 error: " . $r3['error'] . "\n";
} else {
    echo "\nSample document_assignments with status='Completed' (up to 10):\n";
    if (count($r3) === 0) {
        echo "  (none)\n";
    } else {
        foreach ($r3 as $row) {
            echo "  id={$row['id']} doc_id={$row['document_id']} assigned_by={$row['assigned_by']} assigned_to={$row['assigned_to']} office={$row['office_department']} assigned_at={$row['assigned_at']} completed_at={$row['completed_at']} status={$row['status']}\n";
        }
    }
}

// 4) Sample documents whose final_status would be 'Completed' per reports logic (join via completed assignments)
$sql4 = "SELECT DISTINCT d.id, d.tracking_number, d.title, d.office_department FROM documents d JOIN document_assignments da ON da.document_id = d.id WHERE da.status = 'Completed' ORDER BY da.completed_at DESC LIMIT 10";
$r4 = q1($conn, $sql4);
if (isset($r4['error'])) {
    echo "Query4 error: " . $r4['error'] . "\n";
} else {
    echo "\nSample documents linked to Completed assignments (up to 10):\n";
    if (count($r4) === 0) {
        echo "  (none)\n";
    } else {
        foreach ($r4 as $row) {
            echo "  doc_id={$row['id']} tracking={$row['tracking_number']} title=" . ($row['title'] ?? '') . " office={$row['office_department']}\n";
        }
    }
}

// 5) Show counts for current user (if session present)
session_start();
$uid = $_SESSION['user_id'] ?? null;
if ($uid) {
    echo "\nCurrent session user_id={$uid}\n";
    $sql5 = "SELECT COUNT(*) AS c FROM document_assignments WHERE status='Completed' AND (assigned_to = " . intval($uid) . " OR assigned_by = " . intval($uid) . ")";
    $r5 = q1($conn, $sql5);
    if (isset($r5['error'])) {
        echo "Query5 error: " . $r5['error'] . "\n";
    } else {
        echo "Assignments Completed for this user (assigned_to or assigned_by): " . ($r5[0]['c'] ?? 0) . "\n";
    }
    // Fetch user details (office, role)
    $sql_user = "SELECT id, first_name, last_name, role, office_department FROM users WHERE id = " . intval($uid) . " LIMIT 1";
    $uinfo = q1($conn, $sql_user);
    if (isset($uinfo['error'])) {
        echo "User query error: " . $uinfo['error'] . "\n";
    } elseif (count($uinfo) === 0) {
        echo "User not found in DB\n";
    } else {
        $u = $uinfo[0];
        $user_office = $u['office_department'] ?? '';
        echo "User: {$u['first_name']} {$u['last_name']} (role={$u['role']}) office='" . ($user_office ?: 'N/A') . "'\n";

        // Show completed assignments that would be visible by office or assignment
        $esc_office = $conn->real_escape_string($user_office);
        $sql6 = "SELECT da.id, da.document_id, da.assigned_by, da.assigned_to, da.office_department, d.office_department AS doc_office, da.assigned_at, da.completed_at FROM document_assignments da JOIN documents d ON da.document_id = d.id WHERE da.status = 'Completed' AND (da.assigned_to = " . intval($uid) . " OR da.assigned_by = " . intval($uid) . " OR da.office_department = '" . $esc_office . "' OR d.office_department = '" . $esc_office . "') ORDER BY da.completed_at DESC LIMIT 20";
        $r6 = q1($conn, $sql6);
        if (isset($r6['error'])) {
            echo "Query6 error: " . $r6['error'] . "\n";
        } else {
            echo "\nCompleted assignments visible to this user by office/assignment (up to 20):\n";
            if (count($r6) === 0) {
                echo "  (none)\n";
            } else {
                foreach ($r6 as $row) {
                    echo "  id={$row['id']} doc_id={$row['document_id']} assigned_by={$row['assigned_by']} assigned_to={$row['assigned_to']} assignment_office={$row['office_department']} doc_office={$row['doc_office']} assigned_at={$row['assigned_at']} completed_at={$row['completed_at']}\n";
                }
            }
        }
    }
} else {
    echo "\nNo active session user_id detected (visit while logged in to see per-user counts).\n";
}

$conn->close();

echo "\nFinished debug checks.\n";
