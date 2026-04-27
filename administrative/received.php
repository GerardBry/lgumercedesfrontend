<?php
/**
 * Received Documents Page - Administrative Staff
 * View documents received/completed by this administrative staff
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
        d.description,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        da.office_department,
        da.notes as assignment_notes,
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
    AND da.status IN ('Received', 'Checking Documents', 'Waiting For Approval by Mayor')
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
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
<body>
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
                        <a href="documententry.php" class="admin-nav-item">
                            <i class="fas fa-file-upload"></i>
                            <span>Document Entry</span>
                        </a>
                    </li>
                    <li>
                        <a href="assign-document.php" class="admin-nav-item">
                            <i class="fas fa-file-export"></i>
                            <span>Assign Documents</span>
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
                            <span>Received</span>
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
                        <a href="archive.php" class="admin-nav-item">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="#" class="admin-nav-item" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-users"></i>
                            <span>Staff (Coming Soon)</span>
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
            <div class="admin-page">
                <div class="page-header">
                    <h2>Received Documents</h2>
                    <p>Documents you have received and completed</p>
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
                            <?php if (count($received_documents) > 0): ?>
                                <?php foreach ($received_documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? '')); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type'] ?? 'General'); ?></span>
                                        </td>
                                        <td><?php echo $doc['date_sent'] ? date('M d, Y h:i A', strtotime($doc['date_sent'])) : '-'; ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 50) . (strlen($doc['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['assignment_notes'] ?? ''), 0, 40) . (strlen($doc['assignment_notes'] ?? '') > 40 ? '...' : ''); ?></td>
                                        <td>
                                            <?php 
                                                $status = $doc['assignment_status'];
                                                $badge_class = 'badge-info';
                                                if ($status === 'Completed') $badge_class = 'badge-success';
                                                elseif ($status === 'Checking Documents' || $status === 'Waiting For Approval by Mayor') $badge_class = 'badge-warning';
                                                elseif ($status === 'Received') $badge_class = 'badge-info';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReceivedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty-state">No received documents</td>
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
                <h3>Document Details</h3>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button id="headerUpdateBtn" type="button" class="btn btn-primary" onclick="openHeaderUpdate()" style="display:none; padding:8px 16px; font-size:13px;">
                        <i class="fas fa-sync-alt"></i> Update
                    </button>
                    <button class="modal-close" onclick="closeReceivedModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Tracking Code</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewTrackingCode">-</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Document Type</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewDocumentType">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Document Title</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                <span id="viewTitle">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px;">
                                <span id="viewDescription">-</span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>From Sender</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewSender">-</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Date Sent</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewDateSent">-</span>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Received At</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewReceivedAt">-</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewStatus">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes / Instructions</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                                <span id="viewNotes">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Form Details</label>
                            <div id="viewFormDetails" style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 120px;"></div>
                        </div>
                    </div>

                    <div style="border-left: 1px solid #ddd; padding-left: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: var(--text-dark);">Digitalized Paper Format</h4>
                        <div id="viewDigitalPaperPreview"></div>
                    </div>
                </div>
            </div>

            <!-- Removed long Update Workflow Status panel as requested -->

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="openUpdateModal()"><i class="fas fa-edit"></i> Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeReceivedModal()">Close</button>
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

    <!-- File Upload Modal for Completion -->
    <div id="fileUploadModal" class="modal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Upload Completion File</h3>
                <button class="modal-close" onclick="closeFileUploadModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <p style="margin: 0 0 16px 0; color: var(--text-dark);">Before marking this document as <strong>Completed</strong>, you need to upload a file or picture.</p>
                
                <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 24px; text-align: center; background: var(--bg-light); cursor: pointer;" id="fileDropZone" onclick="document.getElementById('fileInput').click()">
                    <input type="file" id="fileInput" style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="handleFileSelect(event)">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: var(--primary-color); margin-bottom: 12px; display: block;"></i>
                    <p style="margin: 0 0 8px 0; color: var(--text-dark); font-weight: 600;">Click to upload or drag and drop</p>
                    <p style="margin: 0; color: var(--text-light); font-size: 13px;">PDF, JPG, PNG, DOC, DOCX (Max 10MB)</p>
                </div>

                <div id="filePreview" style="margin-top: 16px; display: none;">
                    <div style="padding: 12px; background: #d4edda; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <i class="fas fa-file" style="color: #28a745; margin-right: 8px;"></i>
                            <span id="fileName" style="font-weight: 600; color: #155724;"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="clearFileSelection()" style="padding: 4px 8px; font-size: 12px;">Remove</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFileUploadModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="proceedCompletionBtn" onclick="proceedCompletion()" style="background-color: #28a745; border-color: #28a745; opacity: 0.5; cursor: not-allowed;" disabled>
                    <i class="fas fa-check"></i> Proceed to Complete
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentReceivedAssignment = null;
        let pendingWorkflowStatus = '';
        let selectedFile = null;

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
            // Fetch assignment details from server
            fetch('received-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        currentReceivedAssignment = assignment;
                        const content = safeParseJson(assignment.doc_notes);
                        
                        // Populate modal with data
                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewSender').textContent = (assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '') || '-';
                        document.getElementById('viewDateSent').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        document.getElementById('viewReceivedAt').textContent = assignment.received_at ? new Date(assignment.received_at).toLocaleString() : '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewNotes').textContent = assignment.assignment_notes || '-';
                        document.getElementById('viewFormDetails').innerHTML = renderReceivedFormDetails(assignment, content);
                        document.getElementById('viewDigitalPaperPreview').innerHTML = generateReceivedPreview(assignment, content);

                        // Workflow panel removed - no longer available
                        
                        // Open modal
                        document.getElementById('viewReceivedModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function closeReceivedModal() {
            document.getElementById('viewReceivedModal').classList.remove('active');
            currentReceivedAssignment = null;
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
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload PDF, JPG, PNG, DOC, or DOCX');
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
            document.getElementById('fileUploadModal').classList.remove('active');
            clearFileSelection();
        }

        function proceedCompletion() {
            if (!selectedFile) {
                alert('Please select a file to upload');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status_with_file');
            formData.append('id', currentReceivedAssignment.id);
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

                    alert('Document marked as completed successfully!');
                    closeFileUploadModal();
                    closeReceivedModal();
                    // Redirect to finished documents list
                    window.location.href = 'finished.php';
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
    </script>
</body>
</html>
