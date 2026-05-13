let currentDocumentData = null;
let currentDetailDocument = null;
let isEditMode = false;

// Sub-classification mapping for dynamic options
const subClassifications = {
    'Letter': [
        'Request Letter'
    ],
    'Invitation': [
        'Seminar/Training Invitation',
        'Meeting Invitation',
        'Conference/Event Invitation'
    ],
    'Travel-Related Communication': [
        'Official Travel Notice',
        'Field Visit/Inspection',
        'Meeting Assignment'
    ],
    'Indorsement': [
        'For Information',
        'For Action'
    ]
};

function handleLogout() {
    window.location.href = 'admin-logout.php';
}

function notify(message) {
    alert(message);
}

function openCreateDocumentModal() {
    const modal = document.getElementById('createDocumentModal');
    const backdrop = document.getElementById('modalBackdrop');

    modal.classList.add('active');
    backdrop.classList.add('active');
    
    // Only reset if not in edit mode
    if (!isEditMode) {
        resetDocumentForm();
    }

    setTimeout(() => {
        const subject = document.getElementById('docSubject');
        if (subject) {
            subject.focus();
        }
    }, 100);
}

function closeCreateDocumentModal() {
    document.getElementById('createDocumentModal').classList.remove('active');
    document.getElementById('modalBackdrop').classList.remove('active');
    isEditMode = false;
    resetDocumentForm();
}

function resetDocumentForm() {
    const form = document.getElementById('createDocumentForm');
    form.reset();
    document.getElementById('subClassification').value = '';
    document.getElementById('fileNameDisplay').style.display = 'none';
    
    // Show file upload section
    const fileUploadSection = document.getElementById('fileUploadSection');
    if (fileUploadSection) {
        fileUploadSection.style.display = 'block';
    }
    
    // Restore file input required attribute
    const fileInput = document.getElementById('documentFile');
    if (fileInput) {
        fileInput.setAttribute('required', 'required');
    }
    
    // Reset modal header and button
    const modalHeader = document.querySelector('#createDocumentModal .modal-header h3');
    if (modalHeader) {
        modalHeader.textContent = 'Add Document';
    }
    
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.innerHTML = '<i class="fas fa-check"></i> Add Document';
    }
    
    // Remove document_id input
    const docIdInput = document.getElementById('documentIdInput');
    if (docIdInput) {
        docIdInput.remove();
    }
    
    isEditMode = false;
}

function updateSubClassification() {
    const classification = document.getElementById('classification').value;
    const subClassificationSelect = document.getElementById('subClassification');
    
    // Clear existing options
    subClassificationSelect.innerHTML = '<option value="">Select Sub-Classification</option>';
    
    // Populate sub-classifications based on main classification
    if (classification && subClassifications[classification]) {
        subClassifications[classification].forEach(subClass => {
            const option = document.createElement('option');
            option.value = subClass;
            option.textContent = subClass;
            subClassificationSelect.appendChild(option);
        });
        subClassificationSelect.disabled = false;
    } else {
        subClassificationSelect.disabled = true;
    }
}

function hideDynamicForms() {
    // Placeholder for future use if needed
}

function isTravelOrderType(docType) {
    return docType === 'Travel Order' || docType === 'Travel Request';
}

function updateDynamicForm() {
    hideDynamicForms();
}

function initializePersonnelList() {
    // Placeholder for future use if needed
}

function addPersonnel() {
    // Placeholder for future use if needed
}

function removePersonnel(button) {
    // Placeholder for future use if needed
}

function initializeAssignedPersonnelList() {
    // Placeholder for future use if needed
}

function addAssignedPersonnel() {
    // Placeholder for future use if needed
}

function removeAssignedPersonnel(button) {
    // Placeholder for future use if needed
}

function handlePreviewDocument(e) {
    e.preventDefault();
    previewDocument();
}

function previewDocument() {
    // Placeholder for preview functionality
}

function closePreviewModal() {
    document.getElementById('previewDocumentModal').classList.remove('active');
    document.getElementById('previewBackdrop').classList.remove('active');
}

