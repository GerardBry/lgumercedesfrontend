<?php
/**
 * Main Dashboard - Requires Authentication
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
$office_department = $_SESSION['office_department'] ?? '';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LGU Mercedes Document Tracking System</title>
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
                        <a href="index.php" class="nav-item active" data-page="dashboard">
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
                        <a href="archive.php" class="nav-item" data-page="archive">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
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
            <!-- Dashboard Page -->
            <div class="page active">
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h2>Welcome to LGU Mercedes Document Tracking System</h2>
                        <p>Your centralized platform for managing and tracking administrative documents</p>
                    </div>
                </div>

                <div class="description-grid">
                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="description-content">
                            <h3>Digital Document Management</h3>
                            <p>Efficiently manage and organize administrative documents from submission to completion. Store and retrieve documents digitally, reducing the need for physical storage and manual handling.</p>
                        </div>
                    </div>

                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="description-content">
                            <h3>Real-Time Document Tracking</h3>
                            <p>Monitor your document status in real-time throughout the entire workflow. Track documents from incoming submission through received, returned, and finished stages with complete transparency.</p>
                        </div>
                    </div>

                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <div class="description-content">
                            <h3>Paperless & Eco-Friendly</h3>
                            <p>Reduce paperwork and unnecessary printing by managing all documents digitally. This environmentally conscious approach reduces paper waste while improving office efficiency and sustainability.</p>
                        </div>
                    </div>

                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="description-content">
                            <h3>Faster Processing & Workflow</h3>
                            <p>Experience improved document processing times with our streamlined workflow system. Automated routing and organized scheduling ensure documents are processed quickly and efficiently in the Mayor's Office.</p>
                        </div>
                    </div>

                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="description-content">
                            <h3>Enhanced Transparency</h3>
                            <p>Maintain complete visibility of all document statuses and processes. Our system provides transparency in document handling, ensuring accountability and maintaining detailed records for audit purposes.</p>
                        </div>
                    </div>

                    <div class="description-card">
                        <div class="description-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="description-content">
                            <h3>Generate Reports & Insights</h3>
                            <p>Create comprehensive reports on document processing, track metrics, and generate insights into workflow efficiency. Use data-driven information to identify bottlenecks and optimize processes.</p>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <h4>Getting Started</h4>
                            <p>Use the navigation menu on the left to access different sections. Submit new documents through "Document Entry," track existing documents using "Track Documents," and browse documents by status in their respective categories.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>
