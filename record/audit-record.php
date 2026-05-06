<?php
/**
 * Audit Records Page
 * Record Officer - Audit Trail and Export
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: text/html; charset=utf-8');

// STRICT ROLE-BASED ACCESS CONTROL
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Only allow Record Officer role
if ($_SESSION['role'] !== 'Record Officer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$officer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Record Officer';
$last_name = $_SESSION['last_name'] ?? '';

// Get audit logs (assuming they're stored in a table or we're fetching from document_assignments history)
$audit_query = "SELECT 
    da.id,
    d.tracking_number,
    d.title,
    d.document_type,
    da.status,
    da.assigned_at,
    da.received_at,
    da.completed_at,
    u_creator.first_name as creator_first_name,
    u_creator.last_name as creator_last_name
FROM document_assignments da
JOIN documents d ON da.document_id = d.id
LEFT JOIN users u_creator ON da.assigned_by = u_creator.id
ORDER BY GREATEST(COALESCE(da.completed_at, ''), COALESCE(da.received_at, ''), COALESCE(da.assigned_at, '')) DESC
LIMIT 200";

$audit_logs = [];
$result = $conn->query($audit_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $audit_logs[] = $row;
    }
}

// Handle PDF export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // For now, just redirect to a simple view
    // You can integrate a PDF library like TCPDF or mPDF later
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="audit-logs-' . date('Y-m-d-His') . '.txt"');
    
    echo "AUDIT LOGS EXPORT\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "Record Officer: " . $first_name . ' ' . $last_name . "\n";
    echo "=".str_repeat("=", 130)."\n\n";
    
    foreach ($audit_logs as $log) {
        echo "Tracking: " . $log['tracking_number'] . "\n";
        echo "Title: " . $log['title'] . "\n";
        echo "Type: " . $log['document_type'] . "\n";
        echo "Status: " . $log['status'] . "\n";
        echo "Created By: " . $log['creator_first_name'] . " " . $log['creator_last_name'] . "\n";
        echo "Assigned: " . ($log['assigned_at'] ? date('Y-m-d H:i:s', strtotime($log['assigned_at'])) : '-') . "\n";
        echo "Received: " . ($log['received_at'] ? date('Y-m-d H:i:s', strtotime($log['received_at'])) : '-') . "\n";
        echo "Completed: " . ($log['completed_at'] ? date('Y-m-d H:i:s', strtotime($log['completed_at'])) : '-') . "\n";
        echo "-".str_repeat("-", 130)."\n";
    }
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Record Officer</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff9500;
            --sidebar-bg: #1a1a2e;
            --bg-white: #ffffff;
            --bg-light: #f5f5f5;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #e0e0e0;
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --radius-lg: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .sidebar-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .nav-menu ul {
            list-style: none;
        }

        .nav-menu li {
            margin: 4px 0;
            padding: 0 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            color: #ffffff;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin: 2px 0 0 0;
        }

        .logout-btn {
            background-color: #e0e0e0;
            color: var(--text-dark);
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            overflow-y: auto;
            min-height: 100vh;
        }

        .page {
            padding: 40px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h2 {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-light);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .data-table thead th {
            background-color: #f5f5f5;
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
        }

        .data-table tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background-color: #d4edda;
            color: #155724;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            background-color: var(--primary-color);
            color: white;
        }

        .btn:hover {
            background-color: #e68900;
        }

        .export-section {
            background-color: var(--bg-white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .export-section h3 {
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .export-section p {
            color: var(--text-light);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-file-archive"></i>
                </div>
                <h1>Record Officer</h1>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li><a href="admin-dashboard-officer.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
                    <li style="margin: 12px 0; padding: 0; height: 1px; background: rgba(255,255,255,0.1);"></li>
                    <li><a href="manage-document.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Manage Documents</span></a></li>
                    <li><a href="audit-record.php" class="nav-item active"><i class="fas fa-history"></i><span>Audit Logs</span></a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="user-role">Record Officer</p>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page">
                <div class="page-header">
                    <h2><i class="fas fa-history" style="color: var(--primary-color); margin-right: 12px;"></i>Audit Logs</h2>
                    <p>Complete audit trail of all document operations</p>
                </div>

                <!-- Export Section -->
                <div class="export-section">
                    <h3><i class="fas fa-download" style="color: var(--primary-color); margin-right: 8px;"></i>Export Audit Logs</h3>
                    <p>Download all audit logs as a non-editable text file for record keeping and compliance.</p>
                    <a href="?export=pdf" class="btn">
                        <i class="fas fa-download"></i> Export as Text File
                    </a>
                </div>

                <!-- Audit Logs Table -->
                <?php if (count($audit_logs) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tracking Code</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Assigned</th>
                                    <th>Received</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($log['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['title']); ?></td>
                                        <td><?php echo htmlspecialchars($log['document_type']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($log['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars(($log['creator_first_name'] ?? '') . ' ' . ($log['creator_last_name'] ?? '')); ?></td>
                                        <td><?php echo $log['assigned_at'] ? date('M d, Y H:i', strtotime($log['assigned_at'])) : '-'; ?></td>
                                        <td><?php echo $log['received_at'] ? date('M d, Y H:i', strtotime($log['received_at'])) : '-'; ?></td>
                                        <td><?php echo $log['completed_at'] ? date('M d, Y H:i', strtotime($log['completed_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No audit logs found</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