function printPreviewDocument() {
    const html = document.getElementById('previewContent').innerHTML;
    if (!html.trim()) {
        notify('No preview content to print.');
        return;
    }
    openPrintWindow(html);
}

function printCurrentDetails() {
    if (!currentDetailDocument) {
        notify('No document selected for printing.');
        return;
    }
    const html = generateDocumentPreview(currentDetailDocument);
    openPrintWindow(html);
}

function openPrintWindow(contentHtml) {
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        notify('Please allow pop-ups to print document preview.');
        return;
    }

    const markup = '<!DOCTYPE html><html><head><title>Print Preview</title>' +
        '<style>body{font-family:Georgia,serif;color:#1c1c1c;margin:0;padding:24px;} .document-preview{max-width:900px;margin:0 auto;} .document-header{text-align:center;margin-bottom:20px;} .doc-title{font-size:22px;font-weight:700;margin-top:8px;} .doc-number{font-weight:600;margin-top:4px;} .doc-line{display:grid;grid-template-columns:140px 1fr;gap:10px;margin:8px 0;} .doc-label{font-weight:700;} .doc-paragraph{margin:14px 0;line-height:1.5;} @media print { body { padding: 0; } }</style>' +
        '</head><body><div class="print-shell">' + contentHtml + '</div></body></html>';

    printWindow.document.open();
    printWindow.document.write(markup);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function submitDocumentFromPreview() {
    closePreviewModal();
    saveDocumentAsDraft();
}

function collectDocumentData(docType) {
    const title = document.getElementById('modalDocTitle').value;
    const data = {
        title: title,
        type: docType,
        dateCreated: formatDate(new Date())
    };
    return data;
}

function saveDocumentAsDraft() {
    // This will be handled by the form submission
}

function viewSavedDocument(docId) {
    fetch('get-document-details.php?id=' + docId)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                notify('Failed to load document');
                return;
            }

            currentDetailDocument = data.document;
            displayDocumentDetailsModal(data.document);
        })
        .catch(() => notify('Failed to load document'));
}

