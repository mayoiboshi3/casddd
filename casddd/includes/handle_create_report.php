<?php
/**
 * handle_create_report.php
 * -------------------------------------------------------------------------
 * Handles the "New Field Case Report" submission (create_report POST).
 * Pulled out of reports.php to keep that file lighter.
 *
 * Expects $conn (mysqli connection) to already be available — this file is
 * included from reports.php AFTER src/db_config.php has run.
 *
 * Requires the following extra tables (see field_report_upgrade.sql):
 *   - disease_case_diseases (case_id, disease_id)  -> supports 3+ diseases per case
 *   - disease_case_photos   (case_id, filename)     -> supports multiple photos per case
 */

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_report'])) {

    $ref_id       = "REF-" . date("Y") . "-" . strtoupper(substr(md5(time()), 0, 4));
    $farmer_name  = mysqli_real_escape_string($conn, $_POST['farmer_name']);
    $brgy_id      = mysqli_real_escape_string($conn, $_POST['brgy_id']);
    $stage        = mysqli_real_escape_string($conn, $_POST['growth_stage']);
    $date_planted = mysqli_real_escape_string($conn, $_POST['date_planted']);
    $desc         = mysqli_real_escape_string($conn, $_POST['description']);
    $severity     = mysqli_real_escape_string($conn, $_POST['severity']);

    // ── Disease selection — must pick at least 3 diseases ──
    $disease_ids_raw = (isset($_POST['disease_ids']) && is_array($_POST['disease_ids'])) ? $_POST['disease_ids'] : [];
    $disease_ids      = array_values(array_unique(array_filter(array_map('intval', $disease_ids_raw))));

    if (count($disease_ids) < 3) {
        echo "<script>alert('Please select at least 3 diseases before submitting.'); window.history.back();</script>";
        exit;
    }

    // First selected disease is stored on disease_cases.disease_id for backward
    // compatibility with older parts of the system that expect a single disease.
    $primary_disease_id = $disease_ids[0];

    // ── Handle multiple disease photo uploads ──
    $uploaded_photos = [];
    if (!empty($_FILES['disease_photos']['name'][0])) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        foreach ($_FILES['disease_photos']['name'] as $i => $name) {
            if ($_FILES['disease_photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['disease_photos']['type'][$i], $allowed_types)) continue;

            $ext      = pathinfo($name, PATHINFO_EXTENSION);
            $filename = time() . '_evidence_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
            $dest     = 'uploads/' . $filename;

            if (move_uploaded_file($_FILES['disease_photos']['tmp_name'][$i], $dest)) {
                $uploaded_photos[] = $filename;
            }
        }
    }
    // Legacy single-photo column keeps the first uploaded photo (or NULL).
    $photo_evidence_val = !empty($uploaded_photos)
        ? "'" . mysqli_real_escape_string($conn, $uploaded_photos[0]) . "'"
        : "NULL";

    $full_desc = "[FARMER:" . $farmer_name . "]\n" . $desc;
    $full_desc = mysqli_real_escape_string($conn, $full_desc);

    $insertQuery = "INSERT INTO disease_cases
        (reference_id, disease_id, farm_id, farmer_id, barangay_id, reported_by, growth_stage, date_planted, description, severity, photo_evidence, status, report_date)
        VALUES ('$ref_id', '$primary_disease_id', 0, 0, '$brgy_id', 0, '$stage', '$date_planted', '$full_desc', '$severity', $photo_evidence_val, 'pending', NOW())";

    if (mysqli_query($conn, $insertQuery)) {
        $new_case_id = mysqli_insert_id($conn);

        // Link every selected disease to this case (min. 3 rows)
        foreach ($disease_ids as $did) {
            mysqli_query($conn, "INSERT INTO disease_case_diseases (case_id, disease_id) VALUES ($new_case_id, $did)");
        }

        // Link every uploaded photo to this case
        foreach ($uploaded_photos as $filename) {
            $esc_filename = mysqli_real_escape_string($conn, $filename);
            mysqli_query($conn, "INSERT INTO disease_case_photos (case_id, filename) VALUES ($new_case_id, '$esc_filename')");
        }

        echo "<script>alert('Report Successfully Submitted for Review!'); window.location='reports.php?tab=pending';</script>";
        exit;
    }
}