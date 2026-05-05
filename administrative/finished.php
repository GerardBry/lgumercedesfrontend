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
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.completed_at,
        da.completion_file
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE da.assigned_to = ? 
    AND da.status = 'Completed'
    ORDER BY da.completed_at DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
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
                        <a href="received.php" class="admin-nav-item">
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
                        <a href="finished.php" class="admin-nav-item active">
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
                            <?php if (count($finished_documents) > 0): ?>
                                <?php foreach ($finished_documents as $doc): ?>
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
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php 
                                            // Prioritize document description for Notes/Instructions display
                                            $display_notes = !empty($doc['description']) ? $doc['description'] : ($doc['assignment_notes'] ?? '-');
                                            echo htmlspecialchars($display_notes);
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
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No finished documents</td>
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
        <div class="modal-content" style="max-width: 900px; width: 90%;">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeFinishedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
                    <div class="form-group">
                        <label style="font-size: 11px;">Tracking Code</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewTrackingCode">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Document Type</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewDocumentType">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Title</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px; white-space: normal;">
                            <span id="viewTitle">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">From Sender</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewSender">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Date Sent</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewDateSent">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Completed At</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewCompletedAt">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Status</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewStatus">-</span>
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

                <div class="form-group">
                    <label>Uploaded File</label>
                    <div id="viewImageContainer" style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px; display: none; overflow-x: hidden; overflow-y: auto;">
                        <img id="viewImage" src="" alt="Document" style="max-width: 100%; height: auto; border-radius: var(--radius-md); cursor: pointer; display: block; margin: 0 auto;" onclick="openFullImage()">
                        <p id="viewImageName" style="margin-top: 8px; font-size: 12px; color: var(--text-light);"></p>
                    </div>
                    <div id="viewNoImage" style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); color: var(--text-light);">
                        No document/image uploaded
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFinishedModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
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

        function viewFinishedDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('../get-document-details.php?assignment_id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const assignment = data.document;
                        
                        // Populate modal with data
                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewSender').textContent = (assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '') || '-';
                        document.getElementById('viewDateSent').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        document.getElementById('viewCompletedAt').textContent = assignment.completed_at ? new Date(assignment.completed_at).toLocaleString() : '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        // Prioritize document description for Notes/Instructions display
                        const displayNotes = assignment.description ? assignment.description : (assignment.assignment_notes || '-');
                        document.getElementById('viewNotes').textContent = displayNotes;
                        
                        // Handle database-backed completion paper display
                        if (assignment.has_completion_file) {
                            const fileUrl = '../get-document-file.php?assignment_id=' + assignment.assignment_id;
                            const fileName = assignment.completion_file_name || 'Uploaded paper';
                            const extension = fileName.split('.').pop().toLowerCase();
                            const imageTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
                            if (imageTypes.includes(extension)) {
                                document.getElementById('viewImageContainer').innerHTML = `
                                    <img src="${fileUrl}" alt="Uploaded file" style="width: 100%; height: auto; max-height: 420px; object-fit: contain; display: block; margin: 0 auto; border-radius: var(--radius-md);" />
                                    <p style="margin-top: 8px; font-size: 12px; color: var(--text-light);">${fileName}</p>
                                `;
                            } else {
                                document.getElementById('viewImageContainer').innerHTML = `
                                    <div style="width: 100%; overflow-x: hidden; overflow-y: auto;">
                                        <iframe src="${fileUrl}" style="width: 100%; height: 320px; border: 0; border-radius: var(--radius-md); background: #fff;"></iframe>
                                    </div>
                                    <p style="margin-top: 8px; font-size: 12px; color: var(--text-light);">${fileName}</p>
                                `;
                            }
                            document.getElementById('viewImageContainer').style.display = 'block';
                            document.getElementById('viewNoImage').style.display = 'none';
                        } else {
                            document.getElementById('viewImageContainer').style.display = 'none';
                            document.getElementById('viewNoImage').style.display = 'block';
                        }
                        
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

        function closeFinishedModal() {
            document.getElementById('viewFinishedModal').classList.remove('active');
        }
    </script>
    
    <!-- Notification System -->
    <script src="../js/notifications.js"></script>
</body>
</html>