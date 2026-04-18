// ==========================================
// LGU Admin Dashboard - JavaScript
// ==========================================

// Initialize the dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // No authentication check - allow direct access to dashboard for frontend development
    
    const userName = sessionStorage.getItem('userName') || sessionStorage.getItem('userEmail') || 'Guest User';
    const userNameDisplay = document.getElementById('userNameDisplay');
    if (userNameDisplay) {
        userNameDisplay.textContent = userName;
    }
    
    initializeNavigation();
    initializeForm();
    setupFileUpload();
    initializeSearch();
});

// ==========================================
// AUTHENTICATION FUNCTIONS
// ==========================================
// Authentication is now handled in separate login.html and register.html files
// Only handleLogout() is needed here for the dashboard

function handleLogout() {
    // Clear session storage
    sessionStorage.clear();
    
    showNotification('Logged out successfully', 'info');
    
    // Redirect to login
    setTimeout(() => {
        window.location.href = 'login.html';
    }, 800);
}


// ==========================================
// DOCUMENT ENTRY MODAL FUNCTIONALITY
// ==========================================

function openCreateDocumentModal() {
    const modal = document.getElementById('createDocumentModal');
    const backdrop = document.getElementById('modalBackdrop');
    
    modal.classList.add('active');
    backdrop.classList.add('active');
    
    // Reset form
    resetDocumentForm();
    
    // Focus on first input
    setTimeout(() => {
        document.getElementById('modalDocTitle').focus();
    }, 100);
}

function closeCreateDocumentModal() {
    const modal = document.getElementById('createDocumentModal');
    const backdrop = document.getElementById('modalBackdrop');
    
    modal.classList.remove('active');
    backdrop.classList.remove('active');
    
    // Reset form
    resetDocumentForm();
}

function resetDocumentForm() {
    document.getElementById('createDocumentForm').reset();
    document.getElementById('modalDocType').value = '';
    hideDynamicForms();
}

// ==========================================
// DYNAMIC FORM SWITCHING
// ==========================================

function updateDynamicForm() {
    const docType = document.getElementById('modalDocType').value;
    const travelOrderForm = document.getElementById('travelOrderForm');
    const executiveOrderForm = document.getElementById('executiveOrderForm');
    const officeOrderForm = document.getElementById('officeOrderForm');
    
    // Hide all dynamic forms
    travelOrderForm.style.display = 'none';
    executiveOrderForm.style.display = 'none';
    officeOrderForm.style.display = 'none';
    
    // Show selected form
    if (docType === 'Travel Order') {
        travelOrderForm.style.display = 'block';
        // Initialize single personnel entry
        initializePersonnelList();
        // Auto-generate Travel Order Number
        generateTravelOrderNumber();
    } else if (docType === 'Executive Order') {
        executiveOrderForm.style.display = 'block';
    } else if (docType === 'Office Order') {
        officeOrderForm.style.display = 'block';
        // Initialize single personnel entry
        initializeAssignedPersonnelList();
    }
}

// ==========================================
// AUTO-GENERATE TRAVEL ORDER NUMBER
// ==========================================

function generateTravelOrderNumber() {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = String(today.getFullYear()).slice(-2);
    const random = String(Math.floor(Math.random() * 9000) + 1000);
    
    const toNumber = `${day}${month}${year}-${random}`;
    document.getElementById('toNumber').value = toNumber;
}

function hideDynamicForms() {
    document.getElementById('travelOrderForm').style.display = 'none';
    document.getElementById('executiveOrderForm').style.display = 'none';
    document.getElementById('officeOrderForm').style.display = 'none';
}

// ==========================================
// PERSONNEL MANAGEMENT (Travel Order)
// ==========================================

