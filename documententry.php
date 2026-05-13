<?php
/**
 * Document Entry Page - Requires Authentication
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

// Get next document sequence number
$next_seq = 1;
$seq_result = $conn->query("SELECT MAX(doc_sequence_number) as max_seq FROM documents");
if ($seq_result) {
    $row = $seq_result->fetch_assoc();
    $next_seq = ($row['max_seq'] ?? 0) + 1;
}

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

$user_documents = [];
$sql = "SELECT 
        d.id,
        d.tracking_number,
        d.title,
        d.description,
        d.document_type,
        d.date_sent,
        d.created_at,
        d.status,
        d.doc_sequence_number,
        d.notes,
        d.sender_name,
        d.date_received,
        d.classification,
        d.sub_classification,
        d.priority,
        d.deadline,
        d.file_path
    FROM documents d
    WHERE d.created_by = ? 
    AND d.status = 'Pending'
    AND d.document_type != 'Travel Request'
    AND d.id NOT IN (SELECT DISTINCT document_id FROM document_assignments WHERE document_id IS NOT NULL)
    ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_documents[] = $row;
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
    <title>Travel Request Entry - LGU Mercedes Document Tracking System</title>
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
                        <a href="documententry.php" class="nav-item active" data-page="entry">
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
            <!-- Document Entry Page -->
            <div class="page active">
                <div class="page-header">
                    <div class="header-with-button">
                        <div>
                            <h2>Documents</h2>
                            <p>Add incoming documents</p>
                        </div>
                        <button class="btn btn-primary" onclick="openCreateDocumentModal()">
                            <i class="fas fa-plus"></i> Add Document
                        </button>
                    </div>
                </div>

                <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-md);">
                    <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 16px; font-weight: 600;">Filters</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Search Keyword</label>
                            <input type="text" id="keywordFilter" placeholder="Search title, sender, description..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Sender</label>
                            <input type="text" id="senderFilter" placeholder="Filter by sender..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Prioritization</label>
                            <select id="priorityFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                                <option value="">All Priorities</option>
                                <option value="Normal">Normal</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date From</label>
                            <input type="date" id="dateFromFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text-dark);">Date To</label>
                            <input type="date" id="dateToFilter" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px;">
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button onclick="clearFilters()" style="width: 100%; padding: 10px 12px; background-color: #e0e0e0; border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-dark); transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#d0d0d0'" onmouseout="this.style.backgroundColor='#e0e0e0'">
                                <i class="fas fa-redo"></i> Clear Filters
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table documents-table">
                        <thead>
                            <tr>
                                <th>Document ID</th>
                                <th>Subject/Title</th>
                                <th>Sender</th>
                                <th>Description</th>
                                <th>Date Received</th>
                                <th>Classification</th>
                                <th>Sub-Classification</th>
                                <th>Prioritization</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="documentsTableBody">
                            <?php if (count($user_documents) > 0): ?>
                                <?php foreach ($user_documents as $doc): ?>
                                    <?php
                                        // Use direct columns first, fallback to JSON
                                        $additional_data = json_decode($doc['notes'], true) ?? [];
                                        $doc_id = 'DOC-' . str_pad($doc['id'], 4, '0', STR_PAD_LEFT);
                                        $sender = $doc['sender_name'] ?? $additional_data['sender'] ?? 'N/A';
                                        $date_received = $doc['date_received'] ?? $additional_data['date_received'] ?? 'N/A';
                                        $classification = $doc['classification'] ?? $additional_data['classification'] ?? 'N/A';
                                        $sub_classification = $doc['sub_classification'] ?? $additional_data['sub_classification'] ?? 'N/A';
                                        $priority = $doc['priority'] ?? $additional_data['priority'] ?? 'N/A';
                                        $filterDate = $date_received !== 'N/A' ? date('Y-m-d', strtotime($date_received)) : '';
                                        $description = $doc['description'] ?? '';
                                        $keywordSource = strtolower(trim(($doc_id ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($sender ?? '') . ' ' . ($description ?? '')));
                                        
                                        // Determine badge color based on classification
                                        $classification_class = 'badge-info';
                                        if ($classification === 'Letter') {
                                            $classification_class = 'badge-classification-letter';
                                        } elseif ($classification === 'Invitation') {
                                            $classification_class = 'badge-classification-invitation';
                                        } elseif ($classification === 'Travel-Related Communication') {
                                            $classification_class = 'badge-classification-travel';
                                        } elseif ($classification === 'Indorsement') {
                                            $classification_class = 'badge-classification-indorsement';
                                        }
                                        
                                        // Determine badge color based on priority
                                        $priority_class = 'badge-secondary';
                                        if ($priority === 'Normal') {
                                            $priority_class = 'badge-primary';
                                        } elseif ($priority === 'Urgent') {
                                            $priority_class = 'badge-warning';
                                        } elseif ($priority === 'Critical') {
                                            $priority_class = 'badge-danger';
                                        }
                                        
                                    ?>
                                    <tr data-filter-row="true" data-keywords="<?php echo htmlspecialchars($keywordSource); ?>" data-sender="<?php echo htmlspecialchars(strtolower($sender)); ?>" data-priority="<?php echo htmlspecialchars($priority); ?>" data-date="<?php echo htmlspecialchars($filterDate); ?>">
                                        <td><?php echo htmlspecialchars($doc_id); ?></td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($sender); ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 50) . (strlen($doc['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars($date_received !== 'N/A' ? date('M d, Y', strtotime($date_received)) : 'N/A'); ?></td>
                                        <td><span class="badge <?php echo $classification_class; ?>"><?php echo htmlspecialchars($classification); ?></span></td>
                                        <td><?php echo htmlspecialchars($sub_classification); ?></td>
                                        <td><span class="badge <?php echo $priority_class; ?>"><?php echo htmlspecialchars($priority); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewSavedDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr id="emptyFilterRow" style="display: none;">
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No documents created yet</td>
                                </tr>
                            <?php else: ?>
                                <tr id="emptyFilterRow">
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">No documents created yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Create Document Modal -->
        <div id="createDocumentModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add Document</h3>
                    <button class="modal-close" onclick="closeCreateDocumentModal()"><i class="fas fa-times"></i></button>
                </div>

                <form id="createDocumentForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="doc_sequence_number" value="<?php echo $next_seq ?? 1; ?>">

                        <!-- Basic Information -->
                        <div class="form-section">
                            <h4 class="section-title">Information</h4>
                            
                            <div class="form-group">
                                <label for="docSubject">Subject/Title <span style="color: red;">*</span></label>
                                <input type="text" id="docSubject" name="title" required placeholder="Enter document subject or title">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="docSender">Sender <span style="color: red;">*</span></label>
                                    <input type="text" id="docSender" name="sender" required placeholder="Enter sender name or office">
                                </div>

                                <div class="form-group">
                                    <label for="dateReceived">Date Received <span style="color: red;">*</span></label>
                                    <input type="date" id="dateReceived" name="date_received" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="docDescription">Description</label>
                                <textarea id="docDescription" name="description" rows="3" placeholder="Enter document description or content..."></textarea>
                            </div>
                        </div>

                        <!-- Classification & Priority -->
                        <div class="form-section">
                            <h4 class="section-title">Classification & Priority</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="classification">Classification <span style="color: red;">*</span></label>
                                    <select id="classification" name="classification" required onchange="updateSubClassification()">
                                        <option value="">Select Classification</option>
                                        <option value="Letter">Letter</option>
                                        <option value="Invitation">Invitation</option>
                                        <option value="Travel-Related Communication">Travel-Related Communication</option>
                                        <option value="Indorsement">Indorsement</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="subClassification">Sub-Classification <span style="color: red;">*</span></label>
                                    <select id="subClassification" name="sub_classification" required>
                                        <option value="">Select Classification First</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="priority">Prioritization <span style="color: red;">*</span></label>
                                    <select id="priority" name="priority" required>
                                        <option value="">Select Priority</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Urgent">Urgent</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="deadline">Deadline</label>
                                    <input type="date" id="deadline" name="deadline">
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="form-section" id="fileUploadSection">
                            <h4 class="section-title">Document File</h4>
                            
                            <div class="form-group">
                                <label for="documentFile">Choose File <span style="color: red;">*</span></label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 32px; margin-bottom: 12px;"></i>
                                    <p>Click to upload or drag and drop</p>
                                    <p style="font-size: 12px; color: #999;">PDF, Images (JPG, PNG, GIF, WebP, BMP, TIFF) - Max 10MB</p>
                                    <input type="file" id="documentFile" name="document_file" hidden accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff">
                                </div>
                                <div class="file-name-display" id="fileNameDisplay" style="display: none; margin-top: 12px; padding: 8px; background-color: #e8f5e9; color: #2e7d32; border-radius: 4px; font-size: 12px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeCreateDocumentModal()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Add Document</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Backdrop -->
        <div id="modalBackdrop" class="modal-backdrop" onclick="closeCreateDocumentModal()"></div>

        <!-- Document Preview Modal -->
        <div id="previewDocumentModal" class="modal">
            <div class="modal-content modal-preview">
                <div class="modal-header">
                    <h3>Document Preview</h3>
                    <button class="modal-close" onclick="closePreviewModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body" id="previewContent" style="padding: 0;">
                    <!-- Preview content will be inserted here -->
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitDocumentFromPreview()">
                        <i class="fas fa-check"></i> Confirm & Create Document
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmSubmitModal" class="modal">
            <div class="modal-content modal-confirm">
                <div class="modal-header">
                    <h3>Confirm Submission</h3>
                    <button class="modal-close" onclick="closeConfirmModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="confirm-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <h4>Are you sure you want to submit this document?</h4>
                        <p>Please verify all the information is correct before confirming.</p>
                        <div id="confirmDetails" class="confirm-details"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" onclick="finalizeSubmission()">
                        <i class="fas fa-check"></i> Yes, Submit
                    </button>
                </div>
            </div>
        </div>

        <!-- Backdrop for other modals -->
        <div id="previewBackdrop" class="modal-backdrop" onclick="closePreviewModal()"></div>
        <div id="confirmBackdrop" class="modal-backdrop" onclick="closeConfirmModal()"></div>

        <!-- Document Details Modal -->
        <div id="documentDetailsModal" class="modal">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3>Document Details & Preview</h3>
                    <button class="modal-close" onclick="closeDocumentDetailsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                        <!-- Document Details -->
                        <div id="documentDetailsModalContent"></div>

                        <!-- Document Preview -->
                        <div id="documentPreviewModalContent"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDocumentDetailsModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editDocument()">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button type="button" class="btn btn-warning" onclick="forwardDocument()" style="color: white;">
                        <i class="fas fa-arrow-right"></i> Route
                    </button>
                </div>
            </div>
        </div>

        <div id="documentDetailsBackdrop" class="modal-backdrop" onclick="closeDocumentDetailsModal()"></div>

        <!-- Forward Document Modal -->
        <div id="forwardModal" class="modal">
            <div class="modal-content modal-confirm">
                <div class="modal-header">
                    <h3>Forward To Administrative</h3>
                    <button class="modal-close" onclick="closeForwardModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="forwardDocId" value="">

                    <div class="form-group">
                        <label for="incomingTrackingCode">Administrative Tracking Code *</label>
                        <input
                            type="text"
                            id="incomingTrackingCode"
                            placeholder="Enter tracking code provided by Administrative"
                            autocomplete="off"
                        >
                        <small style="display: block; margin-top: 6px; color: var(--text-light);">
                            Enter the same tracking code from Administrative so this remains one connected transaction.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeForwardModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmForwardDocument()">
                        <i class="fas fa-paper-plane"></i> Confirm Forward
                    </button>
                </div>
            </div>
        </div>

        <div id="forwardBackdrop" class="modal-backdrop" onclick="closeForwardModal()"></div>

        <!-- Route Document Modal -->
        <div id="routeModal" class="modal">
            <div class="modal-content modal-confirm">
                <div class="modal-header">
                    <h3>Route Document to Administrative</h3>
                    <button class="modal-close" onclick="closeRouteModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="routeDocId" value="">

                    <div class="form-group">
                        <label for="routeTrackingCode">Tracking Code *</label>
                        <input
                            type="text"
                            id="routeTrackingCode"
                            placeholder="Auto-generated tracking code"
                            autocomplete="off"
                            readonly
                        >
                        <small style="display: block; margin-top: 6px; color: var(--text-light);">
                            This tracking code will be used to track this document through the administrative process.
                        </small>
                    </div>

                    <div style="background-color: #e3f2fd; padding: 12px; border-radius: 6px; border-left: 4px solid #1976d2; margin-bottom: 16px;">
                        <p style="margin: 0; color: #1976d2; font-size: 14px;">
                            <strong>This document will be sent to Administrative Incoming</strong> and will appear in your Outgoing documents.
                        </p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRouteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmRouteDocument()">
                        <i class="fas fa-check"></i> Confirm Route
                    </button>
                </div>
            </div>
        </div>

        <div id="routeBackdrop" class="modal-backdrop" onclick="closeRouteModal()"></div>

        <!-- File Viewer Modal -->
        <div id="fileViewerModal" class="modal">
            <div class="modal-content" style="max-width: 60vw; max-height: 90vh; overflow: auto;">
                <div class="modal-header">
                    <h3 id="fileViewerTitle">File Viewer</h3>
                    <button class="modal-close" onclick="closeFileViewerModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="text-align: center;">
                    <img id="fileViewerImage" src="" style="max-width: 100%; max-height: calc(90vh - 150px); object-fit: contain;" alt="Document Image">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" onclick="downloadDocumentFile()">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeFileViewerModal()">Close</button>
                </div>
            </div>
        </div>

        <div id="fileViewerBackdrop" class="modal-backdrop" onclick="closeFileViewerModal()"></div>
    </div>

    <script>
        function applyFilters() {
            const keyword = (document.getElementById('keywordFilter')?.value || '').toLowerCase();
            const sender = (document.getElementById('senderFilter')?.value || '').toLowerCase();
            const priority = document.getElementById('priorityFilter')?.value || '';
            const dateFromValue = document.getElementById('dateFromFilter')?.value || '';
            const dateToValue = document.getElementById('dateToFilter')?.value || '';
            const dateFrom = dateFromValue ? new Date(dateFromValue) : null;
            const dateTo = dateToValue ? new Date(dateToValue) : null;

            const rows = document.querySelectorAll('#documentsTableBody tr[data-filter-row="true"]');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowKeywords = row.dataset.keywords || '';
                const rowSender = row.dataset.sender || '';
                const rowPriority = row.dataset.priority || '';
                const rowDateValue = row.dataset.date || '';
                const rowDate = rowDateValue ? new Date(rowDateValue) : null;

                if (keyword && !rowKeywords.includes(keyword)) {
                    row.style.display = 'none';
                    return;
                }

                if (sender && !rowSender.includes(sender)) {
                    row.style.display = 'none';
                    return;
                }

                if (priority && rowPriority !== priority) {
                    row.style.display = 'none';
                    return;
                }

                if (dateFrom && (!rowDate || rowDate < dateFrom)) {
                    row.style.display = 'none';
                    return;
                }

                if (dateTo && rowDate) {
                    const dateToEnd = new Date(dateTo);
                    dateToEnd.setDate(dateToEnd.getDate() + 1);
                    if (rowDate >= dateToEnd) {
                        row.style.display = 'none';
                        return;
                    }
                } else if (dateTo && !rowDate) {
                    row.style.display = 'none';
                    return;
                }

                row.style.display = '';
                visibleCount += 1;
            });

            const emptyRow = document.getElementById('emptyFilterRow');
            if (emptyRow) {
                emptyRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        function clearFilters() {
            const keywordInput = document.getElementById('keywordFilter');
            const senderInput = document.getElementById('senderFilter');
            const prioritySelect = document.getElementById('priorityFilter');
            const dateFromInput = document.getElementById('dateFromFilter');
            const dateToInput = document.getElementById('dateToFilter');

            if (keywordInput) keywordInput.value = '';
            if (senderInput) senderInput.value = '';
            if (prioritySelect) prioritySelect.value = '';
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            applyFilters();
        }

        document.addEventListener('DOMContentLoaded', function() {
                        // Set max date to today using browser's local timezone
                        const dateReceivedInput = document.getElementById('dateReceived');
                        if (dateReceivedInput) {
                            const today = new Date();
                            const year = today.getFullYear();
                            const month = String(today.getMonth() + 1).padStart(2, '0');
                            const day = String(today.getDate()).padStart(2, '0');
                            const maxDate = `${year}-${month}-${day}`;
                            dateReceivedInput.setAttribute('max', maxDate);
                        }

                        // Set min date to today for deadline field (cannot pick past dates)
                        const deadlineInput = document.getElementById('deadline');
                        if (deadlineInput) {
                            const today = new Date().toISOString().split('T')[0];
                            deadlineInput.setAttribute('min', today);
                        }

                        // Form submit validation: ensure deadline (if set) is not before today
                        const createForm = document.getElementById('createDocumentForm');
                        if (createForm) {
                            createForm.addEventListener('submit', function (e) {
                                const dl = document.getElementById('deadline');
                                if (dl && dl.value) {
                                    const selected = new Date(dl.value);
                                    const todayDate = new Date();
                                    // zero time portion for comparison
                                    todayDate.setHours(0,0,0,0);
                                    selected.setHours(0,0,0,0);
                                    if (selected < todayDate) {
                                        e.preventDefault();
                                        alert('Deadline cannot be before today. Please choose today or a future date.');
                                        dl.focus();
                                        return false;
                                    }
                                }
                            });
                        }

            document.getElementById('keywordFilter')?.addEventListener('input', applyFilters);
            document.getElementById('senderFilter')?.addEventListener('input', applyFilters);
            document.getElementById('priorityFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateFromFilter')?.addEventListener('change', applyFilters);
            document.getElementById('dateToFilter')?.addEventListener('change', applyFilters);
            applyFilters();
        });
    </script>
    <script src="script.js"></script>
    <script src="js/notifications.js"></script>
    <script src="documententry.js?v=2"></script>
</body>
</html>
