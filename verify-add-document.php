<?php
/**
 * System Verification Script
 * Checks if all components are properly set up
 */

require_once 'config/db_connect.php';

$results = [
    'database' => ['status' => 'pending', 'message' => ''],
    'documents_table' => ['status' => 'pending', 'message' => ''],
    'fields' => ['status' => 'pending', 'message' => ''],
    'upload_dir' => ['status' => 'pending', 'message' => ''],
    'users' => ['status' => 'pending', 'message' => ''],
    'sample_data' => ['status' => 'pending', 'message' => '']
];

// Check database connection
try {
    if ($conn->connect_error) {
        $results['database']['status'] = 'error';
        $results['database']['message'] = 'Connection failed: ' . $conn->connect_error;
    } else {
        $results['database']['status'] = 'success';
        $results['database']['message'] = 'Connected to database: ' . DB_NAME;
    }
} catch (Exception $e) {
    $results['database']['status'] = 'error';
    $results['database']['message'] = $e->getMessage();
}

// Check documents table exists
$check_table = $conn->query("SHOW TABLES LIKE 'documents'");
if ($check_table && $check_table->num_rows > 0) {
    $results['documents_table']['status'] = 'success';
    $results['documents_table']['message'] = 'Documents table exists';
} else {
    $results['documents_table']['status'] = 'error';
    $results['documents_table']['message'] = 'Documents table not found';
}

// Check required columns
$required_fields = [
    'doc_sequence_number', 'tracking_number', 'title', 'description', 
    'document_type', 'sender_id', 'date_received', 'deadline',
    'classification', 'sub_classification', 'priority', 'file_path',
    'status', 'created_by', 'created_at'
];

$columns_result = $conn->query("SHOW COLUMNS FROM documents");
$existing_columns = [];
if ($columns_result) {
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
}

$missing_fields = [];
foreach ($required_fields as $field) {
    if (!in_array($field, $existing_columns)) {
        $missing_fields[] = $field;
    }
}

if (empty($missing_fields)) {
    $results['fields']['status'] = 'success';
    $results['fields']['message'] = 'All required fields exist (' . count($required_fields) . ' fields)';
} else {
    $results['fields']['status'] = 'warning';
    $results['fields']['message'] = 'Missing fields: ' . implode(', ', $missing_fields);
}

// Check upload directory
$upload_path = 'uploads/documents/';
if (is_dir($upload_path)) {
    if (is_writable($upload_path)) {
        $results['upload_dir']['status'] = 'success';
        $results['upload_dir']['message'] = 'Upload directory exists and is writable: ' . realpath($upload_path);
    } else {
        $results['upload_dir']['status'] = 'warning';
        $results['upload_dir']['message'] = 'Upload directory exists but is not writable';
    }
} else {
    $results['upload_dir']['status'] = 'error';
    $results['upload_dir']['message'] = 'Upload directory does not exist: ' . realpath($upload_path);
}

// Check users with Administrative Assistant role
$user_check = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'Administrative Assistant' LIMIT 5");
$admin_count = 0;
$admin_users = [];
if ($user_check) {
    $admin_count = $user_check->num_rows;
    while ($user = $user_check->fetch_assoc()) {
        $admin_users[] = $user;
    }
}

if ($admin_count > 0) {
    $results['users']['status'] = 'success';
    $results['users']['message'] = 'Found ' . $admin_count . ' Administrative Assistant user(s)';
} else {
    $results['users']['status'] = 'error';
    $results['users']['message'] = 'No Administrative Assistant users found in database';
}

