<?php
/**
 * Mayor Dashboard - View-Only Dashboard (Matched to Super Admin Design)
 * Session-protected, role-based access for Mayor role
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

// Get dashboard statistics
$stats = [
    'total_accounts' => 0,
    'pending_accounts' => 0,
    'active_accounts' => 0,
    'inactive_accounts' => 0,
    'total_documents' => 0,
    'pending_documents' => 0
];

// Count total accounts
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role NOT IN ('Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_accounts'] = $row['count'];
}

// Count pending accounts
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending' AND role NOT IN ('Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['pending_accounts'] = $row['count'];
}

// Count active accounts
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active' AND role NOT IN ('Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['active_accounts'] = $row['count'];
}

// Count inactive accounts
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Inactive' AND role NOT IN ('Super Admin')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['inactive_accounts'] = $row['count'];
}

// Count total documents
$result = $conn->query("SELECT COUNT(*) as count FROM documents");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_documents'] = $row['count'];
}

// Count pending documents
$result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE status = 'Pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['pending_documents'] = $row['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor Dashboard - LGU Mercedes</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mayor Dashboard - Matched to Admin Design (Orange Theme) */
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

        /* Stats Cards - Matched to Admin Design */
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

        .admin-stat-icon.stat-total {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }

        .admin-stat-icon.stat-pending {
            background: linear-gradient(135deg, #FFC107, #FFD54F);
        }

        .admin-stat-icon.stat-active {
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

        /* Section Styling */
        .mayor-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }

        .mayor-section h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .coming-soon-badge {
            background-color: #f0f0f0;
            color: #999;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
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
            color: #28a745;
            font-weight: bold;
        }

        @media (max-width: 1024px) {
            .admin-page {
                padding: 32px 24px;
            }
            .admin-stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            .admin-stats-grid {
                grid-template-columns: 1fr;
            }
            .admin-stat-card {
                padding: 20px;
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
                        <a href="admin-dashboard-mayor.php" class="admin-nav-item active">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="accounts-view.php" class="admin-nav-item">
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
                    <h2>Mayor Dashboard</h2>
                    <p>Welcome <?php echo htmlspecialchars($first_name); ?>! View system overview and key metrics.</p>
                </div>

                <!-- Welcome Section -->
                <div class="admin-welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($first_name); ?>! 👋</h2>
                    <p>Your centralized view of the Document Tracking System. Monitor accounts, documents, and system activities.</p>
                </div>

                <!-- Accounts Statistics -->
                <div class="mayor-section">
                    <h3>
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        Total Accounts
                    </h3>
                    <div class="admin-stats-grid">
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-total">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Total Accounts</div>
                                <div class="admin-stat-value"><?php echo $stats['total_accounts']; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-pending">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Pending</div>
                                <div class="admin-stat-value"><?php echo $stats['pending_accounts']; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-active">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Active</div>
                                <div class="admin-stat-value"><?php echo $stats['active_accounts']; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-inactive">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Inactive</div>
                                <div class="admin-stat-value"><?php echo $stats['inactive_accounts']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports & Analytics -->
                <div class="mayor-section">
                    <h3>
                        <i class="fas fa-chart-bar" style="color: var(--primary-color);"></i>
                        Reports & Analytics
                        <span class="coming-soon-badge">Coming Soon</span>
                    </h3>
                    <p style="color: #999; margin: 0;">Generate comprehensive reports and analytics on system usage and document processing metrics.</p>
                </div>

                <!-- Document & Request Status -->
                <div class="mayor-section">
                    <h3>
                        <i class="fas fa-clipboard-list" style="color: var(--primary-color);"></i>
                        Monitor
                    </h3>
                    <ul class="permissions-list">
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Status of documents, requests, and tasks</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>View system-wide document processing metrics</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Monitor departmental performance and workflows</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Track constituent requests and resolution status</span>
                        </li>
                    </ul>
                </div>

                <!-- Your Permissions -->
                <div class="mayor-section">
                    <h3>
                        <i class="fas fa-lock-open" style="color: var(--primary-color);"></i>
                        Your Permissions
                    </h3>
                    <ul class="permissions-list">
                        <li>
                            <i class="fas fa-check"></i>
                            <span>View Dashboard with key metrics and statistics</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Access Reports and Analytics (coming soon)</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Monitor document and request status</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>View system-wide performance metrics</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
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
