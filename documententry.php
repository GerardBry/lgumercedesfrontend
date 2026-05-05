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

// Fetch documents created by this user (saved in document entry)
$user_documents = [];
$sql = "SELECT 
        d.id,
        d.tracking_number,
        d.title,
        d.description,
        d.document_type,
        d.date_sent,
        d.created_at,
        d.status
    FROM documents d
    WHERE d.created_by = ? 
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
                            <h2>Document Entry</h2>
                            <p>Manage and Create Documents</p>
                        </div>
                        <button class="btn btn-primary" onclick="openCreateDocumentModal()">
                            <i class="fas fa-plus"></i> Create Document
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table documents-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date Created</th>
                                <th>Document Title</th>
                                <th>Date Issued</th>
                                <th>Description</th>
                                <th>Document Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="documentsTableBody">
                            <?php if (count($user_documents) > 0): ?>
                                <?php foreach ($user_documents as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['id']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($doc['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo $doc['date_sent'] ? date('M d, Y', strtotime($doc['date_sent'])) : '-'; ?></td>
                                        <td><?php echo substr(htmlspecialchars($doc['description'] ?? ''), 0, 50) . (strlen($doc['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($doc['document_type']); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewSavedDocument(<?php echo $doc['id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="forwardSavedDocument(<?php echo $doc['id']; ?>)" title="Forward">
                                                <i class="fas fa-arrow-right"></i> Forward
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">No documents created yet</td>
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
                    <h3>Create New Travel Request</h3>
                    <button class="modal-close" onclick="closeCreateDocumentModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="createDocumentForm" onsubmit="handlePreviewDocument(event)">
                    <div class="modal-body">
                        <!-- Generic Fields (Always Visible) -->
                        <div class="form-section">
                            <h4 class="section-title">Document Information</h4>
                            
                            <div class="form-group">
                                <label for="modalDocTitle">Document Title *</label>
                                <input type="text" id="modalDocTitle" name="title" required placeholder="Enter document title">
                            </div>

                            <div class="form-group">
                                <label for="modalDocType">Request Type *</label>
                                <select id="modalDocType" name="type" required onchange="updateDynamicForm()">
                                    <option value="">Select request type</option>
                                    <option value="Travel Request">Travel Request</option>
                                    <option value="Executive Order">Executive Order</option>
                                    <option value="Office Order">Office Order</option>
                                </select>
                            </div>
                        </div>

                        <!-- Travel Request Form -->
                        <div id="travelOrderForm" class="dynamic-form-section" style="display: none;">
                            <div class="form-section">
                                <h4 class="section-title">Travel Request Details</h4>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="toNumber">Travel Request Number *</label>
                                        <input type="text" id="toNumber" placeholder="Auto-generated" readonly style="background-color: #f0f0f0; cursor: not-allowed;">
                                    </div>
                                    <div class="form-group">
                                        <label for="toDate">Date Issued *</label>
                                        <input type="date" id="toDate" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Personnel to Travel *</label>
                                    <div id="personnelList">
                                        <div class="multi-entry-item">
                                            <input type="text" placeholder="Name of Employee / Personnel" class="traveler-name" required>
                                            <input type="text" placeholder="Position/Designation" class="traveler-position" required>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="removePersonnel(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addPersonnel()">
                                        <i class="fas fa-plus"></i> Add Personnel
                                    </button>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="toFrom">FROM (Approving Authority) *</label>
                                        <input type="text" id="toFrom" placeholder="e.g., Alexander Pajarillo, Municipal Mayor" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="toSubject">SUBJECT *</label>
                                        <input type="text" id="toSubject" placeholder="e.g., As Stated" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="travelPurpose">Purpose / Details of Travel *</label>
                                    <textarea id="travelPurpose" placeholder="Enter purpose of travel" rows="3" required></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="travelDestination">Destination *</label>
                                        <input type="text" id="travelDestination" placeholder="e.g., Legazpi City" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="travelStartDate">Start Date *</label>
                                        <input type="date" id="travelStartDate" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="travelEndDate">End Date *</label>
                                        <input type="date" id="travelEndDate" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="travelDuration">Duration (days) *</label>
                                        <input type="number" id="travelDuration" min="1" placeholder="e.g., 2" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="travelMode">Mode of Transportation *</label>
                                        <select id="travelMode" required>
                                            <option value="">Select mode</option>
                                            <option value="Land">Land</option>
                                            <option value="Air">Air</option>
                                            <option value="Sea">Sea</option>
                                            <option value="Mixed">Mixed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Executive Order Form -->
                        <div id="executiveOrderForm" class="dynamic-form-section" style="display: none;">
                            <div class="form-section">
                                <h4 class="section-title">Executive Order Details</h4>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="eoNumber">Order Number *</label>
                                        <input type="text" id="eoNumber" placeholder="e.g., EO-2026-001" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="eoTitle">Title of Executive Order *</label>
                                        <input type="text" id="eoTitle" placeholder="Enter order title" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="eoLegalBasis">Legal Basis *</label>
                                    <input type="text" id="eoLegalBasis" placeholder="e.g., Chapter 2, Article 5 of the Municipal Code" required>
                                </div>

                                <div class="form-group">
                                    <label for="eoDescription">Description / Content *</label>
                                    <textarea id="eoDescription" rows="5" placeholder="Enter the full content or description of the executive order" required></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="eoDateIssued">Date Issued *</label>
                                        <input type="date" id="eoDateIssued" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="eoSignatory">Signatory (Mayor, Official) *</label>
                                        <input type="text" id="eoSignatory" placeholder="Name of signatory" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Office Order Form -->
                        <div id="officeOrderForm" class="dynamic-form-section" style="display: none;">
                            <div class="form-section">
                                <h4 class="section-title">Office Order Details</h4>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ooNumber">Office Order Number *</label>
                                        <input type="text" id="ooNumber" placeholder="e.g., OO-2026-001" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="ooDate">Effectivity Date *</label>
                                        <input type="date" id="ooDate" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Assigned Personnel *</label>
                                    <div id="assignedPersonnelList">
                                        <div class="multi-entry-item">
                                            <input type="text" placeholder="Name of Personnel" class="assigned-personnel" required>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="removeAssignedPersonnel(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addAssignedPersonnel()">
                                        <i class="fas fa-plus"></i> Add Personnel
                                    </button>
                                </div>

                                <div class="form-group">
                                    <label for="ooTask">Task / Instruction *</label>
                                    <textarea id="ooTask" rows="4" placeholder="Enter the task or instruction" required></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="ooDepartment">Department *</label>
                                        <input type="text" id="ooDepartment" placeholder="e.g., Administration, Finance" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="ooRemarks">Remarks</label>
                                        <input type="text" id="ooRemarks" placeholder="Optional remarks or notes">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeCreateDocumentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="previewDocument()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
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
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                        <!-- Left side: Document Details -->
                        <div id="documentDetailsModalContent">
                            <!-- Details will be inserted here -->
                        </div>
                        
                        <!-- Right side: Document Preview -->
                        <div id="documentPreviewModalContent" style="border-left: 1px solid #ddd; padding-left: 20px;">
                            <!-- Preview will be inserted here -->
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDocumentDetailsModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="editDocument()">
                        <i class="fas fa-edit"></i> Edit
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
    </div>

    <script src="script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>
