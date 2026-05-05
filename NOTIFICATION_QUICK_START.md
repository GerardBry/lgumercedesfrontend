# Notification System - Quick Start Guide

## 🚀 Setup (5 minutes)

### Step 1: Create the Database Table
1. Open your browser
2. Navigate to: `http://localhost/LGU%20draft%20Design%20Front%20End/migrate-notifications.php`
3. You should see: ✓ "Notifications table created successfully!"

**That's it! Your notifications system is ready.**

---

## 📝 What You Now Have

### For Staff Members:
- 🔔 **Notification Bell** in top-right corner (finished.php page)
- 🔴 **Red Badge** showing unread notification count
- 📲 **Dropdown Menu** with all notifications
- 📢 **Toast Notifications** that pop up when status is updated
- ⏰ **Auto-Refresh** every 10 seconds

### Notification Messages:
**When assigned:**
```
Administrative has assigned you a request with tracking code: LGU-2026-04-30-001 (Travel Order)
```

**When status updated:**
```
Your request with tracking code: LGU-2026-04-30-001 has been updated to: In Progress
```

---

## 🧪 Test It Out

### Test Notification 1: Assignment
1. Log in as Admin
2. Go to "Assign Documents"
3. Create a new assignment to a staff member
4. As staff member, check the notification bell
5. ✅ New notification should appear

### Test Notification 2: Status Update
1. Log in as Admin
2. Open any staff member's assignment
3. Update the status (e.g., "Pending" → "In Progress")
4. As staff member, check the notification bell
5. ✅ Status update notification should appear with toast popup

---

## 📂 Files Created

```
administrative/
├── finished.php (MODIFIED - added notification bell)
├── update-assignment-status.php (NEW - handles status updates)
├── assign-document-handler.php (MODIFIED - creates notifications)
└── notification-header.php (NEW - reusable component)

api/
└── notifications-api.php (NEW - notification API endpoints)

config/
├── notification_helpers.php (NEW - helper functions)
└── notifications.sql (NEW - database schema)

css/
└── notifications.css (NEW - notification styling)

js/
└── notifications.js (NEW - notification JavaScript manager)

Root:
├── migrate-notifications.php (NEW - database migration)
├── NOTIFICATION_SYSTEM.md (NEW - detailed documentation)
└── NOTIFICATION_QUICK_START.md (NEW - this file)
```

---

## 🎨 Customization

### Change Bell Icon Color
Edit `css/notifications.css`, find `.notification-bell`:
```css
.notification-bell {
    color: #333;  /* Change this to your color */
}
```

### Change Badge Color
Edit `css/notifications.css`, find `.notification-badge`:
```css
.notification-badge {
    background-color: #dc3545;  /* Change to your color */
}
```

### Change Poll Interval
Edit `js/notifications.js`, find the interval line (around line 30):
```javascript
setInterval(() => {
    this.checkForNewNotifications();
}, 10000); // Change 10000 (milliseconds) to any value you want
```

---

## ➕ Add to More Pages

To add the notification bell to other administrative pages (incoming.php, received.php, etc.):

### 1. In the `<head>` section:
```html
<link rel="stylesheet" href="../css/notifications.css">
```

### 2. In the main content area (where the bell should appear):
```html
<div class="header-right" style="display: flex; gap: 16px; align-items: center;">
    <!-- Notification Bell will be inserted here by notifications.js -->
</div>
```

### 3. Before closing `</body>`:
```html
<script src="../js/notifications.js"></script>
```

---

## 🔍 Verify Everything Works

### Check 1: Database Table Exists
```sql
SELECT * FROM notifications LIMIT 1;
```
Should return an empty result (table exists but no records yet)

### Check 2: API Working
Visit: `http://localhost/LGU%20draft%20Design%20Front%20End/api/notifications-api.php?action=get-unread-count`

Should return something like:
```json
{"success":true,"count":0}
```

### Check 3: Browser Console
1. Open any admin page with notifications
2. Press F12 (Developer Tools)
3. Check Console for any red errors
4. Should see notifications loading normally

---

## 📊 Database Queries

### Count total notifications:
```sql
SELECT COUNT(*) FROM notifications;
```

### See all unread notifications for user:
```sql
SELECT * FROM notifications WHERE user_id = 1 AND is_read = FALSE;
```

### Delete old notifications (older than 30 days):
```sql
DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## ⚙️ Troubleshooting

### Problem: No notification bell appears
- ✓ Verify CSS file loads: Check browser F12 > Network tab
- ✓ Verify JS file loads: Check browser F12 > Network tab
- ✓ Check browser console for errors (F12 > Console)

### Problem: Notifications not triggering
- ✓ Check migration ran: Visit migrate-notifications.php again
- ✓ Check table exists: `SHOW TABLES LIKE 'notifications';`
- ✓ Check database connection in `config/db_connect.php`

### Problem: Toast notifications not showing
- ✓ Check JS console (F12) for errors
- ✓ Verify `status-update` endpoint is being called
- ✓ Check notification function is being called in handlers

---

## 🎯 What's Next?

1. ✅ Run the migration
2. ✅ Test with an assignment
3. ✅ Test with a status update
4. ✅ Add bell to other pages as needed
5. 🔧 Customize colors/styling to match your theme
6. 📖 Read NOTIFICATION_SYSTEM.md for advanced usage

---

## 💡 Pro Tips

1. **Disable notifications temporarily**: Comment out the notification creation lines in handlers
2. **Clear notifications**: `DELETE FROM notifications WHERE user_id = ?;`
3. **Test notifications**: Create them directly in database for testing
4. **Monitor performance**: Check browser Network tab to see API calls
5. **Customize messages**: Edit notification messages in helper functions

---

## 📞 Support

See `NOTIFICATION_SYSTEM.md` for:
- Detailed API documentation
- Complete database schema
- Advanced customization
- Performance optimization tips
- Future enhancement suggestions
