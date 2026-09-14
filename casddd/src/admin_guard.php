<?php
/**
 * admin_guard.php
 *
 * Include this AFTER session_guard.php at the very top of any page (or API
 * endpoint) that only the Admin role may use — currently accounts.php.
 *
 * session_guard.php already proves the visitor is logged in. This file adds
 * the second check: is the logged-in user's role 'admin'? Field staff
 * (role = 'agri1' or 'agri2') are turned away even if they type the URL
 * directly, and even if they call the accounts.php?api=... endpoints
 * straight from a browser console.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$isAdmin) {
    // API calls (accounts.php?api=list / update / delete / create) should get
    // a JSON 403, not an HTML redirect, so the front-end fetch() calls fail cleanly.
    $isApiRequest = isset($_GET['api'])
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'success' => false,
            'msg'     => 'Access denied. Admins only.',
            'message' => 'Access denied. Admins only.',
        ]);
        exit();
    }

    // Regular page load: bounce back to the dashboard with a message.
    header("Location: " . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2)
        . "dashboard.php?error=" . urlencode("You don't have permission to access that page."));
    exit();
}