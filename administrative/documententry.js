let currentDocumentData = null;
let currentDetailDocument = null;

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
    resetDocumentForm();

    setTimeout(() => {
        const title = document.getElementById('modalDocTitle');
        if (title) {
            title.focus();
        }
    }, 100);
}

function closeCreateDocumentModal() {
    document.getElementById('createDocumentModal').classList.remove('active');
    document.getElementById('modalBackdrop').classList.remove('active');
    resetDocumentForm();
}

function resetDocumentForm() {
    document.getElementById('createDocumentForm').reset();
    document.getElementById('modalDocType').value = '';
    hideDynamicForms();
}

function hideDynamicForms() {
    document.getElementById('travelOrderForm').style.display = 'none';
    document.getElementById('executiveOrderForm').style.display = 'none';
    document.getElementById('officeOrderForm').style.display = 'none';
}

function isTravelOrderType(docType) {
    return docType === 'Travel Order' || docType === 'Travel Request';
}

function updateDynamicForm() {
    const docType = document.getElementById('modalDocType').value;
    hideDynamicForms();

    if (isTravelOrderType(docType)) {
        document.getElementById('travelOrderForm').style.display = 'block';
        initializePersonnelList();
        generateTravelOrderNumber();
    } else if (docType === 'Executive Order') {
        document.getElementById('executiveOrderForm').style.display = 'block';
    } else if (docType === 'Office Order') {
        document.getElementById('officeOrderForm').style.display = 'block';
        initializeAssignedPersonnelList();
    }
}

function generateTravelOrderNumber() {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = String(today.getFullYear()).slice(-2);
    const random = String(Math.floor(Math.random() * 9000) + 1000);
    document.getElementById('toNumber').value = day + month + year + '-' + random;
}

function initializePersonnelList() {
    const list = document.getElementById('personnelList');
    if (list.children.length === 0) {
        addPersonnel();
    }
}

function addPersonnel() {
    const list = document.getElementById('personnelList');
    const item = document.createElement('div');
    item.className = 'multi-entry-item';
    item.innerHTML = '<input type="text" placeholder="Name of Employee / Personnel" class="traveler-name" required>' +
        '<input type="text" placeholder="Position/Designation" class="traveler-position" required>' +
        '<button type="button" class="btn btn-sm btn-secondary" onclick="removePersonnel(this)"><i class="fas fa-trash"></i></button>';
    list.appendChild(item);
}

function removePersonnel(button) {
    const list = document.getElementById('personnelList');
    if (list.children.length > 1) {
        button.closest('.multi-entry-item').remove();
    } else {
        notify('At least one personnel is required');
    }
}

function initializeAssignedPersonnelList() {
    const list = document.getElementById('assignedPersonnelList');
    if (list.children.length === 0) {
        addAssignedPersonnel();
    }
}

function addAssignedPersonnel() {
    const list = document.getElementById('assignedPersonnelList');
    const item = document.createElement('div');
    item.className = 'multi-entry-item';
    item.innerHTML = '<input type="text" placeholder="Name of Personnel" class="assigned-personnel" required>' +
        '<button type="button" class="btn btn-sm btn-secondary" onclick="removeAssignedPersonnel(this)"><i class="fas fa-trash"></i></button>';
    list.appendChild(item);
}

function removeAssignedPersonnel(button) {
    const list = document.getElementById('assignedPersonnelList');
    if (list.children.length > 1) {
        button.closest('.multi-entry-item').remove();
    } else {
        notify('At least one personnel is required');
    }
}

function handlePreviewDocument(e) {
    e.preventDefault();
    previewDocument();
}

function previewDocument() {
    const docType = document.getElementById('modalDocType').value;
    const title = document.getElementById('modalDocTitle').value;

    if (!docType || !title) {
        notify('Please fill in all required fields');
        return;
    }

    currentDocumentData = collectDocumentData(docType);

    let previewHTML = '';
    if (isTravelOrderType(docType)) {
        previewHTML = generateTravelOrderPreview(currentDocumentData);
    } else if (docType === 'Executive Order') {
        previewHTML = generateExecutiveOrderPreview(currentDocumentData);
    } else {
        previewHTML = generateOfficeOrderPreview(currentDocumentData);
    }

    document.getElementById('previewContent').innerHTML = previewHTML;
    document.getElementById('previewDocumentModal').classList.add('active');
    document.getElementById('previewBackdrop').classList.add('active');
    document.getElementById('createDocumentModal').style.zIndex = '1998';
}

function closePreviewModal() {
    document.getElementById('previewDocumentModal').classList.remove('active');
    document.getElementById('previewBackdrop').classList.remove('active');
    document.getElementById('createDocumentModal').style.zIndex = '2000';
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

    if (isTravelOrderType(docType)) {
        data.orderNumber = document.getElementById('toNumber').value;
        data.dateIssued = document.getElementById('toDate').value;
        data.travelers = Array.from(document.querySelectorAll('.traveler-name')).map(el => el.value);
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
        data.eoDateIssued = document.getElementById('eoDateIssued').value;
        data.signatory = document.getElementById('eoSignatory').value;
    } else {
        data.orderNumber = document.getElementById('ooNumber').value;
        data.effectivityDate = document.getElementById('ooDate').value;
        data.assignedPersonnel = Array.from(document.querySelectorAll('.assigned-personnel')).map(el => el.value);
        data.task = document.getElementById('ooTask').value;
        data.department = document.getElementById('ooDepartment').value;
        data.remarks = document.getElementById('ooRemarks').value;
    }

    return data;
}