function initializePersonnelList() {
    const personnelList = document.getElementById('personnelList');
    if (personnelList.children.length === 0) {
        const item = document.createElement('div');
        item.className = 'multi-entry-item';
        item.innerHTML = `
            <input type="text" placeholder="Name of Employee / Personnel" class="traveler-name" required>
            <input type="text" placeholder="Position/Designation" class="traveler-position" required>
            <button type="button" class="btn btn-sm btn-secondary" onclick="removePersonnel(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        personnelList.appendChild(item);
    }
}

function addPersonnel() {
    const personnelList = document.getElementById('personnelList');
    const item = document.createElement('div');
    item.className = 'multi-entry-item';
    item.innerHTML = `
        <input type="text" placeholder="Name of Employee / Personnel" class="traveler-name" required>
        <input type="text" placeholder="Position/Designation" class="traveler-position" required>
        <button type="button" class="btn btn-sm btn-secondary" onclick="removePersonnel(this)">
            <i class="fas fa-trash"></i>
        </button>
    `;
    personnelList.appendChild(item);
}

function removePersonnel(button) {
    const personnelList = document.getElementById('personnelList');
    if (personnelList.children.length > 1) {
        button.closest('.multi-entry-item').remove();
    } else {
        showNotification('At least one personnel is required', 'warning');
    }
}

// ==========================================
// PERSONNEL MANAGEMENT (Office Order)
// ==========================================

function initializeAssignedPersonnelList() {
    const assignedPersonnelList = document.getElementById('assignedPersonnelList');
    if (assignedPersonnelList.children.length === 0) {
        const item = document.createElement('div');
        item.className = 'multi-entry-item';
        item.innerHTML = `
            <input type="text" placeholder="Name of Personnel" class="assigned-personnel" required>
            <button type="button" class="btn btn-sm btn-secondary" onclick="removeAssignedPersonnel(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        assignedPersonnelList.appendChild(item);
    }
}

function addAssignedPersonnel() {
    const assignedPersonnelList = document.getElementById('assignedPersonnelList');
    const item = document.createElement('div');
    item.className = 'multi-entry-item';
    item.innerHTML = `
        <input type="text" placeholder="Name of Personnel" class="assigned-personnel" required>
        <button type="button" class="btn btn-sm btn-secondary" onclick="removeAssignedPersonnel(this)">
            <i class="fas fa-trash"></i>
        </button>
    `;
    assignedPersonnelList.appendChild(item);
}

function removeAssignedPersonnel(button) {
    const assignedPersonnelList = document.getElementById('assignedPersonnelList');
    if (assignedPersonnelList.children.length > 1) {
        button.closest('.multi-entry-item').remove();
    } else {
        showNotification('At least one personnel is required', 'warning');
    }
}

function handlePreviewDocument(e) {
    e.preventDefault();
    previewDocument();
}

// Store current document data for submission
let currentDocumentData = null;

function previewDocument() {
    const docType = document.getElementById('modalDocType').value;
    const title = document.getElementById('modalDocTitle').value;
    
    // Validate
    if (!docType || !title) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Collect data
    currentDocumentData = collectDocumentData(docType);
    
    // Generate preview HTML
    let previewHTML = '';
    
    if (docType === 'Travel Order') {
        previewHTML = generateTravelOrderPreview(currentDocumentData);
    } else if (docType === 'Executive Order') {
        previewHTML = generateExecutiveOrderPreview(currentDocumentData);
    } else if (docType === 'Office Order') {
        previewHTML = generateOfficeOrderPreview(currentDocumentData);
    }
    
    // Show preview modal
    document.getElementById('previewContent').innerHTML = previewHTML;
    document.getElementById('previewDocumentModal').classList.add('active');
    document.getElementById('previewBackdrop').classList.add('active');
    document.getElementById('createDocumentModal').style.zIndex = '1998';
}

function generateTravelOrderPreview(data) {
    const travelers = Array.from(document.querySelectorAll('.traveler-name'))
        .map((el, idx) => {
            const position = document.querySelectorAll('.traveler-position')[idx].value;
            return `${el.value}, ${position}`;
        }).join('<br/>');
    
    return `
        <div class="document-preview">
            <div class="document-header">
                <div class="doc-municipality">Province of Camarines Norte</div>
                <div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                <div class="doc-title">TRAVEL ORDER</div>
                <div class="doc-number">No. ${data.orderNumber || 'TBD'}</div>
            </div>
            
            <div class="document-body">
                <div class="doc-line">
                    <div class="doc-label">TO</div>
                    <div class="doc-content">
                        <div class="doc-list">${travelers}</div>
                    </div>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">FROM</div>
                    <div class="doc-content">${data.from || 'Municipal Mayor'}</div>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">DATE</div>
                    <div class="doc-content">${formatDateLong(data.dateIssued)}</div>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">SUBJECT</div>
                    <div class="doc-content">${data.subject || 'As Stated'}</div>
                </div>
                
                <div class="doc-paragraph">
                    You are hereby directed to attend ${data.purpose}.
                </div>
                
                <div class="doc-paragraph">
                    <strong>Destination:</strong> ${data.destination}<br>
                    <strong>Travel Dates:</strong> ${formatDateShort(data.startDate)} to ${formatDateShort(data.endDate)}<br>
                    <strong>Duration:</strong> ${data.duration} day(s)<br>
                    <strong>Mode of Transportation:</strong> ${data.mode}
                </div>
            </div>
        </div>
    `;
}

