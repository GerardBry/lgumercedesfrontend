<?php
/**
 * Staff Admin Dashboard - Department Dashboard (Matched to Super Admin Design)
 * Session-protected, role-based access for Administrative Assistant role
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
// Only Administrative Assistant role can access this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Explicitly block Super Admin from accessing this page
if ($_SESSION['role'] === 'Super Admin') {
    header('Location: ../admin/admin-dashboard.php');
    exit;
}

// Only allow Administrative Assistant role - STRICT CHECK
if ($_SESSION['role'] !== 'Administrative Assistant') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$admin_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'Admin';
$last_name = $_SESSION['last_name'] ?? '';

// Verify user role in database (anti-session tampering)
$role_check = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'Administrative Assistant' LIMIT 1");
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

// Get admin's department/office
$admin_details = [];
$sql = "SELECT office_department FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_details = $result->fetch_assoc();
    }
    $stmt->close();
}

$department = $admin_details['office_department'] ?? 'Department';

// Get dashboard statistics for the department
$stats = [
    'pending_documents' => 0,
    'approved_documents' => 0,
    'total_documents' => 0,
    'staff_count' => 0
];

// Count pending documents in department (using safe query)
$escaped_dept = $conn->real_escape_string($department);
$result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE office_department = '$escaped_dept' AND status = 'Pending'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['pending_documents'] = $row['count'];
}

// Count approved documents in department
$result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE office_department = '$escaped_dept' AND status = 'Approved'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['approved_documents'] = $row['count'];
}

// Count total documents in department
$result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE office_department = '$escaped_dept'");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_documents'] = $row['count'];
}

// Count staff in department
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE office_department = '$escaped_dept' AND role IN ('Staff', 'Employee')");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['staff_count'] = $row['count'];
}

// Recent documents
$recent_documents = [];
$result = $conn->query("SELECT id, title, status, created_at FROM documents WHERE office_department = '$escaped_dept' ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_documents[] = $row;
    }
}

// Department document statistics (department-scoped counts)
$dept_doc_stats = [
    'total_documents' => 0,
    'incoming' => 0,
    'outgoing' => 0,
    'received' => 0,
    'finished' => 0,
    'archive' => 0
];

// Total documents - match report.php exactly using final_status logic
$latest_docs_sql = "SELECT tracking_number, MAX(id) AS latest_id FROM documents WHERE office_department = '$escaped_dept' AND status <> 'Pending' GROUP BY tracking_number";
$final_status_expr = "CASE
    WHEN EXISTS (SELECT 1 FROM document_assignments da_completed WHERE da_completed.document_id = d.id AND da_completed.status = 'Completed') THEN 'Completed'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_approved WHERE da_approved.document_id = d.id AND da_approved.status = 'Approved') THEN 'Approved'
    ELSE d.status
END";
$status_summary_sql = "SELECT d.id, $final_status_expr AS final_status FROM documents d INNER JOIN ($latest_docs_sql) latest ON latest.latest_id = d.id";

$result = $conn->query("SELECT COUNT(*) as count FROM ($status_summary_sql) docs");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['total_documents'] = $row['count'];
}

// Incoming: assignments where this department is the target and status pending/forwarded
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status IN ('Pending','Forwarded')");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['incoming'] = $row['count'];
}

// Outgoing: assignments created by users from this department (exclude completed/archived/returned)
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments da JOIN users u ON da.assigned_by = u.id WHERE u.office_department = '$escaped_dept' AND da.status NOT IN ('Completed','Archived','Returned')");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['outgoing'] = $row['count'];
}

// Approved assignments for this department
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Approved'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['received'] = $row['count'];
}

// Finished / Completed assignments for this department
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Completed'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['finished'] = $row['count'];
}

// Archived assignments for this department
$result = $conn->query("SELECT COUNT(*) as count FROM document_assignments WHERE office_department = '$escaped_dept' AND status = 'Archived'");
if ($result) {
    $row = $result->fetch_assoc();
    $dept_doc_stats['archive'] = $row['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - LGU Mercedes</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Staff Dashboard - Matched to Admin Design (Orange Theme) */
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

        .admin-stat-icon.stat-pending {
            background: linear-gradient(135deg, #FFC107, #FFD54F);
        }

        .admin-stat-icon.stat-approved {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }

        .admin-stat-icon.stat-total {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }

        .admin-stat-icon.stat-staff {
            background: linear-gradient(135deg, #FF9500, #FFB347);
        }

        .admin-stat-icon.stat-incoming {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }

        .admin-stat-icon.stat-outgoing {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
        }

        .admin-stat-icon.stat-received {
            background: linear-gradient(135deg, #9C27B0, #7B1FA2);
        }

        .admin-stat-icon.stat-finished {
            background: linear-gradient(135deg, #00BCD4, #0097A7);
        }

        .admin-stat-icon.stat-archive {
            background: linear-gradient(135deg, #795548, #5D4037);
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

        /* Recent Documents Section - Matched Styling */
        .recent-section {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
        }

        .recent-section h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: var(--text-dark);
        }

        .documents-table {
            width: 100%;
            border-collapse: collapse;
        }

        .documents-table thead {
            background-color: var(--bg-light);
        }

        .documents-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .documents-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .documents-table tr:hover {
            background-color: var(--bg-light);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background-color: #fff3e0; color: #f57c00; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }

        .department-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
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
<body class="admin-theme">
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
                        <a href="admin-dashboard-staff.php" class="admin-nav-item active">
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
                            <i class="fas fa-check-circle"></i>
                            <span>Approved</span>
                        </a>
                    </li>
                    <li>
                        <a href="returned.php" class="admin-nav-item">
                            <i class="fas fa-undo"></i>
                            <span>Returned</span>
                        </a>
                    </li>
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
                <div class="admin-page-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h2>Administrator Dashboard</h2>
                    </div>
                    <p>Welcome <?php echo htmlspecialchars($first_name); ?>! Manage your department's documents and activities.</p>
                </div>

                <!-- Statistics -->
                <!-- Welcome Section -->
                <div class="admin-welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($first_name); ?>! 👋</h2>
                    <p>Manage your department&apos;s <?php echo htmlspecialchars($department); ?> documents and activities.</p>
                </div>

                <!-- Statistics - Department Document Cards -->
                <div class="admin-stats-grid">
                    <div class="admin-stat-card" data-stat="incoming">
                        <div class="admin-stat-icon stat-incoming">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Incoming</div>
                            <div class="admin-stat-value"><?php echo $dept_doc_stats['incoming']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card" data-stat="outgoing">
                        <div class="admin-stat-icon stat-outgoing">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Outgoing</div>
                            <div class="admin-stat-value"><?php echo $dept_doc_stats['outgoing']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card" data-stat="received">
                        <div class="admin-stat-icon stat-received">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Approved</div>
                            <div class="admin-stat-value"><?php echo $dept_doc_stats['received']; ?></div>
                        </div>
                    </div>

                    <div class="admin-stat-card" data-stat="finished">
                        <div class="admin-stat-icon stat-finished">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="admin-stat-content">
                            <div class="admin-stat-label">Finished</div>
                            <div class="admin-stat-value"><?php echo $dept_doc_stats['finished']; ?></div>
                        </div>
                    </div>

                    <!-- Archive card removed per request -->
                </div>
                <!-- Your Permissions -->
                <h3 class="admin-section-title"><i class="fas fa-lock-open" style="color: var(--primary-color);"></i> Your Permissions</h3>
                <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-md);">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Review and validate encoded documents</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Initiate routing workflows</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Assign documents to departments</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Monitor task progress and document status</span>
                        </li>
                        <li style="padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Manage scheduling (meetings, events)</span>
                        </li>
                        <li style="padding: 12px 0; display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-check" style="color: #28a745; font-weight: bold;"></i>
                            <span>Handle constituent requests routing</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // Update active nav item - Matched Admin JS
        document.querySelectorAll('.admin-nav-item').forEach(item => {
            if (item.href === window.location.href) {
                document.querySelectorAll('.admin-nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            }
        });
    </script>
    <script>
        // Fetch latest department stats and update the dashboard values
        (function(){
            fetch('get-dept-stats.php', { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    console.debug('get-dept-stats response', data);
                    const dbg = document.getElementById('dept-stats-debug');
                    if (dbg) dbg.textContent = 'Response: ' + JSON.stringify(data);
                    if (!data || data.error) {
                        if (dbg && data && data.error) dbg.textContent = 'Error: ' + data.error;
                        return;
                    }
                    const stats = data.stats || data;
                    const allZero = Object.keys(stats).every(k => Number(stats[k]) === 0);
                    if (allZero && data.diag) {
                        const byStatus = data.diag.global_assignments_by_status || {};
                        const fallback = {
                            total_documents: data.diag.global_documents || 0,
                            incoming: (Number(byStatus['Pending']) || 0) + (Number(byStatus['Forwarded']) || 0),
                            outgoing: Object.keys(byStatus).reduce((acc, s) => {
                                if (!['Completed','Archived','Returned'].includes(s)) return acc + (Number(byStatus[s]) || 0);
                                return acc;
                            }, 0),
                            received: Number(byStatus['Approved']) || 0,
                            finished: Number(byStatus['Completed']) || 0
                        };
                        // update UI with fallback values
                        Object.keys(fallback).forEach(key => {
                            const el = document.querySelector(`[data-stat="${key}"] .admin-stat-value`);
                            if (el) el.textContent = fallback[key];
                        });
                        if (dbg) dbg.textContent = dbg.textContent + ' — Using global fallback counts';
                    } else {
                        Object.keys(stats).forEach(key => {
                            const el = document.querySelector(`[data-stat="${key}"] .admin-stat-value`);
                            if (el) el.textContent = stats[key];
                        });
                    }
                })
                .catch(err => {
                    // silently ignore; server-side values remain as fallback
                    console.error('Failed to fetch dept stats', err);
                    const dbg = document.getElementById('dept-stats-debug');
                    if (dbg) dbg.textContent = 'Fetch error: ' + err;
                });
        })();
    </script>
    <style>
        #dept-stats-debug { margin: 12px 0 24px 0; padding: 12px; background:#fff; border:1px dashed #e0e0e0; color:#333; font-size:13px; }
    </style>
    <div id="dept-stats-debug">Debug: waiting for response...</div>
    <script src="../js/notifications.js"></script>
</body>
</html>