function displayDocumentDetailsModal(doc) {
    // Parse additional data from notes JSON
    const additionalData = doc.notes ? JSON.parse(doc.notes) : {};
    const docId = 'DOC-' + String(doc.id).padStart(4, '0');
    // Use direct columns first, fallback to JSON
    const sender = doc.sender_name || additionalData.sender || 'N/A';
    const dateReceived = doc.date_received || additionalData.date_received || 'N/A';
    const classification = doc.classification || additionalData.classification || 'N/A';
    const priority = doc.priority || additionalData.priority || 'N/A';
    const filePath = doc.file_path || additionalData.file_path || null;
    
    // Determine classification badge class
    let classificationClass = 'badge-info';
    if (classification === 'Letter') {
        classificationClass = 'badge-classification-letter';
    } else if (classification === 'Invitation') {
        classificationClass = 'badge-classification-invitation';
    } else if (classification === 'Travel-Related Communication') {
        classificationClass = 'badge-classification-travel';
    } else if (classification === 'Indorsement') {
        classificationClass = 'badge-classification-indorsement';
    }

    // Determine priority badge class
    let priorityClass = 'badge-secondary';
    if (priority === 'Normal') {
        priorityClass = 'badge-primary';
    } else if (priority === 'Urgent') {
        priorityClass = 'badge-warning';
    } else if (priority === 'Critical') {
        priorityClass = 'badge-danger';
    }

    // Prepare file section HTML
    let fileHTML = '';
    if (filePath) {
        const fileExt = filePath.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
        const isPDF = fileExt === 'pdf';
        const fileName = filePath.split('/').pop();
        window.currentDocumentFilePath = filePath;
        
        fileHTML = `
            <div class="detail-row" style="margin-top: 16px;">
                <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Attached File</label>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 14px; color: #333; flex: 1;">${htmlEscape(fileName)}</span>
                    ${isImage ? `<button type="button" class="btn btn-sm btn-primary" onclick="viewDocumentFileModal()"><i class="fas fa-eye"></i> View</button>` : ''}
                    ${isPDF ? `<button type="button" class="btn btn-sm btn-primary" onclick="window.open('view-document-file.php?path=' + encodeURIComponent('${filePath}'), '_blank')"><i class="fas fa-eye"></i> View</button>` : ''}
                    <button type="button" class="btn btn-sm btn-info" onclick="downloadDocumentFile()"><i class="fas fa-download"></i> Download</button>
                </div>
            </div>
        `;
    }

    const detailsHTML = `
        <div class="document-details">
            <div class="details-section">
                <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">Document Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Document ID</label>
                        <span style="font-size: 14px; font-weight: 600; color: #333;">${htmlEscape(docId)}</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Subject/Title</label>
                        <span style="font-size: 14px; color: #333;">${htmlEscape(doc.title)}</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Sender</label>
                        <span style="font-size: 14px; color: #333;">${htmlEscape(sender)}</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Date Received</label>
                        <span style="font-size: 14px; color: #333;">${dateReceived !== 'N/A' ? new Date(dateReceived).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Classification</label>
                        <span><span class="badge ${classificationClass}" style="font-size: 11px; padding: 5px 10px;">${htmlEscape(classification)}</span></span>
                    </div>
                    <div class="detail-row">
                        <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Prioritization</label>
                        <span><span class="badge ${priorityClass}" style="font-size: 11px; padding: 5px 10px;">${htmlEscape(priority)}</span></span>
                    </div>
                </div>
                <div class="detail-row" style="margin-top: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Description</label>
                    <span style="font-size: 14px; color: #333; display: block; line-height: 1.6;">${htmlEscape(doc.description || 'N/A')}</span>
                </div>
                <div class="detail-row" style="margin-top: 16px;">
                    <label style="font-weight: 600; color: #666; font-size: 12px; margin-bottom: 4px; display: block;">Created Date</label>
                    <span style="font-size: 14px; color: #333;">${new Date(doc.created_at).toLocaleString()}</span>
                </div>
                ${fileHTML}
            </div>
        </div>
    `;

    document.getElementById('documentDetailsModalContent').innerHTML = detailsHTML;
    document.getElementById('documentPreviewModalContent').innerHTML = generateDocumentPreview(doc);
    document.getElementById('documentDetailsModal').classList.add('active');
    document.getElementById('documentDetailsBackdrop').classList.add('active');
}

function closeDocumentDetailsModal() {
    document.getElementById('documentDetailsModal').classList.remove('active');
    document.getElementById('documentDetailsBackdrop').classList.remove('active');
}

function viewDocumentFileModal() {
    if (!window.currentDocumentFilePath) {
        alert('No file to view');
        return;
    }
    const url = 'view-document-file.php?path=' + encodeURIComponent(window.currentDocumentFilePath);
    const fileName = window.currentDocumentFilePath.split('/').pop();
    document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
    document.getElementById('fileViewerImage').src = url;
    document.getElementById('fileViewerModal').classList.add('active');
}

function closeFileViewerModal() {
    document.getElementById('fileViewerModal').classList.remove('active');
    document.getElementById('fileViewerImage').src = '';
}

