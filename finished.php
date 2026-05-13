<?php
/**
 * Finished Documents Page - Requires Authentication
 * Regular user only (blocks Super Admin and Administrative Assistant)
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
$office_department = $_SESSION['office_department'] ?? 'N/A';

// Helper function to parse and format JSON notes
function formatNotes($notesJson) {
    if (empty($notesJson)) {
        return '-';
    }
    
    // Try to decode JSON
    $decoded = json_decode($notesJson, true);
    if (is_array($decoded)) {
        // Extract key info from JSON
        $parts = [];
        if (!empty($decoded['title'])) {
            $parts[] = 'Title: ' . htmlspecialchars($decoded['title']);
        }
        if (!empty($decoded['purpose'])) {
            $parts[] = 'Purpose: ' . htmlspecialchars($decoded['purpose']);
        }
        if (!empty($decoded['subject'])) {
            $parts[] = 'Subject: ' . htmlspecialchars($decoded['subject']);
        }
        if (!empty($decoded['type'])) {
            $parts[] = 'Type: ' . htmlspecialchars($decoded['type']);
        }
        
        return !empty($parts) ? implode(' | ', $parts) : htmlspecialchars($notesJson);
    }
    
    // If not JSON, return as-is
    return htmlspecialchars($notesJson);
}

function isValidDateValue($value) {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return false;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false && $timestamp > 0;
}

function formatFinishedDate($primary, $fallbacks = [], $format = 'M d, Y') {
    $candidates = array_merge([$primary], $fallbacks);

    foreach ($candidates as $candidate) {
        if (isValidDateValue($candidate)) {
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
        // Update office_department from database if session doesn't have it
        if (empty($_SESSION['office_department']) && !empty($user_details['office_department'])) {
            $office_department = $user_details['office_department'];
        }
    }
    $stmt->close();
}

// Fetch finished/completed documents using the same final-status logic as reports.php.
// We still expose an assignment_id so the existing view modal can resolve the completed assignment.
$received_documents = [];
$latest_documents_sql = "SELECT tracking_number, MAX(id) AS latest_id FROM documents WHERE document_type <> 'Travel Request' GROUP BY tracking_number";
$final_status_expression = "CASE
    WHEN EXISTS (SELECT 1 FROM document_assignments da_returned WHERE da_returned.document_id = d.id AND da_returned.status = 'Returned') THEN 'Returned'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_completed WHERE da_completed.document_id = d.id AND da_completed.status = 'Completed') THEN 'Completed'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_approved WHERE da_approved.document_id = d.id AND da_approved.status = 'Approved') THEN 'Approved'
    ELSE d.status
END";

$sql = "SELECT
        COALESCE((SELECT da_latest.id FROM document_assignments da_latest WHERE da_latest.document_id = d.id AND da_latest.status = 'Completed' ORDER BY da_latest.completed_at DESC, da_latest.id DESC LIMIT 1), d.id) AS assignment_id,
        d.id AS document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) AS description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.date_received,
        d.notes AS doc_notes,
        u_sender.first_name AS sender_first_name,
        u_sender.last_name AS sender_last_name,
        COALESCE(NULLIF(u_sender.office_department, ''), d.office_department, 'Unknown Office') AS office_department,
        COALESCE(NULLIF(TRIM(d.sender_name), ''), NULLIF(TRIM(CONCAT_WS(' ', u_sender.first_name, u_sender.last_name)), ''), 'Unknown Sender') AS sender_name,
        d.classification,
        d.sub_classification,
        d.priority,
        d.file_path,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) AS assignment_notes,
        'Completed' AS assignment_status,
        (SELECT da_latest.assigned_at FROM document_assignments da_latest WHERE da_latest.document_id = d.id AND da_latest.status = 'Completed' ORDER BY da_latest.completed_at DESC, da_latest.id DESC LIMIT 1) AS assigned_at,
        (SELECT da_latest.received_at FROM document_assignments da_latest WHERE da_latest.document_id = d.id AND da_latest.status = 'Completed' ORDER BY da_latest.completed_at DESC, da_latest.id DESC LIMIT 1) AS received_at,
        (SELECT da_latest.completed_at FROM document_assignments da_latest WHERE da_latest.document_id = d.id AND da_latest.status = 'Completed' ORDER BY da_latest.completed_at DESC, da_latest.id DESC LIMIT 1) AS completed_at
    FROM documents d
    INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE ($final_status_expression) = 'Completed'";

// If the current user is Department Staff, restrict finished documents
// to only those where the completed assignment was assigned to/by this user
// or documents they created. This prevents staff from seeing other
// offices' finished transactions.
if ($role === 'Department Staff') {
    $sql .= " AND (
        EXISTS (SELECT 1 FROM document_assignments da_latest WHERE da_latest.document_id = d.id AND da_latest.status = 'Completed' AND (da_latest.assigned_to = ? OR da_latest.assigned_by = ?))
        OR d.created_by = ?
    )";
}

// finish ORDER BY
$sql .= "\n    ORDER BY completed_at DESC, assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($role === 'Department Staff') {
        $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $received_documents[] = $row;
    }
    $stmt->close();
}

// Debug: log how many finished documents were fetched for this user
error_log("finished.php: fetched " . count($received_documents) . " rows for user_id=" . intval($user_id) . " office='" . addslashes($office_department) . "'");

$conn->close();
?>
    <title>Finished Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .btn-info:hover {
            background-color: #bbdefb;
        }
    </style>
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
                            <i class="fas fa-inbox"></i>
                            <span>Returned</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="nav-item" data-page="reports">
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
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
                            <h2>Finished Documents</h2>
                            <p>Documents that have been completed and finished</p>
                        </div>
                    </div>
                </div>

                <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-md);">
                    <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 16px; font-weight: 600;">Filters</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Search Keyword</label>
                            <input type="text" id="keywordFilter" placeholder="Search title, sender, description..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Sender</label>
                            <input type="text" id="senderFilter" placeholder="Filter by sender..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Prioritization</label>
                            <select id="priorityFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                                <option value="">All Priorities</option>
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date From</label>
                            <input type="date" id="dateFromFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date To</label>
                            <input type="date" id="dateToFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button onclick="clearFilters()" style="width: 100%; padding: 10px 12px; background-color: #e0e0e0; border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-dark); transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#d0d0d0'" onmouseout="this.style.backgroundColor='#e0e0e0'">
                                <i class="fas fa-redo"></i> Clear Filters
                            </button>
                        </div>
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
                        <tbody id="documentsTableBody">
                            <?php if (count($received_documents) > 0): ?>
                                <?php foreach ($received_documents as $doc): ?>
                                    <?php
                                        $senderName = trim($doc['sender_name'] ?? '');
                                        if ($senderName === '') {
                                            $senderName = trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        }
                                        if ($senderName === '') {
                                            $senderName = '-';
                                        }
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $description = $doc['description'] ?? '';
                                        $priorityValue = $doc['priority'] ?? 'Normal';
                                        $displayDate = formatFinishedDate($doc['date_received'] ?? null, [$doc['received_at'] ?? null, $doc['completed_at'] ?? null]);
                                        $filterDate = isValidDateValue($doc['date_received'] ?? null)
                                            ? date('Y-m-d', strtotime($doc['date_received']))
                                            : (isValidDateValue($doc['received_at'] ?? null)
                                                ? date('Y-m-d', strtotime($doc['received_at']))
                                                : (isValidDateValue($doc['completed_at'] ?? null)
                                                    ? date('Y-m-d', strtotime($doc['completed_at']))
                                                    : ''));
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($description ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td><strong><?php echo htmlspecialchars($trackingCode); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo substr(htmlspecialchars($description), 0, 50) . (strlen($description) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars($displayDate); ?></td>
                                        <td><?php echo !empty($doc['classification']) ? '<span class="badge badge-info">' . htmlspecialchars($doc['classification']) . '</span>' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($doc['sub_classification'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                                $priority = $priorityValue;
                                                $priority_class = 'badge-primary';
                                                if ($priority === 'Urgent') $priority_class = 'badge-warning';
                                                elseif ($priority === 'Critical') $priority_class = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">Completed</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReceivedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">No finished documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">No finished documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Received Document Modal -->
    <div id="viewReceivedModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeReceivedModal()">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeReceivedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600; border-bottom: 3px solid var(--primary-color); padding-bottom: 8px;">Document Information</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Document ID</label>
                        <span style="font-size: 14px; font-weight: 600; color: #333;" id="viewDocumentID">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Subject/Title</label>
                        <span style="font-size: 14px; color: #333;" id="viewTitle">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sender</label>
                        <span style="font-size: 14px; color: #333;" id="viewSender">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Date Received</label>
                        <span style="font-size: 14px; color: #333;" id="viewDateReceived">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Classification</label>
                        <span id="viewClassification" style="display: inline-block;"><span class="badge badge-info">-</span></span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sub-Classification</label>
                        <span style="font-size: 14px; color: #333;" id="viewSubClassification">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Prioritization</label>
                        <span id="viewPrioritization" style="display: inline-block;"><span class="badge badge-primary">-</span></span>
                    </div>
                </div>

                <div class="detail-row" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Description</label>
                    <span style="font-size: 14px; color: #333; display: block; line-height: 1.6;" id="viewDescription">-</span>
                </div>

                <div class="detail-row" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Created Date</label>
                    <span style="font-size: 14px; color: #333;" id="viewCreatedDate">-</span>
                </div>

                <div class="detail-row" style="margin-top: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 14px; color: #333; flex: 1;" id="viewFileName">No attachment</span>
                        <button type="button" class="btn btn-sm btn-warning" onclick="viewReceivedFile()" id="viewFileBtn" style="display: none;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="downloadReceivedDocument()" id="downloadReceivedBtn" style="display: none;">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>

                <!-- Travel Request Submissions Section -->
                <div id="travelRequestsSection" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--primary-color);">
                    <h4 style="margin-bottom: 16px; font-size: 14px; font-weight: 600; color: var(--primary-color);">
                        <i class="fas fa-plane"></i> Submitted Travel Requests
                    </h4>
                    <div id="travelRequestsList" style="display: flex; flex-direction: column; gap: 16px;"></div>
                </div>

                <!-- Uploaded Files/Pictures Section -->
                <div id="uploadedFilesSection" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #28a745;">
                    <h4 style="margin-bottom: 16px; font-size: 14px; font-weight: 600; color: #28a745;">
                        <i class="fas fa-file-upload"></i> Uploaded Pictures/Files
                    </h4>
                    <div id="uploadedFilesList" style="display: flex; flex-direction: column; gap: 12px;"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="addDocumentBtn" onclick="openAddRelatedDocumentModal()">
                    <i class="fas fa-plus"></i> Add Document
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeReceivedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Related Document Modal -->
    <div id="addRelatedDocumentModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeAddRelatedDocumentModal()">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Add Related Document</h3>
                <button class="modal-close" onclick="closeAddRelatedDocumentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="addRelatedDocumentForm">
                <div class="modal-body">
                    <!-- Hidden Fields -->
                    <input type="hidden" id="parentDocumentId" name="parent_document_id">
                    <input type="hidden" id="docSequenceNumber" name="doc_sequence_number" value="1">
                    <input type="hidden" id="relatedClassification" name="classification">
                    <input type="hidden" id="relatedSubClassification" name="sub_classification">
                    <input type="hidden" id="relatedDocumentType" name="document_type">
                    <input type="hidden" id="relatedPriority" name="priority" value="Normal">

                    <!-- Travel Request Form Template -->
                    <div class="travel-request-form">
                        <div class="form-section">
                            <h4 class="section-title">REQUEST FOR TRAVEL AND OFFICE ORDERS</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="travelOfficeName">NAME OF OFFICE <span style="color: red;">*</span></label>
                                    <div id="travelOfficeName" style="padding: 6px 8px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; color: #333; font-weight: 500;"></div>
                                    <input type="hidden" id="officeName" name="officer_name">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>TYPE OF ORDER <span style="color: red;">*</span></label>
                                    <div style="display: flex; gap: 16px; margin-top: 6px;">
                                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; font-size: 12px;">
                                            <input type="radio" name="order_type" value="Travel Order" required checked>
                                            Travel Order
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>PURPOSE OF ORDER <span style="color: red;">*</span></label>
                                    <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 6px;">
                                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; font-size: 12px;">
                                            <input type="radio" name="purpose_of_order" value="Meeting" required>
                                            Meeting
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; font-size: 12px;">
                                            <input type="radio" name="purpose_of_order" value="Conduct" required>
                                            Conduct
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; font-size: 12px;">
                                            <input type="radio" name="purpose_of_order" value="Others" required>
                                            Others, please specify
                                        </label>
                                        <input type="text" id="purposeSpecify" name="purpose_specify" placeholder="Please specify" style="margin-top: 4px; display: none; font-size: 12px; padding: 4px 6px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">NAMES INVOLVED IN THE EVENT/ACTIVITY</h4>
                            
                            <div id="travelersContainer" style="margin-bottom: 10px;">
                                <div class="traveler-row" style="display: grid; grid-template-columns: 30px 1fr 1fr; gap: 8px; margin-bottom: 8px; align-items: flex-end;">
                                    <div style="font-weight: 600; color: #666; font-size: 11px; text-align: center;">No.</div>
                                    <div style="font-weight: 600; color: #666; font-size: 11px;">NAME</div>
                                    <div style="font-weight: 600; color: #666; font-size: 11px;">POSITION</div>
                                </div>
                                
                                <div id="travelersList"></div>
                            </div>

                            <button type="button" class="btn btn-sm btn-primary" onclick="addTravelerRow()" style="margin-bottom: 10px; font-size: 12px; padding: 4px 8px;">
                                <i class="fas fa-plus"></i> Add Traveler
                            </button>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">EVENT/ACTIVITY DETAILS</h4>
                            
                            <div class="form-group">
                                <label for="travelEventTitle">TITLE OF EVENT/ACTIVITY <span style="color: red;">*</span></label>
                                <input type="text" id="travelEventTitle" name="event_title" required placeholder="Enter event/activity title">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="travelEventDate">DATE OF EVENT/ACTIVITY <span style="color: red;">*</span></label>
                                    <input type="date" id="travelEventDate" name="event_date" required>
                                </div>

                                <div class="form-group">
                                    <label for="travelEventPlace">PLACE OF EVENT/ACTIVITY <span style="color: red;">*</span></label>
                                    <input type="text" id="travelEventPlace" name="event_place" required placeholder="Enter location/venue">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="travelEventDescription">DESCRIPTION/DETAILS</label>
                                <textarea id="travelEventDescription" name="event_description" rows="3" placeholder="Enter event/activity description or details..."></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="section-title">APPROVAL</h4>
                            
                            <div class="form-group">
                                <label for="notedBy">NOTED BY (Name & Position) <span style="color: red;">*</span></label>
                                <input type="text" id="notedBy" name="noted_by" required placeholder="Name and position">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 12px 20px; gap: 8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddRelatedDocumentModal()" style="font-size: 12px; padding: 6px 12px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="previewTravelRequest()" style="font-size: 12px; padding: 6px 12px;">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Travel Request Preview Modal -->
    <div id="travelRequestPreviewModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);">
        <div class="modal-content" style="max-width: 8.5in; max-height: 90vh; overflow-y: auto; margin: 20px auto; background: white; font-family: Arial, sans-serif;">
            <div class="modal-header" style="padding: 0; border: none;">
                <button class="modal-close" onclick="closePreviewModal()" style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="printableFormContent" style="padding: 40px; min-height: 11in; background: white; color: #333;">
                <!-- Form content will be generated here -->
            </div>
            <div class="modal-footer" style="padding: 12px 20px; gap: 8px; border-top: 1px solid #ddd;">
                <button type="button" class="btn btn-secondary" onclick="closePreviewModal()" style="font-size: 12px; padding: 6px 12px;">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-info" onclick="printTravelRequest()" style="font-size: 12px; padding: 6px 12px;">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-success" onclick="submitTravelRequest()" style="font-size: 12px; padding: 6px 12px;">
                    <i class="fas fa-check"></i> Submit
                </button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileViewerModal" class="modal">
        <div class="modal-content" style="max-width: 60vw; max-height: 90vh; overflow: auto;">
            <div class="modal-header">
                <h3 id="fileViewerTitle">File Viewer</h3>
                <button class="modal-close" onclick="closeFileViewerModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <img id="fileViewerImage" src="" style="max-width: 100%; max-height: calc(90vh - 150px); object-fit: contain;" alt="Document Image">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="downloadReceivedDocument()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function applyFilters() {
            const keyword = (document.getElementById('keywordFilter')?.value || '').toLowerCase();
            const sender = (document.getElementById('senderFilter')?.value || '').toLowerCase();
            const priority = document.getElementById('priorityFilter')?.value || '';
            const dateFromValue = document.getElementById('dateFromFilter')?.value || '';
            const dateToValue = document.getElementById('dateToFilter')?.value || '';
            const dateFrom = dateFromValue ? new Date(dateFromValue) : null;
            const dateTo = dateToValue ? new Date(dateToValue) : null;

            const rows = document.querySelectorAll('#documentsTableBody tr[data-filter-row="true"]');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowKeywords = row.dataset.keywords || '';
                const rowSender = row.dataset.sender || '';
                const rowPriority = row.dataset.priority || '';
                const rowDateValue = row.dataset.date || '';
                const rowDate = rowDateValue ? new Date(rowDateValue) : null;

                if (keyword && !rowKeywords.includes(keyword)) {
                    row.style.display = 'none';
                    return;
                }

                if (sender && !rowSender.includes(sender)) {
                    row.style.display = 'none';
                    return;
                }

                if (priority && rowPriority !== priority) {
                    row.style.display = 'none';
                    return;
                }

                if (dateFrom && (!rowDate || rowDate < dateFrom)) {
                    row.style.display = 'none';
                    return;
                }

                if (dateTo && rowDate) {
                    const dateToEnd = new Date(dateTo);
                    dateToEnd.setDate(dateToEnd.getDate() + 1);
                    if (rowDate >= dateToEnd) {
                        row.style.display = 'none';
                        return;
                    }
                } else if (dateTo && !rowDate) {
                    row.style.display = 'none';
                    return;
                }

                row.style.display = '';
                visibleCount += 1;
            });

            const emptyRow = document.getElementById('emptyFilterRow');
            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        function clearFilters() {
            const keywordInput = document.getElementById('keywordFilter');
            const senderInput = document.getElementById('senderFilter');
            const prioritySelect = document.getElementById('priorityFilter');
            const dateFromInput = document.getElementById('dateFromFilter');
            const dateToInput = document.getElementById('dateToFilter');

            if (keywordInput) keywordInput.value = '';
            if (senderInput) senderInput.value = '';
            if (prioritySelect) prioritySelect.value = '';
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            applyFilters();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('keywordFilter')?.addEventListener('input', applyFilters);
            document.getElementById('senderFilter')?.addEventListener('input', applyFilters);
            document.getElementById('priorityFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateFromFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateToFilter')?.addEventListener('change', applyFilters);
            applyFilters();
        });

        let currentReceivedDocument = null;
        let userOffice = <?php echo json_encode($office_department); ?>;

        function safeParseJson(text) {
            try {
                return JSON.parse(text || '{}');
            } catch (e) {
                return {};
            }
        }

        function escapeText(value) {
            if (typeof htmlEscape === 'function') {
                return htmlEscape(value);
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function isValidDateValue(value) {
            if (!value || value === '0000-00-00' || value === '0000-00-00 00:00:00') {
                return false;
            }

            const parsed = new Date(value);
            return !Number.isNaN(parsed.getTime());
        }

        function formatFinishedModalDate(...candidates) {
            for (const candidate of candidates) {
                if (isValidDateValue(candidate)) {
                    return candidate;
                }
            }

            return '';
        }

        // Sub-classification mappings
        const subClassificationMap = {
            'Letter': ['Official Letter', 'Personal Letter', 'Formal Letter'],
            'Invitation': ['Conference Invitation', 'Event Invitation', 'Meeting Invitation'],
            'Travel-Related Communication': ['Travel Approval', 'Travel Itinerary', 'Travel Expense Report'],
            'Indorsement': ['Endorsement Letter', 'Referral Letter', 'Recommendation Letter']
        };

        function getAutoDocumentFormat(classification) {
            // If classification is Letter, Invitation, or Travel-Related Communication, return "Travel Request"
            if (classification === 'Letter' || classification === 'Invitation' || classification === 'Travel-Related Communication') {
                return 'Travel Request';
            }
            // Default to Travel Request
            return 'Travel Request';
        }

        function openAddRelatedDocumentModal() {
            if (!currentReceivedDocument) {
                alert('Please load a document first');
                return;
            }

            // Store parent document ID
            document.getElementById('parentDocumentId').value = currentReceivedDocument.document_id || currentReceivedDocument.id || '';

            // Auto-set the document format based on the parent document's classification
            const autoFormat = getAutoDocumentFormat(currentReceivedDocument.classification);
            document.getElementById('relatedDocumentType').value = autoFormat;
            document.getElementById('relatedClassification').value = currentReceivedDocument.classification || '';
            document.getElementById('relatedSubClassification').value = currentReceivedDocument.sub_classification || '';
            document.getElementById('relatedPriority').value = currentReceivedDocument.priority || 'Normal';

            // Reset form fields
            document.getElementById('addRelatedDocumentForm').reset();
            
            // Initialize travelers container
            initializeTravelersContainer();

            // Set office name (auto-populated from user account)
            document.getElementById('travelOfficeName').textContent = userOffice || 'N/A';
            document.getElementById('officeName').value = userOffice || 'N/A';

            // Re-set hidden fields after reset
            document.getElementById('relatedDocumentType').value = autoFormat;
            document.getElementById('relatedClassification').value = currentReceivedDocument.classification || '';
            document.getElementById('relatedSubClassification').value = currentReceivedDocument.sub_classification || '';
            document.getElementById('relatedPriority').value = currentReceivedDocument.priority || 'Normal';
            document.getElementById('parentDocumentId').value = currentReceivedDocument.document_id || currentReceivedDocument.id || '';

            // Open modal
            document.getElementById('addRelatedDocumentModal').classList.add('active');
        }

        function closeAddRelatedDocumentModal() {
            document.getElementById('addRelatedDocumentModal').classList.remove('active');
        }

        function initializeTravelersContainer() {
            const container = document.getElementById('travelersList');
            container.innerHTML = '';
            // Add one empty row
            addTravelerRow();
        }

        function addTravelerRow() {
            const container = document.getElementById('travelersList');
            const rowCount = container.querySelectorAll('.traveler-row').length + 1;
            
            if (rowCount > 10) {
                alert('Maximum 10 travelers allowed');
                return;
            }

            const row = document.createElement('div');
            row.className = 'traveler-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = '30px 1fr 1fr 35px';
            row.style.gap = '8px';
            row.style.marginBottom = '8px';
            row.style.alignItems = 'flex-end';

            row.innerHTML = `
                <div style="font-weight: 600; color: #666; text-align: center; font-size: 12px;">${rowCount}</div>
                <input type="text" name="traveler_name_${rowCount}" placeholder="Name" class="traveler-name" style="padding: 4px 6px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 12px;">
                <input type="text" name="traveler_position_${rowCount}" placeholder="Position" class="traveler-position" style="padding: 4px 6px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 12px;">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeTravelerRow(this)" style="padding: 4px 6px; font-size: 11px;">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            row.style.gridTemplateColumns = '30px 1fr 1fr 35px';

            container.appendChild(row);
        }

        function removeTravelerRow(btn) {
            btn.parentElement.remove();
            // Re-number rows
            const container = document.getElementById('travelersList');
            const rows = container.querySelectorAll('.traveler-row');
            rows.forEach((row, index) => {
                const numDiv = row.querySelector('div:first-child');
                numDiv.textContent = index + 1;
            });
        }

        // Handle purpose "Others" checkbox
        document.addEventListener('change', function(e) {
            if (e.target.name === 'purpose_of_order') {
                const purposeSpecifyInput = document.getElementById('purposeSpecify');
                if (e.target.value === 'Others') {
                    purposeSpecifyInput.style.display = 'block';
                } else {
                    purposeSpecifyInput.style.display = 'none';
                    purposeSpecifyInput.value = '';
                }
            }
        });

        function updateRelatedSubClassification() {
            const classification = document.getElementById('relatedClassification').value;
            const subClassSelect = document.getElementById('relatedSubClassification');
            
            subClassSelect.innerHTML = '<option value="">Select Sub-Classification</option>';
            
            if (classification && subClassificationMap[classification]) {
                subClassificationMap[classification].forEach(subClass => {
                    const option = document.createElement('option');
                    option.value = subClass;
                    option.textContent = subClass;
                    subClassSelect.appendChild(option);
                });
            }
        }

        // File upload area handlers
        function setupFileUploadHandlers() {
            // File upload handlers removed - no file attachment needed
        }

        function cleanHTML(str) {
            if (!str) return '';
            return String(str).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        }

        // Build travel request JSON from form data
        function buildTravelRequestJSON(formData) {
            const travelers = [];
            
            // Collect travelers
            for (let i = 1; i <= 10; i++) {
                const name = formData.get(`traveler_name_${i}`);
                const position = formData.get(`traveler_position_${i}`);
                if (name) {
                    travelers.push({
                        name: cleanHTML(name).substring(0, 500),
                        position: cleanHTML(position).substring(0, 500)
                    });
                }
            }

            return {
                officer_name: cleanHTML(formData.get('officer_name')).substring(0, 500),
                order_type: cleanHTML(formData.get('order_type')).substring(0, 100),
                purpose_of_order: cleanHTML(formData.get('purpose_of_order')).substring(0, 100),
                purpose_specify: cleanHTML(formData.get('purpose_specify')).substring(0, 500),
                travelers: travelers,
                event_title: cleanHTML(formData.get('event_title')).substring(0, 500),
                event_date: cleanHTML(formData.get('event_date')).substring(0, 100),
                event_place: cleanHTML(formData.get('event_place')).substring(0, 500),
                event_description: cleanHTML(formData.get('event_description')).substring(0, 2000),
                noted_by: cleanHTML(formData.get('noted_by')).substring(0, 500)
            };
        }

        // Handle form submission
        document.getElementById('addRelatedDocumentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate required fields
            const orderType = document.querySelector('input[name="order_type"]:checked');
            const purposeOfOrder = document.querySelector('input[name="purpose_of_order"]:checked');

            if (!document.getElementById('officeName').value) {
                alert('Office name not available');
                return;
            }

            if (!orderType) {
                alert('Please select order type');
                return;
            }

            if (!purposeOfOrder) {
                alert('Please select purpose of order');
                return;
            }

            if (!document.getElementById('travelEventTitle').value) {
                alert('Please enter event/activity title');
                return;
            }

            if (!document.getElementById('travelEventDate').value) {
                alert('Please enter event/activity date');
                return;
            }

            if (!document.getElementById('travelEventPlace').value) {
                alert('Please enter event/activity place');
                return;
            }

            if (!document.getElementById('notedBy').value) {
                alert('Please enter noted by information');
                return;
            }

            // Validate at least one traveler
            const travelers = document.querySelectorAll('.traveler-name');
            let hasTraveler = false;
            travelers.forEach(t => {
                if (t.value.trim()) hasTraveler = true;
            });

            if (!hasTraveler) {
                alert('Please add at least one traveler');
                return;
            }

            // Collect form data
            const formData = new FormData(this);
            const travelRequestData = buildTravelRequestJSON(formData);

            // Prepare submission data
            const submissionData = {
                action: 'add_travel_request',
                title: `Travel Request - ${document.getElementById('travelEventTitle').value}`,
                sender: document.getElementById('officeName').value,
                date_received: new Date().toISOString().split('T')[0],
                description: document.getElementById('travelEventDescription').value || `Travel to ${document.getElementById('travelEventPlace').value} on ${document.getElementById('travelEventDate').value}`,
                classification: document.getElementById('relatedClassification').value,
                sub_classification: document.getElementById('relatedSubClassification').value,
                priority: document.getElementById('relatedPriority').value,
                document_type: 'Travel Request',
                parent_document_id: document.getElementById('parentDocumentId').value,
                notes: travelRequestData
            };

            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

            fetch('documententry-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(submissionData)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                if (data.success) {
                    alert('Travel Request added successfully!');
                    closeAddRelatedDocumentModal();
                    document.getElementById('addRelatedDocumentForm').reset();
                } else {
                    alert('Error adding document: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert('Error adding document: ' + error.message);
            });
        });

        function viewReceivedDocument(assignmentId) {
            // Fetch document details from server (include credentials so PHP session is sent)
            fetch('get-document-details.php?assignment_id=' + assignmentId, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const doc = data.document;
                        const content = safeParseJson(doc.notes);
                        
                        currentReceivedDocument = doc;
                        
                        // Populate modal with data
                        document.getElementById('viewDocumentID').textContent = doc.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        // Prefer sender first/last name when available; fall back to generic sender_name
                        const senderFullName = ((doc.sender_first_name || '').trim() || (doc.sender_last_name || '').trim())
                            ? ((doc.sender_first_name || '') + ' ' + (doc.sender_last_name || '')).trim()
                            : (doc.sender_name || '-');
                        document.getElementById('viewSender').textContent = senderFullName || '-';
                        const receivedDate = isValidDateValue(doc.date_received) ? doc.date_received : (isValidDateValue(doc.received_at) ? doc.received_at : (isValidDateValue(doc.completed_at) ? doc.completed_at : ''));
                        document.getElementById('viewDateReceived').textContent = receivedDate ? new Date(receivedDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                        
                        // Classification
                        if (doc.classification) {
                            document.getElementById('viewClassification').innerHTML = '<span class="badge badge-info">' + escapeText(doc.classification) + '</span>';
                        }
                        
                        document.getElementById('viewSubClassification').textContent = doc.sub_classification || '-';
                        
                        // Prioritization
                        if (doc.priority) {
                            let badgeClass = 'badge-primary';
                            if (doc.priority === 'Urgent') badgeClass = 'badge-warning';
                            else if (doc.priority === 'Critical') badgeClass = 'badge-danger';
                            document.getElementById('viewPrioritization').innerHTML = '<span class="badge ' + badgeClass + '">' + escapeText(doc.priority) + '</span>';
                        }
                        
                        document.getElementById('viewDescription').textContent = doc.description || '-';
                        document.getElementById('viewCreatedDate').textContent = doc.date_sent ? new Date(doc.date_sent).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                        
                        // File handling
                        const fileBtn = document.getElementById('viewFileBtn');
                        const downloadBtn = document.getElementById('downloadReceivedBtn');
                        const fileNameSpan = document.getElementById('viewFileName');
                        
                        if (doc.file_path && doc.file_path !== '') {
                            const fileName = doc.file_path.split('/').pop();
                            fileNameSpan.textContent = fileName;
                            fileBtn.style.display = 'inline-block';
                            downloadBtn.style.display = 'inline-block';
                            window.currentReceivedFilePath = doc.file_path;
                        } else {
                            fileNameSpan.textContent = 'No attachment';
                            fileBtn.style.display = 'none';
                            downloadBtn.style.display = 'none';
                        }
                        
                        // Load related travel requests
                        loadTravelRequests(doc.document_id || doc.id);
                        // Load uploaded files as well so department staff can see attachments
                        loadUploadedFiles(doc.assignment_id || doc.id);
                        
                        document.getElementById('viewReceivedModal').classList.add('active');
                    } else {
                        alert('Error loading document details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function loadTravelRequests(parentDocumentId) {
            // Fetch travel requests related to this document (include credentials)
            fetch('get-travel-requests.php?parent_document_id=' + parentDocumentId, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('travelRequestsSection');
                    const list = document.getElementById('travelRequestsList');
                    const addBtn = document.getElementById('addDocumentBtn');
                    
                    if (data.success && data.requests && data.requests.length > 0) {
                        list.innerHTML = '';
                        data.requests.forEach(request => {
                            const requestData = safeParseJson(request.notes);
                            const card = createTravelRequestCard(request, requestData);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                        if (addBtn) addBtn.style.display = 'none';
                    } else {
                        section.style.display = 'none';
                        if (addBtn) addBtn.style.display = 'inline-block';
                    }
                })
                .catch(error => {
                    console.error('Error loading travel requests:', error);
                    document.getElementById('travelRequestsSection').style.display = 'none';
                    const addBtn = document.getElementById('addDocumentBtn');
                    if (addBtn) addBtn.style.display = 'inline-block';
                });
        }

        function createTravelRequestCard(request, data) {
            const card = document.createElement('div');
            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9;';
            
            let travelersHtml = '';
            if (data.travelers && data.travelers.length > 0) {
                travelersHtml = data.travelers.map(t => {
                    const days = t.days ? ` - ${t.days} day(s)` : '';
                    return `<li>${escapeText(t.name)} (${escapeText(t.position)})${days}</li>`;
                }).join('');
            }
            
            card.innerHTML = `
                <div style="margin-bottom: 8px;">
                    <h5 style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: var(--primary-color);">
                        Travel Request: ${escapeText(data.event_title || 'Untitled')}
                    </h5>
                    <p style="margin: 0; font-size: 11px; color: #666;">
                        Submitted on: ${request.date_sent ? new Date(request.date_sent).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-'}
                    </p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px; font-size: 11px;">
                    <div>
                        <span style="font-weight: 600; color: #666;">Officer:</span><br>
                        ${escapeText(data.officer_name || '-')}
                    </div>
                    <div>
                        <span style="font-weight: 600; color: #666;">Order Type:</span><br>
                        ${escapeText(data.order_type || '-')}
                    </div>
                    <div>
                        <span style="font-weight: 600; color: #666;">Purpose:</span><br>
                        ${escapeText(data.purpose_of_order || '-')}
                    </div>
                    <div>
                        <span style="font-weight: 600; color: #666;">Event Date:</span><br>
                        ${data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-'}
                    </div>
                </div>
                
                <div style="margin-bottom: 8px; font-size: 11px;">
                    <span style="font-weight: 600; color: #666;">Location:</span> ${escapeText(data.event_place || '-')}
                </div>
                
                ${travelersHtml ? `
                <div style="margin-bottom: 8px; font-size: 11px;">
                    <span style="font-weight: 600; color: #666; display: block; margin-bottom: 4px;">Travelers:</span>
                    <ul style="margin: 0; padding-left: 20px; font-size: 11px;">
                        ${travelersHtml}
                    </ul>
                </div>
                ` : ''}
                
                ${data.event_description ? `
                <div style="margin-bottom: 8px; font-size: 11px;">
                    <span style="font-weight: 600; color: #666;">Description:</span><br>
                    ${escapeText(data.event_description)}
                </div>
                ` : ''}
                
                <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; display: flex; gap: 6px;">
                    <button class="btn btn-sm btn-info" onclick="viewTravelRequest(this.dataset.request)" style="flex: 1; padding: 4px 8px; font-size: 11px;" data-request='${JSON.stringify(data).replace(/'/g, "&apos;")}'>
                        <i class="fas fa-eye"></i> View
                    </button>
                </div>
            `;
            
            return card;
        }

        function viewReceivedFile() {
            if (!window.currentReceivedFilePath) {
                alert('No file attached to this document');
                return;
            }
            
            const filePath = window.currentReceivedFilePath;
            const fileName = filePath.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isPDF = fileExt === 'pdf';
            
            if (isPDF) {
                // Open PDF in new tab
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else {
                // Show image in modal
                const url = 'view-document-file.php?path=' + encodeURIComponent(filePath);
                document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
                document.getElementById('fileViewerImage').src = url;
                document.getElementById('fileViewerModal').classList.add('active');
            }
        }

        function downloadReceivedDocument() {
            if (!window.currentReceivedFilePath) {
                alert('No file to download');
                return;
            }
            
            const filePath = window.currentReceivedFilePath;
            const fileName = filePath.split('/').pop();
            
            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeReceivedModal() {
            document.getElementById('viewReceivedModal').classList.remove('active');
        }

        function loadUploadedFiles(assignmentId) {
            if (!assignmentId) {
                return;
            }

            fetch('administrative/get-document-uploads.php?assignment_id=' + assignmentId, { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('uploadedFilesSection');
                    const list = document.getElementById('uploadedFilesList');

                    if (!section || !list) {
                        return;
                    }

                    if (data.success && Array.isArray(data.uploads) && data.uploads.length > 0) {
                        list.innerHTML = '';
                        data.uploads.forEach(upload => {
                            const card = createUploadedFileCard(upload);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                        list.innerHTML = '';
                    }
                })
                .catch(error => {
                    console.error('Error loading uploaded files:', error);
                    const section = document.getElementById('uploadedFilesSection');
                    if (section) {
                        section.style.display = 'none';
                    }
                });
        }

        function createUploadedFileCard(upload) {
            const card = document.createElement('div');
            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;';
            const fileName = upload.file_path.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
            const iconHtml = isImage ? `<img src="administrative/view-document-file.php?path=${encodeURIComponent(upload.file_path)}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"></img>`
                : (fileExt === 'pdf' ? '<i class="fas fa-file-pdf" style="font-size: 28px; color: #e74c3c;"></i>'
                : '<i class="fas fa-file" style="font-size: 28px; color: #999;"></i>');

            const uploadDate = formatDate(upload.uploaded_at);
            card.innerHTML = `
                <div style="display: flex; gap: 12px; flex: 1; min-width: 220px; align-items: center;">
                    <div style="flex-shrink: 0;">${iconHtml}</div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: #333; margin-bottom: 4px;">${escapeText(fileName)}</div>
                        <div style="font-size: 12px; color: #666; line-height: 1.4;">${escapeText(upload.uploaded_by || 'Staff')} • ${escapeText(uploadDate)}</div>
                        ${upload.notes ? `<div style="font-size: 12px; color: #555; margin-top: 6px;">${escapeText(upload.notes)}</div>` : ''}
                    </div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                    <button type="button" class="btn btn-sm btn-info" onclick="viewUploadedFile('${encodeURIComponent(upload.file_path)}', '${fileExt}')" style="padding: 6px 10px; font-size: 12px;">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="downloadUploadedFile('${encodeURIComponent(upload.file_path)}', '${escapeText(fileName)}')" style="padding: 6px 10px; font-size: 12px;">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            `;

            return card;
        }

        function viewUploadedFile(encodedPath, fileExt) {
            const filePath = decodeURIComponent(encodedPath);
            const isPDF = fileExt === 'pdf';

            if (isPDF) {
                window.open('administrative/view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
                return;
            }

            document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + filePath.split('/').pop();
            document.getElementById('fileViewerImage').src = 'administrative/view-document-file.php?path=' + encodeURIComponent(filePath);
            document.getElementById('fileViewerModal').classList.add('active');
        }

        function downloadUploadedFile(encodedPath, fileName) {
            const filePath = decodeURIComponent(encodedPath);
            const link = document.createElement('a');
            link.href = 'administrative/view-document-file.php?path=' + encodeURIComponent(filePath);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
        }

        function htmlEscape(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function validateTravelRequestForm() {
            const orderType = document.querySelector('input[name="order_type"]:checked');
            const purposeOfOrder = document.querySelector('input[name="purpose_of_order"]:checked');

            if (!document.getElementById('officeName').value) {
                alert('Office name not available');
                return false;
            }

            if (!orderType) {
                alert('Please select order type');
                return false;
            }

            if (!purposeOfOrder) {
                alert('Please select purpose of order');
                return false;
            }

            if (!document.getElementById('travelEventTitle').value) {
                alert('Please enter event/activity title');
                return false;
            }

            if (!document.getElementById('travelEventDate').value) {
                alert('Please enter event/activity date');
                return false;
            }

            if (!document.getElementById('travelEventPlace').value) {
                alert('Please enter event/activity place');
                return false;
            }

            if (!document.getElementById('notedBy').value) {
                alert('Please enter noted by information');
                return false;
            }

            const travelers = document.querySelectorAll('.traveler-name');
            let hasTraveler = false;
            travelers.forEach(t => {
                if (t.value.trim()) hasTraveler = true;
            });

            if (!hasTraveler) {
                alert('Please add at least one traveler');
                return false;
            }

            return true;
        }

        function previewTravelRequest() {
            if (!validateTravelRequestForm()) return;

            const form = document.getElementById('addRelatedDocumentForm');
            const formData = new FormData(form);
            const travelRequestData = buildTravelRequestJSON(formData);

            const eventDate = new Date(travelRequestData.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

            // Build purpose checkboxes
            const meetingChecked = travelRequestData.purpose_of_order === 'Meeting' ? 'X' : '';
            const conductChecked = travelRequestData.purpose_of_order === 'Conduct' ? 'X' : '';
            const othersChecked = travelRequestData.purpose_of_order === 'Others' ? 'X' : '';
            let othersSpecify = '';
            if (travelRequestData.purpose_of_order === 'Others' && travelRequestData.purpose_specify) {
                othersSpecify = ' (' + escapeText(travelRequestData.purpose_specify) + ')';
            }

            let travelersHtml = '';
            for (let i = 0; i < travelRequestData.travelers.length; i++) {
                const traveler = travelRequestData.travelers[i];
                const rowNum = i + 1;
                travelersHtml += `<tr><td style="padding: 6px; text-align: center; border: 1px solid #333;">${rowNum}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.name)}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.position)}</td></tr>`;
            }

            let printableHTML = '<div style="text-align: center; margin-bottom: 20px;">' +
                '<div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 10px;">' +
                '<img src="img/LGU-Mercedes-Official-Logo.png" style="height: 60px; width: auto;" alt="Logo">' +
                '<div>' +
                '<div style="font-size: 11px; color: #666;">MUNICIPALITY OF MERCEDES</div>' +
                '<div style="font-size: 11px; color: #666;">OFFICE OF THE MAYOR</div>' +
                '<div style="font-weight: bold; font-size: 12px; margin-top: 2px;">REQUEST FOR TRAVEL AND OFFICE ORDERS</div>' +
                '</div></div></div>' +
                '<div style="border: 1px solid #333; padding: 15px; margin-bottom: 15px;">' +
                '<table style="width: 100%; font-size: 11px; border-collapse: collapse;">' +
                '<tr><td style="width: 20%; font-weight: bold; padding: 4px;">NAME OF OFFICE:</td>' +
                '<td style="width: 30%; border-bottom: 1px solid #333; padding: 4px;">' + escapeText(travelRequestData.officer_name) + '</td>' +
                '<td style="width: 20%; font-weight: bold; padding: 4px; text-align: right;">PURPOSE OF ORDER</td>' +
                '<td style="width: 30%; padding: 4px; font-size: 11px;">' +
                '[' + meetingChecked + '] Meeting [' + conductChecked + '] Conduct [' + othersChecked + '] Others' + othersSpecify +
                '</td></tr></table></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>' +
                '<table style="width: 100%; font-size: 10px; border-collapse: collapse; border: 1px solid #333;">' +
                '<thead><tr style="background-color: #f0f0f0;">' +
                '<th style="padding: 4px; border: 1px solid #333; text-align: center; width: 5%;">No.</th>' +
                '<th style="padding: 4px; border: 1px solid #333; width: 25%;">NAME</th>' +
                '<th style="padding: 4px; border: 1px solid #333; width: 20%;">POSITION</th>' +
                '</tr></thead><tbody>' +
                (travelersHtml || '<tr><td colspan="3" style="padding: 10px; text-align: center; border: 1px solid #333;">No travelers added</td></tr>') +
                '</tbody></table>' +
                '<div style="font-size: 9px; margin-top: 4px;">Please continue on another page if more than 10 persons are involved.</div></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">TITLE OF EVENT/ACTIVITY:</div>' +
                '<div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(travelRequestData.event_title) + '</div></div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">' +
                '<div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DATE OF EVENT/ACTIVITY:</div>' +
                '<div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + eventDate + '</div></div>' +
                '<div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">PLACE OF EVENT/ACTIVITY:</div>' +
                '<div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(travelRequestData.event_place) + '</div></div></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DESCRIPTION/DETAILS:</div>' +
                '<div style="border: 1px solid #333; padding: 8px; min-height: 50px; font-size: 11px; line-height: 1.4;">' + escapeText(travelRequestData.event_description || '') + '</div></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NOTED BY (Name & Position):</div>' +
                '<div style="font-size: 9px; margin-bottom: 6px; text-align: center; font-weight: 500; min-height: 16px;">' + escapeText(travelRequestData.noted_by) + '</div>' +
                '<div style="border-bottom: 1px solid #333; padding: 6px; min-height: 35px; font-size: 11px;"></div>' +
                '<div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div></div>' +
                '<div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px;">' +
                '<div>Generated: ' + new Date().toLocaleString() + '</div>' +
                '<div style="margin-top: 5px; color: #999;">© ' + new Date().getFullYear() + ' Municipality of Mercedes</div></div>';

            document.getElementById('printableFormContent').innerHTML = printableHTML;
            document.getElementById('travelRequestPreviewModal').classList.add('active');
            
            // Store form data for later submission
            window.pendingTravelRequest = {
                action: 'add_travel_request',
                title: 'Travel Request - ' + cleanHTML(document.getElementById('travelEventTitle').value || ''),
                sender: cleanHTML(document.getElementById('officeName').value || ''),
                date_received: new Date().toISOString().split('T')[0],
                description: cleanHTML(document.getElementById('travelEventDescription').value || ('Travel to ' + (document.getElementById('travelEventPlace').value || '') + ' on ' + (document.getElementById('travelEventDate').value || ''))),
                classification: cleanHTML(document.getElementById('relatedClassification').value || ''),
                sub_classification: cleanHTML(document.getElementById('relatedSubClassification').value || ''),
                priority: cleanHTML(document.getElementById('relatedPriority').value || ''),
                document_type: 'Travel Request',
                parent_document_id: cleanHTML(document.getElementById('parentDocumentId').value || ''),
                notes: travelRequestData
            };
        }

        function closePreviewModal() {
            document.getElementById('travelRequestPreviewModal').classList.remove('active');
        }

        function printTravelRequest() {
            const printWindow = window.open('', '', 'width=900,height=1000');
            const content = document.getElementById('printableFormContent').innerHTML;
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Travel Request Form - Municipality of Mercedes</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
                        @media print { 
                            body { margin: 0; padding: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => printWindow.print(), 500);
        }

        function viewTravelRequest(dataStr) {
            try {
                const data = JSON.parse(dataStr);
                const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                
                let travelersHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersHtml = data.travelers.map((t, idx) => `
                        <tr>
                            <td style="padding: 6px; text-align: center; border: 1px solid #333;">${idx + 1}</td>
                            <td style="padding: 6px; border: 1px solid #333;">${escapeText(t.name)}</td>
                            <td style="padding: 6px; border: 1px solid #333;">${escapeText(t.position)}</td>
                        </tr>
                    `).join('');
                } else {
                    travelersHtml = '<tr><td colspan="3" style="text-align: center; padding: 20px; border: 1px solid #333;">No travelers added</td></tr>';
                }
                
                let purposeText = data.purpose_of_order || 'N/A';
                if (data.purpose_of_order === 'Others' && data.purpose_specify) {
                    purposeText += ' (' + escapeText(data.purpose_specify) + ')';
                }
                
                const viewModal = document.createElement('div');
                viewModal.id = 'travelRequestViewModal';
                viewModal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 3200;';
                viewModal.travelRequestData = data;
                
                const documentContent = `
                    <div style="font-family: Arial, sans-serif; background: white; padding: 40px; max-width: 850px; width: 100%; line-height: 1.6; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px;">
                        <!-- Header -->
                        <div style="text-align: center; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 15px;">
                            <img src="img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;">
                            <div style="text-align: center;">
                                <div style="font-size: 11px; color: #666;">MUNICIPALITY OF MERCEDES</div>
                                <div style="font-size: 11px; color: #666;">OFFICE OF THE MAYOR</div>
                                <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">REQUEST FOR TRAVEL AND OFFICE ORDERS</div>
                            </div>
                        </div>
                        
                        <!-- Info Section -->
                        <div style="border: 1px solid #333; padding: 15px; margin-bottom: 20px;">
                            <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 20%; font-weight: bold; padding: 6px;">NAME OF OFFICE:</td>
                                    <td style="width: 30%; border-bottom: 1px solid #333; padding: 6px;">${escapeText(data.officer_name || '-')}</td>
                                    <td style="width: 20%; font-weight: bold; padding: 6px; text-align: right;">PURPOSE OF ORDER</td>
                                    <td style="width: 30%; padding: 6px; font-size: 11px;">${purposeText}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Travelers Section -->
                        <div style="margin-bottom: 20px;">
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 10px;">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>
                            <table style="width: 100%; font-size: 10px; border-collapse: collapse; border: 1px solid #333;">
                                <thead>
                                    <tr style="background-color: #f0f0f0;">
                                        <th style="padding: 6px; border: 1px solid #333; text-align: center; width: 5%;">No.</th>
                                        <th style="padding: 6px; border: 1px solid #333; width: 45%;">NAME</th>
                                        <th style="padding: 6px; border: 1px solid #333; width: 50%;">POSITION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${travelersHtml}
                                </tbody>
                            </table>
                            <div style="font-size: 9px; margin-top: 4px; color: #666;">Please continue on another page if more than 10 persons are involved.</div>
                        </div>
                        
                        <!-- Event Details -->
                        <div style="margin-bottom: 20px;">
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">TITLE OF EVENT/ACTIVITY:</div>
                            <div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">${escapeText(data.event_title || '-')}</div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div>
                                <div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DATE OF EVENT/ACTIVITY:</div>
                                <div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">${eventDate}</div>
                            </div>
                            <div>
                                <div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">PLACE OF EVENT/ACTIVITY:</div>
                                <div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">${escapeText(data.event_place || '-')}</div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DESCRIPTION/DETAILS:</div>
                            <div style="border: 1px solid #333; padding: 8px; min-height: 60px; font-size: 11px; line-height: 1.4; white-space: pre-wrap;">${escapeText(data.event_description || '-')}</div>
                        </div>
                        
                        <!-- Signature Section -->
                        <div style="margin-bottom: 20px;">
                            <div style="font-weight: bold; font-size: 11px; margin-bottom: 10px;">NOTED BY (Name & Position):</div>
                            <div style="font-size: 9px; margin-bottom: 6px; text-align: center; font-weight: 500; min-height: 16px;">${escapeText(data.noted_by || '')}</div>
                            <div style="border-bottom: 1px solid #333; padding: 6px; min-height: 45px; font-size: 11px;"></div>
                            <div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div>
                        </div>
                        
                        <!-- Footer -->
                        <div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; color: #666;">
                            <div>Generated: ${new Date().toLocaleString()}</div>
                            <div style="margin-top: 4px;">© ${new Date().getFullYear()} Municipality of Mercedes</div>
                        </div>
                    </div>
                `;
                
                viewModal.innerHTML = `
                    <div style="position: relative; background: white; border-radius: 8px; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto;">
                        <div style="position: sticky; top: 0; padding: 12px 20px; background: white; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; z-index: 100;">
                            <h3 style="margin: 0; color: var(--primary-color); font-size: 16px;">Travel Request - Digital Format</h3>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button onclick="printTravelRequestFromCard(document.getElementById('travelRequestViewModal').travelRequestData);" class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 12px; margin: 0;">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button onclick="document.getElementById('travelRequestViewModal').remove();" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666; padding: 0;">×</button>
                            </div>
                        </div>
                        <div style="padding: 20px; overflow-y: auto;">
                            ${documentContent}
                        </div>
                    </div>
                `;
                
                document.body.appendChild(viewModal);
            } catch (e) {
                alert('Error: ' + e.message);
                console.error('View error:', e);
            }
        }

        function generateTravelOrder(data) {
            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                
                // Generate document number in format like: 120125-1481A
                const today = new Date();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const year = String(today.getFullYear()).slice(-2);
                const randomNum = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                const docNumber = `${month}${day}${year}-${randomNum}A`;
                
                // Format current date like: 01 December 2025
                const currentDate = today.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: '2-digit'
                });
                
                // Build travelers list - each on separate line with full formatting
                let travelersListHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersListHtml = data.travelers.map(t => {
                        return `<div style="margin: 3px 0;">${escapeText(t.name)}, ${escapeText(t.position)}</div>`;
                    }).join('');
                } else {
                    travelersListHtml = '<div style="margin: 3px 0;">No travelers listed</div>';
                }
                
                // Create new window for travel order
                const orderWindow = window.open('', '', 'width=900,height=1100');
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Travel Order - Municipality of Mercedes</title>
                        <style>
                            body { 
                                font-family: 'Times New Roman', serif; 
                                margin: 50px 60px; 
                                font-size: 12px; 
                                line-height: 1.4;
                            }
                            .header { 
                                text-align: center; 
                                margin-bottom: 8px;
                            }
                            .header-line { 
                                font-size: 11px; 
                                font-weight: bold; 
                                letter-spacing: 0.5px;
                                margin: 2px 0;
                            }
                            .travel-order-title { 
                                font-size: 16px; 
                                font-weight: bold; 
                                margin-top: 8px;
                                letter-spacing: 2px;
                            }
                            .doc-number { 
                                font-size: 11px; 
                                margin-top: 4px;
                            }
                            .top-separator { 
                                border-top: 2px solid #333;
                                border-bottom: 2px solid #333;
                                margin: 10px 0;
                                padding: 2px 0;
                            }
                            .section { 
                                margin-bottom: 0;
                                padding: 8px 0;
                                display: flex;
                                border-bottom: 1px solid #333;
                            }
                            .section:last-of-type {
                                border-bottom: 2px solid #333;
                            }
                            .section-label { 
                                width: 70px; 
                                font-weight: bold;
                                padding-right: 10px;
                                text-align: left;
                            }
                            .section-content { 
                                flex: 1;
                                padding-left: 10px;
                            }
                            .body-text { 
                                text-align: justify; 
                                font-size: 12px; 
                                line-height: 1.6;
                                margin: 20px 0;
                            }
                            .highlight-box { 
                                margin: 12px 40px; 
                                padding: 12px 15px; 
                                background: #fafafa; 
                                border-left: 3px solid #333;
                                font-size: 11px;
                            }
                            .highlight-box div { 
                                margin: 4px 0;
                            }
                            .signature-section { 
                                margin-top: 50px; 
                                text-align: right;
                                margin-right: 20px;
                            }
                            .signature-name { 
                                margin-top: 40px;
                                font-weight: bold; 
                                letter-spacing: 0.5px;
                                font-size: 12px;
                            }
                            .signature-title { 
                                font-size: 12px; 
                                font-weight: bold;
                                margin-top: 4px;
                            }
                            .footer { 
                                text-align: center; 
                                font-size: 8px; 
                                margin-top: 30px;
                                color: #999;
                            }
                            @media print {
                                body { margin: 30px 40px; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <div style="text-align: center; margin-bottom: 10px;">
                                <img src="./img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;">
                            </div>
                            <div class="header-line">MUNICIPALITY OF MERCEDES</div>
                            <div class="header-line">OFFICE OF THE MUNICIPAL MAYOR</div>
                            <div class="travel-order-title">TRAVEL ORDER</div>
                            <div class="doc-number">No. ${docNumber}</div>
                        </div>
                        
                        <div class="top-separator"></div>
                        
                        <div class="section">
                            <div class="section-label">TO</div>
                            <div class="section-content">: ${travelersListHtml}</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-label">FROM</div>
                            <div class="section-content">: ALEXANDER PAJARILLO<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Municipal Mayor</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-label">DATE</div>
                            <div class="section-content">: ${currentDate}</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-label">SUBJECT</div>
                            <div class="section-content">: As Stated.</div>
                        </div>
                        
                        <div class="body-text">
                            <p style="margin: 0 0 10px 0;">You are hereby directed to attend <strong>${escapeText(data.event_title || 'the event')}</strong>.</p>
                            
                            <div class="highlight-box">
                                <div><strong>Event:</strong> ${escapeText(data.event_title || '-')}</div>
                                <div><strong>Date:</strong> ${data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'}</div>
                                <div><strong>Location:</strong> ${escapeText(data.event_place || '-')}</div>
                                ${data.event_description ? `<div><strong>Description:</strong> ${escapeText(data.event_description)}</div>` : ''}
                            </div>
                            
                            <p style="margin: 10px 0 0 0;"><em>For your immediate compliance.</em></p>
                        </div>
                        
                        <div class="signature-section">
                            <div class="signature-name">ALEXANDER PAJARILLO</div>
                            <div class="signature-title">MUNICIPAL MAYOR</div>
                        </div>
                        
                        <div class="footer">
                            <div>Generated: ${new Date().toLocaleString()}</div>
                        </div>
                    </body>
                    </html>
                `;
                
                orderWindow.document.write(printContent);
                orderWindow.document.close();
                setTimeout(() => orderWindow.print(), 500);
            } catch (e) {
                alert('Error generating travel order: ' + e.message);
                console.error('Generate order error:', e);
            }
        }

        function printTravelRequestFromCard(data) {
            try {
                // If passed as string, parse it
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                
                const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                
                let travelersHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersHtml = data.travelers.map((t, idx) => `
                        <tr>
                            <td style="padding: 6px; text-align: center; border: 1px solid #333;">${idx + 1}</td>
                            <td style="padding: 6px; border: 1px solid #333;">${escapeText(t.name)}</td>
                            <td style="padding: 6px; border: 1px solid #333;">${escapeText(t.position)}</td>
                        </tr>
                    `).join('');
                }
                
                let purposeText = data.purpose_of_order || 'N/A';
                if (data.purpose_of_order === 'Others' && data.purpose_specify) {
                    purposeText += ' (' + escapeText(data.purpose_specify) + ')';
                }
                
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Travel Request - Municipality of Mercedes</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                            .header { text-align: center; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 15px; }
                            .header img { height: 50px; }
                            .header-text { text-align: center; }
                            .title { font-weight: bold; font-size: 14px; margin-top: 5px; }
                            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                            th, td { border: 1px solid #333; padding: 8px; }
                            th { background: #f0f0f0; font-weight: bold; }
                            .section-label { font-weight: bold; margin-top: 15px; margin-bottom: 5px; }
                            .field-label { font-weight: bold; color: #333; }
                            @media print {
                                body { margin: 0; padding: 10px; }
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <img src="img/LGU-Mercedes-Official-Logo.png" alt="Logo">
                            <div class="header-text">
                                <div>MUNICIPALITY OF MERCEDES</div>
                                <div>OFFICE OF THE MAYOR</div>
                                <div class="title">REQUEST FOR TRAVEL AND OFFICE ORDERS</div>
                            </div>
                        </div>
                        
                        <table>
                            <tr>
                                <td style="width: 20%;"><span class="field-label">OFFICER:</span></td>
                                <td style="width: 30%;">${escapeText(data.officer_name || '-')}</td>
                                <td style="width: 20%;"><span class="field-label">PURPOSE:</span></td>
                                <td>${purposeText}</td>
                            </tr>
                            <tr>
                                <td><span class="field-label">ORDER TYPE:</span></td>
                                <td>${escapeText(data.order_type || '-')}</td>
                                <td><span class="field-label">EVENT DATE:</span></td>
                                <td>${eventDate}</td>
                            </tr>
                        </table>
                        
                        <div class="section-label">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>
                        <table>
                            <thead>
                                <tr style="background: #f0f0f0;">
                                    <th style="width: 5%; text-align: center;">No.</th>
                                    <th style="width: 40%;">NAME</th>
                                    <th style="width: 55%;">POSITION</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${travelersHtml || '<tr><td colspan="3" style="text-align: center; padding: 20px;">No travelers added</td></tr>'}
                            </tbody>
                        </table>
                        
                        <div class="section-label">EVENT/ACTIVITY DETAILS:</div>
                        <table>
                            <tr>
                                <td><span class="field-label">TITLE:</span></td>
                                <td>${escapeText(data.event_title || '-')}</td>
                            </tr>
                            <tr>
                                <td><span class="field-label">LOCATION:</span></td>
                                <td>${escapeText(data.event_place || '-')}</td>
                            </tr>
                            <tr>
                                <td><span class="field-label">DESCRIPTION:</span></td>
                                <td><pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">${escapeText(data.event_description || '-')}</pre></td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 30px; text-align: center;">
                            <p>Generated: ${new Date().toLocaleString()}</p>
                        </div>
                    </body>
                    </html>
                `;
                
                const printWindow = window.open('', '', 'width=900,height=1000');
                printWindow.document.write(printContent);
                printWindow.document.close();
                setTimeout(() => printWindow.print(), 500);
            } catch (e) {
                alert('Error printing: ' + e.message);
                console.error('Print error:', e);
            }
        }

        function submitTravelRequest() {
            if (!window.pendingTravelRequest) {
                alert('Form data lost. Please preview again.');
                return;
            }

            const submitBtn = document.querySelector('#travelRequestPreviewModal .btn-success');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                console.log('window.pendingTravelRequest before stringify:', window.pendingTravelRequest);
                console.log('notes object:', window.pendingTravelRequest.notes);
                console.log('travelers array:', window.pendingTravelRequest.notes.travelers);
                
                const jsonData = JSON.stringify(window.pendingTravelRequest);
                console.log('JSON stringified successfully, length:', jsonData.length);
                
                fetch('documententry-handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: jsonData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;

                    if (data.success) {
                        alert('Travel Request submitted successfully!');
                        closePreviewModal();
                        closeAddRelatedDocumentModal();
                        document.getElementById('addRelatedDocumentForm').reset();
                        window.pendingTravelRequest = null;
                        // Reload travel requests if viewing a document
                        if (currentReceivedDocument) {
                            loadTravelRequests(currentReceivedDocument.document_id || currentReceivedDocument.id);
                        }
                    } else {
                        alert('Error submitting document: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    console.error('Error:', error);
                    alert('Error submitting form: ' + error.message);
                });
            } catch (e) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                console.error('JSON stringify error:', e);
                alert('Error preparing form data: ' + e.message);
            }
        }

        function loadReceivedDocuments() {
            // Fallback for legacy upload handlers that call this function.
            location.reload();
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>
