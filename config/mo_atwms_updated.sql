-- MO-ATWMS Database Schema - UPDATED with Document Tracking
-- This shows the structure AFTER running migrate-add-tracking.php

-- Documents Table (Updated)
CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    tracking_number VARCHAR(50) NOT NULL DEFAULT '',
    document_type VARCHAR(100) DEFAULT 'General',
    sender_id INT,
    date_sent TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes LONGTEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Archived') DEFAULT 'Pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tracking_number (tracking_number),
    INDEX idx_document_type (document_type),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_sender_id (sender_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document Assignments Table (Unchanged, but referenced from documents table)
CREATE TABLE IF NOT EXISTS document_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_to INT NOT NULL,
    office_department VARCHAR(150),
    notes TEXT,
    status ENUM('Pending', 'Received', 'Checking Documents', 'Waiting For Approval by Mayor', 'In Progress', 'Completed', 'Returned') DEFAULT 'Pending',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_document_id (document_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status),
    INDEX idx_assigned_at (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TRACKING NUMBER FORMAT
-- Generated format: LGU-YYYY-MM-DD-XXX
-- Example: LGU-2026-04-23-487
-- Where:
--   LGU = System prefix
--   YYYY = 4-digit year
--   MM = 2-digit month
--   DD = 2-digit day
--   XXX = 3-digit random number (001-999)

-- DOCUMENT TYPES (Suggested Values)
-- - Travel Order
-- - Executive Order
-- - Office Order
-- - Memorandum
-- - Request
-- - Report
-- - Other

-- STATUSES
-- Documents: Pending, Approved, Rejected, Archived
-- Assignments: Pending, Received, In Progress, Completed, Returned

-- Sample query to fetch documents with tracking information
SELECT 
    d.id,
    d.tracking_number,
    d.title,
    d.document_type,
    d.description,
    u_sender.first_name as sender_first_name,
    u_sender.last_name as sender_last_name,
    d.date_sent,
    d.notes,
    d.status,
    d.created_by,
    d.created_at
FROM documents d
LEFT JOIN users u_sender ON d.sender_id = u_sender.id
WHERE d.status != 'Archived'
ORDER BY d.created_at DESC;

-- Sample query to fetch assignments with full document details
SELECT 
    da.id,
    da.document_id,
    d.tracking_number,
    d.title,
    d.document_type,
    d.description,
    d.date_sent,
    d.notes,
    u_sender.first_name as sender_first_name,
    u_sender.last_name as sender_last_name,
    u_recipient.first_name as recipient_first_name,
    u_recipient.last_name as recipient_last_name,
    da.office_department,
    da.status,
    da.assigned_at
FROM document_assignments da
JOIN documents d ON da.document_id = d.id
LEFT JOIN users u_sender ON d.sender_id = u_sender.id
JOIN users u_recipient ON da.assigned_to = u_recipient.id
ORDER BY da.assigned_at DESC;
