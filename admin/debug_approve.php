<?php
/**
 * Debug script to test the approve function
 */

session_start();
require_once '../config/db_connect.php';

echo "<h1>Debug: Testing Approve Function</h1>";

// Check database connection
echo "<h3>1. Database Connection</h3>";
if ($conn) {
    echo "✓ Connected to database<br>";
} else {
    die("✗ Failed to connect to database: " . mysqli_connect_error());
}

// Check users table structure
echo "<h3>2. Users Table Structure</h3>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✗ Error describing users table: " . $conn->error;
}

// Get all pending users
echo "<h3>3. Current Pending Users</h3>";
$result = $conn->query("SELECT id, first_name, last_name, status FROM users WHERE status = 'Pending' LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No pending users found.";
    }
} else {
    echo "✗ Error querying pending users: " . $conn->error;
}

// Test update with first pending user
echo "<h3>4. Test Update Query</h3>";
$check = $conn->query("SELECT id FROM users WHERE status = 'Pending' LIMIT 1");
if ($check && $check->num_rows > 0) {
    $user = $check->fetch_assoc();
    $test_id = $user['id'];
    
    echo "Testing update for user ID: " . $test_id . "<br><br>";
    
    // Show before state
    $before = $conn->query("SELECT id, status FROM users WHERE id = " . intval($test_id));
    $before_data = $before->fetch_assoc();
    echo "Before update: Status = " . htmlspecialchars($before_data['status']) . "<br>";
    
    // Attempt update
    echo "<br><strong>Executing query:</strong> UPDATE users SET status = 'Approved' WHERE id = " . intval($test_id) . "<br>";
    $update = $conn->query("UPDATE users SET status = 'Approved' WHERE id = " . intval($test_id));
    
    if ($update === false) {
        echo "✗ Update failed: " . $conn->error . "<br>";
    } else {
        echo "✓ Update query executed<br>";
        echo "Affected rows: " . $conn->affected_rows . "<br>";
        
        // Show after state
        $after = $conn->query("SELECT id, status FROM users WHERE id = " . intval($test_id));
        $after_data = $after->fetch_assoc();
        echo "After update: Status = " . htmlspecialchars($after_data['status']) . "<br>";
        
        if ($after_data['status'] === 'Approved') {
            echo "<span style='color: green;'><strong>✓ Update successful!</strong></span>";
        } else {
            echo "<span style='color: red;'><strong>✗ Status did not change. Current status: " . htmlspecialchars($after_data['status']) . "</strong></span>";
        }
    }
} else {
    echo "No pending users available for testing.";
}

// Check if there are any triggers on the users table
echo "<h3>5. Check for Triggers</h3>";
$triggers = $conn->query("SHOW TRIGGERS LIKE 'users'");
if ($triggers && $triggers->num_rows > 0) {
    echo "⚠ Triggers found on users table:<br>";
    while ($trigger = $triggers->fetch_assoc()) {
        echo "- " . htmlspecialchars($trigger['Trigger']) . " (" . htmlspecialchars($trigger['Timing']) . " " . htmlspecialchars($trigger['Event']) . ")<br>";
    }
} else {
    echo "✓ No triggers found on users table";
}

// Check for any DEFAULT value on status column that might be resetting it
echo "<h3>6. Status Column Details</h3>";
$column_info = $conn->query("SHOW FULL COLUMNS FROM users WHERE Field = 'status'");
if ($column_info) {
    $col = $column_info->fetch_assoc();
    echo "<pre>";
    print_r($col);
    echo "</pre>";
}

$conn->close();
?>

<hr>
<h3>Instructions:</h3>
<ol>
    <li>Check the table structure above - the 'status' column should allow NULL or have a proper default</li>
    <li>If there are triggers, they might be overriding your updates</li>
    <li>The test update should show if the query works at all</li>
    <li>Look for any DEFAULT values that might be resetting the status</li>
</ol>

<a href="accounts.php">Back to Accounts</a>
