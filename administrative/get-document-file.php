<?php
/**
 * Get Document File Handler - Administrative
 * Safely serves uploaded document files
 */
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$file_path = trim($_GET['path'] ?? '');

if (empty($file_path)) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Debug logging
error_log('Download file request - User: ' . $user_id . ', Requested path: ' . $file_path);

// Prevent directory traversal attacks
$file_path = str_replace(['../', '..\\'], '', $file_path);
$file_path = ltrim($file_path, '/\\');

error_log('Sanitized path: ' . $file_path);
require_once '../config/db_connect.php';

try {
    $file_found = false;

    // Allow downloads for uploaded files stored in document_uploads
    $table_check = $conn->query("SHOW TABLES LIKE 'document_uploads'");
    $uploads_table_exists = $table_check && $table_check->num_rows > 0;

    if ($uploads_table_exists) {
        $sql_uploads = "SELECT du.file_path FROM document_uploads du
                        JOIN document_assignments da ON du.assignment_id = da.id
                        JOIN documents d ON da.document_id = d.id
                        WHERE (da.assigned_to = ? OR d.created_by = ?) AND du.file_path = ?
                        LIMIT 1";
        $stmt_uploads = $conn->prepare($sql_uploads);
        if ($stmt_uploads) {
            $stmt_uploads->bind_param('iis', $user_id, $user_id, $file_path);
            $stmt_uploads->execute();
            $result_uploads = $stmt_uploads->get_result();
            if ($result_uploads && $result_uploads->num_rows > 0) {
                $file_found = true;
            }
            $stmt_uploads->close();
        }
    }

    if (!$file_found) {
        // Verify the file path belongs to a document created by the user OR assigned to them
        $sql = "SELECT d.id, d.notes FROM documents d
                WHERE d.created_by = ? 
                UNION
                SELECT d.id, d.notes FROM documents d
                JOIN document_assignments da ON d.id = da.document_id
                WHERE da.assigned_to = ?
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error');
        }

        $stmt->bind_param('ii', $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $notes = json_decode($row['notes'], true) ?? [];
            $stored_path = str_replace(['../', '..\\'], '', $notes['file_path'] ?? '');
            $stored_path = ltrim($stored_path, '/\\');
            
            if ($stored_path === $file_path) {
                $file_found = true;
                break;
            }
        }

        $stmt->close();
    }

    if (!$file_found) {
        http_response_code(403);
        echo 'Access denied';
        $conn->close();
        exit;
    }

    // Construct full file path
    $full_path = __DIR__ . '/../' . $file_path;
    $real_path = realpath($full_path);

    // Additional security check
    $uploads_dir = realpath(__DIR__ . '/../uploads/documents/');
    if ($real_path === false || !file_exists($real_path) || strpos($real_path, $uploads_dir) !== 0) {
        http_response_code(404);
        echo 'File not found';
        $conn->close();
        exit;
    }

    // Serve the file
    $file_name = basename($real_path);
    
    // Get MIME type - use finfo if available
    $file_type = 'application/octet-stream';
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $file_type = finfo_file($finfo, $real_path);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $file_type = mime_content_type($real_path);
    }
    
    $file_size = filesize($real_path);

    header('Content-Type: ' . $file_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($real_path);

    $conn->close();
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Server error';
    if (isset($conn)) {
        $conn->close();
    }
    exit;
}
