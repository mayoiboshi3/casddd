<?php
/**
 * notifications_feed.php
 * ----------------------------------------------------------------------
 * "What's new?" feed for the notification bell (includes/notifications.php).
 *
 *   notifications_feed.php                 first call: returns a cursor + the last 7 days of activity
 *   notifications_feed.php?after={cursor}  returns only what happened since that cursor
 *
 * Reply: {"ok":true,"initial":bool,"cursor":{t,c,f},"events":[{id,kind,title,body,link,urgent,ago}]}
 *
 * Events come from the same tables reports.php already uses, so anything added or changed by
 * the website OR the mobile app shows up:
 *   - new manual disease report            (disease_cases.source = 'manual_report')
 *   - new AI scan                          (disease_cases.source = 'scan')
 *   - manual report status changed         (verified / resolved / rejected -- real status changes only)
 *   - new planting / harvesting report     (planting_harvesting_reports)
 *
 * The cursor is {t: DB time, c: highest case_id, f: highest report_id}. A row with an id above the
 * cursor is NEW; a row at/below it with a newer updated_at is a CHANGE. Nothing is stored server-side.
 */
require_once __DIR__ . '/src/db_config.php';
require_once __DIR__ . '/src/session_guard.php';          // same login check the other pages use
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }   // don't hold the session lock

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const NF_MAX_EVENTS = 40;

$nf_errors = [];                            // shown by notifications_feed.php?debug=1
function nf_rows($conn, $sql) {            // null = the query failed (missing table/column), [] = no rows
    global $nf_errors;
    try {
        $q = mysqli_query($conn, $sql);
        if (!$q) { $nf_errors[] = mysqli_error($conn); return null; }
        $rows = [];
        while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
        return $rows;
    } catch (Throwable $e) { $nf_errors[] = $e->getMessage(); return null; }
}
function nf_one($conn, $sql, $col) {
    $r = nf_rows($conn, $sql);
    return ($r && isset($r[0][$col])) ? $r[0][$col] : null;
}
function nf_reply($data) {
    global $nf_errors;
    $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
    if (isset($_GET['debug'])) {            // open notifications_feed.php?debug=1 in the browser to see what the feed sees
        $data['debug'] = ['query_errors' => $nf_errors, 'event_count' => count($data['events'] ?? [])];
        $flags |= JSON_PRETTY_PRINT;
    }
    echo json_encode($data, $flags);
    exit;
}

try { mysqli_set_charset($conn, 'utf8mb4'); } catch (Throwable $e) {}

// ── Cursor as of right now (taken BEFORE the event queries so nothing falls between two polls) ──
$now = nf_one($conn, "SELECT NOW() AS t", 't');
if ($now === null) nf_reply(['ok' => false]);
$cursor = [
    't' => $now,
    'c' => (int)nf_one($conn, "SELECT COALESCE(MAX(case_id),0) AS m FROM disease_cases", 'm'),
    'f' => (int)nf_one($conn, "SELECT COALESCE(MAX(report_id),0) AS m FROM planting_harvesting_reports", 'm'),
];

// ── Cursor sent by the browser (validated; anything odd -> treated as a first call) ──
$after = json_decode((string)($_GET['after'] ?? ''), true);
$initial = !(is_array($after)
    && isset($after['t'], $after['c'], $after['f'])
    && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$after['t']));

if ($initial) { $c0 = 0; $f0 = 0; $t0 = null; }
else          { $c0 = max(0, (int)$after['c']); $f0 = max(0, (int)$after['f']); $t0 = (string)$after['t']; }
$floor = $initial ? "AND COALESCE(dc.updated_at, dc.report_date) >= (NOW() - INTERVAL 7 DAY)" : "";

$events = [];
function nf_add(&$events, $e) { if (!isset($events[$e['id']])) $events[$e['id']] = $e; }

$caseSelect = "SELECT dc.case_id, dc.reference_id, dc.status, dc.severity, dc.source, dc.updated_at,
        b.name AS barangay, d.disease_name, f.farmer_name,
        TIMESTAMPDIFF(SECOND, COALESCE(dc.updated_at, dc.report_date), NOW()) AS ago
    FROM disease_cases dc
    LEFT JOIN barangays b ON b.id = dc.barangay_id
    LEFT JOIN diseases  d ON d.disease_id = dc.disease_id
    LEFT JOIN farmers   f ON f.farmer_id = dc.farmer_id";

function nf_case_body($r) {
    $parts = array_filter([$r['disease_name'] ?? '', $r['barangay'] ?? '', !empty($r['farmer_name']) ? 'by ' . $r['farmer_name'] : '']);
    return implode(' · ', $parts);
}
function nf_urgent($r) {
    $s = strtolower((string)($r['severity'] ?? ''));
    return $s !== '' && (strpos($s, 'high') !== false || strpos($s, 'sever') !== false);
}

