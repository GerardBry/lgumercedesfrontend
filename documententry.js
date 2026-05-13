console.log('documententry.js is loading...');

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
    ]
};

function handleLogout() {
    window.location.href = 'logout.php';
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
    
    // Reset classification and sub-classification
    const classificationSelect = document.getElementById('classification');
    const subClassificationSelect = document.getElementById('subClassification');
    
    if (classificationSelect) {
        classificationSelect.value = '';
    }
    
    if (subClassificationSelect) {
        subClassificationSelect.value = '';
        subClassificationSelect.innerHTML = '<option value="">Select Sub-Classification</option>';
        subClassificationSelect.disabled = true;
    }
    
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

function addPersonnel() {
    // Placeholder for future use if needed
}

function removePersonnel(button) {
    // Placeholder for future use if needed
}

function viewSavedDocument(docId) {
    const url = './get-document-details.php?id=' + encodeURIComponent(docId);
    fetch(url, { credentials: 'same-origin' })
        .then(async response => {
            if (!response.ok) {
                const text = await response.text();
                console.error('Failed to fetch document details:', response.status, text);
                notify('Failed to load document');
                return null;
            }
            try {
                return await response.json();
            } catch (error) {
                console.error('Invalid JSON from get-document-details.php:', error);
                notify('Failed to load document');
                return null;
            }
        })
        .then(data => {
            if (!data || !data.success) {
                notify('Failed to load document');
                return;
            }

            currentDetailDocument = data.document;
            displayDocumentDetailsModal(data.document);
        })
        .catch(error => {
            console.error('Error loading document details:', error);
            notify('Failed to load document');
        });
}

function displayDocumentDetailsModal(doc) {
    // Parse additional data from notes JSON
    const additionalData = doc.notes ? JSON.parse(doc.notes) : {};
    const docId = 'DOC-' + String(doc.doc_sequence_number || doc.id).padStart(4, '0');
    // Use direct columns first, fallback to JSON
    const sender = doc.sender_name || additionalData.sender || 'N/A';
    const dateReceived = doc.date_received || additionalData.date_received || 'N/A';
    const classification = doc.classification || additionalData.classification || 'N/A';
    const subClassification = doc.sub_classification || additionalData.sub_classification || 'N/A';
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
    let fileHTML = `
        <div class="detail-row" style="grid-column: span 2;">
            <label>Attached File</label>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <span style="flex:1; min-width:0; word-break:break-all; color: var(--text-dark);">No attachment</span>
            </div>
        </div>
    `;
    if (filePath) {
        const fileExt = filePath.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'].includes(fileExt);
        const fileName = filePath.split('/').pop();
        window.currentDocumentFilePath = filePath;

        fileHTML = `
            <div class="detail-row" style="grid-column: span 2;">
                <label>Attached File</label>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                    <span style="flex:1; min-width:0; word-break:break-all; color: var(--text-dark);">${htmlEscape(fileName)}</span>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm btn-warning" onclick="window.open('./view-document-file.php?path=' + encodeURIComponent('${filePath}'), '_blank')"><i class="fas fa-eye"></i> View</button>
                        <button type="button" class="btn btn-sm btn-info" onclick="downloadDocumentFile()"><i class="fas fa-download"></i> Download</button>
                    </div>
                </div>
            </div>
        `;
    }

    const detailsHTML = `
        <div class="document-details">
            <div class="details-section">
                <h4 style="margin-bottom: 15px; font-weight: 600; color: var(--text-dark); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; font-size: 16px;">Document Information</h4>
                <div class="details-grid">
                    <div class="detail-row">
                        <label>Document ID</label>
                        <span>${htmlEscape(docId)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Subject/Title</label>
                        <span>${htmlEscape(doc.title)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Sender</label>
                        <span>${htmlEscape(sender)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Date Received</label>
                        <span>${dateReceived !== 'N/A' ? new Date(dateReceived).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Classification</label>
                        <span><span class="badge ${classificationClass}" style="font-size: 11px; padding: 5px 12px;">${htmlEscape(classification)}</span></span>
                    </div>
                    <div class="detail-row">
                        <label>Sub-Classification</label>
                        <span>${htmlEscape(subClassification)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Prioritization</label>
                        <span><span class="badge ${priorityClass}" style="font-size: 11px; padding: 5px 12px;">${htmlEscape(priority)}</span></span>
                    </div>
                    <div class="detail-row" style="grid-column: span 2;">
                        <label>Description</label>
                        <span style="line-height: 1.6;">${htmlEscape(doc.description || 'N/A')}</span>
                    </div>
                    <div class="detail-row" style="grid-column: span 2;">
                        <label>Created Date</label>
                        <span>${new Date(doc.created_at).toLocaleString()}</span>
                    </div>
                    ${fileHTML}
                </div>
            </div>
        </div>
    `;

    document.getElementById('documentDetailsModalContent').innerHTML = detailsHTML;
    document.getElementById('documentPreviewModalContent').innerHTML = ''; // No preview for department module
    document.getElementById('documentDetailsModal').classList.add('active');
    document.getElementById('documentDetailsBackdrop').classList.add('active');
}

function closeDocumentDetailsModal() {
    document.getElementById('documentDetailsModal').classList.remove('active');
    document.getElementById('documentDetailsBackdrop').classList.remove('active');
}

function editDocument() {
    if (!currentDetailDocument) {
        notify('No document selected');
        return;
    }

    closeDocumentDetailsModal();
    populateEditForm(currentDetailDocument);
    openCreateDocumentModal();
}

function forwardDocument() {
    if (!currentDetailDocument) {
        notify('No document selected');
        return;
    }

    closeDocumentDetailsModal();
    openRouteModal(currentDetailDocument);
}

function openRouteModal(doc) {
    // Generate tracking code
    const trackingCode = generateTrackingCode();
    
    document.getElementById('routeDocId').value = String(doc.id);
    document.getElementById('routeTrackingCode').value = trackingCode;

    document.getElementById('routeModal').classList.add('active');
    document.getElementById('routeBackdrop').classList.add('active');
}

function closeRouteModal() {
    document.getElementById('routeModal').classList.remove('active');
    document.getElementById('routeBackdrop').classList.remove('active');
}

function generateTrackingCode() {
    // Format: DOC-YYYYMMDD-XXXXX (where X is random alphanumeric)
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const random = Math.random().toString(36).substring(2, 7).toUpperCase();
    return `DOC-${year}${month}${day}-${random}`;
}

function confirmRouteDocument() {
    const documentId = parseInt(document.getElementById('routeDocId').value, 10);
    const trackingCode = document.getElementById('routeTrackingCode').value.trim();

    if (!documentId || !trackingCode) {
        notify('Invalid document data');
        return;
    }

    const payload = {
        action: 'route_document',
        document_id: documentId,
        tracking_code: trackingCode
    };

    fetch('route-document-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                notify(data.message || 'Failed to route document');
                return;
            }

            closeRouteModal();
            notify('Document routed to Administrative successfully.');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        })
        .catch(error => {
            console.error('Route error:', error);
            notify('Failed to route document');
        });
}

