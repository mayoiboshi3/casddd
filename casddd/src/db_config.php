<?php
$servername = "localhost";
$username = "root";
$password = "882372";
$dbname = "corncasd_db"; // Based on your file list

// Ensure the variable name is $conn
$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
<?php
/**
 * Self-healing migration: split 'role' enum from ('casd','admin')
 * into ('agri1','agri2','admin'). Safe to run on every page load —
 * it checks the current enum definition before touching anything.
 *
 * Drop this into db_migration.php alongside your other migration steps.
 */
function migrate_role_enum(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    $col = $res ? $res->fetch_assoc() : null;
    if (!$col) return; // table/column not present yet, nothing to do

    $type = $col['Type']; // e.g. "enum('casd','admin')"

    // Already migrated — nothing to do
    if (strpos($type, "'agri1'") !== false) {
        return;
    }

    // Step 1: widen enum so old + new values coexist
    $conn->query("ALTER TABLE `users`
        MODIFY `role` ENUM('casd','agri1','agri2','admin') DEFAULT 'agri1'");

    // Step 2: move existing 'casd' rows to 'agri1' (default bucket)
    $conn->query("UPDATE `users` SET `role` = 'agri1' WHERE `role` = 'casd'");

    // Step 3: narrow enum to final set
    $conn->query("ALTER TABLE `users`
        MODIFY `role` ENUM('agri1','agri2','admin') NOT NULL DEFAULT 'agri1'");
}

// Call it, e.g.:
migrate_role_enum($conn);