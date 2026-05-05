<?php
/**
 * Database Migration - Add Completion File Support
 * Ensures completion_file can store JSON-encoded file payloads for DB-backed previews.
 */

require_once 'config/db_connect.php';

try {
    echo "Starting database migration for completion file support...\n";
    
    // Check if completion_file exists in document_assignments
    $sql_check = "SHOW COLUMNS FROM document_assignments LIKE 'completion_file'";
    $result = $conn->query($sql_check);
    
    if ($result && $result->num_rows == 0) {
        // Add completion_file column to store JSON-encoded payloads (name, type, base64 data)
        $sql = "ALTER TABLE document_assignments ADD COLUMN completion_file LONGTEXT NULL AFTER completed_at";
        if ($conn->query($sql)) {
            echo "[✓] Added completion_file column to document_assignments table\n";
        } else {
            throw new Exception("Error adding completion_file: " . $conn->error);
        }
    } else {
        // Upgrade older varchar columns so they can store JSON payloads
        $sql = "ALTER TABLE document_assignments MODIFY COLUMN completion_file LONGTEXT NULL";
        if ($conn->query($sql)) {
            echo "[✓] Upgraded completion_file column to LONGTEXT\n";
        } else {
            throw new Exception("Error upgrading completion_file: " . $conn->error);
        }
    }

    // Create uploads directory if it doesn't exist
    $upload_dirs = [
        'uploads/completions/',
        'uploads/documents/',
    ];

    foreach ($upload_dirs as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "[✓] Created directory: $dir\n";
            } else {
                echo "[⚠] Could not create directory: $dir\n";
            }
        } else {
            echo "[ℹ] Directory already exists: $dir\n";
        }
    }

    // Create .htaccess to prevent direct access to uploaded files
    $htaccess_path = 'uploads/.htaccess';
    if (!file_exists($htaccess_path)) {
        $htaccess_content = "# Prevent direct access to files\nOptions -Indexes\n";
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            echo "[✓] Created .htaccess in uploads directory\n";
        } else {
            echo "[⚠] Could not create .htaccess file\n";
        }
    }

    echo "\n✅ Migration completed successfully!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    $conn->close();
    exit(1);
}
?>
