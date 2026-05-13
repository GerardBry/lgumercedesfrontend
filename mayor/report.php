<?php
/**
 * Mayor Reports Page
 * Displays all offices and their total related documents
 */

session_start();

// STRICT ROLE-BASED ACCESS CONTROL
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] === 'Super Admin') {
    header('Location: ../admin/admin-dashboard.php');
    exit;
}

if ($_SESSION['role'] === 'Administrative Assistant') {
    header('Location: ../administrative/admin-dashboard-staff.php');
    exit;
}

if ($_SESSION['role'] !== 'Mayor') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/db_connect.php';

$first_name = $_SESSION['first_name'] ?? 'Mayor';
$last_name = $_SESSION['last_name'] ?? '';
$current_file = basename($_SERVER['PHP_SELF']);

$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$date_filter_active = $date_from !== '' && $date_to !== '';
$date_from_sql = $date_filter_active ? $conn->real_escape_string($date_from) : '';
$date_to_sql = $date_filter_active ? $conn->real_escape_string($date_to) : '';

$office_reports = [];
$total_documents_all = 0;
$total_offices = 0;
$active_offices = 0;
$top_office = 'N/A';
$top_office_documents = 0;
$lowest_office = 'N/A';
$lowest_office_documents = null;

if (isset($_GET['export']) && $_GET['export'] === 'csv' && !$date_filter_active) {
    http_response_code(400);
    echo 'Please select Date From and Date To before exporting.';
    exit;
}

// Count only the latest/updated document per tracking number to avoid duplicates.
$latest_documents_sql = "SELECT tracking_number, MAX(id) AS latest_id FROM documents WHERE document_type <> 'Travel Request' GROUP BY tracking_number";
$office_documents_sql = "SELECT
        d.id AS document_id,
        COALESCE(
            NULLIF(TRIM(d.office_department), ''),
            NULLIF(TRIM(u.office_department), ''),
            NULLIF(TRIM((SELECT da.office_department
                FROM document_assignments da
                WHERE da.document_id = d.id
                  AND da.office_department IS NOT NULL
                  AND TRIM(da.office_department) <> ''
                ORDER BY da.assigned_at DESC, da.id DESC
                LIMIT 1)), '')
        ) AS office_department
    FROM documents d
    INNER JOIN ($latest_documents_sql) latest ON latest.latest_id = d.id
    LEFT JOIN users u ON d.created_by = u.id
    WHERE d.document_type <> 'Travel Request'
      " . ($date_filter_active ? "AND DATE(COALESCE(d.date_received, d.date_sent, d.created_at)) BETWEEN '$date_from_sql' AND '$date_to_sql'" : "") . "
";

$reports_sql = "SELECT
        office_list.office_department,
        COALESCE(doc_totals.total_documents, 0) AS total_documents
    FROM (
        SELECT DISTINCT TRIM(office_department) AS office_department
        FROM (
            SELECT office_department FROM users WHERE office_department IS NOT NULL AND TRIM(office_department) <> ''
            UNION
            SELECT office_department FROM document_assignments WHERE office_department IS NOT NULL AND TRIM(office_department) <> ''
            UNION
            SELECT office_department FROM documents WHERE office_department IS NOT NULL AND TRIM(office_department) <> ''
        ) office_source
    ) office_list
    LEFT JOIN (
        SELECT office_department, COUNT(DISTINCT document_id) AS total_documents
        FROM (
            $office_documents_sql
        ) latest_docs
        WHERE office_department IS NOT NULL AND TRIM(office_department) <> ''
        GROUP BY office_department
    ) doc_totals ON doc_totals.office_department = office_list.office_department
    WHERE LOWER(TRIM(office_list.office_department)) <> 'administrative office'
    ORDER BY office_list.office_department ASC";

$reports_result = $conn->query($reports_sql);
if ($reports_result) {
    while ($row = $reports_result->fetch_assoc()) {
        $office_reports[] = $row;
        $doc_count = (int) $row['total_documents'];
        $total_documents_all += $doc_count;

        if ($doc_count > 0) {
            $active_offices++;
        }

        if ($doc_count > $top_office_documents) {
            $top_office_documents = $doc_count;
            $top_office = $row['office_department'];
        }

        if ($lowest_office_documents === null || $doc_count < $lowest_office_documents) {
            $lowest_office_documents = $doc_count;
            $lowest_office = $row['office_department'];
        }
    }
}

$total_offices = count($office_reports);
$lowest_office_documents = $lowest_office_documents ?? 0;

