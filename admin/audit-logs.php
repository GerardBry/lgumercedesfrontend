<?php
/**
 * Admin Audit Logs
 * Comprehensive system audit trail viewer
 * Super Admin only - tracks all system activities
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: admin-login.php');
    exit;
}

if ($_SESSION['role'] === 'Administrative Assistant') {
    header('Location: ../administrative/admin-dashboard-staff.php');
    exit;
}

if ($_SESSION['role'] !== 'Super Admin') {
    header('Location: admin-login.php');
    exit;
}

require_once '../config/db_connect.php';

// Verify user role in database
$admin_id = $_SESSION['user_id'];
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Super Admin' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $admin_id);
    $role_check->execute();
    $role_result = $role_check->get_result();
    
    if ($role_result->num_rows === 0) {
        session_destroy();
        header('Location: admin-login.php');
        exit;
    }
    $role_check->close();
}

// Get filter parameters
$filter_action = $_GET['filter_action'] ?? 'All';
$filter_user = trim($_GET['filter_user'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$search_ip = trim($_GET['search_ip'] ?? '');
$page = intval($_GET['page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query
$where_clauses = [];
$params = [];
$types = "";

if ($filter_action !== 'All') {
    $where_clauses[] = "at.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if (!empty($filter_user)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= "sss";
}

if (!empty($filter_start_date)) {
    $where_clauses[] = "DATE(at.created_at) >= ?";
    $params[] = $filter_start_date;
    $types .= "s";
}

if (!empty($filter_end_date)) {
    $where_clauses[] = "DATE(at.created_at) <= ?";
    $params[] = $filter_end_date;
    $types .= "s";
}

if (!empty($search_ip)) {
    $where_clauses[] = "at.ip_address LIKE ?";
    $params[] = "%$search_ip%";
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM audit_trail at 
              LEFT JOIN users u ON at.user_id = u.id 
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['count'];
$total_pages = ceil($total_records / $limit);
$count_stmt->close();

// Get audit logs
$audit_logs = [];
$sql = "SELECT 
        at.id,
        at.user_id,
        at.action,
        at.details,
        at.ip_address,
        at.created_at,
        u.first_name,
        u.last_name,
        u.email,
        u.role as user_role
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    $where_sql
    ORDER BY at.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $audit_logs[] = $row;
}
$stmt->close();

// Get unique actions for filter dropdown
$actions = [];
$actions_result = $conn->query("SELECT DISTINCT action FROM audit_trail ORDER BY action ASC");
if ($actions_result) {
    while ($row = $actions_result->fetch_assoc()) {
        $actions[] = $row['action'];
    }
}

$conn->close();

// Helper function to get action icon and color
function getActionBadge($action) {
    $badges = [
        'Admin Login' => ['icon' => 'fa-sign-in-alt', 'color' => '#0d7377', 'bg' => '#d1f4f8'],
        'Admin Logout' => ['icon' => 'fa-sign-out-alt', 'color' => '#ff6b6b', 'bg' => '#ffe0e0'],
        'Approve User' => ['icon' => 'fa-check-circle', 'color' => '#28a745', 'bg' => '#d4edda'],
        'Reject User' => ['icon' => 'fa-times-circle', 'color' => '#dc3545', 'bg' => '#f8d7da'],
        'Deactivate User' => ['icon' => 'fa-ban', 'color' => '#ffc107', 'bg' => '#fff3cd'],
        'User Logout' => ['icon' => 'fa-door-open', 'color' => '#6f42c1', 'bg' => '#f0f0ff'],
        'User Registration' => ['icon' => 'fa-user-plus', 'color' => '#17a2b8', 'bg' => '#d1ecf1'],
        'Profile Completion' => ['icon' => 'fa-user-check', 'color' => '#20c997', 'bg' => '#d1e7dd'],
        'Change Password' => ['icon' => 'fa-key', 'color' => '#fd7e14', 'bg' => '#ffe5cc'],
        'Document Assignment' => ['icon' => 'fa-file-export', 'color' => '#6f42c1', 'bg' => '#f0f0ff'],
        'Document Received' => ['icon' => 'fa-check', 'color' => '#28a745', 'bg' => '#d4edda'],
        'Document Forwarded' => ['icon' => 'fa-share', 'color' => '#0d7377', 'bg' => '#d1f4f8'],
        'Document Returned' => ['icon' => 'fa-undo', 'color' => '#ffc107', 'bg' => '#fff3cd'],
        'Document Archived' => ['icon' => 'fa-archive', 'color' => '#6f42c1', 'bg' => '#f0f0ff'],
    ];
    
    return $badges[$action] ?? ['icon' => 'fa-info-circle', 'color' => '#6c757d', 'bg' => '#f8f9fa'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Dashboard Specific Styles */
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
            --primary-color: #66BB6A;
            --primary-light: #81C784;
            --primary-dark: #4CAF50;
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
            background: linear-gradient(135deg, #66BB6A, #81C784);
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
        }

        .admin-nav-item i { width: 20px; text-align: center; }

        .admin-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .admin-nav-item.active {
            background: linear-gradient(135deg, #66BB6A, #81C784);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(102, 187, 106, 0.32);
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
            background: linear-gradient(135deg, #66BB6A, #81C784);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .admin-user-info { flex: 1; }

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

        /* Filter Section */
        .filter-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-white);
            color: var(--text-dark);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #66BB6A;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #57A95C;
        }

        .admin-main-content .btn-search,
        .admin-main-content .btn-primary {
            background: linear-gradient(135deg, #66BB6A, #81C784);
            border-color: #66BB6A;
            color: #ffffff;
        }

        .admin-main-content .btn-search:hover,
        .admin-main-content .btn-primary:hover {
            background: linear-gradient(135deg, #57A95C, #72B875);
            border-color: #57A95C;
            color: #ffffff;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        /* Table Section */
        .table-container {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
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

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name { font-weight: 600; color: #000; }
        .user-role { font-size: 12px; color: #333; }

        .timestamp {
            font-size: 13px;
            color: var(--text-light);
            white-space: nowrap;
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-light);
        }

        .details-text {
            font-size: 13px;
            color: var(--text-dark);
            line-height: 1.5;
            max-width: 300px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text-dark);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: var(--primary-color);
            color: #ffffff;
            border-color: var(--primary-color);
        }

        .pagination .active {
            background-color: var(--primary-color);
            color: #ffffff;
            border-color: var(--primary-color);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .record-count {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
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
                    <li><a href="accounts.php" class="admin-nav-item" title="Manage Accounts">
                        <i class="fas fa-users"></i>
                        <span>Accounts</span>
                    </a></li>
                    <li><a href="audit-logs.php" class="admin-nav-item active" title="Audit Logs">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a></li>
                </ul>
            </div>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></div>
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
                <div class="page-header">
                    <h2><i class="fas fa-history" style="color: var(--primary-color); margin-right: 12px;"></i>Audit Logs</h2>
                    <p>Track all system activities and user actions</p>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter" style="color: var(--primary-color); margin-right: 8px;"></i>Filter Results
                    </div>
                    <form method="GET" action="audit-logs.php">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter_action">Action Type</label>
                                <select id="filter_action" name="filter_action">
                                    <option value="All">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($action); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filter_user">User Name / Email</label>
                                <input type="text" id="filter_user" name="filter_user" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($filter_user); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="filter_start_date">Start Date</label>
                                <input type="date" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="filter_end_date">End Date</label>
                                <input type="date" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="search_ip">IP Address</label>
                                <input type="text" id="search_ip" name="search_ip" placeholder="Search by IP..." value="<?php echo htmlspecialchars($search_ip); ?>">
                            </div>

                            <div class="filter-group" style="justify-content: flex-end; gap: 12px; padding-top: 26px; flex-direction: row;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="audit-logs.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Audit Logs Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Timestamp</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($audit_logs) > 0): ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <?php 
                                    $badge = getActionBadge($log['action']);
                                    $user_name = $log['first_name'] ? htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) : 'System';
                                    $user_email = $log['email'] ? htmlspecialchars($log['email']) : 'N/A';
                                    $user_role = $log['user_role'] ? htmlspecialchars($log['user_role']) : 'N/A';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="action-badge" style="background-color: <?php echo $badge['bg']; ?>;">
                                                <i class="fas <?php echo $badge['icon']; ?>" style="color: <?php echo $badge['color']; ?>;"></i>
                                                <span style="color: <?php echo $badge['color']; ?>;"><?php echo htmlspecialchars($log['action']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name"><?php echo $user_name; ?></div>
                                                <div class="user-role"><?php echo $user_role; ?> • <?php echo $user_email; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="timestamp">
                                                <?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?>
                                                <br>
                                                <small style="color: #999;"><?php echo date('l', strtotime($log['created_at'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ip-address">
                                                <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No audit logs found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="audit-logs.php?page=1&filter_action=<?php echo urlencode($filter_action); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>&search_ip=<?php echo urlencode($search_ip); ?>">« First</a>
                            <a href="audit-logs.php?page=<?php echo $page - 1; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>&search_ip=<?php echo urlencode($search_ip); ?>">‹ Previous</a>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="audit-logs.php?page=<?php echo $i; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>&search_ip=<?php echo urlencode($search_ip); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="audit-logs.php?page=<?php echo $page + 1; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>&search_ip=<?php echo urlencode($search_ip); ?>">Next ›</a>
                            <a href="audit-logs.php?page=<?php echo $total_pages; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>&search_ip=<?php echo urlencode($search_ip); ?>">Last »</a>
                        <?php endif; ?>
                    </div>

                    <div class="record-count">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update active nav item
        document.querySelectorAll('.admin-nav-item').forEach(item => {
            if (item.href === window.location.href) {
                document.querySelectorAll('.admin-nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            }
        });
    </script>
</body>
</html>
