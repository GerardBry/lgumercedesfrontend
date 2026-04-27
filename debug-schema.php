<?php
/**
 * DEBUG: Check Database Schema for document_assignments table
 */
session_start();

require_once 'config/db_connect.php';

echo "<h1>DEBUG - Database Schema Analysis</h1>";

echo "<h2>document_assignments Table Structure</h2>";
$result = $conn->query("SHOW COLUMNS FROM document_assignments");

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>" . $row['Field'] . "</strong></td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . ($row['Key'] ?: '-') . "</td>";
    echo "<td>" . ($row['Default'] !== null ? $row['Default'] : '<em>NULL</em>') . "</td>";
    echo "<td>" . ($row['Extra'] ?: '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Triggers on document_assignments Table</h2>";
$result_triggers = $conn->query("SHOW TRIGGERS LIKE 'document_assignments'");
$trigger_count = $result_triggers->num_rows;

if ($trigger_count > 0) {
    echo "<p><strong>" . $trigger_count . " triggers found!</strong></p>";
    while ($trigger = $result_triggers->fetch_assoc()) {
        echo "<p><strong>Trigger:</strong> " . $trigger['Trigger'] . "</p>";
        echo "<pre>" . htmlspecialchars($trigger['Statement']) . "</pre>";
    }
} else {
    echo "<p>No triggers found.</p>";
}

echo "<h2>Foreign Keys on document_assignments</h2>";
$result_fk = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'document_assignments' AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($result_fk->num_rows > 0) {
    while ($fk = $result_fk->fetch_assoc()) {
        echo "<p>" . $fk['CONSTRAINT_NAME'] . ": " . $fk['COLUMN_NAME'] . " -> " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "</p>";
    }
} else {
    echo "<p>No foreign keys defined.</p>";
}

$conn->close();
?>
