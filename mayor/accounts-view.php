<?php
/**
 * Mayor - View Accounts (Read-Only)
 * View all accounts with details but no approve/reject functionality
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
// Only Mayor role can access this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Block Super Admin and Administrative Assistant from accessing this page
if ($_SESSION['role'] === 'Super Admin') {
    header('Location: ../admin/admin-dashboard.php');
    exit;
}

if ($_SESSION['role'] === 'Administrative Assistant') {
    header('Location: ../administrative/admin-dashboard-staff.php');
    exit;
}

// Only allow Mayor role - STRICT CHECK
if ($_SESSION['role'] !== 'Mayor') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Mayor';
$last_name = $_SESSION['last_name'] ?? '';

// Verify user role in database (anti-session tampering)
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Mayor' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $admin_id);
    $role_check->execute();
    $role_result = $role_check->get_result();
    
    if ($role_result->num_rows === 0) {
        // Role mismatch - session may be tampered with
        session_destroy();
        header('Location: ../login.php');
        exit;
    }
    $role_check->close();
}

// Get filter parameters
$filter_status = $_GET['filter_status'] ?? 'All';
$filter_office = $_GET['filter_office'] ?? 'All';
$search_name = $_GET['search_name'] ?? '';

// Build query
$where_conditions = [];
$where_conditions[] = "role NOT IN ('Super Admin', 'Mayor')"; // Don't show Super Admin or Mayor accounts

if ($filter_status !== 'All') {
    $status_safe = $conn->real_escape_string($filter_status);
    $where_conditions[] = "status = '$status_safe'";
}

if ($filter_office !== 'All') {
    $office_safe = $conn->real_escape_string($filter_office);
    $where_conditions[] = "office_department = '$office_safe'";
}

if (!empty($search_name)) {
    $search_safe = $conn->real_escape_string($search_name);
    $where_conditions[] = "(first_name LIKE '%$search_safe%' OR last_name LIKE '%$search_safe%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get all accounts
$accounts = [];
$sql = "SELECT id, first_name, last_name, middle_name, date_of_birth, civil_status, contact_number, position, office_department, house_no, street, barangay, municipality, province, email, username, role, status, created_at FROM users WHERE $where_clause ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

// Get available statuses
$statuses_result = $conn->query("SELECT DISTINCT status FROM users ORDER BY FIELD(status, 'Pending', 'Active', 'Inactive', 'Approved'), status");
$available_statuses = [];
if ($statuses_result) {
    while ($row = $statuses_result->fetch_assoc()) {
        $available_statuses[] = $row['status'];
    }
}

// Get available office departments
$offices_result = $conn->query("SELECT DISTINCT office_department FROM users WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
$available_offices = [];
if ($offices_result) {
    while ($row = $offices_result->fetch_assoc()) {
        $available_offices[] = $row['office_department'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts - Mayor Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Accounts Page - Matched to Admin Design */
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

        .filter-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
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
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
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
        }

        .btn-reset:hover {
            background: #d0d0d0;
        }

        /* Accounts Table */
        .accounts-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
        }

        .accounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .accounts-table thead {
            background-color: var(--bg-light);
        }

        .accounts-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .accounts-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .accounts-table tr:hover {
            background-color: var(--bg-light);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending { background-color: #fff3e0; color: #f57c00; }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
        .status-approved { background-color: #d1ecf1; color: #0c5460; }

        .role-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 16px;
            opacity: 0.3;
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

        .btn-close-modal {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-close-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        /* Make table rows clickable */
        .accounts-table tbody tr {
            transition: background-color 0.3s ease;
        }

        .accounts-table tbody tr:hover {
            background-color: rgba(255, 149, 0, 0.05);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(255, 149, 0, 0.3);
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
            .filter-row {
                flex-direction: column;
            }
            .accounts-table {
                font-size: 12px;
            }
            .accounts-table th,
            .accounts-table td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Mayor Sidebar -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h1>Mayor Panel</h1>
            </div>

            <nav class="admin-nav-menu">
                <ul>
                    <li>
                        <a href="admin-dashboard-mayor.php" class="admin-nav-item">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="accounts-view.php" class="admin-nav-item active">
                            <i class="fas fa-users"></i>
                            <span>Accounts</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="admin-nav-item" onclick="alert('Reports and analytics coming soon')">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Analytics</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="admin-nav-item" onclick="alert('Document status monitoring coming soon')">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Document Status</span>
                        </a>
                    </li>
                    <li class="divider"></li>
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
                <div class="admin-page-header">
                    <h2>View All Accounts</h2>
                    <p>View and monitor all system accounts.</p>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <select id="filter_status" name="filter_status" onchange="applyFilters()">
                                <option value="All">All Status</option>
                                <?php foreach ($available_statuses as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <input type="text" id="filter_name" name="filter_name" placeholder="Search by name..." value="<?php echo htmlspecialchars($search_name); ?>" oninput="applyFilters()">
                        </div>
                        <div class="filter-group">
                            <select id="filter_office" name="filter_office" onchange="applyFilters()">
                                <option value="All">All Offices</option>
                                <?php foreach ($available_offices as $office): ?>
                                    <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $filter_office === $office ? 'selected' : ''; ?>><?php echo htmlspecialchars($office); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-search" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="accounts-view.php" class="btn-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>

                <!-- Accounts Table -->
                <div class="accounts-section">
                    <h3>All Accounts (<?php echo count($accounts); ?> total)</h3>
                    
                    <?php if (empty($accounts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No accounts found</h4>
                            <p>Try adjusting your filters or search criteria</p>
                        </div>
                    <?php else: ?>
                        <table class="accounts-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Date Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): 
                                    $account_json = htmlspecialchars(json_encode($account), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($account['email']); ?></td>
                                        <td><?php echo htmlspecialchars($account['username']); ?></td>
                                        <td>
                                            <span class="role-badge"><?php echo htmlspecialchars($account['role']); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($account['status']); ?>">
                                                <?php echo htmlspecialchars($account['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($account['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-view" onclick="openUserModal(<?php echo $account_json; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal (View-Only) -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Account Details</h2>
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
                            <label>Role</label>
                            <p id="modal-role"></p>
                        </div>
                        <div class="modal-field">
                            <label>Status</label>
                            <p><span id="modal-badge"></span></p>
                        </div>
                        <div class="modal-field">
                            <label>Registered</label>
                            <p id="modal-registered"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-close-modal" onclick="closeUserModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        let currentUser = null;

        // Wait for DOM to be ready before setting up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update active nav item
            document.querySelectorAll('.admin-nav-item').forEach(item => {
                if (item.href === window.location.href) {
                    document.querySelectorAll('.admin-nav-item').forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                }
            });

            // Close modal when clicking on overlay
            const userModal = document.getElementById('userModal');
            if (userModal) {
                userModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closeUserModal();
                    }
                });
            }

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeUserModal();
                }
            });
        });

        function applyFilters() {
            const status = document.getElementById('filter_status').value;
            const name = document.getElementById('filter_name').value;
            const office = document.getElementById('filter_office').value;
            
            const params = new URLSearchParams();
            if (status !== 'All') params.append('filter_status', status);
            if (name) params.append('search_name', name);
            if (office !== 'All') params.append('filter_office', office);
            
            const url = 'accounts-view.php' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        }

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
            document.getElementById('modal-role').textContent = user.role || 'N/A';
            
            // Set badge
            const badge = document.getElementById('modal-badge');
            badge.textContent = user.status;
            badge.className = 'status-badge status-' + user.status.toLowerCase();
            
            // Set registered date
            const regDate = new Date(user.created_at);
            document.getElementById('modal-registered').textContent = regDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            
            // Show modal
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
            currentUser = null;
        }
    </script>
</body>
</html>
