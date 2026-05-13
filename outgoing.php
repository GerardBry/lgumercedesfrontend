<?php
/**
 * Outgoing Documents Page - Department Staff
 * View documents sent by this staff member
 */
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// STRICT ROLE-BASED ACCESS CONTROL - Only regular users allowed
if (isset($_SESSION['role'])) {
    // Block Super Admin
    if ($_SESSION['role'] === 'Super Admin') {
        header('Location: admin/admin-dashboard.php');
        exit;
    }
    // Block Administrative Assistant
    if ($_SESSION['role'] === 'Administrative Assistant') {
        header('Location: administrative/admin-dashboard-staff.php');
        exit;
    }
}

// Get user info from session
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

// Helper function to parse and format JSON notes
function formatNotes($notesJson) {
    if (empty($notesJson)) {
        return '-';
    }
    
    // Try to decode JSON
    $decoded = json_decode($notesJson, true);
    if (is_array($decoded)) {
        // Extract key info from JSON
        $parts = [];
        if (!empty($decoded['title'])) {
            $parts[] = 'Title: ' . htmlspecialchars($decoded['title']);
        }
        if (!empty($decoded['purpose'])) {
            $parts[] = 'Purpose: ' . htmlspecialchars($decoded['purpose']);
        }
        if (!empty($decoded['subject'])) {
            $parts[] = 'Subject: ' . htmlspecialchars($decoded['subject']);
        }
        if (!empty($decoded['type'])) {
            $parts[] = 'Type: ' . htmlspecialchars($decoded['type']);
        }
        
        return !empty($parts) ? implode(' | ', $parts) : htmlspecialchars($notesJson);
    }
    
    // If not JSON, return as-is
    return htmlspecialchars($notesJson);
}

// Fetch full user details from database
require_once 'config/db_connect.php';

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

