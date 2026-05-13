<?php
/**
 * View Document File Handler - Administrative
 * Safely displays uploaded document files
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
error_log('View file request - User: ' . $user_id . ', Requested path: ' . $file_path);

// Prevent directory traversal attacks
$file_path = str_replace(['../', '..\\'], '', $file_path);
$file_path = ltrim($file_path, '/\\');

error_log('Sanitized path: ' . $file_path);
require_once '../config/db_connect.php';

try {
    // First check if document_uploads table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'document_uploads'");
    $uploads_table_exists = $table_check->num_rows > 0;

    $file_found = false;

    // Check document_uploads table first (new uploaded files)
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
                error_log('File path match found in document_uploads!');
            }
            $stmt_uploads->close();
        }
    }

    // If not found in uploads, check documents table (original documents)
    if (!$file_found) {
        $sql = "SELECT d.id, d.notes, d.file_path FROM documents d
                WHERE d.created_by = ? 
                UNION
                SELECT d.id, d.notes, d.file_path FROM documents d
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
            // Check database column first
            $stored_path = str_replace(['../', '..\\'], '', $row['file_path'] ?? '');
            $stored_path = ltrim($stored_path, '/\\');
            
            error_log('Checking DB column path: ' . $stored_path . ' against requested: ' . $file_path);
            
            if ($stored_path === $file_path && !empty($stored_path)) {
                $file_found = true;
                error_log('File path match found in database column!');
                break;
            }
            
            // Fall back to notes JSON for old documents
            $notes = json_decode($row['notes'], true) ?? [];
            $stored_path_json = str_replace(['../', '..\\'], '', $notes['file_path'] ?? '');
            $stored_path_json = ltrim($stored_path_json, '/\\');
            
            error_log('Checking notes JSON path: ' . $stored_path_json . ' against requested: ' . $file_path);
            
            if ($stored_path_json === $file_path && !empty($stored_path_json)) {
                $file_found = true;
                error_log('File path match found in notes JSON!');
                break;
            }
        }

        $stmt->close();
    }

    if (!$file_found) {
        http_response_code(403);
        error_log('Access denied - file path not found in user documents');
        echo 'Access denied';
        $conn->close();
        exit;
    }

    // Construct full file path
    $full_path = __DIR__ . '/../' . $file_path;
    $real_path = realpath($full_path);

    error_log('Full path: ' . $full_path . ', Real path: ' . $real_path . ', Exists: ' . (file_exists($full_path) ? 'yes' : 'no'));

    // Additional security check
    $uploads_dir = realpath(__DIR__ . '/../uploads/documents/');
    if ($real_path === false || !file_exists($real_path) || strpos($real_path, $uploads_dir) !== 0) {
        http_response_code(404);
        error_log('File not found or not in uploads dir - Real path: ' . $real_path . ', Uploads dir: ' . $uploads_dir);
        echo 'File not found';
        $conn->close();
        exit;
    }

    // Serve the file for viewing
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
    
    error_log('Serving file - Name: ' . $file_name . ', Type: ' . $file_type . ', Size: ' . filesize($real_path));
    
    $file_size = filesize($real_path);

    // For images and PDFs, display inline
    $inline_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'];
    $disposition = in_array($file_type, $inline_types) ? 'inline' : 'attachment';

    header('Content-Type: ' . $file_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: ' . $disposition . '; filename="' . $file_name . '"');
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
