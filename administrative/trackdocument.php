<?php
/**
 * Track Documents Page - Administrative Staff
 * Search documents by tracking code
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// STRICT ROLE-BASED ACCESS CONTROL - Only Administrative Assistant allowed
if (isset($_SESSION['role'])) {
    // Block Super Admin
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: ../admin/admin-dashboard.php');
        exit;
    }
    // Block Regular users
    if ($_SESSION['role'] !== 'Administrative Assistant') {
        header('Location: ../login.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

function formatNotesForDisplay($rawNotes) {
    if ($rawNotes === null) {
        return '-';
    }

    $rawNotes = trim((string)$rawNotes);
    if ($rawNotes === '') {
        return '-';
    }

    $decoded = json_decode($rawNotes, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $parts = [];

        if (!empty($decoded['subject'])) {
            $parts[] = 'Subject: ' . $decoded['subject'];
        }
        if (!empty($decoded['purpose'])) {
            $parts[] = 'Purpose: ' . $decoded['purpose'];
        }
        if (!empty($decoded['title'])) {
            $parts[] = 'Title: ' . $decoded['title'];
        }
        if (!empty($decoded['type'])) {
            $parts[] = 'Type: ' . $decoded['type'];
        }
        if (!empty($decoded['orderNumber'])) {
            $parts[] = 'Order #: ' . $decoded['orderNumber'];
        }

        if (!empty($parts)) {
            return implode(' | ', $parts);
        }

        return 'Document details saved';
    }

    return $rawNotes;
}

function isValidTrackDate($value) {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return false;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false && $timestamp > 0;
}

function formatTrackDate($primary, $fallbacks = [], $format = 'M d, Y') {
    $candidates = array_merge([$primary], $fallbacks);

    foreach ($candidates as $candidate) {
        if (isValidTrackDate($candidate)) {
            return date($format, strtotime($candidate));
        }
    }

    return '-';
}

require_once '../config/db_connect.php';

$user_details = null;
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_details = $result->fetch_assoc();
    }
    $stmt->close();
}

$tracking_code = isset($_GET['tracking_code']) ? trim($_GET['tracking_code']) : '';
$search_performed = $tracking_code !== '';
$results = [];

// Query to fetch all documents assigned by or to the administrative user
// Shows the latest row per tracking code
$latestRowCondition = "NOT EXISTS (
    SELECT 1
    FROM documents d2
    WHERE d2.tracking_number = d.tracking_number
      AND (
          d2.date_sent > d.date_sent
          OR (d2.date_sent = d.date_sent AND d2.id > d.id)
      )
      AND EXISTS (
          SELECT 1
          FROM document_assignments da_check
          WHERE da_check.document_id = d2.id
            AND (da_check.assigned_by = ? OR da_check.assigned_to = ?)
      )
)";

$sql_track = "SELECT DISTINCT
        d.id as document_id,
        d.tracking_number,
        d.title,
        d.sender_name,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.description,
        d.notes,
        d.document_type,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM document_assignments da_returned
                JOIN documents d_returned ON d_returned.id = da_returned.document_id
                WHERE d_returned.tracking_number = d.tracking_number
                  AND da_returned.status = 'Returned'
            ) THEN 'Returned'
            WHEN EXISTS (
                SELECT 1
                FROM document_assignments da_done
                JOIN documents d_done ON d_done.id = da_done.document_id
                WHERE d_done.tracking_number = d.tracking_number
                  AND da_done.status = 'Completed'
            ) THEN 'Completed'
            ELSE d.status
        END as document_status,
        d.date_sent,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        sender.position as sender_position
    FROM documents d
    LEFT JOIN users sender ON d.sender_id = sender.id
    WHERE EXISTS (
        SELECT 1
        FROM document_assignments da
        WHERE da.document_id = d.id
          AND (da.assigned_by = ? OR da.assigned_to = ?)
    )
      AND COALESCE(d.document_type, '') <> 'Travel Request'
      AND " . $latestRowCondition;

if ($search_performed) {
    $sql_track .= " AND d.tracking_number = ?";
}

$sql_track .= " ORDER BY d.date_sent DESC, d.id DESC";

$stmt_track = $conn->prepare($sql_track);
if ($stmt_track) {
    if ($search_performed) {
        $stmt_track->bind_param("iiiis", $user_id, $user_id, $user_id, $user_id, $tracking_code);
    } else {
        $stmt_track->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    }
    $stmt_track->execute();
    $result_track = $stmt_track->get_result();
    while ($row = $result_track->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt_track->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="../css/notifications.css">
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

        .page-header {
            margin-bottom: 24px;
        }

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

        .search-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 260px;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.15);
        }

        .btn-track {
            padding: 12px 18px;
            border: none;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .table-container {
            background: var(--bg-white);
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
            vertical-align: top;
        }

        .data-table tbody tr:hover {
            background-color: var(--bg-light);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-success {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        /* Details Button & Timeline Modal */
        .btn-details {
            padding: 8px 14px;
            border: none;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        /* Timeline Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .timeline-modal {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header-timeline {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--bg-white);
            z-index: 10;
        }

        .modal-header-timeline h2 {
            margin: 0;
            font-size: 22px;
            color: var(--text-dark);
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s;
        }

        .modal-close-btn:hover {
            color: var(--text-dark);
        }

        .modal-body-timeline {
            padding: 32px;
        }

        .document-summary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: #ffffff;
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 32px;
        }

        .doc-summary-item {
            margin: 8px 0;
            font-size: 14px;
        }

        .doc-summary-label {
            font-weight: 600;
            opacity: 0.9;
        }

        .timeline-container {
            position: relative;
            padding: 0 0 0 40px;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-light));
        }

        .timeline-event {
            position: relative;
            margin-bottom: 28px;
            padding: 0;
        }

        .timeline-event::before {
            content: '';
            position: absolute;
            left: -40px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--bg-white);
            border: 3px solid var(--primary-color);
            z-index: 5;
        }

        .timeline-event.completed::before {
            background: #28a745;
            border-color: #28a745;
        }

        .timeline-event.received::before {
            background: #1976d2;
            border-color: #1976d2;
        }

        .timeline-event.returned::before {
            background: #d32f2f;
            border-color: #d32f2f;
        }

        .timeline-event.assigned::before {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .timeline-event.created::before {
            background: #6c757d;
            border-color: #6c757d;
        }

        .event-card {
            background: var(--bg-light);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 16px;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
        }

        .timeline-event.completed .event-card {
            background: rgba(40, 167, 69, 0.05);
        }

        .timeline-event.received .event-card {
            background: rgba(25, 118, 210, 0.05);
        }

        .timeline-event.returned .event-card {
            background: rgba(211, 47, 47, 0.05);
        }

        .event-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .event-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0 0 8px 0;
        }

        .event-description {
            font-size: 13px;
            color: var(--text-dark);
            margin: 0 0 12px 0;
            line-height: 1.5;
        }

        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            font-size: 12px;
            color: var(--text-light);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-dark);
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

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: 100%;
            }

            .timeline-modal {
                width: 95%;
                max-height: 90vh;
            }

            .modal-body-timeline {
                padding: 16px;
            }

            .event-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-theme">
    <div class="admin-container">
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
                        <a href="admin-dashboard-staff.php" class="admin-nav-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="trackdocument.php" class="admin-nav-item active">
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
                            <i class="fas fa-envelope-open"></i>
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
                    <div class="admin-avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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
                <div class="page-header">
                    <h2>Track Documents</h2>
                    <p>Search document status using tracking code only</p>
                </div>

                <div class="search-card">
                    <form class="search-form" method="GET" action="trackdocument.php">
                        <input
                            type="text"
                            name="tracking_code"
                            class="search-input"
                            placeholder="Enter tracking code (e.g., LGU-2026-04-23-913)"
                            value="<?php echo htmlspecialchars($tracking_code); ?>"
                            required
                        >
                        <button type="submit" class="btn-track">
                            <i class="fas fa-search"></i> Track
                        </button>
                    </form>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Subject/Title</th>
                                <th>Sender</th>
                                <th>Description</th>
                                <th>Date Received</th>
                                <th>Classification</th>
                                <th>Sub-Classification</th>
                                <th>Prioritization</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) === 0): ?>
                                <tr>
                                    <td colspan="10" class="empty-state"><?php echo $search_performed ? 'No record found for the tracking code you entered' : 'No documents assigned yet'; ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $row): ?>
                                    <?php
                                        $status = $row['document_status'] ?? 'Pending';
                                        $badge_class = 'badge-info';
                                        if ($status === 'Returned') {
                                            $badge_class = 'badge-danger';
                                        } elseif ($status === 'Received') {
                                            $badge_class = 'badge-success';
                                        } elseif ($status === 'Pending') {
                                            $badge_class = 'badge-warning';
                                        } elseif ($status === 'Checking Documents') {
                                            $badge_class = 'badge-warning';
                                        } elseif ($status === 'Waiting For Approval by Mayor') {
                                            $badge_class = 'badge-info';
                                        } elseif ($status === 'Completed') {
                                            $badge_class = 'badge-primary';
                                        } elseif ($status === 'Approved') {
                                            $badge_class = 'badge-success';
                                        }

                                        // Parse JSON notes for additional data
                                        $additional_data = json_decode($row['notes'], true) ?? [];
                                        $classification = $row['classification'] ?? $additional_data['classification'] ?? 'N/A';
                                        $sub_classification = $row['sub_classification'] ?? $additional_data['sub_classification'] ?? 'N/A';
                                        $priority = $row['priority'] ?? $additional_data['priority'] ?? 'N/A';
                                        
                                        // Determine classification badge color
                                        $classification_class = 'badge-info';
                                        if ($classification === 'Letter') {
                                            $classification_class = 'badge-classification-letter';
                                        } elseif ($classification === 'Invitation') {
                                            $classification_class = 'badge-classification-invitation';
                                        } elseif ($classification === 'Travel-Related Communication') {
                                            $classification_class = 'badge-classification-travel';
                                        } elseif ($classification === 'Indorsement') {
                                            $classification_class = 'badge-classification-indorsement';
                                        }
                                        
                                        // Determine priority badge color
                                        $priority_class = 'badge-secondary';
                                        if ($priority === 'Normal') {
                                            $priority_class = 'badge-primary';
                                        } elseif ($priority === 'Urgent') {
                                            $priority_class = 'badge-warning';
                                        } elseif ($priority === 'Critical') {
                                            $priority_class = 'badge-danger';
                                        }
                                        
                                        $sender_display = !empty($row['sender_name']) ? $row['sender_name'] : (trim(($row['sender_first_name'] ?? '') . ' ' . ($row['sender_last_name'] ?? '')) ?: '-');
                                        $display_date = formatTrackDate($row['date_received'] ?? null, [$row['date_sent'] ?? null]);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['tracking_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($sender_display); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($display_date); ?></td>
                                        <td><span class="badge <?php echo $classification_class; ?>"><?php echo htmlspecialchars($classification); ?></span></td>
                                        <td><?php echo htmlspecialchars($sub_classification); ?></td>
                                        <td><span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                        <td>
                                            <button class="btn-details" onclick="viewAuditTrail('<?php echo htmlspecialchars($row['tracking_number']); ?>')">
                                                <i class="fas fa-history"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Trail Timeline Modal -->
    <div class="modal-overlay" id="auditTrailModal">
        <div class="timeline-modal">
            <div class="modal-header-timeline">
                <h2><i class="fas fa-history" style="margin-right: 10px; color: var(--primary-color);"></i> Document Audit Trail</h2>
                <button class="modal-close-btn" onclick="closeAuditTrail()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-timeline">
                <div id="timelineContent" class="timeline-container">
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i>
                        <p>Loading audit trail...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>
    <script>
        // ==========================================
        // AUDIT TRAIL FUNCTIONS
        // ==========================================
        
        function viewAuditTrail(trackingCode) {
            const modal = document.getElementById('auditTrailModal');
            const timelineContent = document.getElementById('timelineContent');
            
            // Show modal with loading
            modal.classList.add('active');
            timelineContent.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-light);"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i><p>Loading audit trail...</p></div>';
            
            // Fetch audit trail data
            fetch('track-handler.php?tracking_code=' + encodeURIComponent(trackingCode))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        timelineContent.innerHTML = '<div style="padding: 40px; color: #d32f2f; text-align: center;"><i class="fas fa-exclamation-circle" style="font-size: 32px; margin-bottom: 16px;"></i><p>' + htmlEscape(data.message || 'Failed to load audit trail') + '</p></div>';
                        return;
                    }
                    
                    // Build HTML for timeline
                    let html = '';
                    
                    // Document Summary
                    html += '<div class="document-summary">';
                    html += '<div style="margin-bottom: 16px;">';
                    html += '<h3 style="margin: 0 0 12px 0; font-size: 18px;">' + htmlEscape(data.document.title) + '</h3>';
                    html += '</div>';
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; font-size: 14px;">';
                    html += '<div><strong style="opacity: 0.9;">Tracking Code:</strong> <strong>' + htmlEscape(data.document.tracking_number) + '</strong></div>';
                    html += '<div><strong style="opacity: 0.9;">Sender:</strong> ' + htmlEscape(data.document.sender_name || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Date Received:</strong> ' + formatDatetime(data.document.date_received, data.document.date_sent) + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Classification:</strong> ' + htmlEscape(data.document.classification || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Sub-Classification:</strong> ' + htmlEscape(data.document.sub_classification || '-') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Prioritization:</strong> ' + htmlEscape(data.document.priority || 'Normal') + '</div>';
                    html += '<div><strong style="opacity: 0.9;">Created by:</strong> ' + htmlEscape(data.document.created_by) + ' on ' + formatDatetime(data.document.created_at) + '</div>';
                    html += '</div>';
                    if (data.document.description) {
                        html += '<div style="margin-top: 14px; font-size: 14px;"><strong style="opacity: 0.9;">Description:</strong> ' + htmlEscape(data.document.description) + '</div>';
                    }
                    html += '</div>';
                    
                    // Timeline Events
                    html += '<div class="timeline-container">';
                    
                    if (data.timeline && data.timeline.length > 0) {
                        data.timeline.forEach((event, index) => {
                            const eventClass = event.event_type || 'default';
                            html += '<div class="timeline-event ' + htmlEscape(eventClass) + '">';
                            html += '<div class="event-card">';
                            html += '<h4 class="event-title"><i class="fas fa-' + getEventIcon(event.event_type) + '" style="margin-right: 8px;"></i>' + htmlEscape(event.title) + '</h4>';
                            html += '<p class="event-description">' + event.description + '</p>';
                            html += '<div class="event-meta">';
                            html += '<div class="meta-item"><span class="meta-label"><i class="fas fa-user" style="margin-right: 4px;"></i>By:</span> <strong>' + htmlEscape(event.who) + '</strong>';
                            if (event.who_position) {
                                html += ' (' + htmlEscape(event.who_position) + ')';
                            }
                            html += '</div>';
                            html += '<div class="meta-item"><span class="meta-label"><i class="fas fa-clock" style="margin-right: 4px;"></i>Time:</span> <strong>' + formatDatetime(event.timestamp) + '</strong></div>';
                            
                            if (event.recipient) {
                                html += '<div class="meta-item"><span class="meta-label"><i class="fas fa-arrow-right" style="margin-right: 4px;"></i>To:</span> <strong>' + htmlEscape(event.recipient) + '</strong>';
                                if (event.recipient_role) {
                                    html += ' (' + htmlEscape(event.recipient_role) + ')';
                                }
                                html += '</div>';
                            }
                            
                            if (event.status) {
                                const badgeColor = getBadgeColor(event.status);
                                html += '<div class="meta-item"><span class="meta-label"><i class="fas fa-tag" style="margin-right: 4px;"></i>Status:</span> <span style="background: ' + badgeColor + '; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px;">' + htmlEscape(event.status) + '</span></div>';
                            }
                            
                            if (event.notes) {
                                html += '<div class="meta-item" style="grid-column: 1 / -1;"><span class="meta-label"><i class="fas fa-sticky-note" style="margin-right: 4px;"></i>Notes:</span> ' + htmlEscape(event.notes) + '</div>';
                            }
                            
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                    } else {
                        html += '<div style="text-align: center; padding: 40px; color: var(--text-light);"><p>No events recorded yet</p></div>';
                    }
                    
                    html += '</div>';
                    
                    timelineContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    timelineContent.innerHTML = '<div style="padding: 40px; color: #d32f2f; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i><p>Failed to load audit trail</p></div>';
                });
        }
        
        function closeAuditTrail() {
            document.getElementById('auditTrailModal').classList.remove('active');
        }
        
        function getEventIcon(eventType) {
            const icons = {
                'created': 'file-alt',
                'assigned': 'share',
                'received': 'check-circle',
                'returned': 'undo',
                'checking_documents': 'search',
                'waiting_approval': 'user-check',
                'completed': 'check-double',
                'forwarded': 'arrow-right'
            };
            return icons[eventType] || 'circle';
        }
        
        function getBadgeColor(status) {
            const colors = {
                'Created': '#6c757d',
                'Pending': '#ffc107',
                'Received': '#1976d2',
                'Returned': '#d32f2f',
                'Checking Documents': '#f57c00',
                'Waiting For Approval by Mayor': '#5e35b1',
                'Completed': '#28a745',
                'Forwarded': '#ff9500'
            };
            return colors[status] || '#6c757d';
        }
        
        function formatDatetime(...dateStrings) {
            for (const dateString of dateStrings) {
                if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') {
                    continue;
                }

                const date = new Date(dateString);
                if (Number.isNaN(date.getTime())) {
                    continue;
                }

                return date.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            return '-';
        }
        
        function htmlEscape(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAuditTrail();
            }
        });
        
        // Close modal on overlay click
        document.getElementById('auditTrailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAuditTrail();
            }
        });
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