function saveDocumentAsDraft() {
    const docType = document.getElementById('modalDocType').value;
    const title = document.getElementById('modalDocTitle').value;
    const data = collectDocumentData(docType);

    let description = '';
    if (isTravelOrderType(docType)) {
        description = 'Travel to ' + (data.destination || '-') + ' - ' + (data.travelers || []).join(', ');
    } else if (docType === 'Executive Order') {
        description = data.eoTitle || title;
    } else {
        description = (data.assignedPersonnel || []).join(', ') + ' - ' + (data.department || '');
    }

    const payload = {
        action: 'save_draft',
        document_type: docType,
        title: title,
        description: description,
        content: data
    };

    fetch('documententry-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(dataResp => {
            if (!dataResp.success) {
                notify(dataResp.message || 'Failed to save document');
                return;
            }

            closeCreateDocumentModal();
            notify('Document saved successfully.');
            setTimeout(() => {
                window.location.href = 'documententry.php';
            }, 400);
        })
        .catch(() => {
            notify('Failed to save document');
        });
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
    let detailsHTML = '<div class="document-details"><div class="details-section">' +
        '<h4>Document Information</h4>' +
        '<div class="detail-row"><label>Tracking Code:</label><span>' + htmlEscape(doc.tracking_number) + '</span></div>' +
        '<div class="detail-row"><label>Title:</label><span>' + htmlEscape(doc.title) + '</span></div>' +
        '<div class="detail-row"><label>Document Type:</label><span>' + htmlEscape(doc.document_type) + '</span></div>' +
        '<div class="detail-row"><label>Description:</label><span>' + htmlEscape(doc.description) + '</span></div>' +
        '<div class="detail-row"><label>Date Created:</label><span>' + new Date(doc.created_at).toLocaleString() + '</span></div>' +
        '<div class="detail-row"><label>Status:</label><span><span class="badge badge-info">' + htmlEscape(doc.status) + '</span></span></div>' +
        '</div></div>';

    document.getElementById('documentDetailsModalContent').innerHTML = detailsHTML;
    document.getElementById('documentPreviewModalContent').innerHTML = generateDocumentPreview(doc);
    document.getElementById('documentDetailsModal').classList.add('active');
    document.getElementById('documentDetailsBackdrop').classList.add('active');
}

function closeDocumentDetailsModal() {
    document.getElementById('documentDetailsModal').classList.remove('active');
    document.getElementById('documentDetailsBackdrop').classList.remove('active');
}

