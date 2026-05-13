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
    // Convert null values to empty strings, and 0 to empty string (for NULL handling)
    $old_status_val = $old_status !== null ? $old_status : '';
    $new_status_val = $new_status !== null ? $new_status : '';
    
    // Handle document_id and assignment_id - convert 0 to empty string for NULLIF conversion
    $document_id_val = intval($document_id) > 0 ? intval($document_id) : '';
    $assignment_id_val = intval($assignment_id) > 0 ? intval($assignment_id) : '';
    
    $sql = "INSERT INTO notifications (user_id, type, tracking_number, document_id, assignment_id, old_status, new_status, message, is_read)
            VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, FALSE)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare error: " . $conn->error);
        return false;
    }
    
    // Bind parameters - all as strings to support NULLIF conversion
    // Type string: i(user_id), s(type), s(tracking_number), s(document_id), s(assignment_id), s(old_status), s(new_status), s(message)
    // Total: 1 int + 7 strings = 'isssssss'
    $stmt->bind_param('isssssss', $user_id, $type, $tracking_number, $document_id_val, $assignment_id_val, $old_status_val, $new_status_val, $message);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Notification insert error: " . $stmt->error);
    }
    $stmt->close();
    
    return $result;
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
