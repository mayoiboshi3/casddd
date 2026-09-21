<?php 
// Buffer this page's output. includes/layout.php prints the page shell (sidebar, header) BEFORE the
// Accept/Reject handler in planting_harvesting.php runs; buffering lets that handler throw the shell
// away and answer the browser's fetch() with pure JSON. Normal page loads are unaffected.
ob_start();
require_once __DIR__ . "/src/db_config.php"; 
$pageTitle = "Reports"; 
include "includes/layout.php"; 

// ── STYLED DIALOGS (replaces native alert()) ─────────────────────────────────
// casd_dialog_assets() prints the dialog CSS + JS once. It also overrides window.alert,
// so any leftover alert("...") on this page gets the same look.
function casd_dialog_assets() {
    static $done = false;
    if ($done) return;
    $done = true;
    echo <<<'CASD_ASSETS'
<style id="casd-dialog-css">
.casd-overlay{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.45);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);animation:casdFade .22s ease both;font-family:inherit}
.casd-overlay.casd-out{animation:casdFadeOut .18s ease both}
.casd-card{position:relative;overflow:hidden;width:min(92vw,400px);background:#fff;border-radius:2rem;padding:34px 28px 26px;text-align:center;box-shadow:0 30px 70px -12px rgba(15,23,42,.35),0 0 0 1px rgba(226,232,240,.8);animation:casdPop .42s cubic-bezier(.34,1.56,.64,1) both}
.casd-out .casd-card{animation:casdPopOut .18s ease both}
.casd-icon{width:68px;height:68px;margin:0 auto 18px;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:var(--casd-bg);color:var(--casd-fg);box-shadow:0 0 0 8px var(--casd-ring)}
.casd-icon svg{width:32px;height:32px;display:block}
.casd-draw{stroke-dasharray:32;stroke-dashoffset:32;animation:casdDraw .5s .18s ease forwards}
.casd-title{margin:0 0 8px;font-size:1.2rem;font-weight:900;letter-spacing:-.01em;color:#0f172a;line-height:1.25}
.casd-msg{margin:0 0 24px;font-size:.86rem;font-weight:500;line-height:1.6;color:#64748b;white-space:pre-line;word-break:break-word}
.casd-btn{display:block;width:100%;border:0;cursor:pointer;border-radius:.9rem;padding:13px 16px;background:var(--casd-btn);color:#fff;font:inherit;font-size:.7rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase;box-shadow:0 8px 18px -6px var(--casd-glow);transition:transform .25s cubic-bezier(.4,0,.2,1),box-shadow .25s cubic-bezier(.4,0,.2,1),filter .25s}
.casd-btn:hover{transform:translateY(-2px);box-shadow:0 12px 22px -6px var(--casd-glow);filter:brightness(1.08)}
.casd-btn:active{transform:translateY(0) scale(.98)}
.casd-btn:focus-visible{outline:3px solid var(--casd-ring);outline-offset:2px}
.casd-bar{position:absolute;left:0;bottom:0;height:4px;width:100%;background:var(--casd-btn);transform-origin:left;opacity:.85;animation:casdBar var(--casd-dur,2200ms) linear forwards}
@keyframes casdFade{from{opacity:0}to{opacity:1}}
@keyframes casdFadeOut{from{opacity:1}to{opacity:0}}
@keyframes casdPop{from{opacity:0;transform:translateY(14px) scale(.9)}to{opacity:1;transform:none}}
@keyframes casdPopOut{from{opacity:1;transform:none}to{opacity:0;transform:translateY(8px) scale(.96)}}
@keyframes casdDraw{to{stroke-dashoffset:0}}
@keyframes casdBar{from{transform:scaleX(1)}to{transform:scaleX(0)}}
@media (prefers-reduced-motion:reduce){.casd-overlay,.casd-card,.casd-draw,.casd-bar{animation-duration:.01ms!important;animation-delay:0s!important}.casd-draw{stroke-dashoffset:0}}
</style>
<script id="casd-dialog-js">
(function () {
    if (window.casdAlert) return;

    var THEMES = {
        success: { bg: '#d1fae5', fg: '#059669', ring: 'rgba(16,185,129,.16)', btn: '#10b981', glow: 'rgba(16,185,129,.55)', title: 'Success',              label: 'Continue' },
        error:   { bg: '#fee2e2', fg: '#dc2626', ring: 'rgba(239,68,68,.14)',  btn: '#ef4444', glow: 'rgba(239,68,68,.5)',   title: 'Something Went Wrong', label: 'Got it'   },
        warning: { bg: '#fef3c7', fg: '#d97706', ring: 'rgba(245,158,11,.16)', btn: '#f59e0b', glow: 'rgba(245,158,11,.55)', title: 'Heads Up',             label: 'Got it'   },
        info:    { bg: '#dbeafe', fg: '#2563eb', ring: 'rgba(37,99,235,.13)',  btn: '#2563eb', glow: 'rgba(37,99,235,.5)',   title: 'Notice',               label: 'OK'       }
    };
    var SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    var ICONS = {
        success: SVG + '<path class="casd-draw" d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
        error:   SVG + '<path class="casd-draw" d="M6 6l12 12M18 6L6 18"/></svg>',
        warning: SVG + '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        info:    SVG + '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
    };

    var queue = [], active = false;

    function whenBody(fn) {
        if (document.body) fn(); else document.addEventListener('DOMContentLoaded', fn);
    }

    function next() {
        var item = queue.shift();
        if (!item) { active = false; return; }
        active = true;
        whenBody(function () { render(item); });
    }

    function render(item) {
        var o     = item.opts;
        var type  = THEMES[o.type] ? o.type : 'info';
        var t     = THEMES[type];
        var auto  = parseInt(o.autoClose, 10) || 0;
        var prevFocus = document.activeElement;
        var closed = false, timer = null;

        var overlay = document.createElement('div');
        overlay.className = 'casd-overlay';
        overlay.innerHTML =
            '<div class="casd-card" role="alertdialog" aria-modal="true" aria-labelledby="casd-t" aria-describedby="casd-m">' +
                '<div class="casd-icon">' + ICONS[type] + '</div>' +
                '<h3 class="casd-title" id="casd-t"></h3>' +
                '<p class="casd-msg" id="casd-m"></p>' +
                '<button type="button" class="casd-btn"></button>' +
                (auto ? '<div class="casd-bar"></div>' : '') +
            '</div>';

        var card = overlay.firstChild;
        card.style.setProperty('--casd-bg',   t.bg);
        card.style.setProperty('--casd-fg',   t.fg);
        card.style.setProperty('--casd-ring', t.ring);
        card.style.setProperty('--casd-btn',  t.btn);
        card.style.setProperty('--casd-glow', t.glow);
        if (auto) card.style.setProperty('--casd-dur', auto + 'ms');

        overlay.querySelector('.casd-title').textContent = o.title || t.title;
        overlay.querySelector('.casd-msg').textContent   = item.message;
        var btn = overlay.querySelector('.casd-btn');
        btn.textContent = o.buttonText || t.label;

        function close() {
            if (closed) return;
            closed = true;
            clearTimeout(timer);
            document.removeEventListener('keydown', onKey, true);
            overlay.classList.add('casd-out');
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                try { if (prevFocus && prevFocus.focus) prevFocus.focus(); } catch (e) {}
                item.resolve();
                if (o.redirect) {
                    window.location.href = o.redirect;
                    // Page is normally about to unload. If it doesn't (same-page hash, blocked
                    // navigation), resume the queue so later dialogs are not stuck.
                    setTimeout(next, 1500);
                    return;
                }
                next();
            }, 180);
        }

        function onKey(e) {
            if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault(); e.stopPropagation(); close();
            } else if (e.key === 'Tab') {
                e.preventDefault(); btn.focus();
            }
        }

        btn.addEventListener('click', close);
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(overlay);
        btn.focus();
        if (auto) timer = setTimeout(close, auto);
    }

    /**
     * casdAlert(message, { type, title, buttonText, autoClose, redirect })
     *   type       'success' | 'error' | 'warning' | 'info'
     *   autoClose  ms until it dismisses itself (shows a progress bar)
     *   redirect   URL to go to once the dialog is dismissed
     * Returns a Promise that resolves when the dialog is dismissed.
     */
    window.casdAlert = function (message, opts) {
        if (message && typeof message === 'object') { opts = message; message = opts.message; }
        return new Promise(function (resolve) {
            queue.push({ message: message == null ? '' : String(message), opts: opts || {}, resolve: resolve });
            if (!active) next();
        });
    };

    // Any plain alert("...") anywhere on the page (including included files such as
    // pdf_generator.php) now uses the styled dialog too. The type is guessed from the wording.
    window.alert = function (msg) {
        var s = msg == null ? '' : String(msg), type = 'info';
        if (/(success|saved|sent|complete|updated|generated|downloaded)/i.test(s))                        type = 'success';
        else if (/(error|fail|invalid|unable|denied|not allowed|can only|cannot|can't|went wrong)/i.test(s)) type = 'error';
        else if (/(please|select|required|must|warning)/i.test(s))                                        type = 'warning';
        window.casdAlert(s, { type: type });
    };
})();
</script>
CASD_ASSETS;
}

// Shows a styled dialog, then sends the browser to $url when it is dismissed.
// $type: success | error | warning | info.  $autoCloseMs > 0 = closes itself (with a progress bar).
function casd_alert_redirect($type, $title, $message, $url, $autoCloseMs = 0) {
    casd_dialog_assets();
    $payload = json_encode(
        ['type' => $type, 'title' => $title, 'autoClose' => (int)$autoCloseMs, 'redirect' => $url],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $msg = json_encode((string)$message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    echo "<script>casdAlert($msg, $payload);</script>";
}

// --- SELF-HEALING SCHEMA: case_messages table (log of sent recommendations) ---
// Every recommendation sent becomes one timestamped entry here, instead of
// overwriting a single "last message" field.
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'case_messages'");
if ($tbl_check && mysqli_num_rows($tbl_check) === 0) {
    mysqli_query($conn, "CREATE TABLE case_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        sender ENUM('staff') NOT NULL DEFAULT 'staff',
        message_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case_id (case_id)
    )");
}

// --- SELF-HEALING SCHEMA: infection_percentage column on disease_cases ---
// Tracks the infection rate (0-100%) for Common Rust, Northern Leaf Blight,
// Gray Leaf Spot and Healthy Corn reports, editable only while Pending.
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM disease_cases LIKE 'infection_percentage'");
if ($col_check && mysqli_num_rows($col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE disease_cases ADD COLUMN infection_percentage DECIMAL(5,2) DEFAULT NULL AFTER total_plants");
}

// --- REPORT SOURCE: disease_cases.source is 'manual_report' (the default) or 'scan' ---
// 'scan' = the AI image detector classified the photo. Those cases are listed under AI Scans
// (?view=scans). Disease Reports only shows manual_report cases, so every Disease Reports query
// below adds one of these filters.
$manualOnlySql   = "`source` = 'manual_report'";
$manualOnlySqlDc = "dc.`source` = 'manual_report'";

// --- PERSONNEL LOG: who created / verified / rejected each case. Exposes the
// helpers used below (needs verified_at / rejected_by / rejected_at / resolved_by / resolved_at on disease_cases).
require_once __DIR__ . "/personnel_log.php";
// --- CASE CALENDAR: month view of cases per day (shared with the Farm Reports screen) ---
require_once __DIR__ . "/case_calendar.php";

// --- NEW FIELD CASE REPORT: form + insert logic now live in their own file ---
// (runs the POST-insert handling immediately here, same as before; the modal's
// HTML/JS is rendered later via render_new_case_report_modal() where the old
// modal used to sit in the markup, keeping this file lighter)
require_once __DIR__ . "/new_case_report.php";

// --- REVIEW FILE POPUP: view modal / messenger popup / photo lightbox now ---
// live in their own file (review.php), same pattern as new_case_report.php.
require_once __DIR__ . "/review.php";

// --- PLANTING & HARVESTING REPORTS: farmer-submitted planting/harvesting
// reports section + staff "+ Add Report" manual entry, same pattern as
// new_case_report.php / review.php. Handles its own POST logic up top and
// exposes render_planting_harvesting_section() to draw the section below.
require_once __DIR__ . "/planting_harvesting.php";

// --- ONE-WAY CASE STATUS FLOW: Pending -> Verified -> Resolved ---
// 'rejected' is only reachable from 'pending' (an early, dead-end exit for
// invalid/duplicate reports). Once 'resolved' or 'rejected', a case is locked.
$STATUS_FLOW = [
    'pending'  => ['pending', 'verified', 'rejected'],
    'verified' => ['verified', 'resolved'],
    'resolved' => ['resolved'],
    'rejected' => ['rejected'],
];

// 2. HANDLE DATABASE UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_case'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks    = mysqli_real_escape_string($conn, $_POST['remarks']);
    $case_id    = mysqli_real_escape_string($conn, $_POST['case_id_hidden']);

    // Look up the case's current status + disease so we can enforce the one-way
    // flow server-side too (never trust the client-side pill state alone), and
    // so we know whether a severity override is even eligible below. Also grab
    // reference_id: a report with 2-3 diseases selected is several rows sharing
    // one reference_id, and a status change now applies to that whole group at
    // once, not just this one row — so the report stays and moves as ONE file.
    $cur_q      = mysqli_query($conn, "SELECT status, disease_id, reference_id FROM disease_cases WHERE case_id = '$case_id' LIMIT 1");
    $cur_row    = $cur_q ? mysqli_fetch_assoc($cur_q) : null;
    $cur_status = $cur_row['status'] ?? null;
    $reference_id = $cur_row['reference_id'] ?? null;
    $allowedNext = $STATUS_FLOW[$cur_status] ?? [];

    // Severity override — only for "Other / Unidentified" reports (disease_id 999)
    // and only while the case is still Pending. There is no equivalent field for
    // Infection Percentage Rate: that value is read-only here and comes straight
    // from the database (populated elsewhere), never typed in by CASD.
    $severitySql = '';
    if ($cur_status === 'pending' && (int)($cur_row['disease_id'] ?? 0) === 999
        && isset($_POST['severity_override']) && $_POST['severity_override'] !== '') {
        $validSeverities = ['low', 'moderate', 'high', 'critical'];
        $severity_override = $_POST['severity_override'];
        if (in_array($severity_override, $validSeverities, true)) {
            $severitySql = ", severity = '" . mysqli_real_escape_string($conn, $severity_override) . "'";
        }
    }

    if ($cur_status !== null && $reference_id !== null && in_array($new_status, $allowedNext, true)) {
        $ref_esc = mysqli_real_escape_string($conn, $reference_id);
        // WHERE reference_id (not case_id) — every disease row under this report
        // is verified / resolved / rejected together, as one unit.
        // Personnel log: record WHO moved the case, but only when the status really changes
        // (saving remarks on an already-verified case must not overwrite the original verifier).
        $personnelSql = '';
        if ($new_status !== $cur_status) {
            $actorId  = personnel_log_current_user_id($conn);
            $actorSql = $actorId > 0 ? (int)$actorId : 'NULL';
            if ($new_status === 'verified') {
                $personnelSql = ", verified_by = $actorSql, verified_date = CURDATE(), verified_at = NOW()";
            } elseif ($new_status === 'rejected') {
                $personnelSql = ", rejected_by = $actorSql, rejected_at = NOW()";
            } elseif ($new_status === 'resolved') {
                $personnelSql = ", resolved_by = $actorSql, resolved_at = NOW()";
            }
        }
        $updateQuery = "UPDATE disease_cases SET status = '$new_status', remarks = '$remarks', updated_at = NOW() $severitySql $personnelSql WHERE reference_id = '$ref_esc'";
        if(mysqli_query($conn, $updateQuery)) {
            casd_alert_redirect('success', 'Status Updated', 'The case status and remarks were saved successfully.', 'reports.php?view=disease&tab=' . $new_status, 2200);
            exit;
        }
    } else {
        casd_alert_redirect('error', 'Invalid Status Change', "Cases can only move forward:\nPending → Verified → Resolved.", 'reports.php?view=disease&tab=' . ($cur_status ?? 'pending'));
        exit;
    }
}

// 2a. HANDLE MANUAL DISEASE VERIFICATION — "Other / Unidentified" reports only
// CASD picks the real disease(s) from the DB (up to 3) for a report that's
// still Pending + disease_id 999. This fills in the case's disease_id (and,
// via the JOIN, its name/description/treatment on the next page load).
//
// A report is really a group of disease_cases rows sharing one reference_id
// (see the "GROUP ROWS BY reference_id" comment further down) — an "Other /
// Unidentified" report is always exactly ONE row before verification, since
// it couldn't be split into several diseases at submission time. So:
//   - the 1st picked disease reuses/updates THIS row's disease_id directly.
//   - every additional picked disease (2nd, 3rd) is added by cloning this
//     row (same reference_id, farmer, severity, observations, photos, etc.)
//     with only disease_id swapped — exactly the same shape a multi-disease
//     report would already have if it had been submitted that way originally.
// The column list is read from the table itself (SHOW COLUMNS) instead of
// hard-coded, so this keeps working if disease_cases ever gains new columns.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reassign_disease'])) {
    $case_id = (int) $_POST['case_id_hidden'];

    // new_disease_id arrives as a single int ("5") or a comma list ("5,7,12")
    // from the picker — up to 3 diseases. Sanitize: ints only, > 0, not 999
    // (that's the "Other / Unidentified" placeholder itself), de-duplicated,
    // capped at 3 even if something upstream ever sends more.
    $rawIds = explode(',', (string) ($_POST['new_disease_id'] ?? ''));
    $new_disease_ids = [];
    foreach ($rawIds as $rawId) {
        $id = (int) trim($rawId);
        if ($id > 0 && $id !== 999 && !in_array($id, $new_disease_ids, true)) {
            $new_disease_ids[] = $id;
        }
    }
    $new_disease_ids = array_slice($new_disease_ids, 0, 3);

    $cur_q   = mysqli_query($conn, "SELECT * FROM disease_cases WHERE case_id = $case_id LIMIT 1");
    $cur_row = $cur_q ? mysqli_fetch_assoc($cur_q) : null;

    $isEligible = $cur_row
        && $cur_row['status'] === 'pending'
        && (int)$cur_row['disease_id'] === 999
        && !empty($new_disease_ids);

    if ($isEligible) {
        $first_disease_id = array_shift($new_disease_ids); // reuse this row for pick #1
        mysqli_query($conn, "UPDATE disease_cases SET disease_id = $first_disease_id, updated_at = NOW() WHERE case_id = $case_id");

        // Any further picks (#2, #3) become their own disease_cases rows,
        // cloned off the original row so the report stays ONE file (same
        // reference_id) with several diseases attached to it.
        if (!empty($new_disease_ids)) {
            $cols_q = mysqli_query($conn, "SHOW COLUMNS FROM disease_cases");
            $allCols = [];
            if ($cols_q) { while ($c = mysqli_fetch_assoc($cols_q)) { $allCols[] = $c['Field']; } }
            $cloneCols = array_filter($allCols, function ($c) { return $c !== 'case_id'; });

            foreach ($new_disease_ids as $extra_disease_id) {
                $selectParts = [];
                foreach ($cloneCols as $c) {
                    if ($c === 'disease_id') {
                        $selectParts[] = (int)$extra_disease_id . " AS `disease_id`";
                    } elseif ($c === 'updated_at') {
                        $selectParts[] = "NOW() AS `updated_at`";
                    } else {
                        $selectParts[] = "`$c`";
                    }
                }
                $insertColsSql = implode(',', array_map(function ($c) { return "`$c`"; }, $cloneCols));
                $selectSql     = implode(',', $selectParts);
                mysqli_query($conn, "INSERT INTO disease_cases ($insertColsSql) SELECT $selectSql FROM disease_cases WHERE case_id = $case_id");
            }
        }

        echo "<script>window.location='reports.php?view=disease&tab=pending&case_id=" . $case_id . "';</script>";
        exit;
    } else {
        casd_alert_redirect('warning', 'Action Not Available', 'This can only be done for Pending, Other / Unidentified reports.', 'reports.php?view=disease&tab=pending&case_id=' . $case_id);
        exit;
    }
}

// 2b. HANDLE SENDING THE DISEASE RECOMMENDATION TO THE FARMER
// If the reviewer picked an actual treatment instruction (has_recommendation=1),
// the disease name + field observation is sent first as its own separate message,
// then the recommendation itself as a second message — two bubbles in the thread.
// A plain free-typed message (no instruction picked) is sent as-is, with no
// disease info attached.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_recommendation'])) {
    $case_id         = mysqli_real_escape_string($conn, $_POST['case_id_hidden']);
    $rec_text        = mysqli_real_escape_string($conn, $_POST['recommendation_text']);
    $tab_return      = mysqli_real_escape_string($conn, $_POST['return_tab'] ?? 'verified');
    $has_rec         = ($_POST['has_recommendation'] ?? '0') === '1';

    // Field Inspection scheduling — review.php's pinned scheduler posts these two
    // hidden fields alongside every recommendation send:
    //   clear_follow_up_date=1    -> reviewer cancelled the scheduled inspection (X button)
    //   follow_up_date=YYYY-MM-DD -> reviewer scheduled/rescheduled an inspection
    // Neither present -> a plain recommendation message; leave the column untouched.
    $clear_follow_up = ($_POST['clear_follow_up_date'] ?? '0') === '1';
    $follow_up_raw   = trim($_POST['follow_up_date'] ?? '');
    $follow_up_sql   = '';
    if ($clear_follow_up) {
        $follow_up_sql = ", follow_up_date = NULL";
    } elseif ($follow_up_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $follow_up_raw)) {
        $follow_up_esc = mysqli_real_escape_string($conn, $follow_up_raw);
        $follow_up_sql = ", follow_up_date = '$follow_up_esc'";
    }

    $sendQuery = "UPDATE disease_cases 
                  SET recommendation_text = '$rec_text', recommendation_sent = 1, recommendation_sent_at = NOW() $follow_up_sql
                  WHERE case_id = '$case_id'";
    if (mysqli_query($conn, $sendQuery)) {
        if ($has_rec) {
            // Look up this row's reference_id + observation, then pull EVERY disease
            // sharing that reference_id (a report with 2-3 diseases selected is several
            // rows) so the findings message lists all of them as one combined block,
            // not just the disease tied to this representative row.
            $findings_q = mysqli_query($conn, "SELECT reference_id, description FROM disease_cases WHERE case_id = '$case_id' LIMIT 1");
            $findings_row = $findings_q ? mysqli_fetch_assoc($findings_q) : null;
            if ($findings_row) {
                $ref_esc = mysqli_real_escape_string($conn, $findings_row['reference_id']);
                $group_q = mysqli_query($conn, "SELECT d.disease_name, d.description AS disease_description
                                                  FROM disease_cases dc
                                                  LEFT JOIN diseases d ON dc.disease_id = d.disease_id
                                                  WHERE dc.reference_id = '$ref_esc'
                                                  ORDER BY dc.case_id ASC");
                $diseaseBlocks = [];
                if ($group_q) {
                    while ($g = mysqli_fetch_assoc($group_q)) {
                        $gName = $g['disease_name'] ?: 'Hindi tiyak';
                        $gDesc = trim($g['disease_description'] ?? '');
                        if ($gDesc === '') { $gDesc = 'Walang detalyadong paglalarawan para sa sakit na ito.'; }
                        $diseaseBlocks[] = "🌽 {$gName}\n{$gDesc}";
                    }
                }
                if (empty($diseaseBlocks)) { $diseaseBlocks[] = "🌽 Hindi tiyak\nWalang detalyadong paglalarawan para sa sakit na ito."; }

                $caseDesc = trim(stripFarmerMarker($findings_row['description'] ?? ''));
                if ($caseDesc === '') { $caseDesc = 'Walang detalyadong obserbasyon.'; }

                $findingsHeader = count($diseaseBlocks) > 1 ? "Natukoy na mga Sakit:\n\n" : "Natukoy na Sakit:\n\n";
                $findingsMsg = mysqli_real_escape_string($conn,
                    $findingsHeader . implode("\n\n", $diseaseBlocks) . "\n\nObserbasyon sa Bukid: {$caseDesc}");
                mysqli_query($conn, "INSERT INTO case_messages (case_id, sender, message_text) VALUES ('$case_id', 'staff', '$findingsMsg')");
            }
        }
        mysqli_query($conn, "INSERT INTO case_messages (case_id, sender, message_text) VALUES ('$case_id', 'staff', '$rec_text')");
        echo "<script>window.location='reports.php?view=disease&tab=" . $tab_return . "&case_id=" . $case_id . "&open_msg=1';</script>";
        exit;
    }
}

// Helper: extract farmer name from description field
function extractFarmerName($description) {
    if (preg_match('/^\[FARMER:(.+?)\]\n?/s', $description ?? '', $m)) {
        return trim($m[1]);
    }
    return null;
}

// Helper: strip farmer marker from description for display
function stripFarmerMarker($description) {
    return preg_replace('/^\[FARMER:.+?\]\n?/s', '', $description ?? '');
}

// Helper: combine a case report's per-disease rows (name, description, treatment,
// prevention) into single display strings, so a report with 2-3 diseases selected
// still reads and behaves as ONE file instead of separate ones.
// With only one disease, the combined strings are identical to that disease's own
// fields (no visual change from before); with 2+ diseases, each block is labeled
// with its disease name so reviewers/farmers can tell which part applies to which.
// Returns ['name' => ..., 'description' => ..., 'treatment' => ..., 'prevention' => ...]
function combineDiseaseFields($diseases) {
    $multi = count($diseases) > 1;

    $names = array_map(fn($d) => $d['disease_name'] ?: 'Unidentified', $diseases);
    $combinedName = implode(' + ', $names);

    $descParts = [];
    foreach ($diseases as $d) {
        $dn = $d['disease_name'] ?: 'Unidentified';
        $dd = trim($d['disease_description'] ?? '');
        if ($dd === '') { $dd = 'No description on file for this disease.'; }
        $descParts[] = $multi ? "【{$dn}】\n{$dd}" : $dd;
    }
    $combinedDesc = implode("\n\n", $descParts);

    $treatParts = [];
    foreach ($diseases as $d) {
        $lines = preg_split('/\r\n|\r|\n/', trim($d['recommended_treatment'] ?? ''));
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;
            $treatParts[] = $multi ? "[{$d['disease_name']}] {$ln}" : $ln;
        }
    }
    $combinedTreat = implode("\n", $treatParts);

    $prevParts = [];
    foreach ($diseases as $d) {
        $pm = trim($d['prevention_measures'] ?? '');
        if ($pm === '') continue;
        $prevParts[] = $multi ? "【{$d['disease_name']}】\n{$pm}" : $pm;
    }
    $combinedPrev = implode("\n\n", $prevParts);

    return [
        'name'        => $combinedName,
        'description' => $combinedDesc,
        'treatment'   => $combinedTreat,
        'prevention'  => $combinedPrev,
    ];
}

// Helper: pull the disease_id/name/description/treatment/prevention fields off a
// disease_cases (JOIN diseases) row into the small array shape combineDiseaseFields()
// and the "diseases" list sent to the browser both expect.
function diseaseRowSlice($row) {
    return [
        'disease_id'            => (int)$row['disease_id'],
        'disease_name'          => $row['disease_name'],
        'disease_description'   => $row['disease_description'],
        'recommended_treatment' => $row['recommended_treatment'],
        'prevention_measures'   => $row['prevention_measures'],
    ];
}

// Helper: load every logged message for a case, oldest first (the chat thread)
function fetchCaseMessages($conn, $case_id) {
    $messages = [];
    $case_id  = (int)$case_id;
    $q = mysqli_query($conn, "SELECT sender, message_text, created_at FROM case_messages WHERE case_id = $case_id ORDER BY created_at ASC, message_id ASC");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $messages[] = [
                'sender'     => $r['sender'],
                'message'    => $r['message_text'],
                'created_at' => $r['created_at'],
            ];
        }
    }
    return $messages;
}

