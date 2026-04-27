<?php
session_start();

require_once 'config/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['firstName'] ?? '');
    $last_name = trim($_POST['lastName'] ?? '');
    $middle_name = trim($_POST['middleName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirmPassword'] ?? '';
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
    $terms = isset($_POST['terms']);

    if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$terms) {
        $error = 'You must agree to the terms and conditions';
    } elseif ($position === '' || $office_department === '' || $house_no === '' || $street === '' || $barangay === '') {
        $error = 'Please complete all required profile and address fields';
    } else {
        $username = strtok($email, '@');

        $check_sql = 'SELECT id FROM users WHERE email = ? OR username = ?';
        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $check_stmt->bind_param('ss', $email, $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = 'Email or username already exists';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $insert_sql = "INSERT INTO users (first_name, last_name, middle_name, email, username, password, role, position, office_department, civil_status, date_of_birth, contact_number, house_no, street, barangay, municipality, province, status) VALUES (?, ?, ?, ?, ?, ?, 'Department Staff', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

                $insert_stmt = $conn->prepare($insert_sql);

                if (!$insert_stmt) {
                    $error = 'Database error: ' . $conn->error;
                } else {
                    $insert_stmt->bind_param(
                        'ssssssssssssssss',
                        $first_name,
                        $last_name,
                        $middle_name,
                        $email,
                        $username,
                        $hashed_password,
                        $position,
                        $office_department,
                        $civil_status,
                        $date_of_birth,
                        $contact_number,
                        $house_no,
                        $street,
                        $barangay,
                        $municipality,
                        $province
                    );

                    if ($insert_stmt->execute()) {
                        $user_id = $insert_stmt->insert_id;
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                        $log_sql = "INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, 'User Registration', 'New account created, pending admin approval', ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        if ($log_stmt) {
                            $log_stmt->bind_param('is', $user_id, $ip);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }

                        $_SESSION['success'] = true;
                        $_SESSION['registration_message'] = 'Account created successfully! Awaiting admin approval. Redirecting to login...';
                        header('Location: register.php?success=1');
                        exit;
                    } else {
                        $error = 'Registration failed: ' . $insert_stmt->error;
                    }

                    $insert_stmt->close();
                }

                $check_stmt->close();
            }
        }

        $conn->close();
    }
}

if (isset($_GET['error']) && isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_GET['success']) && isset($_SESSION['success'])) {
    $success = $_SESSION['registration_message'] ?? 'Account created successfully! Redirecting to login...';
    unset($_SESSION['success']);
    unset($_SESSION['registration_message']);
    echo '<script>setTimeout(function(){ window.location.href = "login.php"; }, 3000);</script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LGU Mercedes Document Tracking System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body.register-page .auth-screen {
            background: #ffffff;
            background-image: url('img/LGU-Mercedes-Official-Logo.png');
            background-size: 850px 850px;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        body.register-page .auth-screen::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.82);
            z-index: 1;
            pointer-events: none;
        }

        body.register-page #registerForm {
            position: relative;
            z-index: 2;
            display: flex !important;
            justify-content: center !important;
            width: 100%;
            margin: 0 auto !important;
        }

        body.register-page .auth-card {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border-radius: 16px;
            margin: 0 auto !important;
        }
    </style>
