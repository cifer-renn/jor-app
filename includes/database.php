<?php
$db_server = 'localhost';
$db_user = 'root';
$db_pass = ''; // Default for XAMPP
$db_name = 'job_order_db';

// Function to check database connection status
function checkDatabaseConnection($connection) {
    if (!$connection) {
        return false;
    }
    
    if ($connection->connect_error) {
        return false;
    }
    
    if (!$connection->ping()) {
        return false;
    }
    
    return true;
}

try {
    $conn = new mysqli($db_server, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset("utf8");
    
} catch (mysqli_sql_exception $e) {
    // If connection fails, display an error message.
    // In a production environment, you would log this error and show a user-friendly message.
    die("Database connection failed: " . $e->getMessage());
}

// Optional: Check if database exists
if (!checkDatabaseConnection($conn)) {
    die("Database connection is not working properly");
}
?> 