function populateEditForm(doc) {
    const additionalData = doc.notes ? JSON.parse(doc.notes) : {};
    const form = document.getElementById('createDocumentForm');
    if (!form) return;

    isEditMode = true;

    // Remove existing document_id input if present
    const existingInput = form.querySelector('#documentIdInput');
    if (existingInput) {
        existingInput.remove();
    }

    // Create hidden document_id input
    const docIdInput = document.createElement('input');
    docIdInput.type = 'hidden';
    docIdInput.name = 'document_id';
    docIdInput.id = 'documentIdInput';
    docIdInput.value = doc.id;
    form.appendChild(docIdInput);

    setTimeout(() => {
        const subjectField = document.getElementById('docSubject');
        const senderField = document.getElementById('docSender');
        const dateReceivedField = document.getElementById('dateReceived');
        const descriptionField = document.getElementById('docDescription');
        const deadlineField = document.getElementById('deadline');
        const classificationField = document.getElementById('classification');
        const subClassificationField = document.getElementById('subClassification');
        const priorityField = document.getElementById('priority');
        const fileUploadSection = document.getElementById('fileUploadSection');
        const fileInput = document.getElementById('documentFile');
        const submitButton = form.querySelector('button[type="submit"]');
        const modalHeader = document.querySelector('#createDocumentModal .modal-header h3');

        if (subjectField) subjectField.value = doc.title || '';
        if (senderField) senderField.value = additionalData.sender || '';
        if (dateReceivedField) dateReceivedField.value = additionalData.date_received || '';
        if (descriptionField) descriptionField.value = doc.description || '';
        if (deadlineField) deadlineField.value = additionalData.deadline || '';
        if (classificationField) {
            classificationField.value = additionalData.classification || '';
            updateSubClassification();
            setTimeout(() => {
                if (subClassificationField) {
                    subClassificationField.value = additionalData.sub_classification || '';
                }
            }, 50);
        }
        if (priorityField) priorityField.value = additionalData.priority || 'Normal';
        if (fileUploadSection) fileUploadSection.style.display = 'block';
        if (fileInput) fileInput.removeAttribute('required');
        if (submitButton) submitButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        if (modalHeader) modalHeader.textContent = 'Edit Document';
    }, 50);
}

function closeFileViewerModal() {
    document.getElementById('fileViewerModal').classList.remove('active');
    document.getElementById('fileViewerImage').src = '';
}

// Disabled: View document file functionality not used in department module
// function viewDocumentFileModal() {
//     if (!window.currentDocumentFilePath) {
//         alert('No file to view');
//         return;
//     }
//     const url = './view-document-file.php?path=' + encodeURIComponent(window.currentDocumentFilePath);
//     const fileName = window.currentDocumentFilePath.split('/').pop();
//     document.getElementById('fileViewerTitle').textContent = 'Viewing: ' + fileName;
//     document.getElementById('fileViewerImage').src = url;
//     document.getElementById('fileViewerModal').classList.add('active');
// }

function downloadDocumentFile(filePath = '', fileName = '') {
    const targetPath = filePath || window.currentDocumentFilePath;
    if (!targetPath) {
        alert('No file to download');
        return;
    }

    const targetName = fileName || targetPath.split('/').pop();
    const url = './view-document-file.php?path=' + encodeURIComponent(targetPath);
    const link = document.createElement('a');
    link.href = url;
    link.download = targetName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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
                        <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
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
                    <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
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

// Disabled: View document file functionality not used in department module
// function viewDocumentFile(filePath) {
//     // Use the view handler to serve the file properly
//     const url = './view-document-file.php?path=' + encodeURIComponent(filePath);
//     console.log('Opening document file:', url);
//     window.open(url, '_blank');
// }

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
    const endpoint = isEditMode ? 'administrative/update-document-handler.php' : 'documententry-handler.php';
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

// Form submission handler and initialization
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

        if (createModal && createModal.classList.contains('active')) {
            closeCreateDocumentModal();
        }
        if (previewModal && previewModal.classList.contains('active')) {
            // closePreviewModal function not needed
        }
        if (detailsModal && detailsModal.classList.contains('active')) {
            closeDocumentDetailsModal();
        }
    }
});