// 1) NEW disease reports + AI scans (one event per report, even if it lists several diseases)
$rows = nf_rows($conn, "$caseSelect WHERE dc.case_id > $c0 $floor ORDER BY dc.case_id DESC LIMIT 80");
foreach ((array)$rows as $r) {
    $key  = ($r['reference_id'] ?? '') !== '' ? $r['reference_id'] : 'c' . $r['case_id'];
    $scan = ($r['source'] ?? '') === 'scan';
    nf_add($events, [
        'id'     => 'case:' . $key . ':new',
        'key'    => $key,
        'kind'   => $scan ? 'scan' : 'report',
        'title'  => $scan ? 'New AI scan' : 'New disease report',
        'body'   => nf_case_body($r),
        'link'   => 'reports.php?view=' . ($scan ? 'scans' : 'disease'),
        'urgent' => nf_urgent($r),
        'ago'    => max(0, (int)$r['ago']),
    ]);
}

// 2) STATUS CHANGES on manual reports (verified / resolved / rejected) -- not on the first call.
//    IMPORTANT: disease_cases.updated_at is "ON UPDATE CURRENT_TIMESTAMP", so it moves on EVERY write to the row --
//    including each chat message (farmer reply, staff message, inspection notice). Using it here made every chat
//    message look like a "report update" and re-announce that case. The status-specific stamps below are only
//    written when the status really changes (see reports.php), so chat activity can no longer trigger this.
if (!$initial) {
    $t0e = mysqli_real_escape_string($conn, $t0);
    $stamp = "(CASE dc.status WHEN 'verified' THEN dc.verified_at WHEN 'resolved' THEN dc.resolved_at WHEN 'rejected' THEN dc.rejected_at END)";
    $rows = nf_rows($conn, "SELECT dc.case_id, dc.reference_id, dc.status, dc.severity, dc.source, $stamp AS st_at,
            b.name AS barangay, d.disease_name, f.farmer_name,
            TIMESTAMPDIFF(SECOND, $stamp, NOW()) AS ago
        FROM disease_cases dc
        LEFT JOIN barangays b ON b.id = dc.barangay_id
        LEFT JOIN diseases  d ON d.disease_id = dc.disease_id
        LEFT JOIN farmers   f ON f.farmer_id = dc.farmer_id
        WHERE dc.case_id <= $c0 AND dc.source = 'manual_report'
          AND dc.status IN ('verified','resolved','rejected')
          AND $stamp >= '$t0e'
        ORDER BY $stamp DESC LIMIT 80");
    $labels = ['verified' => 'Report verified', 'resolved' => 'Report resolved', 'rejected' => 'Report rejected'];
    foreach ((array)$rows as $r) {
        $key = ($r['reference_id'] ?? '') !== '' ? $r['reference_id'] : 'c' . $r['case_id'];
        $st  = strtolower((string)($r['status'] ?? ''));
        nf_add($events, [
            'id'     => 'case:' . $key . ':' . $st . ':' . $r['st_at'],
            'key'    => $key,
            'kind'   => 'status',
            'title'  => $labels[$st] ?? 'Report updated',
            'body'   => nf_case_body($r),
            'link'   => 'reports.php?view=disease',
            'urgent' => false,
            'ago'    => max(0, (int)$r['ago']),
        ]);
    }
}

// 3) NEW planting / harvesting reports (extra columns are optional -- missing ones are just skipped)
$fl = $initial ? "AND r.submitted_at >= (NOW() - INTERVAL 7 DAY)" : "";
$rows = nf_rows($conn, "SELECT r.*, f.farmer_name, TIMESTAMPDIFF(SECOND, r.submitted_at, NOW()) AS ago
    FROM planting_harvesting_reports r LEFT JOIN farmers f ON f.farmer_id = r.farmer_id
    WHERE r.report_id > $f0 $fl ORDER BY r.report_id DESC LIMIT 40");
if ($rows === null) {   // no farmer_id column -> same thing without the farmer name
    $rows = nf_rows($conn, "SELECT r.*, TIMESTAMPDIFF(SECOND, r.submitted_at, NOW()) AS ago
        FROM planting_harvesting_reports r WHERE r.report_id > $f0 $fl ORDER BY r.report_id DESC LIMIT 40");
}
foreach ((array)$rows as $r) {
    $type  = strtolower((string)($r['report_type'] ?? $r['type'] ?? $r['category'] ?? ''));
    $title = strpos($type, 'harvest') !== false ? 'New harvesting report'
           : (strpos($type, 'plant') !== false ? 'New planting report' : 'New farm report');
    $body  = array_filter([!empty($r['crop']) ? $r['crop'] : '', !empty($r['farmer_name']) ? 'by ' . $r['farmer_name'] : '']);
    nf_add($events, [
        'id'     => 'farm:' . $r['report_id'] . ':new',
        'key'    => 'farm:' . $r['report_id'],
        'kind'   => 'farm',
        'title'  => $title,
        'body'   => implode(' · ', $body),
        'link'   => 'reports.php?view=farm',
        'urgent' => false,
        'ago'    => max(0, (int)($r['ago'] ?? 0)),
    ]);
}

// Newest first, capped
$events = array_values($events);
usort($events, function ($a, $b) { return $a['ago'] <=> $b['ago']; });
$more   = count($events) > NF_MAX_EVENTS;
$events = array_slice($events, 0, NF_MAX_EVENTS);

nf_reply(['ok' => true, 'initial' => $initial, 'cursor' => $cursor, 'events' => $events, 'more' => $more]);