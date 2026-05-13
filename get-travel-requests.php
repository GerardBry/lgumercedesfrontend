<?php
/**
 * Get Travel Requests Related to a Document
 * Returns travel requests (document_type='Travel Request') linked to a parent document
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$parent_document_id = intval($_GET['parent_document_id'] ?? 0);
$parent_document_id_text = (string)$parent_document_id;

if ($parent_document_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parent document ID']);
    exit;
}

require_once 'config/db_connect.php';

try {
    // Get travel requests linked to the parent document
    // Travel requests are marked with document_type='Travel Request'
    // Match either parent_document_id or parent_tracking_code so older and newer links both work
    $sql = "SELECT 
            d.id,
            d.title,
            d.description,
            d.tracking_number,
            d.document_type,
            d.date_sent,
            d.created_by,
            d.notes,
            JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.noted_by')) as noted_by,
            d.sender_name,
            u.first_name,
            u.last_name
        FROM documents d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.document_type = 'Travel Request'
                AND EXISTS (
                        SELECT 1
                        FROM documents parent
                        WHERE parent.id = ?
                            AND (
                                JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_document_id')) = CAST(parent.id AS CHAR)
                                OR JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_document_id')) = ?
                                OR JSON_UNQUOTE(JSON_EXTRACT(d.notes, '$.parent_tracking_code')) = parent.tracking_number
                            )
                )
        ORDER BY d.date_sent DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('is', $parent_document_id, $parent_document_id_text);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
