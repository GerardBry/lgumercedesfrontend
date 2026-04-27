<?php
/**
 * Administrative Document Entry Page
 */
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrative Assistant') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'User';

require_once '../config/db_connect.php';

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
    $stmt->bind_param('i', $user_id);
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
    <title>Document Entry - Administrative Panel</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
        }

        .admin-sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow-y: auto;
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .admin-sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .admin-sidebar-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .admin-nav-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .admin-nav-menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .admin-nav-menu li {
            margin: 4px 0;
            padding: 0 12px;
        }

        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .admin-nav-item i {
            width: 20px;
            text-align: center;
        }

        .admin-nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .admin-nav-item.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(255, 149, 0, 0.3);
        }

        .admin-sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 600;
        }

        .admin-user-name {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .admin-user-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin: 2px 0 0 0;
        }

        .logout-btn {
            background-color: #e0e0e0;
            color: var(--text-dark);
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background-color: #d0d0d0;
        }

        .admin-main-content {
            flex: 1;
            margin-left: 280px;
            background-color: var(--bg-light);
            min-height: 100vh;
        }

        .admin-page {
            padding: 40px;
        }

        .page-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .page-header-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .page-header-info p {
            font-size: 14px;
            color: var(--text-light);
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-info {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .btn-info:hover {
            background-color: #bbdefb;
        }

        .btn-print {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .btn-print:hover {
            background-color: #ffe0b2;
        }

        .modal-content.modal-preview {
            max-width: 980px;
        }

        .modal-content.modal-large {
            max-width: 1100px;
        }

        .print-shell {
            padding: 24px;
            font-family: Georgia, serif;
            color: #1c1c1c;
        }

        .forward-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .admin-main-content {
                margin-left: 0;
            }

            .admin-page {
                padding: 20px;
            }

            .forward-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-logo-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h1>Administrative Panel</h1>
            </div>

            <nav class="admin-nav-menu">
                <ul>
                    <li>
                        <a href="admin-dashboard-staff.php" class="admin-nav-item">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="trackdocument.php" class="admin-nav-item">
                            <i class="fas fa-search"></i>
                            <span>Track Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="documententry.php" class="admin-nav-item active">
                            <i class="fas fa-file-upload"></i>
                            <span>Document Entry</span>
                        </a>
                    </li>
                    <li>
                        <a href="assign-document.php" class="admin-nav-item">
                            <i class="fas fa-file-export"></i>
                            <span>Assign Documents</span>
                        </a>
                    </li>
                    <li>
                        <a href="incoming.php" class="admin-nav-item">
                            <i class="fas fa-inbox"></i>
                            <span>Incoming</span>
                        </a>
                    </li>
                    <li>
                        <a href="outgoing.php" class="admin-nav-item">
                            <i class="fas fa-paper-plane"></i>
                            <span>Outgoing</span>
                        </a>
                    </li>
                    <li>
                        <a href="received.php" class="admin-nav-item">
                            <i class="fas fa-envelope-open"></i>
                            <span>Received</span>
                        </a>
                    </li>
                    <li>
                        <a href="returned.php" class="admin-nav-item">
                            <i class="fas fa-undo"></i>
                            <span>Returned</span>
                        </a>
                    </li>
                    <li>
                        <a href="finished.php" class="admin-nav-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Finished</span>
                        </a>
                    </li>
                    <li>
                        <a href="archive.php" class="admin-nav-item">
                            <i class="fas fa-archive"></i>
                            <span>Archive</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-user-profile">
                    <div class="admin-avatar"><?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?></div>
                    <div class="admin-user-info">
                        <p class="admin-user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></p>
                        <p class="admin-user-role"><?php echo htmlspecialchars($role); ?></p>
                    </div>
                </div>
                <button class="logout-btn" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>

        <main class="admin-main-content">
            <div class="admin-page">
                <div class="page-header">
                    <div class="page-header-info">
                        <h2>Document Entry</h2>
                        <p>Create travel orders, executive orders, and office orders.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openCreateDocumentModal()">
                        <i class="fas fa-plus"></i> Create Document
                    </button>
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
                                            <button class="btn btn-sm btn-info" onclick="viewSavedDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No documents created yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="createDocumentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Document</h3>
                <button class="modal-close" onclick="closeCreateDocumentModal()"><i class="fas fa-times"></i></button>
            </div>

            <form id="createDocumentForm" onsubmit="handlePreviewDocument(event)">
                <div class="modal-body">
                    <div class="form-section">
                        <h4 class="section-title">Document Information</h4>

                        <div class="form-group">
                            <label for="modalDocTitle">Document Title *</label>
                            <input type="text" id="modalDocTitle" name="title" required placeholder="Enter document title">
                        </div>

                        <div class="form-group">
                            <label for="modalDocType">Document Type *</label>
                            <select id="modalDocType" name="type" required onchange="updateDynamicForm()">
                                <option value="">Select type</option>
                                <option value="Travel Order">Travel Order</option>
                                <option value="Executive Order">Executive Order</option>
                                <option value="Office Order">Office Order</option>
                            </select>
                        </div>
                    </div>

                    <div id="travelOrderForm" class="dynamic-form-section" style="display: none;">
                        <div class="form-section">
                            <h4 class="section-title">Travel Order Details</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="toNumber">Travel Order Number *</label>
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
                                    <label for="toFrom">FROM *</label>
                                    <input type="text" id="toFrom" required>
                                </div>
                                <div class="form-group">
                                    <label for="toSubject">SUBJECT *</label>
                                    <input type="text" id="toSubject" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="travelPurpose">Purpose *</label>
                                <textarea id="travelPurpose" rows="3" required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="travelDestination">Destination *</label>
                                    <input type="text" id="travelDestination" required>
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
                                    <input type="number" id="travelDuration" min="1" required>
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

                    <div id="executiveOrderForm" class="dynamic-form-section" style="display: none;">
                        <div class="form-section">
                            <h4 class="section-title">Executive Order Details</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="eoNumber">Order Number *</label>
                                    <input type="text" id="eoNumber" required>
                                </div>
                                <div class="form-group">
                                    <label for="eoTitle">Title *</label>
                                    <input type="text" id="eoTitle" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="eoLegalBasis">Legal Basis *</label>
                                <input type="text" id="eoLegalBasis" required>
                            </div>
                            <div class="form-group">
                                <label for="eoDescription">Description / Content *</label>
                                <textarea id="eoDescription" rows="5" required></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="eoDateIssued">Date Issued *</label>
                                    <input type="date" id="eoDateIssued" required>
                                </div>
                                <div class="form-group">
                                    <label for="eoSignatory">Signatory *</label>
                                    <input type="text" id="eoSignatory" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="officeOrderForm" class="dynamic-form-section" style="display: none;">
                        <div class="form-section">
                            <h4 class="section-title">Office Order Details</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ooNumber">Office Order Number *</label>
                                    <input type="text" id="ooNumber" required>
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
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="removeAssignedPersonnel(this)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addAssignedPersonnel()"><i class="fas fa-plus"></i> Add Personnel</button>
                            </div>

                            <div class="form-group">
                                <label for="ooTask">Task / Instruction *</label>
                                <textarea id="ooTask" rows="4" required></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="ooDepartment">Department *</label>
                                    <input type="text" id="ooDepartment" required>
                                </div>
                                <div class="form-group">
                                    <label for="ooRemarks">Remarks</label>
                                    <input type="text" id="ooRemarks">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateDocumentModal()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="previewDocument()"><i class="fas fa-eye"></i> Preview</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalBackdrop" class="modal-backdrop" onclick="closeCreateDocumentModal()"></div>

    <div id="previewDocumentModal" class="modal">
        <div class="modal-content modal-preview">
            <div class="modal-header">
                <h3>Document Preview</h3>
                <button class="modal-close" onclick="closePreviewModal()"><i class="fas fa-times"></i></button>
            </div>

            <div class="modal-body" id="previewContent" style="padding: 0;"></div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePreviewModal()"><i class="fas fa-times"></i> Close</button>
                <button type="button" class="btn btn-print" onclick="printPreviewDocument()"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="btn btn-primary" onclick="submitDocumentFromPreview()"><i class="fas fa-check"></i> Confirm & Save</button>
            </div>
        </div>
    </div>

    <div id="previewBackdrop" class="modal-backdrop" onclick="closePreviewModal()"></div>

    <div id="documentDetailsModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Document Details & Preview</h3>
                <button class="modal-close" onclick="closeDocumentDetailsModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-height: 600px; overflow-y: auto;">
                    <div id="documentDetailsModalContent"></div>
                    <div id="documentPreviewModalContent" style="border-left: 1px solid #ddd; padding-left: 20px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDocumentDetailsModal()"><i class="fas fa-times"></i> Close</button>
                <button type="button" class="btn btn-print" onclick="printCurrentDetails()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <div id="documentDetailsBackdrop" class="modal-backdrop" onclick="closeDocumentDetailsModal()"></div>

    <script src="documententry.js"></script>
</body>
</html>
