<?php
/**
 * process_farmer.php
 * Handles: bulk_save, update_farmer_info
 *
 * CHANGE: farmers table no longer has farmer_id as PK.
 * Updates are now keyed by farmer_name (the only stable unique identifier).
 *
 * Security practices applied:
 *  - Session-based authentication guard          (Access Control)
 *  - trim() + sanitization on all string inputs   (Sanitization)
 *  - Pattern & range checks before DB write        (Verification)
 *  - Prepared statements for all queries           (SQL Injection prevention)
 *  - password_hash(BCRYPT) for passwords           (Secure storage)
 *  - MIME-type check + size cap on uploads         (File Safety)
 *  - farmer_name collision checks                  (Data Integrity)
 */

/* ──────────────────────────────────────────────────
   AUTH GUARD
   Only a logged-in staff/admin session may create or modify farmer
   records. Uses the same session_guard.php convention as every other
   protected page (dashboard, farmers, reports) — starts the session
   and redirects to index.php (the app's actual login page) if the
   visitor isn't logged in. This used to check made-up session keys
   ($_SESSION['admin_id'] / 'is_logged_in') and redirect to a
   nonexistent "login.php", which 404'd instead of bouncing to login.

   findFirst() below searches a few likely spots for each file instead
   of assuming one fixed path. This project has ended up with the
   same files in more than one folder (e.g. both
   C:\xampp\htdocs\casddd\ and C:\xampp\htdocs\casddd\src\), so this
   keeps process_farmer.php working no matter which copy of it is the
   one actually being run, until that folder layout gets cleaned up.

   Important: the require_once itself happens here, at the top level
   of the script — NOT inside findFirst(). db_config.php defines $conn,
   and a require_once done from inside a function would trap $conn as
   a local variable in that function instead of a global one, which is
   exactly what caused the "$conn is null" error below.
────────────────────────────────────────────────── */
function findFirst(string $filename, array $extraDirs = []): ?string {
    $dirs = array_merge(
        [__DIR__, __DIR__ . '/src', dirname(__DIR__), dirname(__DIR__) . '/src'],
        $extraDirs
    );
    foreach ($dirs as $dir) {
        $path = $dir . '/' . $filename;
        if (is_file($path)) return $path;
    }
    return null;
}

$__sessionGuardPath = findFirst('session_guard.php');
if ($__sessionGuardPath === null) {
    http_response_code(500);
    die('Server setup error: could not find session_guard.php.');
}
require_once $__sessionGuardPath;

$__dbConfigPath = findFirst('db_config.php');
if ($__dbConfigPath === null) {
    http_response_code(500);
    die('Server setup error: could not find db_config.php.');
}
require_once $__dbConfigPath;
unset($__sessionGuardPath, $__dbConfigPath);

// db_config.php (just included, via a dynamic path) defines $conn.
// intelephense can't trace a require_once on a variable path, so without
// this line it flags every later use of $conn as "undefined" even though
// it's genuinely in scope at runtime. The line below is a real statement
// (not just a comment) so the analyzer registers $conn as defined from
// here on — it's a no-op at runtime since $conn already holds the mysqli
// connection object at this point.
/** @var mysqli $conn */
$conn = $conn ?? null;

/* ──────────────────────────────────────────────────
   HELPERS
────────────────────────────────────────────────── */

function clean(string $val): string {
    return trim(str_replace("\0", '', $val));
}

function validPhone(string $phone): bool {
    return (bool) preg_match('/^09[0-9]{9}$/', $phone);
}

function validAge(int $age): bool {
    return $age >= 12 && $age <= 120;
}

function uploadPhoto(array $file): ?string {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true))  return null;
    if ($file['size'] > 5 * 1024 * 1024)  return null;

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dir  = __DIR__ . '/uploads/';

    if (!is_dir($dir)) mkdir($dir, 0755, true);

    return move_uploaded_file($file['tmp_name'], $dir . $name) ? $name : null;
}

function deleteOldPhoto(?string $filename): void {
    if (!$filename) return;
    $path = __DIR__ . '/uploads/' . basename($filename);
    if (file_exists($path)) @unlink($path);
}

function redirect(string $page, string $param): never {
    header("Location: {$page}?{$param}");
    exit;
}

