<?php
/**
 * Incoming Documents Page - Administrative Staff
 * View pending documents assigned to this administrative staff
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: ../admin/admin-dashboard.php');
        exit;
    }
    if ($_SESSION['role'] !== 'Administrative Assistant') {
        header('Location: ../login.php');
        exit;
    }
}

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

$incoming_documents = [];
$sql = "SELECT DISTINCT
        da.id as assignment_id,
        d.id as document_id,
        d.tracking_number,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
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
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        assigner.first_name as assigner_first_name,
        assigner.last_name as assigner_last_name,
        assigner.office_department as assigner_office
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    WHERE da.assigned_to = ?
            AND da.status IN ('Submitted to Administrative Office', 'Pending', 'Forwarded')
    ORDER BY da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
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
    <title>Incoming Documents - Administrative Panel</title>
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

        .admin-sidebar::-webkit-scrollbar { width: 6px; }
        .admin-sidebar::-webkit-scrollbar-track { background: transparent; }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
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

        .admin-nav-menu ul { list-style: none; }
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
            position: relative;
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

        .badge-notification {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            margin-left: auto;
            background-color: #dc3545;
            color: #ffffff;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.15);
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
        .admin-main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            overflow-y: auto;
            min-height: 100vh;
        }

        .admin-page { padding: 40px; }

        .page-header { margin-bottom: 32px; }

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

        .data-table tbody tr:hover { background-color: var(--bg-light); }
        .data-table tbody tr:last-child td { border-bottom: none; }

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

        .btn-info:hover { background-color: #bbdefb; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

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

        .modal.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

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
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

        .modal-close:hover { color: var(--text-dark); }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-group { margin-bottom: 16px; }

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

        .btn-secondary:hover { background-color: #d0d0d0; }

        .btn-success {
            background-color: #4caf50;
            color: #fff;
        }

        .btn-success:hover {
            background-color: #43a047;
        }

        .modal-content.modal-large {
            max-width: 1100px;
        }
    </style>
</head>
<body class="admin-theme">
    <div class="admin-container">
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
                        <a href="incoming.php" class="admin-nav-item active">
                            <i class="fas fa-inbox"></i>
                            <span>Incoming</span>
                            <span class="badge-notification" id="incomingBadge" style="display: none;">0</span>
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
                        </a></li>

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
                    <div class="admin-avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
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

        <div class="admin-main-content">
            <!-- Header with Notifications -->
            <div style="padding: 20px 40px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: flex-end; align-items: center; position: relative; z-index: 10;">
                <div class="header-right" style="display: flex; gap: 16px; align-items: center; position: relative;">
                    <!-- Notification Bell will be inserted here by notifications.js -->
                </div>
            </div>
            <div class="admin-page">
                <div class="page-header">
                    <h2>Incoming Documents</h2>
                    <p>Pending documents assigned to you</p>
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
                            <?php if (count($incoming_documents) > 0): ?>
                                <?php foreach ($incoming_documents as $doc): ?>
                                    <?php 
                                        // Use direct columns first, fallback to JSON if not available
                                        $additionalData = $doc['doc_notes'] ? json_decode($doc['doc_notes'], true) : [];
                                        $classification = $doc['classification'] ?? $additionalData['classification'] ?? 'N/A';
                                        $sub_classification = $doc['sub_classification'] ?? $additionalData['sub_classification'] ?? 'N/A';
                                        $priority = $doc['priority'] ?? $additionalData['priority'] ?? 'N/A';
                                        $dateReceived = $doc['date_received'] ?? $additionalData['date_received'] ?? $doc['date_sent'];
                                        $sender = $doc['sender_name'] ?? $additionalData['sender'] ?? 'N/A';
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $description = $doc['description'] ?? '';
                                        $filterDate = $dateReceived ? date('Y-m-d', strtotime($dateReceived)) : '';
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($sender ?? '') . ' ' . ($description ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($sender)); ?>" data-priority="<?php echo htmlspecialchars($priority); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td><strong><?php echo htmlspecialchars($trackingCode); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doc['assigner_office'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($sender); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo $dateReceived ? date('M d, Y', strtotime($dateReceived)) : '-'; ?></td>
                                        <td>
                                            <?php 
                                                $classificationClass = 'badge-info';
                                                if ($classification === 'Letter') $classificationClass = 'badge-classification-letter';
                                                else if ($classification === 'Invitation') $classificationClass = 'badge-classification-invitation';
                                                else if ($classification === 'Travel-Related Communication') $classificationClass = 'badge-classification-travel';
                                                else if ($classification === 'Indorsement') $classificationClass = 'badge-classification-indorsement';
                                            ?>
                                            <span class="badge <?php echo $classificationClass; ?>" style="font-size: 11px; padding: 5px 10px;"><?php echo htmlspecialchars($classification); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sub_classification); ?></td>
                                        <td>
                                            <?php 
                                                $priorityClass = 'badge-primary';
                                                if ($priority === 'Urgent') $priorityClass = 'badge-warning';
                                                else if ($priority === 'Critical') $priorityClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priorityClass; ?>" style="font-size: 11px; padding: 5px 10px;"><?php echo htmlspecialchars($priority); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['assignment_status'] ?? 'Pending'); ?></td>
                                        <td>
                                            <button class="btn-sm btn-info" onclick="viewIncomingDocument(<?php echo (int)$doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="11" class="empty-state">No incoming documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="11" class="empty-state">No incoming documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal" style="z-index: 5001;">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="confirmationTitle">Confirm Action</h3>
                <button class="modal-close" onclick="closeConfirmationModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirmationMessage" style="font-size: 16px; line-height: 1.6; margin: 0;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">
                    Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmActionBtn" onclick="executeConfirmAction()">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Return Reason Modal -->
    <div id="returnReasonModal" class="modal" style="z-index: 5002;">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Return Document</h3>
                <button class="modal-close" onclick="closeReturnReasonModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="returnReasonInput">Reason for returning this document</label>
                    <textarea id="returnReasonInput" rows="5" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); resize: vertical; font-size: 14px;" placeholder="Enter the reason here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReturnReasonModal()">
                    Cancel
                </button>
                <button type="button" class="btn btn-warning" onclick="submitReturnReason()">
                    <i class="fas fa-undo"></i> Return Document
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
                <button type="button" class="btn btn-info" onclick="downloadIncomingFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="viewIncomingModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details &amp; Preview</h3>
                <button class="modal-close" onclick="closeIncomingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                    <div id="documentDetailsModalContent">
                        <div class="document-details">
                            <div class="details-section">
                                <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">Document Information</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
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
                                        <span id="viewClassification">-</span>
                                    </div>
                                    <div class="detail-row">
                                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sub-Classification</label>
                                        <span style="font-size: 14px; color: #333;" id="viewSubClassification">-</span>
                                    </div>
                                    <div class="detail-row">
                                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Prioritization</label>
                                        <span id="viewPrioritization">-</span>
                                    </div>
                                </div>
                                <div class="detail-row" style="margin-top: 16px;">
                                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Description</label>
                                    <span style="font-size: 14px; color: #333; display: block; line-height: 1.6;" id="viewDescription">-</span>
                                </div>
                                <div class="detail-row" style="margin-top: 16px;">
                                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Created Date</label>
                                    <span style="font-size: 14px; color: #333;" id="viewCreatedDate">-</span>
                                </div>
                                
                                <div class="detail-row" style="margin-top: 16px;">
                                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <span style="font-size: 14px; color: #333; flex: 1;" id="viewFileName">-</span>
                                        <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewIncomingFile()" style="display: none;">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" id="downloadFileBtn" onclick="downloadIncomingFile()" style="display: none;">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="approveBtn" onclick="approveIncomingDocument()" style="display: none;">
                    <i class="fas fa-check"></i> Approve
                </button>
                <button type="button" class="btn btn-warning" id="returnBtn" onclick="returnIncomingDocument()" style="display: none;">
                    <i class="fas fa-undo"></i> Return Document
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeIncomingModal()">
                    <i class="fas fa-times"></i> Close
                </button>
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

        let selectedIncomingAssignmentId = null;

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
                                        $dateReceivedTs = $dateReceived ? strtotime($dateReceived) : false;
                                        $filterDate = $dateReceivedTs ? date('Y-m-d', $dateReceivedTs) : '';

        function formatArray(value) {
            if (!Array.isArray(value) || value.length === 0) {
                return '-';
            }
            return value.join(', ');
        }

        function renderIncomingFormDetails(assignment, content) {
            if (assignment.document_type !== 'Travel Request') {
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

        function generateIncomingPreview(assignment, content) {
            if (assignment.document_type === 'Travel Request') {
                return `
                    <div class="document-preview" style="background:#fff; border:1px solid #ddd; border-radius:10px; padding:16px;">
                        <div style="text-align:center; margin-bottom:14px;">
                            <div style="font-size:12px;">Province of Camarines Norte</div>
                            <div style="font-size:14px; font-weight:700;">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                            <div style="font-size:16px; font-weight:700; margin-top:8px;">TRAVEL REQUEST</div>
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

        function viewIncomingDocument(assignmentId) {
            fetch('incoming-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        const content = safeParseJson(assignment.doc_notes);
                        selectedIncomingAssignmentId = assignmentId;

                        // Store current file path for view/download - use direct column first
                        window.currentIncomingFilePath = assignment.file_path || content.file_path || null;

                        // Populate modal with data - use direct columns first, fallback to JSON
                        document.getElementById('viewDocumentID').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewSender').textContent = assignment.sender_name || content.sender || '-';
                        
                        const dateReceived = assignment.date_received || content.date_received;
                        document.getElementById('viewDateReceived').textContent = formatDisplayDate(dateReceived);
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewCreatedDate').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        
                        // Classification - use direct column first
                        const classification = assignment.classification || content.classification || 'N/A';
                        let classificationClass = 'badge-info';
                        if (classification === 'Letter') classificationClass = 'badge-classification-letter';
                        else if (classification === 'Invitation') classificationClass = 'badge-classification-invitation';
                        else if (classification === 'Travel-Related Communication') classificationClass = 'badge-classification-travel';
                        else if (classification === 'Indorsement') classificationClass = 'badge-classification-indorsement';
                        
                        document.getElementById('viewClassification').innerHTML = `<span class="badge ${classificationClass}" style="font-size: 11px; padding: 5px 10px;">${escapeText(classification)}</span>`;
                        
                        // Sub-Classification - use direct column first
                        const subClassification = assignment.sub_classification || content.sub_classification || 'N/A';
                        document.getElementById('viewSubClassification').textContent = escapeText(subClassification);
                        
                        // Prioritization - use direct column first
                        const priority = assignment.priority || content.priority || 'N/A';
                        let priorityClass = 'badge-primary';
                        if (priority === 'Urgent') priorityClass = 'badge-warning';
                        else if (priority === 'Critical') priorityClass = 'badge-danger';
                        
                        document.getElementById('viewPrioritization').innerHTML = `<span class="badge ${priorityClass}" style="font-size: 11px; padding: 5px 10px;">${escapeText(priority)}</span>`;
                        
                        // File handling - use direct column first
                        const filePath = assignment.file_path || content.file_path;
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
                            document.getElementById('downloadFileBtn').style.display = 'inline-block';
                        } else {
                            document.getElementById('viewFileName').textContent = 'No attachment';
                            document.getElementById('viewFileBtn').style.display = 'none';
                            document.getElementById('downloadFileBtn').style.display = 'none';
                        }

                        // Show/hide action buttons based on status
                        const status = assignment.status || 'Pending';
                        if (status === 'Pending' || status === 'Submitted to Administrative Office') {
                            document.getElementById('approveBtn').style.display = 'inline-block';
                            document.getElementById('returnBtn').style.display = 'inline-block';
                        } else {
                            document.getElementById('approveBtn').style.display = 'none';
                            document.getElementById('returnBtn').style.display = 'none';
                        }

                        document.getElementById('viewIncomingModal').classList.add('active');
                    } else {
                        console.error('Error:', data);
                        alert('Error loading document details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
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

        function downloadIncomingFile() {
            if (!window.currentIncomingFilePath) {
                alert('No file to download');
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

        function closeIncomingModal() {
            document.getElementById('viewIncomingModal').classList.remove('active');
            selectedIncomingAssignmentId = null;
        }

        var pendingConfirmAction = null;
        var pendingConfirmData = null;

        function showConfirmationModal(title, message, actionType) {
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            if (actionType === 'approve') {
                confirmBtn.classList.remove('btn-warning');
                confirmBtn.classList.add('btn-success');
                confirmBtn.textContent = ' Approve';
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> Approve';
            } else if (actionType === 'return') {
                confirmBtn.classList.remove('btn-success');
                confirmBtn.classList.add('btn-warning');
                confirmBtn.textContent = ' Return Document';
                confirmBtn.innerHTML = '<i class="fas fa-undo"></i> Return Document';
            }
            
            pendingConfirmAction = actionType;
            document.getElementById('confirmationModal').classList.add('active');
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.remove('active');
            pendingConfirmAction = null;
            pendingConfirmData = null;
        }

        function executeConfirmAction() {
            if (pendingConfirmAction === 'approve') {
                performApprove();
            } else if (pendingConfirmAction === 'return') {
                performReturn();
            }
            closeConfirmationModal();
        }

        function approveIncomingDocument() {
            if (!selectedIncomingAssignmentId) {
                alert('No document selected');
                return;
            }

            showConfirmationModal(
                'Approve Document',
                'Are you sure you want to approve this document?',
                'approve'
            );
        }

        function performApprove() {
            fetch('incoming-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'approve',
                    id: selectedIncomingAssignmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Document approved successfully');
                    closeIncomingModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to approve document'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error approving document');
            });
        }

        function returnIncomingDocument() {
            if (!selectedIncomingAssignmentId) {
                alert('No document selected');
                return;
            }

            const reasonInput = document.getElementById('returnReasonInput');
            const modal = document.getElementById('returnReasonModal');
            
            if (!reasonInput || !modal) {
                alert('Error: Return modal not found on page');
                return;
            }
            
            reasonInput.value = '';
            modal.classList.add('active');
            reasonInput.focus();
        }

        function closeReturnReasonModal() {
            document.getElementById('returnReasonModal').classList.remove('active');
        }

        function submitReturnReason() {
            const reasonInput = document.getElementById('returnReasonInput');
            
            if (!reasonInput) {
                alert('Error: Return reason form not found');
                return;
            }
            
            const reason = (reasonInput.value || '').trim();

            if (!reason) {
                alert('Please provide a reason for returning the document');
                reasonInput.focus();
                return;
            }

            if (!selectedIncomingAssignmentId) {
                alert('Error: No assignment selected');
                return;
            }

            pendingConfirmData = { reason: reason };
            closeReturnReasonModal();
            showConfirmationModal(
                'Return Document',
                `Are you sure you want to return this document?\n\nReason: ${reason}`,
                'return'
            );
        }

        function performReturn() {
            // Validate that we have the required data
            if (!selectedIncomingAssignmentId) {
                alert('Error: No assignment selected');
                return;
            }
            
            if (!pendingConfirmData || !pendingConfirmData.reason) {
                alert('Error: Return reason not set');
                return;
            }

            fetch('incoming-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'return',
                    id: selectedIncomingAssignmentId,
                    reason: pendingConfirmData.reason
                })
            })
            .then(response => {
                // Check if response is actually JSON
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server error: ' + response.status + ' - ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Document returned successfully');
                    closeIncomingModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to return document'));
                }
            })
            .catch(error => {
                console.error('Return error:', error);
                alert('Error returning document: ' + error.message);
            });
        }

        function markCurrentAsReceived() {
            if (!selectedIncomingAssignmentId) {
                alert('No selected document to receive.');
                return;
            }
            markAsReceived(selectedIncomingAssignmentId);
        }

        function markAsReceived(assignmentId) {
            if (!confirm('Are you sure you want to mark this document as received?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'markReceived');
            formData.append('id', assignmentId);

            fetch('incoming-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Document marked as received successfully! Redirecting to Received Documents...');
                    window.location.href = 'received.php';
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking document as received');
            });
        }
    </script>
    <script>
        /**
         * Incoming Document Badge System
         * Displays unread document count and marks documents as viewed
         */
        
        function updateIncomingBadge() {
            fetch('../api/get-document-counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.counts) {
                        const incomingCount = data.counts.incoming || 0;
                        const badgeElement = document.getElementById('incomingBadge');
                        
                        if (incomingCount > 0) {
                            badgeElement.textContent = incomingCount;
                            badgeElement.style.display = 'inline-flex';
                        } else {
                            badgeElement.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error loading badge count:', error));
        }
        
        function markIncomingAsViewed() {
            const formData = new FormData();
            formData.append('category', 'incoming');
            
            fetch('../api/mark-documents-viewed.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update badge after marking as viewed
                    updateIncomingBadge();
                }
            })
            .catch(error => console.error('Error marking as viewed:', error));
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Load badge count
            updateIncomingBadge();
            
            // Mark all documents as viewed when page is loaded
            markIncomingAsViewed();
            
            // Refresh badge count every 30 seconds
            setInterval(updateIncomingBadge, 30000);
        });
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
