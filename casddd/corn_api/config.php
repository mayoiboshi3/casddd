<?php

$host     = getenv('MYSQLHOST')     ?: '127.0.0.1'; // or 'localhost'
$port     = getenv('MYSQLPORT')     ?: 3306;
$user     = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';        // XAMPP default is empty
$database = getenv('MYSQLDATABASE') ?: 'corncasd_db';

$conn = new mysqli($host, $user, $password, $database, (int)$port);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

?>