function downloadDocumentFile(filePath = '', fileName = '') {
    const targetPath = filePath || window.currentDocumentFilePath;
    if (!targetPath) {
        alert('No file to download');
        return;
    }

    const targetName = fileName || targetPath.split('/').pop();
    const url = 'get-document-file.php?path=' + encodeURIComponent(targetPath);
    const link = document.createElement('a');
    link.href = url;
    link.download = targetName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function editDocument() {
    if (!currentDetailDocument) {
        notify('No document selected');
        return;
    }
    
    // Close details modal and populate form with current data
    closeDocumentDetailsModal();
    populateEditForm(currentDetailDocument);
    openCreateDocumentModal();
}

function populateEditForm(doc) {
    // Parse additional data from notes JSON
    const additionalData = doc.notes ? JSON.parse(doc.notes) : {};
    
    // Set form mode to edit
    isEditMode = true;
    
    // Store document ID
    const form = document.getElementById('createDocumentForm');
    
    // Remove existing document_id input if present
    const existingInput = form.querySelector('#documentIdInput');
    if (existingInput) {
        existingInput.remove();
    }
    
    // Create and add hidden document_id input
    const docIdInput = document.createElement('input');
    docIdInput.type = 'hidden';
    docIdInput.name = 'document_id';
    docIdInput.id = 'documentIdInput';
    docIdInput.value = doc.id;
    form.appendChild(docIdInput);
    
    // Populate form fields with a slight delay to ensure DOM is ready
    setTimeout(() => {
        // Set basic information
        const subjectField = document.getElementById('docSubject');
        const senderField = document.getElementById('docSender');
        const dateReceivedField = document.getElementById('dateReceived');
        const descriptionField = document.getElementById('docDescription');
        const deadlineField = document.getElementById('deadline');
        
        if (subjectField) subjectField.value = doc.title || '';
        if (senderField) senderField.value = additionalData.sender || '';
        if (dateReceivedField) dateReceivedField.value = additionalData.date_received || '';
        if (descriptionField) descriptionField.value = doc.description || '';
        if (deadlineField) deadlineField.value = additionalData.deadline || '';
        
        // Set classification
        const classificationField = document.getElementById('classification');
        if (classificationField) {
            classificationField.value = additionalData.classification || '';
            // Trigger change to update sub-classifications
            updateSubClassification();
            
            // Set sub-classification after a brief delay
            setTimeout(() => {
                const subClassificationField = document.getElementById('subClassification');
                if (subClassificationField) {
                    subClassificationField.value = additionalData.sub_classification || '';
                }
            }, 50);
        }
        
        // Set priority
        const priorityField = document.getElementById('priority');
        if (priorityField) priorityField.value = additionalData.priority || 'Normal';
        
        // Show file upload section (optional for editing)
        const fileUploadSection = document.getElementById('fileUploadSection');
        if (fileUploadSection) {
            fileUploadSection.style.display = 'block';
        }
        
        // Make file input not required in edit mode (optional)
        const fileInput = document.getElementById('documentFile');
        if (fileInput) {
            fileInput.removeAttribute('required');
        }
        
        // Update submit button text
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
        
        // Update modal header
        const modalHeader = document.querySelector('#createDocumentModal .modal-header h3');
        if (modalHeader) {
            modalHeader.textContent = 'Edit Document';
        }
    }, 50);
}

function forwardDocument() {
    if (!currentDetailDocument) {
        notify('No document selected');
        return;
    }
    
    // Close the details modal and open forward modal
    closeDocumentDetailsModal();
    forwardSavedDocument(currentDetailDocument.id);
}

function forwardSavedDocument(docId) {
    document.getElementById('forwardDocId').value = String(docId);
    document.getElementById('forwardOffice').innerHTML = '<option value="">Select Office</option>';
    document.getElementById('forwardRecipient').innerHTML = '<option value="">Select Recipient</option>';

    loadForwardOffices();

    document.getElementById('forwardModal').classList.add('active');
    document.getElementById('forwardBackdrop').classList.add('active');
}

function closeForwardModal() {
    document.getElementById('forwardModal').classList.remove('active');
    document.getElementById('forwardBackdrop').classList.remove('active');
}

function loadForwardOffices() {
    fetch('get-offices.php')
        .then(response => response.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.offices)) {
                return;
            }

            const officeSelect = document.getElementById('forwardOffice');
            data.offices.forEach(office => {
                const option = document.createElement('option');
                option.value = office;
                option.textContent = office;
                officeSelect.appendChild(option);
            });
        })
        .catch(() => {});
}

