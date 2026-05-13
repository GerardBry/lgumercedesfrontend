<?php
require_once 'config/db_connect.php';

$result = $conn->query('SELECT id, role FROM users WHERE role="Administrative Assistant"');
echo "Admin Users: " . $result->num_rows . "\n";
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
}
?>
