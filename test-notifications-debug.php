<?php
/**
 * Debug Notifications - Check what's in the notifications table
 */
session_start();

// Allow access for logged-in users
if (empty($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications Debug</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #ff9500; padding-bottom: 10px; }
        .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2196f3; color: white; padding: 12px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; }
        table tr:hover { background: #f9f9f9; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .unread { background: #ffebee; color: #c62828; }
        .read { background: #e8f5e9; color: #2e7d32; }
        .button { background: #ff9500; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .button:hover { background: #f57c00; }
        .error { background: #ffebee; border-left: 4px solid #f44336; color: #c62828; padding: 15px; margin: 20px 0; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; color: #2e7d32; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Notifications Debug Tool</h1>
        
        <div class="info">
            <strong>Current User:</strong> <?php echo htmlspecialchars("$first_name $last_name"); ?> (ID: <?php echo $user_id; ?>)<br>
            <strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?><br>
            <strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <h2>Your Notifications</h2>
        <?php
        $sql = "SELECT id, user_id, type, tracking_number, message, is_read, created_at FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Type</th><th>Tracking #</th><th>Message</th><th>Status</th><th>Created</th>';
            echo '</tr></thead><tbody>';
            
            while ($row = $result->fetch_assoc()) {
                $status = $row['is_read'] ? 'Read' : 'Unread';
                $statusClass = $row['is_read'] ? 'read' : 'unread';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['tracking_number'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['message']) . '</td>';
                echo '<td><span class="status ' . $statusClass . '">' . $status . '</span></td>';
                echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<div class="error"><i class="fas fa-info-circle"></i> No notifications found for your account.</div>';
        }
        
        $stmt->close();
        
        // Show recent notifications from other admins too (for debugging)
        echo '<hr><h2>All Recent Notifications (for debugging)</h2>';
        $sql_all = "SELECT n.id, u.first_name, u.last_name, u.id as user_id, n.type, n.tracking_number, n.message, n.is_read, n.created_at 
                    FROM notifications n
                    JOIN users u ON n.user_id = u.id
                    ORDER BY n.created_at DESC 
                    LIMIT 50";
        
        $result_all = $conn->query($sql_all);
        
        if ($result_all->num_rows > 0) {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID</th><th>For User</th><th>Type</th><th>Tracking #</th><th>Message</th><th>Status</th><th>Created</th>';
            echo '</tr></thead><tbody>';
            
            while ($row = $result_all->fetch_assoc()) {
                $status = $row['is_read'] ? 'Read' : 'Unread';
                $statusClass = $row['is_read'] ? 'read' : 'unread';
                $userDisplay = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' (ID: ' . $row['user_id'] . ')';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                echo '<td>' . $userDisplay . '</td>';
                echo '<td>' . htmlspecialchars($row['type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['tracking_number'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars(substr($row['message'], 0, 60)) . (strlen($row['message']) > 60 ? '...' : '') . '</td>';
                echo '<td><span class="status ' . $statusClass . '">' . $status . '</span></td>';
                echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        ?>

        <hr>
        <h2>Test Notification API</h2>
        <p>Test the API endpoint directly:</p>
        <button class="button" onclick="testAPI()">Test Get Notifications API</button>
        <div id="apiResult" style="margin-top: 20px; background: #f0f0f0; padding: 15px; border-radius: 4px; display: none;"></div>

        <script>
            async function testAPI() {
                try {
                    const response = await fetch('api/notifications-api.php?action=get-notifications&limit=20');
                    const data = await response.json();
                    
                    const resultDiv = document.getElementById('apiResult');
                    resultDiv.style.display = 'block';
                    
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <h3 style="color: green;"><i class="fas fa-check"></i> API Success</h3>
                            <p><strong>Found ${data.notifications.length} notifications</strong></p>
                            <pre>${JSON.stringify(data.notifications, null, 2)}</pre>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <h3 style="color: red;"><i class="fas fa-times"></i> API Error</h3>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `;
                    }
                } catch (error) {
                    document.getElementById('apiResult').style.display = 'block';
                    document.getElementById('apiResult').innerHTML = `
                        <h3 style="color: red;"><i class="fas fa-times"></i> Error</h3>
                        <p>${error.message}</p>
                    `;
                }
            }
        </script>
    </div>
</body>
</html>
<?php
$conn->close();
?>
