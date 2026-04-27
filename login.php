
<?php
session_start();
require_once 'config/db_connect.php';

$error = '';
$rejected_user = null;
$rejection_remarks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Check user in database - try with rejection_remarks first, fallback if it fails
        $sql = "SELECT id, first_name, last_name, email, username, password, role, status, rejection_remarks FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        
        // If rejection_remarks column doesn't exist, try without it
        if (!$stmt) {
            $sql = "SELECT id, first_name, last_name, email, username, password, role, status FROM users WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
        }
        
        if (!$stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = 'Invalid email or password';
            } else {
                $user = $result->fetch_assoc();
                
                // Get rejection remarks
                $rejection_remarks = isset($user['rejection_remarks']) ? $user['rejection_remarks'] : '';
                
                // Check password first
                if (!password_verify($password, $user['password'])) {
                    $error = 'Invalid email or password';
                } elseif ($user['status'] === 'Inactive') {
                    // Account is rejected/inactive
                    $rejected_user = $user;
                } elseif ($user['status'] === 'Pending') {
                    $error = 'Your account is pending admin approval. Please wait for approval.';
                } elseif ($user['status'] === 'Active' || $user['status'] === 'Approved') {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Check if Administrator needs to complete profile
                    if ($user['role'] === 'Administrative Assistant') {
                        // Check if profile is complete
                        $check_sql = "SELECT id FROM users WHERE id = ? AND position IS NOT NULL AND position != '' AND office_department IS NOT NULL AND office_department != '' AND house_no IS NOT NULL AND house_no != '' AND street IS NOT NULL AND street != '' AND barangay IS NOT NULL AND barangay != ''";
                        $check_stmt = $conn->prepare($check_sql);
                        if ($check_stmt) {
                            $check_stmt->bind_param("i", $user['id']);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            
                            if ($check_result->num_rows === 0) {
                                // Profile incomplete, redirect to completion form
                                header('Location: complete-profile.php');
                                exit;
                            }
                            $check_stmt->close();
                        }
                    }
                    
                    // Redirect based on role
                    if ($user['role'] === 'Super Admin') {
                        header('Location: admin/admin-dashboard.php');
                    } elseif ($user['role'] === 'Administrative Assistant') {
                        header('Location: administrative/admin-dashboard-staff.php');
                    } elseif ($user['role'] === 'Mayor') {
                        header('Location: mayor/admin-dashboard-mayor.php');
                    } elseif ($user['role'] === 'Record Officer') {
                        header('Location: record/admin-dashboard-officer.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                } else {
                    $error = 'Your account is inactive. Please contact admin.';
                }
            }
            $stmt->close();
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
    <title>Login - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        body #loginForm {
            position: relative;
            z-index: 2;
        }

        /* Rejection Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay[style*="display: none"] {
            display: none !important;
        }

        .rejection-modal {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 90%;
            padding: 40px 30px;
            text-align: center;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .rejection-icon {
            width: 80px;
            height: 80px;
            background-color: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: #dc3545;
        }

        .rejection-modal h2 {
            font-size: 24px;
            color: #dc3545;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .rejection-modal p {
            font-size: 14px;
            color: #666;
            margin: 0 0 20px 0;
            line-height: 1.6;
        }

        .rejection-remarks-box {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            text-align: left;
        }

        .rejection-remarks-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .rejection-remarks-text {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }

        .rejection-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .rejection-actions button,
        .rejection-actions a {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-return {
            background-color: #6c757d;
            color: #ffffff;
        }

        .btn-return:hover {
            background-color: #5a6268;
        }

        .btn-contact {
            background-color: #ff9500;
            color: #ffffff;
        }

        .btn-contact:hover {
            background-color: #e68900;
        }
    </style>
</head>
<body>
    <div class="auth-screen active" style="background: #ffffff url('img/LGU-Mercedes-Official-Logo.png') center/850px no-repeat fixed !important;">
        <!-- Login Form -->
        <div id="loginForm" class="auth-form-container active" <?php echo (isset($rejected_user) && $rejected_user ? 'style="display: none;"' : ''); ?>>
            <div class="auth-card">
                <div class="auth-header">
                    <img src="img/LGU-Mercedes-Official-Logo.png" alt="LGU Mercedes Logo" class="auth-logo">
                    <h1>LGU Mercedes</h1>
                    <p>Document Tracking System</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal-overlay" id="rejectionModal" <?php echo (isset($rejected_user) && $rejected_user ? 'style="display: flex;"' : 'style="display: none;"'); ?>>
        <div class="rejection-modal">
            <div class="rejection-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2>Account Rejected</h2>
            <p>Your account registration has been rejected.</p>
            
            <div class="rejection-remarks-box">
                <div class="rejection-remarks-label">Reason for Rejection:</div>
                <div class="rejection-remarks-text" id="rejectionReasonText">
                    <?php echo htmlspecialchars($rejection_remarks); ?>
                </div>
            </div>

            <p style="font-size: 13px; color: #999; margin: 15px 0;">
                If you believe this decision is incorrect, please contact the administrator for assistance.
            </p>

            <div class="rejection-actions">
                <button class="btn-return" onclick="goBackToLogin()">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </button>
            </div>
        </div>
    </div>

    <script>
        function goBackToLogin() {
            const rejectionModal = document.getElementById('rejectionModal');
            const loginForm = document.getElementById('loginForm');
            
            if (rejectionModal) {
                rejectionModal.style.display = 'none';
            }
            if (loginForm) {
                loginForm.style.display = 'block';
            }
        }
    </script>
    <script src="script.js"></script>
</body>
</html>
