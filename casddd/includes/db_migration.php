<?php
/**
 * db_migration.php
 * -------------------------------------------------------------------------
 * Self-healing setup check for the multi-disease / multi-photo field report
 * feature. Runs automatically every time reports.php loads. It:
 *
 *   1. Checks if `diseases` has a PRIMARY KEY on disease_id — adds one if
 *      missing (required before the FK tables below can be created).
 *   2. Checks if `disease_case_diseases` exists — creates it if missing.
 *   3. Checks if `disease_case_photos` exists — creates it if missing.
 *   4. Backfills old single disease_id / photo_evidence data into the new
 *      tables, but ONLY the first time each table is created.
 *
 * Every step is wrapped in a condition so it's safe to run on every page
 * load — once everything is in place, this file does nothing but a few
 * quick existence checks.
 *
 * Requires $conn (mysqli connection) to already exist.
 */

// ── Helper: does a table exist? ──
function tableExists($conn, $tableName) {
    $safe = mysqli_real_escape_string($conn, $tableName);
    $res  = mysqli_query($conn, "SHOW TABLES LIKE '$safe'");
    return $res && mysqli_num_rows($res) > 0;
}

// ── Helper: does a table already have a PRIMARY KEY? ──
function hasPrimaryKey($conn, $tableName) {
    $safe = mysqli_real_escape_string($conn, $tableName);
    $res  = mysqli_query($conn, "SHOW KEYS FROM `$safe` WHERE Key_name = 'PRIMARY'");
    return $res && mysqli_num_rows($res) > 0;
}

// ── 1. Ensure `diseases` has a PRIMARY KEY on disease_id ──
if (!hasPrimaryKey($conn, 'diseases')) {

    // Guard: a PRIMARY KEY can't be added if disease_id has duplicate values.
    $dupCheck = mysqli_query($conn, "SELECT disease_id FROM diseases GROUP BY disease_id HAVING COUNT(*) > 1 LIMIT 1");

    if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
        // Can't safely auto-fix duplicates — stop with a clear message
        // instead of letting every later query fail mysteriously.
        die("Setup error: the 'diseases' table has duplicate disease_id values, "
          . "so a PRIMARY KEY can't be added automatically. Please resolve the "
          . "duplicates manually, then reload this page.");
    }

    if (!mysqli_query($conn, "ALTER TABLE `diseases` ADD PRIMARY KEY (`disease_id`)")) {
        die("Setup error: failed to add PRIMARY KEY to 'diseases' table — " . mysqli_error($conn));
    }
}

// ── 2. Ensure `disease_case_diseases` exists ──
if (!tableExists($conn, 'disease_case_diseases')) {

    $ok = mysqli_query($conn, "
        CREATE TABLE `disease_case_diseases` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `case_id` int(11) NOT NULL,
            `disease_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `case_id` (`case_id`),
            KEY `disease_id` (`disease_id`),
            CONSTRAINT `fk_dcd_case` FOREIGN KEY (`case_id`) REFERENCES `disease_cases` (`case_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_dcd_disease` FOREIGN KEY (`disease_id`) REFERENCES `diseases` (`disease_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!$ok) {
        die("Setup error: failed to create 'disease_case_diseases' table — " . mysqli_error($conn));
    }

    // Backfill existing single-disease cases (only runs the moment the table is first created)
    mysqli_query($conn, "
        INSERT INTO disease_case_diseases (case_id, disease_id)
        SELECT case_id, disease_id FROM disease_cases WHERE disease_id IS NOT NULL
    ");
}

// ── 3. Ensure `disease_case_photos` exists ──
if (!tableExists($conn, 'disease_case_photos')) {

    $ok = mysqli_query($conn, "
        CREATE TABLE `disease_case_photos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `case_id` int(11) NOT NULL,
            `filename` varchar(255) NOT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `case_id` (`case_id`),
            CONSTRAINT `fk_dcp_case` FOREIGN KEY (`case_id`) REFERENCES `disease_cases` (`case_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!$ok) {
        die("Setup error: failed to create 'disease_case_photos' table — " . mysqli_error($conn));
    }

    // Backfill existing single-photo cases (only runs the moment the table is first created)
    mysqli_query($conn, "
        INSERT INTO disease_case_photos (case_id, filename)
        SELECT case_id, photo_evidence FROM disease_cases
        WHERE photo_evidence IS NOT NULL AND photo_evidence <> ''
    ");
}