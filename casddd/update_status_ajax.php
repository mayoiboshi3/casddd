<?php
require_once __DIR__ . "/src/db_config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reference_id'])) {
    $ref_id = mysqli_real_escape_string($conn, $_POST['reference_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    // Update the status to 'pending' because it has been viewed by CASD
    $query = "UPDATE disease_cases SET status = '$status' WHERE reference_id = '$ref_id'";
    
    if (mysqli_query($conn, $query)) {
        echo "Success";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>