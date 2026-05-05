<?php
/**
 * Admin - Manage Accounts
 * View all pending accounts with filtering and approve/reject functionality
 */

session_start();

// Check if user is logged in and is Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Super Admin') {
    header('Location: admin-login.php');
    exit;
}

require_once '../config/db_connect.php';

// Handle user actions (approve, reject, deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = intval($_POST['user_id']);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($action === 'approve') {
        // Use direct query to avoid prepared statement issues
        $user_id_safe = intval($user_id);
        $update_result = $conn->query("UPDATE users SET status = 'Active' WHERE id = " . $user_id_safe);
        
        if ($update_result === false) {
            die("Update failed: " . $conn->error);
        }
        
        if ($conn->affected_rows > 0) {
            // Verify the update
            $verify = $conn->query("SELECT status FROM users WHERE id = " . $user_id_safe);
            $row = $verify->fetch_assoc();
            
            // Log the action
            $session_id = intval($_SESSION['user_id']);
            $details = "User ID: " . $user_id_safe . " approved and activated. New status: " . $row['status'];
            $details_safe = $conn->real_escape_string($details);
            $ip_safe = $conn->real_escape_string($ip);
            
            $conn->query("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (" . $session_id . ", 'Approve User', '" . $details_safe . "', '" . $ip_safe . "')");
        }
        
        // Redirect to refresh
        header('Location: accounts.php?filter_status=All');
        exit;
    } elseif ($action === 'reject') {
        $rejection_remarks = $_POST['rejection_remarks'] ?? 'No remarks provided';
        $user_id_safe = intval($user_id);
        $remarks_safe = $conn->real_escape_string($rejection_remarks);
        
        $update_result = $conn->query("UPDATE users SET status = 'Inactive', rejection_remarks = '" . $remarks_safe . "' WHERE id = " . $user_id_safe);
        
        if ($update_result === false) {
            die("Update failed: " . $conn->error);
        }
        
        if ($conn->affected_rows > 0) {
            // Log the action
            $session_id = intval($_SESSION['user_id']);
            $details = "User ID: " . $user_id_safe . " rejected. Remarks: " . $remarks_safe;
            $details_safe = $conn->real_escape_string($details);
            $ip_safe = $conn->real_escape_string($ip);
            
            $conn->query("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (" . $session_id . ", 'Reject User', '" . $details_safe . "', '" . $ip_safe . "')");
        }
        
        header('Location: accounts.php?filter_status=All');
        exit;
    } elseif ($action === 'deactivate') {
        $user_id_safe = intval($user_id);
        $admin_password = $_POST['admin_password'] ?? '';
        $deactivation_reason = $_POST['deactivation_reason'] ?? 'No reason provided';
        
        // Verify admin password
        $admin_id = intval($_SESSION['user_id']);
        $admin_check = $conn->query("SELECT password FROM users WHERE id = " . $admin_id);
        
        if (!$admin_check || $admin_check->num_rows === 0) {
            die("Error: Admin account not found");
        }
        
        $admin_data = $admin_check->fetch_assoc();
        
        if (!password_verify($admin_password, $admin_data['password'])) {
            die("Error: Incorrect password. Deactivation cancelled for security reasons.");
        }
        
        // Password verified, proceed with deactivation
        $reason_safe = $conn->real_escape_string($deactivation_reason);
        $update_result = $conn->query("UPDATE users SET status = 'Inactive', rejection_remarks = '" . $reason_safe . "' WHERE id = " . $user_id_safe);
        
        if ($update_result === false) {
            die("Update failed: " . $conn->error);
        }
        
        if ($conn->affected_rows > 0) {
            // Log the action
            $session_id = intval($_SESSION['user_id']);
            $details = "User ID: " . $user_id_safe . " deactivated by admin. Reason: " . $reason_safe;
            $details_safe = $conn->real_escape_string($details);
            $ip_safe = $conn->real_escape_string($ip);
            
            $conn->query("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (" . $session_id . ", 'Deactivate User', '" . $details_safe . "', '" . $ip_safe . "')");
        }
        
        header('Location: accounts.php?filter_status=All');
        exit;
    }
}

