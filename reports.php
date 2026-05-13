<?php
session_start();

// Access control: Regular staff only (blocks Super Admin and Administrative Assistant)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Block Super Admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') {
    header('Location: admin/admin-dashboard.php');
    exit;
}

// Block Administrative Assistant (they use /administrative/reports.php)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Administrative Assistant') {
    header('Location: administrative/reports.php');
    exit;
}

require_once __DIR__ . '/config/db_connect.php';

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';

// Resolve user's department
$user_details = [];
$sql = "SELECT office_department FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) $user_details = $res->fetch_assoc();
    $stmt->close();
}

$department = $user_details['office_department'] ?? '';
if ($department === '') $department = 'Department';
$escaped_dept = $conn->real_escape_string($department);

$selected_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_status_sql = $selected_status !== '' ? $conn->real_escape_string($selected_status) : '';
$status_filter_sql = '';
if ($selected_status !== '') {
    $safe_status = $conn->real_escape_string($selected_status);
    $status_filter_sql = " AND status = '$safe_status'";
}

$status_options = ['Returned', 'Approved', 'Completed', 'Received', 'Forwarded', 'Archived'];
$status_badges = [
    'Pending' => 'warning',
    'Returned' => 'danger',
    'Approved' => 'success',
    'Completed' => 'primary',
    'Received' => 'info',
    'Forwarded' => 'secondary',
    'Archived' => 'dark'
];

$department_document_count = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM documents WHERE office_department = '$escaped_dept'");
if ($r) {
    $department_document_count = (int)$r->fetch_assoc()['c'];
}

$document_scope_sql = "d.document_type <> 'Travel Request'";
$activity_scope_sql = "d.document_type <> 'Travel Request'";
$latest_documents_sql = "SELECT tracking_number, MAX(id) AS latest_id FROM documents WHERE document_type <> 'Travel Request' GROUP BY tracking_number";
$final_status_expression = "CASE
    WHEN EXISTS (SELECT 1 FROM document_assignments da_returned WHERE da_returned.document_id = d.id AND da_returned.status = 'Returned') THEN 'Returned'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_completed WHERE da_completed.document_id = d.id AND da_completed.status = 'Completed') THEN 'Completed'
    WHEN EXISTS (SELECT 1 FROM document_assignments da_approved WHERE da_approved.document_id = d.id AND da_approved.status = 'Approved') THEN 'Approved'
    ELSE d.status
END";

// Basic counts
$counts = [
    'total_documents' => 0,
    'pending' => 0,
    'approved' => 0,
    'finished' => 0
];

$count_status_sql = $selected_status_sql !== '' ? " WHERE final_status = '$selected_status_sql'" : '';

$status_summary_sql = "SELECT d.id, d.tracking_number, d.title, d.description, d.date_received, d.classification, d.sub_classification, d.priority, d.sender_name, d.created_by, d.created_at, d.file_path, u.office_department AS creator_office, COALESCE(NULLIF(u.office_department, ''), 'Unknown Office') AS created_by_label, $final_status_expression AS final_status FROM documents d INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d.id LEFT JOIN users u ON d.created_by = u.id WHERE $document_scope_sql";

// If the current user is Department Staff, restrict the report scope to only
// documents they created or where they were assigned (as assigner or assignee).
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Department Staff') {
    $uid = intval($user_id);
    $user_filter_sql = " AND (d.created_by = $uid OR EXISTS (SELECT 1 FROM document_assignments da_r WHERE da_r.document_id = d.id AND (da_r.assigned_by = $uid OR da_r.assigned_to = $uid)))";
    $status_summary_sql .= $user_filter_sql;
}

$r = $conn->query("SELECT COUNT(*) as c FROM ($status_summary_sql) docs" . $count_status_sql);
if ($r) { $counts['total_documents'] = (int)$r->fetch_assoc()['c']; }
$counts['pending'] = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM ($status_summary_sql) docs WHERE final_status = 'Approved'");
if ($r) { $counts['approved'] = (int)$r->fetch_assoc()['c']; }
$r = $conn->query("SELECT COUNT(*) as c FROM ($status_summary_sql) docs WHERE final_status = 'Completed'");
if ($r) { $counts['finished'] = (int)$r->fetch_assoc()['c']; }

