<?php
/**
 * Database Migration - Add office_department to documents table
 * Run this to fix "unknown column office_department" error
 */

require_once 'config/db_connect.php';

try {
    echo "Running migration: Add office_department to documents table...\n";
    
    // Check if office_department column exists
    $sql_check = "SHOW COLUMNS FROM documents LIKE 'office_department'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows > 0) {
        echo "[✓] office_department column already exists in documents table\n";
    } else {
        // Add office_department column
        $sql = "ALTER TABLE documents ADD COLUMN office_department VARCHAR(150)";
        if ($conn->query($sql)) {
            echo "[✓] Added office_department column to documents table\n";
        } else {
            throw new Exception("Error adding office_department: " . $conn->error);
        }
        
        // Add index for faster queries
        $sql_index = "ALTER TABLE documents ADD INDEX idx_office_department (office_department)";
        if ($conn->query($sql_index)) {
            echo "[✓] Added index on office_department column\n";
        }
    }
    
    echo "\n[SUCCESS] Migration completed successfully!\n";
    echo "You can now use office_department in your queries.\n";
    
} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>