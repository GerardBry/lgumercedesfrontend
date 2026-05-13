<?php
/**
 * Received Documents Page - Administrative Staff
 * View documents received/completed by this administrative staff
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

// Fetch received/completed document assignments
$received_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.tracking_number,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.document_type,
        d.date_sent,
        d.date_received,
        d.notes as doc_notes,
        d.classification,
        d.sub_classification,
        d.priority,
        d.file_path,
        d.sender_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
        da.completed_at,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        sender.role as sender_role,
        assigner.first_name as assigner_first_name,
        assigner.last_name as assigner_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    WHERE da.assigned_to = ? 
    AND d.status IN ('Approved')
    AND da.status != 'Completed'
    ORDER BY COALESCE(da.received_at, da.assigned_at) DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $received_documents[] = $row;
    }
    $stmt->close();
}

$conn->close();
// Debug: echo count($received_documents) . " documents found";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .badge-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-progress {
            background-color: #e3f2fd;
            color: #1565c0;
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
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
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
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease;
            flex-shrink: 0;
        }

        .modal-content.modal-large {
            max-width: 1040px;
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
            background: #fff;
            position: sticky;
            bottom: 0;
        }

        .workflow-panel {
            margin-top: 12px;
            margin-bottom: 10px;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #fafafa;
        }

        .workflow-panel select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
            font-size: 13px;
            font-family: inherit;
            background: #fff;
        }

        .workflow-panel select:focus {
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
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: var(--text-dark);
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            font-size: 18px;
            flex: 0 0 auto;
        }

        .modal-close:hover {
            background: #e5e7eb;
            color: #111827;
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
                        <a href="received.php" class="admin-nav-item active">
                            <i class="fas fa-envelope-open"></i>
                            <span>Approved</span>
                        </a>
                    </li>
                                            <li>
                            <a href="returned.php" class="admin-nav-item">
                                <i class="fas fa-undo"></i>
                                <span>Returned</span>
                            </a>
                        </li>
                    <li>
                        <a href="finished.php" class="admin-nav-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
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
                    <h2>Approved Documents</h2>
                    <p>Documents approved by administrative staff</p>
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
                                        $senderName = $doc['sender_name'] ?? trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $description = $doc['description'] ?? '';
                                        $priorityValue = $doc['priority'] ?? 'Normal';
                                        $filterDate = $doc['date_received'] ? date('Y-m-d', strtotime($doc['date_received'])) : '';
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($description ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 50) . (strlen($doc['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo $doc['date_received'] ? date('M d, Y', strtotime($doc['date_received'])) : '-'; ?></td>
                                        <td><?php echo !empty($doc['classification']) ? '<span class="badge badge-info">' . htmlspecialchars($doc['classification']) . '</span>' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($doc['sub_classification'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                                $priority = $doc['priority'] ?? 'Normal';
                                                $priority_class = 'badge-primary';
                                                if ($priority === 'Urgent') $priority_class = 'badge-warning';
                                                elseif ($priority === 'Critical') $priority_class = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">Approved</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReceivedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" class="empty-state">No approved documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" class="empty-state">No approved documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Received Document Modal -->
    <div id="viewReceivedModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details - Approved</h3>
                <button class="modal-close" onclick="closeReceivedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="max-height: 600px; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <!-- Tracking Code -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Tracking Code</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewDocumentID">-</span>
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

                        <!-- Date Received & Status -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Date Received</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDateReceived">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Status</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span class="badge badge-success">Approved</span>
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

                        <!-- Prioritization & Created Date -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Prioritization</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewPrioritization">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Created Date</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewCreatedDate">-</span>
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
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Attached File</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                <span id="viewFileName" style="flex: 1; font-size: 14px; color: var(--text-dark);">-</span>
                                <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewReceivedFile()" style="display: none;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-sm btn-info" id="downloadReceivedBtn" onclick="downloadReceivedDocument()">
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
                <button type="button" class="btn btn-primary" id="uploadBtn" onclick="document.getElementById('quickUploadInput').click()" style="display: none;">
                    <i class="fas fa-upload"></i> Upload
                </button>
                <button type="button" class="btn btn-success" id="markCompletedMainBtn" onclick="markDocumentAsCompleted()" style="display: none;">
                    <i class="fas fa-check"></i> Mark as Completed
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeReceivedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Hidden File Input for Quick Upload -->
    <input type="file" id="quickUploadInput" accept="image/*,.pdf,.doc,.docx" onchange="handleQuickUpload(event)" style="display: none;">

    <!-- File Viewer Modal for Received -->
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
                <button type="button" class="btn btn-info" onclick="downloadReceivedFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Update Document Status</h3>
                <button class="modal-close" onclick="closeUpdateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="updateStatusSelect" style="font-weight: 600; margin-bottom: 8px; display: block;">Select New Status</label>
                    <select id="updateStatusSelect" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px;">
                        <option value="">-- Choose Status --</option>
                    </select>
                </div>
                <p style="margin: 0; color: var(--text-light); font-size: 13px;"><i class="fas fa-info-circle"></i> Click "Confirm" to proceed to verification.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="proceedUpdateVerification()">Confirm</button>
            </div>
        </div>
    </div>

    <div id="updateConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Verify Status Update</h3>
                <button class="modal-close" onclick="closeUpdateConfirmModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <p style="margin: 0 0 12px 0; color: var(--text-dark); font-weight: 600;">You are about to update the status to:</p>
                <div id="updateConfirmText" style="padding: 12px 14px; background: var(--bg-light); border-left: 4px solid var(--primary-color); border-radius: var(--radius-md); font-weight: 600; color: var(--primary-color); margin-bottom: 16px;">-</div>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: var(--radius-md); padding: 12px; display: flex; gap: 10px; align-items: flex-start;">
                    <i class="fas fa-exclamation-triangle" style="color: #ff6b6b; margin-top: 2px; flex-shrink: 0;"></i>
                    <div style="font-size: 13px; color: #856404;">
                        <strong>This is the 2nd verification step.</strong> Please confirm that you want to update the status. This action cannot be easily undone.
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpdateConfirmModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmUpdateStatus()" style="background-color: #28a745; border-color: #28a745;"><i class="fas fa-check"></i> Confirm Update</button>
            </div>
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

        let currentReceivedAssignment = null;
        let pendingWorkflowStatus = '';
        let selectedFile = null;
        let completionPreviewUrl = null;

        function safeParseJson(text) {
            try {
                return JSON.parse(text || '{}');
            } catch (e) {
                return {};
            }
        }

        function escapeText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatArray(value) {
            if (!Array.isArray(value) || value.length === 0) {
                return '-';
            }
            return value.join(', ');
        }

        function isTravelOrderType(value) {
            return value === 'Travel Order' || value === 'Travel Request';
        }

        function canUpdateWorkflow(assignment) {
            return isTravelOrderType(assignment.document_type) && (assignment.sender_role || '') === 'Department Staff';
        }

        function getNextWorkflowStatuses(currentStatus) {
            if (currentStatus === 'Received') {
                return ['Checking Documents'];
            }
            if (currentStatus === 'Checking Documents') {
                return ['Waiting For Approval by Mayor'];
            }
            if (currentStatus === 'Waiting For Approval by Mayor') {
                return ['Completed'];
            }
            return [];
        }

        function renderReceivedFormDetails(assignment, content) {
            if (!isTravelOrderType(assignment.document_type)) {
                return '<p style="margin:0; color: var(--text-light);">No structured form fields available for this document type.</p>';
            }

            return `
                <div style="display:grid; gap:8px; font-size:14px;">
                    <div><strong>Travelers:</strong> ${escapeText(formatArray(content.travelers))}</div>
                    <div><strong>From:</strong> ${escapeText(content.from || 'Municipal Mayor')}</div>
                    <div><strong>Subject:</strong> ${escapeText(content.subject || '-')}</div>
                    <div><strong>Purpose:</strong> ${escapeText(content.purpose || '-')}</div>
                    <div><strong>Destination:</strong> ${escapeText(content.destination || '-')}</div>
                    <div><strong>Start Date:</strong> ${escapeText(content.startDate || '-')}</div>
                    <div><strong>End Date:</strong> ${escapeText(content.endDate || '-')}</div>
                    <div><strong>Duration:</strong> ${escapeText(content.duration || '-')}</div>
                    <div><strong>Mode:</strong> ${escapeText(content.mode || '-')}</div>
                </div>
            `;
        }

        function generateReceivedPreview(assignment, content) {
            if (isTravelOrderType(assignment.document_type)) {
                return `
                    <div class="document-preview" style="background:#fff; border:1px solid #ddd; border-radius:10px; padding:16px;">
                        <div style="text-align:center; margin-bottom:14px;">
                            <div style="font-size:12px;">Province of Camarines Norte</div>
                            <div style="font-size:14px; font-weight:700;">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                            <div style="font-size:16px; font-weight:700; margin-top:8px;">TRAVEL ORDER</div>
                            <div style="font-size:12px; margin-top:4px;">No. ${escapeText(assignment.tracking_number || '-')}</div>
                        </div>
                        <div style="display:grid; gap:8px; font-size:13px;">
                            <div><strong>TO:</strong> ${escapeText(formatArray(content.travelers))}</div>
                            <div><strong>FROM:</strong> ${escapeText(content.from || 'Municipal Mayor')}</div>
                            <div><strong>DATE:</strong> ${escapeText(content.dateIssued || '-')}</div>
                            <div><strong>SUBJECT:</strong> ${escapeText(content.subject || 'As Stated')}</div>
                            <div>You are hereby directed to attend ${escapeText(content.purpose || '-')}.</div>
                            <div><strong>Destination:</strong> ${escapeText(content.destination || '-')}</div>
                            <div><strong>Travel Dates:</strong> ${escapeText(content.startDate || '-')} to ${escapeText(content.endDate || '-')}</div>
                            <div><strong>Duration:</strong> ${escapeText(content.duration || '-')} day(s)</div>
                            <div><strong>Mode of Transportation:</strong> ${escapeText(content.mode || '-')}</div>
                        </div>
                    </div>
                `;
            }

            return `
                <div style="background:#fff; border:1px solid #ddd; border-radius:10px; padding:16px;">
                    <h3 style="margin:0 0 8px 0;">${escapeText(assignment.title || '-')}</h3>
                    <p style="margin:0 0 8px 0;">${escapeText(assignment.description || '-')}</p>
                    <p style="margin:0; color: var(--text-light);">Document Type: ${escapeText(assignment.document_type || '-')}</p>
                </div>
            `;
        }

        function viewReceivedDocument(assignmentId) {
            // Store assignment ID for later use while loading details
            currentReceivedAssignment = { assignment_id: assignmentId };
            
            // Fetch document details from server
            fetch('../get-document-details.php?assignment_id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const doc = data.document;
                        const content = safeParseJson(doc.notes);
                        
                        currentReceivedDocument = doc;
                        currentReceivedAssignment = doc;
                        
                        // Populate modal with data
                        document.getElementById('viewDocumentID').textContent = doc.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        document.getElementById('viewSender').textContent = doc.sender_name || ((doc.sender_first_name || '') + ' ' + (doc.sender_last_name || '')) || '-';
                        document.getElementById('viewDateReceived').textContent = doc.date_received ? new Date(doc.date_received).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                        
                        // Classification
                        if (doc.classification) {
                            document.getElementById('viewClassification').innerHTML = '<span class="badge badge-info">' + escapeText(doc.classification) + '</span>';
                        } else {
                            document.getElementById('viewClassification').innerHTML = '-';
                        }
                        
                        document.getElementById('viewSubClassification').textContent = doc.sub_classification || '-';
                        
                        // Prioritization
                        if (doc.priority) {
                            let badgeClass = 'badge-primary';
                            if (doc.priority === 'Urgent') badgeClass = 'badge-warning';
                            else if (doc.priority === 'Critical') badgeClass = 'badge-danger';
                            document.getElementById('viewPrioritization').innerHTML = '<span class="badge ' + badgeClass + '">' + escapeText(doc.priority) + '</span>';
                        } else {
                            document.getElementById('viewPrioritization').innerHTML = '<span class="badge badge-primary">Normal</span>';
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
                        
                        // Load travel requests if this document exists
                        if (doc.document_id || doc.id) {
                            loadTravelRequests(doc.document_id || doc.id);
                        }
                        
                        // Load uploaded files
                        const assignmentId = doc.assignment_id || doc.id;
                        if (assignmentId) {
                            loadUploadedFiles(assignmentId);
                        }

                        const mainCompleteBtn = document.getElementById('markCompletedMainBtn');
                        if (mainCompleteBtn) {
                            mainCompleteBtn.style.display = (doc.status && doc.status !== 'Completed') ? 'inline-block' : 'none';
                        }
                        
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

        function viewReceivedFile() {
            if (!window.currentReceivedFilePath) {
                alert('No file to view');
                return;
            }
            
            const filePath = window.currentReceivedFilePath;
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

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }

        function downloadReceivedFile() {
            if (!window.currentReceivedFilePath) {
                alert('No file to download');
                return;
            }
            const fileName = window.currentReceivedFilePath.split('/').pop();
            const url = '../get-document-file.php?path=' + encodeURIComponent(window.currentReceivedFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadReceivedDocument() {
            downloadReceivedFile();
        }

        function closeReceivedModal() {
            try {
                const modal = document.getElementById('viewReceivedModal');
                if (modal) {
                    modal.classList.remove('active');
                }
                currentReceivedAssignment = null;
            } catch (error) {
                console.error('Error in closeReceivedModal:', error);
            }
        }

        function openFileUploadModal() {
            try {
                const fileUploadModal = document.getElementById('fileUploadModal');
                const viewReceivedModal = document.getElementById('viewReceivedModal');
                
                if (!fileUploadModal) {
                    console.error('fileUploadModal element not found');
                    alert('Error: Upload modal not found');
                    return;
                }
                
                // Show file upload modal
                fileUploadModal.classList.add('active');
                fileUploadModal.style.display = 'flex';
                
                // Hide the document view modal
                if (viewReceivedModal) {
                    viewReceivedModal.classList.remove('active');
                }
                
                // Clear any previous file selection
                if (typeof clearFileSelection === 'function') {
                    clearFileSelection();
                }
            } catch (error) {
                console.error('Error in openFileUploadModal:', error);
                alert('Error opening upload modal: ' + error.message);
            }
        }

        function handlePhotoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            // Check if file is an image
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }
            
            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Data = e.target.result;
                
                // Get the current assignment ID
                let assignmentId = currentReceivedAssignment;
                if (!assignmentId) {
                    alert('Please open a document first');
                    return;
                }
                
                // Get document title for travel order
                let docTitle = currentReceivedDocument ? currentReceivedDocument.title : 'Event';
                
                // Send to backend
                fetch('./submit-completion-photo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        assignment_id: assignmentId,
                        photo: base64Data,
                        filename: file.name
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success alert instead of modal
                        alert('Photo uploaded successfully! Document will be marked as completed.');
                        // Reload the page to reflect changes
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to upload photo'));
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    alert('Error uploading photo: ' + error.message);
                });
            };
            reader.readAsDataURL(file);
        }

        function showCompletionModal(photoData, docTitle, uploaderName = 'Administrative Assistant', uploadTime = null) {
            // Modal removed - this function is no longer needed
        }

        function closeCompletionModal() {
            // Modal removed - this function is no longer needed
            if (completionPreviewUrl) {
                URL.revokeObjectURL(completionPreviewUrl);
                completionPreviewUrl = null;
            }
        }

        function updateMarkCompletedButton(show) {
            const btn = document.getElementById('markCompletedMainBtn');
            if (btn) {
                btn.style.display = show ? 'inline-block' : 'none';
            }
        }

        function generateCompletionTravelOrder() {
            if (!currentReceivedDocument) {
                alert('No document data available');
                return;
            }
            
            // Get the first travel request from the list
            const travelRequestCards = document.querySelectorAll('#travelRequestsList > div');
            if (travelRequestCards.length === 0) {
                alert('No travel requests found for this document');
                return;
            }
            
            const firstCard = travelRequestCards[0];
            const viewButton = firstCard.querySelector('.btn-info');
            if (viewButton && viewButton.dataset.request) {
                // Trigger the view function which will handle generation
                viewTravelRequest(viewButton.dataset.request);
                
                // Automatically generate order after modal shows
                setTimeout(() => {
                    const generateOrderBtn = document.querySelector('#travelRequestViewModal .btn-success');
                    if (generateOrderBtn) {
                        generateOrderBtn.click();
                    }
                }, 500);
                
                closeCompletionModal();
            } else {
                alert('Unable to find travel request data');
            }
        }

        function markDocumentAsCompleted() {
            if (!currentReceivedDocument || !currentReceivedDocument.assignment_id) {
                alert('Error: Document data not found');
                return;
            }

            const assignmentId = currentReceivedDocument.assignment_id || currentReceivedDocument.id;
            const btn = document.getElementById('markCompletedBtn') || document.getElementById('markCompletedMainBtn');
            if (!btn) {
                alert('Error: Action button not found');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            fetch('received-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_completed',
                    assignment_id: assignmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success
                    alert('Document marked as Completed successfully!');
                    
                    // Close the main modal
                    document.getElementById('viewReceivedModal').classList.remove('active');
                    
                    // Reload the documents list
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update status'));
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                alert('Error updating status: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
                console.error('Error:', error);
            });
        }

        function escapeText(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function safeParseJson(jsonStr) {
            try {
                return JSON.parse(jsonStr);
            } catch (e) {
                return {};
            }
        }

        function loadTravelRequests(parentDocumentId) {
            fetch('../get-travel-requests.php?parent_document_id=' + parentDocumentId)
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('travelRequestsSection');
                    const list = document.getElementById('travelRequestsList');
                    const uploadBtn = document.getElementById('uploadBtn');
                    
                    if (data.success && data.requests && data.requests.length > 0) {
                        list.innerHTML = '';
                        data.requests.forEach(request => {
                            const requestData = safeParseJson(request.notes);
                            const card = createTravelRequestCard(request, requestData);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                        // Show Upload button only when there are travel requests
                        uploadBtn.style.display = 'inline-block';
                    } else {
                        section.style.display = 'none';
                        // Hide Upload button when there are no travel requests
                        uploadBtn.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading travel requests:', error);
                    document.getElementById('travelRequestsSection').style.display = 'none';
                    document.getElementById('uploadBtn').style.display = 'none';
                });
        }

        function createTravelRequestCard(request, data) {
            const card = document.createElement('div');
            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9;';
            
            let travelersHtml = '';
            if (data.travelers && data.travelers.length > 0) {
                travelersHtml = data.travelers.map(t => {
                    return `<li>${escapeText(t.name)} (${escapeText(t.position)})</li>`;
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
                            <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;">
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
                                <button onclick="generateTravelOrder(document.getElementById('travelRequestViewModal').travelRequestData);" class="btn btn-sm btn-success" style="padding: 6px 12px; font-size: 12px; margin: 0;">
                                    <i class="fas fa-file-pdf"></i> Generate Order
                                </button>
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
                                <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;">
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
                            <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo">
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

        function openHeaderUpdate() {
            // Workflow panel removed - feature no longer available
            alert('Workflow status update feature has been removed.');
        }

        function openWorkflowConfirm() {
            // Workflow feature removed - this function is no longer used
        }

        function closeWorkflowConfirm() {
            // Workflow feature removed - this function is no longer used
        }

        function confirmWorkflowUpdate() {
            // Workflow feature removed - this function is no longer used
        }

        // NEW UPDATE STATUS FUNCTIONS
        function getNextStatus(currentStatus) {
            if (currentStatus === 'Received') {
                return ['Checking Documents'];
            }
            if (currentStatus === 'Checking Documents') {
                return ['Waiting For Approval by Mayor'];
            }
            if (currentStatus === 'Waiting For Approval by Mayor') {
                return ['Completed'];
            }
            return [];
        }

        function openUpdateModal() {
            if (!currentReceivedAssignment) {
                alert('No document selected');
                return;
            }

            const currentStatus = currentReceivedAssignment.assignment_status || '';
            const nextStatuses = getNextStatus(currentStatus);

            if (nextStatuses.length === 0) {
                alert('This document cannot be updated further.');
                return;
            }

            // Populate dropdown with only the next available status
            const selectElement = document.getElementById('updateStatusSelect');
            selectElement.innerHTML = '<option value="">-- Choose Status --</option>';
            
            nextStatuses.forEach(status => {
                const option = document.createElement('option');
                option.value = status;
                option.textContent = status;
                selectElement.appendChild(option);
            });

            document.getElementById('updateStatusModal').classList.add('active');
        }

        function closeUpdateModal() {
            document.getElementById('updateStatusModal').classList.remove('active');
            document.getElementById('updateStatusSelect').value = '';
        }

        function proceedUpdateVerification() {
            const selectedStatus = document.getElementById('updateStatusSelect').value;
            if (!selectedStatus) {
                alert('Please select a status to update.');
                return;
            }
            
            // Show the confirmation modal
            document.getElementById('updateConfirmText').textContent = selectedStatus;
            document.getElementById('updateStatusModal').classList.remove('active');
            document.getElementById('updateConfirmModal').classList.add('active');
        }

        function closeUpdateConfirmModal() {
            document.getElementById('updateConfirmModal').classList.remove('active');
            document.getElementById('updateStatusSelect').value = '';
        }

        function confirmUpdateStatus() {
            const selectedStatus = document.getElementById('updateStatusSelect').value;
            
            if (!selectedStatus || !currentReceivedAssignment) {
                alert('Invalid status or document');
                return;
            }

            // If completing, require file upload first
            if (selectedStatus === 'Completed') {
                closeUpdateConfirmModal();
                clearFileSelection();
                document.getElementById('fileUploadModal').classList.add('active');
                return;
            }

            // For other statuses, proceed directly
            performStatusUpdate(selectedStatus);
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit');
                return;
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload JPG or PNG only');
                return;
            }

            selectedFile = file;
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('filePreview').style.display = 'block';
            document.getElementById('proceedCompletionBtn').disabled = false;
            document.getElementById('proceedCompletionBtn').style.opacity = '1';
            document.getElementById('proceedCompletionBtn').style.cursor = 'pointer';
        }

        function clearFileSelection() {
            selectedFile = null;
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('proceedCompletionBtn').disabled = true;
            document.getElementById('proceedCompletionBtn').style.opacity = '0.5';
            document.getElementById('proceedCompletionBtn').style.cursor = 'not-allowed';
        }

        function closeFileUploadModal() {
            try {
                const fileUploadModal = document.getElementById('fileUploadModal');
                if (fileUploadModal) {
                    fileUploadModal.classList.remove('active');
                }
                clearFileSelection();
            } catch (error) {
                console.error('Error in closeFileUploadModal:', error);
            }
        }

        function proceedCompletion() {
            if (!selectedFile) {
                alert('Please select a file to upload');
                return;
            }

            // Use assignment_id if available, otherwise use id
            const assignmentId = currentReceivedAssignment?.assignment_id || currentReceivedAssignment?.id;
            if (!assignmentId) {
                alert('No assignment found. Please open a document first.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status_with_file');
            formData.append('id', assignmentId);
            formData.append('status', 'Completed');
            formData.append('file', selectedFile);

            // Send update request with file
            fetch('received-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Failed to update status');
                        return;
                    }

                    completionPreviewUrl = URL.createObjectURL(selectedFile);
                    const title = currentReceivedDocument ? currentReceivedDocument.title : 'Document';
                    alert('Status updated successfully!');
                    closeFileUploadModal();
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update status');
                });
        }

        function performStatusUpdate(status) {
            // Send update request
            fetch('received-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_status',
                    id: currentReceivedAssignment.id,
                    status: status
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Failed to update status');
                        return;
                    }

                    alert('Status updated successfully!');
                    closeUpdateConfirmModal();
                    closeReceivedModal();
                    // If status became Completed, go to finished list; otherwise reload
                    if (status === 'Completed') {
                        window.location.href = 'finished.php';
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update status');
                });
        }

        function loadReceivedDocuments() {
            // Fallback for legacy upload handlers that call this function.
            location.reload();
        }

        // UPLOAD MODAL FUNCTIONS
        let selectedUploadFile = null;

        function openUploadModal() {
            console.log('openUploadModal called');
            console.log('currentReceivedAssignment:', currentReceivedAssignment);
            
            // Check if we have assignment data
            if (!currentReceivedAssignment) {
                console.error('No currentReceivedAssignment');
                alert('Please open a document first');
                return;
            }
            
            // Get assignment ID from various possible properties
            const assignmentId = currentReceivedAssignment.assignment_id || currentReceivedAssignment.id;
            console.log('assignmentId:', assignmentId);
            
            if (!assignmentId) {
                console.error('No assignment ID found in currentReceivedAssignment');
                alert('Error: Could not determine assignment ID. Please close and reopen the document.');
                return;
            }
            
            // Reset form
            try {
                document.getElementById('uploadFileInput').value = '';
                document.getElementById('uploadNotes').value = '';
                document.getElementById('uploadPreview').style.display = 'none';
                document.getElementById('uploadPreviewImage').style.display = 'none';
                document.getElementById('uploadPreviewFileContent').style.display = 'none';
                selectedUploadFile = null;
                updateUploadButtonState();
                
                // Open modal
                const modal = document.getElementById('uploadPictureModal');
                if (!modal) {
                    console.error('uploadPictureModal element not found');
                    alert('Error: Upload modal not found in page');
                    return;
                }
                
                modal.style.display = 'flex';
                console.log('Modal opened successfully');
                
                // Setup drag and drop
                setupDragDrop();
            } catch (error) {
                console.error('Error in openUploadModal:', error);
                alert('Error opening upload modal: ' + error.message);
            }
        }

        function closeUploadModal() {
            const modal = document.getElementById('uploadPictureModal');
            if (modal) {
                modal.style.display = 'none';
            }
            selectedUploadFile = null;
        }

        function setupDragDrop() {
            const dropZone = document.getElementById('uploadDropZone');
            if (!dropZone) return;

            dropZone.addEventListener('click', () => {
                document.getElementById('uploadFileInput').click();
            });

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.background = '#e3f2fd';
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.style.background = '#fafafa';
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.background = '#fafafa';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    document.getElementById('uploadFileInput').files = files;
                    handleUploadFileSelect({ target: { files: files } });
                }
            });
        }

        function handleUploadFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            console.log('File selected:', file.name, file.type, file.size);

            // Check file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload image, PDF, or Word document');
                return;
            }

            selectedUploadFile = file;
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewDiv = document.getElementById('uploadPreview');
                const previewImage = document.getElementById('uploadPreviewImage');
                const previewFileContent = document.getElementById('uploadPreviewFileContent');
                
                previewDiv.style.display = 'block';
                
                if (file.type.startsWith('image/')) {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                    previewFileContent.style.display = 'none';
                } else {
                    previewImage.style.display = 'none';
                    previewFileContent.style.display = 'block';
                    document.getElementById('uploadFileName').textContent = file.name;
                    document.getElementById('uploadFileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
                }
                
                updateUploadButtonState();
            };
            
            reader.readAsDataURL(file);
        }

        function updateUploadButtonState() {
            const submitBtn = document.getElementById('uploadSubmitBtn');
            if (selectedUploadFile) {
                submitBtn.disabled = false;
                submitBtn.style.background = 'var(--primary-color)';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.background = '#ccc';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        function submitUpload() {
            if (!selectedUploadFile) {
                alert('Please select a file to upload');
                return;
            }

            const assignmentId = currentReceivedAssignment?.assignment_id || currentReceivedAssignment?.id;
            if (!assignmentId) {
                alert('No document selected');
                return;
            }

            const notes = document.getElementById('uploadNotes').value;
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('assignment_id', assignmentId);
            formData.append('file', selectedUploadFile);
            formData.append('notes', notes);

            // Show loading state
            const submitBtn = document.getElementById('uploadSubmitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            console.log('Starting upload for assignment:', assignmentId);

            fetch('received-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Upload response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Upload response:', data);
                if (data.success) {
                    alert('✓ File uploaded successfully!');
                    closeUploadModal();
                    // Reload the uploaded files list
                    loadUploadedFiles(assignmentId);
                } else {
                    alert('Error: ' + (data.message || 'Failed to upload file'));
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Error uploading file: ' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        window.handleQuickUpload = function(event) {
            // Prevent uploads if upload button or hidden file input is disabled (existing upload present)
            const preUploadBtn = document.querySelector('[onclick*="quickUploadInput"]');
            const quickInput = document.getElementById('quickUploadInput');
            if ((preUploadBtn && preUploadBtn.disabled) || (quickInput && quickInput.disabled)) {
                alert('You have already uploaded a file for this assignment. Delete it first to upload another.');
                // Reset file input
                if (event && event.target) event.target.value = '';
                return;
            }
            const file = event.target.files[0];
            if (!file) return;

            console.log('File selected:', file.name);

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Allowed: JPEG, PNG, PDF, DOC, DOCX');
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit');
                return;
            }

            // Get assignment ID from current context
            let assignmentId = null;
            if (currentReceivedAssignment && currentReceivedAssignment.assignment_id) {
                assignmentId = currentReceivedAssignment.assignment_id;
            } else if (currentReceivedAssignment && currentReceivedAssignment.id) {
                assignmentId = currentReceivedAssignment.id;
            }

            if (!assignmentId) {
                alert('Unable to determine assignment ID');
                console.error('Assignment ID not found:', currentReceivedAssignment);
                return;
            }

            // Create FormData and upload directly
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('assignment_id', assignmentId);
            formData.append('file', file);

            // Show uploading status
            const uploadBtn = document.querySelector('[onclick*="quickUploadInput"]');
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            }

            // Upload file
            fetch('received-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (uploadBtn) {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                }

                if (data.success) {
                    alert('File uploaded successfully!');
                    if (uploadBtn) {
                        uploadBtn.style.display = 'none';
                    }
                    
                    // Reload uploaded files
                    if (assignmentId) {
                        window.loadUploadedFiles(assignmentId);
                    }
                    // Reset file input
                    event.target.value = '';
                } else {
                    alert('Upload failed: ' + (data.message || 'Unknown error'));
                    console.error('Upload error:', data);
                }
            })
            .catch(error => {
                if (uploadBtn) {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                }
                alert('Upload error: ' + error.message);
                console.error('Upload error:', error);
            });
        };

        window.loadUploadedFiles = function(assignmentId) {
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
                            const card = window.createUploadedFileCard(upload);
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                        updateMarkCompletedButton(true);
                        // Hide quick upload button and disable hidden file input when there are existing uploads
                        const uploadBtn = document.getElementById('uploadBtn');
                        const quickInput = document.getElementById('quickUploadInput');
                        if (uploadBtn) {
                            uploadBtn.style.display = 'none';
                            uploadBtn.disabled = true;
                            uploadBtn.title = 'Remove existing upload(s) to enable another upload';
                        }
                        if (quickInput) {
                            quickInput.disabled = true;
                        }
                    } else {
                        section.style.display = 'none';
                        updateMarkCompletedButton(false);
                        // Show quick upload button and enable hidden file input when there are no uploads
                        const uploadBtn = document.getElementById('uploadBtn');
                        const quickInput = document.getElementById('quickUploadInput');
                        if (uploadBtn) {
                            uploadBtn.style.display = 'inline-block';
                            uploadBtn.disabled = false;
                            uploadBtn.title = '';
                        }
                        if (quickInput) {
                            quickInput.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading uploads:', error);
                    const section = document.getElementById('uploadedFilesSection');
                    const uploadBtn = document.getElementById('uploadBtn');
                    if (section) {
                        section.style.display = 'none';
                    }
                    if (uploadBtn) {
                        uploadBtn.style.display = 'none';
                    }
                });
        };

        window.createUploadedFileCard = function(upload) {
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
                        ${escapeText(fileName)}
                    </div>
                    <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                        ${uploadDate} | by ${escapeText(upload.uploaded_by || 'Staff')}
                    </div>
                    ${upload.notes ? `<div style="font-size: 12px; color: #666; font-style: italic; margin-bottom: 8px;">"${escapeText(upload.notes)}"</div>` : ''}
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-sm btn-info" onclick="window.viewUploadedFile('${encodeURIComponent(upload.file_path)}', '${fileExt}')" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="window.downloadUploadedFile('${encodeURIComponent(upload.file_path)}', '${fileName}')" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="window.deleteUploadedFile(${upload.id}, ${currentReceivedAssignment?.assignment_id || currentReceivedAssignment?.id})" style="padding: 4px 8px; font-size: 11px; background: #e74c3c; border: none; color: white;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        };

        window.deleteUploadedFile = function(uploadId, assignmentId) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            fetch('delete-document-upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    upload_id: uploadId,
                    assignment_id: assignmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File deleted successfully!');
                    window.loadUploadedFiles(assignmentId);
                } else {
                    alert('Error deleting file: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                console.error('Delete error:', error);
            });
        };

        window.viewUploadedFile = function(filePath, fileExt) {
            filePath = decodeURIComponent(filePath);
            window.currentReceivedFilePath = filePath;
            const isPDF = fileExt === 'pdf';
            
            if (isPDF) {
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                const url = 'view-document-file.php?path=' + encodeURIComponent(filePath);
                document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + filePath.split('/').pop();
                document.getElementById('fileViewerImage').src = url;
                document.getElementById('fileViewerModal').classList.add('active');
            } else {
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            }
        };

        window.downloadUploadedFile = function(filePath, fileName) {
            filePath = decodeURIComponent(filePath);
            const url = 'get-document-file.php?path=' + encodeURIComponent(filePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
