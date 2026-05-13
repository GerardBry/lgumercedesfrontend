<?php
require_once 'config/db_connect.php';

// Get the Seminar Workshop document
$result = $conn->query('SELECT id, notes FROM documents WHERE title = "Seminar Workshop" LIMIT 1');
if ($result->num_rows > 0) {
    $doc = $result->fetch_assoc();
    
    // Try to parse existing JSON to extract any metadata
    $notes_data = json_decode($doc['notes'], true);
    if (!is_array($notes_data)) {
        $notes_data = [];
    }
    
    // Extract values
    $sender_name = $notes_data['sender'] ?? 'Provincial Kapitolyo';
    $date_received = $notes_data['date_received'] ?? '2026-05-10';
    $classification = $notes_data['classification'] ?? 'Letter';
    $sub_classification = $notes_data['sub_classification'] ?? 'Request Letter';
    $priority = $notes_data['priority'] ?? 'Normal';
    $deadline = $notes_data['deadline'] ?? null;
    $file_path = $notes_data['file_path'] ?? null;
    
    // Update the document with proper data
    $update_sql = "UPDATE documents SET 
                   sender_name = ?, 
                   date_received = ?, 
                   classification = ?, 
                   sub_classification = ?, 
                   priority = ?,
                   deadline = ?,
                   file_path = ?
                   WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $date_received_ts = date('Y-m-d H:i:s', strtotime($date_received));
        $deadline_ts = $deadline ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
        
        $stmt->bind_param('ssssssis', 
            $sender_name, 
            $date_received_ts, 
            $classification, 
            $sub_classification, 
            $priority,
            $deadline_ts,
            $file_path,
            $doc['id']
        );
        
        if ($stmt->execute()) {
            echo "SUCCESS: Updated Seminar Workshop document\n";
        } else {
            echo "ERROR: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
} else {
    echo "Document not found\n";
}
?>
