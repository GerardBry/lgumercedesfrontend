<?php
/**
 * Outgoing Documents Page - Administrative Staff
 * View documents sent out by this administrative staff
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

// Fetch outgoing document assignments (documents assigned by this admin)
$outgoing_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        recipient.first_name as recipient_first_name,
        recipient.last_name as recipient_last_name,
        recipient.position as recipient_position,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    JOIN users recipient ON da.assigned_to = recipient.id
        WHERE da.assigned_by = ?
            AND da.status NOT IN ('Completed', 'Returned', 'Forwarded')
    ORDER BY CASE 
        WHEN da.status = 'Pending' THEN 1
        WHEN da.status = 'Received' THEN 2
        WHEN da.status = 'Checking Documents' THEN 3
        ELSE 0
    END, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $outgoing_documents[] = $row;
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
    <title>Outgoing Documents - LGU Mercedes Document Tracking System</title>
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
            overflow-x: auto;
            overflow-y: hidden;
            box-shadow: var(--shadow-md);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
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

        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
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
            margin: 5% auto;
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
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
                        <a href="outgoing.php" class="admin-nav-item active">
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
                    <h2>Outgoing Documents</h2>
                    <p>Documents you have sent to departments and staff</p>
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
                                <th>Office</th>
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
                            <?php if (count($outgoing_documents) > 0): ?>
                                <?php foreach ($outgoing_documents as $doc): ?>
                                    <?php
                                        $docNotes = $doc['doc_notes'] ? json_decode($doc['doc_notes'], true) : [];
                                        $senderName = trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $description = $doc['description'] ?? '';
                                        $priority = $docNotes['priority'] ?? 'N/A';
                                        $dateSent = $doc['date_sent'] ?? $doc['assigned_at'] ?? '';
                                        $filterDate = $dateSent ? date('Y-m-d', strtotime($dateSent)) : '';
                                        
                                        // Handle date_received with fallback and validation
                                        $dateReceived = $doc['date_received'] ?? '';
                                        if (empty($dateReceived) || strpos($dateReceived, '0000-00-00') !== false) {
                                            $dateReceived = $doc['assigned_at'] ?? '';
                                        }
                                        $dateReceivedTs = $dateReceived ? strtotime($dateReceived) : false;
                                        $displayDateReceived = '';
                                        if ($dateReceivedTs && $dateReceivedTs > 0) {
                                            $displayDateReceived = date('M d, Y', $dateReceivedTs);
                                        }
                                        
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($description ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priority); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['office_department'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 80) . (strlen($doc['description'] ?? '') > 80 ? '...' : ''); ?></td>
                                        <td><?php echo $displayDateReceived ?: '-'; ?></td>
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
                                                $priorityValue = $doc['priority'] ?? 'N/A';
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
                                                elseif ($status === 'Completed') $badge_class = 'badge-success';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewOutgoingDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="11" class="empty-state">No outgoing documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="11" class="empty-state">No outgoing documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Outgoing Document Modal -->
    <div id="viewOutgoingModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeOutgoingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="max-height: 600px; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Tracking Code</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewDocumentID">-</span>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Subject / Title</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewTitle">-</span>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">From Sender</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewSender">-</span>
                            </div>
                        </div>

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
                                    <span id="viewStatus">-</span>
                                </div>
                            </div>
                        </div>

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

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Office/Department</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDepartment">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Recipient</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewRecipient">-</span>
                                </div>
                            </div>
                        </div>

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

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Description</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px; font-size: 15px;">
                                <span id="viewDescription">-</span>
                            </div>
                        </div>

                        <div id="fileSection" style="display: block;">
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Attached File</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                <span id="viewFileName" style="flex: 1; font-size: 14px; color: var(--text-dark);">-</span>
                                <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewOutgoingFile()" style="display: none;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-sm btn-info" id="downloadFileBtn" onclick="downloadOutgoingFile()">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>

                        <div id="travelRequestsSection" style="display: none; border-top: 2px solid var(--primary-color); padding-top: 16px; margin-top: 16px;">
                            <h4 style="margin-top: 0; margin-bottom: 12px; color: var(--primary-color);"><i class="fas fa-plane"></i> Submitted Travel Requests</h4>
                            <div id="travelRequestsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
                        </div>

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
                <button type="button" class="btn btn-secondary" onclick="closeOutgoingModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Hidden File Input for Quick Upload -->
    <input type="file" id="quickUploadInput" accept="image/*,.pdf,.doc,.docx" onchange="handleQuickUpload(event)" style="display: none;">



    <!-- File Viewer Modal for Outgoing -->
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
                <button type="button" class="btn btn-info" onclick="downloadOutgoingFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Travel Request Details Modal -->
    <div id="travelRequestDetailsModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Travel Request Details</h3>
                <button class="modal-close" onclick="closeTravelRequestModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="max-height: 600px; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <!-- Officer Name -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Officer Name</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="trOfficerName">-</span>
                            </div>
                        </div>

                        <!-- Order Type & Purpose -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Order Type</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="trOrderType">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Purpose of Order</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="trPurpose">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Event Title -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Event Title</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="trEventTitle">-</span>
                            </div>
                        </div>

                        <!-- Event Date & Location -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Event Date</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="trEventDate">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Location</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="trLocation">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Travelers -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Travelers Involved</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md);">
                                <table style="width: 100%; font-size: 12px; border-collapse: collapse; border: 1px solid #ddd;">
                                    <thead>
                                        <tr style="background-color: #f5f5f5;">
                                            <th style="padding: 8px; border: 1px solid #ddd; text-align: center; width: 5%;">No.</th>
                                            <th style="padding: 8px; border: 1px solid #ddd; width: 45%;">Name</th>
                                            <th style="padding: 8px; border: 1px solid #ddd; width: 50%;">Position</th>
                                        </tr>
                                    </thead>
                                    <tbody id="trTravelersBody">
                                        <tr><td colspan="3" style="text-align: center; padding: 20px; border: 1px solid #ddd;">No travelers added</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Description</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px; font-size: 15px; white-space: pre-wrap; line-height: 1.5;">
                                <span id="trDescription">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-success" onclick="generateTravelOrderFromModal()">
                    <i class="fas fa-file-pdf"></i> Generate Order
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeTravelRequestModal()">Close</button>
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

        let currentOutgoingAssignment = null;

        function escapeText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function safeParseJson(text) {
            try {
                return JSON.parse(text || '{}');
            } catch (e) {
                return {};
            }
        }



        function markDocumentAsCompleted() {
            if (!currentOutgoingAssignment) {
                alert('No document selected');
                return;
            }
            
            const assignmentId = currentOutgoingAssignment.id;
            const btn = document.getElementById('markCompletedMainBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';
            
            fetch('outgoing-handler.php', {
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
                    alert('Document marked as Completed successfully!');
                    document.getElementById('viewOutgoingModal').classList.remove('active');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update'));
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

        window.handleQuickUpload = function(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            console.log('File selected for upload:', file.name, file.type, file.size);
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Allowed: JPEG, PNG, PDF, DOC, DOCX');
                event.target.value = '';
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit');
                event.target.value = '';
                return;
            }
            
            if (!currentOutgoingAssignment) {
                alert('No document selected');
                return;
            }
            
            const assignmentId = currentOutgoingAssignment.id;
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('assignment_id', assignmentId);
            formData.append('file', file);
            formData.append('notes', 'Document uploaded by administrative staff');
            
            const uploadBtn = document.getElementById('uploadBtn');
            const inputElement = document.getElementById('quickUploadInput');
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            console.log('Starting upload for assignment:', assignmentId);
            
            fetch('outgoing-handler.php', {
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
                    alert('File uploaded successfully!');
                    // Reset file input BEFORE reloading - this allows upload again
                    inputElement.value = '';
                    // Reload uploaded files section (this will hide upload button automatically)
                    loadUploadedFiles(assignmentId);
                } else {
                    alert('Error: ' + (data.message || 'Upload failed'));
                    console.error('Upload error:', data);
                }
            })
            .catch(error => {
                alert('Error uploading file: ' + error.message);
                console.error('Upload error:', error);
            })
            .finally(() => {
                // Ensure button is re-enabled and file input is reset
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                if (inputElement.value) {
                    inputElement.value = '';
                }
            });
        };

        function formatDateOrDash(value) {
            if (!value) return '-';
            const date = new Date(value);
            return !isNaN(date.getTime())
                ? date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : '-';
        }

        function escapeText(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function loadTravelRequests(parentDocumentId) {
            fetch('../get-travel-requests.php?parent_document_id=' + parentDocumentId)
                .then(response => response.json())
                .then(data => {
                    console.log('API Response from get-travel-requests:', data);
                    
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
                        // Note: Don't control upload button here - let loadUploadedFiles() handle it
                    } else {
                        section.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading travel requests:', error);
                    document.getElementById('travelRequestsSection').style.display = 'none';
                });
        }

        function createTravelRequestCard(request, data) {
            console.log('createTravelRequestCard - request:', request);
            console.log('createTravelRequestCard - data (parsed notes):', data);
            
            const card = document.createElement('div');
            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9;';
            
            let travelersHtml = '';
            if (data.travelers && data.travelers.length > 0) {
                travelersHtml = data.travelers.map(t => {
                    const days = t.days ? ` - ${escapeText(t.days)} day(s)` : '';
                    return `<li>${escapeText(t.name)} (${escapeText(t.position)})${days}</li>`;
                }).join('');
            }
            
            const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
            const submitDate = request.date_sent ? new Date(request.date_sent).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
            const travelRequestPayload = Object.assign({}, data, {
                noted_by: request.noted_by || data.noted_by || ''
            });
            
            card.innerHTML = `
                <div style="margin-bottom: 8px;">
                    <h5 style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: var(--primary-color);">
                        Travel Request: ${escapeText(data.event_title || 'Untitled')}
                    </h5>
                    <p style="margin: 0; font-size: 11px; color: #666;">
                        Submitted on: ${submitDate}
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
                        ${eventDate}
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
                
                <div style="font-size: 11px; margin-bottom: 10px;">
                    <span style="font-weight: 600; color: #666;">Description:</span><br>
                    ${escapeText(data.event_description || '-')}
                </div>

                <div style="font-size: 11px; margin-bottom: 10px;">
                    <span style="font-weight: 600; color: #666;">Noted By:</span><br>
                    ${escapeText((request.noted_by || data.noted_by || '').trim() || 'Not specified')}
                </div>
                
                <div style="border-top: 1px solid #e5e7eb; padding-top: 8px;">
                    <button class="btn btn-sm btn-info" onclick="window.openTravelRequestModal('${encodeURIComponent(JSON.stringify(travelRequestPayload)).replace(/'/g, '&apos;')}')" style="width: 100%; padding: 6px 12px; font-size: 12px; white-space: nowrap;">
                        <i class="fas fa-eye"></i> View
                    </button>
                </div>
            `;
            
            return card;
        }

        function loadUploadedFiles(assignmentId) {
            fetch('get-document-uploads.php?assignment_id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    const section = document.getElementById('uploadedFilesSection');
                    const list = document.getElementById('uploadedFilesList');
                    const markCompletedBtn = document.getElementById('markCompletedMainBtn');
                    const uploadBtn = document.getElementById('uploadBtn');
                    
                    if (data.success && data.uploads && data.uploads.length > 0) {
                        list.innerHTML = '';
                        data.uploads.forEach(upload => {
                            const fileName = upload.file_path.split('/').pop();
                            const fileExt = fileName.split('.').pop().toLowerCase();
                            const card = document.createElement('div');
                            card.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9; display: flex; gap: 12px; align-items: flex-start;';
                            card.innerHTML = `
                                <div style="flex-grow: 1;">
                                    <div style="font-weight: 600; font-size: 12px; color: #333;">${escapeText(fileName)}</div>
                                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                        Uploaded by: ${escapeText(upload.uploaded_by || 'Unknown')}<br>
                                        On: ${new Date(upload.uploaded_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                                    </div>
                                </div>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <button class="btn btn-sm btn-info" onclick="window.viewUploadedFileOutgoing('${encodeURIComponent(upload.file_path)}', '${fileExt}')" style="padding: 4px 8px; font-size: 11px;"><i class="fas fa-eye"></i> View</button>
                                    <button class="btn btn-sm btn-secondary" onclick="window.downloadUploadedFileOutgoing('${encodeURIComponent(upload.file_path)}', '${fileName}')" style="padding: 4px 8px; font-size: 11px;"><i class="fas fa-download"></i> Download</button>
                                    <button class="btn btn-sm btn-danger" onclick="window.deleteUploadedFileOutgoing(${upload.id}, ${assignmentId})" style="padding: 4px 8px; font-size: 11px; background: #e74c3c; border: none; color: white;"><i class="fas fa-trash"></i> Remove</button>
                                </div>
                            `;
                            list.appendChild(card);
                        });
                        section.style.display = 'block';
                        // Show mark as completed button when files are uploaded
                        markCompletedBtn.style.display = 'inline-block';
                        // HIDE upload button when files exist
                        uploadBtn.style.display = 'none';
                    } else {
                        section.style.display = 'none';
                        // Hide mark as completed if no uploads
                        markCompletedBtn.style.display = 'none';
                        // SHOW upload button when no files
                        uploadBtn.style.display = 'inline-block';
                    }
                })
                .catch(error => {
                    console.error('Error loading uploaded files:', error);
                    document.getElementById('uploadedFilesSection').style.display = 'none';
                    document.getElementById('markCompletedMainBtn').style.display = 'none';
                    // Show upload button on error
                    document.getElementById('uploadBtn').style.display = 'inline-block';
                });
        }

        function downloadUploadedFile(filePath) {
            const fileName = filePath.split('/').pop();
            const url = '../get-document-file.php?path=' + encodeURIComponent(filePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function viewOutgoingDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('outgoing-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        const doc_data = assignment.doc_notes ? safeParseJson(assignment.doc_notes) : {};
                        currentOutgoingAssignment = assignment;
                        
                        document.getElementById('viewDocumentID').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        
                        const senderName = ((assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '')).trim() || '-';
                        document.getElementById('viewSender').textContent = senderName;
                        
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        
                        document.getElementById('viewClassification').textContent = assignment.classification || '-';
                        document.getElementById('viewSubClassification').textContent = assignment.sub_classification || '-';
                        document.getElementById('viewPrioritization').textContent = assignment.priority || '-';
                        
                        document.getElementById('viewDepartment').textContent = assignment.office_department || '-';
                        const recipientName = ((assignment.recipient_first_name || '') + ' ' + (assignment.recipient_last_name || '')).trim() || '-';
                        document.getElementById('viewRecipient').textContent = recipientName;
                        
                        let dateReceived = assignment.date_received;
                        if (!dateReceived || dateReceived.startsWith('0000-00-00')) {
                            dateReceived = assignment.assigned_at || '';
                        }
                        document.getElementById('viewDateReceived').textContent = formatDateOrDash(dateReceived);
                        
                        const statusText = assignment.assignment_status || '-';
                        let statusBadgeClass = 'badge-primary';
                        if (statusText === 'Approved' || statusText === 'Received') {
                            statusBadgeClass = 'badge-success';
                        } else if (statusText === 'Pending') {
                            statusBadgeClass = 'badge-warning';
                        } else if (statusText === 'Returned') {
                            statusBadgeClass = 'badge-danger';
                        }
                        document.getElementById('viewStatus').innerHTML = `<span class="badge ${statusBadgeClass}" style="font-size: 11px; padding: 5px 10px;">${statusText}</span>`;
                        
                        document.getElementById('viewCreatedDate').textContent = assignment.date_sent ? formatDateOrDash(assignment.date_sent) : formatDateOrDash(assignment.assigned_at || '');
                        
                        if (doc_data.file_path) {
                            const fileExt = doc_data.file_path.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
                            const fileName = doc_data.file_path.split('/').pop();
                            
                            window.currentOutgoingFilePath = doc_data.file_path;
                            document.getElementById('fileSection').style.display = 'block';
                            document.getElementById('viewFileName').textContent = fileName;
                            document.getElementById('viewFileBtn').style.display = isImage ? 'inline-flex' : 'none';
                        } else {
                            document.getElementById('fileSection').style.display = 'none';
                            document.getElementById('viewFileName').textContent = '-';
                        }
                        
                        // Load travel requests from department staff
                        if (assignment.document_id) {
                            loadTravelRequests(assignment.document_id);
                            loadUploadedFiles(assignment.id);
                        }
                        
                        document.getElementById('viewOutgoingModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewOutgoingFile() {
            if (!window.currentOutgoingFilePath) {
                alert('No file to view');
                return;
            }
            
            const filePath = window.currentOutgoingFilePath;
            const fileName = filePath.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isPDF = fileExt === 'pdf';
            
            if (isPDF) {
                window.open('../view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else {
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

        function downloadOutgoingFile() {
            if (!window.currentOutgoingFilePath) {
                alert('No file to download');
                return;
            }
            const fileName = window.currentOutgoingFilePath.split('/').pop();
            const url = '../get-document-file.php?path=' + encodeURIComponent(window.currentOutgoingFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeOutgoingModal() {
            document.getElementById('viewOutgoingModal').classList.remove('active');
        }

        function openTravelRequestModal(dataStr) {
            try {
                console.log('openTravelRequestModal called with dataStr:', dataStr);
                const data = JSON.parse(decodeURIComponent(dataStr));
                console.log('Decoded data in modal:', data);
                window.currentTravelRequestData = data;  // Assign to window object so Generate button can access it
                
                // Create modal overlay
                const modalOverlay = document.createElement('div');
                modalOverlay.id = 'travelRequestViewModal';
                modalOverlay.style.cssText = 'display: flex; position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 3200; overflow-y: auto;';
                
                // Build travelers HTML
                let travelersHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersHtml = data.travelers.map((t, idx) => {
                        return '<tr><td style="padding: 6px; text-align: center; border: 1px solid #333;">' + (idx + 1) + '</td><td style="padding: 6px; border: 1px solid #333;">' + escapeText(t.name) + '</td><td style="padding: 6px; border: 1px solid #333;">' + escapeText(t.position) + '</td></tr>';
                    }).join('');
                } else {
                    travelersHtml = '<tr><td colspan="3" style="padding: 6px; text-align: center; border: 1px solid #333;">No travelers listed</td></tr>';
                }
                
                const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
                const notedByText = (data.noted_by || '').trim() || 'Not specified';
                
                // Build modal HTML
                modalOverlay.innerHTML = `
                    <div style="position: relative; background: white; border-radius: 8px; max-width: 900px; width: 95%; max-height: 95vh; overflow-y: auto;">
                        <div style="position: sticky; top: 0; padding: 12px 20px; background: white; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; z-index: 100;">
                            <h3 style="margin: 0; color: var(--primary-color); font-size: 16px;">Travel Request - Digital Format</h3>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button onclick="window.generateTravelOrder(window.currentTravelRequestData);" class="btn btn-sm btn-success" style="padding: 6px 12px; font-size: 12px; margin: 0;">
                                    <i class="fas fa-file-pdf"></i> Generate Order
                                </button>
                                <button onclick="window.printTravelRequestFromCard(window.currentTravelRequestData);" class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 12px; margin: 0;">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button onclick="document.getElementById('travelRequestViewModal').remove();" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666; padding: 0; line-height: 1;">×</button>
                            </div>
                        </div>
                        <div style="padding: 20px; overflow-y: auto;">
                            <div style="font-family: Arial, sans-serif; background: white; padding: 40px; max-width: 850px; width: 100%; line-height: 1.6; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px;">
                                <!-- Header -->
                                <div style="text-align: center; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 15px;">
                                    <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;" onerror="this.style.display='none'">
                                    <div style="text-align: center;">
                                        <div style="font-size: 11px; color: #666;">MUNICIPALITY OF MERCEDES</div>
                                        <div style="font-size: 11px; color: #666;">OFFICE OF THE MAYOR</div>
                                        <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">REQUEST FOR TRAVEL AND OFFICE ORDERS</div>
                                    </div>
                                </div>
                                
                                <!-- Info Section -->
                                <div style="border: 1px solid #333; padding: 15px; margin-bottom: 20px;">
                                    <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                                        <tbody><tr>
                                            <td style="width: 20%; font-weight: bold; padding: 6px;">NAME OF OFFICE:</td>
                                            <td style="width: 30%; border-bottom: 1px solid #333; padding: 6px;">${escapeText(data.officer_name || '-')}</td>
                                            <td style="width: 20%; font-weight: bold; padding: 6px; text-align: right;">PURPOSE OF ORDER</td>
                                            <td style="width: 30%; padding: 6px; font-size: 11px;">${escapeText(data.purpose_of_order || '-')}</td>
                                        </tr>
                                    </tbody></table>
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
                                    <div style="font-size: 10px; margin-bottom: 6px; text-align: center; font-weight: 600; min-height: 16px; color: #222;">${escapeText(notedByText)}</div>
                                    <div style="border-bottom: 1px solid #333; padding: 6px; min-height: 45px; font-size: 11px;"></div>
                                    <div style="font-size: 9px; margin-top: 2px; text-align: center;">Signature</div>
                                </div>
                                
                                <!-- Footer -->
                                <div style="text-align: right; font-size: 9px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; color: #666;">
                                    <div>Generated: ${new Date().toLocaleString()}</div>
                                    <div style="margin-top: 4px;">© ${new Date().getFullYear()} Municipality of Mercedes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Close on background click
                modalOverlay.onclick = function(e) {
                    if (e.target === modalOverlay) {
                        modalOverlay.remove();
                    }
                };
                
                document.body.appendChild(modalOverlay);
            } catch (e) {
                alert('Error viewing travel request: ' + e.message);
                console.error('View error:', e);
            }
        }

        function printTravelRequestFromCard(data) {
            try {
                const eventDate = data.event_date ? new Date(data.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
                
                let travelersHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersHtml = data.travelers.map((t, idx) => {
                        return '<tr><td style="padding: 6px; text-align: center; border: 1px solid #333;">' + (idx + 1) + '</td><td style="padding: 6px; border: 1px solid #333;">' + escapeText(t.name) + '</td><td style="padding: 6px; border: 1px solid #333;">' + escapeText(t.position) + '</td></tr>';
                    }).join('');
                } else {
                    travelersHtml = '<tr><td colspan="3" style="padding: 6px; text-align: center; border: 1px solid #333;">No travelers listed</td></tr>';
                }
                
                const printWindow = window.open('', '', 'width=900,height=1100');
                const printContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Travel Request Form</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 30px; font-size: 11px; line-height: 1.6; }
                            .header { text-align: center; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 15px; }
                            .header img { height: 50px; width: auto; }
                            .header-text { text-align: center; }
                            .header-text div:first-child { font-size: 10px; color: #666; }
                            .header-text div:nth-child(2) { font-size: 10px; color: #666; }
                            .header-text div:last-child { font-weight: bold; font-size: 12px; margin-top: 4px; }
                            table { border-collapse: collapse; width: 100%; }
                            th, td { border: 1px solid #333; padding: 6px; }
                            th { background-color: #f0f0f0; text-align: center; }
                            .info-section { border: 1px solid #333; padding: 15px; margin: 20px 0; }
                            .section-title { font-weight: bold; margin: 15px 0 10px 0; }
                            .field-label { font-weight: bold; margin-bottom: 4px; }
                            .field-value { border-bottom: 1px solid #333; padding: 6px; min-height: 20px; margin-bottom: 15px; }
                            .signature-section { margin-top: 30px; }
                            .signature-line { border-bottom: 1px solid #333; height: 50px; margin: 20px 0; }
                            .footer { text-align: right; font-size: 9px; margin-top: 30px; padding-top: 10px; border-top: 1px solid #333; color: #666; }
                            @media print { body { padding: 20px; } .header { margin-bottom: 20px; } .header img { height: 40px; } }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" onerror="this.style.display='none'">
                            <div class="header-text">
                                <div>MUNICIPALITY OF MERCEDES</div>
                                <div>OFFICE OF THE MAYOR</div>
                                <div>REQUEST FOR TRAVEL AND OFFICE ORDERS</div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <table style="width: 100%;">
                                <tr>
                                    <td style="width: 20%; font-weight: bold;">NAME OF OFFICE:</td>
                                    <td style="width: 30%; border-bottom: 1px solid #333;">${escapeText(data.officer_name || '-')}</td>
                                    <td style="width: 20%; font-weight: bold; text-align: right;">PURPOSE OF ORDER:</td>
                                    <td style="width: 30%;">${escapeText(data.purpose_of_order || '-')}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="section-title">NAMES INVOLVED IN THE EVENT/ACTIVITY:</div>
                        <table>
                            <thead>
                                <tr style="background-color: #f0f0f0;">
                                    <th style="width: 5%; text-align: center;">No.</th>
                                    <th style="width: 45%;">NAME</th>
                                    <th style="width: 50%;">POSITION</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${travelersHtml}
                            </tbody>
                        </table>
                        <div style="font-size: 9px; margin-top: 8px; color: #666;">Please continue on another page if more than 10 persons are involved.</div>
                        
                        <div style="margin-top: 20px;">
                            <div class="field-label">TITLE OF EVENT/ACTIVITY:</div>
                            <div class="field-value">${escapeText(data.event_title || '-')}</div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <div class="field-label">DATE OF EVENT/ACTIVITY:</div>
                                    <div class="field-value">${eventDate}</div>
                                </div>
                                <div>
                                    <div class="field-label">PLACE OF EVENT/ACTIVITY:</div>
                                    <div class="field-value">${escapeText(data.event_place || '-')}</div>
                                </div>
                            </div>
                            
                            <div class="field-label">DESCRIPTION/DETAILS:</div>
                            <div style="border: 1px solid #333; padding: 8px; min-height: 60px; white-space: pre-wrap; line-height: 1.4;">${escapeText(data.event_description || '-')}</div>
                        </div>
                        
                        <div class="signature-section">
                            <div class="field-label">NOTED BY (Name & Position):</div>
                            <div class="signature-line"></div>
                            <div style="text-align: center; font-size: 9px;">Signature</div>
                        </div>
                        
                        <div class="footer">
                            <div>Generated: ${new Date().toLocaleString()}</div>
                            <div style="margin-top: 4px;">© ${new Date().getFullYear()} Municipality of Mercedes</div>
                        </div>
                    </body>
                    </html>
                `;
                
                printWindow.document.write(printContent);
                printWindow.document.close();
                setTimeout(() => printWindow.print(), 500);
            } catch (e) {
                alert('Error printing travel request: ' + e.message);
                console.error('Print error:', e);
            }
        }

        function closeTravelRequestModal() {
            document.getElementById('travelRequestDetailsModal').classList.remove('active');
            currentTravelRequestData = null;
        }

        function generateTravelOrderFromModal() {
            if (currentTravelRequestData) {
                generateTravelOrder(currentTravelRequestData);
                closeTravelRequestModal();
            } else {
                alert('Travel request data not found');
            }
        }

        function generateTravelOrderFromForm() {
            if (currentTravelRequestData) {
                generateTravelOrder(currentTravelRequestData);
            } else {
                alert('Travel request data not found');
            }
        }

        function viewTravelRequest(dataStr) {
            // For backward compatibility - redirects to openTravelRequestModal
            openTravelRequestModal(dataStr);
        }

        function generateTravelOrder(data) {
            try {
                console.log('generateTravelOrder called with data:', data);
                
                if (typeof data === 'string') {
                    try {
                        data = JSON.parse(decodeURIComponent(data));
                    } catch (e) {
                        data = JSON.parse(data);
                    }
                }

                console.log('Parsed data:', data);
                console.log('Data.travelers:', data ? data.travelers : 'data is null/undefined');
                
                const today = new Date();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const year = String(today.getFullYear()).slice(-2);
                const randomNum = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
                const docNumber = `${month}${day}${year}-${randomNum}A`;

                const currentDate = today.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: '2-digit'
                });

                let travelersListHtml = '';
                if (data.travelers && data.travelers.length > 0) {
                    travelersListHtml = data.travelers.map(t => {
                        return `<div style="margin: 3px 0;">${escapeText(t.name)}, ${escapeText(t.position)}</div>`;
                    }).join('');
                } else {
                    travelersListHtml = '<div style="margin: 3px 0;">No travelers listed</div>';
                }

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
                                <img src="../img/LGU-Mercedes-Official-Logo.png" alt="Logo" style="height: 60px; width: auto;" onerror="this.style.display='none'">
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

        // Global variable to store current travel request data
        let currentTravelRequestData = null;

        window.viewTravelRequest = viewTravelRequest;
        window.generateTravelOrder = generateTravelOrder;
        window.openTravelRequestModal = openTravelRequestModal;
        window.printTravelRequestFromCard = printTravelRequestFromCard;
        window.closeTravelRequestModal = closeTravelRequestModal;
        window.generateTravelOrderFromModal = generateTravelOrderFromModal;
        window.generateTravelOrderFromForm = generateTravelOrderFromForm;

        window.viewUploadedFileOutgoing = function(filePath, fileExt) {
            filePath = decodeURIComponent(filePath);
            window.currentOutgoingFilePath = filePath;
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

        window.downloadUploadedFileOutgoing = function(filePath, fileName) {
            filePath = decodeURIComponent(filePath);
            const url = 'get-document-file.php?path=' + encodeURIComponent(filePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        window.deleteUploadedFileOutgoing = function(uploadId, assignmentId) {
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
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>