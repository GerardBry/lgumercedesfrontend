<?php
/**
 * Mark Documents as Viewed API - Mark documents as viewed when user visits a page
 * POST request with category (incoming, outgoing, received, returned, finished)
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;
$category = isset($_POST['category']) ? trim($_POST['category']) : '';

if (empty($category)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category is required']);
    exit;
}

require_once '../config/db_connect.php';

try {
    $is_administrative = ($role === 'Administrative Assistant');
    $now = date('Y-m-d H:i:s');
    
    if ($is_administrative) {
        // ADMINISTRATIVE STAFF - Mark specific documents as viewed
        switch ($category) {
            case 'incoming':
                // Documents assigned by admin to themselves
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_by = ? AND assigned_to = ? AND viewed_at IS NULL";
                break;
            case 'outgoing':
                // Documents assigned by admin to department staff
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_by = ? AND assigned_to != ? AND viewed_at IS NULL";
                break;
            case 'received':
            case 'returned':
                // Documents forwarded back to admin
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND assigned_by != ? AND status = 'Forwarded' AND viewed_at IS NULL";
                break;
            case 'finished':
                // Completed documents
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND status = 'Completed' AND viewed_at IS NULL";
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid category']);
                exit;
        }
    } else {
        // DEPARTMENT STAFF - Mark specific documents as viewed
        switch ($category) {
            case 'incoming':
                // Documents assigned to this staff
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND viewed_at IS NULL";
                break;
            case 'outgoing':
                // Documents assigned by this staff
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_by = ? AND assigned_to != ? AND viewed_at IS NULL";
                break;
            case 'received':
                // Documents forwarded/returned to this staff
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND assigned_by != ? AND (status = 'Forwarded' OR status = 'Pending') AND viewed_at IS NULL";
                break;
            case 'returned':
                // Documents returned by this staff
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND status = 'Returned' AND viewed_at IS NULL";
                break;
            case 'finished':
                // Completed documents
                $sql = "UPDATE document_assignments 
                        SET viewed_at = ? 
                        WHERE assigned_to = ? AND status = 'Completed' AND viewed_at IS NULL";
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid category']);
                exit;
        }
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($category === 'outgoing' && $is_administrative) {
        $stmt->bind_param("sii", $now, $user_id, $user_id);
    } elseif ($category === 'outgoing' && !$is_administrative) {
        $stmt->bind_param("sii", $now, $user_id, $user_id);
    } elseif ($category === 'received' && $is_administrative) {
        $stmt->bind_param("sii", $now, $user_id, $user_id);
    } elseif ($category === 'received' && !$is_administrative) {
        $stmt->bind_param("sii", $now, $user_id, $user_id);
    } elseif ($category === 'incoming' && $is_administrative) {
        $stmt->bind_param("sii", $now, $user_id, $user_id);
    } else {
        $stmt->bind_param("si", $now, $user_id);
    }
    
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Documents marked as viewed',
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
