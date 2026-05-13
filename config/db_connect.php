<?php
/**
 * Database Configuration
 * MO-ATWMS System
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'mo_atwms');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");