// Status breakdown
$status_breakdown = [];
$r = $conn->query("SELECT docs.final_status, COUNT(*) as c FROM ($status_summary_sql) docs GROUP BY docs.final_status");
if ($r) {
    while ($row = $r->fetch_assoc()) $status_breakdown[$row['final_status']] = (int)$row['c'];
}

// Recent uploads
$recent_uploads = [];
$recent_uploads_sql = "SELECT * FROM ($status_summary_sql) recent_docs" . ($selected_status !== '' ? " WHERE final_status = '$selected_status_sql'" : '') . " ORDER BY created_at DESC LIMIT 10";
$r = $conn->query($recent_uploads_sql);
if ($r) {
    while ($row = $r->fetch_assoc()) $recent_uploads[] = $row;
}

// Recent activities (document_assignments)
$recent_activities = [];
$r = $conn->query("SELECT da.id, da.document_id, da.assigned_by, da.assigned_to, da.status, da.assigned_at, d.title, d.tracking_number, d.office_department, u_by.first_name AS assigned_by_name, u_by.last_name AS assigned_by_lname, u_to.first_name AS assigned_to_name, u_to.last_name AS assigned_to_lname FROM document_assignments da LEFT JOIN documents d ON da.document_id = d.id LEFT JOIN users u_by ON da.assigned_by = u_by.id LEFT JOIN users u_to ON da.assigned_to = u_to.id WHERE d.document_type <> 'Travel Request' AND da.status <> 'Pending' ORDER BY da.assigned_at DESC LIMIT 15");
if ($r) {
    while ($row = $r->fetch_assoc()) $recent_activities[] = $row;
}

// Monthly counts (last 12 months)
$monthly = [];
$r = $conn->query("SELECT DATE_FORMAT(d.created_at, '%Y-%m') as ym, COUNT(*) as c FROM documents d INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d.id GROUP BY ym ORDER BY ym DESC LIMIT 12");
if ($r) { while ($row = $r->fetch_assoc()) $monthly[$row['ym']] = (int)$row['c']; }

// Yearly counts
$yearly = [];
$r = $conn->query("SELECT YEAR(d.created_at) as yr, COUNT(*) as c FROM documents d INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d.id GROUP BY yr ORDER BY yr DESC");
if ($r) { while ($row = $r->fetch_assoc()) $yearly[$row['yr']] = (int)$row['c']; }

