<?php
/**
 * Database Migration - Add Tracking Fields to Documents
 * Adds unique tracking number, document type, sender info, and document metadata
 */

require_once 'config/db_connect.php';

try {
    echo "Starting database migration...\n";
    
    // Check if tracking_number exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'tracking_number'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add tracking_number column (non-unique) to allow shared transaction tracking
        $sql = "ALTER TABLE documents ADD COLUMN tracking_number VARCHAR(50) NOT NULL DEFAULT ''";
        if ($conn->query($sql)) {
            echo "[✓] Added tracking_number column\n";
        } else {
            throw new Exception("Error adding tracking_number: " . $conn->error);
        }
    }
    
    // Check if document_type exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'document_type'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add document_type column
        $sql = "ALTER TABLE documents ADD COLUMN document_type VARCHAR(100) DEFAULT 'General'";
        if ($conn->query($sql)) {
            echo "[✓] Added document_type column\n";
        } else {
            throw new Exception("Error adding document_type: " . $conn->error);
        }
    }
    
    // Check if sender_id exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'sender_id'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add sender_id column
        $sql = "ALTER TABLE documents ADD COLUMN sender_id INT REFERENCES users(id)";
        if ($conn->query($sql)) {
            echo "[✓] Added sender_id column\n";
        } else {
            throw new Exception("Error adding sender_id: " . $conn->error);
        }
    }
    
    // Check if date_sent exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'date_sent'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add date_sent column
        $sql = "ALTER TABLE documents ADD COLUMN date_sent TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        if ($conn->query($sql)) {
            echo "[✓] Added date_sent column\n";
        } else {
            throw new Exception("Error adding date_sent: " . $conn->error);
        }
    }
    
    // Check if notes exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'notes'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add notes column
        $sql = "ALTER TABLE documents ADD COLUMN notes LONGTEXT";
        if ($conn->query($sql)) {
            echo "[✓] Added notes column\n";
        } else {
            throw new Exception("Error adding notes: " . $conn->error);
        }
    }
    
    // Check if status exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'status'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add status column
        $sql = "ALTER TABLE documents ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Archived') DEFAULT 'Pending'";
        if ($conn->query($sql)) {
            echo "[✓] Added status column\n";
        } else {
            throw new Exception("Error adding status: " . $conn->error);
        }
    }

    // Expand assignment workflow statuses for received workflow updates
    $sql_check = "SHOW COLUMNS FROM document_assignments LIKE 'status'";
    $result = $conn->query($sql_check);

    if ($result && $result->num_rows > 0) {
        $sql = "ALTER TABLE document_assignments MODIFY COLUMN status ENUM('Pending', 'Received', 'Checking Documents', 'Waiting For Approval by Mayor', 'Completed', 'Returned', 'In Progress') DEFAULT 'Pending'";
        if ($conn->query($sql)) {
            echo "[✓] Expanded document_assignments.status workflow values\n";
        } else {
            throw new Exception("Error updating document_assignments status enum: " . $conn->error);
        }
    }
    
    echo "\n[✓] Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
