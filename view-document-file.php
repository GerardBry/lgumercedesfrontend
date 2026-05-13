<?php
/**
 * View Document File Handler - Staff
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
require_once 'config/db_connect.php';

try {
    // Construct full file path
    $full_path = __DIR__ . '/' . $file_path;
    $real_path = realpath($full_path);

    error_log('Full path: ' . $full_path . ', Real path: ' . $real_path . ', Exists: ' . (file_exists($full_path) ? 'yes' : 'no'));

    // Additional security check
    $uploads_dir = realpath(__DIR__ . '/uploads/documents/');
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
