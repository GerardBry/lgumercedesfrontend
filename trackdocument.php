<?php
/**
 * Track Documents Page - Department Staff
 * Search documents by tracking code and view complete audit trail
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// STRICT ROLE-BASED ACCESS CONTROL - Only regular users allowed
if (isset($_SESSION['role'])) {
    // Block Super Admin
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: admin/admin-dashboard.php');
        exit;
    }
    // Block Administrative Assistant
    if ($_SESSION['role'] === 'Administrative Assistant') {
        header('Location: administrative/admin-dashboard-staff.php');
        exit;
    }
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

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

function isValidTrackDate($value) {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return false;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false && $timestamp > 0;
}

function formatTrackDate($primary, $fallbacks = [], $format = 'M d, Y h:i A') {
    $candidates = array_merge([$primary], $fallbacks);

    foreach ($candidates as $candidate) {
        if (isValidTrackDate($candidate)) {
            return date($format, strtotime($candidate));
        }
    }

    return '-';
}

// Fetch full user details from database
require_once 'config/db_connect.php';

$user_details = null;
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_details = $result->fetch_assoc();
    }
    $stmt->close();
}

// Tracking search functionality
$tracking_code = isset($_GET['tracking_code']) ? trim($_GET['tracking_code']) : '';
$search_performed = $tracking_code !== '';
$results = [];

$accessCondition = "(
      d.created_by = ?
      OR d.sender_id = ?
      OR EXISTS (
        SELECT 1
        FROM document_assignments da_access
        WHERE da_access.document_id = d.id
          AND (da_access.assigned_by = ? OR da_access.assigned_to = ?)
      )
)";

$latestRowCondition = "NOT EXISTS (
      SELECT 1
      FROM documents d2
      WHERE d2.tracking_number = d.tracking_number
        AND (
            d2.created_by = ?
            OR d2.sender_id = ?
            OR EXISTS (
              SELECT 1
              FROM document_assignments da_access2
              WHERE da_access2.document_id = d2.id
                AND (da_access2.assigned_by = ? OR da_access2.assigned_to = ?)
            )
        )
        AND (
            d2.date_sent > d.date_sent
            OR (d2.date_sent = d.date_sent AND d2.id > d.id)
        )
)";

$sql_track = "SELECT
        d.id as document_id,
        d.tracking_number,
        d.title,
        d.description,
        d.sender_name,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM document_assignments da_returned
                JOIN documents d_returned ON d_returned.id = da_returned.document_id
                WHERE d_returned.tracking_number = d.tracking_number
                  AND da_returned.status = 'Returned'
            ) THEN 'Returned'
            WHEN EXISTS (
                SELECT 1
                FROM document_assignments da_done
                JOIN documents d_done ON d_done.id = da_done.document_id
                WHERE d_done.tracking_number = d.tracking_number
                  AND da_done.status = 'Completed'
            ) THEN 'Completed'
            ELSE d.status
        END as document_status,
        d.document_type,
        d.date_sent,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        sender.position as sender_position,
        COALESCE(NULLIF(TRIM(d.sender_name), ''), NULLIF(TRIM(CONCAT(sender.first_name, ' ', sender.last_name)), ''), '-') as sender_display
    FROM documents d
    LEFT JOIN users sender ON d.sender_id = sender.id
        WHERE " . $accessCondition . "
            AND COALESCE(d.document_type, '') <> 'Travel Request'
            AND " . $latestRowCondition;

if ($search_performed) {
    $sql_track .= " AND d.tracking_number = ?";
}

$sql_track .= " ORDER BY d.date_sent DESC, d.id DESC";

$stmt_track = $conn->prepare($sql_track);
if ($stmt_track) {
    if ($search_performed) {
        $stmt_track->bind_param("iiiiiiiis", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $tracking_code);
    } else {
        $stmt_track->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    }
    $stmt_track->execute();
    $result_track = $stmt_track->get_result();
    while ($row = $result_track->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt_track->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h1>LGU Mercedes</h1>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="index.php" class="nav-item" data-page="dashboard">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="trackdocument.php" class="nav-item" data-page="track">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                                        <li>    
                        <a href="documententry.php" class="nav-item" data-page="entry">
                            <i class="fas fa-file-upload"></i>
                            <span>Incoming</span>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="nav-item" data-page="outgoing">
                            <i class="fas fa-paper-plane"></i>
                            <span>Outgoing</span>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="nav-item" data-page="received">
                            <i class="fas fa-envelope-open"></i>
                            <span>Approved</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                                       <li>
                        <a href="incoming.php" class="nav-item" data-page="incoming">
                            <div>
                                <i class="fas fa-inbox"></i>
                                <span>Returned</span>
                            </div>
                        </a>

                                        <li>
                        <a href="reports.php" class="nav-item" data-page="reports">
                            <div>
                                <i class="fas fa-chart-pie"></i>
                                <span>Reports</span>
                            </div>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="profile.php" class="nav-item" data-page="profile">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="user-name" id="userNameDisplay"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="handleLogout()" style="width: 100%; margin-top: 12px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header with Notifications -->
            <div style="padding: 15px 30px; border-bottom: 1px solid #eee; display: flex; justify-content: flex-end; align-items: center; background: white; position: relative; z-index: 10;">
                <div class="header-right" style="display: flex; gap: 16px; align-items: center; position: relative;">
                    <!-- Notification Bell will be inserted here by notifications.js -->
                </div>
            </div>
            <div class="page active">
                <div class="page-header">
                    <div class="header-with-button">
                        <div>
                            <h2>Track Documents</h2>
                            <p>Browse all routed documents and filter by tracking code</p>
                        </div>
                    </div>
                </div>

                <div class="search-section">
                    <form class="search-box" method="GET" action="trackdocument.php" style="display: flex; gap: 12px;">
                        <div style="flex: 1; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light);"></i>
                            <input 
                                type="text" 
                                name="tracking_code"
                                placeholder="Enter tracking code"
                                style="width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px;"
                                value="<?php echo htmlspecialchars($tracking_code); ?>"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Track
                        </button>
                    </form>
                            <div style="margin-top: 10px; font-size: 13px; color: var(--text-light);">
                                Tracking code filters the list below. All documents you created or routed to are shown by default.
                            </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Subject/Title</th>
                                <th>Sender</th>
                                <th>Description</th>
                                <th>Date Received</th>
                                <th>Classification</th>
                                <th>Sub-Classification</th>
                                <th>Prioritization</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) === 0): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                        <?php echo $search_performed ? 'No record found for the tracking code you entered' : 'No documents available'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                        $status = $row['document_status'] ?? 'Pending';
                                        $badge_class = 'badge-warning';
                                        if ($status === 'Returned') {
                                            $badge_class = 'badge-danger';
                                        } elseif ($status === 'Approved') {
                                            $badge_class = 'badge-info';
                                        } elseif ($status === 'Completed') {
                                            $badge_class = 'badge-success';
                                        } elseif ($status === 'Rejected') {
                                            $badge_class = 'badge-danger';
                                        } elseif ($status === 'Archived') {
                                            $badge_class = 'badge-secondary';
                                        }

                                        $priority = $row['priority'] ?? 'Normal';
                                        $priority_class = 'badge-primary';
                                        if ($priority === 'Urgent') {
                                            $priority_class = 'badge-warning';
                                        } elseif ($priority === 'Critical') {
                                            $priority_class = 'badge-danger';
                                        }

                                        $sender_display = trim(($row['sender_display'] ?? '') ?: (($row['sender_first_name'] ?? '') . ' ' . ($row['sender_last_name'] ?? '')));
                                        if ($sender_display === '') {
                                            $sender_display = $row['sender_name'] ?? '-';
                                        }

                                        $description = trim((string)($row['description'] ?? ''));
                                        if ($description === '') {
                                            $description = '-';
                                        } elseif (mb_strlen($description) > 90) {
                                            $description = mb_substr($description, 0, 90) . '...';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['tracking_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($sender_display ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($description); ?></td>
                                        <td><?php echo htmlspecialchars(formatTrackDate($row['date_received'] ?? null, [$row['date_sent'] ?? null])); ?></td>
                                        <td><?php echo !empty($row['classification']) ? '<span class="badge badge-info">' . htmlspecialchars($row['classification']) . '</span>' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($row['sub_classification'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span></td>
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
        </main>
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

    <script src="script.js"></script>
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
            fetch('administrative/track-handler.php?tracking_code=' + encodeURIComponent(trackingCode))
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
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; font-size: 14px;">';
                    html += '<div><strong style="opacity: 0.9;">Tracking Code:</strong> <strong>' + htmlEscape(data.document.tracking_number) + '</strong></div>';
                    html += '<div><strong style="opacity: 0.9;">Sender:</strong> ' + htmlEscape(data.document.sender_name || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Date Received:</strong> ' + formatDatetime(data.document.date_received, data.document.date_sent) + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Classification:</strong> ' + htmlEscape(data.document.classification || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Sub-Classification:</strong> ' + htmlEscape(data.document.sub_classification || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Prioritization:</strong> ' + htmlEscape(data.document.priority || 'Normal') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Created by:</strong> ' + htmlEscape(data.document.created_by) + ' on ' + formatDatetime(data.document.created_at) + '</div>';
                    html += '</div>';
                    if (data.document.description) {
                        html += '<div style="margin-top: 14px; font-size: 14px;"><strong style="opacity: 0.9;">Description:</strong> ' + htmlEscape(data.document.description) + '</div>';
                    }
                    html += '</div>';
                    
                    // Timeline Events
                    html += '<div style="position: relative; padding: 0 0 0 40px;">';
                    html += '<div style="position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, var(--primary-color), var(--primary-light));"></div>';
                    
                    if (data.timeline && data.timeline.length > 0) {
                        data.timeline.forEach((event, index) => {
                            let bulletColor = '#6c757d';
                            if (event.event_type === 'completed') bulletColor = '#28a745';
                            else if (event.event_type === 'uploaded') bulletColor = '#009688';
                            else if (event.event_type === 'travel_request') bulletColor = '#1565c0';
                            else if (event.event_type === 'approved') bulletColor = '#2e7d32';
                            else if (event.event_type === 'received') bulletColor = '#1976d2';
                            else if (event.event_type === 'returned') bulletColor = '#d32f2f';
                            else if (event.event_type === 'routed') bulletColor = 'var(--primary-color)';
                            else if (event.event_type === 'created') bulletColor = '#6c757d';
                            
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
                'routed': 'route',
                'received': 'check-circle',
                'returned': 'undo',
                'approved': 'user-check',
                'travel_request': 'plane',
                'uploaded': 'file-upload',
                'completed': 'check-double',
                'forwarded': 'arrow-right'
            };
            return icons[eventType] || 'circle';
        }
        
        function getBadgeColor(status) {
            const colors = {
                'Submitted': '#6c757d',
                'Routed': 'var(--primary-color)',
                'Received': '#1976d2',
                'Returned': '#d32f2f',
                'Approved': '#2e7d32',
                'Travel Request': '#1565c0',
                'Uploaded': '#009688',
                'Completed': '#28a745',
                'Forwarded': '#ff9500'
            };
            return colors[status] || '#6c757d';
        }
        
        function formatDatetime(...dateStrings) {
            for (const dateString of dateStrings) {
                if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') {
                    continue;
                }

                const date = new Date(dateString);
                if (Number.isNaN(date.getTime())) {
                    continue;
                }

                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            return '-';
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
    <script src="js/notifications.js"></script>
</body>
