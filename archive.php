<?php
/**
 * Archive Page - Requires Authentication
 * Regular user only (blocks Super Admin and Administrative Assistant)
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

// Fetch archived documents
$archived_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.date_sent,
    d.date_received,
        d.notes as doc_notes,
    d.sender_name,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.archived_at
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE (d.created_by = ? OR da.assigned_to = ?)
    AND da.status = 'Archived'
    ORDER BY da.archived_at DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $archived_documents[] = $row;
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
    <title>Archive - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                            <i class="fas fa-inbox"></i>
                            <span>Incoming</span>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="nav-item" data-page="outgoing">
                            <i class="fas fa-paper-plane"></i>
                            <span>Outgoing</span>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="nav-item" data-page="received">
                            <i class="fas fa-envelope-open"></i>
                            <span>Approved</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="nav-item active" data-page="archive">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
                        </a>
                    </li>
                                        <li>
                        <a href="reports.php" class="nav-item" data-page="reports">
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
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
                            <h2>Archived Documents</h2>
                            <p>Documents stored in the archive for historical records</p>
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
                        <tbody>
                            <?php if (count($archived_documents) > 0): ?>
                                <?php foreach ($archived_documents as $doc): ?>
                                    <?php
                                        $senderName = trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''));
                                        if ($senderName === '') {
                                            $senderName = trim($doc['sender_name'] ?? '');
                                        }
                                        if ($senderName === '') {
                                            $senderName = 'N/A';
                                        }
                                        $priorityValue = $doc['priority'] ?? 'N/A';
                                        $dateReceived = $doc['date_received'] ?? $doc['archived_at'] ?? $doc['date_sent'] ?? '';
                                        $filterDate = $dateReceived ? date('Y-m-d', strtotime($dateReceived)) : '';
                                        $keywordSource = strtolower(trim(($doc['tracking_number'] ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($senderName ?? '') . ' ' . ($doc['description'] ?? '') . ' ' . ($doc['assignment_notes'] ?? '')));
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($senderName)); ?>" data-priority="<?php echo htmlspecialchars($priorityValue); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($senderName); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo $dateReceived ? date('M d, Y', strtotime($dateReceived)) : '-'; ?></td>
                                        <td>
                                            <?php
                                                $classification = $doc['classification'] ?? 'N/A';
                                                $classificationClass = 'badge-info';
                                                if ($classification === 'Letter') $classificationClass = 'badge-classification-letter';
                                                elseif ($classification === 'Invitation') $classificationClass = 'badge-classification-invitation';
                                                elseif ($classification === 'Travel-Related Communication') $classificationClass = 'badge-classification-travel';
                                                elseif ($classification === 'Indorsement') $classificationClass = 'badge-classification-indorsement';
                                            ?>
                                            <span class="badge <?php echo $classificationClass; ?>"><?php echo htmlspecialchars($classification); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['sub_classification'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                                $priorityClass = 'badge-primary';
                                                if ($priorityValue === 'Urgent') $priorityClass = 'badge-warning';
                                                elseif ($priorityValue === 'Critical') $priorityClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($priorityValue); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars($doc['assignment_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewArchivedDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #999;">No archived documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Archived Document Modal -->
    <div id="viewArchivedModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details - Archived</h3>
                <button class="modal-close" onclick="closeArchivedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="max-height: 600px; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        <!-- Tracking Code -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Tracking Code</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewDocumentID">-</span>
                            </div>
                        </div>

                        <!-- Subject / Title -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Subject / Title</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewTitle">-</span>
                            </div>
                        </div>

                        <!-- From Sender -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">From Sender</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                <span id="viewSender">-</span>
                            </div>
                        </div>

                        <!-- Date Received & Status -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Date Received</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewDateReceived">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Status</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span class="badge badge-secondary">Archived</span>
                                </div>
                            </div>
                        </div>

                        <!-- Classification & Sub-Classification -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Classification</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewClassification">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Sub-Classification</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewSubClassification">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Prioritization & Created Date -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Prioritization</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewPrioritization">-</span>
                                </div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Created Date</label>
                                <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500; font-size: 15px;">
                                    <span id="viewCreatedDate">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Description</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px; font-size: 15px;">
                                <span id="viewDescription">-</span>
                            </div>
                        </div>

                        <!-- Attached File -->
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 6px;">Attached File</label>
                            <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                <span id="viewFileName" style="flex: 1; font-size: 14px; color: var(--text-dark);">-</span>
                                <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewArchivedFile()" style="display: none;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-sm btn-info" id="downloadArchivedBtn" onclick="downloadArchivedDocument()">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeArchivedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal for Archived -->
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
                <button type="button" class="btn btn-info" onclick="downloadArchivedFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        let currentArchivedFilePath = '';

        function applyFilters() {
            const keyword = (document.getElementById('keywordFilter')?.value || '').toLowerCase();
            const sender = (document.getElementById('senderFilter')?.value || '').toLowerCase();
            const priority = document.getElementById('priorityFilter')?.value || '';
            const dateFromValue = document.getElementById('dateFromFilter')?.value || '';
            const dateToValue = document.getElementById('dateToFilter')?.value || '';
            const dateFrom = dateFromValue ? new Date(dateFromValue) : null;
            const dateTo = dateToValue ? new Date(dateToValue) : null;

            const rows = document.querySelectorAll('tbody tr[data-filter-row="true"]');
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

        function escapeText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function viewArchivedDocument(assignmentId) {
            // Fetch document details via AJAX
            fetch('get-document-details.php?assignment_id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.document) {
                        const doc = data.document;
                        
                        // Populate modal fields - match the new format
                        document.getElementById('viewDocumentID').textContent = doc.tracking_number || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        document.getElementById('viewSender').textContent = doc.sender_name || ((doc.sender_first_name || '') + ' ' + (doc.sender_last_name || '')) || '-';
                        document.getElementById('viewDateReceived').textContent = doc.date_received ? new Date(doc.date_received).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                        
                        // Classification
                        if (doc.classification) {
                            document.getElementById('viewClassification').innerHTML = '<span class="badge badge-info">' + escapeText(doc.classification) + '</span>';
                        } else {
                            document.getElementById('viewClassification').innerHTML = '-';
                        }
                        
                        document.getElementById('viewSubClassification').textContent = doc.sub_classification || '-';
                        
                        // Prioritization
                        if (doc.priority) {
                            let badgeClass = 'badge-primary';
                            if (doc.priority === 'Urgent') badgeClass = 'badge-warning';
                            else if (doc.priority === 'Critical') badgeClass = 'badge-danger';
                            document.getElementById('viewPrioritization').innerHTML = '<span class="badge ' + badgeClass + '">' + escapeText(doc.priority) + '</span>';
                        } else {
                            document.getElementById('viewPrioritization').innerHTML = '<span class="badge badge-primary">Normal</span>';
                        }
                        
                        document.getElementById('viewDescription').textContent = doc.description || '-';
                        document.getElementById('viewCreatedDate').textContent = doc.date_sent ? new Date(doc.date_sent).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                        
                        // File handling
                        const fileBtn = document.getElementById('viewFileBtn');
                        const downloadBtn = document.getElementById('downloadArchivedBtn');
                        const fileNameSpan = document.getElementById('viewFileName');
                        
                        if (doc.file_path && doc.file_path !== '') {
                            const fileName = doc.file_path.split('/').pop();
                            fileNameSpan.textContent = fileName;
                            fileBtn.style.display = 'inline-block';
                            downloadBtn.style.display = 'inline-block';
                            currentArchivedFilePath = doc.file_path;
                        } else {
                            fileNameSpan.textContent = 'No attachment';
                            fileBtn.style.display = 'none';
                            downloadBtn.style.display = 'none';
                        }
                        
                        document.getElementById('viewArchivedModal').classList.add('active');
                    } else {
                        alert('Error loading document details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewArchivedFile() {
            if (!currentArchivedFilePath) {
                alert('No file attached to this document');
                return;
            }
            
            const filePath = currentArchivedFilePath;
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

        function downloadArchivedFile() {
            if (!currentArchivedFilePath) {
                alert('No file to download');
                return;
            }
            
            const filePath = currentArchivedFilePath;
            const fileName = filePath.split('/').pop();
            
            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function downloadArchivedDocument() {
            downloadArchivedFile();
        }

        function closeArchivedModal() {
            document.getElementById('viewArchivedModal').classList.remove('active');
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
        }

        function htmlEscape(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
