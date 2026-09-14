<?php
/**
 * logout.php — Ends the current session and returns the user to the login page.
 *
 * Steps, in order:
 *  1. Start/resume the session so we have something to destroy.
 *  2. Clear all session data in $_SESSION.
 *  3. Expire the session cookie itself (not just the data), so the browser
 *     doesn't keep sending a now-invalid session id.
 *  4. Destroy the session on the server side.
 *  5. Redirect to index.php (the login page).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wipe all session variables (role, user_id, etc.)
$_SESSION = [];

// Remove the session cookie from the browser, if cookies are used for sessions.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session data on the server.
session_destroy();

// Send the user back to the login page.
header("Location: index.php");
exit;