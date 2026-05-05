<?php
/**
 * Admin Track Documents Page
 * Search documents by tracking code and view complete audit trail
 * Super Admin only
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: admin-login.php');
    exit;
}

// STRICT ROLE-BASED ACCESS CONTROL - Only Super Admin allowed
if ($_SESSION['role'] !== 'Super Admin') {
    header('Location: ../index.php');
    exit;
}

// Verify user role in database
$admin_id = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Super Admin' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $admin_id);
    $role_check->execute();
    $role_result = $role_check->get_result();
    
    if ($role_result->num_rows === 0) {
        session_destroy();
        header('Location: admin-login.php');
        exit;
    }
    $role_check->close();
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Admin';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'Super Admin';

function formatNotesForDisplay($rawNotes) {
    if ($rawNotes === null) {
        return '-';
    }

    $rawNotes = trim((string)$rawNotes);
    if ($rawNotes === '') {
        return '-';
    }

    $decoded = json_decode($rawNotes, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $parts = [];

        if (!empty($decoded['subject'])) {
            $parts[] = 'Subject: ' . $decoded['subject'];
        }
        if (!empty($decoded['purpose'])) {
            $parts[] = 'Purpose: ' . $decoded['purpose'];
        }
        if (!empty($decoded['title'])) {
            $parts[] = 'Title: ' . $decoded['title'];
        }
        if (!empty($decoded['type'])) {
            $parts[] = 'Type: ' . $decoded['type'];
        }
        if (!empty($decoded['orderNumber'])) {
            $parts[] = 'Order #: ' . $decoded['orderNumber'];
        }

        if (!empty($parts)) {
            return implode(' | ', $parts);
        }

        return 'Document details saved';
    }

    return $rawNotes;
}

// Tracking search functionality
$tracking_code = isset($_GET['tracking_code']) ? trim($_GET['tracking_code']) : '';
$search_performed = $tracking_code !== '';
$results = [];

if ($search_performed) {
    $sql_track = "SELECT
            da.id as assignment_id,
            d.id as document_id,
            d.tracking_number,
            d.title,
            d.description,
            da.notes,
            d.document_type,
            d.status as document_status,
            d.date_sent,
            da.status as assignment_status,
            da.assigned_at,
            da.received_at,
            da.completed_at,
            recipient.first_name as recipient_first_name,
            recipient.last_name as recipient_last_name,
            recipient.position as recipient_position,
            assigner.first_name as assigner_first_name,
            assigner.last_name as assigner_last_name
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users recipient ON da.assigned_to = recipient.id
        LEFT JOIN users assigner ON da.assigned_by = assigner.id
        WHERE d.tracking_number = ?
        ORDER BY COALESCE(da.completed_at, da.received_at, da.assigned_at, d.date_sent) DESC, da.id DESC
        LIMIT 1";

    $stmt_track = $conn->prepare($sql_track);
    if ($stmt_track) {
        $stmt_track->bind_param("s", $tracking_code);
        $stmt_track->execute();
        $result_track = $stmt_track->get_result();
        while ($row = $result_track->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt_track->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Documents - Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Dashboard Specific Styles */
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
        }

        .admin-sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2c2c3e 0%, #1a1a2e 100%);
            color: #ffffff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow-y: auto;
        }

        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: transparent; }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .admin-sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .admin-sidebar-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .admin-nav-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .admin-nav-menu ul { list-style: none; }
        .admin-nav-menu li {
            margin: 4px 0;
            padding: 0 12px;
        }

        .admin-nav-menu .divider {
            height: 1px;
            background-color: rgba(255, 255, 255, 0.1);
            margin: 12px 0;
            padding: 0;
        }

        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .admin-nav-item i { width: 20px; text-align: center; }

        .admin-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .admin-nav-item.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        .admin-sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .admin-user-info { flex: 1; }

        .admin-user-name {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .admin-user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin: 2px 0 0 0;
        }

        .logout-btn {
            background-color: #e0e0e0;
            color: var(--text-dark);
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .admin-main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            overflow-y: auto;
            min-height: 100vh;
        }

        .admin-page { padding: 40px; }

        .page-header { margin-bottom: 32px; }

        .page-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-light);
        }

        /* Search Section */
        .search-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
        }

        .search-box {
            display: flex;
            gap: 12px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-white);
            color: var(--text-dark);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-info {
            background-color: #0d7377;
            color: #ffffff;
        }

        .btn-info:hover {
            background-color: #09515a;
        }

        /* Table Section */
        .table-container {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background-color: var(--bg-light);
            border-bottom: 2px solid var(--border-color);
        }

        .data-table th {
            padding: 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-dark);
        }

        .data-table tbody tr:hover { background-color: var(--bg-light); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Admin Panel</h1>
            </div>

            <div class="admin-nav-menu">
                <ul>
                    <li><a href="admin-dashboard.php" class="admin-nav-item" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="accounts.php" class="admin-nav-item" title="Manage Accounts">
                        <i class="fas fa-users"></i>
                        <span>Accounts</span>
                    </a></li>
                    <li><a href="audit-logs.php" class="admin-nav-item" title="Audit Logs">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a></li>
                    <li class="divider"></li>
                    <li><a href="trackdocument.php" class="admin-nav-item active" title="Track Document">
                        <i class="fas fa-map-location-dot"></i>
                        <span>Track Document</span>
                    </a></li>
                </ul>
            </div>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
                <a href="admin-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main-content">
            <div class="admin-page">
                <div class="page-header">
                    <h2><i class="fas fa-map-location-dot" style="color: var(--primary-color); margin-right: 12px;"></i>Track Documents</h2>
                    <p>Search and track any document in the system using its tracking code</p>
                </div>

                <div class="search-section">
                    <form class="search-box" method="GET" action="trackdocument.php">
                        <input 
                            type="text" 
                            name="tracking_code"
                            placeholder="Enter tracking code (e.g., LGU-2026-04-23-913)"
                            value="<?php echo htmlspecialchars($tracking_code); ?>"
                            required
                        >
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Track
                        </button>
                    </form>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Document Title</th>
                                <th>Sender</th>
                                <th>Document Type</th>
                                <th>Date Sent</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$search_performed): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-search" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                        Enter a tracking code to search
                                    </td>
                                </tr>
                            <?php elseif (count($results) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                        No record found for the tracking code you entered
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                        $status = $row['assignment_status'] ?? 'Pending';
                                        $badge_class = 'badge-info';
                                        if ($status === 'Received') {
                                            $badge_class = 'badge-success';
                                        } elseif ($status === 'Pending') {
                                            $badge_class = 'badge-warning';
                                        } elseif ($status === 'Checking Documents') {
                                            $badge_class = 'badge-warning';
                                        } elseif ($status === 'Completed') {
                                            $badge_class = 'badge-success';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['tracking_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($row['assigner_first_name'] ?? '') . ' ' . ($row['assigner_last_name'] ?? '')) ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_type'] ?? '-'); ?></td>
                                        <td><?php echo $row['date_sent'] ? date('M d, Y h:i A', strtotime($row['date_sent'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewAuditTrail('<?php echo htmlspecialchars($row['tracking_number']); ?>')">
                                                <i class="fas fa-history"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Timeline Modal -->
    <div id="auditTrailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-white); border-radius: var(--radius-lg); width: 90%; max-width: 900px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--bg-white); z-index: 10;">
                <h2 style="margin: 0; font-size: 22px; color: var(--text-dark);"><i class="fas fa-history" style="margin-right: 10px; color: var(--primary-color);"></i> Document Audit Trail</h2>
                <button onclick="closeAuditTrail()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-light);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 32px;" id="timelineContent">
                <div style="text-align: center; padding: 40px; color: var(--text-light);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i>
                    <p>Loading audit trail...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ==========================================
        // AUDIT TRAIL FUNCTIONS
        // ==========================================
        
        function viewAuditTrail(trackingCode) {
            const modal = document.getElementById('auditTrailModal');
            const timelineContent = document.getElementById('timelineContent');
            
            // Show modal with loading
            modal.style.display = 'flex';
            timelineContent.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-light);"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i><p>Loading audit trail...</p></div>';
            
            // Fetch audit trail data from admin API
            fetch('../administrative/track-handler.php?tracking_code=' + encodeURIComponent(trackingCode))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        timelineContent.innerHTML = '<div style="padding: 40px; color: #d32f2f; text-align: center;"><i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px;"></i><p>' + htmlEscape(data.message || 'Failed to load audit trail') + '</p></div>';
                        return;
                    }
                    
                    // Build HTML for timeline
                    let html = '';
                    
                    // Document Summary
                    html += '<div style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%); color: #ffffff; padding: 20px; border-radius: var(--radius-md); margin-bottom: 32px;">';
                    html += '<div style="margin-bottom: 16px;">';
                    html += '<h3 style="margin: 0 0 12px 0; font-size: 18px;">' + htmlEscape(data.document.title) + '</h3>';
                    html += '</div>';
                    html += '<div style="margin: 8px 0; font-size: 14px;"><strong style="opacity: 0.9;">Tracking Code:</strong> <strong>' + htmlEscape(data.document.tracking_number) + '</strong></div>';
                    html += '<div style="margin: 8px 0; font-size: 14px;"><strong style="opacity: 0.9;">Type:</strong> ' + htmlEscape(data.document.document_type) + '</div>';
                    html += '<div style="margin: 8px 0; font-size: 14px;"><strong style="opacity: 0.9;">Status:</strong> <strong style="color: #ffeb3b; text-shadow: 0 0 2px rgba(0,0,0,0.3);">' + htmlEscape(data.document.status) + '</strong></div>';
                    html += '<div style="margin: 8px 0; font-size: 14px;"><strong style="opacity: 0.9;">Created by:</strong> ' + htmlEscape(data.document.created_by) + ' on ' + formatDatetime(data.document.created_at) + '</div>';
                    html += '</div>';
                    
                    // Timeline Events
                    html += '<div style="position: relative; padding: 0 0 0 40px;">';
                    html += '<div style="position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, var(--primary-color), var(--primary-light));"></div>';
                    
                    if (data.timeline && data.timeline.length > 0) {
                        data.timeline.forEach((event, index) => {
                            let bulletColor = '#6c757d';
                            if (event.event_type === 'completed') bulletColor = '#28a745';
                            else if (event.event_type === 'received') bulletColor = '#1976d2';
                            else if (event.event_type === 'assigned') bulletColor = 'var(--primary-color)';
                            
                            html += '<div style="position: relative; margin-bottom: 28px;">';
                            html += '<div style="position: absolute; left: -40px; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: var(--bg-white); border: 3px solid ' + bulletColor + '; z-index: 5;"></div>';
                            html += '<div style="background: var(--bg-light); border: 1px solid rgba(0, 0, 0, 0.05); padding: 16px; border-radius: var(--radius-md);">';
                            html += '<h4 style="font-size: 15px; font-weight: 600; color: var(--text-dark); margin: 0 0 8px 0;"><i class="fas fa-' + getEventIcon(event.event_type) + '" style="margin-right: 8px;"></i>' + htmlEscape(event.title) + '</h4>';
                            html += '<p style="font-size: 13px; color: var(--text-dark); margin: 0 0 12px 0; line-height: 1.5;">' + event.description + '</p>';
                            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 12px; color: var(--text-light);">';
                            html += '<div style="display: flex; align-items: center; gap: 6px;"><span style="font-weight: 600; color: var(--text-dark);"><i class="fas fa-user" style="margin-right: 4px;"></i>By:</span> <strong>' + htmlEscape(event.who) + '</strong>';
                            if (event.who_position) {
                                html += ' (' + htmlEscape(event.who_position) + ')';
                            }
                            html += '</div>';
                            html += '<div style="display: flex; align-items: center; gap: 6px;"><span style="font-weight: 600; color: var(--text-dark);"><i class="fas fa-clock" style="margin-right: 4px;"></i>Time:</span> <strong>' + formatDatetime(event.timestamp) + '</strong></div>';
                            
                            if (event.recipient) {
                                html += '<div style="display: flex; align-items: center; gap: 6px;"><span style="font-weight: 600; color: var(--text-dark);"><i class="fas fa-arrow-right" style="margin-right: 4px;"></i>To:</span> <strong>' + htmlEscape(event.recipient) + '</strong>';
                                if (event.recipient_role) {
                                    html += ' (' + htmlEscape(event.recipient_role) + ')';
                                }
                                html += '</div>';
                            }
                            
                            if (event.status) {
                                const badgeColor = getBadgeColor(event.status);
                                html += '<div style="display: flex; align-items: center; gap: 6px;"><span style="font-weight: 600; color: var(--text-dark);"><i class="fas fa-tag" style="margin-right: 4px;"></i>Status:</span> <span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px;">' + htmlEscape(event.status) + '</span></div>';
                            }
                            
                            if (event.notes) {
                                html += '<div style="grid-column: 1 / -1;"><span style="font-weight: 600; color: var(--text-dark);"><i class="fas fa-sticky-note" style="margin-right: 4px;"></i>Notes:</span> ' + htmlEscape(event.notes) + '</div>';
                            }
                            
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                    } else {
                        html += '<div style="text-align: center; padding: 40px; color: var(--text-light);"><p>No events recorded yet</p></div>';
                    }
                    
                    html += '</div>';
                    
                    timelineContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    timelineContent.innerHTML = '<div style="padding: 40px; color: #d32f2f; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>Failed to load audit trail</p></div>';
                });
        }
        
        function closeAuditTrail() {
            document.getElementById('auditTrailModal').style.display = 'none';
        }
        
        function getEventIcon(eventType) {
            const icons = {
                'created': 'file-alt',
                'assigned': 'share',
                'received': 'check-circle',
                'checking_documents': 'search',
                'waiting_approval': 'user-check',
                'completed': 'check-double',
                'forwarded': 'arrow-right'
            };
            return icons[eventType] || 'circle';
        }
        
        function getBadgeColor(status) {
            const colors = {
                'Created': '#6c757d',
                'Pending': '#ffc107',
                'Received': '#1976d2',
                'Checking Documents': '#f57c00',
                'Waiting For Approval by Mayor': '#5e35b1',
                'Completed': '#28a745',
                'Forwarded': '#ff9500'
            };
            return colors[status] || '#6c757d';
        }
        
        function formatDatetime(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                };
                return date.toLocaleString('en-US', options);
            } catch (e) {
                return dateString;
            }
        }
        
        function htmlEscape(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAuditTrail();
            }
        });
        
        // Close modal on overlay click
        document.getElementById('auditTrailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAuditTrail();
            }
        });
    </script>
</body>
</html>