function generateExecutiveOrderPreview(data) {
    return `
        <div class="document-preview">
            <div class="document-header">
                <div class="doc-municipality">Province of Camarines Norte</div>
                <div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                <div class="doc-title">EXECUTIVE ORDER</div>
                <div class="doc-number">No. ${data.orderNumber || 'TBD'}</div>
            </div>
            
            <div class="document-body">
                <div class="doc-line">
                    <div class="doc-label">DATE</div>
                    <div class="doc-content">${formatDateLong(data.eoDateIssued)}</div>
                </div>
                
                <div class="doc-paragraph" style="margin-top: 30px;">
                    <strong>${data.eoTitle}</strong>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">WHEREAS:</div>
                    <div class="doc-content">${data.legalBasis}</div>
                </div>
                
                <div class="doc-paragraph">
                    ${data.description}
                </div>
                
                <div class="doc-paragraph">
                    <strong>Signed this ${formatDateLong(data.eoDateIssued)}</strong>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">BY</div>
                    <div class="doc-content">${data.signatory || 'Municipal Mayor'}</div>
                </div>
            </div>
        </div>
    `;
}

function generateOfficeOrderPreview(data) {
    const personnel = Array.from(document.querySelectorAll('.assigned-personnel')).map(el => el.value).join(', ');
    
    return `
        <div class="document-preview">
            <div class="document-header">
                <div class="doc-municipality">Province of Camarines Norte</div>
                <div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>
                <div class="doc-title">OFFICE ORDER</div>
                <div class="doc-number">No. ${data.orderNumber || 'TBD'}</div>
            </div>
            
            <div class="document-body">
                <div class="doc-line">
                    <div class="doc-label">DATE</div>
                    <div class="doc-content">${formatDateLong(data.effectivityDate)}</div>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">TO</div>
                    <div class="doc-content">${personnel}</div>
                </div>
                
                <div class="doc-line">
                    <div class="doc-label">DEPARTMENT</div>
                    <div class="doc-content">${data.department}</div>
                </div>
                
                <div class="doc-paragraph">
                    <strong>TASK/INSTRUCTION:</strong><br>
                    ${data.task}
                </div>
                
                <div class="doc-paragraph">
                    <strong>Effectivity:</strong> ${formatDateLong(data.effectivityDate)}
                </div>
                
                ${data.remarks ? `<div class="doc-paragraph"><strong>Remarks:</strong> ${data.remarks}</div>` : ''}
            </div>
        </div>
    `;
}

