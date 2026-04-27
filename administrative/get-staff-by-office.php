<?php
/**
 * Get Staff By Office - Fetch staff members registered in a specific office
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

// Validate office parameter
if (!isset($_GET['office']) || empty($_GET['office'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing office parameter']);
    exit;
}

$office = trim($_GET['office']);

require_once '../config/db_connect.php';

try {
    // Get all staff members from the specified office
    $sql = "SELECT id, first_name, last_name, position, email 
            FROM users 
            WHERE office_department = ? 
            AND status = 'Active'
            AND role = 'Department Staff'
            ORDER BY first_name ASC, last_name ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $office);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'position' => $row['position'] ?? 'Staff',
            'email' => $row['email']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
