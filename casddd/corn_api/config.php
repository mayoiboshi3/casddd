<?php
// Login uses fresh database reads and must not depend on the shared cache deployment.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'login.php') {
    require_once __DIR__ . '/../includes/data_cache.php';
}


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
