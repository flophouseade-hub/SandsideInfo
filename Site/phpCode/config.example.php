<?php
// Database configuration template
// Copy this file to config.php and fill in your actual credentials

define('DB_HOST', 'your-database-host');
define('DB_USER', 'your-database-username');
define('DB_PASS', 'your-database-password');
define('DB_NAME', 'your-database-name');

// Create database connection
function getDatabaseConnection() {
    $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$con) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    return $con;
}