// CSV export: Office name + Total Documents only
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'mayor_office_report_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Office name', 'Total Documents']);

    foreach ($office_reports as $row) {
        fputcsv($output, [
            $row['office_department'],
            (int) $row['total_documents']
        ]);
    }

    fclose($output);
    $conn->close();
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Mayor</title>
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
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
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
        .nav-item.active { background: linear-gradient(135deg, var(--primary-color), #ffa500); color: #ffffff; }

        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .user-profile { display: flex; align-items: center; gap: 12px; padding: 12px; background-color: rgba(255, 255, 255, 0.05); border-radius: 8px; margin-bottom: 12px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), #ffa500); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; font-weight: 600; }
        .user-name { font-size: 13px; font-weight: 600; color: #ffffff; margin: 0; }
        .user-role { font-size: 12px; color: rgba(255, 255, 255, 0.6); margin: 2px 0 0 0; }
        .logout-btn { background-color: #e0e0e0; color: var(--text-dark); border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-size: 14px; }

        .main-content { flex: 1; margin-left: 280px; background: radial-gradient(circle at top right, #ffe8c4 0%, #f5f5f5 34%); min-height: 100vh; display: flex; flex-direction: column; }
        .page { display: block !important; width: 100%; padding: 36px; }

        .hero {
            border-radius: 18px;
            background: linear-gradient(120deg, #1f243a 0%, #2b3154 55%, #424a73 100%);
            padding: 24px;
            color: #fff;
            box-shadow: 0 14px 30px rgba(34, 35, 61, 0.22);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 149, 0, 0.42) 0%, rgba(255, 149, 0, 0) 70%);
        }

        .hero h2 { font-size: 30px; margin-bottom: 8px; font-weight: 800; letter-spacing: 0.2px; color: #ffffff !important; }
        .hero p { color: rgba(255, 255, 255, 0.92) !important; font-size: 14px; max-width: 640px; }

        .hero-chip {
            margin-top: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 7px 12px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 12px;
            font-weight: 700;
            color: #ffffff !important;
        }

        .hero-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .report-controls {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 16px;
            padding: 12px;
        }

        .range-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }

        .range-field label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92);
        }

        .range-field input {
            height: 40px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.94);
            padding: 0 12px;
            color: #1f243a;
            font-size: 13px;
            outline: none;
        }

        .range-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .range-help {
            margin-top: 8px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.78);
        }

        .btn-range {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 999px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 999px;
            padding: 9px 14px;
            background: #ffb347;
            color: #1f243a;
            font-size: 12px;
            font-weight: 800;
            transition: transform 0.2s ease;
        }

        .btn-export:hover {
            transform: translateY(-1px);
        }

        .btn-range:hover { transform: translateY(-1px); }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            padding: 16px;
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 12px;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }

        .summary-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            background: linear-gradient(135deg, #ff9500, #ffb347);
        }

        .summary-title { color: var(--text-light); font-size: 11px; text-transform: uppercase; letter-spacing: 0.7px; font-weight: 700; margin-bottom: 5px; }
        .summary-value { font-size: 28px; font-weight: 800; color: var(--text-dark); line-height: 1; }

        .insight-card {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            padding: 16px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .insight-card strong { color: #202020; }
        .insight-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #fff3cd;
            color: #7a5d00;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-wrap {
            background: #fff;
            border: 1px solid #ececec;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-title {
            padding: 14px 16px;
            border-bottom: 1px solid #ececec;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
        }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            background: #f7f7f7;
            text-align: left;
            padding: 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #ececec;
        }

        .data-table tbody td {
            padding: 12px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 14px;
        }

        .data-table tbody tr:hover { background: #fff9ef; }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .rank-badge {
            display: inline-flex;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            background: #2d3250;
            color: #fff;
        }

        .office-name { font-weight: 700; color: #222; }

        .count-badge {
            display: inline-block;
            min-width: 48px;
            text-align: center;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff3cd;
            color: #7a5d00;
            font-weight: 700;
            font-size: 12px;
        }

        .share-bar {
            height: 8px;
            border-radius: 999px;
            background: #f0f0f0;
            overflow: hidden;
            min-width: 120px;
        }

        .share-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff9500, #ffc063);
            border-radius: inherit;
        }

        .share-label {
            margin-left: 8px;
            font-size: 12px;
            color: #555;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            color: var(--text-light);
            padding: 56px 20px;
            background: var(--bg-white);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .empty-state i { font-size: 40px; margin-bottom: 12px; opacity: 0.5; }

        @media (max-width: 960px) {
            .sidebar { width: 240px; }
            .main-content { margin-left: 240px; }
            .page { padding: 24px; }
            .insight-card { flex-direction: column; align-items: flex-start; }
            .report-controls { width: 100%; }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .page { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-crown"></i></div>
            <h1>Mayor Panel</h1>
        </div>

        <nav class="nav-menu">
            <ul>
                <li>
                    <a href="admin-dashboard-mayor.php" class="nav-item <?php echo $current_file === 'admin-dashboard-mayor.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="accounts-view.php" class="nav-item <?php echo $current_file === 'accounts-view.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Accounts</span>
                    </a>
                </li>
                <li>
                    <a href="documents.php" class="nav-item <?php echo $current_file === 'documents.php' ? 'active' : ''; ?>">
                        <i class="fas fa-folder-open"></i>
                        <span>Documents</span>
                    </a>
                </li>
                <li>
                    <a href="report.php" class="nav-item <?php echo $current_file === 'report.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                    <p class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page">
            <div class="hero">
                <h2><i class="fas fa-chart-bar" style="color: #ffbc5f; margin-right: 10px;"></i>Office Reports</h2>
                <p>Comprehensive office-level document analytics with totals and distribution insights.</p>
                <span class="hero-chip"><i class="fas fa-bolt"></i> Live data from offices and assignments</span>
                <div class="hero-actions">
                    <form method="GET" action="report.php" class="report-controls">
                        <div class="range-field">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
                        </div>
                        <div class="range-field">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
                        </div>
                        <div class="range-actions">
                            <button type="submit" class="btn-export" name="export" value="csv">
                                <i class="fas fa-file-csv"></i>
                                Export CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="summary-title">Total Offices</div>
                        <div class="summary-value"><?php echo number_format($total_offices); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fas fa-folder-open"></i></div>
                    <div>
                        <div class="summary-title">Total Documents</div>
                        <div class="summary-value"><?php echo number_format($total_documents_all); ?></div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon"><i class="fas fa-signal"></i></div>
                    <div>
                        <div class="summary-title">Active Offices</div>
                        <div class="summary-value"><?php echo number_format($active_offices); ?></div>
                    </div>
                </div>
            </div>

            <div class="insight-card">
                <div>
                    <div class="summary-title" style="margin-bottom: 6px;">Office with Highest Document</div>
                    <strong><?php echo htmlspecialchars($top_office); ?></strong>
                </div>
                <span class="insight-pill"><i class="fas fa-trophy"></i> <?php echo number_format($top_office_documents); ?> documents</span>
            </div>

            <div class="insight-card">
                <div>
                    <div class="summary-title" style="margin-bottom: 6px;">Office with Lowest Document</div>
                    <strong><?php echo htmlspecialchars($lowest_office); ?></strong>
                </div>
                <span class="insight-pill" style="background:#eef2ff;color:#334155;"><i class="fas fa-arrow-down"></i> <?php echo number_format($lowest_office_documents); ?> documents</span>
            </div>

            <?php if (!empty($office_reports)): ?>
                <div class="table-wrap">
                    <div class="table-title">Office Document Distribution</div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Rank</th>
                                <th>Office/Department</th>
                                <th>Total Documents</th>
                                <th style="width: 220px;">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($office_reports as $index => $row): ?>
                                <?php
                                    $doc_count = (int) $row['total_documents'];
                                    $share_percent = $total_documents_all > 0 ? round(($doc_count / $total_documents_all) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><span class="rank-badge"><?php echo (int) $index + 1; ?></span></td>
                                    <td class="office-name"><?php echo htmlspecialchars($row['office_department']); ?></td>
                                    <td><span class="count-badge"><?php echo number_format($doc_count); ?></span></td>
                                    <td>
                                        <div style="display:flex; align-items:center;">
                                            <div class="share-bar"><div class="share-fill" style="width: <?php echo max(2, min(100, $share_percent)); ?>%;"></div></div>
                                            <span class="share-label"><?php echo number_format($share_percent, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No office report data found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
    (function () {
        const form = document.querySelector('.report-controls');
        if (!form) return;

        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');

        function submitWhenReady() {
            if (dateFrom.value && dateTo.value) {
                form.requestSubmit();
            }
        }

        dateFrom.addEventListener('change', submitWhenReady);
        dateTo.addEventListener('change', submitWhenReady);
    })();
</script>
</body>
</html>
