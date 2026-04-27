<?php
/**
 * Incoming Page - Requires Authentication
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

// Fetch incoming document assignments with tracking information
$incoming_documents = [];
$sql = "SELECT 
        da.id as assignment_id,
        d.id as document_id,
        d.title,
        d.description,
        d.tracking_number,
        d.document_type,
        d.date_sent,
        d.notes,
        u_sender.first_name as sender_first_name,
        u_sender.last_name as sender_last_name,
        u.first_name as assigned_by_first,
        u.last_name as assigned_by_last,
        da.office_department,
        da.notes as assignment_notes,
        da.status,
        da.assigned_at,
        da.received_at
    FROM document_assignments da
    JOIN documents d ON da.document_id = d.id
    JOIN users u ON da.assigned_by = u.id
    LEFT JOIN users u_sender ON d.sender_id = u_sender.id
    WHERE da.assigned_to = ? 
    AND da.status = 'Pending'
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
    <title>Incoming - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
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
                            <span>Document Entry</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="incoming.php" class="nav-item active" data-page="incoming">
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
            <div class="page active">
                <div class="page-header">
                    <div class="header-with-button">
                        <div>
                            <h2>Incoming Documents</h2>
                            <p>Documents received but not yet processed</p>
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
                        <tbody>
                            <?php if (count($incoming_documents) > 0): ?>
                                <?php foreach ($incoming_documents as $doc): ?>
                                    <tr data-assignment-id="<?php echo (int)$doc['assignment_id']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['tracking_number'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars(($doc['sender_first_name'] ?? '') . ' ' . ($doc['sender_last_name'] ?? '')); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type'] ?? 'General'); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($doc['date_sent'] ?? $doc['assigned_at'])); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 50) . (strlen($doc['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['notes'] ?? ''), 0, 40) . (strlen($doc['notes'] ?? '') > 40 ? '...' : ''); ?></td>
                                        <td>
                                            <?php 
                                                $status = $doc['status'];
                                                $badge_class = 'badge-info';
                                                if ($status === 'Received') $badge_class = 'badge-success';
                                                elseif ($status === 'Pending') $badge_class = 'badge-warning';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> assignment-status"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewIncomingDocument(<?php echo $doc['assignment_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No incoming documents</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Incoming Document Modal -->
    <div id="viewIncomingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Details</h3>
                <button class="modal-close" onclick="closeIncomingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Tracking Code</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewTrackingCode">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Document Type</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewDocumentType">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Document Title</label>
                    <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                        <span id="viewTitle">-</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; min-height: 80px;">
                        <span id="viewDescription">-</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>From Sender</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewSender">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Date Sent</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewDateSent">-</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Assigned By (Admin)</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewAssignedBy">-</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Assigned At</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewAssignedAt">-</span>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Status</label>
                        <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; font-weight: 500;">
                            <span id="viewStatus">-</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes / Instructions from Admin</label>
                    <div style="padding: 10px; background-color: #f5f5f5; border-radius: 6px; min-height: 60px;">
                        <span id="viewNotes">-</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="incomingReceiveBtn" onclick="receiveIncomingDocument()" style="display: none;">
                    <i class="fas fa-check"></i> Receive
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeIncomingModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
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

        .modal.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }
    </style>

    <script src="script.js"></script>
    <script>
        let currentIncomingAssignmentId = null;

        function viewIncomingDocument(assignmentId) {
            // Fetch assignment details from server
            fetch('incoming-handler.php?action=view&id=' + assignmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.assignment) {
                        const assignment = data.assignment;
                        
                        // Populate modal with data
                        currentIncomingAssignmentId = assignmentId;

                        document.getElementById('viewTrackingCode').textContent = assignment.tracking_number || '-';
                        document.getElementById('viewDocumentType').textContent = assignment.document_type || '-';
                        document.getElementById('viewTitle').textContent = assignment.title || '-';
                        document.getElementById('viewDescription').textContent = assignment.description || '-';
                        document.getElementById('viewSender').textContent = (assignment.sender_first_name || '') + ' ' + (assignment.sender_last_name || '') || '-';
                        document.getElementById('viewDateSent').textContent = assignment.date_sent ? new Date(assignment.date_sent).toLocaleString() : '-';
                        document.getElementById('viewAssignedBy').textContent = (assignment.assigned_by_first || '') + ' ' + (assignment.assigned_by_last || '') || '-';
                        document.getElementById('viewNotes').textContent = assignment.assignment_notes || '-';
                        document.getElementById('viewStatus').textContent = assignment.assignment_status || '-';
                        document.getElementById('viewAssignedAt').textContent = assignment.assigned_at ? new Date(assignment.assigned_at).toLocaleString() : '-';

                        const receiveBtn = document.getElementById('incomingReceiveBtn');
                        if (assignment.assignment_status === 'Pending') {
                            receiveBtn.style.display = 'inline-flex';
                        } else {
                            receiveBtn.style.display = 'none';
                        }
                        
                        // Open modal
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

        function receiveIncomingDocument() {
            if (!currentIncomingAssignmentId) {
                return;
            }

            fetch('incoming-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'receive',
                    id: currentIncomingAssignmentId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Failed to receive document');
                        return;
                    }

                    document.getElementById('viewStatus').textContent = 'Received';
                    document.getElementById('incomingReceiveBtn').style.display = 'none';

                    const row = document.querySelector('tr[data-assignment-id="' + currentIncomingAssignmentId + '"]');
                    if (row) {
                        const statusBadge = row.querySelector('.assignment-status');
                        if (statusBadge) {
                            statusBadge.textContent = 'Received';
                            statusBadge.classList.remove('badge-warning');
                            statusBadge.classList.add('badge-success');
                        }
                    }

                    alert('Document marked as received');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update status');
                });
        }

        function closeIncomingModal() {
            document.getElementById('viewIncomingModal').classList.remove('active');
        }
    </script>
</body>
</html>
