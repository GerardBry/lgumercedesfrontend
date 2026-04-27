<?php
/**
 * Record Officer Dashboard
 * Manage and view archived documents and records
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
// Only Record Officer role can access this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Block Super Admin, Mayor, and Administrative Assistant from accessing this page
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

// Only allow Record Officer role - STRICT CHECK
if ($_SESSION['role'] !== 'Record Officer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$officer_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Record Officer';
$last_name = $_SESSION['last_name'] ?? '';

// Verify user role in database (anti-session tampering)
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Record Officer' LIMIT 1");
if ($role_check) {
    $role_check->bind_param("i", $officer_id);
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

// Get record statistics with error handling
$total_records = 0;
$records_this_month = 0;
$pending_verification = 0;
$verified_records = 0;

// Try to get statistics, but don't fail if tables don't exist
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('Staff', 'Employee', 'Administrative Assistant')");
if ($result) {
    $row = $result->fetch_assoc();
    $total_records = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('Staff', 'Employee', 'Administrative Assistant') AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
if ($result) {
    $row = $result->fetch_assoc();
    $records_this_month = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $pending_verification = $row['count'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'");
if ($result) {
    $row = $result->fetch_assoc();
    $verified_records = $row['count'] ?? 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Officer Dashboard - MO-ATWMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --shadow-md: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.15);
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .admin-container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar */
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

        .divider {
            margin: 12px 0;
            height: 1px;
            background-color: rgba(255, 255, 255, 0.1);
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

        /* Main Content */
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
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .admin-page-header p {
            color: var(--text-light);
            font-size: 14px;
        }

        .admin-welcome-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            color: #ffffff;
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
        }

        .admin-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Sections */
        .admin-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }

        .admin-section h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
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
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 240px;
            }
            .admin-main-content {
                margin-left: 240px;
            }
            .admin-page {
                padding: 16px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .admin-sidebar {
                width: 200px;
            }
            .admin-main-content {
                margin-left: 200px;
            }
            .admin-page-header h2 {
                font-size: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Record Officer Sidebar -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <h1>Record Officer</h1>
            </div>

            <nav class="admin-nav-menu">
                <ul>
                    <li>
                        <a href="admin-dashboard-officer.php" class="admin-nav-item active">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="archived-documents.php" class="admin-nav-item">
                            <i class="fas fa-file-archive"></i>
                            <span>Archived Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="record-verification.php" class="admin-nav-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Verification</span>
                        </a>
                    </li>
                    <li>
                        <a href="document-retrieval.php" class="admin-nav-item">
                            <i class="fas fa-search"></i>
                            <span>Document Retrieval</span>
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
                    <h2>Records Dashboard</h2>
                    <p>Manage archived documents and records</p>
                </div>

                <!-- Welcome Section -->
                <div class="admin-welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</h2>
                    <p>You have full access to manage archived documents and records. View, verify, and retrieve records as needed.</p>
                </div>

                <!-- Statistics Grid -->
                <div class="admin-stats-grid">
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-total">
                                <i class="fas fa-archive"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Total Records</div>
                                <div class="admin-stat-value"><?php echo $total_records; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-pending">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Pending</div>
                                <div class="admin-stat-value"><?php echo $pending_verification; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-active">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">Verified</div>
                                <div class="admin-stat-value"><?php echo $verified_records; ?></div>
                            </div>
                        </div>

                        <div class="admin-stat-card">
                            <div class="admin-stat-icon stat-inactive">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="admin-stat-content">
                                <div class="admin-stat-label">This Month</div>
                                <div class="admin-stat-value"><?php echo $records_this_month; ?></div>
                            </div>
                        </div>
                    </div>

                <!-- Monitor Section -->
                <div class="admin-section">
                    <h2>
                        <i class="fas fa-tasks"></i> Quick Actions
                    </h2>
                    <ul class="permissions-list">
                        <li>
                            <i class="fas fa-file-archive"></i>
                            <span>View Archives - Browse all archived documents</span>
                        </li>
                        <li>
                            <i class="fas fa-check-double"></i>
                            <span>Verify Records - Verify and approve pending records</span>
                        </li>
                        <li>
                            <i class="fas fa-search"></i>
                            <span>Search & Retrieve - Find and retrieve specific documents</span>
                        </li>
                        <li>
                            <i class="fas fa-database"></i>
                            <span>Records Management - Manage archived record metadata</span>
                        </li>
                    </ul>
                </div>

                <!-- Permissions Section -->
                <div class="admin-section">
                    <h2>
                        <i class="fas fa-shield-alt"></i> Your Permissions
                    </h2>
                    <ul class="permissions-list">
                        <li>
                            <i class="fas fa-eye"></i>
                            <span>View Archives - Access and view all archived documents</span>
                        </li>
                        <li>
                            <i class="fas fa-clipboard-check"></i>
                            <span>Verify Records - Verify and authenticate records</span>
                        </li>
                        <li>
                            <i class="fas fa-map-pin"></i>
                            <span>Retrieve Documents - Locate and retrieve archived documents</span>
                        </li>
                        <li>
                            <i class="fas fa-edit"></i>
                            <span>Update Metadata - Update record metadata and descriptions</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // Update active nav item
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.admin-nav-item').forEach(item => {
                if (item.href === window.location.href) {
                    document.querySelectorAll('.admin-nav-item').forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
