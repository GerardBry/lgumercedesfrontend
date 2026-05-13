<?php
/**
 * Manage Documents Page
 * Record Officer - Document Management Interface
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: text/html; charset=utf-8');

// STRICT ROLE-BASED ACCESS CONTROL
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Only allow Record Officer role
if ($_SESSION['role'] !== 'Record Officer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$officer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Record Officer';
$last_name = $_SESSION['last_name'] ?? '';

// Get filtering parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$office_filter = isset($_GET['office']) ? $_GET['office'] : '';
$user_filter = isset($_GET['user']) ? $_GET['user'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get all offices for filter dropdown
$offices = [];
$office_result = $conn->query("SELECT DISTINCT office_department FROM document_assignments WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
if ($office_result) {
    while ($row = $office_result->fetch_assoc()) {
        $offices[] = $row['office_department'];
    }
}

// Get all users for filter dropdown
$users = [];
$user_result = $conn->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM document_assignments da JOIN users u ON da.assigned_by = u.id WHERE u.role NOT IN ('Super Admin', 'Record Officer') ORDER BY u.first_name, u.last_name");
if ($user_result) {
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Build document query with filters
// If no status filter (All Status), get latest status for each document (exclude Forwarded)
if (!$status_filter) {
    $document_query = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.tracking_number,
        d.title,
        d.document_type,
        d.description,
        d.date_sent,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.file_path,
        d.status as document_status,
        da.status as assignment_status,
        da.office_department,
        da.assigned_at,
        da.received_at,
        da.completed_at,
        da.completion_file,
        u_creator.first_name as creator_first_name,
        u_creator.last_name as creator_last_name,
        u_assigned.first_name as assigned_to_first_name,
        u_assigned.last_name as assigned_to_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_creator ON da.assigned_by = u_creator.id
    LEFT JOIN users u_assigned ON da.assigned_to = u_assigned.id
    WHERE (da.document_id, da.assigned_at) IN (
        SELECT document_id, MAX(assigned_at) 
        FROM document_assignments 
        WHERE status != 'Forwarded'
        GROUP BY document_id
    )
    AND da.status != 'Forwarded'";
} else {
    // Specific status selected
    $document_query = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.tracking_number,
        d.title,
        d.document_type,
        d.description,
        d.date_sent,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.file_path,
        d.status as document_status,
        da.status as assignment_status,
        da.office_department,
        da.assigned_at,
        da.received_at,
        da.completed_at,
        da.completion_file,
        u_creator.first_name as creator_first_name,
        u_creator.last_name as creator_last_name,
        u_assigned.first_name as assigned_to_first_name,
        u_assigned.last_name as assigned_to_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_creator ON da.assigned_by = u_creator.id
    LEFT JOIN users u_assigned ON da.assigned_to = u_assigned.id
    WHERE da.status = ?";
}

$params = [];
$types = '';

// Only add status parameter if status filter is set
if ($status_filter) {
    $params[] = $status_filter;
    $types .= 's';
}

if ($office_filter) {
    $document_query .= " AND da.office_department = ?";
    $params[] = $office_filter;
    $types .= 's';
}

if ($user_filter) {
    $document_query .= " AND da.assigned_by = ?";
    $params[] = intval($user_filter);
    $types .= 'i';
}

if ($type_filter) {
    $document_query .= " AND d.document_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if ($date_from) {
    $document_query .= " AND DATE(d.date_sent) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $document_query .= " AND DATE(d.date_sent) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$document_query .= " ORDER BY COALESCE(da.completed_at, da.received_at, da.assigned_at, d.date_sent) DESC LIMIT 100";

$documents = [];
if ($types) {
    $stmt = $conn->prepare($document_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query($document_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Documents - Record Officer</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff9500;
            --sidebar-bg: #1a1a2e;
            --bg-white: #ffffff;
            --bg-light: #f5f5f5;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #e0e0e0;
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --radius-lg: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .sidebar-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .nav-menu ul {
            list-style: none;
        }

        .nav-menu li {
            margin: 4px 0;
            padding: 0 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            color: #ffffff;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin: 2px 0 0 0;
        }

        .logout-btn {
            background-color: #e0e0e0;
            color: var(--text-dark);
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            overflow-y: auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .page {
            padding: 40px;
            display: block;
            width: 100%;
            flex: 1;
        }

        .page-header {
            margin-bottom: 32px;
            display: block;
            width: 100%;
        }

        .page-header h2 {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-light);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .data-table thead th {
            background-color: #f5f5f5;
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .data-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-returned {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #e68900;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 13px;
        }

        .filters-section {
            background-color: #fafafa;
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: block;
            width: 100%;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .modal-large {
            max-width: 900px;
        }

        .modal-header {
            background-color: #f5f5f5;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            background-color: #f5f5f5;
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-file-archive"></i>
                </div>
                <h1>Record Officer</h1>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li><a href="admin-dashboard-officer.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
                    <li style="margin: 12px 0; padding: 0; height: 1px; background: rgba(255,255,255,0.1);"></li>
                    <li><a href="manage-document.php" class="nav-item active"><i class="fas fa-folder-open"></i><span>Manage Documents</span></a></li>
                    <li><a href="audit-record.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="user-role">Record Officer</p>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page">
                <div class="page-header">
                    <h2><i class="fas fa-folder-open" style="color: var(--primary-color); margin-right: 12px;"></i>Manage Documents</h2>
                    <p>View and manage all document assignments</p>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label>Office/Department</label>
                                <select name="office" onchange="this.form.submit()">
                                    <option value="">All Offices</option>
                                    <?php foreach ($offices as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $office_filter === $office ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($office); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Created By</label>
                                <select name="user" onchange="this.form.submit()">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Document Type</label>
                                <select name="type" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="Travel Request" <?php echo $type_filter === 'Travel Request' ? 'selected' : ''; ?>>Travel Request</option>
                                    <option value="Office Order" <?php echo $type_filter === 'Office Order' ? 'selected' : ''; ?>>Office Order</option>
                                    <option value="Executive Request" <?php echo $type_filter === 'Executive Request' ? 'selected' : ''; ?>>Executive Request</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" onchange="this.form.submit()">
                            </div>

                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" onchange="this.form.submit()">
                            </div>

                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Received" <?php echo $status_filter === 'Received' ? 'selected' : ''; ?>>Received</option>
                                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Returned" <?php echo $status_filter === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Documents Table -->
                <?php if (count($documents) > 0): ?>
                    <div style="overflow-x: auto;">
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
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <?php
                                        // Use assignment status, fallback to document status, default to Pending
                                        // Check for non-empty values, not just null
                                        $status = (!empty($doc['assignment_status']) ? $doc['assignment_status'] : 
                                                  (!empty($doc['document_status']) ? $doc['document_status'] : 'Pending'));
                                        $badge_class = 'badge-pending';
                                        if (in_array($status, ['Completed', 'Approved'])) {
                                            $badge_class = 'badge-completed';
                                        } elseif ($status === 'Returned') {
                                            $badge_class = 'badge-returned';
                                        }

                                        $senderName = trim(($doc['creator_first_name'] ?? '') . ' ' . ($doc['creator_last_name'] ?? '')) ?: ($doc['sender_name'] ?? '-');
                                        $descriptionShort = isset($doc['description']) ? (strlen($doc['description']) > 80 ? substr($doc['description'],0,80) . '...' : $doc['description']) : '-';
                                        $dateReceived = !empty($doc['date_received']) ? date('M d, Y', strtotime($doc['date_received'])) : ($doc['date_sent'] ? date('M d, Y', strtotime($doc['date_sent'])) : '-');
                                        $classification = $doc['classification'] ?? '-';
                                        $sub_class = $doc['sub_classification'] ?? '-';
                                        $priority = $doc['priority'] ?? 'Normal';
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars(strtolower(($doc['tracking_number'] ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($doc['description'] ?? ''))); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priority); ?>" data-date="<?php echo htmlspecialchars(!empty($doc['date_received']) ? $doc['date_received'] : ($doc['date_sent'] ?? '')); ?>">
                                        <td><strong><?php echo htmlspecialchars($doc['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo htmlspecialchars($descriptionShort); ?></td>
                                        <td><?php echo htmlspecialchars($dateReceived); ?></td>
                                        <td><?php echo !empty($classification) ? '<span class="badge badge-info">' . htmlspecialchars($classification) . '</span>' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($sub_class); ?></td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($priority); ?></span></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewRecordDocument(<?php echo intval($doc['assignment_id'] ?? 0); ?>, <?php echo intval($doc['document_id'] ?? 0); ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No documents found</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

            <!-- View Record Document Modal -->
            <div id="viewRecordModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeRecordModal()">
                <div class="modal-content modal-large">
                    <div class="modal-header">
                        <h3>Document Details</h3>
                        <button class="modal-close" onclick="closeRecordModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="modal-body">
                        <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600; border-bottom: 3px solid var(--primary-color); padding-bottom: 8px;">Document Information</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Document ID</label>
                                <span style="font-size: 14px; font-weight: 600; color: #333;" id="viewRecordDocumentID">-</span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Subject/Title</label>
                                <span style="font-size: 14px; color: #333;" id="viewRecordTitle">-</span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sender</label>
                                <span style="font-size: 14px; color: #333;" id="viewRecordSender">-</span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Date Received</label>
                                <span style="font-size: 14px; color: #333;" id="viewRecordDateReceived">-</span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Classification</label>
                                <span id="viewRecordClassification" style="display: inline-block;"><span class="badge badge-info">-</span></span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sub-Classification</label>
                                <span style="font-size: 14px; color: #333;" id="viewRecordSubClassification">-</span>
                            </div>
                            <div class="detail-row">
                                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Prioritization</label>
                                <span id="viewRecordPrioritization" style="display: inline-block;"><span class="badge badge-primary">-</span></span>
                            </div>
                        </div>

                        <div class="detail-row" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Description</label>
                            <span style="font-size: 14px; color: #333; display: block; line-height: 1.6;" id="viewRecordDescription">-</span>
                        </div>

                        <div class="detail-row" style="margin-bottom: 16px;">
                            <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <button type="button" id="viewRecordFileName" onclick="viewRecordFile()" style="flex: 1; border: none; background: transparent; padding: 0; text-align: left; font-size: 14px; color: #0d6efd; text-decoration: underline; cursor: pointer;">No attachment</button>
                                <button type="button" class="btn btn-sm btn-warning" onclick="viewRecordFile()" id="viewRecordFileBtn" style="display: none;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-sm btn-info" onclick="downloadRecordDocument()" id="downloadRecordBtn" style="display: none;">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>

                        <!-- Travel Requests Section -->
                        <div id="travelRequestsSectionRecord" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--primary-color);">
                            <h4 style="margin-bottom: 16px; font-size: 14px; font-weight: 600; color: var(--primary-color);">
                                <i class="fas fa-plane"></i> Submitted Travel Requests
                            </h4>
                            <div id="travelRequestsListRecord" style="display: flex; flex-direction: column; gap: 12px;"></div>
                        </div>

                        <!-- Uploaded Files Section -->
                        <div id="uploadedFilesSectionRecord" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #28a745;">
                            <h4 style="margin-bottom: 16px; font-size: 14px; font-weight: 600; color: #28a745;">
                                <i class="fas fa-file-upload"></i> Uploaded Pictures/Files
                            </h4>
                            <div id="uploadedFilesListRecord" style="display: flex; flex-direction: column; gap: 12px;"></div>
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
                        <button type="button" class="btn btn-secondary" onclick="closeRecordModal()">Close</button>
                    </div>
                </div>
            </div>
        <div id="recordFileViewerModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeRecordFileViewerModal()">
            <div class="modal-content" style="max-width: 72vw; width: min(980px, 92vw); max-height: 90vh; overflow: hidden;">
                <div class="modal-header">
                    <h3 id="recordFileViewerTitle">Attached File Preview</h3>
                    <button class="modal-close" onclick="closeRecordFileViewerModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 18px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; min-height: 72vh;">
                    <img id="recordFileViewerImage" alt="Attached file preview" style="max-width: 100%; max-height: 72vh; width: auto; height: auto; object-fit: contain; display: none;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" onclick="downloadRecordDocument()">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeRecordFileViewerModal()">Close</button>
                </div>
            </div>
        </div>
        </body>
        <script>
            let currentRecordDocument = null;
            let currentRecordAttachment = {
                type: '',
                path: '',
                name: '',
                assignmentId: 0
            };

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

            function viewRecordDocument(assignmentId, documentId) {
                currentRecordDocument = null;
                const basePath = '..';
                
                // Try assignment-based fetch first, then fallback to document-id fetch
                const tryAssignment = parseInt(assignmentId) > 0;
                const assignmentUrl = basePath + '/get-document-details.php?assignment_id=' + encodeURIComponent(assignmentId);
                const docUrl = basePath + '/get-document-details.php?id=' + encodeURIComponent(documentId);

                const fetchAndShow = (url) => {
                    return fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) {
                                return Promise.reject(data.message || 'Failed to load document');
                            }
                            return data.document;
                        });
                };

                const primary = tryAssignment ? fetchAndShow(assignmentUrl) : fetchAndShow(docUrl);

                primary.catch(() => {
                    if (parseInt(documentId) > 0 && tryAssignment) {
                        return fetchAndShow(docUrl);
                    }
                    return Promise.reject('Document not found');
                })
                .then(d => {
                    currentRecordDocument = d;
                    const attachmentPath = d.file_path || d.completion_file_path || '';
                    const attachmentName = d.completion_file_name || (attachmentPath ? attachmentPath.split('/').pop() : 'attachment');
                    currentRecordAttachment = {
                        type: d.file_path ? 'path' : (d.completion_file_path ? 'assignment' : ''),
                        path: attachmentPath,
                        name: attachmentName,
                        assignmentId: d.assignment_id || 0
                    };

                    document.getElementById('viewRecordDocumentID').textContent = d.tracking_number || d.id || '';
                    document.getElementById('viewRecordTitle').textContent = d.title || '-';
                    document.getElementById('viewRecordSender').textContent = d.sender_name || (d.sender_first_name ? (d.sender_first_name + ' ' + (d.sender_last_name||'')) : '-');
                    document.getElementById('viewRecordDateReceived').textContent = d.date_received || d.date_sent || '-';
                    document.getElementById('viewRecordClassification').innerHTML = d.classification ? ('<span class="badge badge-info">' + d.classification + '</span>') : '-';
                    document.getElementById('viewRecordSubClassification').textContent = d.sub_classification || '-';
                    document.getElementById('viewRecordPrioritization').innerHTML = d.priority ? ('<span class="badge badge-primary">' + d.priority + '</span>') : '<span class="badge badge-primary">Normal</span>';
                    document.getElementById('viewRecordDescription').textContent = d.description || '-';

                    const rejectionSection = document.getElementById('rejectionDetailsSection');
                    if ((d.assignment_status || d.document_status || '').toLowerCase() === 'returned') {
                        const rejectedByName = ((d.assigned_by_first || '') + ' ' + (d.assigned_by_last || '')).trim();
                        const rejectedByPosition = (d.assigned_by_position || '').trim();
                        const rejectedBy = rejectedByPosition
                            ? (rejectedByName ? rejectedByName + ' (' + rejectedByPosition + ')' : rejectedByPosition)
                            : (rejectedByName || '-');
                        const rejectedAtRaw = d.rejection_at || d.returned_at || d.completed_at || null;

                        document.getElementById('viewRejectedBy').textContent = rejectedBy;
                        document.getElementById('viewRejectedAt').textContent = rejectedAtRaw ? new Date(rejectedAtRaw).toLocaleString() : '-';
                        document.getElementById('viewRejectedReason').textContent = extractReturnReason(d.assignment_notes || d.notes || '');
                        rejectionSection.style.display = 'block';
                    } else {
                        rejectionSection.style.display = 'none';
                    }

                    const fileNameEl = document.getElementById('viewRecordFileName');
                    const viewBtn = document.getElementById('viewRecordFileBtn');
                    const downloadBtn = document.getElementById('downloadRecordBtn');

                    if (attachmentPath) {
                        fileNameEl.textContent = attachmentName;
                        fileNameEl.style.color = '#0d6efd';
                        fileNameEl.style.textDecoration = 'underline';
                        fileNameEl.style.cursor = 'pointer';
                        fileNameEl.disabled = false;
                        viewBtn.style.display = '';
                        downloadBtn.style.display = '';
                    } else {
                        fileNameEl.textContent = 'No attachment';
                        fileNameEl.style.color = '#333';
                        fileNameEl.style.textDecoration = 'none';
                        fileNameEl.style.cursor = 'default';
                        fileNameEl.disabled = true;
                        viewBtn.style.display = 'none';
                        downloadBtn.style.display = 'none';
                    }

                    loadRecordUploadedFiles(assignmentId);
                    loadRecordTravelRequests(d.id || currentRecordDocument.document_id);
                    const modal = document.getElementById('viewRecordModal');
                    modal.style.display = 'flex';
                })
                .catch(err => {
                    console.error(err);
                    alert(typeof err === 'string' ? err : 'Error loading document');
                });
            }

            function closeRecordModal() {
                const modal = document.getElementById('viewRecordModal');
                modal.style.display = 'none';
            }

            function viewRecordFile() {
                if (!currentRecordDocument || !currentRecordAttachment.path) return;

                const image = document.getElementById('recordFileViewerImage');
                const title = document.getElementById('recordFileViewerTitle');
                const basePath = '..';
                const fileName = currentRecordAttachment.name || 'Attached File';
                const fileExt = fileName.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);

                if (!isImage) {
                    window.open(
                        currentRecordAttachment.type === 'assignment'
                            ? (basePath + '/get-document-file.php?assignment_id=' + encodeURIComponent(currentRecordAttachment.assignmentId || currentRecordDocument.assignment_id || currentRecordDocument.id))
                            : (basePath + '/view-document-file.php?path=' + encodeURIComponent(currentRecordAttachment.path)),
                        '_blank'
                    );
                    return;
                }

                const imageUrl = currentRecordAttachment.type === 'assignment'
                    ? (basePath + '/get-document-file.php?assignment_id=' + encodeURIComponent(currentRecordAttachment.assignmentId || currentRecordDocument.assignment_id || currentRecordDocument.id))
                    : (basePath + '/view-document-file.php?path=' + encodeURIComponent(currentRecordAttachment.path));

                image.src = imageUrl;
                image.style.display = 'block';
                title.textContent = 'Viewing: ' + fileName;
                document.getElementById('recordFileViewerModal').style.display = 'flex';
            }

            function downloadRecordDocument() {
                if (!currentRecordDocument || !currentRecordAttachment.path) return;

                const basePath = '..';
                const link = document.createElement('a');
                if (currentRecordAttachment.type === 'assignment') {
                    link.href = basePath + '/get-document-file.php?assignment_id=' + encodeURIComponent(currentRecordAttachment.assignmentId || currentRecordDocument.assignment_id || currentRecordDocument.id);
                } else {
                    link.href = basePath + '/' + currentRecordAttachment.path;
                }
                link.download = currentRecordAttachment.name || 'attachment';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function closeRecordFileViewerModal() {
                const modal = document.getElementById('recordFileViewerModal');
                const image = document.getElementById('recordFileViewerImage');
                modal.style.display = 'none';
                image.src = '';
                image.style.display = 'none';
            }

            function loadRecordUploadedFiles(assignmentId) {
                const listEl = document.getElementById('uploadedFilesListRecord');
                const section = document.getElementById('uploadedFilesSectionRecord');
                listEl.innerHTML = '';
                section.style.display = 'none';

                const basePath = '..';
                const uploadsUrl = basePath + '/administrative/get-document-uploads.php?assignment_id=' + encodeURIComponent(assignmentId);

                fetch(uploadsUrl)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) return;
                        const uploads = data.uploads || [];
                        if (uploads.length === 0) return;
                        section.style.display = 'block';
                        uploads.forEach(u => {
                            const row = document.createElement('div');
                            row.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9; display: flex; gap: 12px; align-items: flex-start;';

                            const fileName = u.file_path ? u.file_path.split('/').pop() : 'attachment';
                            const ext = fileName.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(ext);
                            const filePath = basePath + '/' + u.file_path;

                            const iconWrap = document.createElement('div');
                            iconWrap.style.flexShrink = '0';

                            if (isImage) {
                                const img = document.createElement('img');
                                img.src = basePath + '/view-document-file.php?path=' + encodeURIComponent(u.file_path);
                                img.alt = fileName;
                                img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 4px; cursor: pointer;';
                                img.addEventListener('click', function () {
                                    viewRecordUploadedFile(filePath, ext, fileName);
                                });
                                iconWrap.appendChild(img);
                            } else {
                                const icon = document.createElement('div');
                                icon.style.cssText = 'width: 60px; height: 60px; background-color: #e0e0e0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 24px;';
                                icon.innerHTML = '<i class="fas fa-file"></i>';
                                iconWrap.appendChild(icon);
                            }

                            const contentWrap = document.createElement('div');
                            contentWrap.style.flexGrow = '1';

                            const nameLine = document.createElement('div');
                            nameLine.style.cssText = 'font-weight: 600; color: #333; margin-bottom: 4px; word-break: break-word;';
                            nameLine.textContent = fileName;

                            const metaLine = document.createElement('div');
                            metaLine.style.cssText = 'font-size: 12px; color: #666; margin-bottom: 4px;';
                            const uploadDate = u.uploaded_at ? new Date(u.uploaded_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }) : '-';
                            metaLine.textContent = uploadDate + ' | by ' + (u.uploaded_by || 'Administrative');

                            contentWrap.appendChild(nameLine);
                            contentWrap.appendChild(metaLine);

                            if (u.notes) {
                                const notesLine = document.createElement('div');
                                notesLine.style.cssText = 'font-size: 12px; color: #666; margin-bottom: 8px; font-style: italic;';
                                notesLine.textContent = '"' + u.notes + '"';
                                contentWrap.appendChild(notesLine);
                            }

                            const buttonRow = document.createElement('div');
                            buttonRow.style.cssText = 'display: flex; gap: 6px; flex-wrap: wrap;';

                            const viewButton = document.createElement('button');
                            viewButton.type = 'button';
                            viewButton.className = 'btn btn-sm btn-info';
                            viewButton.style.cssText = 'padding: 4px 8px; font-size: 11px;';
                            viewButton.innerHTML = '<i class="fas fa-eye"></i> View';
                            viewButton.addEventListener('click', function () {
                                viewRecordUploadedFile(filePath, ext, fileName);
                            });

                            const downloadButton = document.createElement('button');
                            downloadButton.type = 'button';
                            downloadButton.className = 'btn btn-sm btn-secondary';
                            downloadButton.style.cssText = 'padding: 4px 8px; font-size: 11px;';
                            downloadButton.innerHTML = '<i class="fas fa-download"></i> Download';
                            downloadButton.addEventListener('click', function () {
                                downloadRecordFile(filePath, fileName);
                            });

                            buttonRow.appendChild(viewButton);
                            buttonRow.appendChild(downloadButton);
                            contentWrap.appendChild(buttonRow);

                            row.appendChild(iconWrap);
                            row.appendChild(contentWrap);
                            listEl.appendChild(row);
                        });
                    })
                    .catch(err => console.error(err));
            }

            function viewRecordUploadedFile(filePath, ext, fileName) {
                const decodedPath = decodeURIComponent(filePath);
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes((ext || '').toLowerCase());

                if (isImage) {
                    const image = document.getElementById('recordFileViewerImage');
                    const title = document.getElementById('recordFileViewerTitle');
                    image.src = '../view-document-file.php?path=' + encodeURIComponent(decodedPath.replace(/^\.\//, ''));
                    image.style.display = 'block';
                    title.textContent = 'Viewing: ' + (fileName || decodedPath.split('/').pop());
                    document.getElementById('recordFileViewerModal').style.display = 'flex';
                    return;
                }

                window.open('../view-document-file.php?path=' + encodeURIComponent(decodedPath.replace(/^\.\//, '')), '_blank');
            }

            function downloadRecordFile(filePath, fileName) {
                const link = document.createElement('a');
                link.href = filePath;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function loadRecordTravelRequests(documentId) {
                const listEl = document.getElementById('travelRequestsListRecord');
                const section = document.getElementById('travelRequestsSectionRecord');
                listEl.innerHTML = '';
                section.style.display = 'none';

                const basePath = '..';
                const url = basePath + '/get-travel-requests.php?parent_document_id=' + encodeURIComponent(documentId);

                const safeParseJson = (value) => {
                    if (!value) return {};
                    if (typeof value === 'object') return value;
                    try {
                        return JSON.parse(value);
                    } catch (error) {
                        return {};
                    }
                };

                const escapeText = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) return;
                        const requests = data.requests || data.travel_requests || [];
                        if (requests.length === 0) return;
                        section.style.display = 'block';
                        
                        requests.forEach(req => {
                            const row = document.createElement('div');
                            row.style.cssText = 'border: 1px solid #ddd; border-radius: 6px; padding: 12px; background-color: #f9f9f9;';

                            const requestData = safeParseJson(req.notes);
                            const travelers = Array.isArray(requestData.travelers) ? requestData.travelers : [];
                            const travelersHtml = travelers.map(t => {
                                const name = escapeText(t.name || '');
                                const position = escapeText(t.position || '');
                                const days = t.days ? ' - ' + escapeText(t.days) + ' day(s)' : '';
                                return '<li>' + name + ' (' + position + ')' + days + '</li>';
                            }).join('');

                            const submittedOn = req.date_sent ? new Date(req.date_sent).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            }) : 'N/A';

                            row.innerHTML = '<div style="margin-bottom: 8px;"><h5 style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: var(--primary-color);"><i class="fas fa-plane"></i> Travel Request: ' + escapeText(requestData.event_title || req.title || 'Untitled') + '</h5><p style="margin: 0; font-size: 11px; color: #666;">Submitted on: ' + submittedOn + '</p></div><div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px; font-size: 11px;"><div><span style="font-weight: 600; color: #666;">Officer:</span><br>' + escapeText(requestData.officer_name || req.sender_name || '-') + '</div><div><span style="font-weight: 600; color: #666;">Order Type:</span><br>' + escapeText(requestData.order_type || '-') + '</div><div><span style="font-weight: 600; color: #666;">Purpose:</span><br>' + escapeText(requestData.purpose_of_order || '-') + '</div><div><span style="font-weight: 600; color: #666;">Event Date:</span><br>' + (requestData.event_date ? new Date(requestData.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-') + '</div></div><div style="margin-bottom: 8px; font-size: 11px;"><span style="font-weight: 600; color: #666;">Location:</span><br>' + escapeText(requestData.event_place || '-') + '</div><div style="margin-bottom: 8px; font-size: 11px;"><span style="font-weight: 600; color: #666;">Travelers:</span><ul style="margin: 4px 0 0 20px; padding: 0;">' + (travelersHtml || '<li style="color: #999;">No travelers listed</li>') + '</ul></div><div style="font-size: 11px;"><span style="font-weight: 600; color: #666;">Description:</span><br>' + escapeText(requestData.event_description || req.description || '-') + '</div>';
                            
                            listEl.appendChild(row);
                        });
                    })
                    .catch(err => console.error('Failed to load travel requests:', err));
            }
        </script>
</html>
