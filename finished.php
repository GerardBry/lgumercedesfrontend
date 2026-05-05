<?php
/**
 * Finished Page - Requires Authentication
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

// DEBUG: Check all documents assigned to this user
$debug_sql = "SELECT da.id, da.assigned_to, da.status, d.title FROM document_assignments da JOIN documents d ON da.document_id = d.id WHERE da.assigned_to = ? ORDER BY da.assigned_at DESC";
$debug_stmt = $conn->prepare($debug_sql);
$debug_results = [];
if ($debug_stmt) {
    $debug_stmt->bind_param("i", $user_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    while ($row = $debug_result->fetch_assoc()) {
        $debug_results[] = $row;
    }
    $debug_stmt->close();
}

// Fetch finished/completed document assignments
$finished_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        (SELECT d_orig.description FROM documents d_orig WHERE d_orig.tracking_number = d.tracking_number ORDER BY d_orig.date_sent ASC, d_orig.id ASC LIMIT 1) as description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes as doc_notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        da.office_department,
        (SELECT da_orig.notes FROM document_assignments da_orig JOIN documents d_orig ON da_orig.document_id = d_orig.id WHERE d_orig.tracking_number = d.tracking_number AND da_orig.assigned_by != da_orig.assigned_to ORDER BY da_orig.assigned_at ASC, da_orig.id ASC LIMIT 1) as assignment_notes,
        da.status as assignment_status,
        da.assigned_at,
        da.completed_at,
        da.completion_file
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE (d.created_by = ? OR da.assigned_to = ?)
    AND da.status = 'Completed'
    ORDER BY da.completed_at DESC, da.assigned_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $finished_documents[] = $row;
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
    <title>Finished - LGU Mercedes Document Tracking System</title>
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
                            <span>Document Entry</span>
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
                            <span>Received</span>
                        </a>
                    </li>
                    <li>
                        <a href="returned.php" class="nav-item" data-page="returned">
                            <i class="fas fa-undo"></i>
                            <span>Returned</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="nav-item active" data-page="finished">
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
                            <h2>Finished Documents</h2>
                            <p>Completed and fully processed documents</p>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <!-- DEBUG OUTPUT -->
                    <?php
                    echo "<!-- DEBUG: User ID = " . htmlspecialchars($user_id) . " -->";
                    echo "<!-- DEBUG: Total assignments for this user = " . count($debug_results) . " -->";
                    foreach ($debug_results as $d) {
                        echo "<!-- DEBUG: Assignment " . $d['id'] . ": " . htmlspecialchars($d['title']) . " - Status: " . htmlspecialchars($d['status']) . " -->";
                    }
                    echo "<!-- DEBUG: Finished documents count = " . count($finished_documents) . " -->";
                    ?>

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
                            <?php if (count($finished_documents) === 0): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No finished documents</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($finished_documents as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['tracking_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['title'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? '')) ?: '-'); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type'] ?? '-'); ?></span></td>
                                        <td><?php echo htmlspecialchars($doc['date_sent'] ? date('M d, Y g:i A', strtotime($doc['date_sent'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo formatNotes($doc['assignment_notes']); ?></td>
                                        <td><span class="badge badge-success"><?php echo htmlspecialchars($doc['assignment_status'] ?? '-'); ?></span></td>
                                        <td><button class="btn btn-sm btn-primary" onclick="viewFinishedDocument(<?php echo $doc['assignment_id']; ?>)" style="color: #0066cc; text-decoration: none;"><i class="fas fa-eye"></i> View</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Document Modal -->
    <div id="viewFinishedModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5);" onclick="if(event.target === this) closeFinishedModal()">
        <div class="modal-content" style="max-width: 900px; width: 90%;">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeFinishedModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px;">
                    <div class="form-group">
                        <label style="font-size: 11px;">Tracking Code</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewTrackingCode">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Document Type</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewDocumentType">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Title</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px; white-space: normal;">
                            <span id="viewTitle">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">From Sender</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewSender">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Date Sent</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewDateSent">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Completed At</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewCompletedAt">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 11px;">Status</label>
                        <div style="padding: 12px; background-color: var(--bg-light); border-radius: var(--radius-md); font-weight: 600; font-size: 13px;">
                            <span id="viewStatus">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label>Description</label>
                    <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                        <span id="viewDescription">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes / Instructions</label>
                    <div style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 60px;">
                        <span id="viewNotes">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Uploaded File</label>
                    <div id="viewDocumentPreview" style="padding: 10px; background-color: var(--bg-light); border-radius: var(--radius-md); min-height: 200px; overflow-x: hidden; overflow-y: auto;"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFinishedModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function viewFinishedDocument(assignmentId) {
            // Fetch document details from server
            fetch('get-document-details.php?assignment_id=' + assignmentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error, status = ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Document data:', data);
                    if (data.success && data.document) {
                        const doc = data.document;
                        
                        // Populate modal with data
                        document.getElementById('viewTrackingCode').textContent = doc.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = doc.document_type || '-';
                        document.getElementById('viewTitle').textContent = doc.title || '-';
                        document.getElementById('viewSender').textContent = (doc.sender_first_name || '') + ' ' + (doc.sender_last_name || '') || '-';
                        document.getElementById('viewDescription').textContent = doc.description || '-';
                        document.getElementById('viewDateSent').textContent = doc.date_sent ? new Date(doc.date_sent).toLocaleString() : '-';
                        document.getElementById('viewCompletedAt').textContent = doc.completed_at ? new Date(doc.completed_at).toLocaleString() : '-';
                        document.getElementById('viewStatus').textContent = doc.assignment_status || doc.document_status || '-';
                        document.getElementById('viewNotes').textContent = doc.assignment_notes || '-';

                        // Display completion paper from the database-backed endpoint
                        if (doc.has_completion_file) {
                            const fileUrl = 'get-document-file.php?assignment_id=' + assignmentId;
                            const fileName = doc.completion_file_name || 'Uploaded paper';
                            const extension = fileName.split('.').pop().toLowerCase();
                            const imageTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
                            if (imageTypes.includes(extension)) {
                                document.getElementById('viewDocumentPreview').innerHTML = `
                                    <img src="${fileUrl}" alt="Uploaded file" style="width: 100%; height: auto; max-height: 420px; object-fit: contain; display: block; margin: 0 auto; border-radius: var(--radius-md);" />
                                    <p style="margin: 10px 0 0; font-size: 12px; color: var(--text-light);">${fileName}</p>
                                `;
                            } else {
                                document.getElementById('viewDocumentPreview').innerHTML = `
                                    <div style="width: 100%; overflow-x: hidden; overflow-y: auto;">
                                        <iframe src="${fileUrl}" style="width: 100%; min-height: 420px; height: 420px; border: 0; border-radius: var(--radius-md); background: #fff;"></iframe>
                                    </div>
                                    <p style="margin: 10px 0 0; font-size: 12px; color: var(--text-light);">${fileName}</p>
                                `;
                            }
                        } else {
                            document.getElementById('viewDocumentPreview').innerHTML = '<p style="margin:0; color: var(--text-light);">No file attached.</p>';
                        }
                        
                        // Open modal
                        document.getElementById('viewFinishedModal').classList.add('active');
                    } else {
                        console.error('Error response:', data);
                        alert('Error: ' + (data.message || 'Unable to load document details'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error loading document details: ' + error.message);
                });
        }

        function closeFinishedModal() {
            document.getElementById('viewFinishedModal').classList.remove('active');
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
