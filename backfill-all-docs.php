<?php
require_once 'config/db_connect.php';

// Get all documents without proper column data
$result = $conn->query('SELECT id, notes FROM documents WHERE (sender_name IS NULL OR sender_name = "") LIMIT 20');
if ($result->num_rows > 0) {
    $count = 0;
    while ($doc = $result->fetch_assoc()) {
        // Try to parse existing JSON to extract metadata
        $notes_data = json_decode($doc['notes'], true);
        if (!is_array($notes_data)) {
            $notes_data = [];
        }
        
        // Extract values
        $sender_name = $notes_data['sender'] ?? null;
        $date_received = $notes_data['date_received'] ?? null;
        $classification = $notes_data['classification'] ?? null;
        $sub_classification = $notes_data['sub_classification'] ?? null;
        $priority = $notes_data['priority'] ?? null;
        $deadline = $notes_data['deadline'] ?? null;
        $file_path = $notes_data['file_path'] ?? null;
        
        if (!$sender_name || !$date_received || !$classification || !$priority) {
            continue; // Skip if missing data
        }
        
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
            
            $stmt->bind_param('ssssssi', 
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
                echo "Updated doc ID: " . $doc['id'] . "\n";
                $count++;
            }
            $stmt->close();
        }
    }
    echo "Backfilled $count documents\n";
} else {
    echo "No documents to backfill\n";
}
?>
