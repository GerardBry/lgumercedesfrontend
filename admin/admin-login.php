<?php
/**
 * Admin Login Page
 * Super Admin only authentication
 */

session_start();

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Super Admin') {
    header('Location: admin-dashboard.php');
    exit;
}

require_once '../config/db_connect.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Get user from database
        $sql = "SELECT id, first_name, last_name, email, username, password, role, status FROM users WHERE (email = ? OR username = ?) AND role = 'Super Admin'";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param("ss", $email, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // Log failed attempt
                $log_sql = "INSERT INTO login_attempts (email, username, success, ip_address) VALUES (?, ?, 0, ?)";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $log_stmt->bind_param("sss", $email, $email, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $error = 'Invalid admin credentials or not authorized';
            } else {
                $user = $result->fetch_assoc();

                // Check account status
                if ($user['status'] === 'Pending') {
                    $error = 'Your account is pending approval. Please contact system administrator.';
                } elseif ($user['status'] === 'Inactive') {
                    $error = 'Your account has been deactivated.';
                } elseif (!password_verify($password, $user['password'])) {
                    // Log failed attempt
                    $log_sql = "INSERT INTO login_attempts (email, username, success, ip_address) VALUES (?, ?, 0, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("sss", $email, $user['username'], $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    $error = 'Invalid admin credentials';
                } else {
                    // Password verified - create session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];

                    // Log successful attempt
                    $log_sql = "INSERT INTO login_attempts (email, username, success, ip_address) VALUES (?, ?, 1, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("sss", $email, $user['username'], $ip);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }

                    // Log admin login
                    $audit_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'Admin Login', 'Super Admin logged in', ?)";
                    $audit_stmt = $conn->prepare($audit_sql);
                    if ($audit_stmt) {
                        $audit_stmt->bind_param("is", $user['id'], $ip);
                        $audit_stmt->execute();
                        $audit_stmt->close();
                    }

                    $success = true;
                }
            }
            $stmt->close();
        }
    }

    $conn->close();
}

// Handle redirect
if ($success) {
    $_SESSION['login_success'] = true;
    header('Location: admin-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-screen active" style="background: #ffffff url('../img/LGU-Mercedes-Official-Logo.png') center/850px no-repeat fixed !important;">
        <div class="auth-form-container active">
            <div class="auth-card">
                <div class="auth-header">
                    <img src="../img/LGU-Mercedes-Official-Logo.png" alt="LGU Mercedes Logo" class="auth-logo">
                    <h1>Admin Portal</h1>
                    <p>Super Admin Access Only</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px; padding: 12px 16px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" placeholder="Enter your admin email" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-sign-in-alt"></i> Login to Admin Dashboard
                    </button>
                </form>

                <div class="auth-footer">
                    <p><a href="../login.php">← Back to Main Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <style>
        body .auth-screen::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.82);
            z-index: 0;
            pointer-events: none;
        }

        body .auth-form-container {
            position: relative;
            z-index: 2;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert i {
            margin-right: 8px;
        }
    </style>
</body>
</html>
