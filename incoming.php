<?php
/**
 * Incoming Page - Requires Authentication
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

// Fetch incoming document assignments with tracking information
$incoming_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        da.assigned_to,
        d.id as document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        d.sender_name,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.deadline,
        d.file_path,
        da.notes as current_assignment_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
        da.completed_at
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    JOIN users recipient ON da.assigned_to = recipient.id
    WHERE (
        (da.assigned_to = ? AND da.status IN ('Pending', 'Forwarded', 'Received', 'Returned'))
        OR (da.assigned_by = ? AND da.status = 'Returned')
    )
    ORDER BY da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $incoming_documents[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming - LGU Mercedes Document Tracking System</title>
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
                    <li>    
                        <a href="documententry.php" class="nav-item" data-page="entry">
                            <i class="fas fa-file-upload"></i>
                            <span>Documents</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="incoming.php" class="nav-item active" data-page="incoming">
                            <div>
                                <i class="fas fa-inbox"></i>
                                <span>Incoming</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="nav-item" data-page="outgoing">
                            <div>
                                <i class="fas fa-paper-plane"></i>
                                <span>Outgoing</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="nav-item" data-page="received">
                            <div>
                                <i class="fas fa-envelope-open"></i>
                                <span>Approved</span>
                            </div>
                        </a>
                    </li>
                    
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <div>
                                <i class="fas fa-check-circle"></i>
                                <span>Finished</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="nav-item" data-page="archive">
                            <div>
                                <i class="fas fa-archive"></i>
                                <span>Archive</span>
                            </div>
                        </a>
                    </li>
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
                            <h2>Incoming Documents</h2>
                            <p>Documents received but not yet processed</p>
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
                            <?php if (count($incoming_documents) > 0): ?>
                                <?php foreach ($incoming_documents as $doc): ?>
                                    <?php
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $senderName = trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        if ($senderName === '') {
                                            $senderName = trim($doc['sender_name'] ?? '');
                                        }
                                        if ($senderName === '') {
                                            $senderName = 'N/A';
                                        }
                                        $description = $doc['description'] ?? '';
                                        $status = $doc['assignment_status'] ?? '';
                                        $notes = $doc['assignment_notes'] ?? '';
                                        if ($status === 'Returned' && !empty($doc['current_assignment_notes'])) {
                                            $notes = $doc['current_assignment_notes'];
                                        }
                                        $priorityValue = $doc['priority'] ?? 'N/A';
                                        $dateReceived = $doc['date_received'] ?? '';
                                        // If date_received is null/zero, use assigned_at instead
                                        if (empty($dateReceived) || strpos($dateReceived, '0000-00-00') !== false) {
                                            $dateReceived = $doc['assigned_at'] ?? '';
                                        }
                                        $dateReceivedTs = $dateReceived ? strtotime($dateReceived) : false;
                                        $filterDate = $dateReceivedTs && $dateReceivedTs > 0 ? date('Y-m-d', $dateReceivedTs) : '';
                                        $displayDate = '';
                                        if ($dateReceivedTs && $dateReceivedTs > 0) {
                                            $displayDate = date('M d, Y', $dateReceivedTs);
                                        }
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($description ?? '') . ' ' . ($notes ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-assignment-id="<?php echo (int)$doc['assignment_id']; ?>" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($trackingCode); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 80) . (strlen($doc['description'] ?? '') > 80 ? '...' : ''); ?></td>
                                        <td><?php echo $displayDate ?: '-'; ?></td>
                                        <td>
                                            <?php 
                                                $classification = $doc['classification'] ?? 'N/A';
                                                $classificationClass = 'badge-info';
                                                if ($classification === 'Letter') $classificationClass = 'badge-classification-letter';
                                                elseif ($classification === 'Invitation') $classificationClass = 'badge-classification-invitation';
                                                elseif ($classification === 'Travel-Related Communication') $classificationClass = 'badge-classification-travel';
                                                elseif ($classification === 'Indorsement') $classificationClass = 'badge-classification-indorsement';
                                            ?>
                                            <span class="badge <?php echo $classificationClass; ?>"><?php echo htmlspecialchars($classification); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['sub_classification'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                                $priorityClass = 'badge-primary';
                                                if ($priorityValue === 'Urgent') $priorityClass = 'badge-warning';
                                                elseif ($priorityValue === 'Critical') $priorityClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($priorityValue); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = $doc['assignment_status'];
                                                $badge_class = 'badge-info';
                                                if ($status === 'Received') $badge_class = 'badge-success';
                                                elseif ($status === 'Pending') $badge_class = 'badge-warning';
                                                elseif ($status === 'Returned') $badge_class = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> assignment-status"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewIncomingDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">No incoming documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">No incoming documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Incoming Document Modal -->
    <div id="viewIncomingModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeIncomingModal()">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeIncomingModal()">
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
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Status</label>
                        <span style="font-size: 14px; color: #333;" id="viewStatus">-</span>
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
                        <button type="button" class="btn btn-sm btn-warning" onclick="viewIncomingFile()" id="viewFileBtn" style="display: none;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="downloadIncomingDocument()" id="downloadIncomingBtn" style="display: none;">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>

                <div id="rejectionDetailsSection" style="display: none; margin-top: 16px; margin-bottom: 8px; background: #fff4f4; border: 1px solid #f4cccc; border-radius: 8px; padding: 12px 14px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #b71c1c;">Rejection Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                        <div>
                            <label style="font-weight: 600; color: #8a3b3b; font-size: 12px; margin-bottom: 4px; display: block;">Rejected By</label>
                            <span id="viewRejectedBy" style="font-size: 14px; color: #333;">-</span>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #8a3b3b; font-size: 12px; margin-bottom: 4px; display: block;">Rejected At</label>
                            <span id="viewRejectedAt" style="font-size: 14px; color: #333;">-</span>
                        </div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #8a3b3b; font-size: 12px; margin-bottom: 4px; display: block;">Reason</label>
                        <span id="viewRejectedReason" style="font-size: 14px; color: #333; line-height: 1.6; display: block;">-</span>
                    </div>
                </div>

                <!-- Travel Request Submissions Section -->
                <div id="travelRequestsSection" style="display: block; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--primary-color);">
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
                <button type="button" class="btn btn-success" id="incomingAddDocumentBtn" onclick="openAddDocumentFromIncoming()" style="display: none;">
                    <i class="fas fa-plus"></i> Add Document
                </button>
                <button type="button" class="btn btn-primary" id="incomingReceiveBtn" onclick="receiveIncomingDocument()" style="display: none;">
                    <i class="fas fa-check"></i> Receive
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeIncomingModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Related Document Modal -->
    <div id="addRelatedDocumentModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if (event.target === this) closeAddRelatedDocumentModal()">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Add Related Document</h3>
                <button class="modal-close" onclick="closeAddRelatedDocumentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="addRelatedDocumentForm" onsubmit="return false;">
                <div class="modal-body">
                    <input type="hidden" id="parentDocumentId" name="parent_document_id">
                    <input type="hidden" id="docSequenceNumber" name="doc_sequence_number" value="1">
                    <input type="hidden" id="relatedClassification" name="classification">
                    <input type="hidden" id="relatedSubClassification" name="sub_classification">
                    <input type="hidden" id="relatedDocumentType" name="document_type">
                    <input type="hidden" id="relatedPriority" name="priority" value="Normal">
                    
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
                <button type="button" class="btn btn-info" onclick="downloadIncomingDocument()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }
    </style>

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
            setMinimumTravelEventDate();
            applyFilters();
        });

        let currentIncomingAssignmentId = null;
        let currentIncomingAssignment = null;
        const currentUserId = <?php echo (int)$user_id; ?>;
        const userOffice = <?php echo json_encode($user_details['office_department'] ?? ''); ?>;

        function escapeText(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/[&<>\"'`=\/]/g, function (s) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '/': '&#x2F;',
                    '`': '&#x60;',
                    '=': '&#x3D;'
                }[s];
            });
        }

        function cleanHTML(str) {
            return String(str || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        }

        function getAutoDocumentFormat(classification) {
            return 'Travel Request';
        }

        function safeParseJson(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.warn('Failed to parse JSON:', text);
                return {};
            }
        }

        function extractReturnReason(notesText) {
            const text = String(notesText || '').trim();
            if (!text) {
                return '-';
            }

            const returnReasonMatch = text.match(/Return\s+Reason\s*:\s*([\s\S]+)/i);
            if (returnReasonMatch && returnReasonMatch[1] && returnReasonMatch[1].trim()) {
                return returnReasonMatch[1].trim();
            }

            const reasonMatch = text.match(/Reason\s*:\s*([\s\S]+)/i);
            if (reasonMatch && reasonMatch[1] && reasonMatch[1].trim()) {
                return reasonMatch[1].trim();
            }

            return text;
        }

        function isIncomingRequestAssignment(assignment) {
            if (!assignment) return false;
            const combined = [assignment.document_type, assignment.sub_classification, assignment.classification, assignment.title]
                .filter(Boolean)
                .join(' | ')
                .toLowerCase();

            return combined.includes('request');
        }

        function openAddDocumentFromIncoming() {
            if (!currentIncomingAssignment) {
                alert('No incoming request loaded');
                return;
            }

            setMinimumTravelEventDate();

            const parentDocumentId = currentIncomingAssignment.document_id || currentIncomingAssignment.doc_id || currentIncomingAssignment.id || currentIncomingAssignmentId || '';
            const autoFormat = getAutoDocumentFormat(currentIncomingAssignment.classification || currentIncomingAssignment.document_type || 'Travel Request');

            document.getElementById('parentDocumentId').value = parentDocumentId;
            document.getElementById('relatedDocumentType').value = autoFormat;
            document.getElementById('relatedClassification').value = currentIncomingAssignment.classification || '';
            document.getElementById('relatedSubClassification').value = currentIncomingAssignment.sub_classification || '';
            document.getElementById('relatedPriority').value = currentIncomingAssignment.priority || 'Normal';

            const form = document.getElementById('addRelatedDocumentForm');
            form.reset();
            initializeTravelersContainer();

            const purposeSpecifyInput = document.getElementById('purposeSpecify');
            if (purposeSpecifyInput) {
                purposeSpecifyInput.style.display = 'none';
                purposeSpecifyInput.value = '';
            }

            document.getElementById('travelOfficeName').textContent = userOffice || currentIncomingAssignment.office_department || 'N/A';
            document.getElementById('officeName').value = userOffice || currentIncomingAssignment.office_department || 'N/A';

            document.getElementById('relatedDocumentType').value = autoFormat;
            document.getElementById('relatedClassification').value = currentIncomingAssignment.classification || '';
            document.getElementById('relatedSubClassification').value = currentIncomingAssignment.sub_classification || '';
            document.getElementById('relatedPriority').value = currentIncomingAssignment.priority || 'Normal';
            document.getElementById('parentDocumentId').value = parentDocumentId;

            document.getElementById('addRelatedDocumentModal').classList.add('active');
        }

        function closeAddRelatedDocumentModal() {
            document.getElementById('addRelatedDocumentModal').classList.remove('active');
        }

        function setMinimumTravelEventDate() {
            const travelEventDateInput = document.getElementById('travelEventDate');
            if (!travelEventDateInput) return;

            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            travelEventDateInput.min = `${year}-${month}-${day}`;
        }

        function initializeTravelersContainer() {
            const container = document.getElementById('travelersList');
            if (!container) return;
            container.innerHTML = '';
            addTravelerRow();
        }

        function addTravelerRow() {
            const container = document.getElementById('travelersList');
            if (!container) return;

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

            container.appendChild(row);
        }

        function removeTravelerRow(btn) {
            const row = btn?.parentElement;
            if (!row) return;
            row.remove();

            const container = document.getElementById('travelersList');
            container.querySelectorAll('.traveler-row').forEach((rowItem, index) => {
                const numberCell = rowItem.querySelector('div:first-child');
                if (numberCell) {
                    numberCell.textContent = index + 1;
                }
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'purpose_of_order') {
                const purposeSpecifyInput = document.getElementById('purposeSpecify');
                if (!purposeSpecifyInput) return;
                if (e.target.value === 'Others') {
                    purposeSpecifyInput.style.display = 'block';
                } else {
                    purposeSpecifyInput.style.display = 'none';
                    purposeSpecifyInput.value = '';
                }
            }
        });

        function buildTravelRequestJSON(formData) {
            const travelers = [];
            for (let i = 1; i <= 10; i++) {
                const name = formData.get(`traveler_name_${i}`);
                const position = formData.get(`traveler_position_${i}`);
                if (name && name.trim()) {
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
            const eventDate = travelRequestData.event_date ? new Date(travelRequestData.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';

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
                travelersHtml += `<tr><td style="padding: 6px; text-align: center; border: 1px solid #333;">${i + 1}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.name)}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.position)}</td></tr>`;
            }

            const printableHTML = '<div style="text-align: center; margin-bottom: 20px;">' +
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
                '<td style="width: 30%; padding: 4px; font-size: 11px;">[' + meetingChecked + '] Meeting [' + conductChecked + '] Conduct [' + othersChecked + '] Others' + othersSpecify + '</td></tr></table></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>' +
                '<table style="width: 100%; font-size: 10px; border-collapse: collapse; border: 1px solid #333;">' +
                '<thead><tr style="background-color: #f0f0f0;"><th style="padding: 4px; border: 1px solid #333; text-align: center; width: 5%;">No.</th><th style="padding: 4px; border: 1px solid #333; width: 25%;">NAME</th><th style="padding: 4px; border: 1px solid #333; width: 20%;">POSITION</th></tr></thead><tbody>' +
                (travelersHtml || '<tr><td colspan="3" style="padding: 10px; text-align: center; border: 1px solid #333;">No travelers added</td></tr>') +
                '</tbody></table>' +
                '<div style="font-size: 9px; margin-top: 4px;">Please continue on another page if more than 10 persons are involved.</div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">TITLE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(travelRequestData.event_title) + '</div></div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;"><div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DATE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + eventDate + '</div></div>' +
                '<div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">PLACE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(travelRequestData.event_place) + '</div></div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DESCRIPTION/DETAILS:</div><div style="border: 1px solid #333; padding: 8px; min-height: 50px; font-size: 11px; line-height: 1.4;">' + escapeText(travelRequestData.event_description || '') + '</div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NOTED BY (Name & Position):</div><div style="font-size: 9px; margin-bottom: 6px; text-align: center; font-weight: 500; min-height: 16px;">' + escapeText(travelRequestData.noted_by) + '</div><div style="border-bottom: 1px solid #333; padding: 6px; min-height: 35px; font-size: 11px;"></div><div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div></div>' +
                '<div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px;"><div>Generated: ' + new Date().toLocaleString() + '</div><div style="margin-top: 5px; color: #999;">© ' + new Date().getFullYear() + ' Municipality of Mercedes</div></div>';

            document.getElementById('printableFormContent').innerHTML = printableHTML;
            document.getElementById('travelRequestPreviewModal').classList.add('active');

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
                assignment_id: cleanHTML(currentIncomingAssignmentId || ''),
                parent_document_id: cleanHTML(document.getElementById('parentDocumentId').value || ''),
                notes: travelRequestData
            };

            document.getElementById('travelRequestPreviewModal').dataset.pendingTravelRequest = JSON.stringify(window.pendingTravelRequest);
        }

        function closePreviewModal() {
            document.getElementById('travelRequestPreviewModal').classList.remove('active');
        }

        function buildTravelRequestPayload() {
            return {
                action: 'add_travel_request',
                title: 'Travel Request - ' + cleanHTML(document.getElementById('travelEventTitle').value || ''),
                sender: cleanHTML(document.getElementById('officeName').value || ''),
                date_received: new Date().toISOString().split('T')[0],
                description: cleanHTML(document.getElementById('travelEventDescription').value || ('Travel to ' + (document.getElementById('travelEventPlace').value || '') + ' on ' + (document.getElementById('travelEventDate').value || ''))),
                classification: cleanHTML(document.getElementById('relatedClassification').value || ''),
                sub_classification: cleanHTML(document.getElementById('relatedSubClassification').value || ''),
                priority: cleanHTML(document.getElementById('relatedPriority').value || ''),
                document_type: 'Travel Request',
                assignment_id: cleanHTML(currentIncomingAssignmentId || ''),
                parent_document_id: cleanHTML(document.getElementById('parentDocumentId').value || ''),
                notes: window.pendingTravelRequest?.notes || {}
            };
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
                        @media print { body { margin: 0; padding: 0; } .no-print { display: none; } }
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

        function submitTravelRequest() {
            const previewModal = document.getElementById('travelRequestPreviewModal');
            let payload = window.pendingTravelRequest;
            if (!payload && previewModal?.dataset?.pendingTravelRequest) {
                try {
                    payload = JSON.parse(previewModal.dataset.pendingTravelRequest);
                } catch (err) {
                    payload = buildTravelRequestPayload();
                }
            }
            if (!payload) {
                payload = buildTravelRequestPayload();
            }

            const submitBtn = document.querySelector('#travelRequestPreviewModal .btn-success');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submitting...';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            }

            fetch('documententry-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                if (data.success) {
                    alert('Travel Request submitted successfully!');
                    closePreviewModal();
                    closeAddRelatedDocumentModal();
                    document.getElementById('addRelatedDocumentForm').reset();
                    window.pendingTravelRequest = null;
                } else {
                    alert('Error adding document: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                console.error('Error:', error);
                alert('Error adding document: ' + (error.message || 'Unknown error'));
            });
        }

        function formatDisplayDate(value) {
            const text = String(value || '').trim();
            if (!text || text === 'N/A' || text === '0000-00-00 00:00:00') {
                return '-';
            }

            const parsedDate = new Date(text);
            if (Number.isNaN(parsedDate.getTime())) {
                return '-';
            }

            return parsedDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function viewIncomingDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('incoming-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        
                        // Populate modal with data
                        currentIncomingAssignmentId = assignmentId;

                        document.getElementById('viewDocumentID').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        const senderFullName = ((assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '')).trim();
                        document.getElementById('viewSender').textContent = senderFullName || assignment.sender_name || '-';

                        const dateReceived = assignment.date_received;
                        document.getElementById('viewDateReceived').textContent = formatDisplayDate(dateReceived) !== '-'
                            ? formatDisplayDate(dateReceived)
                            : formatDisplayDate(assignment.date_sent);

                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewCreatedDate').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';

                        // Classification badge
                        const classification = assignment.classification || 'N/A';
                        let classificationClass = 'badge-info';
                        if (classification === 'Letter') classificationClass = 'badge-classification-letter';
                        else if (classification === 'Invitation') classificationClass = 'badge-classification-invitation';
                        else if (classification === 'Travel-Related Communication') classificationClass = 'badge-classification-travel';
                        else if (classification === 'Indorsement') classificationClass = 'badge-classification-indorsement';
                        document.getElementById('viewClassification').innerHTML = `<span class="badge ${classificationClass}" style="font-size: 11px; padding: 5px 10px;">${classification}</span>`;

                        // Sub-classification
                        document.getElementById('viewSubClassification').textContent = assignment.sub_classification || 'N/A';

                        // Prioritization badge
                        const priority = assignment.priority || 'N/A';
                        let priorityClass = 'badge-primary';
                        if (priority === 'Urgent') priorityClass = 'badge-warning';
                        else if (priority === 'Critical') priorityClass = 'badge-danger';
                        document.getElementById('viewPrioritization').innerHTML = `<span class="badge ${priorityClass}" style="font-size: 11px; padding: 5px 10px;">${priority}</span>`;

                        // Rejection details
                        const isReturned = (assignment.assignment_status || '').toLowerCase() === 'returned';
                        const rejectionSection = document.getElementById('rejectionDetailsSection');
                        if (isReturned) {
                            const rejectedByName = ((assignment.assigned_by_first || '') + ' ' + (assignment.assigned_by_last || '')).trim();
                            const rejectedByPosition = (assignment.assigned_by_position || '').trim();
                            let rejectedBy = rejectedByName || '-';
                            if (rejectedByPosition) {
                                rejectedBy = rejectedByName ? `${rejectedByName} (${rejectedByPosition})` : rejectedByPosition;
                            }
                            const rejectedAtRaw = assignment.rejection_at || assignment.assigned_at || null;
                            const rejectedAt = rejectedAtRaw ? new Date(rejectedAtRaw).toLocaleString() : '-';
                            const reason = extractReturnReason(assignment.assignment_notes || '');

                            document.getElementById('viewRejectedBy').textContent = rejectedBy;
                            document.getElementById('viewRejectedAt').textContent = rejectedAt;
                            document.getElementById('viewRejectedReason').textContent = reason;
                            rejectionSection.style.display = 'block';
                        } else {
                            rejectionSection.style.display = 'none';
                            document.getElementById('viewRejectedBy').textContent = '-';
                            document.getElementById('viewRejectedAt').textContent = '-';
                            document.getElementById('viewRejectedReason').textContent = '-';
                        }
                        
                        // File handling
                        const filePath = assignment.file_path;
                        if (filePath) {
                            const fileName = filePath.split('/').pop();
                            document.getElementById('viewFileName').textContent = fileName;
                            
                            const fileExt = fileName.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
                            const isPDF = fileExt === 'pdf';
                            
                            if (isImage || isPDF) {
                                document.getElementById('viewFileBtn').style.display = 'inline-block';
                            } else {
                                document.getElementById('viewFileBtn').style.display = 'none';
                            }
                            document.getElementById('downloadIncomingBtn').style.display = 'inline-block';
                        } else {
                            document.getElementById('viewFileName').textContent = 'No attachment';
                            document.getElementById('viewFileBtn').style.display = 'none';
                            document.getElementById('downloadIncomingBtn').style.display = 'none';
                        }
                        
                        // Store file path for viewing/downloading
                        window.currentIncomingFilePath = filePath || null;
                        currentIncomingAssignment = assignment;

                        // Load travel requests for this document
                        loadTravelRequests(assignment.document_id || assignment.id, isReturned);

                        const incomingAddBtn = document.getElementById('incomingAddDocumentBtn');
                        if (isIncomingRequestAssignment(assignment) && !isReturned) {
                            incomingAddBtn.style.display = 'inline-flex';
                        } else {
                            incomingAddBtn.style.display = 'none';
                        }

                        const receiveBtn = document.getElementById('incomingReceiveBtn');
                        if (assignment.assignment_status === 'Pending') {
                            receiveBtn.style.display = 'inline-flex';
                        } else {
                            receiveBtn.style.display = 'none';
                        }
                        
                        // Open modal
                        document.getElementById('viewIncomingModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewIncomingFile() {
            if (!window.currentIncomingFilePath) {
                alert('No file to view');
                return;
            }
            
            const filePath = window.currentIncomingFilePath;
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

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }

        function downloadIncomingDocument() {
            if (!window.currentIncomingFilePath) {
                alert('No file available for download');
                return;
            }

            const fileName = window.currentIncomingFilePath.split('/').pop();
            const url = 'get-document-file.php?path=' + encodeURIComponent(window.currentIncomingFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function receiveIncomingDocument() {
            if (!currentIncomingAssignmentId) {
                return;
            }

            fetch('incoming-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'receive',
                    id: currentIncomingAssignmentId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Failed to receive document');
                        return;
                    }

                    document.getElementById('viewStatus').textContent = 'Received';
                    document.getElementById('incomingReceiveBtn').style.display = 'none';

                    const row = document.querySelector('tr[data-assignment-id="' + currentIncomingAssignmentId + '"]');
                    if (row) {
                        const statusBadge = row.querySelector('.assignment-status');
                        if (statusBadge) {
                            statusBadge.textContent = 'Received';
                            statusBadge.classList.remove('badge-warning');
                            statusBadge.classList.add('badge-success');
                        }
                    }

                    alert('Document marked as received');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update status');
                });
        }

        function closeIncomingModal() {
            document.getElementById('viewIncomingModal').classList.remove('active');
        }

        function loadTravelRequests(parentDocumentId, isReturned = false) {
            // Fetch travel requests related to this document
            fetch('get-travel-requests.php?parent_document_id=' + parentDocumentId)
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('travelRequestsSection');
                    const list = document.getElementById('travelRequestsList');
                    const addBtn = document.getElementById('incomingAddDocumentBtn');
                    
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
                        if (addBtn) addBtn.style.display = isReturned ? 'none' : 'inline-block';
                    }
                })
                .catch(error => {
                    console.error('Error loading travel requests:', error);
                    document.getElementById('travelRequestsSection').style.display = 'none';
                    const addBtn = document.getElementById('incomingAddDocumentBtn');
                    if (addBtn) addBtn.style.display = isReturned ? 'none' : 'inline-block';
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

        function viewTravelRequest(requestData) {
            // Parse the request data
            let data;
            try {
                data = JSON.parse(requestData);
            } catch (e) {
                console.error('Failed to parse travel request data:', requestData);
                alert('Error loading travel request details');
                return;
            }

            // Build preview using the data
            const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';

            const meetingChecked = data.purpose_of_order === 'Meeting' ? 'X' : '';
            const conductChecked = data.purpose_of_order === 'Conduct' ? 'X' : '';
            const othersChecked = data.purpose_of_order === 'Others' ? 'X' : '';
            let othersSpecify = '';
            if (data.purpose_of_order === 'Others' && data.purpose_specify) {
                othersSpecify = ' (' + escapeText(data.purpose_specify) + ')';
            }

            let travelersHtml = '';
            if (data.travelers && data.travelers.length > 0) {
                for (let i = 0; i < data.travelers.length; i++) {
                    const traveler = data.travelers[i];
                    travelersHtml += `<tr><td style="padding: 6px; text-align: center; border: 1px solid #333;">${i + 1}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.name)}</td><td style="padding: 6px; border: 1px solid #333;">${escapeText(traveler.position)}</td></tr>`;
                }
            }

            const printableHTML = '<div style="text-align: center; margin-bottom: 20px;">' +
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
                '<td style="width: 30%; border-bottom: 1px solid #333; padding: 4px;">' + escapeText(data.officer_name) + '</td>' +
                '<td style="width: 20%; font-weight: bold; padding: 4px; text-align: right;">PURPOSE OF ORDER</td>' +
                '<td style="width: 30%; padding: 4px; font-size: 11px;">[' + meetingChecked + '] Meeting [' + conductChecked + '] Conduct [' + othersChecked + '] Others' + othersSpecify + '</td></tr></table></div>' +
                '<div style="margin-bottom: 15px;">' +
                '<div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>' +
                '<table style="width: 100%; font-size: 10px; border-collapse: collapse; border: 1px solid #333;">' +
                '<thead><tr style="background-color: #f0f0f0;"><th style="padding: 4px; border: 1px solid #333; text-align: center; width: 5%;">No.</th><th style="padding: 4px; border: 1px solid #333; width: 25%;">NAME</th><th style="padding: 4px; border: 1px solid #333; width: 20%;">POSITION</th></tr></thead><tbody>' +
                (travelersHtml || '<tr><td colspan="3" style="padding: 10px; text-align: center; border: 1px solid #333;">No travelers added</td></tr>') +
                '</tbody></table>' +
                '<div style="font-size: 9px; margin-top: 4px;">Please continue on another page if more than 10 persons are involved.</div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">TITLE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(data.event_title) + '</div></div>' +
                '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;"><div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DATE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + eventDate + '</div></div>' +
                '<div><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">PLACE OF EVENT/ACTIVITY:</div><div style="border-bottom: 1px solid #333; padding: 6px; font-size: 11px; min-height: 20px;">' + escapeText(data.event_place) + '</div></div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 4px;">DESCRIPTION/DETAILS:</div><div style="border: 1px solid #333; padding: 8px; min-height: 50px; font-size: 11px; line-height: 1.4;">' + escapeText(data.event_description || '') + '</div></div>' +
                '<div style="margin-bottom: 15px;"><div style="font-weight: bold; font-size: 11px; margin-bottom: 8px;">NOTED BY (Name & Position):</div><div style="font-size: 9px; margin-bottom: 6px; text-align: center; font-weight: 500; min-height: 16px;">' + escapeText(data.noted_by) + '</div><div style="border-bottom: 1px solid #333; padding: 6px; min-height: 35px; font-size: 11px;"></div><div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div></div>' +
                '<div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px;"><div>Generated: ' + new Date().toLocaleString() + '</div><div style="margin-top: 5px; color: #999;">© ' + new Date().getFullYear() + ' Municipality of Mercedes</div></div>';

            document.getElementById('printableFormContent').innerHTML = printableHTML;
            document.getElementById('travelRequestPreviewModal').classList.add('active');
        }


    </script>
    <script src="js/notifications.js"></script>
</body>
</html>