// Helper: build the sent-recommendations list for a case row, falling back to the
// older single recommendation_text column for cases sent before case_messages existed.
// Also folds in the farmer's reply (disease_cases.farmer_reply_text / farmer_reply_at,
// a single-field reply — not its own table) as a 'farmer' bubble, placed in chronological
// order alongside the staff messages so the thread reads like a real conversation.
function buildCaseMessages($conn, $row) {
    $messages = fetchCaseMessages($conn, $row['case_id']);
    if (empty($messages) && !empty($row['recommendation_text'])) {
        $messages[] = [
            'sender'     => 'staff',
            'message'    => $row['recommendation_text'],
            'created_at' => $row['recommendation_sent_at'] ?? null,
        ];
    }

    if (!empty($row['farmer_reply_text'])) {
        $messages[] = [
            'sender'     => 'farmer',
            'message'    => $row['farmer_reply_text'],
            'created_at' => $row['farmer_reply_at'] ?? null,
        ];
    }

    usort($messages, function ($a, $b) {
        $ta = strtotime($a['created_at'] ?? '') ?: 0;
        $tb = strtotime($b['created_at'] ?? '') ?: 0;
        return $ta <=> $tb;
    });

    return $messages;
}

// 3. STATS CALCULATION
$stats_query = mysqli_query($conn, "SELECT COUNT(*) as total, 
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending, 
    COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified, 
    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved, 
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected FROM disease_cases WHERE $manualOnlySql");
$stats     = mysqli_fetch_assoc($stats_query);

// AI scans are counted on their own and never mixed into the report numbers above.
$scanStats = ['total' => 0, 'week' => 0];
$ss_q = mysqli_query($conn, "SELECT
    COUNT(DISTINCT COALESCE(NULLIF(reference_id,''), CONCAT('c', case_id))) AS total,
    COUNT(DISTINCT CASE WHEN report_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          THEN COALESCE(NULLIF(reference_id,''), CONCAT('c', case_id)) END) AS week
    FROM disease_cases WHERE `source` = 'scan'");
if ($ss_q && $ss_row = mysqli_fetch_assoc($ss_q)) {
    $scanStats = ['total' => (int)$ss_row['total'], 'week' => (int)$ss_row['week']];
}
$activeTab = $_GET['tab'] ?? 'pending';

// ── TOP-LEVEL REPORT VIEW: Disease Reports vs Planting & Harvesting Reports ──
// These are two different kinds of farmer-submitted reports. They used to live
// on one continuous page — the disease case table first, with the planting &
// harvesting section buried below it — which made the page feel like a wall of
// mixed reports. Now they're two separate screens, switched at the very top of
// the page, so only one report type (and its own stats/actions) is ever on
// screen at once.
$view = $_GET['view'] ?? 'disease';
if (!in_array($view, ['disease', 'scans', 'farm'], true)) { $view = 'disease'; }

// FROM DASHBOARD MAP
$autoOpenCaseId   = isset($_GET['case_id'])     ? (int)$_GET['case_id']     : 0;
$filterBarangayId = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;

// A deep link from the dashboard map always points at a specific disease case,
// so always land on the Disease Reports view for it regardless of ?view=.
if ($autoOpenCaseId || $filterBarangayId) { $view = 'disease'; }

// ...unless that case was AI-scanned: scanned cases live in the AI Scans view, not in Reports.
// Send the browser there instead (and highlight the row).
if ($autoOpenCaseId) {
    $dl_q   = mysqli_query($conn, "SELECT `source` FROM disease_cases WHERE case_id = $autoOpenCaseId LIMIT 1");
    $dl_row = $dl_q ? mysqli_fetch_assoc($dl_q) : null;
    if ($dl_row && $dl_row['source'] === 'scan') {
        $dl_url = 'reports.php?view=scans&scan_id=' . $autoOpenCaseId
                . ($filterBarangayId ? '&brgy_filter=' . $filterBarangayId : '');
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Location: ' . $dl_url);
        } else {
            echo "<script>window.location='" . $dl_url . "';</script>";
        }
        exit;
    }
}

// ── TAB FILTERS: date range + barangay dropdown (applies within each tab) ──
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to']   ?? '');
$filterBrgyDrop = isset($_GET['brgy_filter']) ? (int)$_GET['brgy_filter'] : 0;
if ($filterBrgyDrop) { $filterBarangayId = $filterBrgyDrop; }

$allBarangays_q = mysqli_query($conn, "SELECT id, name FROM barangays ORDER BY name ASC");

if ($filterBarangayId && !isset($_GET['tab'])) {
    $tab_q = mysqli_query($conn, "SELECT status FROM disease_cases WHERE $manualOnlySql AND barangay_id=$filterBarangayId ORDER BY FIELD(status,'pending','verified','resolved','rejected') LIMIT 1");
    if ($tab_q && $row_t = mysqli_fetch_assoc($tab_q)) {
        $activeTab = $row_t['status'];
    }
}

$autoOpenData = null;
if ($autoOpenCaseId) {
    // Resolve which report (reference_id) this case_id belongs to, then pull EVERY
    // disease row sharing that reference_id — same grouping as the table above —
    // so a multi-disease report reopens as one combined file, not just its first disease.
    $ao_ref_q   = mysqli_query($conn, "SELECT reference_id FROM disease_cases WHERE case_id = $autoOpenCaseId LIMIT 1");
    $ao_ref_row = $ao_ref_q ? mysqli_fetch_assoc($ao_ref_q) : null;

    if ($ao_ref_row) {
        $ao_ref = mysqli_real_escape_string($conn, $ao_ref_row['reference_id']);
        $ao_q = mysqli_query($conn, "SELECT dc.*, b.name AS brgy_name, d.disease_name,
            d.description AS disease_description,
            d.recommended_treatment, d.prevention_measures,
            f.profile_farmers AS farmer_photo,
            " . personnel_log_select_sql() . "
            FROM disease_cases dc
            LEFT JOIN barangays b  ON dc.barangay_id = b.id
            LEFT JOIN diseases d   ON dc.disease_id  = d.disease_id
            LEFT JOIN farmers f    ON f.farmer_name = SUBSTRING_INDEX(SUBSTRING(dc.description, LOCATE('[FARMER:', dc.description) + 8), ']', 1)
            " . personnel_log_join_sql() . "
            WHERE dc.reference_id = '$ao_ref'
            ORDER BY dc.case_id ASC");
        $ao_rows = [];
        if ($ao_q) { while ($r = mysqli_fetch_assoc($ao_q)) { $ao_rows[] = $r; } }

        if (!empty($ao_rows)) {
            $ao_primary  = $ao_rows[0]; // ORDER BY case_id ASC → lowest case_id = representative row
            $ao_diseases = array_map('diseaseRowSlice', $ao_rows);
            $ao_combined = combineDiseaseFields($ao_diseases);

            $autoOpenData = $ao_primary;
            $autoOpenData['resolved_farmer_name'] = extractFarmerName($ao_primary['description']) ?? '— Unassigned —';
            $autoOpenData['clean_description']    = stripFarmerMarker($ao_primary['description']);
            $autoOpenData['messages']             = buildCaseMessages($conn, $ao_primary);
            $autoOpenData['disease_name']         = $ao_combined['name'];
            $autoOpenData['disease_description']  = $ao_combined['description'];
            $autoOpenData['recommended_treatment']= $ao_combined['treatment'];
            $autoOpenData['prevention_measures']  = $ao_combined['prevention'];
            $autoOpenData['diseases']             = $ao_diseases;
            $activeTab = $autoOpenData['status'];
        }
    }
}

// Date constraints used by the new case report form
$today   = date('Y-m-d');
$minDate = date('Y-m-d', strtotime('-130 days'));

// Collected for bulk PDF export of the active tab — only populated when the
// Disease Reports view actually builds its table below.
$tableRows = [];
?>

<style>
    .modal-container { max-height: 92vh; display: flex; flex-direction: column; border-radius: 2.5rem; overflow: hidden; }
    .modal-scroll-area { overflow-y: auto; flex-grow: 1; padding: 2rem; scrollbar-width: thin; }
    .farmer-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

    .report-row { transition: all 0.3s ease; position: relative; }
    .report-row:hover { background-color: #f8fafc; transform: translateX(5px); }
    .report-row::after { content:''; position:absolute; left:0; top:0; height:100%; width:0; background:#10b981; transition:width 0.3s ease; border-radius:4px; }
    .report-row:hover::after { width:4px; }

    .btn-intel { transition: all 0.3s cubic-bezier(0.4,0,0.2,1); cursor:pointer; }
    .btn-intel:hover { transform:translateY(-3px); box-shadow:0 12px 20px -5px rgba(16,185,129,0.4); filter:brightness(1.1); }

    /* ── Updated tab styles to match stat card color theme ── */
    .tab-pill {
        px: 6; py: 2.5;
        border-radius: 0.75rem;
        font-size: 0.625rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        transition: color 0.25s ease, background-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-block;
        padding: 0.625rem 1.5rem;
        cursor: pointer;
    }
    .tab-pill.inactive {
        color: #9ca3af;
    }
    .tab-pill.inactive:hover {
        color: #374151;
        background: rgba(255,255,255,0.85);
        transform: translateY(-1px) scale(1.03);
        box-shadow: 0 4px 12px -2px rgba(0,0,0,0.08);
    }
    .tab-pill.active-pending {
        background-color: #f59e0b;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(245,158,11,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-pending:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(245,158,11,0.55);
    }
    .tab-pill.active-verified {
        background-color: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(37,99,235,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-verified:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(37,99,235,0.55);
    }
    .tab-pill.active-resolved {
        background-color: #10b981;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(16,185,129,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-resolved:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(16,185,129,0.55);
    }
    .tab-pill.active-received {
        background-color: #9333ea;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(147,51,234,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-received:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(147,51,234,0.55);
    }
    .tab-pill.active-rejected {
        background-color: #e11d48;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(225,29,72,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-all {
        background-color: #1f2937;
        color: #ffffff;
        box-shadow: 0 8px 20px -4px rgba(31,41,55,0.45);
        transform: scale(1.05);
    }
    .tab-pill.active-all:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(31,41,55,0.55);
    }
    .tab-pill.active-rejected:hover {
        transform: scale(1.08);
        box-shadow: 0 10px 24px -4px rgba(225,29,72,0.55);
    }
    @keyframes pop { 0%{transform:scale(.95)} 100%{transform:scale(1)} }

    /* ── AJAX tab/filter switching for the Disease Reports panel: no full
       page reload when hopping between Pending / Verified / Resolved. ── */
    #drPanel { transition: opacity .15s ease; }
    #drPanel.dr-panel-loading { opacity: .45; pointer-events: none; }

    /* ── Farm-Reports-style browser tabs, reused here so Disease Reports and
       Farm Reports share the same tab look ── */
    .dr-tabs-wrap { border-bottom: 1.5px solid #e5e7eb; }
    .dr-tabs {
        display: flex;
        align-items: flex-end;
        gap: 3px;
        padding: 0 4px;
        overflow-x: auto;
    }
    .dr-tab {
        position: relative;
        top: 1.5px;
        padding: 9px 16px 8px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #9ca3af;
        background: #f3f4f6;
        border: 1.5px solid #e5e7eb;
        border-bottom: none;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        white-space: nowrap;
        transition: all .15s ease;
        text-decoration: none;
        display: inline-block;
    }
    .dr-tab:hover { color: #374151; background: #e9eaec; }
    .dr-tab-active {
        top: 0;
        color: #111827;
        background: #fff;
        z-index: 1;
    }
    .dr-tab-count { margin-left: 3px; font-weight: 800; color: #c2c6cc; }
    .dr-tab-active .dr-tab-count { color: #9ca3af; }

    /* ── Farm-Reports-style row list, reused here so Disease Reports and
       Farm Reports share the same row look ── */
    .dr-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 24px;
        cursor: pointer;
        transition: background .15s ease;
    }
    .dr-row:hover { background: #fafafa; }
    .dr-type-chip {
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 2px 8px;
        border-radius: 999px;
        background: #d1fae5;
        color: #065f46;
    }
    .dr-empty {
        text-align: center;
        padding: 56px 24px;
        color: #9ca3af;
    }

    /* ── Smooth hover feedback for filter inputs and action buttons ── */
    .filter-input {
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .filter-input:hover {
        border-color: #a7f3d0;
        background-color: #ffffff;
    }
    .filter-input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16,185,129,0.15);
    }
    .btn-intel {
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease, filter 0.2s ease;
    }
    .btn-intel:hover {
        transform: translateY(-2px) scale(1.02);
        filter: brightness(1.06);
        box-shadow: 0 10px 22px -6px rgba(0,0,0,0.25);
    }
    .btn-intel:active {
        transform: translateY(0) scale(0.98);
    }

    .field-label {
        display:block; font-size:0.6rem; font-weight:900; text-transform:uppercase;
        letter-spacing:0.12em; color:#6b7280; margin-bottom:0.4rem; margin-left:0.15rem;
    }
    .field-input {
        width:100%; padding:0.85rem 1rem; border-radius:1rem; background:#ffffff;
        font-weight:700; border:1.5px solid #e5e7eb; outline:none;
        font-size:0.82rem; color:#111827; box-sizing:border-box;
        transition:border-color 0.2s, box-shadow 0.2s;
        -webkit-appearance:none;
    }
    .field-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,0.12); }
    .field-input::placeholder { color:#9ca3af; font-weight:600; }

    /* Visually mark out-of-range dates */
    input[type="date"]:out-of-range { border-color: #fca5a5; background: #fff1f2; }

    .sev-badge { display:inline-block; font-size:0.58rem; font-weight:900; text-transform:uppercase; letter-spacing:0.1em; padding:2px 8px; border-radius:999px; margin-top:3px; }
    .sev-low      { background:#d1fae5; color:#065f46; }
    .sev-moderate { background:#fef3c7; color:#92400e; }
    .sev-high     { background:#fee2e2; color:#991b1b; }
    .sev-critical { background:#fce7f3; color:#9d174d; }

    .upload-zone {
        border:2px dashed #d1d5db; border-radius:1rem; padding:1.5rem;
        text-align:center; cursor:pointer; transition:all 0.25s ease;
        background:#f9fafb; position:relative;
    }
    .upload-zone:hover { border-color:#10b981; background:#f0fdf4; }
    .upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
    #photo_preview_wrap { display:none; margin-top:0.75rem; text-align:center; }
    #photo_preview_wrap img { width:100%; max-height:180px; object-fit:cover; border-radius:0.75rem; border:1.5px solid #d1d5db; }

    /* Note: the "Review File" popup's own CSS (view modal, messenger popup,
       status pills, treatment checklist, GPS copy button, photo lightbox,
       and the reportPopIn animation they use) now lives in review.php,
       rendered by render_review_modal(). */
</style>
<?php casd_dialog_assets(); ?>

<!-- ═══ REPORT TYPE SWITCHER ═══
     Always visible at the top, regardless of which view is active. This is the
     single place a user picks which kind of report they want to see or file —
     Disease Reports and Farm (Planting & Harvesting) Reports never mix on the
     same screen anymore.
     Card order, left to right: Disease Reports, Farm Reports, AI Scans. -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <a href="?view=disease" class="btn-intel flex items-center gap-4 p-6 rounded-[1.75rem] border-2 <?= $view === 'disease' ? 'bg-emerald-600 border-emerald-600 shadow-lg' : 'bg-white border-gray-100 hover:border-emerald-200' ?>">
        <div class="w-12 h-12 flex-shrink-0 rounded-2xl flex items-center justify-center text-2xl <?= $view === 'disease' ? 'bg-white/15' : 'bg-emerald-50' ?>">🌽</div>
        <div class="text-left">
            <p class="font-black text-sm uppercase tracking-wide <?= $view === 'disease' ? 'text-white' : 'text-gray-800' ?>">Disease Reports</p>
            <p class="text-[11px] font-bold <?= $view === 'disease' ? 'text-white/80' : 'text-gray-400' ?>"><?= (int)$stats['total'] ?> manual &middot; <?= (int)$stats['pending'] ?> awaiting review</p>
        </div>
    </a>
    <a href="?view=farm" class="btn-intel flex items-center gap-4 p-6 rounded-[1.75rem] border-2 <?= $view === 'farm' ? 'bg-emerald-600 border-emerald-600 shadow-lg' : 'bg-white border-gray-100 hover:border-emerald-200' ?>">
        <div class="w-12 h-12 flex-shrink-0 rounded-2xl flex items-center justify-center text-2xl <?= $view === 'farm' ? 'bg-white/15' : 'bg-emerald-50' ?>">🌱</div>
        <div class="text-left">
            <p class="font-black text-sm uppercase tracking-wide <?= $view === 'farm' ? 'text-white' : 'text-gray-800' ?>">Farm Reports</p>
            <p class="text-[11px] font-bold <?= $view === 'farm' ? 'text-white/80' : 'text-gray-400' ?>">Farmer planting &amp; harvest activity</p>
        </div>
    </a>
    <a href="?view=scans" class="btn-intel flex items-center gap-4 p-6 rounded-[1.75rem] border-2 <?= $view === 'scans' ? 'bg-emerald-600 border-emerald-600 shadow-lg' : 'bg-white border-gray-100 hover:border-emerald-200' ?>">
        <div class="w-12 h-12 flex-shrink-0 rounded-2xl flex items-center justify-center text-2xl <?= $view === 'scans' ? 'bg-white/15' : 'bg-emerald-50' ?>">&#128247;</div>
        <div class="text-left">
            <p class="font-black text-sm uppercase tracking-wide <?= $view === 'scans' ? 'text-white' : 'text-gray-800' ?>">AI Scans</p>
            <p class="text-[11px] font-bold <?= $view === 'scans' ? 'text-white/80' : 'text-gray-400' ?>"><?= (int)$scanStats['total'] ?> scanned &middot; <?= (int)$scanStats['week'] ?> this week</p>
        </div>
    </a>
</div>

<?php if ($view === 'disease'): ?>

<?php if ($filterBarangayId): ?>
<div class="mb-6">
    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-black uppercase tracking-wide">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
        Filtered by Barangay
        <?php $bn_q = mysqli_query($conn,"SELECT name FROM barangays WHERE id=$filterBarangayId LIMIT 1"); if($bn_q && $bn_r = mysqli_fetch_assoc($bn_q)) echo htmlspecialchars($bn_r['name']); ?>
        &nbsp;&mdash;&nbsp;<a href="reports.php" class="underline hover:text-amber-900">Clear filter</a>
    </div>
</div>
<?php endif; ?>

<?php render_new_case_report_modal($conn, $today, $minDate); ?>

<!-- ═══ CASE FILE LIST ═══ -->
<div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl">
    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-2xl">🌽</div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">Disease Reports</h3>
                <p class="text-gray-400 font-medium text-[11px] mt-0.5 tracking-tight">Manual reports that need a person to review them. AI-scanned cases are under AI Scans.</p>
            </div>
        </div>
        <div class="flex gap-3 shrink-0">
            <a href="dashboard.php" class="btn-intel bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Back to Map
            </a>
            <button onclick="openCreateModal()" type="button" class="btn-intel bg-gray-900 text-white px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shrink-0">
                + New Report
            </button>
        </div>
    </div>

    <!-- ── AJAX panel: tabs + filters + case list. Swapped in place via JS
         (see bottom of file) instead of a full page navigation, so switching
         between Pending / Verified / Resolved doesn't reload the page. ── -->
    <?php render_case_calendar_assets(); ?>
    <div id="drPanel">

    <!-- ── Tabs: same browser-tab look as the Farm Reports section ── -->
    <div class="dr-tabs-wrap">
        <div class="dr-tabs">
            <?php
            $tabConfig = [
                'all'      => ['label' => 'All',      'count' => (int)$stats['total']],
                'pending'  => ['label' => 'Pending',  'count' => (int)$stats['pending']],
                'verified' => ['label' => 'Verified', 'count' => (int)$stats['verified']],
                'resolved' => ['label' => 'Resolved', 'count' => (int)$stats['resolved']],
                'rejected' => ['label' => 'Rejected', 'count' => (int)$stats['rejected']],
            ];
            $tabQueryExtra = '';
            if ($filterBarangayId) $tabQueryExtra .= '&brgy_filter=' . $filterBarangayId;
            if ($filterDateFrom)   $tabQueryExtra .= '&date_from=' . urlencode($filterDateFrom);
            if ($filterDateTo)     $tabQueryExtra .= '&date_to='   . urlencode($filterDateTo);

            foreach ($tabConfig as $tab => $cfg): 
                $isActive   = ($activeTab === $tab);
                $activeClass = $isActive ? 'dr-tab dr-tab-active' : 'dr-tab';
            ?>
                <a href="?tab=<?= $tab ?><?= $tabQueryExtra ?>" class="<?= $activeClass ?>">
                    <?= $cfg['label'] ?> <span class="dr-tab-count"><?= $cfg['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Search + filters, all in one row: search is instant/client-side
         (same behavior as the Farm Reports search bar), the barangay/date
         fields alongside it still submit server-side since each tab only
         loads its own page of reports. Export sits at the row's far end. ── -->
    <div class="pt-5 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-2.5 flex-wrap flex-1 min-w-0">
            <div class="relative flex-1 min-w-[180px]">
                <svg class="w-4 h-4 text-gray-350 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                <input type="text" id="drSearchInput" placeholder="Search by farmer, report number, or disease..."
                       class="w-full pl-9 pr-3 py-2.5 text-xs font-medium rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-300"
                       oninput="filterDrRows()">
            </div>

            <form method="get" class="flex items-center gap-2 flex-wrap">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

                <select name="brgy_filter" onchange="drSubmitFilterForm(this.form)"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 uppercase tracking-wide">
                    <option value="0">All Barangays</option>
                    <?php
                    if ($allBarangays_q) {
                        mysqli_data_seek($allBarangays_q, 0);
                        while ($b = mysqli_fetch_assoc($allBarangays_q)):
                    ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ($filterBarangayId == $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endwhile; } ?>
                </select>

                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5">
                <span class="text-[10px] font-black text-gray-300 uppercase">to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5">

                <button type="submit"
                    class="btn-intel bg-gray-900 text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest">
                    Filter
                </button>
                <?php if ($filterBarangayId || $filterDateFrom || $filterDateTo): ?>
                <a href="?tab=<?= htmlspecialchars($activeTab) ?>"
                    class="text-[10px] font-black text-gray-400 hover:text-gray-600 uppercase underline">
                    Clear
                </a>
                <?php endif; ?>
            </form>

            <button type="button" id="drCalToggle" onclick="drToggleCalendar()"
                class="btn-intel bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center gap-2 shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"></path></svg>
                Calendar
            </button>
        </div>

        <?php if ($activeTab === 'verified' || $activeTab === 'resolved'): ?>
        <button id="exportTabBtn" onclick="exportAllReports()"
            class="btn-intel bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm flex items-center gap-2 shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z"/></svg>
            Export <?= ucfirst($activeTab) ?> Reports (PDF)
        </button>
        <?php endif; ?>
    </div>
    
    <?php
    // ── CALENDAR DATA: one entry per REPORT (a report with several diseases is several rows sharing a
    // reference_id, so it is grouped exactly like the list below), dated by when it was filed.
    // Covers every status, so the calendar is a true date summary no matter which tab is open;
    // it follows the barangay filter only. Picking a day applies the date filter above.
    $calEvents  = [];
    $calBrgySql = "WHERE $manualOnlySql" . ($filterBarangayId ? " AND barangay_id = " . (int)$filterBarangayId : "");
    $cal_q = mysqli_query($conn, "SELECT reference_id, DATE(MIN(report_date)) AS d, MIN(status) AS s
                                  FROM disease_cases $calBrgySql GROUP BY reference_id");
    if ($cal_q) {
        while ($c = mysqli_fetch_assoc($cal_q)) {
            if (!empty($c['d'])) { $calEvents[] = ['d' => $c['d'], 's' => $c['s'] ?: 'pending']; }
        }
    }
    $calIso      = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : null;
    $calFrom     = $calIso($filterDateFrom);
    $calTo       = $calIso($filterDateTo);
    $calSelected = ($calFrom && $calFrom === $calTo) ? $calFrom : null;
    ?>
    <div id="drCalendarWrap" class="hidden pt-4">
        <div id="drCalendarMount"></div>
    </div>
    <script type="application/json" id="drCalendarData"><?= json_encode([
        'events' => $calEvents, 'selected' => $calSelected, 'from' => $calFrom, 'to' => $calTo,
    ], JSON_HEX_TAG) ?></script>

    <div class="divide-y divide-gray-50 mt-2">
        <?php
        $brgy_filter = $filterBarangayId ? "AND dc.barangay_id = $filterBarangayId" : "";

        $date_filter = "";
        if ($filterDateFrom !== '') {
            $safeFrom = mysqli_real_escape_string($conn, $filterDateFrom);
            $date_filter .= " AND dc.report_date >= '$safeFrom'";
        }
        if ($filterDateTo !== '') {
            $safeTo = mysqli_real_escape_string($conn, $filterDateTo);
            $date_filter .= " AND dc.report_date <= '$safeTo 23:59:59'";
        }

        // "All" shows every status (including rejected, which otherwise has
        // no tab of its own) — every other tab still filters to its status.
        $safeActiveTab = mysqli_real_escape_string($conn, $activeTab);
        $status_filter = ($activeTab === 'all') ? '' : "AND dc.status = '$safeActiveTab'";

        $query = "SELECT dc.*, 
                    b.name AS brgy_name, 
                    d.disease_name,
                    d.description AS disease_description,
                    d.recommended_treatment,
                    d.prevention_measures,
                    f.profile_farmers AS farmer_photo,
                    " . personnel_log_select_sql() . "
                  FROM disease_cases dc 
                  LEFT JOIN barangays b ON dc.barangay_id = b.id 
                  LEFT JOIN diseases d  ON dc.disease_id  = d.disease_id
                  LEFT JOIN farmers f   ON f.farmer_name = SUBSTRING_INDEX(SUBSTRING(dc.description, LOCATE('[FARMER:', dc.description) + 8), ']', 1)
                  " . personnel_log_join_sql() . "
                  WHERE $manualOnlySqlDc $status_filter $brgy_filter $date_filter 
                  ORDER BY dc.report_date DESC, dc.case_id ASC";
        $result = mysqli_query($conn, $query);

        // ── GROUP ROWS BY reference_id ──
        // A report with 2-3 diseases selected is stored as several disease_cases
        // rows sharing one reference_id. Group them here so the list (and every
        // action below) treats that report as ONE file, not one file per disease.
        $groups      = [];  // reference_id => ['primary' => row, 'diseases' => [...], 'case_ids' => [...]]
        $groupOrder  = [];  // preserves first-seen order (already sorted by report_date desc)
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $ref = $row['reference_id'];
                if (!isset($groups[$ref])) {
                    $groups[$ref] = ['primary' => $row, 'diseases' => [], 'case_ids' => []];
                    $groupOrder[] = $ref;
                }
                // Primary row = lowest case_id in the group — the one representative
                // row used for farmer/severity/date/photo/messaging fields.
                if ((int)$row['case_id'] < (int)$groups[$ref]['primary']['case_id']) {
                    $groups[$ref]['primary'] = $row;
                }
                $groups[$ref]['case_ids'][] = (int)$row['case_id'];
                $groups[$ref]['diseases'][] = diseaseRowSlice($row);
            }
        }

        $tableRows = []; // collected for bulk PDF export (current tab)

        if(count($groupOrder) > 0):
            foreach ($groupOrder as $__ref):
                $group    = $groups[$__ref];
                $row      = $group['primary'];
                $diseases = $group['diseases'];
                $combined = combineDiseaseFields($diseases);

                $farmer_display = extractFarmerName($row['description']) ?? '— Unassigned —';
                $sev_class = match($row['severity'] ?? '') {
                    'low'      => 'sev-low',
                    'moderate' => 'sev-moderate',
                    'high'     => 'sev-high',
                    'critical' => 'sev-critical',
                    default    => 'sev-moderate'
                };
                // Common Rust, Northern Leaf Blight, Gray Leaf Spot and Healthy Corn
                // track an Infection Percentage Rate instead of a severity level —
                // only meaningful when the report is that single disease alone.
                $isInfectionDisease = count($diseases) === 1
                    && in_array($diseases[0]['disease_name'] ?? '', ['Common Rust', 'Northern Leaf Blight', 'Gray Leaf Spot', 'Healthy Corn'], true);
                $modal_row = [
                    'case_id'       => $row['case_id'],
                    'case_ids'      => $group['case_ids'],
                    'reference_id'  => $row['reference_id'],
                    'report_date'   => $row['report_date'],
                    'farmer_photo'  => $row['farmer_photo'],
                    'growth_stage'  => $row['growth_stage'],
                    'farmer_name'   => $farmer_display,
                    'brgy_name'     => $row['brgy_name'] ?? '—',
                    'description'   => stripFarmerMarker($row['description']),
                    'status'        => $row['status'],
                    'remarks'       => $row['remarks'],
                    'severity'      => $row['severity'],
                    'infection_percentage'  => $row['infection_percentage'],
                    'photo_evidence'=> $row['photo_evidence'],
                    'disease_name'  => $combined['name'],
                    'disease_description' => $combined['description'],
                    'diseases'      => $diseases,
                    'latitude'      => $row['latitude'],
                    'longitude'     => $row['longitude'],
                    'gps_accuracy'  => $row['gps_accuracy'],
                    'follow_up_date'=> $row['follow_up_date'] ?? null,
                    'recommended_treatment'  => $combined['treatment'],
                    'prevention_measures'    => $combined['prevention'],
                    'recommendation_sent'    => $row['recommendation_sent']    ?? 0,
                    'recommendation_text'    => $row['recommendation_text']    ?? null,
                    'recommendation_sent_at' => $row['recommendation_sent_at'] ?? null,
                    'messages'               => buildCaseMessages($conn, $row),
                    'personnel'              => personnel_log_build($row),
                ];
                $tableRows[] = $modal_row;

                // Left-edge accent color, same idea as the Farm Reports rows
                // (a colored strip keyed to the row's category) — keyed to the
                // case's status, matching the status-pill colors below:
                // orange = pending, blue = verified, red = rejected, green = resolved.
                $rowAccent = match($row['status']) {
                    'pending'  => '#f97316',
                    'verified' => '#2563eb',
                    'resolved' => '#059669',
                    'rejected' => '#ef4444',
                    default    => '#9ca3af',
                };

                // Searchable text for the client-side search bar — farmer,
                // report #, barangay, and disease name(s), same fields the
                // Farm Reports search matches on.
                $rowSearch = strtolower(implode(' ', [
                    $farmer_display, $row['reference_id'], $row['brgy_name'] ?? '',
                    $combined['name'] ?? '',
                ]));
        ?>
        <div class="dr-row" style="border-left:3px solid <?= $rowAccent ?>"
             data-search="<?= htmlspecialchars($rowSearch) ?>"
             onclick='openViewModal(<?= htmlspecialchars(json_encode($modal_row), ENT_QUOTES, 'UTF-8') ?>)'>
            <div class="flex items-center gap-3.5 min-w-0">
                <?php if (!empty($row['farmer_photo'])): ?>
                <img src="uploads/<?= htmlspecialchars($row['farmer_photo']) ?>"
                     class="farmer-avatar"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="farmer-avatar bg-emerald-100 items-center justify-center text-emerald-600 font-black text-sm rounded-xl" style="display:none">
                    <?= strtoupper(substr($farmer_display, 0, 1)) ?>
                </div>
                <?php else: ?>
                <div class="farmer-avatar bg-emerald-100 flex items-center justify-center text-emerald-600 font-black text-sm rounded-xl shrink-0">
                    <?= strtoupper(substr($farmer_display, 0, 1)) ?>
                </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-gray-900 text-sm truncate"><?= htmlspecialchars($farmer_display) ?></span>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-300"><?= htmlspecialchars($row['reference_id']) ?></span>
                        <span class="dr-type-chip"><?= htmlspecialchars($combined['name'] ?: '—') ?></span>
                    </div>
                    <p class="text-xs text-gray-500 font-medium truncate">
                        <?= htmlspecialchars($row['brgy_name'] ?? '— Unassigned —') ?> &nbsp;&bull;&nbsp; <?= htmlspecialchars(date('M d, Y', strtotime($row['report_date']))) ?> &middot; <?= htmlspecialchars(date('g:i A', strtotime($row['report_date']))) ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <?php if ($isInfectionDisease): ?>
                <span class="sev-badge sev-low"><?= $row['infection_percentage'] !== null ? number_format((float)$row['infection_percentage'], 2) . '%' : 'No data on file' ?></span>
                <?php elseif (!empty($row['severity'])): ?>
                <span class="sev-badge <?= $sev_class ?>"><?= ucfirst($row['severity']) ?></span>
                <?php endif; ?>
                <?php if ($activeTab === 'all'):
                    $statusPillStyle = match($row['status']) {
                        'pending'  => 'background:#ffedd5;color:#c2410c',
                        'verified' => 'background:#dbeafe;color:#1d4ed8',
                        'resolved' => 'background:#d1fae5;color:#065f46',
                        'rejected' => 'background:#fee2e2;color:#b91c1c',
                        default    => 'background:#f3f4f6;color:#6b7280',
                    };
                ?>
                <span class="sev-badge" style="<?= $statusPillStyle ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; else: ?>
            <div class="dr-empty"><p class="text-xs font-bold uppercase tracking-widest">No reports found for this barangay.</p></div>

        <?php endif; ?>
    </div>

    <!-- Shown by filterDrRows() when a search term matches none of the
         currently loaded rows (distinct from the "no reports" message
         above, which covers an empty tab). -->
    <div id="drSearchEmpty" class="dr-empty hidden"><p class="text-xs font-bold uppercase tracking-widest">No reports match your search</p></div>

    <!-- Carries this tab's rows to the client so the PDF export button (and
         the AJAX tab-switch script below) always has the right data, even
         after switching tabs without a page reload. -->
    <script type="application/json" id="drTableRowsData"><?= json_encode($tableRows) ?></script>

    </div><!-- /#drPanel -->
</div>

<?php endif; // end $view === 'disease' ?>

<?php if ($view === 'scans'): ?>
<?php
// ═══ AI SCANS VIEW ═══
// Cases the AI image detector classified (disease_cases.source = 'scan').
// They are a plain log: no Pending -> Verified -> Resolved review, and they are never counted in
// the Disease Reports tabs or stats. Everything else (source = 'manual_report') is a manual report
// and shows up under Disease Reports.
//
// How a scan row is stored (so this view reads it the same way):
//   - disease_id is empty; the AI's answer is in the description text:
//       "AI scan: Corn___Common_Rust detected. Confidence: 92.2%"
//   - there is no [FARMER:...] tag; the farmer is identified by farmer_id.
//   - photo_evidence is a full path such as "uploads/scan_results/scan_5_1782904381.jpg".
//
// This panel deliberately does NOT use #drPanel: the AJAX tab script above swaps #drPanel and
// intercepts its links, and this view is a normal page load (?view=scans) with its own filters.
$tableRows = []; // the PDF-export script further down reads this; nothing to export here

$scanBrgySql = $filterBarangayId ? "AND dc.barangay_id = " . (int)$filterBarangayId : "";
$scanDateSql = "";
if ($filterDateFrom !== '') {
    $scanDateSql .= " AND dc.report_date >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
}
if ($filterDateTo !== '') {
    $scanDateSql .= " AND dc.report_date <= '" . mysqli_real_escape_string($conn, $filterDateTo) . " 23:59:59'";
}
$scanHighlightId = isset($_GET['scan_id']) ? (int)$_GET['scan_id'] : 0;   // set by the dashboard deep link
$scanLimit       = 500;
$scanHasFilters  = ($filterBarangayId || $filterDateFrom !== '' || $filterDateTo !== '');

// Farmer name comes from the farmers table via disease_cases.farmer_id. The key column of
// `farmers` is looked up instead of assumed (farmer_id or id); if it can't be found, the row
// falls back to "Farmer #<id>" rather than breaking the page.
$farmerPk = null;
$farmerCols = [];
$fc_q = mysqli_query($conn, "SHOW COLUMNS FROM farmers");
if ($fc_q) { while ($fc = mysqli_fetch_assoc($fc_q)) { $farmerCols[] = $fc['Field']; } }
if (in_array('farmer_name', $farmerCols, true)) {
    foreach (['farmer_id', 'id'] as $cand) {
        if (in_array($cand, $farmerCols, true)) { $farmerPk = $cand; break; }
    }
}
$scanFarmerSelect = $farmerPk ? "f.farmer_name AS farmer_name_db," : "NULL AS farmer_name_db,";
$scanFarmerJoin   = $farmerPk ? "LEFT JOIN farmers f ON f.`$farmerPk` = dc.farmer_id" : "";

// Read the AI label + confidence out of the description text.
// "Corn___Common_Rust" -> "Common Rust", "Corn__MLN" -> "MLN", "Healthy corn" and "Other" stay as they are.
$scanParse = function ($desc) {
    $label = null; $conf = null; $confNum = null;
    if (preg_match('/AI scan:\s*(.+?)\s+detected\b/i', (string)$desc, $m)) {
        $label = trim(str_replace('_', ' ', preg_replace('/^Corn_+/i', '', trim($m[1]))));
    }
    if (preg_match('/Confidence:\s*([\d.]+)\s*%/i', (string)$desc, $m)) {
        $conf    = rtrim(rtrim(number_format((float)$m[1], 1), '0'), '.') . '%';
        $confNum = (float)$m[1];
    }
    return [$label, $conf, $confNum];
};

// One row per scan. Same grouping idea as Disease Reports (rows sharing a reference_id are one
// file); a scan with an empty reference_id is kept on its own instead of being merged with others.
$scanGroups = [];
$scanOrder  = [];
$scan_q = mysqli_query($conn, "SELECT dc.case_id, dc.reference_id, dc.report_date, dc.description,
            dc.farmer_id, dc.photo_evidence, dc.latitude, dc.longitude, dc.gps_accuracy,
            $scanFarmerSelect
            b.name AS brgy_name, d.disease_name
        FROM disease_cases dc
        LEFT JOIN barangays b ON dc.barangay_id = b.id
        LEFT JOIN diseases d  ON dc.disease_id  = d.disease_id
        $scanFarmerJoin
        WHERE dc.`source` = 'scan' $scanBrgySql $scanDateSql
        ORDER BY dc.report_date DESC, dc.case_id ASC
        LIMIT $scanLimit");
if ($scan_q) {
    while ($r = mysqli_fetch_assoc($scan_q)) {
        $key = trim((string)($r['reference_id'] ?? '')) !== '' ? (string)$r['reference_id'] : 'case-' . $r['case_id'];
        if (!isset($scanGroups[$key])) {
            $scanGroups[$key] = ['primary' => $r, 'diseases' => [], 'confs' => [], 'confnums' => [], 'case_ids' => []];
            $scanOrder[] = $key;
        }
        [$pLabel, $pConf, $pNum] = $scanParse($r['description']);
        $dn = trim((string)(($r['disease_name'] ?? '') !== '' ? $r['disease_name'] : ($pLabel ?? '')));
        if ($dn !== '' && !in_array($dn, $scanGroups[$key]['diseases'], true)) {
            $scanGroups[$key]['diseases'][] = $dn;
        }
        if ($pConf !== null) { $scanGroups[$key]['confs'][] = $pConf; }
        if ($pNum !== null)  { $scanGroups[$key]['confnums'][] = $pNum; }
        $scanGroups[$key]['case_ids'][] = (int)$r['case_id'];
    }
}
?>

<div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl">
    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0 text-2xl">&#128247;</div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 tracking-tight">AI Scans</h3>
                <p class="text-gray-400 font-medium text-[11px] mt-0.5 tracking-tight">Photos the AI scanned and classified. Logged automatically, nothing to review here. Everything else is a manual report under Disease Reports.</p>
            </div>
        </div>
        <div class="flex gap-3 shrink-0">
            <a href="dashboard.php" class="btn-intel bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Back to Map
            </a>
        </div>
    </div>

    <div id="scanPanel">

    <!-- Search is instant/client-side; barangay + date submit as a normal GET (this view has no AJAX panel). -->
    <div class="pt-1 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-2.5 flex-wrap flex-1 min-w-0">
            <div class="relative flex-1 min-w-[180px]">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                <input type="text" id="scanSearchInput" placeholder="Search by farmer, scan number, or disease..."
                       class="w-full pl-9 pr-3 py-2.5 text-xs font-medium rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-300"
                       oninput="filterScanRows()">
            </div>

            <form method="get" class="flex items-center gap-2 flex-wrap">
                <input type="hidden" name="view" value="scans">

                <select name="brgy_filter" onchange="this.form.submit()"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 uppercase tracking-wide">
                    <option value="0">All Barangays</option>
                    <?php
                    if ($allBarangays_q) {
                        mysqli_data_seek($allBarangays_q, 0);
                        while ($b = mysqli_fetch_assoc($allBarangays_q)):
                    ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ($filterBarangayId == $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endwhile; } ?>
                </select>

                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5">
                <span class="text-[10px] font-black text-gray-300 uppercase">to</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                    class="filter-input text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5">

                <button type="submit"
                    class="btn-intel bg-gray-900 text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest">
                    Filter
                </button>
                <?php if ($scanHasFilters): ?>
                <a href="?view=scans" class="text-[10px] font-black text-gray-400 hover:text-gray-600 uppercase underline">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="divide-y divide-gray-50 mt-4">
        <?php if (count($scanOrder) > 0):
            foreach ($scanOrder as $__sref):
                $sg        = $scanGroups[$__sref];
                $srow      = $sg['primary'];
                // Farmer: [FARMER:] tag if a row has one, else the farmers table (by farmer_id), else "Farmer #id".
                $sFarmer   = extractFarmerName($srow['description'])
                             ?? (!empty($srow['farmer_name_db']) ? $srow['farmer_name_db']
                             : ((int)($srow['farmer_id'] ?? 0) > 0 ? 'Farmer #' . (int)$srow['farmer_id'] : '— Unassigned —'));
                $sDiseases = !empty($sg['diseases']) ? implode(' + ', $sg['diseases']) : '—';
                $sConf     = !empty($sg['confs']) ? $sg['confs'][0] : null;
                $sConfNum  = !empty($sg['confnums']) ? $sg['confnums'][0] : null;
                // Display bands only (same colors the modal uses): 80+ green, 60-79 amber, below 60 red.
                $sConfClass = $sConfNum === null ? 'sev-low' : ($sConfNum >= 80 ? 'sev-low' : ($sConfNum >= 60 ? 'sev-moderate' : 'sev-high'));
                $sIsTarget = $scanHighlightId && in_array($scanHighlightId, $sg['case_ids'], true);
                // Scan photos are stored with their full path ("uploads/scan_results/..."); a bare file name lives in uploads/.
                $sPhoto    = trim((string)($srow['photo_evidence'] ?? ''));
                $sPhotoSrc = $sPhoto === '' ? '' : (strpos($sPhoto, '/') !== false ? $sPhoto : 'uploads/' . $sPhoto);
                $sInitial  = preg_match('/\p{L}/u', $sFarmer, $__im) ? htmlspecialchars(strtoupper($__im[0])) : '?';
                $sTs       = strtotime($srow['report_date']);
                $scanModal = [
                    'case_id'        => (int)$srow['case_id'],
                    'reference_id'   => (string)($srow['reference_id'] ?? ''),
                    'farmer'         => $sFarmer,
                    'barangay'       => $srow['brgy_name'] ?? '— Unassigned —',
                    'disease'        => $sDiseases,
                    'confidence'     => $sConf,
                    'confidence_pct' => $sConfNum,
                    'date'           => date('M d, Y', $sTs),
                    'time'           => date('g:i A', $sTs),
                    'photo'          => $sPhotoSrc,
                    'lat'            => ($srow['latitude']  ?? '') !== '' ? $srow['latitude']  : null,
                    'lng'            => ($srow['longitude'] ?? '') !== '' ? $srow['longitude'] : null,
                    'acc'            => ($srow['gps_accuracy'] ?? '') !== '' ? $srow['gps_accuracy'] : null,
                ];
                $sSearch   = strtolower(implode(' ', [
                    $sFarmer, (string)($srow['reference_id'] ?? ''), $srow['brgy_name'] ?? '', $sDiseases,
                ]));
        ?>
        <div class="dr-row scan-row" <?= $sIsTarget ? 'id="scanTargetRow"' : '' ?>
             style="border-left:3px solid #10b981;<?= $sIsTarget ? 'background:#ecfdf5;' : '' ?>"
             data-search="<?= htmlspecialchars($sSearch) ?>"
             role="button" tabindex="0"
             onclick='openScanModal(<?= htmlspecialchars(json_encode($scanModal), ENT_QUOTES, 'UTF-8') ?>)'
             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();this.click();}">
            <div class="flex items-center gap-3.5 min-w-0">
                <?php if ($sPhotoSrc !== ''): ?>
                <img src="<?= htmlspecialchars($sPhotoSrc) ?>" alt="Scan photo"
                     class="farmer-avatar" style="object-fit:cover"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="farmer-avatar bg-emerald-100 items-center justify-center text-emerald-600 font-black text-sm rounded-xl" style="display:none"><?= $sInitial ?></div>
                <?php else: ?>
                <div class="farmer-avatar bg-emerald-100 flex items-center justify-center text-emerald-600 font-black text-sm rounded-xl shrink-0"><?= $sInitial ?></div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-gray-900 text-sm truncate"><?= htmlspecialchars($sFarmer) ?></span>
                        <?php if (!empty($srow['reference_id'])): ?>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-300"><?= htmlspecialchars($srow['reference_id']) ?></span>
                        <?php endif; ?>
                        <span class="dr-type-chip"><?= htmlspecialchars($sDiseases) ?></span>
                    </div>
                    <p class="text-xs text-gray-500 font-medium truncate">
                        <?= htmlspecialchars($srow['brgy_name'] ?? '— Unassigned —') ?> &nbsp;&bull;&nbsp; <?= htmlspecialchars(date('M d, Y', strtotime($srow['report_date']))) ?> &middot; <?= htmlspecialchars(date('g:i A', strtotime($srow['report_date']))) ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <?php if ($sConf !== null): ?>
                <span class="sev-badge <?= $sConfClass ?>"><?= htmlspecialchars($sConf) ?> confidence</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; else: ?>
            <div class="dr-empty">
                <p class="text-xs font-bold uppercase tracking-widest">
                    <?php if ($scanHasFilters): ?>
                        No AI scans match these filters.
                    <?php else: ?>
                        No AI scans yet. They appear here when a case is saved with source = scan.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div id="scanSearchEmpty" class="dr-empty hidden"><p class="text-xs font-bold uppercase tracking-widest">No scans match your search</p></div>

    <?php if (count($scanOrder) >= $scanLimit): ?>
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pt-4 text-center">Showing the latest <?= (int)$scanLimit ?> scans. Use the barangay and date filters to narrow down.</p>
    <?php endif; ?>

    </div><!-- /#scanPanel -->
</div>

<!-- ═══ AI SCAN DETAIL MODAL — opens when a scan row is clicked (openScanModal below).
     Same overlay/centering pattern as the PDF preview modal. ═══ -->
<div id="scanModal" class="hidden fixed inset-0 z-[150]" style="background:rgba(15,23,42,0.72);backdrop-filter:blur(4px);" onclick="if(event.target===this) closeScanModal()">
    <div role="dialog" aria-modal="true" aria-labelledby="scanModalTitle" style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;
        width:min(760px,94vw);max-height:92vh;overflow:auto;
        box-shadow:0 30px 70px rgba(0,0,0,0.4);animation:reportPopIn .22s ease;">

        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 24px 8px;">
            <div style="min-width:0;">
                <p style="color:#10b981;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">AI Scan</p>
                <h3 id="scanModalTitle" style="color:#0f172a;font-size:1.15rem;font-weight:900;margin:0;">—</h3>
                <p id="scanModalRef" style="color:#94a3b8;font-size:0.7rem;font-weight:700;letter-spacing:0.05em;margin:2px 0 0;"></p>
            </div>
            <button type="button" id="scanModalClose" onclick="closeScanModal()" aria-label="Close"
                style="flex-shrink:0;background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.1);color:#64748b;border-radius:8px;padding:8px 12px;cursor:pointer;font-size:1rem;line-height:1;">&#10005;</button>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;padding:12px 24px 24px;">

            <div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;min-height:240px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <img id="scanModalPhoto" alt="Scan photo" style="display:none;width:100%;max-height:340px;object-fit:contain;cursor:zoom-in;" onclick="window.open(this.src,'_blank')">
                    <p id="scanModalNoPhoto" style="color:#94a3b8;font-size:0.72rem;font-weight:700;margin:0;padding:24px;text-align:center;">No photo available</p>
                </div>
                <p id="scanModalPhotoHint" style="display:none;color:#94a3b8;font-size:10px;font-weight:700;margin:6px 0 0;text-align:center;">Click the photo to open it full size</p>
            </div>

            <div style="display:flex;flex-direction:column;gap:14px;min-width:0;">

                <div style="border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;">
                    <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 6px;">AI result</p>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;">
                        <span id="scanModalDisease" style="color:#0f172a;font-size:1.05rem;font-weight:900;"></span>
                        <span id="scanModalConf" style="font-size:1.05rem;font-weight:900;"></span>
                    </div>
                    <div style="height:8px;border-radius:999px;background:#e5e7eb;margin-top:10px;overflow:hidden;">
                        <div id="scanModalBar" style="height:100%;width:0;border-radius:999px;transition:width .3s ease;"></div>
                    </div>
                    <p id="scanModalBand" style="font-size:11px;font-weight:700;margin:6px 0 0;"></p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;">
                    <div style="min-width:0;">
                        <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Farmer</p>
                        <p id="scanModalFarmer" style="color:#0f172a;font-size:0.8rem;font-weight:700;margin:0;word-break:break-word;"></p>
                    </div>
                    <div style="min-width:0;">
                        <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Barangay</p>
                        <p id="scanModalBrgy" style="color:#0f172a;font-size:0.8rem;font-weight:700;margin:0;word-break:break-word;"></p>
                    </div>
                    <div style="min-width:0;">
                        <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Scanned on</p>
                        <p id="scanModalDate" style="color:#0f172a;font-size:0.8rem;font-weight:700;margin:0;"></p>
                    </div>
                    <div style="min-width:0;">
                        <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Case ID</p>
                        <p id="scanModalCase" style="color:#0f172a;font-size:0.8rem;font-weight:700;margin:0;"></p>
                    </div>
                </div>

                <div id="scanModalGeoWrap" style="display:none;border-top:1px solid #f1f5f9;padding-top:12px;">
                    <p style="color:#94a3b8;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Location</p>
                    <p id="scanModalGeo" style="color:#0f172a;font-size:0.8rem;font-weight:700;margin:0;"></p>
                    <a id="scanModalMap" href="#" target="_blank" rel="noopener" style="display:inline-block;margin-top:4px;color:#059669;font-size:0.7rem;font-weight:800;text-decoration:underline;">Open in Maps</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Instant client-side search across the scans already loaded on this page.
function filterScanRows() {
    var panel = document.getElementById('scanPanel');
    if (!panel) return;
    var input = document.getElementById('scanSearchInput');
    var term  = ((input && input.value) || '').trim().toLowerCase();
    var visible = 0;
    panel.querySelectorAll('.scan-row').forEach(function (row) {
        var match = !term || (row.dataset.search || '').indexOf(term) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    var empty = document.getElementById('scanSearchEmpty');
    if (empty) empty.classList.toggle('hidden', !(term && visible === 0));
}

// ── AI scan detail modal ──
function openScanModal(d) {
    var modal = document.getElementById('scanModal');
    if (!modal) return;
    window.__scanLastRow = document.activeElement;

    function txt(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }
    txt('scanModalTitle',   d.disease || 'AI Scan');
    txt('scanModalRef',     d.reference_id || ('Case #' + d.case_id));
    txt('scanModalDisease', d.disease || '—');
    txt('scanModalFarmer',  d.farmer || '—');
    txt('scanModalBrgy',    d.barangay || '—');
    txt('scanModalDate',    (d.date || '') + ' · ' + (d.time || ''));
    txt('scanModalCase',    '#' + d.case_id);

    // Confidence: number + bar + band. Bands are display-only: 80+ high, 60-79 medium, below 60 low.
    var pct = (d.confidence_pct === null || d.confidence_pct === undefined) ? null : parseFloat(d.confidence_pct);
    var bar = document.getElementById('scanModalBar'), conf = document.getElementById('scanModalConf'), band = document.getElementById('scanModalBand');
    if (pct === null || isNaN(pct)) {
        conf.textContent = '—'; conf.style.color = '#94a3b8';
        bar.style.width = '0'; band.textContent = 'No confidence recorded'; band.style.color = '#94a3b8';
    } else {
        var level = pct >= 80 ? ['#10b981', '#047857', 'High confidence']
                  : pct >= 60 ? ['#f59e0b', '#b45309', 'Medium confidence']
                              : ['#ef4444', '#b91c1c', 'Low confidence'];
        conf.textContent = d.confidence || (pct + '%'); conf.style.color = level[1];
        bar.style.background = level[0]; bar.style.width = '0';
        setTimeout(function () { bar.style.width = Math.max(0, Math.min(100, pct)) + '%'; }, 30);
        band.textContent = level[2]; band.style.color = level[1];
    }

    // Photo (falls back to a placeholder if the file is missing)
    var img = document.getElementById('scanModalPhoto'), noPhoto = document.getElementById('scanModalNoPhoto'), hint = document.getElementById('scanModalPhotoHint');
    function showNoPhoto() { img.style.display = 'none'; noPhoto.style.display = 'block'; hint.style.display = 'none'; }
    if (d.photo) {
        img.onerror = showNoPhoto;
        img.onload  = function () { img.style.display = 'block'; noPhoto.style.display = 'none'; hint.style.display = 'block'; };
        img.src = d.photo;
    } else {
        img.removeAttribute('src'); showNoPhoto();
    }

    // Location (only when the scan has coordinates)
    var geoWrap = document.getElementById('scanModalGeoWrap');
    if (d.lat !== null && d.lat !== undefined && d.lng !== null && d.lng !== undefined) {
        var g = parseFloat(d.lat).toFixed(6) + ', ' + parseFloat(d.lng).toFixed(6);
        if (d.acc !== null && d.acc !== undefined && d.acc !== '') { g += '  (±' + parseFloat(d.acc).toFixed(1) + ' m)'; }
        txt('scanModalGeo', g);
        document.getElementById('scanModalMap').href = 'https://www.google.com/maps?q=' + encodeURIComponent(d.lat + ',' + d.lng);
        geoWrap.style.display = 'block';
    } else {
        geoWrap.style.display = 'none';
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    var closeBtn = document.getElementById('scanModalClose');
    if (closeBtn) closeBtn.focus();
}

function closeScanModal() {
    var modal = document.getElementById('scanModal');
    if (!modal || modal.classList.contains('hidden')) return;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    var last = window.__scanLastRow;
    if (last && typeof last.focus === 'function') { last.focus(); }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeScanModal(); }
});

// Arriving from a dashboard map link: scroll to that scan and open its details.
window.addEventListener('DOMContentLoaded', function () {
    var t = document.getElementById('scanTargetRow');
    if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'center' }); t.click(); }
});
</script>
<?php endif; // end $view === 'scans' ?>

<?php if ($view === 'farm'): ?>
<?php render_planting_harvesting_section($conn); ?>
<?php endif; // end $view === 'farm' ?>

<?php
$allDiseasesForPicker = [];
$diseases_q = mysqli_query($conn, "SELECT disease_id, disease_name, description AS disease_description FROM diseases WHERE disease_id != 999 ORDER BY disease_name ASC");
if ($diseases_q) {
    while ($d = mysqli_fetch_assoc($diseases_q)) {
        $allDiseasesForPicker[] = [
            'disease_id'          => (int)$d['disease_id'],
            'disease_name'        => $d['disease_name'],
            'disease_description' => $d['disease_description'],
        ];
    }
}
render_review_modal($STATUS_FLOW, $allDiseasesForPicker);
?>

<!-- ═══ PDF PREVIEW MODAL — every export (single case or whole tab) renders here
     first, from an in-memory blob; nothing is written to disk until the reviewer
     clicks "Download PDF" inside it. Driven by pdf_generator.php's showPdfPreview() /
     closePdfPreview() / downloadPreviewedPdf(). ═══ -->
<div id="pdfPreviewModal" class="hidden fixed inset-0 z-[140]" style="background:rgba(15,23,42,0.72);backdrop-filter:blur(4px);" onclick="if(event.target===this) closePdfPreview()">
    <div id="pdfPreviewBox" style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:20px;
        width:min(820px,96vw);
        height:min(92vh,960px);
        display:flex;
        flex-direction:column;
        overflow:hidden;
        z-index:141;
        box-shadow:0 30px 70px rgba(0,0,0,0.4);
        animation:reportPopIn .22s ease;
    ">
        <div style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid rgba(15,23,42,0.08);">
            <div style="min-width:0;">
                <p style="color:#10b981;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin:0 0 2px;">Preview</p>
                <h3 id="pdfPreviewTitle" style="color:#0f172a;font-size:0.95rem;font-weight:900;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Report Preview</h3>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <button type="button" id="pdfPreviewDownloadBtn" onclick="downloadPreviewedPdf()"
                    style="background:linear-gradient(135deg,#059669,#047857);color:#fff;font-size:0.68rem;font-weight:900;padding:10px 16px;border-radius:10px;border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.08em;box-shadow:0 8px 18px rgba(5,150,105,0.35);display:flex;align-items:center;gap:6px;">
                    <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z"/></svg>
                    Download PDF
                </button>
                <button type="button" onclick="closePdfPreview()" style="background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.1);color:#64748b;border-radius:8px;padding:8px 12px;cursor:pointer;font-size:1rem;line-height:1;">&#10005;</button>
            </div>
        </div>
        <div style="flex:1;min-height:0;background:#525659;">
            <iframe id="pdfPreviewFrame" style="width:100%;height:100%;border:none;" title="PDF Preview"></iframe>
        </div>
    </div>
</div>

<!-- jsPDF — used to build the PDF in-memory for preview; the actual download only
     happens when the reviewer clicks "Download PDF" inside the preview modal above. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>


<script>
// All reports currently loaded for the active tab (used for bulk PDF export).
// `let`, not `const` — the AJAX tab-switch script below reassigns this each
// time the panel is swapped in, so the export button stays in sync.
let currentTabReports = <?= json_encode($tableRows) ?>;

<?php include __DIR__ . '/pdf_generator.php'; ?>

<?php if ($autoOpenData): ?>
window.addEventListener('DOMContentLoaded', function() {
    openViewModal(<?= json_encode([
        'case_id'       => $autoOpenData['case_id'],
        'reference_id'  => $autoOpenData['reference_id'],
        'report_date'   => $autoOpenData['report_date'],
        'growth_stage'  => $autoOpenData['growth_stage'],
        'farmer_name'   => $autoOpenData['resolved_farmer_name'],
        'farmer_photo'  => $autoOpenData['farmer_photo'],
        'brgy_name'     => $autoOpenData['brgy_name'] ?? '—',
        'description'   => $autoOpenData['clean_description'],
        'status'        => $autoOpenData['status'],
        'remarks'       => $autoOpenData['remarks'],
        'severity'      => $autoOpenData['severity'],
        'infection_percentage'  => $autoOpenData['infection_percentage'],
        'photo_evidence'=> $autoOpenData['photo_evidence'],
        'disease_name'  => $autoOpenData['disease_name'],
        'disease_description' => $autoOpenData['disease_description'],
        'diseases'      => $autoOpenData['diseases'],
        'latitude'      => $autoOpenData['latitude'],
        'longitude'     => $autoOpenData['longitude'],
        'gps_accuracy'  => $autoOpenData['gps_accuracy'],
        'follow_up_date' => $autoOpenData['follow_up_date'] ?? null,
        'recommended_treatment'  => $autoOpenData['recommended_treatment'],
        'prevention_measures'    => $autoOpenData['prevention_measures'],
        'recommendation_sent'    => $autoOpenData['recommendation_sent']    ?? 0,
        // After a send-triggered reload (open_msg=1) don't feed the just-sent text
        // back in — renderRecInstructions() would re-match it against the treatment
        // list and silently re-fill the compose box, undoing the "clear on send".
        'recommendation_text'    => (isset($_GET['open_msg']) && $_GET['open_msg'] === '1') ? null : ($autoOpenData['recommendation_text'] ?? null),
        'recommendation_sent_at' => $autoOpenData['recommendation_sent_at'] ?? null,
        'messages'               => $autoOpenData['messages']               ?? [],
        'personnel'              => personnel_log_build($autoOpenData),
    ]) ?>);
    <?php if (isset($_GET['open_msg']) && $_GET['open_msg'] === '1'): ?>
    openMessageModal();
    <?php endif; ?>
});
<?php endif; ?>
<?php if ($filterBarangayId && !$autoOpenData): ?>
window.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('table');
    if (table) table.scrollIntoView({ behavior:'smooth', block:'start' });
});
<?php endif; ?>
</script>

<!-- ═══ AJAX TAB/FILTER SWITCHING FOR THE DISEASE REPORTS PANEL ═══
     Turns the Pending / Verified / Resolved tabs, the barangay/date filters,
     and the "Clear" link inside #drPanel into in-place AJAX swaps instead of
     full-page navigations, so switching tabs doesn't reload the whole page.
     Falls back to a normal navigation if anything goes wrong. -->
<script>
(function () {
    var PANEL_ID = 'drPanel';

    function getPanel() {
        return document.getElementById(PANEL_ID);
    }

    function swapPanel(html, url, pushState) {
        var panel = getPanel();
        if (!panel) { window.location.href = url; return; }

        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newPanel = doc.getElementById(PANEL_ID);
        if (!newPanel) { window.location.href = url; return; }

        panel.replaceWith(newPanel);

        // The calendar lives inside the panel, so it was just replaced too — redraw it.
        if (window.drCalendarInit) { window.drCalendarInit(); }

        // Keep the PDF export button's data in sync with whichever tab is
        // now showing (each panel carries its own rows in a JSON island).
        var dataEl = newPanel.querySelector('#drTableRowsData');
        if (dataEl) {
            try { currentTabReports = JSON.parse(dataEl.textContent || '[]'); }
            catch (e) { /* keep the previous data rather than break export */ }
        }

        if (pushState) {
            history.pushState({ drPanel: true }, '', url);
        }
    }

    function loadPanel(url, pushState) {
        var panel = getPanel();
        if (panel) panel.classList.add('dr-panel-loading');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (resp) {
                if (!resp.ok) throw new Error('Request failed: ' + resp.status);
                return resp.text();
            })
            .then(function (html) { swapPanel(html, url, pushState); })
            .catch(function () { window.location.href = url; })
            .finally(function () {
                var p = getPanel();
                if (p) p.classList.remove('dr-panel-loading');
            });
    }

    window.drLoadPanel = loadPanel;   // used by the calendar to filter by day

    // Tabs + the "Clear filter" link are plain "?..." query links inside the
    // panel — intercept clicks on any of them and swap in place.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('#' + PANEL_ID + ' a[href^="?"]');
        if (!link) return;
        e.preventDefault();
        loadPanel(link.getAttribute('href'), true);
    });

    // The "Filter" button submits the barangay/date form via GET — route
    // that through the same AJAX path.
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('#' + PANEL_ID + ' form[method="get"]');
        if (!form) return;
        e.preventDefault();
        var params = new URLSearchParams(new FormData(form));
        loadPanel(window.location.pathname + '?' + params.toString(), true);
    });

    // The barangay <select> submits on change (not via the Filter button) —
    // called directly from its onchange attribute instead of this.form.submit().
    window.drSubmitFilterForm = function (form) {
        var params = new URLSearchParams(new FormData(form));
        loadPanel(window.location.pathname + '?' + params.toString(), true);
    };

    // Instant client-side search across the rows already loaded for the
    // current tab — same behavior as the Farm Reports search bar. Defined
    // globally (not re-bound per panel) since #drPanel gets replaced whole
    // on every tab switch; this just re-queries the DOM each time it runs.
    window.filterDrRows = function () {
        var panel = getPanel();
        if (!panel) return;
        var input = panel.querySelector('#drSearchInput');
        var term = ((input && input.value) || '').trim().toLowerCase();
        var rows = panel.querySelectorAll('.dr-row');
        var visibleCount = 0;
        rows.forEach(function (row) {
            var matches = !term || (row.dataset.search || '').indexOf(term) !== -1;
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });
        var emptyEl = panel.querySelector('#drSearchEmpty');
        if (emptyEl) emptyEl.classList.toggle('hidden', !(term && visibleCount === 0));
    };

    // Support the browser's Back/Forward buttons for tab/filter changes.
    window.addEventListener('popstate', function () {
        loadPanel(window.location.href, false);
    });
})();
</script>

<!-- ── DISEASE REPORTS CALENDAR: draws the calendar inside the panel and turns a day click into the
     existing date filter (same AJAX panel swap the tabs use). Redrawn by drCalendarInit() after every swap. ── -->
<script>
(function () {
    var STATUSES = [
        { key: 'pending',  label: 'Pending',  color: '#f97316' },
        { key: 'verified', label: 'Verified', color: '#2563eb' },
        { key: 'resolved', label: 'Resolved', color: '#059669' },
        { key: 'rejected', label: 'Rejected', color: '#ef4444' }
    ];

    // Day picked -> show that day's cases from EVERY status (All tab); cleared -> back to the tab that was open.
    function go(dateStr) {
        var panel = document.getElementById('drPanel');
        var sel   = panel && panel.querySelector('select[name="brgy_filter"]');
        var tabEl = panel && panel.querySelector('input[name="tab"]');
        var brgy  = sel ? sel.value : '0';
        var p = new URLSearchParams();
        p.set('tab', dateStr ? 'all' : (tabEl && tabEl.value ? tabEl.value : 'all'));
        if (brgy && brgy !== '0') { p.set('brgy_filter', brgy); }
        if (dateStr) { p.set('date_from', dateStr); p.set('date_to', dateStr); }
        var url = window.location.pathname + '?' + p.toString();
        if (window.drLoadPanel) { window.drLoadPanel(url, true); } else { window.location.href = url; }
    }

    // Closed until the Calendar button is clicked. The choice is kept in memory only, so it survives
    // the panel being swapped (day click / filter change) but every fresh page load starts closed.
    function applyOpenState() {
        var open = !!window.__drCalOpen;
        var wrap = document.getElementById('drCalendarWrap');
        var btn  = document.getElementById('drCalToggle');
        if (wrap) { wrap.classList.toggle('hidden', !open); }
        if (btn)  { btn.classList.toggle('cc-toggle-on', open); }
    }

    window.drCalendarInit = function () {
        var mount  = document.getElementById('drCalendarMount');
        var dataEl = document.getElementById('drCalendarData');
        if (!mount || !dataEl || !window.CaseCalendar) { return; }
        var data;
        try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
        var cal = window.CaseCalendar.create({
            mount: mount, viewKey: 'disease', noun: 'case', statuses: STATUSES,
            onPick:  function (d) { go(d); },
            onClear: function ()  { go(null); }
        });
        cal.setData(data);
        applyOpenState();
    };

    window.drToggleCalendar = function () {
        var wrap = document.getElementById('drCalendarWrap');
        if (!wrap) { return; }
        window.__drCalOpen = wrap.classList.contains('hidden');   // opening?
        applyOpenState();
    };

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.drCalendarInit); }
    else { window.drCalendarInit(); }
})();
</script>

<?php include "includes/layout-end.php"; ?>