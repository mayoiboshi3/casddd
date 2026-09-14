<?php 
require_once __DIR__ . "/src/db_config.php"; 
$pageTitle = "Disease Reports"; 
include "includes/layout.php"; 

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
        $updateQuery = "UPDATE disease_cases SET status = '$new_status', remarks = '$remarks', updated_at = NOW() $severitySql WHERE reference_id = '$ref_esc'";
        if(mysqli_query($conn, $updateQuery)) {
            echo "<script>alert('Intelligence Update Saved!'); window.location='reports.php?tab=" . $new_status . "';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Invalid status change — cases can only move forward: Pending → Verified → Resolved.'); window.location='reports.php?tab=" . ($cur_status ?? 'pending') . "';</script>";
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

        echo "<script>window.location='reports.php?tab=pending&case_id=" . $case_id . "';</script>";
        exit;
    } else {
        echo "<script>alert('This can only be done for Pending, Other / Unidentified reports.'); window.location='reports.php?tab=pending&case_id=" . $case_id . "';</script>";
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
        echo "<script>window.location='reports.php?tab=" . $tab_return . "&case_id=" . $case_id . "&open_msg=1';</script>";
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
    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved FROM disease_cases");
$stats     = mysqli_fetch_assoc($stats_query);
$activeTab = $_GET['tab'] ?? 'pending';

// FROM DASHBOARD MAP
$autoOpenCaseId   = isset($_GET['case_id'])     ? (int)$_GET['case_id']     : 0;
$filterBarangayId = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;

// ── TAB FILTERS: date range + barangay dropdown (applies within each tab) ──
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to']   ?? '');
$filterBrgyDrop = isset($_GET['brgy_filter']) ? (int)$_GET['brgy_filter'] : 0;
if ($filterBrgyDrop) { $filterBarangayId = $filterBrgyDrop; }

$allBarangays_q = mysqli_query($conn, "SELECT id, name FROM barangays ORDER BY name ASC");

if ($filterBarangayId && !isset($_GET['tab'])) {
    $tab_q = mysqli_query($conn, "SELECT status FROM disease_cases WHERE barangay_id=$filterBarangayId ORDER BY FIELD(status,'pending','verified','resolved') LIMIT 1");
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
            f.profile_farmers AS farmer_photo
            FROM disease_cases dc
            LEFT JOIN barangays b  ON dc.barangay_id = b.id
            LEFT JOIN diseases d   ON dc.disease_id  = d.disease_id
            LEFT JOIN farmers f    ON f.farmer_name = SUBSTRING_INDEX(SUBSTRING(dc.description, LOCATE('[FARMER:', dc.description) + 8), ']', 1)
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
?>

<style>
    .modal-container { max-height: 92vh; display: flex; flex-direction: column; border-radius: 2.5rem; overflow: hidden; }
    .modal-scroll-area { overflow-y: auto; flex-grow: 1; padding: 2rem; scrollbar-width: thin; }
    .farmer-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }

    .stats-card { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .stats-card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 25px 30px -10px rgba(0,0,0,0.1); }

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

<!-- ═══ PAGE HEADER ═══ -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-3">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Monitoring Active
        </div>
        <h2 class="text-4xl font-black text-slate-900 tracking-tighter leading-none">
            Disease <span class="text-emerald-600">Report</span>
        </h2>
        <p class="text-slate-400 font-bold text-xs mt-2 uppercase tracking-tight"> </p>
        <?php if ($filterBarangayId): ?>
        <div class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-black uppercase tracking-wide">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
            Filtered by Barangay
            <?php $bn_q = mysqli_query($conn,"SELECT name FROM barangays WHERE id=$filterBarangayId LIMIT 1"); if($bn_q && $bn_r = mysqli_fetch_assoc($bn_q)) echo htmlspecialchars($bn_r['name']); ?>
            &nbsp;&mdash;&nbsp;<a href="reports.php" class="underline hover:text-amber-900">Clear filter</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="flex gap-3">
        <a href="dashboard.php" class="btn-intel bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            Back to Map
        </a>
        <button onclick="openCreateModal()" class="btn-intel bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg">
            + New Report
        </button>
    </div>
</div>

<?php render_new_case_report_modal($conn, $today, $minDate); ?>

<!-- ═══ STAT CARDS ═══ -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
    <?php 
    $cards = [
        ['Total Reports',        $stats['total'],    'bg-white',       'text-gray-800'],
        ['Pending Verification', $stats['pending'],  'bg-amber-500',   'text-white'],
        ['Verified Cases',       $stats['verified'], 'bg-blue-600',    'text-white'],
        ['Resolved / Treated',   $stats['resolved'], 'bg-emerald-600', 'text-white']
    ];
    foreach($cards as $c): ?>
    <div class="stats-card <?= $c[2] ?> p-7 rounded-[2rem] border border-black/5 shadow-sm">
        <p class="text-[9px] font-black uppercase tracking-widest <?= str_contains($c[2],'white') ? 'text-gray-400' : 'text-white/70' ?>"><?= $c[0] ?></p>
        <h4 class="text-4xl font-black mt-1 <?= $c[3] ?>"><?= $c[1] ?></h4>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ CASE FILE TABLE ═══ -->
<div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
        <h3 class="text-xl font-black text-gray-800 tracking-tight">Farmer Reports</h3>

        <!-- ── Tabs: always anchored top-right, never shifts between tabs ── -->
        <div class="flex bg-gray-100 p-1.5 rounded-2xl gap-1">
            <?php
            $tabConfig = [
                'pending'  => ['label' => 'Pending',  'active' => 'active-pending'],
                'verified' => ['label' => 'Verified', 'active' => 'active-verified'],
                'resolved' => ['label' => 'Resolved', 'active' => 'active-resolved'],
            ];
            $tabQueryExtra = '';
            if ($filterBarangayId) $tabQueryExtra .= '&brgy_filter=' . $filterBarangayId;
            if ($filterDateFrom)   $tabQueryExtra .= '&date_from=' . urlencode($filterDateFrom);
            if ($filterDateTo)     $tabQueryExtra .= '&date_to='   . urlencode($filterDateTo);

            foreach ($tabConfig as $tab => $cfg): 
                $isActive   = ($activeTab === $tab);
                $activeClass = $isActive ? ('tab-pill ' . $cfg['active']) : 'tab-pill inactive';
            ?>
                <a href="?tab=<?= $tab ?><?= $tabQueryExtra ?>" class="<?= $activeClass ?>">
                    <?= $cfg['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Second row: filters (left) + export (right), always its own row so it never pushes the tabs ── -->
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <form method="get" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

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
            <?php if ($filterBarangayId || $filterDateFrom || $filterDateTo): ?>
            <a href="?tab=<?= htmlspecialchars($activeTab) ?>"
                class="text-[10px] font-black text-gray-400 hover:text-gray-600 uppercase underline">
                Clear
            </a>
            <?php endif; ?>
        </form>

        <?php if ($activeTab === 'verified' || $activeTab === 'resolved'): ?>
        <button id="exportTabBtn" onclick="exportAllReports()"
            class="btn-intel bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm flex items-center gap-2">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z"/></svg>
            Export <?= ucfirst($activeTab) ?> Reports (PDF)
        </button>
        <?php endif; ?>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-50">
                    <th class="px-4 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Report No.</th>
                    <th class="px-4 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Farmer / Barangay</th>
                    <th class="px-4 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Date Reported</th>
                    <th class="px-4 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Barangay</th>
                    <th class="px-4 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
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

                $query = "SELECT dc.*, 
                            b.name AS brgy_name, 
                            d.disease_name,
                            d.description AS disease_description,
                            d.recommended_treatment,
                            d.prevention_measures,
                            f.profile_farmers AS farmer_photo
                          FROM disease_cases dc 
                          LEFT JOIN barangays b ON dc.barangay_id = b.id 
                          LEFT JOIN diseases d  ON dc.disease_id  = d.disease_id
                          LEFT JOIN farmers f   ON f.farmer_name = SUBSTRING_INDEX(SUBSTRING(dc.description, LOCATE('[FARMER:', dc.description) + 8), ']', 1)
                          WHERE dc.status = '$activeTab' $brgy_filter $date_filter 
                          ORDER BY dc.report_date DESC, dc.case_id ASC";
                $result = mysqli_query($conn, $query);

                // ── GROUP ROWS BY reference_id ──
                // A report with 2-3 diseases selected is stored as several disease_cases
                // rows sharing one reference_id. Group them here so the table (and every
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
                        ];
                        $tableRows[] = $modal_row;
                ?>
                <tr class="report-row">
                    <td class="px-4 py-5">
                        <span class="text-[10px] font-black text-emerald-600 tracking-tighter"><?= htmlspecialchars($row['reference_id']) ?></span><br>
                        <span class="font-black text-gray-800 text-sm uppercase"><?= htmlspecialchars($combined['name'] ?: '—') ?></span>
                        <?php if ($isInfectionDisease): ?>
                        <br><span class="sev-badge sev-low"><?= $row['infection_percentage'] !== null ? number_format((float)$row['infection_percentage'], 2) . '%' : 'No data on file' ?></span>
                        <?php elseif (!empty($row['severity'])): ?>
                        <br><span class="sev-badge <?= $sev_class ?>"><?= ucfirst($row['severity']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-5">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($row['farmer_photo'])): ?>
                            <img src="uploads/<?= htmlspecialchars($row['farmer_photo']) ?>"
                                 class="farmer-avatar"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="farmer-avatar bg-emerald-100 items-center justify-center text-emerald-600 font-black text-sm rounded-xl" style="display:none">
                                <?= strtoupper(substr($farmer_display, 0, 1)) ?>
                            </div>
                            <?php else: ?>
                            <div class="farmer-avatar bg-emerald-100 flex items-center justify-center text-emerald-600 font-black text-sm rounded-xl">
                                <?= strtoupper(substr($farmer_display, 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="font-black text-gray-800 text-sm block leading-none"><?= htmlspecialchars($farmer_display) ?></span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase"><?= htmlspecialchars($row['brgy_name'] ?? '—') ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-5">
                        <span class="text-[11px] font-black text-gray-700"><?= htmlspecialchars(date('M d, Y', strtotime($row['report_date']))) ?></span><br>
                        <span class="text-[9px] font-bold text-gray-400 uppercase"><?= htmlspecialchars(date('g:i A', strtotime($row['report_date']))) ?></span>
                    </td>
                    <td class="px-4 py-5">
                        <span class="text-[11px] font-black text-gray-700 uppercase"><?= htmlspecialchars($row['brgy_name'] ?? '— Unassigned —') ?></span>
                    </td>
                    <td class="px-4 py-5 text-center">
                        <button onclick='openViewModal(<?= htmlspecialchars(json_encode($modal_row), ENT_QUOTES, 'UTF-8') ?>)' class="btn-intel bg-gray-900 text-white px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest">View Report</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" class="p-10 text-center text-gray-400 font-bold uppercase text-xs">No reports found for this barangay.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_planting_harvesting_section($conn); ?>

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
// All reports currently loaded for the active tab (used for bulk PDF export)
const currentTabReports = <?= json_encode($tableRows) ?>;

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

<?php include "includes/layout-end.php"; ?>