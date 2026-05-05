<?php
/**
 * Get Document Counts API - Fetch unread/pending document counts by category
 * Returns JSON with counts for: Incoming, Outgoing, Received, Returned, Finished
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;

require_once '../config/db_connect.php';

try {
    $counts = [
        'incoming' => 0,
        'outgoing' => 0,
        'received' => 0,
        'returned' => 0,
        'finished' => 0
    ];
    
    // Determine if user is staff or administrative
    $is_administrative = ($role === 'Administrative Assistant');
    
    if ($is_administrative) {
        // ADMINISTRATIVE STAFF COUNTS
        
        // Incoming: Documents assigned by admin to themselves (to process/approve)
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_by = ? AND da.assigned_to = ? AND da.status IN ('Pending', 'Forwarded')
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['incoming'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Outgoing: Documents assigned by admin to department staff
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_by = ? AND da.assigned_to != ? AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['outgoing'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Received: Documents forwarded back to admin from department staff
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_to = ? AND da.assigned_by != ? AND da.status = 'Forwarded' AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['received'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Returned: Documents returned from department (same as received for admin)
        $counts['returned'] = $counts['received'];
        
        // Finished: Documents completed and not yet viewed by admin
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_to = ? AND da.status = 'Completed' AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['finished'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
    } else {
        // DEPARTMENT STAFF COUNTS
        
        // Incoming: Documents assigned to this staff member
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
            WHERE da.assigned_to = ? AND da.status IN ('Pending', 'Forwarded')
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['incoming'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Outgoing: Documents assigned by this staff to others
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_by = ? AND da.assigned_to != ? AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['outgoing'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Received: Documents that were forwarded back from admin
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_to = ? AND da.assigned_by != ? AND (da.status = 'Forwarded' OR da.status = 'Pending') AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['received'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Returned: Documents returned by department staff
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_to = ? AND da.status = 'Returned' AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['returned'] = $row['count'] ?? 0;
        }
        $stmt->close();
        
        // Finished: Documents completed by this staff
        $sql = "SELECT COUNT(*) as count FROM document_assignments da
                JOIN documents d ON da.document_id = d.id
                WHERE da.assigned_to = ? AND da.status = 'Completed' AND da.viewed_at IS NULL
                GROUP BY d.tracking_number HAVING COUNT(*) = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $counts['finished'] = $row['count'] ?? 0;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