// Check for sample documents
$doc_count = $conn->query("SELECT COUNT(*) as count FROM documents");
$doc_result = $doc_count->fetch_assoc();
$results['sample_data']['message'] = 'Total documents in system: ' . $doc_result['count'];
if ($doc_result['count'] > 0) {
    $results['sample_data']['status'] = 'success';
} else {
    $results['sample_data']['status'] = 'info';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Verification - Add Document Feature</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .check-item {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-left: 4px solid;
        }

        .check-item.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }

        .check-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .check-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }

        .check-item.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        .check-item.pending {
            background: #e2e3e5;
            border-left-color: #6c757d;
            color: #383d41;
        }

        .check-item i {
            font-size: 18px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .check-message {
            flex: 1;
        }

        .check-label {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .check-desc {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 2px;
        }

        .user-list {
            font-size: 13px;
            margin-top: 8px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        .user-item {
            margin: 4px 0;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            font-size: 13px;
            color: #666;
        }

        .next-steps {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 16px;
            border-radius: 4px;
            color: #0c47a3;
        }

        .next-steps h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
        }

        .next-steps ol {
            margin: 0;
            padding-left: 20px;
        }

        .next-steps li {
            margin: 6px 0;
            font-size: 13px;
        }

        a {
            color: #2196f3;
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-check-double"></i> System Verification</h1>
            <p>Add Document Feature - Setup Status</p>
        </div>

        <div class="content">
            <!-- Database -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-database"></i> Database
                </div>
                <div class="check-item <?php echo $results['database']['status']; ?>">
                    <i class="fas fa-<?php echo $results['database']['status'] === 'success' ? 'check-circle' : ($results['database']['status'] === 'error' ? 'times-circle' : 'exclamation-circle'); ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Database Connection</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['database']['message']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Database Structure -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-table"></i> Database Structure
                </div>
                <div class="check-item <?php echo $results['documents_table']['status']; ?>">
                    <i class="fas fa-<?php echo $results['documents_table']['status'] === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Documents Table</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['documents_table']['message']); ?></div>
                    </div>
                </div>

                <div class="check-item <?php echo $results['fields']['status']; ?>">
                    <i class="fas fa-<?php echo $results['fields']['status'] === 'success' ? 'check-circle' : ($results['fields']['status'] === 'warning' ? 'exclamation-triangle' : 'check-circle'); ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Required Fields</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['fields']['message']); ?></div>
                    </div>
                </div>
            </div>

            <!-- File System -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-folder"></i> File System
                </div>
                <div class="check-item <?php echo $results['upload_dir']['status']; ?>">
                    <i class="fas fa-<?php echo $results['upload_dir']['status'] === 'success' ? 'check-circle' : ($results['upload_dir']['status'] === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Upload Directory</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['upload_dir']['message']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Users -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-users"></i> Users
                </div>
                <div class="check-item <?php echo $results['users']['status']; ?>">
                    <i class="fas fa-<?php echo $results['users']['status'] === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Administrative Assistant Accounts</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['users']['message']); ?></div>
                        <?php if (!empty($admin_users)): ?>
                            <div class="user-list">
                                <strong>Available Accounts:</strong>
                                <?php foreach ($admin_users as $user): ?>
                                    <div class="user-item">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
                                        (<?php echo htmlspecialchars($user['email']); ?>)
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Data -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i> Data
                </div>
                <div class="check-item <?php echo $results['sample_data']['status']; ?>">
                    <i class="fas fa-<?php echo $results['sample_data']['status'] === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
                    <div class="check-message">
                        <div class="check-label">Documents in System</div>
                        <div class="check-desc"><?php echo htmlspecialchars($results['sample_data']['message']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="next-steps">
                <h3><i class="fas fa-arrow-right"></i> Next Steps</h3>
                <ol>
                    <li>Login to the system using an Administrative Assistant account</li>
                    <li>Navigate to <strong>Administrative Panel → Add Document</strong></li>
                    <li>Fill in the form with:
                        <ul style="margin: 6px 0; padding-left: 20px;">
                            <li>Subject/Title</li>
                            <li>Sender (from dropdown)</li>
                            <li>Date Received</li>
                            <li>Description (optional)</li>
                            <li>Classification (Letter, Invitation, or Travel-Related Communication)</li>
                            <li>Sub-Classification (based on classification selected)</li>
                            <li>Priority (Normal, Urgent, or Critical)</li>
                            <li>Deadline (optional)</li>
                            <li>Document file (PDF or image)</li>
                        </ul>
                    </li>
                    <li>Click "Add Document" to submit</li>
                    <li>Document will be assigned an auto-generated ID (DOC-0001, DOC-0002, etc.)</li>
                </ol>
            </div>
        </div>

        <div class="footer">
            <p>System Status: 
                <?php 
                $error_count = 0;
                foreach ($results as $result) {
                    if ($result['status'] === 'error') $error_count++;
                }
                if ($error_count === 0) {
                    echo '<span style="color: #28a745; font-weight: 600;">✓ Ready for Testing</span>';
                } else {
                    echo '<span style="color: #dc3545; font-weight: 600;">⚠ Issues Found (' . $error_count . ')</span>';
                }
                ?>
            </p>
            <p><a href="administrative/add-document.php">→ Go to Add Document Form</a> | <a href="login.php">← Back to Login</a></p>
        </div>
    </div>
</body>
</html>
