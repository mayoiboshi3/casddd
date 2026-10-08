<?php
/**
 * chat_feed.php  (put it in the SAME folder as reports.php)
 * ----------------------------------------------------------------------
 * "Which chats have farmer replies, and how many?" -- used by includes/notifications.php to pop up the
 * new-message alert on ANY page.
 *
 * It deliberately sends only COUNTS per chat (plus the last few message texts). The browser remembers, for
 * each chat on its own, how many replies it has already seen, so:
 *   - a reply in chat A can never re-announce chat B,
 *   - a chat is announced exactly once per new reply,
 *   - there is no shared "cursor" / timestamp that can drift between chats or timezones.
 *
 * Test in the browser:  chat_feed.php   -> {"ok":true,"chats":[{"key":"...","count":3,...}]}
 */
require_once __DIR__ . '/src/db_config.php';
require_once __DIR__ . '/src/session_guard.php';          // same login check the Reports page uses
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Every farmer reply inside one case row's farmer_reply_text (JSON list, single JSON object, or plain text).
function casd_chat_entries($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    $out = [];
    if ($raw[0] === '[' || $raw[0] === '{') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            if (isset($j['message'])) { $j = [$j]; }
            foreach ($j as $item) {
                $t = is_array($item) ? trim((string)($item['message'] ?? '')) : trim((string)$item);
                if ($t !== '') $out[] = $t;
            }
            return $out;
        }
    }
    return [$raw];
}

$chats = [];
$ok    = true;
try {
    $q = mysqli_query($conn, "SELECT dc.case_id, dc.reference_id, dc.farmer_reply_text, f.farmer_name
                              FROM disease_cases dc
                              LEFT JOIN farmers f ON f.farmer_id = dc.farmer_id
                              WHERE dc.farmer_reply_text IS NOT NULL AND dc.farmer_reply_text <> ''
                              ORDER BY dc.case_id ASC");
    if (!$q) { $ok = false; }
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $entries = casd_chat_entries($r['farmer_reply_text']);
        if (!$entries) continue;
        // Same key the Reports page uses for one report (a multi-disease report is several rows, one chat).
        $ref = trim((string)$r['reference_id']);
        $key = $ref !== '' ? $ref : 'c' . (int)$r['case_id'];
        if (isset($chats[$key]) && $chats[$key]['count'] >= count($entries)) continue;
        $chats[$key] = [
            'key'   => $key,
            'case'  => (int)$r['case_id'],
            'name'  => $r['farmer_name'] ?: 'Farmer',
            'count' => count($entries),
            'tail'  => array_map(function ($t) { return mb_substr($t, 0, 300); }, array_slice($entries, -3)),
        ];
    }
} catch (Throwable $e) { $ok = false; }

$list = array_values($chats);
foreach ($list as &$c) {
    $c['link'] = 'reports.php?view=disease&case_id=' . $c['case'] . '&open_msg=1';
}
unset($c);

echo json_encode(['ok' => $ok, 'chats' => $list], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);