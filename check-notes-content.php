<?php
require_once 'config/db_connect.php';

$sql = "SELECT id, tracking_number, notes FROM documents WHERE (file_path IS NULL OR file_path = '') LIMIT 5";
$result = $conn->query($sql);

echo "<h2>Documents with Empty file_path - Notes Content Check</h2>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<h3>Doc ID: {$row['id']} - Tracking: {$row['tracking_number']}</h3>";
        echo "<p>Notes JSON:</p>";
        if ($row['notes']) {
            $notes = json_decode($row['notes'], true);
            echo "<pre>";
            print_r($notes);
            echo "</pre>";
        } else {
            echo "<p>(No notes)</p>";
        }
        echo "<hr>";
    }
} else {
    echo "<p>All documents have file_path populated!</p>";
}

$conn->close();
?>
