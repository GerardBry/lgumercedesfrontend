# Notification System Documentation

## Overview
A complete notification system for the administrative staff side of the LGU Mercedes Document Tracking System. Staff members receive real-time notifications when documents are assigned to them and when status updates occur.

## Features

### 1. **Notification Types**
- **Assignment Notifications**: Triggered when admin assigns a document to staff
  - Format: "Administrative has assigned you a request with tracking code: [CODE] ([TITLE])"
  
- **Status Update Notifications**: Triggered when admin updates document status
  - Format: "Your request with tracking code: [CODE] has been updated to: [STATUS]"

### 2. **User Interface**
- **Notification Bell Icon** (Top-right corner)
  - Shows unread count in red badge
  - Displays "99+" for more than 99 unread notifications
  - Toggles dropdown with click

- **Notification Dropdown**
  - Lists all recent notifications (max 20 shown)
  - "Mark all as read" button
  - Unread notifications highlighted in light blue
  - "NEW" badge on unread items
  - Shows time/date of each notification

- **Toast Notifications**
  - Real-time slide notification when status is updated
  - Auto-hides after 4 seconds
  - Appears in top-right corner
  - Non-intrusive design

### 3. **Auto-Polling**
- Checks for new notifications every 10 seconds
- Only loads full list when unread count changes
- Efficient background checking

## Installation

### Step 1: Run Database Migration
1. Open your browser and navigate to:
   ```
   http://localhost/LGU%20draft%20Design%20Front%20End/migrate-notifications.php
   ```
2. The system will create the `notifications` table automatically
3. You should see a success message

### Step 2: Verify Files
Check that all files are in place:
```
- config/notification_helpers.php
- config/notifications.sql
- api/notifications-api.php
- css/notifications.css
- js/notifications.js
- administrative/update-assignment-status.php
- administrative/notification-header.php
- migrate-notifications.php
```

### Step 3: Update Pages
The `finished.php` page has already been updated. To add notifications to other administrative pages, add these lines:

**In `<head>` section:**
```php
<link rel="stylesheet" href="../css/notifications.css">
```

**In the header area (where the notification bell should appear):**
```php
<div class="header-right" style="display: flex; gap: 16px; align-items: center;">
    <!-- Notification Bell will be inserted here by notifications.js -->
</div>
```

**Before `</body>` tag:**
```php
<script src="../js/notifications.js"></script>
```

## Database Schema

### notifications Table
```sql
- id (INT): Primary key
- user_id (INT): User receiving notification
- type (VARCHAR 50): 'assignment' or 'status_update'
- tracking_number (VARCHAR 50): Document tracking code
- document_id (INT): Foreign key to documents
- assignment_id (INT): Foreign key to document_assignments
- old_status (VARCHAR 100): Previous status (null for assignments)
- new_status (VARCHAR 100): New status (null for assignments)
- message (LONGTEXT): Full notification message
- is_read (BOOLEAN): Read status
- created_at (TIMESTAMP): Creation time
- read_at (TIMESTAMP): When marked as read
```

## How It Works

### Assignment Flow
1. Admin assigns document via `assign-document.php`
2. `assign-document-handler.php` processes the assignment
3. `createAssignmentNotification()` creates notification in database
4. Staff sees notification bell badge increase
5. Notification appears in dropdown list

### Status Update Flow
1. Admin updates status via admin dashboard or similar
2. `update-assignment-status.php` processes the status change
3. `createStatusUpdateNotification()` creates notification
4. Staff's browser detects new unread count (every 10 seconds)
5. Notification dropdown refreshes
6. Toast notification appears with status update

## API Endpoints

### Get Unread Count
```
GET: /api/notifications-api.php?action=get-unread-count
Response: { success: true, count: 3 }
```

### Get All Notifications
```
GET: /api/notifications-api.php?action=get-notifications&limit=20
Response: {
  success: true,
  notifications: [
    {
      id: 1,
      type: 'assignment',
      tracking_number: 'LGU-2026-04-30-001',
      message: 'Administrative has assigned you...',
      is_read: false,
      created_at: 'Apr 30, 2026 02:15 PM'
    }
  ]
}
```

