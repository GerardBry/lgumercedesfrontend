<?php
/**
 * Assign Document Page - Administrative Assistant
 * Assign documents to departments and staff
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
    // Block Mayor
    if ($_SESSION['role'] === 'Mayor') {
        header('Location: ../mayor/admin-dashboard-mayor.php');
        exit;
    }
    // Block Record Officer
    if ($_SESSION['role'] === 'Record Officer') {
        header('Location: ../record/admin-dashboard-officer.php');
        exit;
    }
}

// Only allow Administrative Assistant role
if ($_SESSION['role'] !== 'Administrative Assistant') {
    header('Location: ../login.php');
    exit;
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

// Fetch full user details from database
require_once '../config/db_connect.php';

/**
 * Function to generate unique tracking number
 * Format: LGU-YYYY-MM-DD-XXX (where XXX is a random 3-digit number)
 */
function generateTrackingNumber($conn) {
    $date_part = date('Ymd');
    $random_part = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    $tracking_number = 'LGU-' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . $random_part;
    
    // Check if this tracking number already exists
    $check_sql = "SELECT id FROM documents WHERE tracking_number = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    
    while (true) {
        $check_stmt->bind_param("s", $tracking_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $check_stmt->close();
            return $tracking_number;
        }
        
        // Generate new number if it exists
        $random_part = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $tracking_number = 'LGU-' . date('Y') . '-' . date('m') . '-' . date('d') . '-' . $random_part;
    }
}

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

// Fetch document assignments with all tracking details
$assignments = [];
$sql = "SELECT 
        da.id,
        da.document_id,
        d.id as doc_id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes,
        d.status,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        u.first_name,
        u.last_name,
        u.position,
        da.office_department,
        da.status as assignment_status,
        da.assigned_at
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    JOIN users u ON da.assigned_to = u.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    WHERE da.assigned_by = ?
    AND da.status = 'Pending'
    ORDER BY da.assigned_at DESC";