function loadForwardRecipients() {
    const office = document.getElementById('forwardOffice').value;
    const recipientSelect = document.getElementById('forwardRecipient');

    recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
    if (!office) {
        return;
    }

    fetch('get-staff-by-office.php?office=' + encodeURIComponent(office))
        .then(response => response.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.staff)) {
                return;
            }

            data.staff.forEach(staff => {
                const option = document.createElement('option');
                option.value = String(staff.id);
                option.textContent = staff.first_name + ' ' + staff.last_name + ' (' + (staff.position || 'Staff') + ')';
                recipientSelect.appendChild(option);
            });
        })
        .catch(() => {});
}

function confirmForwardDocument() {
    const documentId = parseInt(document.getElementById('forwardDocId').value, 10);
    const office = document.getElementById('forwardOffice').value;
    const recipientId = parseInt(document.getElementById('forwardRecipient').value, 10);

    if (!documentId || !office || !recipientId) {
        notify('Please complete office and recipient before forwarding.');
        return;
    }

    const payload = {
        action: 'forward_document',
        document_id: documentId,
        office: office,
        recipient_id: recipientId
    };

    fetch('forward-document-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                notify(data.message || 'Failed to forward document');
                return;
            }

            closeForwardModal();
            notify('Document forwarded successfully.');
            setTimeout(() => {
                window.location.reload();
            }, 400);
        })
        .catch(() => {
            notify('Failed to forward document');
        });
}

function generateDocumentPreview(doc) {
    const additionalData = doc.notes ? JSON.parse(doc.notes) : {};
    const filePath = additionalData.file_path || '';
    
    let previewHTML = `
        <div class="document-preview">
            <h4 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">Document File</h4>
    `;
    
    if (filePath) {
        const fileName = filePath.split('/').pop();
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'];
        const isImage = imageExtensions.includes(fileExtension);
        
        if (isImage) {
            // Show image preview
            const imageUrl = 'view-document-file.php?path=' + encodeURIComponent(filePath);
            previewHTML += `
                <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background-color: #f9f9f9; margin-bottom: 12px;">
                    <div style="max-height: 400px; overflow-y: auto; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0;">
                        <img src="${imageUrl}" style="max-width: 100%; max-height: 400px; object-fit: contain;" alt="${htmlEscape(fileName)}">
                    </div>
                    <div style="padding: 12px;">
                        <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 14px; word-break: break-all;">${htmlEscape(fileName)}</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <button type="button" onclick="viewDocumentFile('${htmlEscape(filePath)}')" style="padding: 10px 16px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                <i class="fas fa-eye"></i> View Full
                            </button>
                            <button type="button" onclick="downloadDocumentFile('${htmlEscape(filePath)}', '${htmlEscape(fileName)}')" style="padding: 10px 16px; background-color: #28a745; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Show file info for PDFs and other files
            previewHTML += `
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 16px; background-color: #f9f9f9; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <i class="fas fa-file-pdf" style="font-size: 32px; color: ${fileExtension === 'pdf' ? '#d32f2f' : '#ff9500'};"></i>
                        <div>
                            <p style="margin: 0; font-weight: 600; font-size: 14px; word-break: break-all;">${htmlEscape(fileName)}</p>
                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">File: ${htmlEscape(fileExtension.toUpperCase())}</p>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <button type="button" onclick="viewDocumentFile('${htmlEscape(filePath)}')" style="padding: 10px 16px; background-color: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" onclick="downloadDocumentFile('${htmlEscape(filePath)}', '${htmlEscape(fileName)}')" style="padding: 10px 16px; background-color: #28a745; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            `;
        }
    } else {
        previewHTML += `
            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 16px; background-color: #f9f9f9; text-align: center; color: #999;">
                <i class="fas fa-file-slash" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                <p>No file attached to this document</p>
            </div>
        `;
    }
    
    previewHTML += '</div>';
    return previewHTML;
}

function viewDocumentFile(filePath) {
    // Use the view handler to serve the file properly
    const url = 'view-document-file.php?path=' + encodeURIComponent(filePath);
    console.log('Opening document file:', url);
    window.open(url, '_blank');
}

