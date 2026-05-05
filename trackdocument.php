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

if ($search_performed) {
    $sql_track = "SELECT
            da.id as assignment_id,
            d.id as document_id,
            d.tracking_number,
            d.title,
                        (
                                SELECT d_seed.description
                                FROM document_assignments da_seed
                                JOIN documents d_seed ON d_seed.id = da_seed.document_id
                                WHERE d_seed.tracking_number = d.tracking_number
                                    AND da_seed.assigned_to = ?
                                    AND da_seed.assigned_by <> ?
                                    AND COALESCE(TRIM(d_seed.description), '') <> ''
                                ORDER BY da_seed.assigned_at ASC, da_seed.id ASC
                                LIMIT 1
                        ) as description,
                        (
                                SELECT da_seed.notes
                                FROM document_assignments da_seed
                                JOIN documents d_seed ON d_seed.id = da_seed.document_id
                                WHERE d_seed.tracking_number = d.tracking_number
                                    AND da_seed.assigned_to = ?
                                    AND da_seed.assigned_by <> ?
                                    AND COALESCE(TRIM(da_seed.notes), '') <> ''
                                ORDER BY da_seed.assigned_at ASC, da_seed.id ASC
                                LIMIT 1
                        ) as notes_instructions,
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
          AND (da.assigned_by = ? OR da.assigned_to = ?)
                ORDER BY COALESCE(da.completed_at, da.received_at, da.assigned_at, d.date_sent) DESC, da.id DESC
                LIMIT 1";

    $stmt_track = $conn->prepare($sql_track);
    if ($stmt_track) {
        $stmt_track->bind_param("iiiisii", $user_id, $user_id, $user_id, $user_id, $tracking_code, $user_id, $user_id);
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
                        <a href="trackdocument.php" class="nav-item active" data-page="track">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="documententry.php" class="nav-item" data-page="entry">
                            <i class="fas fa-file-upload"></i>
                            <span>Document Entry</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="incoming.php" class="nav-item" data-page="incoming">
                            <i class="fas fa-inbox"></i>
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
                            <span>Received</span>
                        </a>
                    </li>
                    <li>
                        <a href="returned.php" class="nav-item" data-page="returned">
                            <i class="fas fa-undo"></i>
                            <span>Returned</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="nav-item" data-page="archive">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
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
                            <p>Search document status using tracking code</p>
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
                                placeholder="Enter tracking code (e.g., LGU-2026-04-23-913)"
                                style="width: 100%; padding: 12px 12px 12px 40px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px;"
                                value="<?php echo htmlspecialchars($tracking_code); ?>"
                                required
                            >
                        </div>
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
                                <th>Notes/Instructions</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$search_performed): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">Enter a tracking code to search</td>
                                </tr>
                            <?php elseif (count($results) === 0): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No record found for the tracking code you entered</td>
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
                                        } elseif ($status === 'Waiting For Approval by Mayor') {
                                            $badge_class = 'badge-info';
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
                                        <td><?php echo htmlspecialchars(formatNotesForDisplay($row['notes_instructions'] ?? '')); ?></td>
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
    <script src="js/notifications.js"></script>
</body>
