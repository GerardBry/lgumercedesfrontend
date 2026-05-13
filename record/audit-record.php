<?php
/**
 * Audit Records Page
 * Record Officer - Document movement audit trail
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/db_connect.php';

if (!$conn) {
    die('Database connection failed!');
}

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'Record Officer') {
    header('Location: ../login.php');
    exit;
}

$first_name = $_SESSION['first_name'] ?? 'Record Officer';
$last_name = $_SESSION['last_name'] ?? '';

$filter_action = $_GET['filter_action'] ?? 'All';
$filter_document = trim($_GET['filter_document'] ?? '');
$filter_user = trim($_GET['filter_user'] ?? '');
$filter_start_date = $_GET['filter_start_date'] ?? '';
$filter_end_date = $_GET['filter_end_date'] ?? '';
$filter_office = trim($_GET['filter_office'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$uploadsTableExists = false;
$uploadsTableCheck = $conn->query("SHOW TABLES LIKE 'document_uploads'");
if ($uploadsTableCheck && $uploadsTableCheck->num_rows > 0) {
    $uploadsTableExists = true;
}

$hasReturnedAt = false;
$returnedAtCheck = $conn->query("SHOW COLUMNS FROM document_assignments LIKE 'returned_at'");
if ($returnedAtCheck && $returnedAtCheck->num_rows > 0) {
    $hasReturnedAt = true;
}

$returnedEventTimeSql = $hasReturnedAt
    ? "CASE
            WHEN da.status = 'Returned' THEN COALESCE(da.returned_at, (SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned'), da.completed_at, da.received_at, da.assigned_at, d.date_sent)
            ELSE COALESCE(da.completed_at, da.received_at, da.assigned_at, d.date_sent)
        END"
    : "CASE
            WHEN da.status = 'Returned' THEN COALESCE((SELECT MAX(n.created_at) FROM notifications n WHERE n.assignment_id = da.id AND n.new_status = 'Returned'), da.completed_at, da.received_at, da.assigned_at, d.date_sent)
            ELSE COALESCE(da.completed_at, da.received_at, da.assigned_at, d.date_sent)
        END";

$uploadsSelect = '';
if ($uploadsTableExists) {
    $uploadsSelect = "
        UNION ALL
        SELECT
            CONCAT('upload-', du.id) AS event_key,
            'Administrative Uploads' AS movement_label,
            'Uploaded' AS movement_status,
            d.tracking_number,
            d.title,
            d.document_type,
            d.description,
            COALESCE(NULLIF(TRIM(da.office_department), ''), NULLIF(TRIM(u_upload.office_department), ''), NULLIF(TRIM(d.office_department), ''), 'Unknown Office') AS office_department,
            du.uploaded_at AS event_time,
            COALESCE(NULLIF(TRIM(du.uploaded_by), ''), 'Administrative') AS actor_name,
            'Administrative Staff' AS actor_role,
            NULL AS counterpart_name,
            NULL AS counterpart_role,
            du.notes AS movement_notes
        FROM document_uploads du
        JOIN document_assignments da ON du.assignment_id = da.id
        JOIN documents d ON da.document_id = d.id
        LEFT JOIN users u_upload ON du.uploaded_by = CONCAT(u_upload.first_name, ' ', u_upload.last_name)
    ";
}

$base_sql = "
    SELECT
        CONCAT('submitted-', d.id) AS event_key,
        'Document Submitted' AS movement_label,
        'Submitted' AS movement_status,
        d.tracking_number,
        d.title,
        d.document_type,
        d.description,
        d.office_department,
        d.date_sent AS event_time,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(sender.first_name, ''), ' ', COALESCE(sender.last_name, ''))), ''), d.sender_name, 'Unknown') AS actor_name,
        COALESCE(sender.role, 'Sender') AS actor_role,
        NULL AS counterpart_name,
        NULL AS counterpart_role,
        d.notes AS movement_notes
    FROM documents d
    LEFT JOIN users sender ON d.created_by = sender.id
    WHERE d.date_sent IS NOT NULL

    UNION ALL

    SELECT
        CONCAT('assignment-', da.id) AS event_key,
        CASE
            WHEN da.status = 'Assigned' AND LOWER(COALESCE(u_assignee.role, '')) IN ('administrative assistant', 'super admin') THEN 'Document routed to Administrative Head'
            WHEN da.status = 'Assigned' THEN 'Routed to Department Staff'
            WHEN da.status = 'Received' THEN 'Administrative Received'
            WHEN da.status = 'Approved' THEN 'Approved by Administrative'
            WHEN da.status = 'Forwarded' THEN 'Document routed to Administrative Head'
            WHEN da.status = 'Completed' THEN 'Completed'
            WHEN da.status = 'Returned' THEN 'Returned'
            ELSE CONCAT('Document ', da.status)
        END AS movement_label,
        da.status AS movement_status,
        d.tracking_number,
        d.title,
        d.document_type,
        d.description,
        COALESCE(NULLIF(TRIM(da.office_department), ''), NULLIF(TRIM(u_assignee.office_department), ''), NULLIF(TRIM(u_assigner.office_department), ''), NULLIF(TRIM(d.office_department), ''), 'Unknown Office') AS office_department,
        {$returnedEventTimeSql} AS event_time,
        CASE
            WHEN da.status IN ('Received', 'Approved', 'Completed', 'Returned')
                THEN COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u_assignee.first_name, ''), ' ', COALESCE(u_assignee.last_name, ''))), ''), 'Administrative')
            ELSE COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u_assigner.first_name, ''), ' ', COALESCE(u_assigner.last_name, ''))), ''), 'System')
        END AS actor_name,
        CASE
            WHEN da.status IN ('Received', 'Approved', 'Completed', 'Returned') THEN COALESCE(u_assignee.role, 'Administrative Assistant')
            ELSE COALESCE(u_assigner.role, 'Record Officer')
        END AS actor_role,
        CASE
            WHEN da.status IN ('Received', 'Approved', 'Completed', 'Returned')
                THEN COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u_assigner.first_name, ''), ' ', COALESCE(u_assigner.last_name, ''))), ''), 'System')
            ELSE COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u_assignee.first_name, ''), ' ', COALESCE(u_assignee.last_name, ''))), ''), 'Administrative')
        END AS counterpart_name,
        CASE
            WHEN da.status IN ('Received', 'Approved', 'Completed', 'Returned') THEN COALESCE(u_assigner.role, 'Record Officer')
            ELSE COALESCE(u_assignee.role, 'Department Staff')
        END AS counterpart_role,
        da.notes AS movement_notes
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_assigner ON da.assigned_by = u_assigner.id
    LEFT JOIN users u_assignee ON da.assigned_to = u_assignee.id
    WHERE da.status IS NOT NULL

    UNION ALL

    SELECT
        CONCAT('travel-', d.id) AS event_key,
        'Travel Request Submitted' AS movement_label,
        'Travel Request' AS movement_status,
        d.tracking_number,
        d.title,
        d.document_type,
        d.description,
        COALESCE(NULLIF(TRIM(d.office_department), ''), NULLIF(TRIM(u.office_department), ''), 'Unknown Office') AS office_department,
        COALESCE(d.date_sent, d.created_at) AS event_time,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), d.sender_name, 'Department Staff') AS actor_name,
        COALESCE(u.role, 'Department Staff') AS actor_role,
        NULL AS counterpart_name,
        NULL AS counterpart_role,
        d.notes AS movement_notes
    FROM documents d
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.document_type = 'Travel Request'
    $uploadsSelect
";

$where_clauses = [];
$params = [];
$types = '';

if ($filter_action !== 'All') {
    $where_clauses[] = 'movement_label = ?';
    $params[] = $filter_action;
    $types .= 's';
}

if ($filter_document !== '') {
    $where_clauses[] = '(tracking_number LIKE ? OR title LIKE ? OR document_type LIKE ? OR description LIKE ?)';
    $searchValue = '%' . $filter_document . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= 'ssss';
}

if ($filter_user !== '') {
    $where_clauses[] = '(actor_name LIKE ? OR counterpart_name LIKE ?)';
    $searchValue = '%' . $filter_user . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= 'ss';
}

if ($filter_office !== '') {
    $where_clauses[] = 'office_department = ?';
    $params[] = $filter_office;
    $types .= 's';
}

if ($filter_start_date !== '') {
    $where_clauses[] = 'DATE(event_time) >= ?';
    $params[] = $filter_start_date;
    $types .= 's';
}

if ($filter_end_date !== '') {
    $where_clauses[] = 'DATE(event_time) <= ?';
    $params[] = $filter_end_date;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

$count_sql = "SELECT COUNT(*) AS count FROM ($base_sql) audit_events $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
    die('Database error: ' . $conn->error);
}
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = intval($count_row['count'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));
$count_stmt->close();

$sql = "SELECT * FROM ($base_sql) audit_events $where_sql ORDER BY event_time DESC, event_key DESC LIMIT ? OFFSET ?";
$audit_logs = [];
$paramsWithPaging = $params;
$typesWithPaging = $types . 'ii';
$paramsWithPaging[] = $limit;
$paramsWithPaging[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Database error: ' . $conn->error);
}
if (!empty($paramsWithPaging)) {
    $stmt->bind_param($typesWithPaging, ...$paramsWithPaging);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $audit_logs[] = $row;
}
$stmt->close();

$offices = [];
$office_result = $conn->query("SELECT DISTINCT office_department FROM document_assignments WHERE office_department IS NOT NULL AND office_department != '' ORDER BY office_department");
if ($office_result) {
    while ($row = $office_result->fetch_assoc()) {
        $offices[] = $row['office_department'];
    }
}

function getMovementBadge($label) {
    $badges = [
        'Routed to Department Staff' => ['icon' => 'fa-file-export', 'color' => '#6f42c1', 'bg' => '#f0f0ff'],
        'Document routed to Administrative Head' => ['icon' => 'fa-share', 'color' => '#fd7e14', 'bg' => '#ffe5cc'],
        'Document Submitted' => ['icon' => 'fa-paper-plane', 'color' => '#0d7377', 'bg' => '#d1f4f8'],
        'Administrative Received' => ['icon' => 'fa-inbox', 'color' => '#1976d2', 'bg' => '#dbeafe'],
        'Approved by Administrative' => ['icon' => 'fa-check-circle', 'color' => '#28a745', 'bg' => '#d4edda'],
        'Travel Request Submitted' => ['icon' => 'fa-plane', 'color' => '#8e44ad', 'bg' => '#f3e8ff'],
        'Administrative Uploads' => ['icon' => 'fa-file-upload', 'color' => '#198754', 'bg' => '#d1e7dd'],
        'Completed' => ['icon' => 'fa-flag-checkered', 'color' => '#0d7377', 'bg' => '#d1f4f8'],
        'Returned' => ['icon' => 'fa-undo', 'color' => '#ffc107', 'bg' => '#fff3cd'],
        'Document Forwarded' => ['icon' => 'fa-share', 'color' => '#fd7e14', 'bg' => '#ffe5cc'],
    ];

    return $badges[$label] ?? ['icon' => 'fa-circle-info', 'color' => '#6c757d', 'bg' => '#f8f9fa'];
}

function formatMovementLabel($label) {
    return $label;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Record Officer</title>
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
            --radius-md: 8px;
            --radius-lg: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-light); color: var(--text-dark); }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background-color: var(--sidebar-bg); color: #ffffff; position: fixed; left: 0; top: 0; height: 100vh; display: flex; flex-direction: column; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15); z-index: 1000; overflow-y: auto; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary-color), #ffa500); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; }
        .sidebar-header h1 { font-size: 18px; font-weight: 700; color: #ffffff; margin: 0; }
        .nav-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .nav-menu ul { list-style: none; }
        .nav-menu li { margin: 4px 0; padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: rgba(255, 255, 255, 0.7); text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-size: 14px; font-weight: 500; }
        .nav-item i { width: 20px; text-align: center; }
        .nav-item:hover, .nav-item.active { background: linear-gradient(135deg, var(--primary-color), #ffa500); color: #ffffff; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .user-profile { display: flex; align-items: center; gap: 12px; padding: 12px; background-color: rgba(255, 255, 255, 0.05); border-radius: 8px; margin-bottom: 12px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), #ffa500); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; font-weight: 600; }
        .user-info { flex: 1; }
        .user-name { font-size: 13px; font-weight: 600; color: #ffffff; margin: 0; }
        .user-role { font-size: 12px; color: rgba(255, 255, 255, 0.6); margin: 2px 0 0 0; }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; background-color: #e0e0e0; color: var(--text-dark); text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-size: 14px; font-weight: 600; }
        .logout-btn:hover { background-color: #d0d0d0; }
        .main-content { flex: 1; margin-left: 280px; background-color: var(--bg-light); min-height: 100vh; overflow-y: auto; }
        .audit-page { padding: 40px; }
        .page-header { margin-bottom: 24px; }
        .page-header h2 { display: flex; align-items: center; font-size: 28px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .page-header p { font-size: 14px; color: var(--text-light); }
        .filter-section { background: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-md); margin-bottom: 32px; }
        .filter-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-dark); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 12px; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group input, .filter-group select { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 14px; background: var(--bg-white); color: var(--text-dark); }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-md); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background-color: var(--primary-color); color: #ffffff; }
        .btn-primary:hover { background-color: #e68900; }
        .btn-secondary { background-color: #e0e0e0; color: var(--text-dark); }
        .btn-secondary:hover { background-color: #d0d0d0; }
        .table-container { background: var(--bg-white); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 32px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead { background-color: var(--bg-light); border-bottom: 2px solid var(--border-color); }
        .data-table th { padding: 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-dark); vertical-align: top; }
        .data-table tbody tr:hover { background-color: var(--bg-light); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .action-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: var(--radius-md); font-size: 12px; font-weight: 600; }
        .user-info { display: flex; flex-direction: column; gap: 4px; }
        .user-name { font-weight: 600; }
        .user-role { font-size: 12px; color: var(--text-light); }
        .timestamp { font-size: 13px; color: var(--text-light); white-space: nowrap; }
        .office-badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; background: #f3f3f3; color: var(--text-dark); font-size: 12px; font-weight: 600; }
        .details-text { font-size: 13px; color: var(--text-dark); line-height: 1.5; max-width: 320px; white-space: pre-wrap; word-break: break-word; }
        .empty-state { text-align: center; padding: 60px 40px; color: var(--text-light); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 32px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); text-decoration: none; color: var(--text-dark); font-size: 14px; transition: all 0.3s ease; }
        .pagination a:hover { background-color: var(--primary-color); color: #ffffff; border-color: var(--primary-color); }
        .pagination .active { background-color: var(--primary-color); color: #ffffff; border-color: var(--primary-color); }
        .record-count { font-size: 13px; color: var(--text-light); margin-top: 16px; }
        @media (max-width: 1024px) { .sidebar { width: 240px; } .main-content { margin-left: 240px; } }
        @media (max-width: 768px) { .container { flex-direction: column; } .sidebar { position: relative; width: 100%; height: auto; } .main-content { margin-left: 0; } .data-table { min-width: 980px; } .table-container { overflow-x: auto; } }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><i class="fas fa-file-archive"></i></div>
                <h1>Record Officer</h1>
            </div>
            <div class="nav-menu">
                <ul>
                    <li><a href="admin-dashboard-officer.php" class="nav-item" title="Dashboard"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
                    <li style="margin: 12px 0; padding: 0; height: 1px; background: rgba(255,255,255,0.1);"></li>
                    <li><a href="manage-document.php" class="nav-item" title="Manage Documents"><i class="fas fa-folder-open"></i><span>Manage Documents</span></a></li>
                    <li><a href="audit-record.php" class="nav-item active" title="Audit Logs"><i class="fas fa-history"></i><span>Audit Logs</span></a></li>
                </ul>
            </div>
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

        <main class="main-content">
            <div class="audit-page">
                <div class="page-header">
                    <h2><i class="fas fa-history" style="color: var(--primary-color); margin-right: 12px;"></i>Audit Logs</h2>
                    <p>Document movement history only</p>
                </div>

                <div class="filter-section">
                    <div class="filter-title"><i class="fas fa-filter" style="color: var(--primary-color); margin-right: 8px;"></i>Filter Results</div>
                    <form method="GET" action="audit-record.php" id="auditFilterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter_action">Movement Type</label>
                                <select id="filter_action" name="filter_action">
                                    <option value="All">Show All</option>
                                    <?php foreach (['Completed','Administrative Uploads','Travel Request Submitted','Document Submitted'] as $movement): ?>
                                        <option value="<?php echo htmlspecialchars($movement); ?>" <?php echo $filter_action === $movement ? 'selected' : ''; ?>><?php echo htmlspecialchars($movement); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="filter_document">Document / Tracking</label>
                                <input type="text" id="filter_document" name="filter_document" placeholder="Search by tracking code, title, type..." value="<?php echo htmlspecialchars($filter_document); ?>" autocomplete="off">
                            </div>
                            <div class="filter-group">
                                <label for="filter_user">User Name</label>
                                <input type="text" id="filter_user" name="filter_user" placeholder="Search by actor or counterpart..." value="<?php echo htmlspecialchars($filter_user); ?>" autocomplete="off">
                            </div>
                            <div class="filter-group">
                                <label for="filter_office">Office</label>
                                <select id="filter_office" name="filter_office">
                                    <option value="">All Offices</option>
                                    <?php foreach ($offices as $office): ?>
                                        <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $filter_office === $office ? 'selected' : ''; ?>><?php echo htmlspecialchars($office); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="filter_start_date">Start Date</label>
                                <input type="date" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="filter_end_date">End Date</label>
                                <input type="date" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                            </div>
                            <div class="filter-group" style="justify-content: flex-end; gap: 12px; padding-top: 26px; flex-direction: row;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                <a href="audit-record.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Clear</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Movement</th>
                                <th>Document</th>
                                <th>Actor</th>
                                <th>Timestamp</th>
                                <th>Office</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($audit_logs) > 0): ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <?php $badge = getMovementBadge($log['movement_label']); ?>
                                    <tr>
                                        <td>
                                            <div class="action-badge" style="background-color: <?php echo $badge['bg']; ?>;">
                                                <i class="fas <?php echo $badge['icon']; ?>" style="color: <?php echo $badge['color']; ?>;"></i>
                                                <span style="color: <?php echo $badge['color']; ?>;"><?php echo htmlspecialchars(formatMovementLabel($log['movement_label'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div><strong><?php echo htmlspecialchars($log['tracking_number'] ?? '-'); ?></strong></div>
                                                <div style="color: var(--text-light); font-size: 12px;"><?php echo htmlspecialchars($log['title'] ?? '-'); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name"><?php echo htmlspecialchars(trim((string)($log['actor_name'] ?? '')) !== '' ? trim((string)$log['actor_name']) : 'System'); ?></div>
                                                <div class="user-role"><?php echo htmlspecialchars(trim((string)($log['actor_role'] ?? '')) !== '' ? trim((string)$log['actor_role']) : 'N/A'); ?><?php echo !empty($log['counterpart_name']) ? ' • ' . htmlspecialchars($log['counterpart_name']) : ''; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="timestamp">
                                                <?php echo !empty($log['event_time']) ? htmlspecialchars(date('M d, Y h:i:s A', strtotime($log['event_time']))) : '-'; ?>
                                                <br>
                                                <small style="color: #999;"><?php echo !empty($log['event_time']) ? htmlspecialchars(date('l', strtotime($log['event_time']))) : ''; ?></small>
                                            </div>
                                        </td>
                                        <td><span class="office-badge"><?php echo htmlspecialchars($log['office_department'] ?: 'Unknown Office'); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><div class="empty-state"><i class="fas fa-inbox"></i><p>No document movement logs found</p></div></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="audit-record.php?page=1&filter_action=<?php echo urlencode($filter_action); ?>&filter_document=<?php echo urlencode($filter_document); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_office=<?php echo urlencode($filter_office); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">« First</a>
                            <a href="audit-record.php?page=<?php echo $page - 1; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_document=<?php echo urlencode($filter_document); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_office=<?php echo urlencode($filter_office); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">‹ Previous</a>
                        <?php endif; ?>

                        <?php $start = max(1, $page - 2); $end = min($total_pages, $page + 2); for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="audit-record.php?page=<?php echo $i; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_document=<?php echo urlencode($filter_document); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_office=<?php echo urlencode($filter_office); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="audit-record.php?page=<?php echo $page + 1; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_document=<?php echo urlencode($filter_document); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_office=<?php echo urlencode($filter_office); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">Next ›</a>
                            <a href="audit-record.php?page=<?php echo $total_pages; ?>&filter_action=<?php echo urlencode($filter_action); ?>&filter_document=<?php echo urlencode($filter_document); ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_office=<?php echo urlencode($filter_office); ?>&filter_start_date=<?php echo urlencode($filter_start_date); ?>&filter_end_date=<?php echo urlencode($filter_end_date); ?>">Last »</a>
                        <?php endif; ?>
                    </div>
                    <div class="record-count">Showing <?php echo $total_records > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records</div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const form = document.getElementById('auditFilterForm');
            if (!form) return;

            const textInputs = [
                document.getElementById('filter_document'),
                document.getElementById('filter_user')
            ].filter(Boolean);

            const instantFields = [
                document.getElementById('filter_action'),
                document.getElementById('filter_office'),
                document.getElementById('filter_start_date'),
                document.getElementById('filter_end_date')
            ].filter(Boolean);

            let typingTimer = null;

            function submitForm() {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }

            textInputs.forEach((input) => {
                input.addEventListener('input', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(submitForm, 350);
                });
            });

            instantFields.forEach((field) => {
                field.addEventListener('change', submitForm);
            });

            form.addEventListener('submit', function () {
                clearTimeout(typingTimer);
            });
        })();
    </script>
</body>
</html>