function formatDateLong(dateString) {
    if (!dateString) {
        return 'N/A';
    }
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatDateShort(dateString) {
    if (!dateString) {
        return 'N/A';
    }
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDate(date) {
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function htmlEscape(str) {
    if (!str) {
        return '';
    }
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// File Upload Handler
document.addEventListener('DOMContentLoaded', function() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('documentFile');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    if (fileUploadArea && fileInput) {
        // Click to upload
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.style.backgroundColor = '#f0f0f0';
            fileUploadArea.style.borderColor = 'var(--primary-color)';
        });

        fileUploadArea.addEventListener('dragleave', function() {
            fileUploadArea.style.backgroundColor = '';
            fileUploadArea.style.borderColor = '';
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.style.backgroundColor = '';
            fileUploadArea.style.borderColor = '';

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                updateFileDisplay(this.files[0]);
            }
        });
    }
});

function updateFileDisplay(file) {
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
    const maxSize = 10 * 1024 * 1024; // 10MB

    // Validate file
    if (!allowedTypes.includes(file.type)) {
        notify('Invalid file type. Please upload PDF or image files.');
        document.getElementById('documentFile').value = '';
        fileNameDisplay.style.display = 'none';
        return;
    }

    if (file.size > maxSize) {
        notify('File size exceeds 10MB limit.');
        document.getElementById('documentFile').value = '';
        fileNameDisplay.style.display = 'none';
        return;
    }

    // Display file name
    fileNameDisplay.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
    fileNameDisplay.style.display = 'block';
}

function handleDocumentFormSubmit() {
    const form = document.getElementById('createDocumentForm');
    if (!form) return;

    // Validate all required fields
    const title = document.getElementById('docSubject').value.trim();
    const sender = document.getElementById('docSender').value.trim();
    const dateReceived = document.getElementById('dateReceived').value;
    const classification = document.getElementById('classification').value;
    const subClassification = document.getElementById('subClassification').value;
    const priority = document.getElementById('priority').value;
    const fileInput = document.getElementById('documentFile');

    if (!title) {
        notify('Please enter a subject/title');
        return;
    }

    if (!sender) {
        notify('Please enter sender information');
        return;
    }

    if (!dateReceived) {
        notify('Please select date received');
        return;
    }

    if (!classification) {
        notify('Please select a classification');
        return;
    }

    if (!subClassification) {
        notify('Please select a sub-classification');
        return;
    }

    if (!priority) {
        notify('Please select a priority level');
        return;
    }

    // For new documents, require file upload
    if (!isEditMode && (!fileInput.files || fileInput.files.length === 0)) {
        notify('Please upload a document file');
        return;
    }

    // Prepare form data
    const formData = new FormData(form);

    // Debug: Log all form data
    console.log('=== FORM DATA BEING SENT ===');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    console.log('============================');

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    // Determine endpoint and display message
    const endpoint = isEditMode ? 'update-document-handler.php' : 'add-document-handler.php';
    const successMessage = isEditMode ? 'Document updated successfully!' : 'Document added successfully!';

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response OK:', response.ok);
            console.log('Endpoint URL:', endpoint);
            if (!response.ok && response.status !== 200) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                if (!data.success) {
                    notify(data.message || 'Failed to save document');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                    return;
                }

                closeCreateDocumentModal();
                notify(successMessage);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response:', text);
                notify('Error: Invalid server response. Check console for details.');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            notify('Failed to save document: ' + error.message);
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        });
}

// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createDocumentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleDocumentFormSubmit();
        });
    }
});

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const createModal = document.getElementById('createDocumentModal');
        const previewModal = document.getElementById('previewDocumentModal');
        const detailsModal = document.getElementById('documentDetailsModal');
        const forwardModal = document.getElementById('forwardModal');

        if (createModal && createModal.classList.contains('active')) {
            closeCreateDocumentModal();
        }
        if (previewModal && previewModal.classList.contains('active')) {
            closePreviewModal();
        }
        if (detailsModal && detailsModal.classList.contains('active')) {
            closeDocumentDetailsModal();
        }
        if (forwardModal && forwardModal.classList.contains('active')) {
            closeForwardModal();
        }
    }
});