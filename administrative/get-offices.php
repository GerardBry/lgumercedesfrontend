<?php
/**
 * Get Offices - Fetch unique office/department names from database
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is Administrative Assistant
if ($_SESSION['role'] !== 'Administrative Assistant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once '../config/db_connect.php';

try {
    // Get unique office_department values from users table
    // where status is 'Active' and role is 'Department Staff'
    $sql = "SELECT DISTINCT office_department FROM users 
            WHERE status = 'Active' 
            AND role = 'Department Staff'
            AND office_department IS NOT NULL
            AND office_department != ''
            ORDER BY office_department ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $offices = [];
        while ($row = $result->fetch_assoc()) {
            $offices[] = $row['office_department'];
        }
        
        echo json_encode([
            'success' => true,
            'offices' => $offices
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
    }
    
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