</head>
<body class="register-page">
    <div class="auth-screen active">
        <!-- Register Form -->
        <div id="registerForm" class="auth-form-container active">
            <div class="auth-card" style="max-width: 900px; width: 95vw; max-height: 90vh; overflow-y: auto;">
                <div class="auth-header">
                    <img src="img/LGU-Mercedes-Official-Logo.png" alt="LGU Mercedes Logo" class="auth-logo">
                    <h1>Create Account</h1>
                    <p>LGU Mercedes Document Tracking System</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="POST" action="register.php">
                    <!-- Name Fields -->
                    <h4 class="form-section-title">Personal Information</h4>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="regLastName">Last Name *</label>
                            <input type="text" id="regLastName" name="lastName" placeholder="Last name" required>
                        </div>
                        <div class="form-group">
                            <label for="regFirstName">First Name *</label>
                            <input type="text" id="regFirstName" name="firstName" placeholder="First name" required>
                        </div>
                        <div class="form-group">
                            <label for="regMiddleName">Middle Name</label>
                            <input type="text" id="regMiddleName" name="middleName" placeholder="Middle name">
                        </div>
                    </div>

                    <!-- Civil Status -->
                    <div class="form-group">
                        <label for="regCivilStatus">Civil Status *</label>
                        <select id="regCivilStatus" name="civilStatus" required>
                            <option value="">Select civil status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>

                    <!-- Date of Birth & Age -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="regDateOfBirth">Date of Birth *</label>
                            <input type="date" id="regDateOfBirth" name="dateOfBirth" required onchange="calculateAge()">
                        </div>
                        <div class="form-group">
                            <label for="regAge">Age</label>
                            <input type="number" id="regAge" name="age" placeholder="" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="regContactNumber">Contact Number</label>
                        <input type="tel" id="regContactNumber" name="contact_number" placeholder="09xxxxxxxxx">
                    </div>

                    <!-- Office Information -->
                    <h4 class="form-section-title">Office Information</h4>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="regPosition">Position *</label>
                            <select id="regPosition" name="position" required>
                                <option value="">Select position</option>
                                <option value="Head">Head</option>
                                <option value="Staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="regOfficeDepartment">Office Department *</label>
                            <select id="regOfficeDepartment" name="office_department" required>
                                <option value="">Select office/department</option>
                                <option value="Municipal Health Office">Municipal Health Office</option>
                                <option value="Local Civil Registrar">Local Civil Registrar</option>
                                <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                                <option value="Mayor's Office">Mayor's Office</option>
                                <option value="Municipal Planning and Development Office">Municipal Planning and Development Office</option>
                                <option value="Accounting Office">Accounting Office</option>
                                <option value="Municipal Budget Office">Municipal Budget Office</option>
                                <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                                <option value="Municipal Assessor's Office">Municipal Assessor's Office</option>
                            </select>
                        </div>
                    </div>

                    <!-- Address -->
                    <h4 class="form-section-title">Address</h4>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="regHouseNo">House No. / Building *</label>
                            <input type="text" id="regHouseNo" name="houseNo" placeholder="House number or building name" required>
                        </div>
                        <div class="form-group">
                            <label for="regStreet">Street *</label>
                            <input type="text" id="regStreet" name="street" placeholder="Street name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="regBarangay">Barangay *</label>
                        <select id="regBarangay" name="barangay" required>
                            <option value="">Select barangay</option>
                            <option value="Apuao">Apuao</option>
                            <option value="Barangay I (Poblacion)">Barangay I (Poblacion)</option>
                            <option value="Barangay II (Poblacion)">Barangay II (Poblacion)</option>
                            <option value="Barangay III (Poblacion)">Barangay III (Poblacion)</option>
                            <option value="Barangay IV (Poblacion)">Barangay IV (Poblacion)</option>
                            <option value="Barangay V (Poblacion)">Barangay V (Poblacion)</option>
                            <option value="Barangay VI (Poblacion)">Barangay VI (Poblacion)</option>
                            <option value="Barangay VII (Poblacion)">Barangay VII (Poblacion)</option>
                            <option value="Caringo">Caringo</option>
                            <option value="Catandunganon">Catandunganon</option>
                            <option value="Cayucyucan">Cayucyucan</option>
                            <option value="Colasi">Colasi</option>
                            <option value="Del Rosario (Tagongtong)">Del Rosario (Tagongtong)</option>
                            <option value="Gaboc">Gaboc</option>
                            <option value="Hamoraon">Hamoraon</option>
                            <option value="Hinipaan">Hinipaan</option>
                            <option value="Lalawigan">Lalawigan</option>
                            <option value="Lanot">Lanot</option>
                            <option value="Mambungalon">Mambungalon</option>
                            <option value="Manguisoc">Manguisoc</option>
                            <option value="Masalongsalong">Masalongsalong</option>
                            <option value="Matoogtoog">Matoogtoog</option>
                            <option value="Pambuhan">Pambuhan</option>
                            <option value="Quinapaguian">Quinapaguian</option>
                            <option value="San Roque">San Roque</option>
                            <option value="Tarum">Tarum</option>
                        </select>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="regMunicipality">Municipality *</label>
                            <input type="text" id="regMunicipality" name="municipality" value="Mercedes" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label for="regProvince">Province *</label>
                            <input type="text" id="regProvince" name="province" value="Camarines Norte" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                        </div>
                    </div>

                    <!-- Account Information -->
                    <h4 class="form-section-title">Account Details</h4>

                    <div class="form-group">
                        <label for="regEmail">Email Address *</label>
                        <input type="email" id="regEmail" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="regPassword">Password *</label>
                            <input type="password" id="regPassword" name="password" placeholder="Enter password" required>
                        </div>
                        <div class="form-group">
                            <label for="regConfirmPassword">Confirm Password *</label>
                            <input type="password" id="regConfirmPassword" name="confirmPassword" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <!-- Terms & Agreement -->
                    <div class="form-group checkbox">
                        <input type="checkbox" id="regTerms" name="terms" required>
                        <label for="regTerms">I agree to the Terms and Conditions *</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function calculateAge() {
            const dateOfBirth = document.getElementById('regDateOfBirth').value;
            if (!dateOfBirth) return;
            
            const birthDate = new Date(dateOfBirth);
            const today = new Date();
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            document.getElementById('regAge').value = age;
        }


    </script>
</body>
</html>
