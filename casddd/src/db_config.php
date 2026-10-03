<?php
$host     = "localhost";
$dbname   = "u250976479_corncasd_db";
$username = "u250976479_Corn_Admin";
$password = "CornCasd_26";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

function migrate_role_enum(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $col = $res ? $res->fetch_assoc() : null;
    if (!$col) return;

    if (strpos($col['Type'], "'agri1'") !== false) return; // already migrated

    $conn->query("ALTER TABLE `users`
        MODIFY `role` ENUM('casd','agri1','agri2','admin') DEFAULT 'agri1'");
    $conn->query("UPDATE `users` SET `role` = 'agri1' WHERE `role` = 'casd'");
    $conn->query("ALTER TABLE `users`
        MODIFY `role` ENUM('agri1','agri2','admin') NOT NULL DEFAULT 'agri1'");
}

migrate_role_enum($conn);