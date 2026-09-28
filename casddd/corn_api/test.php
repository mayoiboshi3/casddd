<?php

// Example configuration for XAMPP
$db_host = 'localhost';
$db_user = 'root';     // Default XAMPP username
$db_pass = '';         // Default XAMPP password is blank
$db_name = 'corncasd_db'; 

// 1. Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 2. Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Execute the query (This defines $result)
$sql = "SELECT COUNT(*) AS total FROM users"; // Replace 'users' with your actual table name
$result = $conn->query($sql);

// 4. Handle the query result
if ($result) {
    $row = $result->fetch_assoc();

    echo "Database connection successful!<br>";
    echo "Users in database: " . $row['total'];
} else {
    echo "Database query failed: " . $conn->error;
}

$conn->close();
?>