// Get filter parameters
$filter_status = $_GET['filter_status'] ?? 'All';
$filter_name = trim($_GET['filter_name'] ?? '');
$filter_office = trim($_GET['filter_office'] ?? '');

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($filter_status !== 'All') {
    $where_clauses[] = 'status = ?';
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_name)) {
    $where_clauses[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
    $search = '%' . $filter_name . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if (!empty($filter_office)) {
    $where_clauses[] = 'office_department = ?';
    $params[] = $filter_office;
    $types .= 's';
}

$where_sql = empty($where_clauses) ? 'WHERE role NOT IN ("Mayor", "Super Admin")' : 'WHERE ' . implode(' AND ', $where_clauses) . ' AND role NOT IN ("Mayor", "Super Admin")';
$sql = "SELECT id, first_name, last_name, email, username, position, office_department, status, created_at, civil_status, date_of_birth, contact_number, house_no, street, barangay, municipality, province, middle_name, rejection_remarks FROM users $where_sql ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$users = [];

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

// Get unique offices for filter dropdown
$offices = [];
$result = $conn->query("SELECT DISTINCT office_department FROM users WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $offices[] = $row['office_department'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Admin Dashboard</title>
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

        .admin-page-header {
            margin-bottom: 32px;
        }

        .admin-page-header h2 {
            font-size: 28px;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .admin-page-header p {
            font-size: 14px;
            color: var(--text-light);
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

        .filter-section {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }

        .filter-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            outline: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(255, 149, 0, 0.3);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.4);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-success {
            background-color: #28a745;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #ffffff;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-search {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            height: fit-content;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        .btn-reset {
            background: #e0e0e0;
            color: var(--text-dark);
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            flex-shrink: 0;
            height: fit-content;
        }

        .btn-reset:hover {
            background: #d0d0d0;
        }

        .table-container {
            background-color: var(--bg-white);
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
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 16px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--border-color);
            margin-bottom: 20px;
            display: block;
        }

        .empty-state h3 {
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-light);
        }

        /* Scrollbar styling */
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

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-section {
            margin-bottom: 24px;
        }

        .modal-section h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-light);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-field {
            display: flex;
            flex-direction: column;
        }

        .modal-field label {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .modal-field p {
            font-size: 14px;
            color: var(--text-dark);
            margin: 0;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .modal-footer .btn {
            margin: 0;
        }

        .reject-form {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background-color: var(--bg-light);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .reject-form.active {
            display: block;
        }

        .reject-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .reject-form-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Admin Panel</h1>
            </div>

            <div class="admin-nav-menu">
                <ul>
                    <li><a href="admin-dashboard.php" class="admin-nav-item" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="accounts.php" class="admin-nav-item active" title="Manage Accounts">
                        <i class="fas fa-users"></i>
                        <span>Accounts</span>
                    </a></li>
                    <li><a href="audit-logs.php" class="admin-nav-item" title="Audit Logs">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a></li>
                    <li class="divider"></li>
                    <li><a href="trackdocument.php" class="admin-nav-item" title="Track Document">
                        <i class="fas fa-map-location-dot"></i>
                        <span>Track Document</span>
                    </a></li>
                </ul>
            </div>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
                    </div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                    </div>
                </div>
                <a href="admin-logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main-content">
            <div class="admin-page">
                <div class="admin-page-header">
                    <h2>Manage Accounts</h2>
                    <p>View and manage all user accounts</p>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form method="GET" action="" id="filterForm">
                        <div class="filter-row">
                            <div class="filter-group">
                                <select name="filter_status">
                                    <option value="All" <?php echo $filter_status === 'All' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                    <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <input type="text" name="filter_name" placeholder="Search by name..." value="<?php echo htmlspecialchars($filter_name); ?>">
                            </div>

                            <div class="filter-group">
                                <select name="filter_office">
                                    <option value="">All Offices</option>
                                    <?php foreach ($offices as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $filter_office === $office ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($office); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn-search">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="accounts.php" class="btn-reset">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Accounts Table -->
                <?php if (count($users) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Position</th>
                                    <th>Office/Department</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                            <small style="color: var(--text-light);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['office_department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($user['status']); ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="openUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
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
                        <h3>No Accounts Found</h3>
                        <p>There are no accounts matching your filter criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>User Details</h2>
                <button class="modal-close" onclick="closeUserModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <!-- Personal Information -->
                <div class="modal-section">
                    <h3>Personal Information</h3>
                    <div class="modal-details">
                        <div class="modal-field">
                            <label>First Name</label>
                            <p id="modal-first-name"></p>
                        </div>
                        <div class="modal-field">
                            <label>Last Name</label>
                            <p id="modal-last-name"></p>
                        </div>
                        <div class="modal-field">
                            <label>Middle Name</label>
                            <p id="modal-middle-name"></p>
                        </div>
                        <div class="modal-field">
                            <label>Date of Birth</label>
                            <p id="modal-dob"></p>
                        </div>
                        <div class="modal-field">
                            <label>Civil Status</label>
                            <p id="modal-civil-status"></p>
                        </div>
                        <div class="modal-field">
                            <label>Contact Number</label>
                            <p id="modal-contact"></p>
                        </div>
                    </div>
                </div>

                <!-- Work Information -->
                <div class="modal-section">
                    <h3>Work Information</h3>
                    <div class="modal-details">
                        <div class="modal-field">
                            <label>Position</label>
                            <p id="modal-position"></p>
                        </div>
                        <div class="modal-field">
                            <label>Office/Department</label>
                            <p id="modal-office"></p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="modal-section">
                    <h3>Address</h3>
                    <div class="modal-details">
                        <div class="modal-field">
                            <label>House No.</label>
                            <p id="modal-house-no"></p>
                        </div>
                        <div class="modal-field">
                            <label>Street</label>
                            <p id="modal-street"></p>
                        </div>
                        <div class="modal-field">
                            <label>Barangay</label>
                            <p id="modal-barangay"></p>
                        </div>
                        <div class="modal-field">
                            <label>Municipality</label>
                            <p id="modal-municipality"></p>
                        </div>
                        <div class="modal-field">
                            <label>Province</label>
                            <p id="modal-province"></p>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="modal-section">
                    <h3>Account Information</h3>
                    <div class="modal-details">
                        <div class="modal-field">
                            <label>Username</label>
                            <p id="modal-username"></p>
                        </div>
                        <div class="modal-field">
                            <label>Email</label>
                            <p id="modal-email"></p>
                        </div>
                        <div class="modal-field">
                            <label>Status</label>
                            <p><span class="badge" id="modal-badge"></span></p>
                        </div>
                        <div class="modal-field">
                            <label>Registered</label>
                            <p id="modal-registered"></p>
                        </div>
                    </div>
                </div>

                <!-- Rejection Remarks Form -->
                <div class="reject-form" id="rejectForm">
                    <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Reason for Rejection</label>
                    <textarea id="rejectionRemarks" placeholder="Enter the reason for rejection..."></textarea>
                    <div class="reject-form-buttons">
                        <button class="btn btn-danger btn-sm" onclick="submitRejection()">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="cancelRejection()">
                            <i class="fas fa-undo"></i> Cancel
                        </button>
                    </div>
                </div>

                <!-- Deactivation Form -->
                <div class="reject-form" id="deactivateForm">
                    <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Verify your password</label>
                    <input type="password" id="adminPassword" placeholder="Enter your password for verification..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; margin-bottom: 12px;">
                    
                    <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Reason for Deactivation</label>
                    <textarea id="deactivationReasons" placeholder="Enter the reason for deactivation..."></textarea>
                    <div class="reject-form-buttons">
                        <button class="btn btn-danger btn-sm" onclick="submitDeactivation()">
                            <i class="fas fa-ban"></i> Confirm Deactivation
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="cancelDeactivation()">
                            <i class="fas fa-undo"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="modalFooter">
                <!-- Action buttons will be inserted here by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentUser = null;

        function openUserModal(user) {
            currentUser = user;
            
            // Populate modal fields
            document.getElementById('modal-first-name').textContent = user.first_name || 'N/A';
            document.getElementById('modal-last-name').textContent = user.last_name || 'N/A';
            document.getElementById('modal-middle-name').textContent = user.middle_name || 'N/A';
            document.getElementById('modal-dob').textContent = user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString() : 'N/A';
            document.getElementById('modal-civil-status').textContent = user.civil_status || 'N/A';
            document.getElementById('modal-contact').textContent = user.contact_number || 'N/A';
            document.getElementById('modal-position').textContent = user.position || 'N/A';
            document.getElementById('modal-office').textContent = user.office_department || 'N/A';
            document.getElementById('modal-house-no').textContent = user.house_no || 'N/A';
            document.getElementById('modal-street').textContent = user.street || 'N/A';
            document.getElementById('modal-barangay').textContent = user.barangay || 'N/A';
            document.getElementById('modal-municipality').textContent = user.municipality || 'N/A';
            document.getElementById('modal-province').textContent = user.province || 'N/A';
            document.getElementById('modal-username').textContent = user.username || 'N/A';
            document.getElementById('modal-email').textContent = user.email || 'N/A';
            
            // Set badge
            const badge = document.getElementById('modal-badge');
            badge.textContent = user.status;
            badge.className = 'badge badge-' + user.status.toLowerCase();
            
            // Set registered date
            const regDate = new Date(user.created_at);
            document.getElementById('modal-registered').textContent = regDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            
            // Set action buttons based on status
            const footer = document.getElementById('modalFooter');
            footer.innerHTML = '';
            
            if (user.status === 'Pending') {
                footer.innerHTML = `
                    <button class="btn btn-success" onclick="approveUser(${user.id})">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-danger" onclick="showRejectForm()">
                        <i class="fas fa-times"></i> Reject
                    </button>
                `;
            } else if (user.status === 'Active') {
                footer.innerHTML = `
                    <button class="btn btn-danger" onclick="deactivateUser(${user.id})">
                        <i class="fas fa-ban"></i> Deactivate
                    </button>
                    <button class="btn btn-secondary" onclick="closeUserModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                `;
            } else {
                footer.innerHTML = `
                    <button class="btn btn-secondary" onclick="closeUserModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                `;
            }
            
            // Hide rejection form
            document.getElementById('rejectForm').classList.remove('active');
            document.getElementById('rejectionRemarks').value = '';
            
            // Show modal
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
            currentUser = null;
        }

        function showRejectForm() {
            document.getElementById('rejectForm').classList.add('active');
        }

        function cancelRejection() {
            document.getElementById('rejectForm').classList.remove('active');
            document.getElementById('rejectionRemarks').value = '';
        }

        function approveUser(userId) {
            if (confirm('Approve this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'approve';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(actionInput);
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deactivateUser(userId) {
            // Show deactivation form
            document.getElementById('deactivateForm').classList.add('active');
            document.getElementById('adminPassword').value = '';
            document.getElementById('deactivationReasons').value = '';
        }

        function cancelDeactivation() {
            document.getElementById('deactivateForm').classList.remove('active');
            document.getElementById('adminPassword').value = '';
            document.getElementById('deactivationReasons').value = '';
        }

        function submitDeactivation() {
            const password = document.getElementById('adminPassword').value.trim();
            const reason = document.getElementById('deactivationReasons').value.trim();
            
            if (!password) {
                alert('Please enter your password for verification.');
                return;
            }
            
            if (!reason) {
                alert('Please enter a reason for deactivation.');
                return;
            }
            
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'deactivate';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = currentUser.id;
            
            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'admin_password';
            passwordInput.value = password;
            
            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'deactivation_reason';
            reasonInput.value = reason;
            
            form.appendChild(actionInput);
            form.appendChild(userIdInput);
            form.appendChild(passwordInput);
            form.appendChild(reasonInput);
            document.body.appendChild(form);
            form.submit();
        }

        function submitRejection() {
            const remarks = document.getElementById('rejectionRemarks').value.trim();
            if (!remarks) {
                alert('Please enter a reason for rejection.');
                return;
            }
            
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'reject';
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = currentUser.id;
            
            const remarksInput = document.createElement('input');
            remarksInput.type = 'hidden';
            remarksInput.name = 'rejection_remarks';
            remarksInput.value = remarks;
            
            form.appendChild(actionInput);
            form.appendChild(userIdInput);
            form.appendChild(remarksInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        document.getElementById('userModal').addEventListener('click', (e) => {
            if (e.target.id === 'userModal') {
                closeUserModal();
            }
        });

        // Dynamic filtering - auto-submit form on filter change
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const filterInputs = filterForm.querySelectorAll('select, input[name="filter_name"]');
            
            filterInputs.forEach(input => {
                if (input.name === 'filter_name') {
                    // For name input, use a debounce to avoid too many submissions
                    let debounceTimer;
                    input.addEventListener('input', () => {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                            filterForm.submit();
                        }, 500);
                    });
                } else {
                    // For selects, submit immediately on change
                    input.addEventListener('change', () => {
                        filterForm.submit();
                    });
                }
            });
        }
    </script>
</body>
</html>