function formatDateLong(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function formatDateShort(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function closePreviewModal() {
    document.getElementById('previewDocumentModal').classList.remove('active');
    document.getElementById('previewBackdrop').classList.remove('active');
    document.getElementById('createDocumentModal').style.zIndex = '2000';
}

function submitDocumentFromPreview() {
    closePreviewModal();
    showConfirmation(currentDocumentData);
}

function showConfirmation(docData) {
    // Build confirmation details
    const details = `
        <div><strong>Document Type:</strong> ${docData.type}</div>
        <div><strong>Title:</strong> ${document.getElementById('modalDocTitle').value}</div>
        ${docData.type === 'Travel Order' ? `<div><strong>Destination:</strong> ${docData.destination}</div>` : ''}
        ${docData.type === 'Travel Order' ? `<div><strong>Travelers:</strong> ${docData.travelers.join(', ')}</div>` : ''}
        ${docData.type === 'Executive Order' ? `<div><strong>Order Number:</strong> ${docData.orderNumber}</div>` : ''}
        ${docData.type === 'Office Order' ? `<div><strong>Order Number:</strong> ${docData.orderNumber}</div>` : ''}
    `;
    
    document.getElementById('confirmDetails').innerHTML = details;
    document.getElementById('confirmSubmitModal').classList.add('active');
    document.getElementById('confirmBackdrop').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmSubmitModal').classList.remove('active');
    document.getElementById('confirmBackdrop').classList.remove('active');
}

function finalizeSubmission() {
    handleCreateDocument(new Event('submit'));
    closeConfirmModal();
}



function collectDocumentData(docType) {
    const title = document.getElementById('modalDocTitle').value;
    
    let data = {
        title: title,
        type: docType,
        dateCreated: formatDate(new Date()),
        dateIssued: formatDate(new Date(Date.now() + 86400000))
    };
    
    if (docType === 'Travel Order') {
        const travelers = Array.from(document.querySelectorAll('.traveler-name')).map(el => el.value);
        data.travelers = travelers;
        data.orderNumber = document.getElementById('toNumber').value;
        data.dateIssued = document.getElementById('toDate').value;
        data.destination = document.getElementById('travelDestination').value;
        data.purpose = document.getElementById('travelPurpose').value;
        data.startDate = document.getElementById('travelStartDate').value;
        data.endDate = document.getElementById('travelEndDate').value;
        data.duration = document.getElementById('travelDuration').value;
        data.mode = document.getElementById('travelMode').value;
        data.from = document.getElementById('toFrom').value;
        data.subject = document.getElementById('toSubject').value;
    } else if (docType === 'Executive Order') {
        data.orderNumber = document.getElementById('eoNumber').value;
        data.eoTitle = document.getElementById('eoTitle').value;
        data.legalBasis = document.getElementById('eoLegalBasis').value;
        data.description = document.getElementById('eoDescription').value;
        data.dateIssued = document.getElementById('eoDateIssued').value;
        data.signatory = document.getElementById('eoSignatory').value;
    } else if (docType === 'Office Order') {
        data.orderNumber = document.getElementById('ooNumber').value;
        data.effectivityDate = document.getElementById('ooDate').value;
        const assignedPersonnel = Array.from(document.querySelectorAll('.assigned-personnel')).map(el => el.value);
        data.assignedPersonnel = assignedPersonnel;
        data.task = document.getElementById('ooTask').value;
        data.department = document.getElementById('ooDepartment').value;
        data.remarks = document.getElementById('ooRemarks').value;
    }
    
    return data;
}

// ==========================================
// HANDLE DOCUMENT CREATION
// ==========================================

function handleCreateDocument(e) {
    if (e && e.preventDefault) {
        e.preventDefault();
    }
    
    const docType = document.getElementById('modalDocType').value;
    const title = document.getElementById('modalDocTitle').value;
    
    // Validate
    if (!docType || !title) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Collect data
    const docData = collectDocumentData(docType);
    
    // Generate document code
    let docCode = '';
    if (docType === 'Travel Order') {
        docCode = document.getElementById('toNumber').value || generateDocCode(docType);
    } else if (docType === 'Executive Order') {
        docCode = document.getElementById('eoNumber').value || generateDocCode(docType);
    } else if (docType === 'Office Order') {
        docCode = document.getElementById('ooNumber').value || generateDocCode(docType);
    }
    
    const docID = generateDocId();
    
    // Create new row
    const tableBody = document.getElementById('documentsTableBody');
    const newRow = document.createElement('tr');
    
    // Determine badge color based on type
    let badgeClass = 'badge-info';
    if (docType === 'Executive Order') {
        badgeClass = 'badge-warning';
    } else if (docType === 'Office Order') {
        badgeClass = 'badge-success';
    }
    
    // Create brief description
    let description = '';
    if (docType === 'Travel Order') {
        description = `Travel to ${docData.destination} - ${docData.travelers.join(', ')}`;
    } else if (docType === 'Executive Order') {
        description = docData.eoTitle || title;
    } else if (docType === 'Office Order') {
        const personnel = Array.from(document.querySelectorAll('.assigned-personnel')).map(el => el.value).join(', ');
        description = `${personnel} - ${docData.department}`;
    }
    
    const displayDate = docData.dateIssued || docData.eoDateIssued || docData.effectivityDate || formatDate(new Date());
    
    newRow.innerHTML = `
        <td>${docID}</td>
        <td>${docData.dateCreated}</td>
        <td>${title}</td>
        <td>${displayDate}</td>
        <td>${docCode}</td>
        <td>${description}</td>
        <td><span class="badge ${badgeClass}">${docType}</span></td>
        <td>
            <button class="btn btn-sm btn-info" onclick="viewDocument('${docID}', '${btoa(JSON.stringify(docData))}')">
                <i class="fas fa-eye"></i> View
            </button>
        </td>
    `;
    
    // Add to beginning of table (newest first)
    if (tableBody.firstChild) {
        tableBody.insertBefore(newRow, tableBody.firstChild);
    } else {
        tableBody.appendChild(newRow);
    }
    
    // Close modal
    closeCreateDocumentModal();
    
    // Show success message
    showNotification(`Document "${title}" created successfully! ID: ${docID}`, 'success');
}

function generateDocId() {
    const num = Math.floor(Math.random() * 900) + 100;
    return `DOC-${num}`;
}

function generateDocCode(docType) {
    const year = new Date().getFullYear();
    const num = Math.floor(Math.random() * 9000) + 1000;
    
    let prefix = 'TO'; // Travel Order
    if (docType === 'Executive Order') {
        prefix = 'EO';
    } else if (docType === 'Office Order') {
        prefix = 'OO';
    }
    
    return `${prefix}-${year}-${num}`;
}

function formatDate(date) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function viewDocument(docID, docDataEncoded) {
    showNotification(`Viewing document: ${docID}`, 'info');
    // In a real application, this would display a preview of the document
}

// ==========================================
// MODAL CLOSE ON ESC KEY
// ========================================== 

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('createDocumentModal');
        if (modal.classList.contains('active')) {
            closeCreateDocumentModal();
        }
    }
});



function initializeNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    navItems.forEach(item => {
        // Get the href attribute
        const href = item.getAttribute('href');
        
        // Mark the active nav item based on current page
        if (href === currentPage || (href === 'index.html' && currentPage === '')) {
            item.classList.add('active');
        }
        
        // Allow natural navigation via href
        // Don't prevent default - let links work normally
    });
}

// ==========================================
// TRACKING FUNCTIONALITY
// ==========================================

function initializeSearch() {
    const trackingInput = document.getElementById('trackingInput');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    
    // Add event listeners for real-time search
    if (trackingInput) {
        trackingInput.addEventListener('input', handleTracking);
        trackingInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleTracking();
            }
        });
    }
    
    // Add event listeners for filter dropdowns
    if (statusFilter) {
        statusFilter.addEventListener('change', handleTracking);
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', handleTracking);
    }
}

function handleTracking() {
    const trackingInput = document.getElementById('trackingInput').value.trim().toLowerCase();
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const typeFilter = document.getElementById('typeFilter')?.value || '';
    const tableBody = document.querySelector('.data-table tbody');
    
    if (!tableBody) return; // If no table found, skip
    
    const rows = tableBody.querySelectorAll('tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const docId = row.cells[0]?.textContent.trim().toLowerCase() || '';
        const title = row.cells[1]?.textContent.trim().toLowerCase() || '';
        const code = row.cells[2]?.textContent.trim().toLowerCase() || '';
        const status = row.cells[3]?.textContent.trim().toLowerCase() || '';
        const type = row.cells[4]?.textContent.trim().toLowerCase() || '';
        
        // Check if row matches search and filter criteria
        const matchesSearch = !trackingInput || 
                             docId.includes(trackingInput) || 
                             title.includes(trackingInput) || 
                             code.includes(trackingInput);
        
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        const matchesType = !typeFilter || type.includes(typeFilter);
        
        if (matchesSearch && matchesStatus && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (visibleCount === 0 && trackingInput) {
        showNotification(`No documents found matching "${trackingInput}"`, 'info');
    } else if (visibleCount > 0) {
        showNotification(`Found ${visibleCount} document(s)`, 'success');
    }
}

// ==========================================
// FORM HANDLING
// ==========================================

function initializeForm() {
    const form = document.querySelector('.form-container');
    if (form) {
        // Form submission is already handled by onsubmit
        // This could be expanded for more complex validation
    }
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const title = document.getElementById('docTitle').value;
    const type = document.getElementById('docType').value;
    const description = document.getElementById('docDescription').value;
    const applicant = document.getElementById('applicant').value;
    const email = document.getElementById('email').value;
    
    // Validation
    if (!title || !type || !description || !applicant || !email) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Simulate form submission
    const formData = {
        documentId: generateDocumentId(),
        title: title,
        type: type,
        description: description,
        applicant: applicant,
        email: email,
        submittedAt: new Date().toLocaleString(),
        status: 'Received'
    };
    
    // Log to console (in real app, this would send to server)
    console.log('Document submitted:', formData);
    
    // Show success message
    showNotification(
        `Document "${title}" submitted successfully! ID: ${formData.documentId}`,
        'success'
    );
    
    // Reset form
    e.target.reset();
    
    // Optional: Clear the form after a delay
    setTimeout(() => {
        document.querySelector('form').reset();
    }, 500);
}

function generateDocumentId() {
    const year = new Date().getFullYear();
    const random = Math.floor(Math.random() * 10000).toString().padStart(5, '0');
    return `DOC-${year}-${random}`;
}

// ==========================================
// FILE UPLOAD HANDLING
// ==========================================

function setupFileUpload() {
    const fileUpload = document.querySelector('.file-upload');
    const fileInput = document.getElementById('docAttachment');
    
    if (fileUpload && fileInput) {
        // Click to upload
        fileUpload.addEventListener('click', () => {
            fileInput.click();
        });
        
        // File selection
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.style.borderColor = 'var(--primary-color)';
            fileUpload.style.backgroundColor = 'rgba(255, 149, 0, 0.05)';
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.style.borderColor = '';
            fileUpload.style.backgroundColor = '';
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.style.borderColor = '';
            fileUpload.style.backgroundColor = '';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect({ target: { files: files } });
            }
        });
    }
}

