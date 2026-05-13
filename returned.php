<?php
/**
 * Returned Page - Requires Authentication
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

header('Location: incoming.php');
exit;

// Get user info from session
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

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

$conn->close();
// Fetch returned document assignments authored by this user (documents returned by recipients)
$returned_documents = [];
require_once 'config/db_connect.php';

$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        da.notes as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.returned_at,
        recipient.first_name as recipient_first_name,
        recipient.last_name as recipient_last_name
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    JOIN users recipient ON da.assigned_to = recipient.id
    WHERE da.assigned_by = ?
    AND da.status = 'Returned'
    ORDER BY da.returned_at DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $returned_documents[] = $row;
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
    <title>Returned - LGU Mercedes Document Tracking System</title>
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
                        <a href="returned.php" class="nav-item active" data-page="returned">
                            <i class="fas fa-undo"></i>
                            <span>Returned</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item" data-page="finished">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="nav-item" data-page="archive">
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
                            <h2>Returned Documents</h2>
                            <p>Documents that require revision or re-submission</p>
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
                        <tbody id="documentsTableBody">
                            <?php if (!empty($returned_documents)): ?>
                                <?php foreach ($returned_documents as $doc): ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars(strtolower(($doc['tracking_number'] ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($doc['description'] ?? ''))); ?>" data-sender="<?php echo htmlspecialchars(strtolower(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? ''))); ?>" data-priority="" data-date="<?php echo htmlspecialchars($doc['date_sent'] ?? ''); ?>">
                                        <td><strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? '')); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type'] ?? 'General'); ?></span></td>
                                        <td><?php echo !empty($doc['date_sent']) ? date('M d, Y h:i A', strtotime($doc['date_sent'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['doc_notes'] ?? ($doc['assignment_notes'] ?? '-')); ?></td>
                                        <td><span class="badge badge-warning"><?php echo htmlspecialchars($doc['assignment_status'] ?? 'Returned'); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewReturnedDocument(<?php echo (int)$doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No returned documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Returned Document Modal -->
    <div id="viewReturnedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeReturnedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
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
                    <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 80px;">
                        <span id="viewDescription">-</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>From Sender</label>
                        <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                            <span id="viewSender">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Date Sent</label>
                        <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                            <span id="viewDateSent">-</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Returned At</label>
                        <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 500;">
                            <span id="viewReturnedAt">-</span>
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

                <div class="form-group" id="fileSection" style="display: none;">
                    <label>Attached File</label>
                    <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <span id="fileName" style="flex: 1; font-size: 14px; color: var(--text-dark);">-</span>
                        <button type="button" class="btn btn-sm btn-primary" id="viewFileBtn" onclick="viewReturnedFile()" style="display: none;">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-sm btn-info" id="downloadFileBtn" onclick="downloadReturnedFile()">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReturnedModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- File Viewer Modal for Returned -->
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
                <button type="button" class="btn btn-info" onclick="downloadReturnedFile()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewReturnedDocument(assignmentId) {
            fetch('returned-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        const doc_data = assignment.doc_notes ? JSON.parse(assignment.doc_notes) : {};

                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewSender').textContent = (assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '') || '-';
                        document.getElementById('viewDateSent').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        document.getElementById('viewReturnedAt').textContent = assignment.returned_at ? new Date(assignment.returned_at).toLocaleString() : '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewNotes').textContent = assignment.assignment_notes || '-';

                        if (doc_data.file_path) {
                            const fileExt = doc_data.file_path.split('.').pop().toLowerCase();
                            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
                            const fileName = doc_data.file_path.split('/').pop();

                            window.currentReturnedFilePath = doc_data.file_path;
                            document.getElementById('fileSection').style.display = 'block';
                            document.getElementById('fileName').textContent = fileName;
                            document.getElementById('viewFileBtn').style.display = isImage ? 'inline-flex' : 'none';
                        } else {
                            document.getElementById('fileSection').style.display = 'none';
                        }

                        document.getElementById('viewReturnedModal').classList.add('active');
                    } else {
                        alert('Error loading document details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading document details');
                });
        }

        function viewReturnedFile() {
            if (!window.currentReturnedFilePath) {
                alert('No file to view');
                return;
            }
            const url = 'view-document-file.php?path=' + encodeURIComponent(window.currentReturnedFilePath);
            const fileName = window.currentReturnedFilePath.split('/').pop();
            document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
            document.getElementById('fileViewerImage').src = url;
            document.getElementById('fileViewerModal').classList.add('active');
        }

        function closeFileViewerModal() {
            document.getElementById('fileViewerModal').classList.remove('active');
            document.getElementById('fileViewerImage').src = '';
        }

        function downloadReturnedFile() {
            if (!window.currentReturnedFilePath) {
                alert('No file to download');
                return;
            }
            const fileName = window.currentReturnedFilePath.split('/').pop();
            const url = 'get-document-file.php?path=' + encodeURIComponent(window.currentReturnedFilePath);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function closeReturnedModal() {
            document.getElementById('viewReturnedModal').classList.remove('active');
        }
    </script>

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
    </script>
    <script src="script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>
