<?php
/**
 * User Profile Page - Requires Authentication
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

// Handle password change
$password_message = '';
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        if (!password_verify($current_password, $user_details['password'])) {
            $password_error = 'Current password is incorrect.';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if ($update_stmt) {
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                if ($update_stmt->execute()) {
                    $password_message = 'Password changed successfully!';
                    // Log the action
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'Change Password', 'User changed their password', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("is", $user_id, $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } else {
                    $password_error = 'Error updating password. Please try again.';
                }
                $update_stmt->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #ff9500 0%, #ffb84d 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            flex-shrink: 0;
        }

        .profile-header-info h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
        }

        .profile-header-info p {
            margin: 0;
            opacity: 0.9;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-card h2 {
            font-size: 18px;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #ff9500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-card h2 i {
            color: #ff9500;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .profile-item {
            display: flex;
            flex-direction: column;
        }

        .profile-item label {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .profile-item p {
            font-size: 15px;
            color: #333;
            margin: 0;
            word-break: break-word;
        }

        .password-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .password-form h2 {
            font-size: 18px;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #ff9500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-form h2 i {
            color: #ff9500;
        }

        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff9500;
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .password-requirements {
            background: #f5f5f5;
            border-left: 4px solid #ff9500;
            padding: 12px;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
        }

        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .password-requirements li {
            margin: 4px 0;
        }

        .form-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #ff9500;
            color: white;
        }

        .btn-primary:hover {
            background-color: #ff8000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message i {
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .form-buttons {
                flex-direction: column-reverse;
            }

            .btn {
                justify-content: center;
            }
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
                            <i class="fas fa-chart-pie"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <a href="profile.php" class="nav-item active" data-page="profile">
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
                <div class="profile-container">
                    <!-- Profile Header -->
                    <?php if ($user_details): ?>
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-header-info">
                                <h1><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h1>
                                <p><?php echo htmlspecialchars($user_details['position'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($user_details['office_department'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="profile-card">
                            <h2><i class="fas fa-id-card"></i> Personal Information</h2>
                            <div class="profile-grid">
                                <div class="profile-item">
                                    <label>First Name</label>
                                    <p><?php echo htmlspecialchars($user_details['first_name'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Last Name</label>
                                    <p><?php echo htmlspecialchars($user_details['last_name'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Middle Name</label>
                                    <p><?php echo htmlspecialchars($user_details['middle_name'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Date of Birth</label>
                                    <p><?php echo $user_details['date_of_birth'] ? date('F d, Y', strtotime($user_details['date_of_birth'])) : '-'; ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Civil Status</label>
                                    <p><?php echo htmlspecialchars($user_details['civil_status'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Contact Number</label>
                                    <p><?php echo htmlspecialchars($user_details['contact_number'] ?? '-'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Work Information -->
                        <div class="profile-card">
                            <h2><i class="fas fa-briefcase"></i> Work Information</h2>
                            <div class="profile-grid">
                                <div class="profile-item">
                                    <label>Position</label>
                                    <p><?php echo htmlspecialchars($user_details['position'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Office/Department</label>
                                    <p><?php echo htmlspecialchars($user_details['office_department'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Role</label>
                                    <p><?php echo htmlspecialchars($user_details['role'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Account Status</label>
                                    <p><?php echo htmlspecialchars($user_details['status'] ?? '-'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="profile-card">
                            <h2><i class="fas fa-map-marker-alt"></i> Address</h2>
                            <div class="profile-grid">
                                <div class="profile-item">
                                    <label>House No.</label>
                                    <p><?php echo htmlspecialchars($user_details['house_no'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Street</label>
                                    <p><?php echo htmlspecialchars($user_details['street'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Barangay</label>
                                    <p><?php echo htmlspecialchars($user_details['barangay'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Municipality</label>
                                    <p><?php echo htmlspecialchars($user_details['municipality'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Province</label>
                                    <p><?php echo htmlspecialchars($user_details['province'] ?? '-'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="profile-card">
                            <h2><i class="fas fa-lock"></i> Account Information</h2>
                            <div class="profile-grid">
                                <div class="profile-item">
                                    <label>Username</label>
                                    <p><?php echo htmlspecialchars($user_details['username'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Email</label>
                                    <p><?php echo htmlspecialchars($user_details['email'] ?? '-'); ?></p>
                                </div>
                                <div class="profile-item">
                                    <label>Registered Date</label>
                                    <p><?php echo $user_details['created_at'] ? date('F d, Y', strtotime($user_details['created_at'])) : '-'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password Form -->
                        <div class="password-form">
                            <h2><i class="fas fa-key"></i> Change Password</h2>
                            
                            <?php if ($password_message): ?>
                                <div class="message success">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo htmlspecialchars($password_message); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($password_error): ?>
                                <div class="message error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span><?php echo htmlspecialchars($password_error); ?></span>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="password-requirements">
                                    <strong>Password Requirements:</strong>
                                    <ul>
                                        <li>Minimum 6 characters long</li>
                                        <li>New password and confirm password must match</li>
                                    </ul>
                                </div>

                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                                </div>

                                <div class="form-buttons">
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="profile-card">
                            <p>User details could not be loaded. Please try again later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>
