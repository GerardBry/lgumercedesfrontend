<?php
/**
 * Notification Helper Functions
 * Handle all notification creation and retrieval logic
 */

/**
 * Create a notification for document assignment
 */
function createAssignmentNotification($conn, $user_id, $document_id, $assignment_id, $tracking_number, $title) {
    $type = 'assignment';
    $message = "Administrative has assigned you a request with tracking code: $tracking_number ($title)";
    
    $sql = "INSERT INTO notifications (user_id, type, tracking_number, document_id, assignment_id, message, is_read) 
            VALUES (?, ?, ?, ?, ?, ?, FALSE)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ississ', $user_id, $type, $tracking_number, $document_id, $assignment_id, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

/**
 * Create a notification for status update
 */
function createStatusUpdateNotification($conn, $user_id, $document_id, $assignment_id, $tracking_number, $old_status, $new_status) {
    $type = 'status_update';
    $message = "Your request with tracking code: $tracking_number has been updated to: $new_status";
    
    $sql = "INSERT INTO notifications (user_id, type, tracking_number, document_id, assignment_id, old_status, new_status, message, is_read) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('issiisss', $user_id, $type, $tracking_number, $document_id, $assignment_id, $old_status, $new_status, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

/**
 * Create a custom notification message
 */
function createCustomNotification($conn, $user_id, $document_id, $assignment_id, $tracking_number, $message, $type = 'status_update', $old_status = null, $new_status = null) {
    $sql = "INSERT INTO notifications (user_id, type, tracking_number, document_id, assignment_id, old_status, new_status, message, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('issiisss', $user_id, $type, $tracking_number, $document_id, $assignment_id, $old_status, $new_status, $message);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    return false;
}

/**
 * Get unread notifications count for a user
 */
function getUnreadNotificationsCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['count'];
    }
    return 0;
}

/**
 * Get recent notifications for a user
 */
function getUserNotifications($conn, $user_id, $limit = 10) {
    $sql = "SELECT 
            id,
            type,
            tracking_number,
            message,
            is_read,
            created_at,
            old_status,
            new_status
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    }
    return [];
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $notification_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

/**
 * Mark a specific assignment notification as read for a user
 */
function markAssignmentNotificationAsRead($conn, $user_id, $assignment_id) {
    $sql = "UPDATE notifications
            SET is_read = TRUE, read_at = NOW()
            WHERE user_id = ? AND assignment_id = ? AND type = 'assignment' AND is_read = FALSE";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $assignment_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    return false;
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
            WHERE user_id = ? AND is_read = FALSE";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
}

?>
