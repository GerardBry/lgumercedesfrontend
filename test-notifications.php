<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification System Test</title>
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .test-header h1 {
            margin: 0;
        }
        .header-right {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .test-content {
            padding: 20px 0;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .instructions {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #0066cc;
        }
        .instructions h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🔔 Notification System Test Page</h1>
            <div class="header-right">
                <!-- Notification Bell will appear here -->
            </div>
        </div>

        <div class="test-content">
            <div class="status success">
                ✓ Notification bell should be visible in the top-right corner
            </div>

            <div class="status info">
                ℹ If you see a bell icon (🔔) with an orange color in the header, the notification system is working!
            </div>

            <div class="instructions">
                <h3>What to check:</h3>
                <ul>
                    <li>✓ See an <strong>orange bell icon</strong> in the top-right?</li>
                    <li>✓ Can you <strong>click on the bell</strong>?</li>
                    <li>✓ Does a <strong>dropdown menu appear</strong> below it?</li>
                    <li>✓ Does the dropdown say <strong>"No notifications"</strong>?</li>
                </ul>
            </div>

            <div class="status info">
                <strong>Next steps:</strong>
                <ol>
                    <li>Assign a document from the admin side</li>
                    <li>Return to the staff side</li>
                    <li>The notification bell should show a red badge with a number</li>
                    <li>Click the bell to see the notification</li>
                </ol>
            </div>

            <div class="instructions">
                <h3>Troubleshooting:</h3>
                <ul>
                    <li>If no bell appears: Open browser console (F12) and check for errors</li>
                    <li>If bell appears but can't click: Check browser console</li>
                    <li>If dropdown won't open: Reload the page and try again</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="js/notifications.js"></script>
    <script>
        console.log('Notification test page loaded');
        setTimeout(() => {
            const bell = document.getElementById('notificationBell');
            if (bell) {
                console.log('✓ Notification bell found and working!');
            } else {
                console.error('✗ Notification bell not found!');
            }
        }, 1000);
    </script>
</body>
</html>
