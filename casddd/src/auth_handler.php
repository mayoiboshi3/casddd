<?php
/**
 * CASD Corn Portal - Authentication Handler
 * Handles: signup, profile update, logout.
 *
 * NOTE: Plain "login" is intentionally NOT handled here — index.php posts
 * to itself and handles login inline, since that's where the login form
 * lives and where success/error messages need to be redisplayed. Having a
 * second, slightly different login implementation in this file was a source
 * of confusion (it checked status differently and could drift out of sync
 * with index.php), so it has been removed. Everything below is organized
 * into one function per action, dispatched at the bottom of the file.
 */
session_start();
require_once __DIR__ . '/db_config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── ACTIONS ─────────────────────────────────────────────────────

function handleLogout(): void {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

function handleSignup(mysqli $conn): void {
    $username    = trim($_POST['username']    ?? '');
    $fullname    = trim($_POST['fullname']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $password    = $_POST['password']         ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($fullname) || empty($email) || empty($password)) {
        redirectBack('index.php', null, 'All required fields must be filled.');
    }

    if ($password !== $confirm) {
        redirectBack('index.php', null, 'Passwords do not match.');
    }

    if (strlen($password) < 8) {
        redirectBack('index.php', null, 'Password must be at least 8 characters.');
    }
    if (!preg_match('/[A-Z]/', $password)) {
        redirectBack('index.php', null, 'Password must contain at least one uppercase letter.');
    }
    if (!preg_match('/[a-z]/', $password)) {
        redirectBack('index.php', null, 'Password must contain at least one lowercase letter.');
    }
    if (!preg_match('/[0-9]/', $password)) {
        redirectBack('index.php', null, 'Password must contain at least one number.');
    }
    if (!preg_match('/[\W_]/', $password)) {
        redirectBack('index.php', null, 'Password must contain at least one symbol (e.g. @, #, !).');
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        redirectBack('index.php', null, 'Username already exists.');
    }

    // Every public signup is a regular field-staff account ('agri1' —
    // Agriculturist I). Admin accounts, and promotion to Agriculturist II,
    // are never done through this form — use the admin-only Account
    // Manager instead.
    $photoFilename = handleProfilePhotoUpload($_FILES['profile_photo'] ?? null, true);

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (username, password, full_name, email, role, profile_photo)
        VALUES (?, ?, ?, ?, 'agri1', ?)
    ");
    $stmt->bind_param("sssss", $username, $hashed, $fullname, $email, $photoFilename);

    if ($stmt->execute()) {
        $stmt->close();
        $params = http_build_query([
            'view'     => 'login',
            'new_user' => $username,
            'success'  => 'Account created successfully! Please enter your password to log in.'
        ]);
        header("Location: ../index.php?" . $params);
        exit();
    }

    $stmt->close();
    redirectBack('index.php', null, 'Account creation failed. Please try again.');
}

function handleUpdateProfile(mysqli $conn): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }

    $userId   = $_SESSION['user_id'];
    $fullName = trim($_POST['full_name']    ?? '');
    $username = trim($_POST['username']     ?? '');
    $email    = trim($_POST['email']        ?? '');
    $phone    = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password']          ?? '';
    $rePass   = $_POST['confirm_password']  ?? '';

    if (empty($fullName) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and Email are required.']);
        exit();
    }

    $photoFilename = handleProfilePhotoUpload($_FILES['profile_photo'] ?? null, false)
        ?? $_SESSION['photo']
        ?? null;

    if (!empty($password)) {
        if ($password !== $rePass) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit();
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit();
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users SET full_name=?, username=?, email=?, phone_number=?, profile_photo=?, password=?
            WHERE user_id=?
        ");
        $stmt->bind_param("ssssssi", $fullName, $username, $email, $phone, $photoFilename, $hashed, $userId);
    } else {
        $stmt = $conn->prepare("
            UPDATE users SET full_name=?, username=?, email=?, phone_number=?, profile_photo=?
            WHERE user_id=?
        ");
        $stmt->bind_param("sssssi", $fullName, $username, $email, $phone, $photoFilename, $userId);
    }

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $fullName;
        $_SESSION['username']  = $username;
        $_SESSION['email']     = $email;
        $_SESSION['phone']     = $phone;
        $_SESSION['photo']     = $photoFilename;

        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.', 'photo' => $photoFilename]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
    exit();
}

// ── HELPERS ─────────────────────────────────────────────────────

/**
 * Validates and stores an uploaded profile photo.
 * $required = true (signup) redirects back with an error on bad input.
 * $required = false (profile update) silently ignores bad input and returns
 * null, so the caller falls back to the existing photo on file.
 */
function handleProfilePhotoUpload(?array $file, bool $required): ?string {
    if (empty($file['name'])) {
        return null;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed) || $file['size'] > $maxSize) {
        if ($required) {
            $message = !in_array($file['type'], $allowed)
                ? 'Profile photo must be JPG, PNG, or GIF.'
                : 'Profile photo must be under 5MB.';
            redirectBack('index.php', null, $message);
        }
        return null;
    }

    $ext           = pathinfo($file['name'], PATHINFO_EXTENSION);
    $photoFilename = time() . '_' . uniqid() . '.' . $ext;
    $uploadDir     = __DIR__ . '/../uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $photoFilename)) {
        if ($required) {
            redirectBack('index.php', null, 'Failed to upload profile photo.');
        }
        return null;
    }

    return $photoFilename;
}

function redirectBack(string $page, ?string $success = null, ?string $error = null): void {
    $params = [];
    if ($success) $params[] = 'success=' . urlencode($success);
    if ($error)   $params[] = 'error='   . urlencode($error);
    $query = $params ? '?' . implode('&', $params) : '';
    header("Location: ../{$page}{$query}");
    exit();
}

// ── DISPATCH ────────────────────────────────────────────────────

switch ($action) {
    case 'logout':
        handleLogout();
        break;
    case 'signup':
        handleSignup($conn);
        break;
    case 'update_profile':
        handleUpdateProfile($conn);
        break;
}