// Fetch outgoing document assignments (documents created/sent by this user that have been assigned)
$outgoing_documents = [];
$sql = "SELECT 
        d.id as document_id,
        d.title,
        d.notes,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as doc_notes,
        d.status,
        d.sender_name,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.deadline,
        d.file_path,
        da.id as assignment_id,
        da.assigned_to,
        da.office_department,
        da.status as assignment_status,
        da.assigned_at,
        recipient.first_name as recipient_first_name,
        recipient.last_name as recipient_last_name,
        recipient.position as recipient_position
    FROM document_assignments da
    INNER JOIN documents d ON d.id = da.document_id
    LEFT JOIN users recipient ON da.assigned_to = recipient.id
    WHERE d.created_by = ? 
    AND d.status NOT IN ('Approved', 'Completed', 'Returned')
    AND da.status <> 'Returned'
    ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $outgoing_documents[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
    <title>Outgoing Documents - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
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

        .btn-info:hover {
            background-color: #bbdefb;
        }

        .table-container {
            overflow-x: auto;
            overflow-y: hidden;
        }

        .data-table {
            min-width: 1200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">

            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h1>LGU Mercedes</h1>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="index.php" class="nav-item" data-page="dashboard">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="trackdocument.php" class="nav-item" data-page="track">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="documententry.php" class="nav-item" data-page="entry">
                            <i class="fas fa-file-upload"></i>
                            <span>Documents</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="incoming.php" class="nav-item" data-page="incoming">
                            <div>
                                <i class="fas fa-inbox"></i>
                                <span>Incoming</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="nav-item active" data-page="outgoing">
                            <div>
                                <i class="fas fa-paper-plane"></i>
                                <span>Outgoing</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="nav-item" data-page="received">
                            <div>
                                <i class="fas fa-envelope-open"></i>
                                <span>Approved</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <div>
                                <i class="fas fa-check-circle"></i>
                                <span>Finished</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="nav-item" data-page="archive">
                            <div>
                                <i class="fas fa-archive"></i>
                                <span>Archive</span>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="nav-item" data-page="reports">
                            <div>
                                <i class="fas fa-chart-pie"></i>
                                <span>Reports</span>
                            </div>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="profile.php" class="nav-item" data-page="profile">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="user-name" id="userNameDisplay"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="handleLogout()" style="width: 100%; margin-top: 12px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header with Notifications -->
            <div style="padding: 15px 30px; border-bottom: 1px solid #eee; display: flex; justify-content: flex-end; align-items: center; background: white; position: relative; z-index: 10;">
                <div class="header-right" style="display: flex; gap: 16px; align-items: center; position: relative;">
                    <!-- Notification Bell will be inserted here by notifications.js -->
                </div>
            </div>
            <div class="page active">
                <div class="page-header">
                    <div class="header-with-button">
                        <div>
                            <h2>Outgoing Documents</h2>
                            <p>Documents you have sent to administrative staff</p>
                        </div>
                    </div>
                </div>

                <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-md);">
                    <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 16px; font-weight: 600;">Filters</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Search Keyword</label>
                            <input type="text" id="keywordFilter" placeholder="Search title, sender, description..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Sender</label>
                            <input type="text" id="senderFilter" placeholder="Filter by sender..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Prioritization</label>
                            <select id="priorityFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                                <option value="">All Priorities</option>
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date From</label>
                            <input type="date" id="dateFromFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date To</label>
                            <input type="date" id="dateToFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button onclick="clearFilters()" style="width: 100%; padding: 10px 12px; background-color: #e0e0e0; border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-dark); transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#d0d0d0'" onmouseout="this.style.backgroundColor='#e0e0e0'">
                                <i class="fas fa-redo"></i> Clear Filters
                            </button>
                        </div>
                    </div>
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
                        <tbody id="documentsTableBody">
                            <?php if (count($outgoing_documents) > 0): ?>
                                <?php foreach ($outgoing_documents as $doc): ?>
                                    <?php 
                                        // Use direct columns first, fallback to JSON
                                        $additionalData = $doc['notes'] ? json_decode($doc['notes'], true) : [];
                                        $classification = $doc['classification'] ?? $additionalData['classification'] ?? 'N/A';
                                        $sub_classification = $doc['sub_classification'] ?? $additionalData['sub_classification'] ?? 'N/A';
                                        $priority = $doc['priority'] ?? $additionalData['priority'] ?? 'N/A';
                                        $dateReceived = $doc['date_received'] ?? $additionalData['date_received'] ?? $doc['date_sent'];
                                        $sender = $doc['sender_name'] ?? $additionalData['sender'] ?? 'N/A';
                                        $trackingCode = $doc['tracking_number'] ?? 'N/A';
                                        $description = $doc['description'] ?? '';
                                        $filterDate = $dateReceived ? date('Y-m-d', strtotime($dateReceived)) : '';
                                        $keywordSource = strtolower(trim(($trackingCode ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($sender ?? '') . ' ' . ($description ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($sender)); ?>" data-priority="<?php echo htmlspecialchars($priority); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($trackingCode); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($sender); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo $dateReceived ? date('M d, Y', strtotime($dateReceived)) : '-'; ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($classification); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sub_classification); ?></td>
                                        <td>
                                            <?php 
                                                $priority_class = 'badge-primary';
                                                if ($priority === 'Urgent') $priority_class = 'badge-warning';
                                                elseif ($priority === 'Critical') $priority_class = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = !empty($doc['assignment_status']) ? $doc['assignment_status'] : ($doc['status'] ?? 'Pending');
                                                $badge_class = 'badge-info';
                                                if ($status === 'Received' || $status === 'Approved' || $status === 'Completed') $badge_class = 'badge-success';
                                                elseif ($status === 'Pending') $badge_class = 'badge-warning';
                                                elseif ($status === 'Rejected' || $status === 'Returned') $badge_class = 'badge-warning';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewOutgoingDocument(<?php echo $doc['document_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="10" class="empty-state">No outgoing documents</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="10" class="empty-state">No outgoing documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Outgoing Document Modal -->
    <div id="viewOutgoingModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeOutgoingModal()">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeOutgoingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600; border-bottom: 3px solid var(--primary-color); padding-bottom: 8px;">Document Information</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Document ID</label>
                        <span style="font-size: 14px; font-weight: 600; color: #333;" id="viewDocumentID">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Subject/Title</label>
                        <span style="font-size: 14px; color: #333;" id="viewTitle">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sender</label>
                        <span style="font-size: 14px; color: #333;" id="viewSender">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Date Received</label>
                        <span style="font-size: 14px; color: #333;" id="viewDateReceived">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Classification</label>
                        <span id="viewClassification" style="display: inline-block;"><span class="badge badge-info">-</span></span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sub-Classification</label>
                        <span style="font-size: 14px; color: #333;" id="viewSubClassification">-</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Prioritization</label>
                        <span id="viewPrioritization" style="display: inline-block;"><span class="badge badge-primary">-</span></span>
                    </div>
                </div>

                <div class="detail-row" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Description</label>
                    <span style="font-size: 14px; color: #333; display: block; line-height: 1.6;" id="viewDescription">-</span>
                </div>

                <div class="detail-row" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Created Date</label>
                    <span style="font-size: 14px; color: #333;" id="viewCreatedDate">-</span>
                </div>

                <div class="detail-row" style="margin-top: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 14px; color: #333; flex: 1;" id="viewFileName">No attachment</span>
                        <button type="button" class="btn btn-sm btn-warning" onclick="viewOutgoingFile()" id="viewFileBtn" style="display: none;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="downloadOutgoingDocument()" id="downloadOutgoingBtn" style="display: none;">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOutgoingModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileViewerModal" class="modal">
        <div class="modal-content" style="max-width: 60vw; max-height: 90vh; overflow: auto;">
            <div class="modal-header">
                <h3 id="fileViewerTitle">File Viewer</h3>
                <button class="modal-close" onclick="closeFileViewerModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <img id="fileViewerImage" src="" style="max-width: 100%; max-height: calc(90vh - 150px); object-fit: contain;" alt="Document Image">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="downloadOutgoingDocument()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function applyFilters() {
            const keyword = (document.getElementById('keywordFilter')?.value || '').toLowerCase();
            const sender = (document.getElementById('senderFilter')?.value || '').toLowerCase();
            const priority = document.getElementById('priorityFilter')?.value || '';
            const dateFromValue = document.getElementById('dateFromFilter')?.value || '';
            const dateToValue = document.getElementById('dateToFilter')?.value || '';
            const dateFrom = dateFromValue ? new Date(dateFromValue) : null;
            const dateTo = dateToValue ? new Date(dateToValue) : null;

            const rows = document.querySelectorAll('#documentsTableBody tr[data-filter-row="true"]');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowKeywords = row.dataset.keywords || '';
                const rowSender = row.dataset.sender || '';
                const rowPriority = row.dataset.priority || '';
                const rowDateValue = row.dataset.date || '';
                const rowDate = rowDateValue ? new Date(rowDateValue) : null;

                if (keyword && !rowKeywords.includes(keyword)) {
                    row.style.display = 'none';
                    return;
                }

                if (sender && !rowSender.includes(sender)) {
                    row.style.display = 'none';
                    return;
                }

                if (priority && rowPriority !== priority) {
                    row.style.display = 'none';
                    return;
                }

                if (dateFrom && (!rowDate || rowDate < dateFrom)) {
                    row.style.display = 'none';
                    return;
                }

                if (dateTo && rowDate) {
                    const dateToEnd = new Date(dateTo);
                    dateToEnd.setDate(dateToEnd.getDate() + 1);
                    if (rowDate >= dateToEnd) {
                        row.style.display = 'none';
                        return;
                    }
                } else if (dateTo && !rowDate) {
                    row.style.display = 'none';
                    return;
                }

                row.style.display = '';
                visibleCount += 1;
            });

            const emptyRow = document.getElementById('emptyFilterRow');
            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        function clearFilters() {
            const keywordInput = document.getElementById('keywordFilter');
            const senderInput = document.getElementById('senderFilter');
            const prioritySelect = document.getElementById('priorityFilter');
            const dateFromInput = document.getElementById('dateFromFilter');
            const dateToInput = document.getElementById('dateToFilter');

            if (keywordInput) keywordInput.value = '';
            if (senderInput) senderInput.value = '';
            if (prioritySelect) prioritySelect.value = '';
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            applyFilters();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('keywordFilter')?.addEventListener('input', applyFilters);
            document.getElementById('senderFilter')?.addEventListener('input', applyFilters);
            document.getElementById('priorityFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateFromFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateToFilter')?.addEventListener('change', applyFilters);
            applyFilters();
        });

        let currentOutgoingDocument = null;

        function safeParseJson(text) {
            try {
                return JSON.parse(text || '{}');
            } catch (e) {
                return {};
            }
        }

        function formatDateOrDash(value) {
            if (!value) return '-';
            const date = new Date(value);
            return !isNaN(date.getTime())
                ? date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : '-';
        }

        function escapeText(value) {
            if (typeof htmlEscape === 'function') {
                return htmlEscape(value);
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function viewOutgoingDocument(documentId) {
            // Fetch document details from server
            fetch('outgoing-handler.php?action=view&id=' + documentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const doc = data.document;
                        const content = safeParseJson(doc.notes);
                        
                        currentOutgoingDocument = doc;
                        
                        // Populate modal with data - use direct columns first
                        document.getElementById('viewDocumentID').textContent = doc.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        document.getElementById('viewSender').textContent = doc.sender_name || content.sender || '-';
                        
                        const dateReceived = doc.date_received || content.date_received;
                        document.getElementById('viewDateReceived').textContent = formatDateOrDash(dateReceived);
                        document.getElementById('viewDescription').textContent = doc.description || '-';
                        document.getElementById('viewCreatedDate').textContent = doc.date_sent ? new Date(doc.date_sent).toLocaleString() : '-';
                        
                        // Classification - use direct column first
                        const classification = doc.classification || content.classification || 'N/A';
                        let classificationClass = 'badge-info';
                        if (classification === 'Letter') classificationClass = 'badge-classification-letter';
                        else if (classification === 'Invitation') classificationClass = 'badge-classification-invitation';
                        else if (classification === 'Travel-Related Communication') classificationClass = 'badge-classification-travel';
                        else if (classification === 'Indorsement') classificationClass = 'badge-classification-indorsement';
                        
                        document.getElementById('viewClassification').innerHTML = `<span class="badge ${classificationClass}" style="font-size: 11px; padding: 5px 10px;">${escapeText(classification)}</span>`;
                        
                        // Sub-Classification - use direct column first
                        const subClassification = doc.sub_classification || content.sub_classification || 'N/A';
                        document.getElementById('viewSubClassification').textContent = escapeText(subClassification);
                        
                        // Prioritization - use direct column first
                        const priority = doc.priority || content.priority || 'N/A';
                        let priorityClass = 'badge-primary';
                        if (priority === 'Urgent') priorityClass = 'badge-warning';
                        else if (priority === 'Critical') priorityClass = 'badge-danger';
                        
                        document.getElementById('viewPrioritization').innerHTML = `<span class="badge ${priorityClass}" style="font-size: 11px; padding: 5px 10px;">${escapeText(priority)}</span>`;
                        
                        // File handling - use direct column first
                        const filePath = doc.file_path || content.file_path;
                        if (filePath) {
                            const fileName = filePath.split('/').pop();
                            document.getElementById('viewFileName').textContent = fileName;
                            
                            const fileExt = fileName.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
                            const isPDF = fileExt === 'pdf';
                            
                            if (isImage || isPDF) {
                                document.getElementById('viewFileBtn').style.display = 'inline-block';
                            } else {
                                document.getElementById('viewFileBtn').style.display = 'none';
                            }
                            document.getElementById('downloadOutgoingBtn').style.display = 'inline-block';
                        } else {
                            document.getElementById('viewFileName').textContent = 'No attachment';
                            document.getElementById('viewFileBtn').style.display = 'none';
                            document.getElementById('downloadOutgoingBtn').style.display = 'none';
                        }
                        
                        // Open modal
                        document.getElementById('viewOutgoingModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewOutgoingFile() {
            if (!currentOutgoingDocument) {
                alert('No file to view');
                return;
            }
            const content = safeParseJson(currentOutgoingDocument.notes);
            const filePath = currentOutgoingDocument.file_path || content.file_path;
            if (!filePath) {
                alert('No file attached');
                return;
            }
            
            const fileName = filePath.split('/').pop();
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isPDF = fileExt === 'pdf';
            
            if (isPDF) {
                // Open PDF in new tab
                window.open('view-document-file.php?path=' + encodeURIComponent(filePath), '_blank');
            } else {
                // Show image in modal
                const url = 'view-document-file.php?path=' + encodeURIComponent(filePath);
                document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
                document.getElementById('fileViewerImage').src = url;
                document.getElementById('fileViewerModal').classList.add('active');
            }
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }

        function downloadOutgoingDocument() {
            if (!currentOutgoingDocument) {
                alert('No document selected');
                return;
            }

            const content = safeParseJson(currentOutgoingDocument.notes);
            const filePath = currentOutgoingDocument.file_path || content.file_path;
            if (!filePath) {
                alert('No file available for download');
                return;
            }

            const fileName = filePath.split('/').pop();
            const url = 'get-document-file.php?path=' + encodeURIComponent(filePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeOutgoingModal() {
            document.getElementById('viewOutgoingModal').classList.remove('active');
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>