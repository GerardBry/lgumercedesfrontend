<?php
/**
 * Finished Documents Page - Administrative Staff
 * View documents that have been completed/finished
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// STRICT ROLE-BASED ACCESS CONTROL - Only Administrative Assistant allowed
if (isset($_SESSION['role'])) {
    // Block Super Admin
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: ../admin/admin-dashboard.php');
        exit;
    }
    // Block Regular users
    if ($_SESSION['role'] !== 'Administrative Assistant') {
        header('Location: ../login.php');
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
require_once '../config/db_connect.php';

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

// Fetch finished/completed document assignments
$finished_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.date_received,
        d.sender_name,
        d.classification,
        d.sub_classification,
        d.priority,
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
        da.completed_at,
        da.completion_file
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE (da.assigned_to = ? OR da.assigned_by = ?)
    AND da.status = 'Completed'
    ORDER BY da.completed_at DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $finished_documents[] = $row;
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
    <title>Finished Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
        }

        .admin-sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
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

        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
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

        .admin-nav-menu ul {
            list-style: none;
        }

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

        .admin-nav-item i {
            width: 20px;
            text-align: center;
        }

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

        .admin-user-info {
            flex: 1;
        }

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

        .logout-btn:hover {
            background-color: #d0d0d0;
        }

        .admin-main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            overflow-y: auto;
            min-height: 100vh;
        }

        .admin-page {
            padding: 40px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .page-header-left {
            flex: 1;
        }

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

        .data-table tbody tr:hover {
            background-color: var(--bg-light);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius-md);
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

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
            background-color: var(--bg-white);
            margin: 3% auto;
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
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
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-light);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 24px;
            flex: 1;
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
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        @media (max-width: 1024px) {
            .admin-page {
                padding: 32px 24px;
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 240px;
            }
            .admin-main-content {
                margin-left: 240px;
            }
            .admin-page {
                padding: 24px 16px;
            }
            .data-table {
                font-size: 12px;
            }
            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }
            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .admin-sidebar {
                width: 200px;
            }
            .admin-main-content {
                margin-left: 200px;
            }
            .admin-page {
                padding: 16px 12px;
            }
            .page-header h2 {
                font-size: 20px;
            }
            .data-table {
                font-size: 11px;
            }
            .data-table th,
            .data-table td {
                padding: 8px 4px;
            }
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body class="admin-theme">
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h1>Administrative Panel</h1>
            </div>

            <nav class="admin-nav-menu">
                <ul>
                    <li>
                        <a href="admin-dashboard-staff.php" class="admin-nav-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                        </a>
                    </li>
                                        <li>
                        <a href="trackdocument.php" class="admin-nav-item">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="incoming.php" class="admin-nav-item">
                            <i class="fas fa-inbox"></i>
                            <span>Incoming</span>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="admin-nav-item">
                            <i class="fas fa-paper-plane"></i>
                            <span>Outgoing</span>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="admin-nav-item">
                            <i class="fas fa-envelope-open"></i>
                            <span>Approved</span>
                        </a>
                    </li>
                                                                <li>
                            <a href="returned.php" class="admin-nav-item">
                                <i class="fas fa-undo"></i>
                                <span>Returned</span>
                            </a>
                    <li>
                        <a href="finished.php" class="admin-nav-item active">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                        </li>
                    <li>
                        <a href="reports.php" class="admin-nav-item">
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
                        </a>
                    </li>

                </ul>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?>
                    </div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main-content">
            <!-- Header with Notifications -->
            <div style="padding: 20px 40px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: flex-end; align-items: center; position: relative; z-index: 10;">
                <div class="header-right" style="display: flex; gap: 16px; align-items: center; position: relative;">
                    <!-- Notification Bell will be inserted here by notifications.js -->
                </div>
            </div>

            <div class="admin-page">
                <div class="page-header">
                    <div class="page-header-top">
                        <div class="page-header-left">
                            <h2>Finished Documents</h2>
                            <p>Documents that have been completed</p>
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
                            <?php if (count($finished_documents) > 0): ?>
                                <?php foreach ($finished_documents as $doc): ?>
                                    <?php
                                        $senderName = ($doc['sender_name'] ?? '') ?: trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
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
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($doc['sender_name'] ?? (($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($displayDate); ?></td>
                                        <td><?php echo !empty($doc['classification']) ? '<span class="badge badge-info">' . htmlspecialchars($doc['classification']) . '</span>' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($doc['sub_classification'] ?? '-'); ?></td>
                                        <td><?php 
                                            $priority = $doc['priority'] ?? 'Normal';
                                            $priority_class = 'badge-primary';
                                            if ($priority === 'Urgent') $priority_class = 'badge-warning';
                                            elseif ($priority === 'Critical') $priority_class = 'badge-danger';
                                            echo '<span class="badge ' . $priority_class . '">' . htmlspecialchars($priority) . '</span>';
                                        ?></td>
                                        <td>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($doc['assignment_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewFinishedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" class="empty-state">No finished documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" class="empty-state">No finished documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Finished Document Modal -->
    <div id="viewFinishedModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details - Completed</h3>
                <button class="modal-close" onclick="closeFinishedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="max-height: 70vh; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <!-- Tracking Code -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Tracking Code</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewTrackingCode">-</span>
                            </div>
                        </div>

                        <!-- Subject / Title -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Subject / Title</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewTitle">-</span>
                            </div>
                        </div>

                        <!-- From Sender -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">From Sender</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewSender">-</span>
                            </div>
                        </div>

                        <!-- Date Received & Completed At -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Date Received</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDateReceived">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Completed At</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewCompletedAt">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Classification & Sub-Classification -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Classification</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewClassification">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Sub-Classification</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewSubClassification">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Document Type & Date Sent -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Document Type</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDocumentType">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Date Sent</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDateSent">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Description</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px; font-size: 15px;">
                                <span id="viewDescription">-</span>
                            </div>
                        </div>

                        <!-- Attached File -->
                        <div id="fileSection" style="display: none;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Attached File</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                <span id="fileName" style="flex: 1; font-size: 14px; color: var(--text-dark);">-</span>
                                <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewFinishedFile()" style="display: none;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-sm btn-info" id="downloadFileBtn" onclick="downloadFinishedDocument()">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                        
                        <!-- Travel Requests Section -->
                        <div id="travelRequestsSection" style="display: none; border-top: 2px solid var(--primary-color); padding-top: 16px; margin-top: 16px;">
                            <h4 style="margin-top: 0; margin-bottom: 12px; color: var(--primary-color);"><i class="fas fa-plane"></i> Submitted Travel Requests</h4>
                            <div id="travelRequestsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
                        </div>

                        <!-- Uploaded Files/Pictures Section -->
                        <div id="uploadedFilesSection" style="display: none; border-top: 2px solid #28a745; padding-top: 16px; margin-top: 16px;">
                            <h4 style="margin-top: 0; margin-bottom: 12px; color: #28a745;"><i class="fas fa-file-upload"></i> Uploaded Pictures/Files</h4>
                            <div id="uploadedFilesList" style="display: flex; flex-direction: column; gap: 12px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFinishedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal for Finished -->
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
                <button type="button" class="btn btn-info" onclick="downloadFinishedFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>

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

        function formatNotes(notesJson) {
            if (!notesJson) return '-';

            try {
                const decoded = JSON.parse(notesJson);
                if (typeof decoded === 'object' && decoded !== null) {
                    const parts = [];
                    if (decoded.title) parts.push('Title: ' + decoded.title);
                    if (decoded.purpose) parts.push('Purpose: ' + decoded.purpose);
                    if (decoded.subject) parts.push('Subject: ' + decoded.subject);
                    if (decoded.type) parts.push('Type: ' + decoded.type);

                    return parts.length > 0 ? parts.join(' | ') : notesJson;
                }
            } catch (e) {
                // Not JSON, return as-is
            }

            return notesJson;
        }

        function isValidFinishedDate(value) {
            if (!value || value === '0000-00-00' || value === '0000-00-00 00:00:00') {
                return false;
            }

            const parsed = new Date(value);
            return !Number.isNaN(parsed.getTime());
        }

        function formatFinishedModalDate(...candidates) {
            for (const candidate of candidates) {
                if (isValidFinishedDate(candidate)) {
                    return candidate;
                }
            }

            return '';
        }

        function viewFinishedDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('../get-document-details.php?assignment_id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const doc = data.document;
                        
                        // Populate modal with data
                        document.getElementById('viewTrackingCode').textContent = doc.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        document.getElementById('viewDocumentType').textContent = doc.document_type || '-';
                        document.getElementById('viewDescription').textContent = doc.description || '-';
                        
                        // Use sender_name first, fallback to first+last name
                        document.getElementById('viewSender').textContent = doc.sender_name || ((doc.sender_first_name || '') + ' ' + (doc.sender_last_name || '')) || '-';

                        // Use the first valid date available for received date
                        const receivedDate = formatFinishedModalDate(doc.date_received, doc.received_at, doc.completed_at);
                        document.getElementById('viewDateReceived').textContent = receivedDate ? new Date(receivedDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                        document.getElementById('viewClassification').textContent = doc.classification || '-';
                        document.getElementById('viewSubClassification').textContent = doc.sub_classification || '-';
                        document.getElementById('viewCompletedAt').textContent = isValidFinishedDate(doc.completed_at) ? new Date(doc.completed_at).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                        document.getElementById('viewDateSent').textContent = isValidFinishedDate(doc.date_sent) ? new Date(doc.date_sent).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                        
                        // Handle file display
                        const fileSection = document.getElementById('fileSection');
                        if (doc.has_completion_file) {
                            const fileUrl = '../get-document-file.php?assignment_id=' + assignmentId;
                            const fileName = doc.completion_file_name || 'Uploaded paper';
                            const extension = fileName.split('.').pop().toLowerCase();
                            const imageTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
                            
                            document.getElementById('fileName').innerHTML = '<strong>' + htmlEscape(fileName) + '</strong>';
                            fileSection.style.display = 'block';
                            window.currentFinishedFilePath = doc.completion_file_path;
                            
                            if (imageTypes.includes(extension)) {
                                document.getElementById('viewFileBtn').style.display = 'inline-block';
                                document.getElementById('viewFileBtn').onclick = function() {
                                    viewFinishedFile();
                                };
                            } else {
                                document.getElementById('viewFileBtn').style.display = 'none';
                            }
                            
                            document.getElementById('downloadFileBtn').onclick = function() {
                                downloadFinishedDocument();
                            };
                        } else {
                            fileSection.style.display = 'none';
                        }
                        
                        // Fetch travel requests for this document
                        fetchTravelRequests(doc.id);
                        
                        // Fetch uploaded files for this assignment
                        fetchUploadedFiles(assignmentId);
                        
                        // Open modal
                        document.getElementById('viewFinishedModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function fetchTravelRequests(documentId) {
            fetch('../get-travel-requests.php?parent_document_id=' + documentId)
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('travelRequestsSection');
                    const list = document.getElementById('travelRequestsList');
                    
                    if (data.success && data.requests && data.requests.length > 0) {
                        list.innerHTML = '';
                        data.requests.forEach(request => {
                            const requestData = safeParseJson(request.notes);
                            const card = createTravelRequestCard(request, requestData);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading travel requests:', error);
                    document.getElementById('travelRequestsSection').style.display = 'none';
                });
        }

        function safeParseJson(str) {
            try {
                return typeof str === 'string' ? JSON.parse(str) : str;
            } catch (e) {
                return {};
            }
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
                    <span style="font-weight: 600; color: #666;">Location:</span><br>
                    ${escapeText(data.event_place || '-')}
                </div>
                
                <div style="margin-bottom: 8px; font-size: 11px;">
                    <span style="font-weight: 600; color: #666;">Travelers:</span>
                    <ul style="margin: 4px 0 0 20px; padding: 0;">
                        ${travelersHtml || '<li style="color: #999;">No travelers listed</li>'}
                    </ul>
                </div>
                
                <div style="font-size: 11px;">
                    <span style="font-weight: 600; color: #666;">Description:</span><br>
                    ${escapeText(data.event_description || '-')}
                </div>

                <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; display: flex; gap: 6px;">
                    <button class="btn btn-sm btn-info" onclick="viewTravelRequest(this.dataset.request)" style="flex: 1; padding: 4px 8px; font-size: 11px;" data-request='${JSON.stringify(data).replace(/'/g, "&apos;")}'>
                        <i class="fas fa-eye"></i> View
                    </button>
                </div>
            `;
            
            return card;
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

                const existingModal = document.getElementById('travelRequestViewModal');
                if (existingModal) {
                    existingModal.remove();
                }

                const viewModal = document.createElement('div');
                viewModal.id = 'travelRequestViewModal';
                viewModal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 3200;';
                viewModal.travelRequestData = data;
                viewModal.innerHTML = `
                    <div style="position: relative; background: white; border-radius: 8px; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto;">
                        <div style="position: sticky; top: 0; padding: 12px 20px; background: white; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; z-index: 100;">
                            <h3 style="margin: 0; color: var(--primary-color); font-size: 16px;">Travel Request - Digital Format</h3>
                            <button type="button" onclick="document.getElementById('travelRequestViewModal').remove();" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666; padding: 0;">×</button>
                        </div>
                        <div style="padding: 20px; overflow-y: auto;">
                            <div style="font-family: Arial, sans-serif; background: white; padding: 40px; max-width: 850px; width: 100%; line-height: 1.6; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; margin: 0 auto;">
                                <div style="text-align: center; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 15px;">
                                    <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 11px; color: #666;">MUNICIPALITY OF MERCEDES</div>
                                        <div style="font-size: 11px; color: #666;">OFFICE OF THE MAYOR</div>
                                        <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">REQUEST FOR TRAVEL AND OFFICE ORDERS</div>
                                    </div>
                                </div>

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

                                <div style="margin-bottom: 20px;">
                                    <div style="font-weight: bold; font-size: 11px; margin-bottom: 10px;">NOTED BY (Name & Position):</div>
                                    <div style="font-size: 9px; margin-bottom: 6px; text-align: center; font-weight: 500; min-height: 16px;">${escapeText(data.noted_by || '')}</div>
                                    <div style="border-bottom: 1px solid #333; padding: 6px; min-height: 45px; font-size: 11px;"></div>
                                    <div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div>
                                </div>

                                <div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; color: #666;">
                                    <div>Generated: ${new Date().toLocaleString()}</div>
                                    <div style="margin-top: 4px;">© ${new Date().getFullYear()} Municipality of Mercedes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(viewModal);
            } catch (e) {
                alert('Error: ' + e.message);
                console.error('View error:', e);
            }
        }

        function escapeText(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function fetchUploadedFiles(assignmentId) {
            // Fetch uploaded files from server
            console.log('Loading uploaded files for assignment:', assignmentId);
            
            fetch('get-document-uploads.php?assignment_id=' + assignmentId)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Uploads data:', data);
                    const section = document.getElementById('uploadedFilesSection');
                    const list = document.getElementById('uploadedFilesList');
                    
                    if (!section || !list) {
                        console.error('Section or list element not found');
                        return;
                    }
                    
                    if (data.success && data.uploads && data.uploads.length > 0) {
                        list.innerHTML = '';
                        data.uploads.forEach(upload => {
                            const card = createUploadedFileCard(upload);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading uploads:', error);
                    const section = document.getElementById('uploadedFilesSection');
                    if (section) {
                        section.style.display = 'none';
                    }
                });
        }

        function createUploadedFileCard(upload) {
            const card = document.createElement('div');
            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9; display: flex; gap: 12px;';
            
            const fileName = upload.file_path.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
            
            let fileIconHtml = '<i class="fas fa-file" style="font-size: 32px; color: #999;"></i>';
            if (isImage) {
                fileIconHtml = `<img src="view-document-file.php?path=${encodeURIComponent(upload.file_path)}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">`;
            } else if (fileExt === 'pdf') {
                fileIconHtml = '<i class="fas fa-file-pdf" style="font-size: 32px; color: #e74c3c;"></i>';
            } else if (['doc', 'docx'].includes(fileExt)) {
                fileIconHtml = '<i class="fas fa-file-word" style="font-size: 32px; color: #3498db;"></i>';
            }
            
            const uploadDate = new Date(upload.uploaded_at).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            card.innerHTML = `
                <div style="flex-shrink: 0;">
                    ${fileIconHtml}
                </div>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">
                        ${htmlEscape(fileName)}
                    </div>
                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                        ${uploadDate} | by ${htmlEscape(upload.uploaded_by || 'Staff')}
                    </div>
                    ${upload.notes ? `<div style="font-size: 12px; color: #666; font-style: italic; margin-bottom: 8px;">"${htmlEscape(upload.notes)}"</div>` : ''}
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-sm btn-info" onclick="viewUploadedFileFinished('${encodeURIComponent(upload.file_path)}', '${fileExt}')" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="downloadUploadedFileFinished('${encodeURIComponent(upload.file_path)}', '${fileName}')" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }

        function viewUploadedFileFinished(filePath, fileExt) {
            const decodedPath = decodeURIComponent(filePath);
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
            
            if (isImage) {
                const viewerImg = document.getElementById('fileViewerImage');
                viewerImg.src = 'view-document-file.php?path=' + encodeURIComponent(decodedPath);
                document.getElementById('fileViewerTitle').textContent = 'File Viewer';
                document.getElementById('fileViewerModal').classList.add('active');
            } else if (fileExt === 'pdf') {
                window.open('view-document-file.php?path=' + encodeURIComponent(decodedPath), '_blank');
            } else {
                alert('Preview not available for this file type. Please download to view.');
            }
        }

        function downloadUploadedFileFinished(filePath, fileName) {
            const link = document.createElement('a');
            link.href = 'view-document-file.php?path=' + encodeURIComponent(decodeURIComponent(filePath));
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function htmlEscape(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function closeFinishedModal() {
            document.getElementById('viewFinishedModal').classList.remove('active');
        }

        function viewFinishedFile() {
            if (!window.currentFinishedFilePath) {
                alert('No file to view');
                return;
            }
            
            const filePath = window.currentFinishedFilePath;
            const fileName = filePath.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isPDF = fileExt === 'pdf';
            
            if (isPDF) {
                // Open PDF in new tab
                window.open('../view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else {
                // Show image in modal
                const url = '../view-document-file.php?path=' + encodeURIComponent(filePath);
                document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
                document.getElementById('fileViewerImage').src = url;
                document.getElementById('fileViewerModal').classList.add('active');
            }
        }

        function downloadFinishedDocument() {
            if (!window.currentFinishedFilePath) {
                alert('No file to download');
                return;
            }
            const fileName = window.currentFinishedFilePath.split('/').pop();
            const url = '../get-document-file.php?path=' + encodeURIComponent(window.currentFinishedFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }
    </script>
    
    <!-- Notification System -->
    <script src="../js/notifications.js"></script>
</body>
</html>