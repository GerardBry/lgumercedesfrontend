<?php
/**
 * Migration: Add Document Classification Fields
 * Adds fields for enhanced document entry system
 * 
 * Fields added:
 * - priority (Urgent, Critical, Normal)
 * - classification (Letter, Invitation, Travel-Related Communication)
 * - sub_classification
 * - date_received
 * - deadline
 * - file_path
 * - doc_sequence_number (for auto-increment ID like DOC-0001)
 */

require_once 'db_connect.php';

$migration_queries = [
    // Add new columns to documents table
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS priority ENUM('Normal', 'Urgent', 'Critical') DEFAULT 'Normal' AFTER document_type",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS classification VARCHAR(100) AFTER priority",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS sub_classification VARCHAR(100) AFTER classification",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS date_received TIMESTAMP NULL AFTER date_sent",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS deadline TIMESTAMP NULL AFTER date_received",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) AFTER deadline",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS sender_name VARCHAR(255) AFTER sender_id",
    
    "ALTER TABLE documents ADD COLUMN IF NOT EXISTS doc_sequence_number INT AFTER id",
    
    // Create indexes for new columns
    "ALTER TABLE documents ADD INDEX idx_priority (priority)",
    
    "ALTER TABLE documents ADD INDEX idx_classification (classification)",
    
    "ALTER TABLE documents ADD INDEX idx_deadline (deadline)"
];

// Track migration status
$success = true;
$messages = [];

try {
    $conn->begin_transaction();
    
    foreach ($migration_queries as $index => $query) {
        if (!$conn->query($query)) {
            // Check if error is about duplicate key (column already exists)
            if (strpos($conn->error, 'Duplicate column') === false && 
                strpos($conn->error, 'Duplicate key') === false) {
                throw new Exception("Query " . ($index + 1) . " failed: " . $conn->error);
            }
            // If it's a duplicate column/key error, we can continue
            $messages[] = "Query " . ($index + 1) . ": " . $conn->error;
        } else {
            $messages[] = "Query " . ($index + 1) . ": Success";
        }
    }
    
    $conn->commit();
    $success = true;
    $messages[] = "Migration completed successfully!";
    
} catch (Exception $e) {
    $conn->rollback();
    $success = false;
    $messages[] = "Migration failed: " . $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Document Fields</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .success { color: green; background: #e8f5e9; padding: 20px; border-radius: 5px; }
        .error { color: red; background: #ffebee; padding: 20px; border-radius: 5px; }
        .info { color: #1976d2; background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0; }
        ul { margin: 10px 0; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <?php if ($success): ?>
        <div class="success">
            <h2>✓ Migration Successful</h2>
            <ul>
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="error">
            <h2>✗ Migration Failed</h2>
            <ul>
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="info">
        <h3>Migration Details</h3>
        <p>Database: <strong><?php echo DB_NAME; ?></strong></p>
        <p>Table: <strong>documents</strong></p>
        <p>Fields Added:</p>
        <ul>
            <li><code>priority</code> - Urgent, Critical, Normal</li>
            <li><code>classification</code> - Letter, Invitation, Travel-Related Communication</li>
            <li><code>sub_classification</code> - Based on classification type</li>
            <li><code>date_received</code> - Date document was received</li>
            <li><code>deadline</code> - Document deadline</li>
            <li><code>file_path</code> - Path to uploaded file</li>
            <li><code>sender_name</code> - Name of sender (if not in system)</li>
        </ul>
    </div>
</body>
</html>
