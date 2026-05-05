<?php
/**
 * Get Document File - Safe file serving handler
 * Streams uploaded completion papers from the database.
 */
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$assignment_id = intval($_GET['assignment_id'] ?? 0);
$legacy_file = trim($_GET['file'] ?? '');

if ($assignment_id <= 0 && $legacy_file === '') {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

$user_id = intval($_SESSION['user_id']);
require_once 'config/db_connect.php';

function decodeCompletionPayload($payload)
{
    if (empty($payload)) {
        return null;
    }

    $decoded = json_decode($payload, true);
    if (is_array($decoded)) {
        // New format: store path reference
        if (isset($decoded['path'])) {
            return [
                'name' => $decoded['name'] ?? 'completion-file',
                'type' => $decoded['type'] ?? 'application/octet-stream',
                'path' => $decoded['path'],
                'data' => null, // No embedded data in new format
            ];
        }
        // Legacy format: base64 encoded data (for backwards compatibility)
        if (isset($decoded['data'])) {
            return [
                'name' => $decoded['name'] ?? 'completion-file',
                'type' => $decoded['type'] ?? 'application/octet-stream',
                'data' => base64_decode($decoded['data'], true),
                'path' => null,
            ];
        }
    }

    return [
        'name' => $payload,
        'type' => null,
        'data' => null,
        'path' => null,
    ];
}

if ($assignment_id > 0) {
    $sql = "SELECT da.id, da.completion_file, d.created_by, da.assigned_to, da.status
        FROM document_assignments da
        JOIN documents d ON da.document_id = d.id
        WHERE da.id = ?
        AND (d.created_by = ? OR da.assigned_to = ?)
        AND da.status = 'Completed'
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo 'Database error';
        $conn->close();
        exit;
    }

    $stmt->bind_param('iii', $assignment_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        http_response_code(403);
        echo 'Access denied';
        exit;
    }

    $assignment = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    $payload = decodeCompletionPayload($assignment['completion_file'] ?? '');
    
    // Handle new format with file path
    if ($payload && !empty($payload['path'])) {
        $file_path = __DIR__ . '/' . $payload['path'];
        $real_path = realpath($file_path);
        $base_path = realpath(__DIR__ . '/uploads/completions/');

        if ($real_path !== false && $base_path !== false && strpos($real_path, $base_path) === 0 && file_exists($real_path) && is_file($real_path)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $real_path);
            finfo_close($finfo);

            if ($mime_type === false) {
                $mime_type = $payload['type'] ?: 'application/octet-stream';
            }

            header('Content-Type: ' . $mime_type);
            header('Content-Length: ' . filesize($real_path));
            header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($real_path);
            exit;
        }
    }
    
    // Handle legacy format with base64-encoded data (backwards compatibility)
    if ($payload && !empty($payload['data'])) {
        $mime_type = $payload['type'] ?: 'application/octet-stream';
        $file_name = $payload['name'] ?: 'completion-file';

        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . strlen($payload['data']));
        header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $payload['data'];
        exit;
    }

    if (!empty($payload['name'])) {
        $legacy_file = $payload['name'];
    }
}

if ($legacy_file === '') {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Legacy fallback for older records stored on disk.
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $legacy_file)) {
    http_response_code(400);
    echo 'Invalid filename';
    exit;
}

$file_path = __DIR__ . '/uploads/completions/' . $legacy_file;
$real_path = realpath($file_path);
$base_path = realpath(__DIR__ . '/uploads/completions/');

if ($real_path === false || $base_path === false || strpos($real_path, $base_path) !== 0) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if (!file_exists($real_path) || !is_file($real_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $real_path);
finfo_close($finfo);

if ($mime_type === false) {
    $mime_type = 'application/octet-stream';
}

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($real_path);
exit;
?>