$stmt_assignments = $conn->prepare($sql);
if ($stmt_assignments) {
    $stmt_assignments->bind_param("i", $user_id);
    $stmt_assignments->execute();
    $result = $stmt_assignments->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt_assignments->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-page-container {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
        }

        .admin-sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: white;
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
            border-radius: 8px;
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .page-header-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .page-header-info p {
            font-size: 14px;
            color: var(--text-light);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
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

        .btn-success {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .btn-success:hover {
            background-color: #c8e6c9;
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
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .input-readonly {
            background-color: var(--bg-light);
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .admin-page {
                padding: 32px 24px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .btn-primary {
                width: 100%;
                justify-content: center;
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
            
            .page-header {
                flex-direction: column;
                gap: 12px;
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
            
            .page-header-info h2 {
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
    <div class="admin-page-container">
        <!-- Sidebar Navigation -->
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
                        <a href="finished.php" class="admin-nav-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                        <li>
                            <a href="returned.php" class="admin-nav-item">
                                <i class="fas fa-undo"></i>
                                <span>Returned</span>
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
                        <p class="admin-user-role"><?php echo htmlspecialchars($role); ?></p>
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
                    <div class="page-header-info">
                        <h2>Assign Documents</h2>
                        <p>Assign and route documents to departments and staff</p>
                    </div>
                    <button class="btn-primary" onclick="openAssignModal()">
                        <i class="fas fa-plus"></i> Assign Document
                    </button>
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
                        <tbody id="documentsTableBody">
                            <?php if (count($assignments) > 0): ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php
                                        $docNotes = $assignment['notes'] ? json_decode($assignment['notes'], true) : [];
                                        $senderName = trim(($assignment['sender_first_name'] ?? '') . ' ' . ($assignment['sender_last_name'] ?? ''));
                                        $trackingCode = $assignment['tracking_number'] ?? 'N/A';
                                        $description = $assignment['description'] ?? '';
                                        $priorityValue = $docNotes['priority'] ?? 'N/A';
                                        $dateSent = $assignment['date_sent'] ?? $assignment['assigned_at'] ?? '';
                                        $filterDate = $dateSent ? date('Y-m-d', strtotime($dateSent)) : '';
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($assignment['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($description ?? '') . ' ' . ($assignment['notes'] ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                        <td><?php echo htmlspecialchars(($assignment['sender_first_name'] ?? '') . ' ' . ($assignment['sender_last_name'] ?? '')); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($assignment['document_type'] ?? 'General'); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($assignment['date_sent'] ?? $assignment['assigned_at'])); ?></td>
                                        <td style="white-space: normal;"><?php echo nl2br(htmlspecialchars($assignment['description'] ?? '-')); ?></td>
                                        <td style="white-space: normal;"><?php echo nl2br(htmlspecialchars($assignment['notes'] ?? '-')); ?></td>
                                        <td>
                                            <?php 
                                                $status = $assignment['assignment_status'];
                                                $badge_class = 'badge-info';
                                                if ($status === 'Received') $badge_class = 'badge-success';
                                                elseif ($status === 'In Progress') $badge_class = 'badge-warning';
                                                elseif ($status === 'Completed') $badge_class = 'badge-success';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn-sm btn-info" onclick="viewAssignment(<?php echo $assignment['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" class="empty-state">No documents assigned yet</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" class="empty-state">No documents assigned yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Document Modal -->
    <div id="assignDocumentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Document</h3>
                <button class="modal-close" onclick="closeAssignModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="assignDocumentForm" onsubmit="handleAssignDocument(event)">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="trackingCode">Tracking Code *</label>
                            <input type="text" id="trackingCode" readonly placeholder="Auto-generated" class="input-readonly">
                        </div>

                        <div class="form-group">
                            <label for="documentType">Document Type *</label>
                            <select id="documentType" required>
                                <option value="">-- Select Document Type --</option>
                                <option value="Travel Request">Travel Request</option>
                                <option value="Executive Request">Executive Request</option>
                                <option value="Office Request">Office Request</option>
                                <option value="Memorandum">Memorandum</option>
                                <option value="Request">Request</option>
                                <option value="Report">Report</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="documentTitle">Document Title *</label>
                        <input type="text" id="documentTitle" required placeholder="Enter document title...">
                    </div>

                    <div class="form-group">
                        <label for="documentDescription">Description</label>
                        <textarea id="documentDescription" rows="3" placeholder="Enter document description..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="senderSelect">Sender *</label>
                        <select id="senderSelect" required>
                            <option value="">-- Select Sender --</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignToSelect">Assign To Department *</label>
                        <select id="assignToSelect" required onchange="loadRecipients()">
                            <option value="">-- Choose Office/Department --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="recipientSelect">Recipient *</label>
                        <select id="recipientSelect" required>
                            <option value="">-- Select Office First --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignNotes">Notes / Instructions</label>
                        <textarea id="assignNotes" rows="4" placeholder="Add any notes or special instructions..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Document Modal -->
    <div id="viewDocumentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Tracking Code</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewTrackingCode">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Document Type</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewDocumentType">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 200px;">
                        <label style="font-size: 11px;">Title</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <span id="viewTitle">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Administrative</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewSender">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Date Sent</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewDateSent">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Department Staff</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewAssignedTo">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Department</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewDepartment">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 120px;">
                        <label style="font-size: 11px;">Status</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewStatus">-</span>
                        </div>
                    </div>

                    <div class="form-group" style="min-width: 150px;">
                        <label style="font-size: 11px;">Assigned At</label>
                        <div style="padding: 8px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 13px;">
                            <span id="viewAssignedAt">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label>Description</label>
                    <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                        <span id="viewDescription">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes / Instructions</label>
                    <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                        <span id="viewNotes">-</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
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

        // Load offices on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadOffices();
            loadSenders();
            // Generate tracking code
            generateTrackingCode();
            document.getElementById('keywordFilter')?.addEventListener('input', applyFilters);
            document.getElementById('senderFilter')?.addEventListener('input', applyFilters);
            document.getElementById('priorityFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateFromFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateToFilter')?.addEventListener('change', applyFilters);
            applyFilters();
        });

        function viewAssignment(assignmentId) {
            // Fetch assignment details from server
            fetch('assign-document-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        
                        // Populate modal with data
                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewSender').textContent = (assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '') || '-';
                        document.getElementById('viewDateSent').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        document.getElementById('viewAssignedTo').textContent = (assignment.first_name || '') + ' ' + (assignment.last_name || '') || '-';
                        document.getElementById('viewDepartment').textContent = assignment.office_department || '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewAssignedAt').textContent = assignment.assigned_at ? new Date(assignment.assigned_at).toLocaleString() : '-';
                        document.getElementById('viewNotes').textContent = assignment.notes || '-';
                        
                        // Open modal
                        document.getElementById('viewDocumentModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function closeViewModal() {
            document.getElementById('viewDocumentModal').classList.remove('active');
        }

        function generateTrackingCode() {
            // Generate format: LGU-YYYY-MM-DD-XXX
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const random = String(Math.floor(Math.random() * 1000)).padStart(3, '0');
            
            const trackingCode = `LGU-${year}-${month}-${day}-${random}`;
            document.getElementById('trackingCode').value = trackingCode;
        }

        function loadSenders() {
            const senderSelect = document.getElementById('senderSelect');
            senderSelect.innerHTML = '';

            const currentSender = {
                id: <?php echo (int)$user_id; ?>,
                name: '<?php echo htmlspecialchars($first_name . ' ' . $last_name, ENT_QUOTES); ?>'
            };

            const option = document.createElement('option');
            option.value = currentSender.id;
            option.textContent = currentSender.name;
            option.selected = true;
            senderSelect.appendChild(option);
        }

        function loadOffices() {
            fetch('get-offices.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('assignToSelect');
                    select.innerHTML = '<option value="">-- Choose Office/Department --</option>';
                    
                    if (data.success && data.offices) {
                        data.offices.forEach(office => {
                            const option = document.createElement('option');
                            option.value = office;
                            option.textContent = office;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading offices:', error));
        }

        function loadRecipients() {
            const office = document.getElementById('assignToSelect').value;
            const select = document.getElementById('recipientSelect');
            
            if (!office) {
                select.innerHTML = '<option value="">-- Select Office First --</option>';
                return;
            }
            
            fetch('get-staff-by-office.php?office=' + encodeURIComponent(office))
                .then(response => response.json())
                .then(data => {
                    select.innerHTML = '<option value="">-- Choose Staff Member --</option>';
                    
                    if (data.success && data.staff) {
                        data.staff.forEach(member => {
                            const option = document.createElement('option');
                            option.value = member.id;
                            option.textContent = member.first_name + ' ' + member.last_name + ' (' + member.position + ')';
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading recipients:', error));
        }

        function openAssignModal() {
            // Generate new tracking code when opening modal
            generateTrackingCode();
            document.getElementById('assignDocumentModal').classList.add('active');
        }

        function closeAssignModal() {
            document.getElementById('assignDocumentModal').classList.remove('active');
        }

        function handleAssignDocument(event) {
            event.preventDefault();
            
            const trackingCode = document.getElementById('trackingCode').value;
            const documentType = document.getElementById('documentType').value;
            const title = document.getElementById('documentTitle').value;
            const description = document.getElementById('documentDescription').value;
            const sender = document.getElementById('senderSelect').value;
            const office = document.getElementById('assignToSelect').value;
            const recipientId = document.getElementById('recipientSelect').value;
            const notes = document.getElementById('assignNotes').value;

            // Validate required fields
            if (!documentType || !title || !sender || !office || !recipientId) {
                alert('Please fill in all required fields');
                return;
            }

            // Prepare data
            const formData = {
                trackingCode: trackingCode,
                documentType: documentType,
                title: title,
                description: description,
                sender: sender,
                office: office,
                recipientId: recipientId,
                notes: notes
            };

            // Submit to backend
            fetch('assign-document-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Document assigned successfully!');
                    closeAssignModal();
                    // Reset form
                    document.getElementById('assignDocumentForm').reset();
                    // Refresh the page to show the new assignment
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting form: ' + error.message);
            });
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('assignDocumentModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        };

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('assignDocumentModal').classList.remove('active');
            }
        });
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
