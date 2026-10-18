<?php
/**
 * reports_poll.php
 * ----------------------------------------------------------------------
 * "Has anything changed?" check for the Reports page.
 *
 *  - Included by reports.php  -> only defines reports_signature() / reports_poll_stats().
 *  - Requested directly       -> (reports_poll.php?since=...) answers with a tiny JSON reply:
 *        {"changed":false}                                   nothing new, nothing else is sent
 *        {"changed":true,"sig":{...},"stats":{...}}          something was added / changed / removed
 *
 * The signature is built from cheap aggregate queries (row count, newest id, newest update time,
 * and a checksum of every row's id + status), so a new report, a status change, a farmer reply or
 * a deletion all change it -- no matter whether it came from this website or from the mobile app.
 * ----------------------------------------------------------------------
 */

// One short string per section of the Reports page. '' means "could not be read" (never treated as a change).
function reports_signature($conn) {
    $sig = ['disease' => '', 'scans' => '', 'farm' => ''];

    foreach (['disease' => 'manual_report', 'scans' => 'scan'] as $key => $src) {
        try {
            $q = mysqli_query($conn, "SELECT COUNT(*) AS c,
                    COALESCE(MAX(case_id), 0) AS m,
                    COALESCE(MAX(updated_at), '') AS u,
                    COALESCE(SUM(CRC32(CONCAT(case_id, '-', COALESCE(`status`, '')))), 0) AS h
                FROM disease_cases WHERE `source` = '" . mysqli_real_escape_string($conn, $src) . "'");
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                $sig[$key] = $r['c'] . '|' . $r['m'] . '|' . $r['u'] . '|' . $r['h'];
            }
        } catch (Throwable $e) { /* leave '' */ }
    }

    try {
        $q = mysqli_query($conn, "SELECT COUNT(*) AS c,
                COALESCE(MAX(report_id), 0) AS m,
                COALESCE(MAX(submitted_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(report_id, '-', COALESCE(`status`, '')))), 0) AS h
            FROM planting_harvesting_reports");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $sig['farm'] = $r['c'] . '|' . $r['m'] . '|' . $r['u'] . '|' . $r['h'];
        }
    } catch (Throwable $e) { /* leave '' */ }

    return $sig;
}

// The numbers shown on the three view cards at the top of the Reports page (same rules as reports.php).
function reports_poll_stats($conn) {
    $out = ['manual_total' => 0, 'manual_pending' => 0, 'scan_total' => 0, 'scan_week' => 0];
    $key = "COALESCE(NULLIF(reference_id,''), CONCAT('c', case_id))";
    try {
        $q = mysqli_query($conn, "SELECT COUNT(DISTINCT $key) AS total,
                COUNT(DISTINCT CASE WHEN `status` = 'pending' THEN $key END) AS pending
            FROM disease_cases WHERE `source` = 'manual_report'");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $out['manual_total']   = (int)$r['total'];
            $out['manual_pending'] = (int)$r['pending'];
        }
        $q = mysqli_query($conn, "SELECT COUNT(DISTINCT $key) AS total,
                COUNT(DISTINCT CASE WHEN report_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN $key END) AS week
            FROM disease_cases WHERE `source` = 'scan'");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $out['scan_total'] = (int)$r['total'];
            $out['scan_week']  = (int)$r['week'];
        }
    } catch (Throwable $e) { /* keep zeros */ }
    return $out;
}

// ── Endpoint mode: only when this file itself is the requested script ──
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    require_once __DIR__ . '/src/db_config.php';
    require_once __DIR__ . '/src/session_guard.php';   // same login check the Reports page uses
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }   // don't hold the session lock

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $sig   = reports_signature($conn);
    $since = (string)($_GET['since'] ?? '');

    if ($since !== '' && $since === json_encode($sig)) {
        echo json_encode(['changed' => false]);
    } else {
        echo json_encode(['changed' => true, 'sig' => $sig, 'stats' => reports_poll_stats($conn)]);
    }
    exit;
}