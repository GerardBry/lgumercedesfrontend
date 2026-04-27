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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finished - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
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
                    <?php
                    // DEBUG: Show all documents assigned to this user
                    echo "<!-- DEBUG: user_id = " . htmlspecialchars($user_id) . " -->";
                    
                    // Fetch finished/completed document assignments for regular user (assigned_to)
                    // Shows documents that are Received or Completed
                    $finished_documents = [];
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
                            da.completed_at
                        FROM document_assignments da
                        JOIN documents d ON da.document_id = d.id
                        LEFT JOIN users u_sender ON d.sender_id = u_sender.id
                        WHERE da.assigned_to = ?
                        AND da.status IN ('Received', 'Completed')
                        ORDER BY da.completed_at DESC, da.assigned_at DESC";

                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        echo "<!-- DEBUG: Finished documents found: " . $result->num_rows . " -->";
                        while ($row = $result->fetch_assoc()) {
                            $finished_documents[] = $row;
                        }
                        $stmt->close();
                    } else {
                        echo "<!-- Database Error: " . htmlspecialchars($conn->error) . " -->";
                    }
                    
                    // DEBUG: Show ALL documents assigned to this user (any status)
                    $sql_all = "SELECT da.id, da.assigned_to, da.status, d.title FROM document_assignments da JOIN documents d ON da.document_id = d.id WHERE da.assigned_to = ?";
                    $stmt_all = $conn->prepare($sql_all);
                    if ($stmt_all) {
                        $stmt_all->bind_param("i", $user_id);
                        $stmt_all->execute();
                        $result_all = $stmt_all->get_result();
                        echo "<!-- DEBUG: ALL documents assigned to user (any status): " . $result_all->num_rows . " -->";
                        while ($row = $result_all->fetch_assoc()) {
                            echo "<!-- DEBUG: Assignment " . $row['id'] . ": " . $row['title'] . " - Status: " . $row['status'] . " -->";
                        }
                        $stmt_all->close();
                    }
                    
                    $conn->close();
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
                                        <td><?php echo htmlspecialchars($doc['document_type'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['date_sent'] ? date('Y-m-d', strtotime($doc['date_sent'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['description'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['assignment_notes'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['assignment_status'] ?? '-'); ?></td>
                                        <td><a href="trackdocument.php?id=<?php echo intval($doc['document_id']); ?>" class="btn btn-sm btn-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
