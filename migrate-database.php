<?php
/**
 * Database Migration - Create Document Tables
 * Run this script once to set up the document and assignment tables
 */

require_once 'config/db_connect.php';

try {
    echo "Starting database migration...\n";
    
    // Create documents table
    $sql_docs = "CREATE TABLE IF NOT EXISTS documents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_docs)) {
        echo "[✓] Documents table created successfully\n";
    } else {
        throw new Exception("Error creating documents table: " . $conn->error);
    }
    
    // Create document_assignments table
    $sql_assign = "CREATE TABLE IF NOT EXISTS document_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NOT NULL,
        assigned_by INT NOT NULL,
        assigned_to INT NOT NULL,
        office_department VARCHAR(150),
        notes TEXT,
        status ENUM('Pending', 'Received', 'In Progress', 'Completed', 'Returned') DEFAULT 'Pending',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql_assign)) {
        echo "[✓] Document assignments table created successfully\n";
    } else {
        throw new Exception("Error creating document_assignments table: " . $conn->error);
    }
    
    echo "\n[✓] Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
} finally {
    $conn->close();
}
?>
