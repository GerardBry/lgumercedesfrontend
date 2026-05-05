<?php
/**
 * Admin Dashboard - Super Admin Main Dashboard
 * Session-protected, role-based access
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
// Only Super Admin role can access this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: admin-login.php');
    exit;
}

// Explicitly block Administrative Assistant from accessing this page
if ($_SESSION['role'] === 'Administrative Assistant') {
    header('Location: ../administrative/admin-dashboard-staff.php');
    exit;
}

// Only allow Super Admin role - STRICT CHECK
if ($_SESSION['role'] !== 'Super Admin') {
    header('Location: admin-login.php');
    exit;
}

require_once '../config/db_connect.php';

// Verify user role in database (anti-session tampering)
$admin_id = $_SESSION['user_id'];
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Super Admin' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $admin_id);
    $role_check->execute();
    $role_result = $role_check->get_result();
    
    if ($role_result->num_rows === 0) {
        // Role mismatch - session may be tampered with
        session_destroy();
        header('Location: admin-login.php');
        exit;
    }
    $role_check->close();
}

// Get dashboard statistics
$stats = [
    'total_users' => 0,
    'pending_users' => 0,
    'approved_users' => 0,
    'inactive_users' => 0,
    'total_documents' => 0
];

// Count total users (excluding Mayor and Super Admin)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('Mayor', 'Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_users'] = $row['count'];
}

// Count pending users (excluding Mayor and Super Admin)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending' AND role NOT IN ('Mayor', 'Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['pending_users'] = $row['count'];
}

// Count approved users (excluding Mayor and Super Admin)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Approved' AND role NOT IN ('Mayor', 'Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['approved_users'] = $row['count'];
}

// Count inactive users (excluding Mayor and Super Admin)
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Inactive' AND role NOT IN ('Mayor', 'Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['inactive_users'] = $row['count'];
}

// Recent login attempts
$recent_logins = [];
$result = $conn->query("SELECT email, username, success, created_at FROM login_attempts ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_logins[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LGU Mercedes</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Dashboard Specific Styles */
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

        .admin-welcome-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            color: #ffffff;
        }

        .admin-welcome-section h2 {
            font-size: 32px;
            color: #ffffff;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .admin-welcome-section p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }

        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .admin-stat-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            gap: 16px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .admin-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .admin-stat-icon {
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

        .admin-stat-icon.stat-users {
            background: linear-gradient(135deg, #FF9500, #FFB347);
        }

        .admin-stat-icon.stat-pending {
            background: linear-gradient(135deg, #FFC107, #FFD54F);
        }

        .admin-stat-icon.stat-approved {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }

        .admin-stat-icon.stat-inactive {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        .admin-stat-content {
            flex: 1;
        }

        .admin-stat-label {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .admin-section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            margin-top: 40px;
            color: var(--text-dark);
        }

        .admin-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }

        .admin-action-btn {
            padding: 16px 24px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background-color: var(--bg-white);
            color: var(--text-dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .admin-action-btn:hover {
            background-color: var(--primary-color);
            color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }

        .admin-action-btn i {
            font-size: 20px;
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
                    <li><a href="admin-dashboard.php" class="admin-nav-item active" title="Dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a></li>
                    <li><a href="accounts.php" class="admin-nav-item" title="Manage Accounts">
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
                    <h2>Dashboard</h2>
                    <p>Welcome to the Admin Control Panel</p>
                </div>

                <!-- Welcome Section -->
                <div class="admin-welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</h2>
                    <p>You have full control over the system as Super Admin. Manage users, approve registrations, and monitor all activities.</p>
                </div>

                <!-- Statistics Grid -->
                <div class="admin-stats-grid">
                    <div class="admin-stat-card">
                        <div class="admin-stat-icon stat-users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Total Users</div>
                            <div class="admin-stat-value"><?php echo $stats['total_users']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card">
                        <div class="admin-stat-icon stat-pending">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Pending Approval</div>
                            <div class="admin-stat-value"><?php echo $stats['pending_users']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card">
                        <div class="admin-stat-icon stat-approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Approved Users</div>
                            <div class="admin-stat-value"><?php echo $stats['approved_users']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card">
                        <div class="admin-stat-icon stat-inactive">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Inactive Users</div>
                            <div class="admin-stat-value"><?php echo $stats['inactive_users']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Your Permissions -->
                <h3 class="admin-section-title"><i class="fas fa-lock-open" style="color: var(--primary-color);"></i> Your Permissions</h3>
                <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-md);">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: var(--primary-color); font-weight: bold;"></i>
                            <span>Deactivate user accounts</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: var(--primary-color); font-weight: bold;"></i>
                            <span>Assign and modify user roles & permissions</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: var(--primary-color); font-weight: bold;"></i>
                            <span>View all modules and system data</span>
                        </li>
                        <li style="padding: 12px 0; display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: var(--primary-color); font-weight: bold;"></i>
                            <span>Access audit trail and activity logs</span>
                        </li>
                    </ul>
                </div>
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