function jsonOut(bool $success, string $message = ''): never {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/**
 * Returns true if $name already exists in the farmers table.
 * Since farmer_name has no UNIQUE constraint at the DB level, the app
 * must enforce uniqueness itself before every insert/rename.
 * Pass $excludeName to ignore a row's own current name (used on update/rename).
 */
function farmerNameExists(mysqli $conn, string $name, ?string $excludeName = null): bool {
    if ($excludeName !== null) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM farmers WHERE farmer_name = ? AND farmer_name <> ?");
        $stmt->bind_param("ss", $name, $excludeName);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM farmers WHERE farmer_name = ?");
        $stmt->bind_param("s", $name);
    }
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

/**
 * Returns how many rows currently match $name. Used before an UPDATE that is
 * keyed by farmer_name, so we can refuse to proceed if the name is ambiguous
 * (matches 0 or more than 1 row) instead of silently touching the wrong record(s).
 */
function countFarmersByName(mysqli $conn, string $name): int {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM farmers WHERE farmer_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count;
}


/* ══════════════════════════════════════════════════
   1. BULK CREATE — Registration Enrollment form
══════════════════════════════════════════════════ */
if (isset($_POST['bulk_save'])) {

    $names = $_POST['name']        ?? [];
    $users = $_POST['username']    ?? [];
    $passes= $_POST['password']    ?? [];
    $ages  = $_POST['age']         ?? [];
    $phones= $_POST['phone']       ?? [];
    $brgy  = $_POST['barangay_id'] ?? [];
    $gender= $_POST['gender']      ?? [];
    $exp   = $_POST['experience']  ?? [];

    $errors = [];
    $namesInThisBatch = []; // tracks names already claimed earlier in this same submission

    foreach ($names as $i => $rawName) {

        // ── Sanitize ──────────────────────────────
        $name    = clean($rawName);
        $user    = clean($users[$i]    ?? '');
        $plain   = clean($passes[$i]   ?? '');
        $age     = (int)($ages[$i]     ?? 0);
        $phone   = clean($phones[$i]   ?? '');
        $brgyId  = (int)($brgy[$i]     ?? 0);
        $gend    = in_array($gender[$i] ?? '', ['Male','Female'], true) ? $gender[$i] : 'Male';
        $years   = (int)($exp[$i]      ?? 0);

        // ── Verify ───────────────────────────────
        if ($name === '')        { $errors[] = "Record #".($i+1).": Name is required.";       continue; }
        if (!validAge($age))     { $errors[] = "Record #".($i+1).": Age must be 12–120.";     continue; }
        if (!validPhone($phone)) { $errors[] = "Record #".($i+1).": Invalid contact number."; continue; }
        if ($brgyId <= 0)        { $errors[] = "Record #".($i+1).": Barangay is required.";   continue; }
        if ($plain === '')       { $errors[] = "Record #".($i+1).": Password is required.";   continue; }
        if ($user  === '')       { $errors[] = "Record #".($i+1).": Username is required.";   continue; }

        // ── Uniqueness (farmer_name is our only stable key, and has no
        //    UNIQUE constraint in the DB, so the app must enforce it) ──
        $nameKey = mb_strtolower($name);
        if (isset($namesInThisBatch[$nameKey])) {
            $errors[] = "Record #".($i+1).": \"$name\" is duplicated elsewhere in this submission.";
            continue;
        }
        if (farmerNameExists($conn, $name)) {
            $errors[] = "Record #".($i+1).": A farmer named \"$name\" already exists. Names must be unique.";
            continue;
        }
        $namesInThisBatch[$nameKey] = true;

        // ── Photo ────────────────────────────────
        $photo = null;
        if (isset($_FILES['photo']['name'][$i]) && $_FILES['photo']['error'][$i] === UPLOAD_ERR_OK) {
            $singleFile = [
                'name'     => $_FILES['photo']['name'][$i],
                'tmp_name' => $_FILES['photo']['tmp_name'][$i],
                'size'     => $_FILES['photo']['size'][$i],
                'error'    => $_FILES['photo']['error'][$i],
            ];
            $photo = uploadPhoto($singleFile);
        }

        // ── Insert ───────────────────────────────
        $hash = password_hash($plain, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("
            INSERT INTO farmers
              (farmer_name, gender, age, contact_number, email, password,
               barangay_id, years_farming, profile_farmers, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param(
            "ssisssiis",
            $name, $gend, $age, $phone, $user, $hash,
            $brgyId, $years, $photo
        );
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($errors)) {
        $errParam = urlencode(implode(' | ', $errors));
        redirect('farmers.php', "error={$errParam}");
    }

    redirect('farmers.php', 'success=created');
}


/* ══════════════════════════════════════════════════
   2. UPDATE FARMER INFO
      Keyed by farmer_name (since farmer_id no longer
      exists as a PK in the farmers table).
══════════════════════════════════════════════════ */
if (isset($_POST['update_farmer_info'])) {

    // ── Get the original farmer name (used as WHERE key) ──
    $originalName = clean($_POST['farmer_name_key'] ?? '');
    if ($originalName === '') redirect('farmers.php', 'error=Invalid+farmer+identifier');

    // ── Sanitize inputs ───────────────────────
    $name    = clean($_POST['farmer_name']     ?? '');
    $contact = clean($_POST['contact_number']  ?? '');
    $gend    = in_array($_POST['gender'] ?? '', ['Male','Female'], true) ? $_POST['gender'] : 'Male';
    $age     = (int)($_POST['age']             ?? 0);
    $brgyId  = (int)($_POST['barangay_id']     ?? 0);
    $years   = isset($_POST['years_farming']) && $_POST['years_farming'] !== ''
               ? (int)$_POST['years_farming'] : null;
    $email   = clean($_POST['email']           ?? '');
    $status  = in_array($_POST['status'] ?? '', ['active','inactive'], true)
               ? $_POST['status'] : 'active';
    $newPass = clean($_POST['new_password']    ?? '');

    // ── Verify ────────────────────────────────
    if ($name === '')          redirect('farmers.php', 'error=Name+is+required');
    if (!validAge($age))       redirect('farmers.php', 'error=Age+must+be+12%E2%80%93120');
    if (!validPhone($contact)) redirect('farmers.php', 'error=Invalid+contact+number+%2809XXXXXXXXX%29');
    if ($brgyId <= 0)          redirect('farmers.php', 'error=Barangay+is+required');

    // ── Data integrity: farmer_name has no UNIQUE constraint in the DB,
    //    so before mutating anything we confirm the WHERE key isn't ambiguous. ──
    $matchCount = countFarmersByName($conn, $originalName);
    if ($matchCount === 0) {
        redirect('farmers.php', 'error=' . urlencode("Farmer \"$originalName\" was not found."));
    }
    if ($matchCount > 1) {
        // Refuse to guess which of several same-named rows the admin meant.
        redirect('farmers.php', 'error=' . urlencode("Multiple farmers are named \"$originalName\" — cannot safely update. Please resolve the duplicate names first."));
    }

    // ── Renaming to a name that's already taken by a *different* farmer? Block it. ──
    if ($name !== $originalName && farmerNameExists($conn, $name)) {
        redirect('farmers.php', 'error=' . urlencode("A farmer named \"$name\" already exists. Choose a different name."));
    }

    // ── Photo ────────────────────────────────
    $photo = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = uploadPhoto($_FILES['photo']);
        // Delete old photo
        $oldFile = null;
        $oldQ = $conn->prepare("SELECT profile_farmers FROM farmers WHERE farmer_name = ?");
        $oldQ->bind_param("s", $originalName);
        $oldQ->execute();
        $oldQ->bind_result($oldFile);
        $oldQ->fetch();
        $oldQ->close();
        deleteOldPhoto($oldFile);
    }

    // ── Build UPDATE query ───────────────────
    $hashSection  = '';
    $hashParams   = [];
    $hashTypes    = '';
    if ($newPass !== '') {
        $hash        = password_hash($newPass, PASSWORD_BCRYPT);
        $hashSection = ', password = ?';
        $hashParams  = [$hash];
        $hashTypes   = 's';
    }

    $photoSection = '';
    $photoParams  = [];
    $photoTypes   = '';
    if ($photo !== null) {
        $photoSection = ', profile_farmers = ?';
        $photoParams  = [$photo];
        $photoTypes   = 's';
    }

    $sql = "UPDATE farmers SET
                farmer_name    = ?,
                contact_number = ?,
                gender         = ?,
                age            = ?,
                barangay_id    = ?,
                years_farming  = ?,
                email          = ?,
                status         = ?
                {$hashSection}
                {$photoSection}
            WHERE farmer_name = ?";

    // types: name(s), contact(s), gender(s), age(i), brgyId(i), years(i nullable→s), email(s), status(s),
    //        [hash(s)], [photo(s)], originalName(s)
    $types  = 'sssiis' . 'ss' . $hashTypes . $photoTypes . 's';
    $params = array_merge(
        [$name, $contact, $gend, $age, $brgyId, $years, $email, $status],
        $hashParams,
        $photoParams,
        [$originalName]
    );

    $stmt = $conn->prepare($sql);
    $refs = [];
    foreach ($params as $k => &$v) $refs[] = &$v;
    unset($v);
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if ($stmt->execute()) {
        $stmt->close();
        redirect('farmers.php', 'success=updated');
    } else {
        $stmt->close();
        redirect('farmers.php', 'error=Update+failed+%E2%80%94+please+try+again');
    }
}


/* ══════════════════════════════════════════════════
   Fallback
══════════════════════════════════════════════════ */
redirect('farmers.php', '');