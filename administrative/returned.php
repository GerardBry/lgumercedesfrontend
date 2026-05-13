<?php
/**
 * Returned Documents Page - Administrative Staff
 * View documents that have been returned by recipients
 */
date_default_timezone_set('Asia/Manila');
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

// Fetch returned document assignments
$returned_documents = [];
$hasReturnedAt = false;
$returnedAtCheck = $conn->query("SHOW COLUMNS FROM document_assignments LIKE 'returned_at'");
if ($returnedAtCheck && $returnedAtCheck->num_rows > 0) {
    $hasReturnedAt = true;
}

$returnedAtSelect = $hasReturnedAt ? "IFNULL(da.returned_at, da.assigned_at) as returned_at" : "da.assigned_at as returned_at";
$returnedAtOrder = $hasReturnedAt ? "IFNULL(da.returned_at, da.assigned_at) DESC, " : "da.assigned_at DESC, ";
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        d.description,
        d.tracking_number,
        d.date_sent,
    d.date_received,
    d.classification,
    d.sub_classification,
    d.priority,
    d.file_path,
    d.sender_name,
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        assigner.first_name as assigned_by_first,
        assigner.last_name as assigned_by_last,
        assigner.position as assigned_by_position,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        {$returnedAtSelect}
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    WHERE da.assigned_to = ? 
    AND da.status = 'Returned'
    ORDER BY {$returnedAtOrder} da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $returned_documents[] = $row;
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
    <title>Returned Documents - LGU Mercedes Document Tracking System</title>
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

        .search-section {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #fff;
        }

        .search-box input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 13px;
            background: transparent;
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

        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
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

        .modal-large {
            max-width: 900px;
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
                        <a href="returned.php" class="admin-nav-item active">
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
                    <h2>Returned Documents</h2>
                    <p>Documents that have been returned by recipients</p>
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
                            <?php if (count($returned_documents) > 0): ?>
                                <?php foreach ($returned_documents as $doc): ?>
                                    <?php
                                        $senderName = trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        if ($senderName === '') {
                                            $senderName = trim($doc['sender_name'] ?? '');
                                        }
                                        if ($senderName === '') {
                                            $senderName = 'N/A';
                                        }
                                        $priorityValue = $doc['priority'] ?? 'N/A';
                                        $dateReceived = $doc['date_received'] ?? $doc['returned_at'] ?? $doc['date_sent'] ?? '';
                                        $filterDate = $dateReceived ? date('Y-m-d', strtotime($dateReceived)) : '';
                                        $keywordSource = strtolower(trim(($doc['tracking_number'] ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($doc['description'] ?? '') . ' ' . ($doc['assignment_notes'] ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo $dateReceived ? date('M d, Y', strtotime($dateReceived)) : '-'; ?></td>
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
                                            <span class="badge badge-warning"><?php echo htmlspecialchars($doc['assignment_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReturnedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="empty-state">No returned documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Returned Document Modal -->
    <div id="viewReturnedModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeReturnedModal()">
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
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Date Sent</label>
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

                <div class="detail-row" id="fileSection" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 14px; color: #333; flex: 1;" id="fileName">-</span>
                        <button type="button" class="btn btn-sm btn-warning" onclick="viewReturnedFile()" id="viewFileBtn" style="display: none;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="downloadReturnedFile()" id="downloadFileBtn" style="display: none;">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>

                <div id="rejectionDetailsSection" style="display: none; margin-bottom: 16px; background: #fff4f4; border: 1px solid #f4cccc; border-radius: 8px; padding: 12px 14px;">
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
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReturnedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal for Returned -->
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
                <button type="button" class="btn btn-info" onclick="downloadReturnedFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>

    <script>
        function escapeText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function extractReturnReason(notesText) {
            const text = String(notesText || '').trim();
            if (!text) return '-';

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

        function viewReturnedDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('returned-handler.php?action=view&id=' + assignmentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Request failed with status ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        let doc_data = {};
                        if (assignment.doc_notes) {
                            try {
                                doc_data = JSON.parse(assignment.doc_notes);
                            } catch (error) {
                                doc_data = {};
                            }
                        }
                        
                        // Populate modal with data
                        document.getElementById('viewDocumentID').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        const senderFullName = ((assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '')).trim();
                        document.getElementById('viewSender').textContent = senderFullName || assignment.sender_name || '-';
                        document.getElementById('viewDateReceived').textContent = assignment.date_sent_formatted || (assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-');
                        document.getElementById('viewCreatedDate').textContent = assignment.date_sent_formatted || (assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-');
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';

                        const classification = assignment.classification || 'N/A';
                        let classificationClass = 'badge-info';
                        if (classification === 'Letter') classificationClass = 'badge-classification-letter';
                        else if (classification === 'Invitation') classificationClass = 'badge-classification-invitation';
                        else if (classification === 'Travel-Related Communication') classificationClass = 'badge-classification-travel';
                        else if (classification === 'Indorsement') classificationClass = 'badge-classification-indorsement';
                        document.getElementById('viewClassification').innerHTML = '<span class="badge ' + classificationClass + '" style="font-size: 11px; padding: 5px 10px;">' + escapeText(classification) + '</span>';

                        document.getElementById('viewSubClassification').textContent = assignment.sub_classification || 'N/A';

                        const priority = assignment.priority || 'N/A';
                        let priorityClass = 'badge-primary';
                        if (priority === 'Urgent') priorityClass = 'badge-warning';
                        else if (priority === 'Critical') priorityClass = 'badge-danger';
                        document.getElementById('viewPrioritization').innerHTML = '<span class="badge ' + priorityClass + '" style="font-size: 11px; padding: 5px 10px;">' + escapeText(priority) + '</span>';

                        const rejectedByName = ((assignment.assigned_by_first || '') + ' ' + (assignment.assigned_by_last || '')).trim();
                        const rejectedByPosition = (assignment.assigned_by_position || '').trim();
                        const rejectedBy = rejectedByPosition
                            ? (rejectedByName ? `${rejectedByName} (${rejectedByPosition})` : rejectedByPosition)
                            : (rejectedByName || '-');
                        const rejectionReason = extractReturnReason(assignment.assignment_notes || assignment.doc_notes || '');
                        const rejectionSection = document.getElementById('rejectionDetailsSection');

                        if ((assignment.assignment_status || '').toLowerCase() === 'returned') {
                            document.getElementById('viewRejectedBy').textContent = rejectedBy;
                            const rejectedAtRaw = assignment.rejection_at || assignment.returned_at || null;
                            document.getElementById('viewRejectedAt').textContent = rejectedAtRaw ? new Date(rejectedAtRaw).toLocaleString() : '-';
                            document.getElementById('viewRejectedReason').textContent = rejectionReason;
                            rejectionSection.style.display = 'block';
                        } else {
                            rejectionSection.style.display = 'none';
                        }

                        // Handle file display
                        const filePath = assignment.file_path || doc_data.file_path;
                        if (filePath) {
                            const fileExt = filePath.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
                            const fileName = filePath.split('/').pop();
                            
                            window.currentReturnedFilePath = filePath;
                            document.getElementById('fileSection').style.display = 'block';
                            document.getElementById('fileName').textContent = fileName;
                            document.getElementById('viewFileBtn').style.display = isImage ? 'inline-flex' : 'none';
                            document.getElementById('downloadFileBtn').style.display = 'inline-flex';
                        } else {
                            document.getElementById('fileSection').style.display = 'none';
                            document.getElementById('downloadFileBtn').style.display = 'none';
                        }
                        
                        // Open modal
                        document.getElementById('viewReturnedModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewReturnedFile() {
            if (!window.currentReturnedFilePath) {
                alert('No file to view');
                return;
            }
            const url = 'view-document-file.php?path=' + encodeURIComponent(window.currentReturnedFilePath);
            const fileName = window.currentReturnedFilePath.split('/').pop();
            document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
            document.getElementById('fileViewerImage').src = url;
            document.getElementById('fileViewerModal').classList.add('active');
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }

        function downloadReturnedFile() {
            if (!window.currentReturnedFilePath) {
                alert('No file to download');
                return;
            }
            const fileName = window.currentReturnedFilePath.split('/').pop();
            const url = 'get-document-file.php?path=' + encodeURIComponent(window.currentReturnedFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeReturnedModal() {
            document.getElementById('viewReturnedModal').classList.remove('active');
        }

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
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>