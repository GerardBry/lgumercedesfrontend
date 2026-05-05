<?php
/**
 * Notifications API
 * Handle notification retrieval and updates
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db_connect.php';
require_once '../config/notification_helpers.php';

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
if ($action === 'get-unread-count') {
    // Get unread notifications count
    $count = getUnreadNotificationsCount($conn, $user_id);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

if ($action === 'get-notifications') {
    // Get recent notifications
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $notifications = getUserNotifications($conn, $user_id, $limit);
    
    // Format notifications for display
    $formatted = [];
    foreach ($notifications as $notif) {
        $formatted[] = [
            'id' => $notif['id'],
            'type' => $notif['type'],
            'tracking_number' => $notif['tracking_number'],
            'message' => htmlspecialchars($notif['message']),
            'is_read' => (bool)$notif['is_read'],
            'created_at' => date('M d, Y h:i A', strtotime($notif['created_at'])),
            'created_timestamp' => $notif['created_at'],
            'old_status' => $notif['old_status'],
            'new_status' => $notif['new_status']
        ];
    }
    
    echo json_encode(['success' => true, 'notifications' => $formatted]);
    exit;
}

if ($action === 'mark-as-read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['notification_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
        exit;
    }
    
    $notification_id = intval($data['notification_id']);
    $result = markNotificationAsRead($conn, $notification_id, $user_id);
    
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'mark-all-as-read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = markAllNotificationsAsRead($conn, $user_id);
    echo json_encode(['success' => $result]);
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
$conn->close();
?>