function handleFileSelect(e) {
    const files = e.target.files;
    if (files.length > 0) {
        const file = files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024).toFixed(2); // Convert to KB
        
        // Update UI
        const fileUpload = document.querySelector('.file-upload');
        fileUpload.innerHTML = `<span><i class="fas fa-check-circle"></i> ${fileName} (${fileSize} KB)</span>`;
        fileUpload.style.borderColor = '#28a745';
        fileUpload.style.backgroundColor = '#dff0d8';
        fileUpload.style.color = '#3c763d';
        
        showNotification(`File "${fileName}" selected`, 'success');
    }
}

// ==========================================
// NOTIFICATION SYSTEM
// ==========================================

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="notification-icon"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles if not already in CSS
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 2000;
                animation: slideIn 0.3s ease-out;
                max-width: 400px;
            }
            
            .notification-success {
                background-color: #dff0d8;
                color: #3c763d;
                border-left: 4px solid #28a745;
            }
            
            .notification-warning {
                background-color: #fcf8e3;
                color: #8a6d3b;
                border-left: 4px solid #ffc107;
            }
            
            .notification-info {
                background-color: #d9edf7;
                color: #31708f;
                border-left: 4px solid #5bc0de;
            }
            
            .notification-error {
                background-color: #f2dede;
                color: #a94442;
                border-left: 4px solid #d9534f;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .notification-icon::before {
                font-family: 'Font Awesome 6 Free';
                font-weight: 900;
            }
            
            .notification-success .notification-icon::before {
                content: "\\f058";
            }
            
            .notification-warning .notification-icon::before {
                content: "\\f071";
            }
            
            .notification-info .notification-icon::before {
                content: "\\f05a";
            }
            
            .notification-error .notification-icon::before {
                content: "\\f057";
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
            
            .notification.removing {
                animation: slideOut 0.3s ease-out;
            }
            
            @media (max-width: 768px) {
                .notification {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Add to DOM
    document.body.appendChild(notification);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        notification.classList.add('removing');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// ==========================================
// BUTTON ACTIONS
// ==========================================

// Search/Review buttons in tables
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-sm')) {
        const buttonText = e.target.textContent.toLowerCase();
        
        if (buttonText.includes('review')) {
            showNotification('Opening document for review...', 'info');
        } else if (buttonText.includes('resubmit')) {
            showNotification('Opening resubmission form...', 'info');
        } else if (buttonText.includes('download') || e.target.querySelector('i.fa-download')) {
            showNotification('Starting download...', 'success');
        } else if (buttonText.includes('view')) {
            showNotification('Opening archived document...', 'info');
        }
    }
});

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

// Clear tracking input on focus
document.addEventListener('DOMContentLoaded', function() {
    const trackingInput = document.getElementById('trackingInput');
    if (trackingInput) {
        trackingInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleTracking();
            }
        });
    }
});

// Close mobile sidebar when menu item is clicked (mobile only)
function closeSidebarOnMobile() {
    if (window.innerWidth <= 768) {
        // Sidebar automatically closes on mobile due to bottom position
        // This is a placeholder for additional mobile-specific logic if needed
    }
}

// Handle window resize for responsive behavior
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        // Re-initialize navigation if needed on resize
    }, 250);
});

// ==========================================
// PAGE LOAD ANIMATIONS
// ==========================================

// Add fade-in animation to cards on dashboard load
window.addEventListener('load', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.animation = `fadeIn 0.5s ease-out ${index * 0.1}s forwards`;
    });
});

// ==========================================
// ACCESSIBILITY ENHANCEMENTS
// ==========================================

// Keyboard navigation for sidebar
document.addEventListener('keydown', function(e) {
    // Alt + D for Dashboard (if needed)
    if (e.altKey && e.key === 'd') {
        document.querySelector('[data-page="dashboard"]').click();
    }
});

console.log('LGU Admin Dashboard initialized successfully');