### Mark as Read
```
POST: /api/notifications-api.php?action=mark-as-read
Body: { notification_id: 1 }
Response: { success: true }
```

### Mark All as Read
```
POST: /api/notifications-api.php?action=mark-all-as-read
Response: { success: true }
```

## JavaScript Functions

### NotificationManager Class
Located in `js/notifications.js`

**Key Methods:**
- `init()`: Initializes the notification system
- `loadNotifications()`: Fetches current notifications
- `checkForNewNotifications()`: Polls for updates
- `showToast(message, type, title)`: Shows toast notification
- `toggleDropdown()`: Opens/closes notification dropdown
- `markAsRead(notificationId)`: Marks single notification
- `markAllAsRead()`: Marks all as read

**Usage Example:**
```javascript
// Show a manual toast notification
notificationManager.showToast('Document status updated', 'info', 'Update');
```

## PHP Helper Functions

Located in `config/notification_helpers.php`

### createAssignmentNotification()
```php
createAssignmentNotification(
  $conn, 
  $user_id,      // Staff member receiving notification
  $document_id,  // Document ID
  $assignment_id,// Assignment ID
  $tracking_number, // e.g., "LGU-2026-04-30-001"
  $title         // Document title
)
```

### createStatusUpdateNotification()
```php
createStatusUpdateNotification(
  $conn,
  $user_id,      // Staff member receiving notification
  $document_id,  // Document ID
  $assignment_id,// Assignment ID
  $tracking_number, // e.g., "LGU-2026-04-30-001"
  $old_status,   // Previous status
  $new_status    // New status
)
```

### getUserNotifications()
```php
$notifications = getUserNotifications($conn, $user_id, $limit = 10);
// Returns array of notification objects
```

### getUnreadNotificationsCount()
```php
$count = getUnreadNotificationsCount($conn, $user_id);
// Returns integer count
```

## Styling Customization

### Color Scheme
Edit `css/notifications.css`:
- Change `.notification-badge` background-color for badge color (default: #dc3545 - red)
- Change `.notification-item.unread` background-color for unread highlight
- Change `.notification-item-icon` color for icon color

### Toast Position
In `notifications.js`, modify the `showToast()` function:
```javascript
toast.style.top = '20px';    // Change vertical position
toast.style.right = '20px';  // Change horizontal position
```

### Poll Interval
In `notifications.js`, change the interval (default: 10000ms = 10 seconds):
```javascript
setInterval(() => {
    this.checkForNewNotifications();
}, 10000); // Change this value
```

## Troubleshooting

### Notifications Not Showing
1. Verify migration ran successfully (check table exists)
2. Check browser console for JavaScript errors
3. Verify API endpoints are accessible: `/api/notifications-api.php`
4. Check database connection in `config/db_connect.php`

### Notifications Not Triggering
1. Verify `createAssignmentNotification()` is called in assign-document-handler.php
2. Verify `createStatusUpdateNotification()` is called in status update handlers
3. Check database for notification records being inserted
4. Verify user_id is correct

### Performance Issues
1. Reduce poll interval if needed: change 10000 to 5000 in `notifications.js`
2. Limit notifications shown: change `&limit=20` in API calls
3. Consider adding pagination for older notifications

## Future Enhancements

Possible improvements:
1. Add notification preferences (disable certain types)
2. Add email notifications
3. Add SMS notifications for urgent updates
4. Add notification history/archive
5. Add notification filtering by type
6. Add notification deletion
7. Add notification expiration (auto-delete after X days)
8. Add sound alerts for important notifications
9. Add browser push notifications
10. Add notification categories/labels

## Compatibility

- **PHP**: 7.4+
- **MySQL/MariaDB**: 5.7+
- **Browsers**: All modern browsers (Chrome, Firefox, Safari, Edge)
- **Mobile**: Fully responsive

## Support

For issues or questions:
1. Check JavaScript console for errors (F12)
2. Check PHP error logs
3. Verify database table structure
4. Ensure all files are in correct directories
