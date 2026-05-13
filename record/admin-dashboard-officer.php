<?php
/**
 * Record Officer Dashboard
 * Manage documents with filtering and audit logs with PDF export
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

// Block other roles from accessing this page
if ($_SESSION['role'] === 'Super Admin') {
    header('Location: ../admin/admin-dashboard.php');
    exit;
}
if ($_SESSION['role'] === 'Mayor') {
    header('Location: ../mayor/admin-dashboard-mayor.php');
    exit;
}
if ($_SESSION['role'] === 'Administrative Assistant') {
    header('Location: ../administrative/admin-dashboard-staff.php');
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

// Verify user role in database
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Record Officer' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $officer_id);
    $role_check->execute();
    $role_result = $role_check->get_result();
    
    if ($role_result->num_rows === 0) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }
    $role_check->close();
}

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
$document_query = "SELECT 
    da.id as assignment_id,
    d.id as document_id,
    d.tracking_number,
    d.title,
    d.document_type,
    d.description,
    d.date_sent,
    d.status as document_status,
    da.status as assignment_status,
    da.office_department,
    da.assigned_at,
    da.received_at,
    da.completed_at,
    u_creator.first_name as creator_first_name,
    u_creator.last_name as creator_last_name,
    u_assigned.first_name as assigned_to_first_name,
    u_assigned.last_name as assigned_to_last_name
FROM document_assignments da
JOIN documents d ON da.document_id = d.id
LEFT JOIN users u_creator ON da.assigned_by = u_creator.id
LEFT JOIN users u_assigned ON da.assigned_to = u_assigned.id
WHERE 1=1";

$params = [];
$types = '';

if ($status_filter) {
    $document_query .= " AND da.status = ?";
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

// Get statistics for dashboard
$finished_docs = 0;
$pending_docs = 0;
$returned_docs = 0;
$travel_completed = 0;
$office_order_completed = 0;
$executive_completed = 0;

// Count finished documents
$stats_result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Completed'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $finished_docs = $row['count'] ?? 0;
}

// Count pending documents
$stats_result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Pending'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $pending_docs = $row['count'] ?? 0;
}

// Count returned documents
$stats_result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE status = 'Returned'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $returned_docs = $row['count'] ?? 0;
}

// Count Travel Request Completed
$stats_result = $conn->query("SELECT COUNT(*) as count FROM documents d JOIN document_assignments da ON d.id = da.document_id WHERE d.document_type = 'Travel Request' AND da.status = 'Completed'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $travel_completed = $row['count'] ?? 0;
}

// Count Office Order Completed
$stats_result = $conn->query("SELECT COUNT(*) as count FROM documents d JOIN document_assignments da ON d.id = da.document_id WHERE d.document_type = 'Office Order' AND da.status = 'Completed'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $office_order_completed = $row['count'] ?? 0;
}

// Count Executive Request Completed
$stats_result = $conn->query("SELECT COUNT(*) as count FROM documents d JOIN document_assignments da ON d.id = da.document_id WHERE d.document_type = 'Executive Request' AND da.status = 'Completed'");
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    $executive_completed = $row['count'] ?? 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Officer Dashboard - LGU Mercedes</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff9500;
            --primary-light: #ffa500;
            --primary-dark: #e68900;
            --sidebar-bg: #1a1a2e;
            --bg-white: #ffffff;
            --bg-light: #f5f5f5;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #e0e0e0;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #1976d2;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.15);
            --radius-sm: 4px;
            --radius-md: 8px;
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

        /* Sidebar */
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
            box-shadow: var(--shadow-lg);
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
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: var(--radius-md);
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

        .nav-menu .divider {
            height: 1px;
            background-color: rgba(255, 255, 255, 0.1);
            margin: 12px 0;
            padding: 0;
        }

        .nav-item {
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

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(108, 99, 255, 0.3);
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
            border-radius: var(--radius-md);
            margin-bottom: 12px;
        }

        .avatar {
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
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: #d0d0d0;
        }

        /* Main Content */
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
            min-height: 100%;
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
            display: block;
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-light);
            display: block;
        }

        /* Welcome Section */
        .welcome-section {
            background-color: var(--primary-color);
            border-radius: var(--radius-lg);
            padding: 48px 40px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            background-image: linear-gradient(135deg, rgba(255, 149, 0, 0.5), rgba(230, 138, 0, 0.5)), url(../img/LGU-Mercedes-Official-Logo.png);
            background-size: auto, 900px 900px;
            background-position: 0 0, center;
            background-repeat: repeat, no-repeat;
            background-attachment: scroll, scroll;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
        }

        .welcome-section-content {
            position: relative;
            z-index: 1;
            max-width: 50%;
        }

        .welcome-section h2 {
            font-size: 36px;
            color: #ffffff;
            margin: 0 0 12px 0;
            font-weight: 700;
            text-align: left;
            position: relative;
            z-index: 1;
        }

        .welcome-section p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            text-align: left;
            position: relative;
            z-index: 1;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
            width: 100%;
        }

        .stat-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            gap: 16px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            width: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #ffffff;
            flex-shrink: 0;
        }

        .stat-icon.stat-finished {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }

        .stat-icon.stat-pending {
            background: linear-gradient(135deg, #ffc107, #ffd54f);
        }

        .stat-icon.stat-returned {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        .stat-icon.stat-travel {
            background: linear-gradient(135deg, #1976d2, #42a5f5);
        }

        .stat-icon.stat-office {
            background: linear-gradient(135deg, #ff9500, #ffb347);
        }

        .stat-icon.stat-executive {
            background: linear-gradient(135deg, #9c27b0, #ba68c8);
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            margin-top: 40px;
            color: var(--text-dark);
            display: block;
            width: 100%;
        }

        .permissions-grid {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 40px;
            display: block;
            width: 100%;
        }

        .permissions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .permissions-list li {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .permissions-list li:last-child {
            border-bottom: none;
        }

        .permissions-list i {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 14px;
        }

        .permissions-list span {
            font-size: 14px;
            color: var(--text-dark);
        }

        /* Tabs */
        .tab-navigation {
            display: flex;
            gap: 0;
            margin-bottom: 32px;
            border-bottom: 2px solid var(--border-color);
            background-color: var(--bg-white);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 0;
            box-shadow: var(--shadow-sm);
            width: 100%;
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 16px 24px;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-button:hover {
            color: var(--text-dark);
            background-color: #f9f9f9;
        }

        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
            background-color: var(--bg-white);
            padding: 24px;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
        }

        .tab-content.active {
            display: block;
            width: 100%;
        }

        /* Filters */
        .filters-section {
            background-color: #fafafa;
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: block;
            width: 100%;
        }

        .filters-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            width: 100%;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 13px;
            background-color: var(--bg-white);
            color: var(--text-dark);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-white);
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
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-received {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-returned {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-checking {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-waiting {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-completed {
            background-color: #d4edda;
            color: #155724;
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

        .empty-state p {
            font-size: 16px;
            margin: 0;
        }

        .scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }

        .scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        .status-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar scrollbar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-file-archive"></i>
                </div>
                <h1>Record Officer</h1>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="admin-dashboard-officer.php" class="nav-item active" title="Dashboard">
                            <i class="fas fa-th-large"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="manage-document.php" class="nav-item" title="Manage Documents">
                            <i class="fas fa-folder-open"></i>
                            <span>Manage Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="audit-record.php" class="nav-item" title="Audit Logs">
                            <i class="fas fa-history"></i>
                            <span>Audit Logs</span>
                        </a>
                    </li>
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
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page">
                <div class="page-header">
                    <h2>Record Officer Dashboard</h2>
                    <p>Manage documents and monitor your operations</p>
                </div>

                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-section-content">
                        <h2>Welcome to LGU Mercedes Document Tracking System</h2>
                        <p>Your centralized platform for managing and tracking administrative documents</p>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon stat-finished">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Finished Documents</div>
                            <div class="stat-value"><?php echo $finished_docs; ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon stat-returned">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Returned Documents</div>
                            <div class="stat-value"><?php echo $returned_docs; ?></div>
                        </div>
                    </div>


                </div>

                <!-- Your Permissions -->
                <h3 class="section-title"><i class="fas fa-lock-open" style="color: var(--primary-color);"></i> Your Permissions</h3>
                <div class="permissions-grid">
                    <ul class="permissions-list">
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Manage Documents - View, organize, and manage all document assignments</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Track Documents - Monitor document workflow and status updates in real-time</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Audit Logs - Access complete audit trail of all document operations</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Export Audit - Download audit logs as non-editable PDF for record keeping</span>
                        </li>
                    </ul>
                </div>

            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab-button').classList.add('active');
        }

        // Update active nav item
        document.querySelectorAll('.nav-item').forEach(item => {
            if (item.href === window.location.href) {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            }
        });
    </script>
</body>
</html>