function forwardSavedDocument(docId) {
    document.getElementById('forwardDocId').value = String(docId);
    document.getElementById('forwardNotes').value = '';
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
    const notes = document.getElementById('forwardNotes').value.trim();

    if (!documentId || !office || !recipientId) {
        notify('Please complete office and recipient before forwarding.');
        return;
    }

    const payload = {
        action: 'forward_document',
        document_id: documentId,
        office: office,
        recipient_id: recipientId,
        notes: notes
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

function generateTravelOrderPreview(data) {
    const travelers = Array.from(document.querySelectorAll('.traveler-name'))
        .map((el, idx) => {
            const positions = document.querySelectorAll('.traveler-position');
            const position = positions[idx] ? positions[idx].value : '';
            return el.value + ', ' + position;
        })
        .join('<br/>');

    return '<div class="document-preview"><div class="document-header">' +
        '<div class="doc-municipality">Province of Camarines Norte</div>' +
        '<div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>' +
        '<div class="doc-title">TRAVEL ORDER</div>' +
        '<div class="doc-number">No. ' + htmlEscape(data.orderNumber || 'TBD') + '</div></div>' +
        '<div class="document-body">' +
        '<div class="doc-line"><div class="doc-label">TO</div><div class="doc-content"><div class="doc-list">' + travelers + '</div></div></div>' +
        '<div class="doc-line"><div class="doc-label">FROM</div><div class="doc-content">' + htmlEscape(data.from || 'Municipal Mayor') + '</div></div>' +
        '<div class="doc-line"><div class="doc-label">DATE</div><div class="doc-content">' + htmlEscape(formatDateLong(data.dateIssued)) + '</div></div>' +
        '<div class="doc-line"><div class="doc-label">SUBJECT</div><div class="doc-content">' + htmlEscape(data.subject || 'As Stated') + '</div></div>' +
        '<div class="doc-paragraph">You are hereby directed to attend ' + htmlEscape(data.purpose || '') + '.</div>' +
        '<div class="doc-paragraph"><strong>Destination:</strong> ' + htmlEscape(data.destination || '-') + '<br><strong>Travel Dates:</strong> ' + htmlEscape(formatDateShort(data.startDate)) + ' to ' + htmlEscape(formatDateShort(data.endDate)) + '<br><strong>Duration:</strong> ' + htmlEscape(data.duration || '-') + ' day(s)<br><strong>Mode of Transportation:</strong> ' + htmlEscape(data.mode || '-') + '</div>' +
        '</div></div>';
}

function generateExecutiveOrderPreview(data) {
    return '<div class="document-preview"><div class="document-header">' +
        '<div class="doc-municipality">Province of Camarines Norte</div>' +
        '<div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>' +
        '<div class="doc-title">EXECUTIVE ORDER</div>' +
        '<div class="doc-number">No. ' + htmlEscape(data.orderNumber || 'TBD') + '</div></div>' +
        '<div class="document-body">' +
        '<div class="doc-line"><div class="doc-label">DATE</div><div class="doc-content">' + htmlEscape(formatDateLong(data.eoDateIssued)) + '</div></div>' +
        '<div class="doc-paragraph" style="margin-top: 30px;"><strong>' + htmlEscape(data.eoTitle || '') + '</strong></div>' +
        '<div class="doc-line"><div class="doc-label">WHEREAS:</div><div class="doc-content">' + htmlEscape(data.legalBasis || '') + '</div></div>' +
        '<div class="doc-paragraph">' + htmlEscape(data.description || '') + '</div>' +
        '<div class="doc-paragraph"><strong>Signed this ' + htmlEscape(formatDateLong(data.eoDateIssued)) + '</strong></div>' +
        '<div class="doc-line"><div class="doc-label">BY</div><div class="doc-content">' + htmlEscape(data.signatory || 'Municipal Mayor') + '</div></div>' +
        '</div></div>';
}

function generateOfficeOrderPreview(data) {
    const personnel = Array.from(document.querySelectorAll('.assigned-personnel')).map(el => el.value).join(', ');

    return '<div class="document-preview"><div class="document-header">' +
        '<div class="doc-municipality">Province of Camarines Norte</div>' +
        '<div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>' +
        '<div class="doc-title">OFFICE ORDER</div>' +
        '<div class="doc-number">No. ' + htmlEscape(data.orderNumber || 'TBD') + '</div></div>' +
        '<div class="document-body">' +
        '<div class="doc-line"><div class="doc-label">DATE</div><div class="doc-content">' + htmlEscape(formatDateLong(data.effectivityDate)) + '</div></div>' +
        '<div class="doc-line"><div class="doc-label">TO</div><div class="doc-content">' + htmlEscape(personnel) + '</div></div>' +
        '<div class="doc-line"><div class="doc-label">DEPARTMENT</div><div class="doc-content">' + htmlEscape(data.department || '') + '</div></div>' +
        '<div class="doc-paragraph"><strong>TASK/INSTRUCTION:</strong><br>' + htmlEscape(data.task || '') + '</div>' +
        '<div class="doc-paragraph"><strong>Effectivity:</strong> ' + htmlEscape(formatDateLong(data.effectivityDate)) + '</div>' +
        (data.remarks ? '<div class="doc-paragraph"><strong>Remarks:</strong> ' + htmlEscape(data.remarks) + '</div>' : '') +
        '</div></div>';
}

function generateDocumentPreview(doc) {
    let content = {};
    try {
        content = JSON.parse(doc.notes || '{}');
    } catch (e) {
        content = {};
    }

    if (isTravelOrderType(doc.document_type)) {
        return '<div class="document-preview"><div class="document-header">' +
            '<div class="doc-municipality">Province of Camarines Norte</div>' +
            '<div class="doc-office">MUNICIPALITY OF MERCEDES<br>OFFICE OF THE MUNICIPAL MAYOR</div>' +
            '<div class="doc-title">TRAVEL ORDER</div>' +
            '<div class="doc-number">No. ' + htmlEscape(doc.tracking_number || '-') + '</div></div>' +
            '<div class="document-body">' +
            '<div class="doc-line"><div class="doc-label">TO</div><div class="doc-content">' + htmlEscape((content.travelers || []).join(', ')) + '</div></div>' +
            '<div class="doc-line"><div class="doc-label">FROM</div><div class="doc-content">' + htmlEscape(content.from || 'Municipal Mayor') + '</div></div>' +
            '<div class="doc-line"><div class="doc-label">DATE</div><div class="doc-content">' + htmlEscape(content.dateIssued || '-') + '</div></div>' +
            '<div class="doc-line"><div class="doc-label">SUBJECT</div><div class="doc-content">' + htmlEscape(content.subject || 'As Stated') + '</div></div>' +
            '<div class="doc-paragraph">You are hereby directed to attend ' + htmlEscape(content.purpose || '') + '.</div>' +
            '</div></div>';
    }

    return '<div class="document-preview"><h3>' + htmlEscape(doc.title || '-') + '</h3><p>' + htmlEscape(doc.description || '-') + '</p><p><small>Document Type: ' + htmlEscape(doc.document_type || '-') + '</small></p></div>';
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