// CSV export for department documents (optional)
if (isset($_GET['export']) && $_GET['export'] === 'documents') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename_suffix = $selected_status !== '' ? '-' . preg_replace('/[^A-Za-z0-9_-]/','_', strtolower($selected_status)) : '';
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/','_', $escaped_dept) . '-documents' . $filename_suffix . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tracking Code','Subject/Title','By','Description','Date Received','Classification','Sub-Classification','Prioritization','Status']);
    $export_sql = "SELECT * FROM ($status_summary_sql) export_docs" . ($selected_status !== '' ? " WHERE final_status = '$selected_status_sql'" : '') . " ORDER BY date_received DESC, created_at DESC";
    $q = $conn->query($export_sql);
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $dateReceived = $row['date_received'] ? date('M d, Y', strtotime($row['date_received'])) : '';

            fputcsv($out, [
                $row['tracking_number'] ?? '',
                $row['title'] ?? '',
                $row['created_by_label'] ?? '',
                $row['description'] ?? '',
                $dateReceived,
                $row['classification'] ?? '',
                $row['sub_classification'] ?? '',
                $row['priority'] ?? '',
                $row['final_status'] ?? '',
            ]);
        }
    }
    fclose($out);
    $conn->close();
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reports - <?php echo htmlspecialchars($department); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
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
            background: linear-gradient(135deg, #ff9500, #ffb84d) !important;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .admin-logo-icon:hover {
            background: linear-gradient(135deg, #ff8c00, #ffa500) !important;
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.4);
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
            background-color: rgba(255, 149, 0, 0.15);
            color: #ffffff;
        }

        .admin-nav-item.active {
            background: linear-gradient(135deg, #ff9500, #ffb84d) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        .admin-nav-item.active span {
            color: #ffffff !important;
        }

        .admin-nav-item.active i {
            color: #ffffff !important;
        }

        .admin-nav-item.active:hover {
            background: linear-gradient(135deg, #ff8c00, #ffa500) !important;
            box-shadow: 0 6px 14px rgba(255, 149, 0, 0.4);
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
            background: linear-gradient(135deg, #ff9500, #ffb84d) !important;
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
            background: linear-gradient(135deg, #ff9500 0%, #ff7c00 100%);
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

        /* Report-specific styles (modern dashboard treatment) */
        .reports-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ff9500 0%, #ff7c00 55%, #ffb84d 100%);
            color: #fff;
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 24px 54px rgba(13, 110, 253, 0.22);
            margin-bottom: 22px;
        }

        .reports-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.22), transparent 28%);
            pointer-events: none;
        }

        .reports-hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(240px, 310px);
            gap: 18px;
            align-items: start;
        }

        .reports-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .reports-hero h2 {
            margin: 0 0 10px;
            color: #fff;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.05;
        }

        .reports-hero p {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 15px;
            line-height: 1.7;
            max-width: 760px;
        }

        .hero-metrics {
            display: grid;
            gap: 12px;
        }

        .hero-metric {
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 18px;
            padding: 16px 18px;
            backdrop-filter: blur(8px);
        }

        .hero-metric-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.78);
            margin-bottom: 4px;
            font-weight: 700;
        }

        .hero-metric-value {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }

        .reports-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            padding: 18px 20px;
            margin-bottom: 22px;
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 22px;
            box-shadow: 0 16px 34px rgba(15,23,42,0.08);
            backdrop-filter: blur(14px);
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .toolbar-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .toolbar-select {
            min-width: 190px;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
            color: var(--text-dark);
            font-size: 14px;
            outline: none;
        }

        .toolbar-select:focus {
            border-color: #ff9500;
            box-shadow: 0 0 0 4px rgba(255,149,0,0.12);
        }

        .toolbar-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(148,163,184,0.22);
            background: #fff;
            color: var(--text-dark);
            padding: 12px 16px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .toolbar-link.primary {
            background: linear-gradient(135deg, #ff9500, #ff7c00);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 12px 24px rgba(13,110,253,0.24);
        }

        .toolbar-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(15,23,42,0.12);
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .report-card {
            background: rgba(255,255,255,0.88);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15,23,42,0.08);
            backdrop-filter: blur(14px);
            overflow: hidden;
        }

        .report-card-inner {
            padding: 20px;
        }

        .report-card-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .report-card-title {
            margin: 0;
            font-size: 15px;
            color: var(--text-dark);
            font-weight: 800;
        }

        .report-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #ff9500, #ffb84d);
            box-shadow: 0 10px 20px rgba(255,149,0,0.22);
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .stat-subtext {
            font-size: 13px;
            color: var(--text-light);
            line-height: 1.6;
        }

        .metric-list {
            display: grid;
            gap: 10px;
            margin-top: 6px;
        }

        .metric-row {
            display: grid;
            gap: 6px;
        }

        .metric-row-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            font-size: 13px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .metric-bar {
            width: 100%;
            height: 10px;
            background: #edf2f7;
            border-radius: 999px;
            overflow: hidden;
        }

        .metric-bar > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(135deg, #ff9500, #ffb84d);
        }

        .sections-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
            gap: 18px;
            margin-top: 18px;
        }

        .section-card {
            background: rgba(255,255,255,0.88);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15,23,42,0.08);
            backdrop-filter: blur(14px);
            overflow: hidden;
        }

        .section-card-header {
            padding: 18px 20px 0;
        }

        .section-card-title {
            margin: 0;
            font-size: 17px;
            color: var(--text-dark);
            font-weight: 800;
        }

        .section-card-body {
            padding: 18px 20px 20px;
        }

        .table-shell {
            width: 100%;
            overflow-x: auto;
            border-radius: 18px;
            border: 1px solid rgba(148,163,184,0.16);
            background: rgba(255,255,255,0.72);
        }

        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-table thead th {
            text-align: left;
            padding: 14px 16px;
            background: rgba(255,149,0,0.07);
            color: var(--text-dark);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid rgba(148,163,184,0.18);
        }

        .recent-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148,163,184,0.14);
            font-size: 13px;
            color: var(--text-dark);
            vertical-align: top;
        }

        .recent-table tbody tr:hover {
            background: rgba(255,149,0,0.04);
        }

        .table-title-cell {
            font-weight: 700;
        }

        .doc-link {
            color: #ff9500;
            text-decoration: none;
            font-weight: 700;
        }

        .doc-link:hover {
            text-decoration: underline;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .status-pill.warning { background: rgba(255,193,7,0.14); color: #946b00; border-color: rgba(255,193,7,0.25); }
        .status-pill.success { background: rgba(25,135,84,0.14); color: #0b6b43; border-color: rgba(25,135,84,0.2); }
        .status-pill.primary { background: rgba(13,110,253,0.14); color: #0c4aa6; border-color: rgba(13,110,253,0.18); }
        .status-pill.info { background: rgba(13,202,240,0.14); color: #0c6b84; border-color: rgba(13,202,240,0.18); }
        .status-pill.secondary { background: rgba(108,117,125,0.14); color: #495057; border-color: rgba(108,117,125,0.18); }
        .status-pill.dark { background: rgba(33,37,41,0.12); color: #212529; border-color: rgba(33,37,41,0.18); }

        .activity-timeline {
            display: grid;
            gap: 14px;
        }

        .activity-item {
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 12px;
            align-items: flex-start;
            padding: 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(148,163,184,0.14);
        }

        .activity-dot {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff9500, #ffb84d);
            color: #fff;
            box-shadow: 0 10px 18px rgba(255,149,0,0.22);
        }

        .activity-title {
            margin: 0 0 3px;
            font-weight: 700;
            color: var(--text-dark);
            font-size: 14px;
        }

        .activity-meta {
            margin: 0;
            font-size: 12px;
            color: var(--text-light);
            line-height: 1.6;
        }

        .empty-state {
            padding: 18px;
            text-align: center;
            color: var(--text-light);
            font-size: 14px;
        }

        .soft-note {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255,149,0,0.08);
            border: 1px solid rgba(255,149,0,0.18);
            color: #cc6b00;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 1024px) {
            .admin-page { padding: 32px 24px; }
            .reports-hero-grid,
            .sections-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 240px; }
            .admin-main-content { margin-left: 240px; }
            .admin-page { padding: 24px 16px; }
            .reports-hero,
            .reports-toolbar,
            .section-card-body,
            .section-card-header {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        @media (max-width: 1024px) {
            .admin-page { padding: 32px 24px; }
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 240px; }
            .admin-main-content { margin-left: 240px; }
            .admin-page { padding: 24px 16px; }
        }
    </style>
</head>
<body class="admin-theme">
    <div class="admin-container">
        <!-- Sidebar (simple copy of admin sidebar from other pages) -->
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon"><i class="fas fa-building"></i></div>
                <h1>LGU Mercedes</h1>
            </div>
            <nav class="admin-nav-menu">
                <ul>
                    <li><a href="index.php" class="admin-nav-item"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
                    <li><a href="trackdocument.php" class="admin-nav-item"><i class="fas fa-search"></i> <span>Track Documents</span></a></li>
                    <li><a href="documententry.php" class="admin-nav-item"><i class="fas fa-file-upload"></i> <span>Documents</span></a></li>
                    <li class="divider"></li>
                    <li><a href="incoming.php" class="admin-nav-item"><i class="fas fa-inbox"></i> <span>Incoming</span></a></li>
                    <li><a href="outgoing.php" class="admin-nav-item"><i class="fas fa-paper-plane"></i> <span>Outgoing</span></a></li>
                    <li><a href="received.php" class="admin-nav-item"><i class="fas fa-envelope-open"></i> <span>Approved</span></a></li>
                    <li><a href="finished.php" class="admin-nav-item"><i class="fas fa-check-circle"></i> <span>Finished</span></a></li>
                                        <li>
                        <a href="archive.php" class="nav-item" data-page="archive">
                            <div>
                                <i class="fas fa-archive"></i>
                                <span>Archive</span>
                            </div>
                        </a>
                    </li>
                    <li><a href="reports.php" class="admin-nav-item active"><i class="fas fa-chart-pie"></i> <span>Reports</span></a></li>
                    <li class="divider"></li>
                    <li><a href="profile.php" class="admin-nav-item"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                </ul>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar"><?php echo strtoupper(substr($_SESSION['first_name'] ?? 'A',0,1) . substr($_SESSION['last_name'] ?? '',0,1)); ?></div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '')); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="admin-main-content">
            <!-- Header with Notifications -->
            <div style="padding: 20px 40px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: flex-end; align-items: center; position: relative; z-index: 10;">
                <div class="header-right" style="display: flex; gap: 16px; align-items: center; position: relative;">
                    <!-- Notification Bell will be inserted here by notifications.js -->
                </div>
            </div>
            <div class="admin-page">
                <div class="reports-hero">
                    <div class="reports-hero-grid">
                        <div>
                            <div class="reports-kicker"><i class="fas fa-chart-line"></i> Department Reports</div>
                            <h2>Reports for <?php echo htmlspecialchars($department); ?></h2>
                            <p>Track document flow, review activity trends, and export clean department records from a modern reporting workspace.</p>
                        </div>
                        <div class="hero-metrics">
                            <div class="hero-metric">
                                <span class="hero-metric-label">Documents in scope</span>
                                <div class="hero-metric-value"><?php echo (int)$counts['total_documents']; ?></div>
                            </div>
                            <div class="hero-metric">
                                <span class="hero-metric-label">Last updated</span>
                                <div class="hero-metric-value"><?php echo date('M d, Y h:i A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="reports-toolbar">
                    <div class="toolbar-group">
                        <span class="toolbar-label">Quick filter</span>
                        <select class="toolbar-select" id="statusFilter">
                            <option value="">All statuses</option>
                            <?php foreach ($status_options as $status_option): ?>
                                <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $selected_status === $status_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="reports.php<?php echo $selected_status !== '' ? '?status=' . urlencode($selected_status) : ''; ?>" class="toolbar-link primary"><i class="fas fa-sync-alt"></i> Refresh</a>
                    </div>
                    <div class="toolbar-group">
                        <a href="reports.php?export=documents<?php echo $selected_status !== '' ? '&status=' . urlencode($selected_status) : ''; ?>" class="toolbar-link"><i class="fas fa-download"></i> Export CSV</a>
                        <a href="#recent-uploads" class="toolbar-link"><i class="fas fa-table"></i> Go to tables</a>
                    </div>
                </div>

                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-card-inner">
                            <div class="report-card-header">
                                <div>
                                    <p class="report-card-title">Total Documents</p>
                                </div>
                                <div class="report-card-icon"><i class="fas fa-folder-open"></i></div>
                            </div>
                            <div class="stat-value"><?php echo $counts['total_documents']; ?></div>
                        </div>
                    </div>

                    <div class="report-card">
                        <div class="report-card-inner">
                            <div class="report-card-header">
                                <div><p class="report-card-title">Status Breakdown</p></div>
                                <div class="report-card-icon"><i class="fas fa-layer-group"></i></div>
                            </div>
                            <div class="metric-list">
                                <?php if (!empty($status_breakdown)): ?>
                                    <?php foreach ($status_breakdown as $s => $c): ?>
                                        <?php $percent = $counts['total_documents'] > 0 ? round(($c / $counts['total_documents']) * 100) : 0; ?>
                                        <div class="metric-row">
                                            <div class="metric-row-head">
                                                <span><?php echo htmlspecialchars($s); ?></span>
                                                <span><?php echo (int)$c; ?><?php echo $counts['total_documents'] > 0 ? ' • ' . $percent . '%' : ''; ?></span>
                                            </div>
                                            <div class="metric-bar"><span style="width: <?php echo $percent; ?>%;"></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">No status data available.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="report-card">
                        <div class="report-card-inner">
                            <div class="report-card-header">
                                <div><p class="report-card-title">Monthly Trend</p></div>
                                <div class="report-card-icon"><i class="fas fa-chart-bar"></i></div>
                            </div>
                            <div class="metric-list">
                                <?php $monthly_max = !empty($monthly) ? max($monthly) : 0; ?>
                                <?php if (!empty($monthly)): ?>
                                    <?php foreach ($monthly as $m => $c): ?>
                                        <?php $percent = $monthly_max > 0 ? round(($c / $monthly_max) * 100) : 0; ?>
                                        <div class="metric-row">
                                            <div class="metric-row-head">
                                                <span><?php echo htmlspecialchars($m); ?></span>
                                                <span><?php echo (int)$c; ?></span>
                                            </div>
                                            <div class="metric-bar"><span style="width: <?php echo $percent; ?>%;"></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">No monthly data available.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="report-card">
                        <div class="report-card-inner">
                            <div class="report-card-header">
                                <div><p class="report-card-title">Yearly Trend</p></div>
                                <div class="report-card-icon"><i class="fas fa-calendar-alt"></i></div>
                            </div>
                            <div class="metric-list">
                                <?php $yearly_max = !empty($yearly) ? max($yearly) : 0; ?>
                                <?php if (!empty($yearly)): ?>
                                    <?php foreach ($yearly as $y => $c): ?>
                                        <?php $percent = $yearly_max > 0 ? round(($c / $yearly_max) * 100) : 0; ?>
                                        <div class="metric-row">
                                            <div class="metric-row-head">
                                                <span><?php echo htmlspecialchars($y); ?></span>
                                                <span><?php echo (int)$c; ?></span>
                                            </div>
                                            <div class="metric-bar"><span style="width: <?php echo $percent; ?>%;"></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">No yearly data available.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sections-grid">
                    <section class="section-card" id="recent-uploads">
                        <div class="section-card-header">
                            <h3 class="section-card-title">Recent Uploads</h3>
                        </div>
                        <div class="section-card-body">
                            <div class="table-shell">
                                <table class="recent-table" id="uploadsTable">
                                    <thead>
                                        <tr><th>Title</th><th>Status</th><th>Created At</th><th>By</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($recent_uploads)): ?>
                                        <?php foreach ($recent_uploads as $doc): ?>
                                            <?php $docStatus = $doc['final_status'] ?? ''; ?>
                                            <tr data-status="<?php echo htmlspecialchars($docStatus); ?>">
                                                <td class="table-title-cell">
                                                    <?php if (!empty($doc['file_path'])): ?>
                                                        <a class="doc-link" href="view-document-file.php?id=<?php echo (int)$doc['id']; ?>"><?php echo htmlspecialchars($doc['title']); ?></a>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($doc['title']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="status-pill <?php echo $status_badges[$docStatus] ?? 'secondary'; ?>"><?php echo htmlspecialchars($docStatus); ?></span></td>
                                                <td><?php echo htmlspecialchars($doc['created_at']); ?></td>
                                                <td>
                                                    <?php
                                                        echo htmlspecialchars($doc['created_by_label'] ?? ('Unknown Office'));
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4"><div class="empty-state">No documents found for this filter.</div></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="section-card">
                        <div class="section-card-header">
                            <h3 class="section-card-title">Recent Activities</h3>
                        </div>
                        <div class="section-card-body">
                            <div class="activity-timeline">
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach ($recent_activities as $a): ?>
                                        <?php $activityStatus = $a['status'] ?? 'Activity'; ?>
                                        <div class="activity-item" data-status="<?php echo htmlspecialchars($activityStatus); ?>">
                                            <div class="activity-dot"><i class="fas fa-random"></i></div>
                                            <div>
                                                <p class="activity-title"><?php echo htmlspecialchars($a['title'] ?: ('Document #' . $a['document_id'])); ?></p>
                                                <p class="activity-meta">
                                                    Action: <?php echo htmlspecialchars($activityStatus); ?><br>
                                                    By: <?php echo htmlspecialchars(($a['assigned_by_name'] ?: '') . ' ' . ($a['assigned_by_lname'] ?: '')); ?> · To: <?php echo htmlspecialchars(($a['assigned_to_name'] ?: '') . ' ' . ($a['assigned_to_lname'] ?: '')); ?><br>
                                                    At: <?php echo htmlspecialchars($a['assigned_at']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">No recent activity yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="soft-note">
                    Use the filter above to narrow the view by status. Export follows the current filter, so the CSV stays aligned with what you are viewing.
                </div>

            </div>
        </div>
    </div>
    <script src="js/notifications.js"></script>
    <script>
        (function () {
            const filter = document.getElementById('statusFilter');
            if (!filter) return;

            filter.addEventListener('change', function () {
                const nextUrl = new URL(window.location.href);
                const value = this.value.trim();
                if (value) {
                    nextUrl.searchParams.set('status', value);
                } else {
                    nextUrl.searchParams.delete('status');
                }
                nextUrl.searchParams.delete('export');
                window.location.href = nextUrl.toString();
            });
        })();
    </script>
</body>
</html>
</body>
</html>
