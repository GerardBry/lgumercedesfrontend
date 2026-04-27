<?php
/**
 * Complete Profile - Administrator Profile Information Form
 * First-time login requirement for Administrator role
 */

session_start();

// Check if user is logged in and is Administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrative Assistant') {
    header('Location: login.php');
    exit;
}

require_once 'config/db_connect.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current user data
$user_data = [];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $middle_name = trim($_POST['middleName'] ?? '');
    $civil_status = trim($_POST['civilStatus'] ?? '');
    $date_of_birth = $_POST['dateOfBirth'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $office_department = trim($_POST['office_department'] ?? '');
    $house_no = trim($_POST['houseNo'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $municipality = trim($_POST['municipality'] ?? 'Mercedes');
    $province = trim($_POST['province'] ?? 'Camarines Norte');

    // Validation
    if ($position === '' || $office_department === '' || $house_no === '' || $street === '' || $barangay === '') {
        $error = 'Please fill in all required fields';
    } else {
        // Update user profile
        $update_sql = "UPDATE users SET middle_name = ?, position = ?, office_department = ?, civil_status = ?, date_of_birth = ?, contact_number = ?, house_no = ?, street = ?, barangay = ?, municipality = ?, province = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $update_stmt->bind_param(
                'sssssssssssi',
                $middle_name,
                $position,
                $office_department,
                $civil_status,
                $date_of_birth,
                $contact_number,
                $house_no,
                $street,
                $barangay,
                $municipality,
                $province,
                $user_id
            );

            if ($update_stmt->execute()) {
                // Log the action
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $log_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'Profile Completion', 'Administrator completed profile information', ?)";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $log_stmt->bind_param('is', $user_id, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                // Redirect to dashboard
                header('Location: administrative/admin-dashboard-staff.php');
                exit;
            } else {
                $error = 'Failed to update profile: ' . $update_stmt->error;
            }

            $update_stmt->close();
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
    <title>Complete Profile - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: var(--bg-light);
        }

        .profile-completion-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .profile-completion-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .profile-completion-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .profile-completion-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .profile-completion-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group.required label::after {
            content: ' *';
            color: #dc3545;
            font-weight: 700;
        }

        .form-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
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
            background-color: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background-color: #5568d3;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #d0d0d0;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .profile-completion-container {
                margin: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
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
    <div class="profile-completion-container">
        <div class="profile-completion-header">
            <h1><i class="fas fa-user-check"></i> Complete Your Profile</h1>
            <p>Welcome Administrator! Please complete your profile information to access your dashboard</p>
        </div>

        <div class="profile-completion-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-user"></i> Personal Information</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name (Display)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" disabled style="background-color: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Last Name (Display)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" disabled style="background-color: #f5f5f5;">
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" placeholder="Enter your middle name" value="<?php echo htmlspecialchars($user_data['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="dateOfBirth">Date of Birth</label>
                            <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="civilStatus">Civil Status</label>
                            <select id="civilStatus" name="civilStatus">
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?php echo ($user_data['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($user_data['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo ($user_data['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($user_data['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" placeholder="+63 XXX XXX XXXX" value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Work Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-briefcase"></i> Work Information <span style="font-size: 12px; color: #dc3545;">(Required)</span></div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" placeholder="Your position/title" value="<?php echo htmlspecialchars($user_data['position'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group required">
                            <label for="office_department">Office/Department</label>
                            <input type="text" id="office_department" name="office_department" placeholder="Your department" value="<?php echo htmlspecialchars($user_data['office_department'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> Address <span style="font-size: 12px; color: #dc3545;">(Required)</span></div>
                    
                    <div class="form-row">
                        <div class="form-group required">
                            <label for="houseNo">House No.</label>
                            <input type="text" id="houseNo" name="houseNo" placeholder="House number/lot number" value="<?php echo htmlspecialchars($user_data['house_no'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group required">
                            <label for="street">Street</label>
                            <input type="text" id="street" name="street" placeholder="Street name" value="<?php echo htmlspecialchars($user_data['street'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group required">
                            <label for="barangay">Barangay</label>
                            <input type="text" id="barangay" name="barangay" placeholder="Barangay" value="<?php echo htmlspecialchars($user_data['barangay'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="municipality">Municipality</label>
                            <input type="text" id="municipality" name="municipality" value="Mercedes" disabled style="background-color: #f5f5f5;">
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" value="Camarines Norte" disabled style="background-color: #f5f5f5;">
                        </div>
                    </div>
                </div>

                <!-- Form Buttons -->
                <div class="form-buttons">
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Complete Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
