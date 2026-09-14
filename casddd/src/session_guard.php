<?php
/**
 * session_guard.php
 * Include this at the very top of every protected page (dashboard, farmers, reports, analytics).
 * It starts the session and redirects unauthenticated visitors back to the login page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . getPathToRoot() . "index.php");
    exit();
}

/**
 * Works out how many folders "up" the current page is from the project root,
 * based on the actual requested script's location relative to the document root
 * (NOT __DIR__ of this include, which stays constant no matter who includes it).
 *
 * Root-level pages (dashboard.php, farmers.php, reports.php, accounts.php, etc.)
 * return "" so the redirect is simply "index.php".
 * A page one folder deep (e.g. /admin/reports.php) would return "../".
 *
 * This replaces the old PHP_SELF-based calculation, which produced a negative
 * str_repeat() count for any root-level page (a fatal error in PHP 8+) and was
 * the reason users got bounced/crashed right after a successful login.
 */
function getPathToRoot(): string {
    $docRoot   = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));

    if (!$docRoot || !$scriptDir || $scriptDir === $docRoot) {
        return ''; // same level as project root — no "../" needed
    }

    $relative = trim(str_replace($docRoot, '', $scriptDir), '/');
    $depth    = $relative === '' ? 0 : count(explode('/', $relative));

    return str_repeat('../', max(0, $depth));
}