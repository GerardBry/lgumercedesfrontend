<?php
/**
 * Incoming Documents Page - Administrative Staff
 * View pending documents assigned to this administrative staff
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: ../admin/admin-dashboard.php');
        exit;
    }
    if ($_SESSION['role'] !== 'Administrative Assistant') {
        header('Location: ../login.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

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

$incoming_documents = [];
$sql = "SELECT
        da.id as assignment_id,
        d.id as document_id,
        d.tracking_number,
        d.title,
        d.description,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.received_at,
        sender.first_name as sender_first_name,
        sender.last_name as sender_last_name,
        assigner.first_name as assigner_first_name,
        assigner.last_name as assigner_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users sender ON d.sender_id = sender.id
    LEFT JOIN users assigner ON da.assigned_by = assigner.id
    WHERE da.assigned_to = ?
      AND da.status IN ('Forwarded')
    ORDER BY da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $incoming_documents[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
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

        .logout-btn:hover { background-color: #d0d0d0; }

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
        }

        .data-table tbody tr:hover { background-color: var(--bg-light); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .btn-info:hover { background-color: #bbdefb; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s ease;
        }

        .modal.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-content {
            background-color: var(--bg-white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-light);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover { color: var(--text-dark); }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: var(--text-dark);
        }

        .btn-secondary:hover { background-color: #d0d0d0; }

        .btn-success {
            background-color: #4caf50;
            color: #fff;
        }

        .btn-success:hover {
            background-color: #43a047;
        }

        .modal-content.modal-large {
            max-width: 1100px;
        }
    </style>
</head>
<body>
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
                        <a href="trackdocument.php" class="admin-nav-item">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="documententry.php" class="admin-nav-item">
                            <i class="fas fa-file-upload"></i>
                            <span>Document Entry</span>
                        </a>
                    </li>
                    <li>
                        <a href="assign-document.php" class="admin-nav-item">
                            <i class="fas fa-file-export"></i>
                            <span>Assign Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="incoming.php" class="admin-nav-item active">
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
                            <span>Received</span>
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
                        <a href="archive.php" class="admin-nav-item">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="#" class="admin-nav-item" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-users"></i>
                            <span>Staff (Coming Soon)</span>
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
            <div class="admin-page">
                <div class="page-header">
                    <h2>Incoming Documents</h2>
                    <p>Pending documents assigned to you</p>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Document Title</th>
                                <th>Sender</th>
                                <th>Document Type</th>
                                <th>Date Sent</th>
                                <th>Description</th>
                                <th>Notes/Instructions</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($incoming_documents) > 0): ?>
                                <?php foreach ($incoming_documents as $doc): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? '')) ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['document_type'] ?? '-'); ?></td>
                                        <td><?php echo $doc['date_sent'] ? date('M d, Y h:i A', strtotime($doc['date_sent'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['assignment_notes'] ?? '-'); ?></td>
                                        <td><span class="badge badge-warning"><?php echo htmlspecialchars($doc['assignment_status'] ?? 'Pending'); ?></span></td>
                                        <td>
                                            <button class="btn-sm btn-info" onclick="viewIncomingDocument(<?php echo (int)$doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty-state">No incoming documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="viewIncomingModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeIncomingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Tracking Code</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewTrackingCode">-</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Document Type</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewDocumentType">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Document Title</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                <span id="viewTitle">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                                <span id="viewDescription">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Assigned By</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                <span id="viewAssignedBy">-</span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Assigned At</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewAssignedAt">-</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                                    <span id="viewStatus">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes / Instructions</label>
                            <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                                <span id="viewNotes">-</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Form Details</label>
                            <div id="viewFormDetails" style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 120px;"></div>
                        </div>
                    </div>

                    <div style="border-left: 1px solid #ddd; padding-left: 20px;">
                        <h4 style="margin: 0 0 10px 0; color: var(--text-dark);">Digitalized Paper Format</h4>
                        <div id="viewDigitalPaperPreview"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="modalReceiveBtn" onclick="markCurrentAsReceived()">
                    <i class="fas fa-check"></i> Received
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeIncomingModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let selectedIncomingAssignmentId = null;

        function safeParseJson(text) {
            try {
                return JSON.parse(text || '{}');
            } catch (e) {
                return {};
            }
        }

        function escapeText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatArray(value) {
            if (!Array.isArray(value) || value.length === 0) {
                return '-';
            }
            return value.join(', ');
        }

        function renderIncomingFormDetails(assignment, content) {
            if (assignment.document_type !== 'Travel Request') {
                return '<p style="margin:0; color: var(--text-light);">No structured form fields available for this document type.</p>';
            }

            return `
                <div style="display:grid; gap:8px; font-size:14px;">
                    <div><strong>Travelers:</strong> ${escapeText(formatArray(content.travelers))}</div>
                    <div><strong>From:</strong> ${escapeText(content.from || 'Municipal Mayor')}</div>
                    <div><strong>Subject:</strong> ${escapeText(content.subject || '-')}</div>
                    <div><strong>Purpose:</strong> ${escapeText(content.purpose || '-')}</div>
                    <div><strong>Destination:</strong> ${escapeText(content.destination || '-')}</div>
                    <div><strong>Start Date:</strong> ${escapeText(content.startDate || '-')}</div>
                    <div><strong>End Date:</strong> ${escapeText(content.endDate || '-')}</div>
                    <div><strong>Duration:</strong> ${escapeText(content.duration || '-')}</div>
                    <div><strong>Mode:</strong> ${escapeText(content.mode || '-')}</div>
                </div>
            `;
        }

        function generateIncomingPreview(assignment, content) {
            if (assignment.document_type === 'Travel Request') {
                return `
                    <div class="document-preview" style="background:#fff; border:1px solid #ddd; border-radius:10px; padding:16px;">
                        <div style="text-align:center; margin-bottom:14px;">
                            <div style="font-size:12px;">Province of Camarines Norte</div>
                            <div style="font-size:14px; font-weight:700;">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                            <div style="font-size:16px; font-weight:700; margin-top:8px;">TRAVEL REQUEST</div>
                            <div style="font-size:12px; margin-top:4px;">No. ${escapeText(assignment.tracking_number || '-')}</div>
                        </div>
                        <div style="display:grid; gap:8px; font-size:13px;">
                            <div><strong>TO:</strong> ${escapeText(formatArray(content.travelers))}</div>
                            <div><strong>FROM:</strong> ${escapeText(content.from || 'Municipal Mayor')}</div>
                            <div><strong>DATE:</strong> ${escapeText(content.dateIssued || '-')}</div>
                            <div><strong>SUBJECT:</strong> ${escapeText(content.subject || 'As Stated')}</div>
                            <div>You are hereby directed to attend ${escapeText(content.purpose || '-')}.</div>
                            <div><strong>Destination:</strong> ${escapeText(content.destination || '-')}</div>
                            <div><strong>Travel Dates:</strong> ${escapeText(content.startDate || '-')} to ${escapeText(content.endDate || '-')}</div>
                            <div><strong>Duration:</strong> ${escapeText(content.duration || '-')} day(s)</div>
                            <div><strong>Mode of Transportation:</strong> ${escapeText(content.mode || '-')}</div>
                        </div>
                    </div>
                `;
            }

            return `
                <div style="background:#fff; border:1px solid #ddd; border-radius:10px; padding:16px;">
                    <h3 style="margin:0 0 8px 0;">${escapeText(assignment.title || '-')}</h3>
                    <p style="margin:0 0 8px 0;">${escapeText(assignment.description || '-')}</p>
                    <p style="margin:0; color: var(--text-light);">Document Type: ${escapeText(assignment.document_type || '-')}</p>
                </div>
            `;
        }

        function viewIncomingDocument(assignmentId) {
            fetch('incoming-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        const content = safeParseJson(assignment.doc_notes);
                        selectedIncomingAssignmentId = assignmentId;

                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewAssignedBy').textContent = ((assignment.assigner_first_name || '') + ' ' + (assignment.assigner_last_name || '')).trim() || '-';
                        document.getElementById('viewAssignedAt').textContent = assignment.assigned_at ? new Date(assignment.assigned_at).toLocaleString() : '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewNotes').textContent = assignment.assignment_notes || '-';
                        document.getElementById('viewFormDetails').innerHTML = renderIncomingFormDetails(assignment, content);
                        document.getElementById('viewDigitalPaperPreview').innerHTML = generateIncomingPreview(assignment, content);

                        const canReceive = assignment.assignment_status === 'Pending' || assignment.assignment_status === 'Forwarded';
                        document.getElementById('modalReceiveBtn').style.display = canReceive ? 'inline-flex' : 'none';

                        document.getElementById('viewIncomingModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function closeIncomingModal() {
            document.getElementById('viewIncomingModal').classList.remove('active');
            selectedIncomingAssignmentId = null;
        }

        function markCurrentAsReceived() {
            if (!selectedIncomingAssignmentId) {
                alert('No selected document to receive.');
                return;
            }
            markAsReceived(selectedIncomingAssignmentId);
        }

        function markAsReceived(assignmentId) {
            if (!confirm('Are you sure you want to mark this document as received?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'markReceived');
            formData.append('id', assignmentId);

            fetch('incoming-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Document marked as received successfully! Redirecting to Received Documents...');
                    window.location.href = 'received.php';
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking document as received');
            });
        }
    </script>
</body>
</html>
