<?php
// ════════════════════════════════════════════════════════════════════════
// PLANTING & HARVESTING REPORTS
// Shows the planting/harvesting reports farmers submit (planting_harvesting_reports
// table, sent in from the farmer-side app) as their own section on this page,
// with a "Review Report" popup like the Disease Report table above, PLUS a
// "+ Add Report" button so staff can log a planting/harvesting report by hand.
// Kept in its own file (same pattern as new_case_report.php / review.php) so
// it can be dropped into reports.php with one require_once + one function call.
// ════════════════════════════════════════════════════════════════════════

// --- SELF-HEALING SCHEMA: 'source' column on planting_harvesting_reports ---
// Lets us tell farmer-submitted reports apart from ones a staff member typed
// in manually via the "+ Add Report" button below.
$ph_col_check = mysqli_query($conn, "SHOW COLUMNS FROM planting_harvesting_reports LIKE 'source'");
if ($ph_col_check && mysqli_num_rows($ph_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE planting_harvesting_reports ADD COLUMN source ENUM('farmer','staff') NOT NULL DEFAULT 'farmer' AFTER report_type");
}

// --- SELF-HEALING SCHEMA: 'growth' & 'damage' field reports ---
// The farmer app now also submits Growth and Damage reports (free-text
// description + photo + GPS fix) alongside Planting/Harvesting. This brings
// the table up to date with that shape WITHOUT touching the existing
// planting/harvesting-only columns (source, planting_stage, source_report_id,
// the 'cassava' crop option, etc.) — the two report families just share one
// table, same as before.
$ph_type_check = mysqli_query($conn, "SHOW COLUMNS FROM planting_harvesting_reports LIKE 'report_type'");
$ph_type_row   = $ph_type_check ? mysqli_fetch_assoc($ph_type_check) : null;
if ($ph_type_row && strpos($ph_type_row['Type'], "'damage'") === false) {
    mysqli_query($conn, "ALTER TABLE planting_harvesting_reports
        MODIFY report_type ENUM('planting','harvesting','damage','growth') NOT NULL");
}
// Growth/Damage reports don't have a crop or an area — both columns need to
// become nullable (they were NOT NULL, planting/harvesting-only, before).
$ph_crop_check = mysqli_query($conn, "SHOW COLUMNS FROM planting_harvesting_reports LIKE 'crop_type'");
$ph_crop_row   = $ph_crop_check ? mysqli_fetch_assoc($ph_crop_check) : null;
if ($ph_crop_row && strtoupper($ph_crop_row['Null']) === 'NO') {
    mysqli_query($conn, "ALTER TABLE planting_harvesting_reports
        MODIFY crop_type ENUM('yellow_corn','white_corn','cassava') DEFAULT NULL");
}
$ph_area_check = mysqli_query($conn, "SHOW COLUMNS FROM planting_harvesting_reports LIKE 'area_hectares'");
$ph_area_row   = $ph_area_check ? mysqli_fetch_assoc($ph_area_check) : null;
if ($ph_area_row && strtoupper($ph_area_row['Null']) === 'NO') {
    mysqli_query($conn, "ALTER TABLE planting_harvesting_reports
        MODIFY area_hectares DECIMAL(10,2) DEFAULT NULL");
}
// New columns Growth/Damage reports need, carried over from the farmer app's
// side of this table.
$ph_new_columns = [
    'description'  => "TEXT DEFAULT NULL AFTER crop_type",
    'photo'        => "VARCHAR(255) DEFAULT NULL AFTER description",
    'latitude'     => "DECIMAL(10,7) DEFAULT NULL AFTER photo",
    'longitude'    => "DECIMAL(10,7) DEFAULT NULL AFTER latitude",
    'gps_accuracy' => "DECIMAL(10,2) DEFAULT NULL AFTER longitude",
    'altitude'     => "DECIMAL(10,2) DEFAULT NULL AFTER gps_accuracy",
];
foreach ($ph_new_columns as $ph_col_name => $ph_col_def) {
    $ph_chk = mysqli_query($conn, "SHOW COLUMNS FROM planting_harvesting_reports LIKE '$ph_col_name'");
    if ($ph_chk && mysqli_num_rows($ph_chk) === 0) {
        mysqli_query($conn, "ALTER TABLE planting_harvesting_reports ADD COLUMN $ph_col_name $ph_col_def");
    }
}

// One-way status flow, same idea as $STATUS_FLOW above for disease_cases —
// once verified, a report is locked. Rejecting a pending report deletes it
// outright instead of moving it into a "rejected" state, so the only
// statuses that actually persist are Pending and Verified.
$PH_STATUS_FLOW = [
    'pending'  => ['pending', 'verified', 'rejected'],
    'verified' => ['verified'],
];

// 1. HANDLE "+ ADD REPORT" (manual, staff-entered)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_ph_report'])) {
    $farmer_id      = (int)($_POST['ph_farmer_id'] ?? 0);
    $report_type    = mysqli_real_escape_string($conn, $_POST['ph_report_type'] ?? 'planting');
    $crop_type      = mysqli_real_escape_string($conn, $_POST['ph_crop_type'] ?? 'yellow_corn');
    $variety        = mysqli_real_escape_string($conn, trim($_POST['ph_variety'] ?? ''));
    $planting_stage = mysqli_real_escape_string($conn, trim($_POST['ph_planting_stage'] ?? ''));
    $area_hectares  = (float)($_POST['ph_area'] ?? 0);
    $description    = mysqli_real_escape_string($conn, trim($_POST['ph_description'] ?? ''));
    $latitude_raw   = trim($_POST['ph_latitude'] ?? '');
    $longitude_raw  = trim($_POST['ph_longitude'] ?? '');
    $remarks        = mysqli_real_escape_string($conn, trim($_POST['ph_remarks'] ?? ''));

    // Manually-added reports are NOT verified automatically — they go in as
    // Pending, same as a farmer submission, so staff still has a chance to
    // Verify or Reject it from the report list (Reject deletes the record).
    $status = '';

    $validReportTypes = ['planting', 'harvesting', 'damage'];
    $validCropTypes   = ['yellow_corn', 'white_corn', 'cassava'];
    if (!in_array($report_type, $validReportTypes, true)) $report_type = 'planting';
    if (!in_array($crop_type, $validCropTypes, true))     $crop_type   = 'yellow_corn';

    $isDamage = $report_type === 'damage';

    // Damage reports don't carry a crop/variety/stage/area — they're the
    // photo + description + GPS style report, same shape as the farmer app's
    // Growth/Damage submissions.
    $canSave = $isDamage
        ? ($farmer_id > 0 && $description !== '')
        : ($farmer_id > 0 && $area_hectares > 0);

    if ($canSave) {
        $reference_id = ($isDamage ? 'DR-' : 'PH-') . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Optional photo upload for Damage reports — same "uploads/<name>"
        // convention the farmer app already uses for photo evidence.
        $photo_db_value = 'NULL';
        if ($isDamage && isset($_FILES['ph_photo']) && $_FILES['ph_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['ph_photo']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowedExt, true)) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                $fileName = 'report_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($_FILES['ph_photo']['tmp_name'], $uploadDir . $fileName)) {
                    $photo_db_value = "'" . mysqli_real_escape_string($conn, $fileName) . "'";
                }
            }
        }
        $lat_db_value = is_numeric($latitude_raw)  ? (float)$latitude_raw  : 'NULL';
        $lng_db_value = is_numeric($longitude_raw) ? (float)$longitude_raw : 'NULL';

        if ($isDamage) {
            $insertQuery = "INSERT INTO planting_harvesting_reports
                (reference_id, farmer_id, report_type, source, description, photo, latitude, longitude,
                 privacy_consent, status, remarks, submitted_at, verified_at, created_at)
                VALUES ('$reference_id', $farmer_id, 'damage', 'staff', '$description', $photo_db_value, $lat_db_value, $lng_db_value,
                 1, '$status', '$remarks', NOW(), NULL, NOW())";
        } else {
            $insertQuery = "INSERT INTO planting_harvesting_reports
                (reference_id, farmer_id, report_type, source, crop_type, variety, planting_stage,
                 area_hectares, privacy_consent, status, remarks, submitted_at, verified_at, created_at)
                VALUES ('$reference_id', $farmer_id, '$report_type', 'staff', '$crop_type', '$variety', '$planting_stage',
                 $area_hectares, 1, '$status', '$remarks', NOW(), NULL, NOW())";
        }

        if (mysqli_query($conn, $insertQuery)) {
            $new_report_id = mysqli_insert_id($conn);
            // Redirect back with the new report's id so the section can show a
            // "View Report" button for it (instead of just an alert).
            echo "<script>window.location='reports.php?ph_new=" . $new_report_id . "#ph-section';</script>";
            exit;
        } else {
            echo "<script>alert('Could not save the report. Please try again.'); window.location='reports.php#ph-section';</script>";
            exit;
        }
    } else {
        $errMsg = $isDamage
            ? 'Please pick a farmer and describe the damage observed.'
            : 'Please pick a farmer and enter a valid area (in hectares).';
        echo "<script>alert('$errMsg'); window.location='reports.php#ph-section';</script>";
        exit;
    }
}

// 2. HANDLE STATUS UPDATE from the "Review Report" popup (Pending -> Verified, or delete on Reject)
// Rewritten to be a real, checked state machine instead of trusting the
// request: we re-check the row's live status right before acting (so two
// people reviewing the same report at once can't both "succeed"), we check
// every query's actual result before reporting success, and -- when the
// request comes from the Review modal's JS (fetch/AJAX) -- we answer with
// JSON so the page can update instantly instead of a full reload. A normal
// (non-JS) form POST still falls back to the old redirect behavior, so this
// keeps working — for any report type, farmer- or staff-submitted alike —
// even if JavaScript is unavailable.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ph_report'])) {
    $is_ajax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    $ph_report_id = (int)($_POST['ph_report_id_hidden'] ?? 0);
    $new_status   = mysqli_real_escape_string($conn, $_POST['ph_new_status'] ?? '');
    $remarks      = mysqli_real_escape_string($conn, trim($_POST['ph_remarks_update'] ?? ''));

    // 'gone' tells the front-end the report is no longer there for ANY reason
    // (already rejected/deleted, already verified by someone else, bad id) so
    // it can drop the row locally instead of getting stuck on a stale one.
    $result = ['success' => false, 'gone' => false, 'report_id' => $ph_report_id, 'message' => 'Something went wrong. Please try again.'];

    if ($ph_report_id <= 0) {
        $result['message'] = 'Invalid report.';
    } else {
        // Re-read the CURRENT status fresh from the DB right now -- never trust
        // whatever status the modal happened to be showing when it was opened.
        $cur_q   = mysqli_query($conn, "SELECT status FROM planting_harvesting_reports WHERE report_id = $ph_report_id LIMIT 1");
        $cur_row = $cur_q ? mysqli_fetch_assoc($cur_q) : null;

        if (!$cur_row) {
            $result['gone']    = true;
            $result['message'] = 'This report no longer exists — it may have already been reviewed.';
        } else {
            $raw_status  = $cur_row['status'] ?? '';
            $cur_status  = ($raw_status === '' || $raw_status === 'received') ? 'pending' : $raw_status;
            $allowedNext = $PH_STATUS_FLOW[$cur_status] ?? [];

            if (!in_array($new_status, $allowedNext, true)) {
                $result['message'] = ($cur_status === 'verified')
                    ? 'This report is already finalized and can no longer be changed.'
                    : 'A pending report can only be Verified, or Rejected (which removes it).';
                // Already verified/locked reports are still "present" -- don't
                // tell the front-end to remove the row, just refuse the change.
                $result['gone'] = ($cur_status !== 'pending' && $cur_status !== 'verified');
            } elseif ($new_status === 'rejected') {
                // Rejected reports are not kept — the record is deleted outright
                // rather than saved with a "rejected" status. We only call this a
                // success once the DELETE actually removed a row.
                $del_ok = mysqli_query($conn, "DELETE FROM planting_harvesting_reports WHERE report_id = $ph_report_id LIMIT 1");
                if ($del_ok && mysqli_affected_rows($conn) > 0) {
                    $result = ['success' => true, 'deleted' => true, 'gone' => true, 'report_id' => $ph_report_id,
                               'message' => 'Report rejected and removed.'];
                } else {
                    $result['gone']    = true;
                    $result['message'] = 'Could not reject this report — it may have already been removed. Error: ' . mysqli_error($conn);
                }
            } else {
                $verifiedSql = ($new_status === 'verified') ? ", verified_at = NOW()" : "";
                $upd_ok = mysqli_query($conn, "UPDATE planting_harvesting_reports SET status = '$new_status', remarks = '$remarks', updated_at = NOW() $verifiedSql WHERE report_id = $ph_report_id");
                if ($upd_ok) {
                    $result = ['success' => true, 'deleted' => false, 'gone' => false, 'report_id' => $ph_report_id,
                               'new_status' => $new_status, 'remarks' => $remarks,
                               'message' => $new_status === 'verified' ? 'Report verified.' : 'Report updated.'];
                } else {
                    $result['message'] = 'Could not update this report. Error: ' . mysqli_error($conn);
                }
            }
        }
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // Non-JS fallback: same old-school redirect behavior as before.
    if ($result['success']) {
        echo "<script>window.location='reports.php#ph-section';</script>";
    } else {
        $safeMsg = addslashes($result['message']);
        echo "<script>alert('$safeMsg'); window.location='reports.php#ph-section';</script>";
    }
    exit;
}

// Helpers: display labels/badges for crop type + report type
function ph_crop_label($crop) {
    if ($crop === null || $crop === '') return '—';
    return match ($crop) {
        'yellow_corn' => 'Yellow Corn',
        'white_corn'  => 'White Corn',
        'cassava'     => 'Cassava',
        default       => ucfirst(str_replace('_', ' ', $crop)),
    };
}
// True for the photo/description/GPS style reports (Growth, Damage), as
// opposed to the crop/area/variety style reports (Planting, Harvesting).
function ph_is_field_report($type) {
    return $type === 'growth' || $type === 'damage';
}
function ph_type_label($type) {
    return match ($type) {
        'harvesting' => 'Harvesting',
        'damage'     => 'Damage',
        'growth'     => 'Growth',
        default      => 'Planting',
    };
}
function ph_type_badge_class($type) {
    return match ($type) {
        'harvesting' => 'bg-orange-100 text-orange-700',
        'damage'     => 'bg-rose-100 text-rose-700',
        'growth'     => 'bg-teal-100 text-teal-700',
        default      => 'bg-sky-100 text-sky-700', // planting
    };
}
function ph_status_pill_class($status) {
    return match ($status) {
        'verified' => 'bg-blue-100 text-blue-700',
        default    => 'bg-purple-100 text-purple-700', // pending / received / ''
    };
}
function ph_status_label($status) {
    return ($status === '' || $status === null || $status === 'received') ? 'Pending' : ucfirst($status);
}

// ────────────────────────────────────────────────────────────────────────
// Renders the whole "Planting & Harvesting Reports" section: stat cards,
// status tabs, the reports table, the "Review Report" modal and the
// "+ Add Report" modal. Called once from reports.php, right below the
// Disease Report case-file table.
// ────────────────────────────────────────────────────────────────────────
function render_planting_harvesting_section($conn) {
    global $PH_STATUS_FLOW;

    // Stats — an empty-string (or legacy "received") status in the DB counts
    // as "pending". Rejected reports are deleted on rejection, so any legacy
    // rejected rows left over from before that change are excluded here.
    $ph_stats_q = mysqli_query($conn, "SELECT COUNT(*) AS total,
        COUNT(CASE WHEN status = 'received' OR status = '' OR status IS NULL THEN 1 END) AS pending,
        COUNT(CASE WHEN status = 'verified' THEN 1 END) AS verified
        FROM planting_harvesting_reports WHERE status != 'rejected' OR status IS NULL");
    $ph_stats = mysqli_fetch_assoc($ph_stats_q) ?: ['total' => 0, 'pending' => 0, 'verified' => 0];

    // If we just redirected back from "+ Add Report", pull that one report's
    // full data so we can show a "View Report" button for it right away.
    $ph_new_report = null;
    if (!empty($_GET['ph_new'])) {
        $ph_new_id = (int)$_GET['ph_new'];
        $pn_q = mysqli_query($conn, "SELECT phr.*, f.farmer_name, f.profile_farmers AS farmer_photo, b.name AS brgy_name
                     FROM planting_harvesting_reports phr
                     LEFT JOIN farmers f    ON phr.farmer_id  = f.farmer_id
                     LEFT JOIN barangays b  ON f.barangay_id  = b.id
                     WHERE phr.report_id = $ph_new_id LIMIT 1");
        if ($pn_q && $pn_row = mysqli_fetch_assoc($pn_q)) {
            $ph_new_report = [
                'report_id'       => (int)$pn_row['report_id'],
                'reference_id'    => $pn_row['reference_id'],
                'farmer_name'     => $pn_row['farmer_name'] ?: '— Unassigned —',
                'farmer_photo'    => $pn_row['farmer_photo'],
                'brgy_name'       => $pn_row['brgy_name'] ?? '—',
                'report_type'     => $pn_row['report_type'],
                'source'          => $pn_row['source'],
                'crop_type'       => $pn_row['crop_type'],
                'variety'         => $pn_row['variety'],
                'planting_stage'  => $pn_row['planting_stage'],
                'area_hectares'   => $pn_row['area_hectares'],
                'description'     => $pn_row['description'] ?? null,
                'photo'           => $pn_row['photo'] ?? null,
                'latitude'        => $pn_row['latitude'] ?? null,
                'longitude'       => $pn_row['longitude'] ?? null,
                'gps_accuracy'    => $pn_row['gps_accuracy'] ?? null,
                'altitude'        => $pn_row['altitude'] ?? null,
                'privacy_consent' => (int)$pn_row['privacy_consent'],
                'status'          => (in_array($pn_row['status'], ['', 'received'], true) || $pn_row['status'] === null) ? 'pending' : $pn_row['status'],
                'remarks'         => $pn_row['remarks'],
                'submitted_at'    => $pn_row['submitted_at'],
                'verified_at'     => $pn_row['verified_at'],
            ];
        }
    }

    // Farmers, for the "+ Add Report" farmer picker
    $ph_farmers = [];
    $ph_farmers_q = mysqli_query($conn, "SELECT f.farmer_id, f.farmer_name, b.name AS brgy_name
                                          FROM farmers f LEFT JOIN barangays b ON f.barangay_id = b.id
                                          ORDER BY f.farmer_name ASC");
    if ($ph_farmers_q) { while ($f = mysqli_fetch_assoc($ph_farmers_q)) { $ph_farmers[] = $f; } }

    // Full list of every non-rejected report — feeds the single "Planting &
    // Harvesting" popup. Rejected reports are deleted on rejection, so this
    // effectively only ever surfaces Pending and Verified reports (any
    // legacy rejected rows from before that change are filtered out here).
    $ph_all_rows = [];
    $ph_all_q = mysqli_query($conn, "SELECT phr.*, f.farmer_name, f.profile_farmers AS farmer_photo, b.name AS brgy_name
                 FROM planting_harvesting_reports phr
                 LEFT JOIN farmers f    ON phr.farmer_id  = f.farmer_id
                 LEFT JOIN barangays b  ON f.barangay_id  = b.id
                 WHERE phr.status != 'rejected' OR phr.status IS NULL
                 ORDER BY phr.submitted_at DESC, phr.report_id DESC");
    if ($ph_all_q) {
        while ($ar = mysqli_fetch_assoc($ph_all_q)) {
            $ph_all_rows[] = [
                'report_id'      => (int)$ar['report_id'],
                'reference_id'   => $ar['reference_id'],
                'farmer_name'    => $ar['farmer_name'] ?: '— Unassigned —',
                'farmer_photo'   => $ar['farmer_photo'],
                'brgy_name'      => $ar['brgy_name'] ?? '—',
                'report_type'    => $ar['report_type'],
                'source'         => $ar['source'],
                'crop_type'      => $ar['crop_type'],
                'variety'        => $ar['variety'],
                'planting_stage' => $ar['planting_stage'],
                'area_hectares'  => $ar['area_hectares'],
                'description'    => $ar['description'] ?? null,
                'photo'          => $ar['photo'] ?? null,
                'latitude'       => $ar['latitude'] ?? null,
                'longitude'      => $ar['longitude'] ?? null,
                'gps_accuracy'   => $ar['gps_accuracy'] ?? null,
                'altitude'       => $ar['altitude'] ?? null,
                'privacy_consent'=> (int)$ar['privacy_consent'],
                'status'         => (in_array($ar['status'], ['', 'received'], true) || $ar['status'] === null) ? 'pending' : $ar['status'],
                'remarks'        => $ar['remarks'],
                'submitted_at'   => $ar['submitted_at'],
                'verified_at'    => $ar['verified_at'],
            ];
        }
    }
    ?>

    <!-- ═══ PLANTING & HARVESTING REPORTS ═══
         Shown directly on the page (no "View Reports" click required) — the
         farm activity list, tabs, search and filters all render inline as
         soon as the page loads. -->
    <div id="ph-section" class="mt-10">
    <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl">
        <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="8" y="2" width="8" height="4" rx="1"></rect>
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <path d="M9 12h6"></path>
                        <path d="M9 16h6"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 tracking-tight">Farm Reports</h3>
                    <p class="text-gray-400 font-medium text-[11px] mt-0.5 tracking-tight">Planting, harvesting, and crop damage reports from farmers and staff</p>
                </div>
            </div>
            <button onclick="openPHAddModal()" type="button" class="btn-intel bg-gray-900 text-white px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shrink-0">
                + Add Report
            </button>
        </div>

        <div id="phTypeTabsWrap">
            <div class="ph-tabs" id="phTypeTabs"></div>
        </div>

        <div class="pt-5 flex items-center gap-2.5 flex-wrap">
            <div class="relative flex-1 min-w-[180px]">
                <svg class="w-4 h-4 text-gray-350 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                <input type="text" id="phSearchInput" placeholder="Search by farmer, report number, or crop..."
                       class="w-full pl-9 pr-3 py-2.5 text-xs font-medium rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-300"
                       oninput="renderPHRows()">
            </div>
            <input type="date" id="phDateInput"
                   class="px-3 py-2.5 text-xs font-medium rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-300"
                   onchange="renderPHRows()">
            <button type="button" id="phClearFiltersBtn" onclick="clearPHFilters()"
                    class="hidden text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-gray-700 px-2">
                Clear
            </button>
        </div>

        <div class="pt-3 pb-1 flex items-center gap-2 flex-wrap" id="phFilterChips"></div>

        <div id="phAllReportsBody" class="divide-y divide-gray-100 mt-2"></div>
    </div>
    </div>

    <!-- ═══ SAVE SUCCESS MODAL ═══ -->
    <div id="phSuccessModal" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-3 sm:p-4">
        <div class="modal-container bg-white w-full max-w-xs sm:max-w-sm">
            <div class="p-6 sm:p-8 text-center">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center mx-auto mb-4 sm:mb-5">
                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 mb-1">Report Saved</h3>
                <p class="text-xs sm:text-sm text-gray-500 mb-6 break-words">
                    Report <span class="font-bold text-gray-800" id="phSuccessRef"></span> was saved successfully.
                </p>
                <button onclick="closePHModal('phSuccessModal')" class="btn-intel w-full bg-gray-900 text-white py-3 rounded-xl text-[11px] font-bold uppercase tracking-widest">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ REVIEW REPORT MODAL ═══ -->
    <div id="phViewModal" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-4">
        <div class="modal-container bg-white w-full max-w-lg">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div id="phv_avatar" class="farmer-avatar bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600 font-bold text-sm rounded-xl shrink-0"></div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest" id="phv_reference"></p>
                        <h3 class="text-base font-bold text-gray-900 truncate" id="phv_farmer"></h3>
                        <p class="text-xs font-medium text-gray-400" id="phv_brgy"></p>
                    </div>
                </div>
                <button onclick="closePHModal('phViewModal')" class="text-gray-400 hover:text-gray-700 text-xl leading-none w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-50 shrink-0">&times;</button>
            </div>
            <div class="modal-scroll-area">
                <div class="flex items-center justify-between mb-6 pb-5 border-b border-gray-100">
                    <div>
                        <span class="field-label">Crop</span>
                        <p class="font-bold text-base text-gray-900" id="phv_crop"></p>
                    </div>
                    <span class="sev-badge" id="phv_status_pill"></span>
                </div>

                <div id="phv_ph_fields" class="grid grid-cols-2 gap-x-6 gap-y-4 mb-6">
                    <div>
                        <span class="field-label">Type of Report</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_type"></p>
                    </div>
                    <div>
                        <span class="field-label">Farm Area (hectares)</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_area"></p>
                    </div>
                    <div>
                        <span class="field-label">Variety</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_variety"></p>
                    </div>
                    <div>
                        <span class="field-label">Growth Stage</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_stage"></p>
                    </div>
                    <div>
                        <span class="field-label">Date Submitted</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_submitted"></p>
                    </div>
                </div>

                <!-- ═ Growth / Damage field report fields (photo + location + notes) ═ -->
                <div id="phv_field_fields" class="hidden mb-6">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-4 mb-4">
                        <div>
                            <span class="field-label">Type of Report</span>
                            <p class="font-semibold text-sm text-gray-800" id="phv_field_type"></p>
                        </div>
                        <div>
                            <span class="field-label">Date Submitted</span>
                            <p class="font-semibold text-sm text-gray-800" id="phv_field_submitted"></p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <span class="field-label">Farmer's Notes</span>
                        <p class="font-semibold text-sm text-gray-800" id="phv_description"></p>
                    </div>
                    <div id="phv_photo_wrap" class="mb-4 hidden">
                        <span class="field-label">Photo</span>
                        <img id="phv_photo" class="w-full max-h-64 object-cover rounded-xl border border-gray-100 mt-1" onerror="this.parentElement.classList.add('hidden')">
                    </div>
                    <div id="phv_gps_wrap" class="hidden">
                        <span class="field-label">Location</span>
                        <p class="font-semibold text-sm text-gray-800">
                            <a id="phv_gps_link" href="#" target="_blank" class="text-emerald-600 hover:underline"></a>
                            <span id="phv_gps_accuracy" class="text-xs text-gray-400 font-medium"></span>
                        </p>
                    </div>
                </div>

                <form method="POST" id="phv_form">
                    <input type="hidden" name="update_ph_report" value="1">
                    <input type="hidden" name="ph_report_id_hidden" id="phv_report_id">

                    <span class="field-label">Remarks</span>
                    <textarea name="ph_remarks_update" id="phv_remarks" rows="3" class="field-input mb-4" placeholder="Notes for this report..."></textarea>

                    <p id="phv_error" class="hidden text-center text-[10px] font-bold text-rose-600 mb-2"></p>

                    <div id="phv_action_row" class="flex gap-3">
                        <button type="submit" name="ph_new_status" value="verified" id="phv_verify_btn"
                            class="btn-intel flex-1 bg-blue-600 text-white py-3 rounded-xl text-[11px] font-bold uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed">
                            Accept Report
                        </button>
                        <button type="submit" name="ph_new_status" value="rejected" id="phv_reject_btn"
                            class="btn-intel flex-1 bg-rose-600 text-white py-3 rounded-xl text-[11px] font-bold uppercase tracking-widest disabled:opacity-50 disabled:cursor-not-allowed">
                            Reject
                        </button>
                    </div>
                    <p class="text-center text-[9px] font-semibold text-gray-400 mt-2">Rejecting a report deletes it permanently — it will not be saved.</p>
                    <p id="phv_locked_note" class="hidden text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-2 flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        This report is locked — status already finalized
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ ADD REPORT MODAL (manual, staff-entered) ═══ -->
    <div id="phAddModal" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-4">
        <div class="modal-container bg-white w-full max-w-lg">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-black text-gray-800">+ Log a New Report</h3>
                <button onclick="closePHModal('phAddModal')" class="text-gray-400 hover:text-gray-700 font-black text-xl">&times;</button>
            </div>
            <div class="modal-scroll-area">
                <form method="POST" enctype="multipart/form-data" id="phAddForm">
                    <input type="hidden" name="add_ph_report" value="1">

                    <span class="field-label">Farmer</span>
                    <select name="ph_farmer_id" class="field-input mb-4" required>
                        <option value="">Select a farmer...</option>
                        <?php foreach ($ph_farmers as $f): ?>
                        <option value="<?= (int)$f['farmer_id'] ?>"><?= htmlspecialchars($f['farmer_name']) ?> — <?= htmlspecialchars($f['brgy_name'] ?? '—') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <span class="field-label">What kind of report is this?</span>
                    <input type="hidden" name="ph_report_type" id="phAddTypeInput" value="planting">
                    <div class="grid grid-cols-3 gap-3 mb-5" id="phAddTypeToggle">
                        <button type="button" class="ph-type-btn ph-type-btn-active" data-type="planting" onclick="phSelectAddType('planting', this)">
                            Planting
                        </button>
                        <button type="button" class="ph-type-btn" data-type="harvesting" onclick="phSelectAddType('harvesting', this)">
                            Harvesting
                        </button>
                        <button type="button" class="ph-type-btn" data-type="damage" onclick="phSelectAddType('damage', this)">
                            Damage
                        </button>
                    </div>

                    <!-- ═ Planting / Harvesting fields ═ -->
                    <div id="phAddCropFields" class="ph-add-section mb-5">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <span class="field-label">Crop</span>
                                <select name="ph_crop_type" class="field-input">
                                    <option value="yellow_corn">Yellow Corn</option>
                                    <option value="white_corn">White Corn</option>
                                    <option value="cassava">Cassava</option>
                                </select>
                            </div>
                            <div>
                                <span class="field-label">Variety</span>
                                <input type="text" name="ph_variety" class="field-input" placeholder="e.g. IPB Var 6">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="field-label">Growth Stage</span>
                                <input type="text" name="ph_planting_stage" class="field-input" placeholder="e.g. V6, Mature">
                            </div>
                            <div>
                                <span class="field-label">Farm Area (hectares)</span>
                                <div class="ph-area-stepper">
                                    <button type="button" class="ph-area-step-btn" aria-label="Decrease area" onclick="phAdjustArea(-0.25)">−</button>
                                    <input type="number" step="0.01" min="0.01" name="ph_area" id="phAddAreaInput" class="ph-area-input"
                                           placeholder="0.00" inputmode="decimal" oninput="phSyncAreaPresets()">
                                    <button type="button" class="ph-area-step-btn" aria-label="Increase area" onclick="phAdjustArea(0.25)">+</button>
                                </div>
                                <div class="ph-area-presets">
                                    <button type="button" class="ph-area-preset-btn" data-ph-area="0.25" onclick="phSetArea(0.25)">0.25</button>
                                    <button type="button" class="ph-area-preset-btn" data-ph-area="0.5" onclick="phSetArea(0.5)">0.5</button>
                                    <button type="button" class="ph-area-preset-btn" data-ph-area="1" onclick="phSetArea(1)">1</button>
                                    <button type="button" class="ph-area-preset-btn" data-ph-area="2" onclick="phSetArea(2)">2</button>
                                    <button type="button" class="ph-area-preset-btn" data-ph-area="5" onclick="phSetArea(5)">5</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═ Damage report fields ═ -->
                    <div id="phAddDamageFields" class="ph-add-section mb-5 hidden">
                        <div class="mb-4">
                            <span class="field-label">What damage was observed?</span>
                            <textarea name="ph_description" id="phAddDescriptionInput" rows="3" class="field-input" placeholder="e.g. Fall armyworm infestation on lower leaves, roughly 2 rows affected..."></textarea>
                        </div>
                        <div class="mb-4">
                            <span class="field-label">Photo (optional)</span>
                            <input type="file" name="ph_photo" id="phAddDamagePhotoInput" accept="image/png,image/jpeg,image/webp"
                                   class="field-input" onchange="phHandleDamagePhotoSelect(event)">
                            <div id="phAddDamagePhotoPreviewWrap" class="hidden mt-2.5">
                                <div class="ph-damage-photo-preview">
                                    <img id="phAddDamagePhotoPreviewImg" src="" alt="Selected photo">
                                    <button type="button" onclick="phRemoveDamagePhoto()" aria-label="Remove photo">&times;</button>
                                </div>
                            </div>
                        </div>
                        <div class="mb-1">
                            <span class="field-label">Location (optional)</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <input type="text" name="ph_latitude" class="field-input" placeholder="Latitude, e.g. 14.1894">
                            </div>
                            <div>
                                <input type="text" name="ph_longitude" class="field-input" placeholder="Longitude, e.g. 121.1670">
                            </div>
                        </div>
                    </div>

                    <p class="text-[10px] font-semibold text-gray-400 -mt-2 mb-4">New reports are saved as Pending — Verify or Reject it from the report list.</p>

                    <span class="field-label">Remarks</span>
                    <textarea name="ph_remarks" rows="3" class="field-input mb-5" placeholder="Optional notes..."></textarea>

                    <button type="submit" class="btn-intel w-full bg-gray-900 text-white py-3.5 rounded-xl text-[11px] font-black uppercase tracking-widest">
                        Save Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .ph-chip {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            background: #fff;
            transition: all .15s ease;
        }
        .ph-chip:hover { border-color: #d1d5db; color: #374151; }
        .ph-chip-active {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }
        .ph-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 24px;
            cursor: pointer;
            transition: background .15s ease;
        }
        .ph-row:hover { background: #fafafa; }
        .ph-row-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            flex-shrink: 0;
        }
        .ph-empty {
            text-align: center;
            padding: 56px 24px;
            color: #9ca3af;
        }
        .ph-type-chip {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 8px;
            border-radius: 999px;
        }
        .ph-type-btn {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 12px 8px;
            min-height: 44px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: all .15s ease;
        }
        .ph-type-btn:hover { border-color: #d1d5db; color: #374151; }
        .ph-type-btn-active {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }
        .ph-add-section {
            border: 1.5px solid #eef0f2;
            background: #fafafa;
            border-radius: 16px;
            padding: 18px;
        }
        /* Farm Area — tap-friendly stepper instead of a bare number box, since
           typing small decimals (0.03, 0.25 ha) on a phone keyboard is fiddly. */
        .ph-area-stepper {
            display: flex;
            align-items: stretch;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .ph-area-step-btn {
            width: 38px;
            flex-shrink: 0;
            background: #f9fafb;
            border: none;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            color: #374151;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s ease, color .15s ease;
            user-select: none;
        }
        .ph-area-step-btn:hover { background: #f0fdf4; color: #059669; }
        .ph-area-step-btn:active { background: #dcfce7; }
        .ph-area-input {
            flex: 1;
            min-width: 0;
            border: none;
            border-left: 1.5px solid #e5e7eb;
            border-right: 1.5px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            padding: 0 4px;
            background: transparent;
        }
        .ph-area-input:focus { outline: none; background: #f9fafb; }
        .ph-area-input::-webkit-outer-spin-button,
        .ph-area-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .ph-area-input[type=number] { -moz-appearance: textfield; }
        .ph-area-presets {
            display: flex;
            gap: 5px;
            margin-top: 7px;
            flex-wrap: wrap;
        }
        .ph-area-preset-btn {
            font-size: 9.5px;
            font-weight: 700;
            color: #6b7280;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 3px 9px;
            cursor: pointer;
            transition: all .15s ease;
        }
        .ph-area-preset-btn:hover { border-color: #10b981; color: #059669; background: #f0fdf4; }
        .ph-area-preset-btn.active { background: #111827; border-color: #111827; color: #fff; }
        /* Damage report photo — shows what was actually picked instead of
           just a filename, so staff can confirm it's the right shot before
           saving. */
        .ph-damage-photo-preview {
            position: relative;
            width: 130px;
            aspect-ratio: 1 / 1;
            border-radius: 12px;
            overflow: hidden;
            border: 1.5px solid #d1d5db;
        }
        .ph-damage-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .ph-damage-photo-preview button {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: rgba(17, 24, 39, 0.75);
            color: #fff;
            border: none;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s ease;
        }
        .ph-damage-photo-preview button:hover { background: #111827; }
        .ph-tabs {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            padding: 0 4px;
            border-bottom: 1.5px solid #e5e7eb;
            overflow-x: auto;
        }
        .ph-tab {
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
        }
        .ph-tab:hover { color: #374151; background: #e9eaec; }
        .ph-tab-active {
            top: 0;
            color: #111827;
            background: #fff;
            z-index: 1;
        }
        .ph-tab-active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -2px;
            height: 2px;
            background: #fff;
        }
        .ph-tab-count {
            margin-left: 3px;
            font-weight: 800;
            color: #c2c6cc;
        }
        .ph-tab-active .ph-tab-count { color: #9ca3af; }
    </style>

    <script>
    const phNewReport = <?= $ph_new_report ? json_encode($ph_new_report) : 'null' ?>;

    if (phNewReport) {
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('phSuccessRef').textContent = phNewReport.reference_id;
            document.getElementById('phSuccessModal').classList.remove('hidden');
            document.getElementById('phSuccessModal').classList.add('flex');
        });
    }
    const phAllReports = <?= json_encode($ph_all_rows) ?>;
    const PH_STATUS_BADGE = {
        verified: 'bg-blue-100 text-blue-700',
        pending: 'bg-purple-100 text-purple-700'
    };

    const PH_STATUS_ICON = {
        verified: { bg: '#dbeafe', fg: '#1d4ed8', path: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        pending: { bg: '#ede9fe', fg: '#6d28d9', path: 'M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }
    };
    const PH_CROP_LABEL = { yellow_corn: 'Yellow Corn', white_corn: 'White Corn', cassava: 'Cassava' };
    const PH_TYPE_BADGE = {
        planting: 'bg-sky-100 text-sky-700',
        harvesting: 'bg-orange-100 text-orange-700',
        damage: 'bg-rose-100 text-rose-700',
        growth: 'bg-teal-100 text-teal-700'
    };
    const PH_TYPE_ACCENT = {
        planting: '#38bdf8',
        harvesting: '#fb923c',
        damage: '#fb7185',
        growth: '#2dd4bf'
    };
    const PH_STATUS_ORDER = ['pending', 'verified'];
    const PH_STATUS_LABEL = { pending: 'Pending', verified: 'Verified' };
    // "Growth" reports don't get their own tab — they're rare enough that a
    // dedicated section would mostly sit empty; they still show up fine
    // under "All".
    const PH_TYPE_ORDER = ['planting', 'harvesting', 'damage'];
    const PH_TYPE_TAB_LABEL = { planting: 'Planting', harvesting: 'Harvesting', damage: 'Damage' };
    let phCurrentFilter = 'all';
    let phCurrentTypeTab = 'all';

    function phFormatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d)) return iso;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function phFormatDateTime(iso) {
        if (!iso) return '—';
        const d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d)) return iso;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
            + ' · ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    // Splits the list into a tab per report SECTION (Planting, Harvesting,
    // Damage, Growth) instead of lumping every type into one combined view.
    // Only tabs for sections that actually have reports are shown — no empty
    // "Growth" tab if nothing's ever come in as a growth report.
    // Always shows one tab per section (All, Planting, Harvesting, Damage,
    // Growth) — like real browser/folder tabs — instead of only rendering
    // whichever types happen to have data. A section with 0 reports right
    // now still gets a tab; it just shows a 0 count.
    function buildPHTypeTabs() {
        const wrap = document.getElementById('phTypeTabs');
        const outer = document.getElementById('phTypeTabsWrap');
        if (!wrap || !outer) return;
        wrap.innerHTML = '';
        outer.classList.remove('hidden');

        const countFor = (type) => type === 'all'
            ? phAllReports.length
            : phAllReports.filter(r => r.report_type === type).length;

        const makeTab = (type, label, active) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.phTypeTab = type;
            btn.className = 'ph-tab' + (active ? ' ph-tab-active' : '');
            btn.innerHTML = `${label} <span class="ph-tab-count">${countFor(type)}</span>`;
            btn.onclick = () => setPHTypeTab(type, btn);
            return btn;
        };

        wrap.appendChild(makeTab('all', 'All', phCurrentTypeTab === 'all'));
        PH_TYPE_ORDER.forEach(type => {
            wrap.appendChild(makeTab(type, PH_TYPE_TAB_LABEL[type], phCurrentTypeTab === type));
        });
    }

    function setPHTypeTab(type, btnEl) {
        phCurrentTypeTab = type;
        document.querySelectorAll('#phTypeTabs .ph-tab').forEach(el => el.classList.remove('ph-tab-active'));
        if (btnEl) btnEl.classList.add('ph-tab-active');
        // Switching sections resets the status filter, since which statuses
        // even apply can differ per section — rebuild the chips for it.
        phCurrentFilter = 'all';
        buildPHFilterChips();
        renderPHRows();
    }

    // Only render a chip for a status if at least one report in the CURRENT
    // section actually has it — no empty "Rejected" filter sitting there if
    // nothing in this section has ever been rejected.
    function buildPHFilterChips() {
        const chipsEl = document.getElementById('phFilterChips');
        chipsEl.innerHTML = '';

        const scoped = phCurrentTypeTab === 'all'
            ? phAllReports
            : phAllReports.filter(r => r.report_type === phCurrentTypeTab);

        const presentStatuses = PH_STATUS_ORDER.filter(
            status => scoped.some(r => r.status === status)
        );

        // Nothing to filter, or everything's the same single status — skip the row entirely.
        if (presentStatuses.length < 2) {
            phCurrentFilter = 'all';
            return;
        }

        const makeChip = (filter, label, active) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.phFilter = filter;
            btn.className = 'ph-chip' + (active ? ' ph-chip-active' : '');
            btn.textContent = label;
            btn.onclick = () => setPHFilter(filter, btn);
            return btn;
        };

        chipsEl.appendChild(makeChip('all', 'All', phCurrentFilter === 'all'));
        presentStatuses.forEach(status => {
            chipsEl.appendChild(makeChip(status, PH_STATUS_LABEL[status], phCurrentFilter === status));
        });
    }

    function setPHFilter(filter, btnEl) {
        phCurrentFilter = filter;
        document.querySelectorAll('#phFilterChips .ph-chip').forEach(el => el.classList.remove('ph-chip-active'));
        if (btnEl) btnEl.classList.add('ph-chip-active');
        renderPHRows();
    }

    function clearPHFilters() {
        phCurrentFilter = 'all';
        phCurrentTypeTab = 'all';
        document.getElementById('phSearchInput').value = '';
        document.getElementById('phDateInput').value = '';
        buildPHTypeTabs();
        buildPHFilterChips();
        renderPHRows();
    }

    function renderPHRows() {
        const body = document.getElementById('phAllReportsBody');
        const searchTerm = (document.getElementById('phSearchInput')?.value || '').trim().toLowerCase();
        const dateFilter = document.getElementById('phDateInput')?.value || '';

        let rows = phCurrentTypeTab === 'all'
            ? phAllReports
            : phAllReports.filter(r => r.report_type === phCurrentTypeTab);

        rows = phCurrentFilter === 'all'
            ? rows
            : rows.filter(r => r.status === phCurrentFilter);

        if (searchTerm) {
            rows = rows.filter(r => {
                const cropLabel = (PH_CROP_LABEL[r.crop_type] || r.crop_type || '').toLowerCase();
                return (r.farmer_name || '').toLowerCase().includes(searchTerm)
                    || (r.reference_id || '').toLowerCase().includes(searchTerm)
                    || (r.brgy_name || '').toLowerCase().includes(searchTerm)
                    || cropLabel.includes(searchTerm)
                    || (r.description || '').toLowerCase().includes(searchTerm)
                    || (r.report_type || '').toLowerCase().includes(searchTerm);
            });
        }

        if (dateFilter) {
            rows = rows.filter(r => (r.submitted_at || '').slice(0, 10) === dateFilter);
        }

        const clearBtn = document.getElementById('phClearFiltersBtn');
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', !(searchTerm || dateFilter || phCurrentFilter !== 'all' || phCurrentTypeTab !== 'all'));
        }

        body.innerHTML = '';

        if (!rows.length) {
            body.innerHTML = '<div class="ph-empty"><p class="text-xs font-bold uppercase tracking-widest">No reports match these filters</p></div>';
            return;
        }

        rows.forEach((rep) => {
            const isFieldReport = rep.report_type === 'growth' || rep.report_type === 'damage';
            const cropLabel = PH_CROP_LABEL[rep.crop_type] || rep.crop_type || '';
            const badgeClass = PH_STATUS_BADGE[rep.status] || PH_STATUS_BADGE.pending;
            const icon = PH_STATUS_ICON[rep.status] || PH_STATUS_ICON.pending;
            const statusLabel = rep.status.charAt(0).toUpperCase() + rep.status.slice(1);
            const typeLabel = rep.report_type.charAt(0).toUpperCase() + rep.report_type.slice(1);
            const typeChipClass = PH_TYPE_BADGE[rep.report_type] || PH_TYPE_BADGE.planting;
            const sourceLabel = rep.source === 'staff' ? 'Logged by staff' : 'Farmer-submitted';

            const subtitle = isFieldReport
                ? `${typeLabel} Report &mdash; ${(rep.description || 'No notes provided').substring(0, 60)}`
                : `${typeLabel} &middot; ${cropLabel} &middot; ${parseFloat(rep.area_hectares || 0).toFixed(2)} ha`;

            // Growth/Damage reports show their submitted photo as the thumbnail
            // instead of the generic status icon, when one was uploaded.
            const iconHtml = (isFieldReport && rep.photo)
                ? `<img src="uploads/${rep.photo}" class="ph-row-icon" style="object-fit:cover" onerror="this.outerHTML='<div class=&quot;ph-row-icon&quot; style=&quot;background:${icon.bg};color:${icon.fg}&quot;></div>'">`
                : `<div class="ph-row-icon" style="background:${icon.bg};color:${icon.fg}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${icon.path}"/></svg>
                   </div>`;

            const accentColor = PH_TYPE_ACCENT[rep.report_type] || PH_TYPE_ACCENT.planting;

            const row = document.createElement('div');
            row.className = 'ph-row';
            row.dataset.reportId = rep.report_id; // ties this container to its specific report
            row.style.borderLeft = `3px solid ${accentColor}`;
            row.innerHTML = `
                <div class="flex items-center gap-3.5 min-w-0">
                    ${iconHtml}
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-900 text-sm truncate">${rep.farmer_name}</span>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-gray-300">${rep.reference_id}</span>
                            <span class="ph-type-chip ${typeChipClass}">${typeLabel}</span>
                        </div>
                        <p class="text-xs text-gray-500 font-medium truncate">${subtitle}</p>
                        <p class="text-[10px] text-gray-400 font-medium mt-0.5">${rep.brgy_name} &nbsp;&bull;&nbsp; ${sourceLabel} &nbsp;&bull;&nbsp; ${phFormatDateTime(rep.submitted_at)}</p>
                    </div>
                </div>
                <span class="sev-badge shrink-0 ${badgeClass}">${statusLabel}</span>
            `;
            row.onclick = () => {
                openPHViewModal(rep);
            };
            body.appendChild(row);
        });
    }

    // Builds and renders the farm activity list right away — no button click
    // needed to see it, it's live on the page as soon as it loads.
    function initPHReportsList() {
        phCurrentFilter = 'all';
        phCurrentTypeTab = 'all';
        const searchEl = document.getElementById('phSearchInput');
        const dateEl = document.getElementById('phDateInput');
        if (searchEl) searchEl.value = '';
        if (dateEl) dateEl.value = '';
        buildPHTypeTabs();
        buildPHFilterChips();
        renderPHRows();
    }

    function closePHModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).classList.remove('flex');
    }
    function openPHAddModal() {
        // Always reopen fresh on the Planting type.
        phSelectAddType('planting', document.querySelector('#phAddTypeToggle .ph-type-btn[data-type="planting"]'));
        phRemoveDamagePhoto();

        document.getElementById('phAddModal').classList.remove('hidden');
        document.getElementById('phAddModal').classList.add('flex');
    }
    function phSelectAddType(type, btnEl) {
        document.getElementById('phAddTypeInput').value = type;
        document.querySelectorAll('#phAddTypeToggle .ph-type-btn').forEach(b => b.classList.remove('ph-type-btn-active'));
        if (btnEl) btnEl.classList.add('ph-type-btn-active');
        phToggleAddFields(type);
    }
    function phToggleAddFields(type) {
        const isDamage = type === 'damage';
        document.getElementById('phAddCropFields').classList.toggle('hidden', isDamage);
        document.getElementById('phAddDamageFields').classList.toggle('hidden', !isDamage);

        const areaInput = document.getElementById('phAddAreaInput');
        const descInput = document.getElementById('phAddDescriptionInput');
        if (areaInput) areaInput.required = !isDamage;
        if (descInput) descInput.required = isDamage;
    }

    // ── FARM AREA — stepper + quick-pick presets, so entering a value doesn't
    // mean fighting a phone's decimal keypad for something like 0.25 or 0.03. ──
    function phAdjustArea(delta) {
        const input = document.getElementById('phAddAreaInput');
        if (!input) return;
        let val = parseFloat(input.value);
        if (isNaN(val)) val = 0;
        val = Math.max(0.01, Math.round((val + delta) * 100) / 100);
        input.value = val.toFixed(2);
        phSyncAreaPresets();
    }

    function phSetArea(val) {
        const input = document.getElementById('phAddAreaInput');
        if (!input) return;
        input.value = Number(val).toFixed(2);
        phSyncAreaPresets();
    }

    // Highlights whichever preset chip matches the current value (typed or
    // stepped), so the presets still feel connected to manual typing.
    function phSyncAreaPresets() {
        const input = document.getElementById('phAddAreaInput');
        if (!input) return;
        const val = parseFloat(input.value);
        document.querySelectorAll('.ph-area-preset-btn').forEach(btn => {
            btn.classList.toggle('active', !isNaN(val) && parseFloat(btn.dataset.phArea) === val);
        });
    }

    // ── DAMAGE REPORT PHOTO — shows the actual picked image right away. ──
    function phHandleDamagePhotoSelect(event) {
        const file = event.target.files && event.target.files[0];
        const wrap = document.getElementById('phAddDamagePhotoPreviewWrap');
        const img  = document.getElementById('phAddDamagePhotoPreviewImg');
        if (!file) {
            phRemoveDamagePhoto();
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            wrap.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    function phRemoveDamagePhoto() {
        const input = document.getElementById('phAddDamagePhotoInput');
        const wrap  = document.getElementById('phAddDamagePhotoPreviewWrap');
        const img   = document.getElementById('phAddDamagePhotoPreviewImg');
        if (input) input.value = '';
        if (img) img.src = '';
        if (wrap) wrap.classList.add('hidden');
    }

    function openPHViewModal(data) {
        const isFieldReport = data.report_type === 'growth' || data.report_type === 'damage';
        const typeLabel = data.report_type.charAt(0).toUpperCase() + data.report_type.slice(1);

        // Fresh modal open — clear out any error left over from a previous review.
        phSetReviewError('');

        document.getElementById('phv_reference').textContent = data.reference_id;
        document.getElementById('phv_crop').textContent = isFieldReport
            ? (typeLabel + ' Report')
            : (({ 'yellow_corn': 'Yellow Corn', 'white_corn': 'White Corn', 'cassava': 'Cassava' })[data.crop_type] || data.crop_type || '—');

        document.getElementById('phv_farmer').textContent = data.farmer_name;
        document.getElementById('phv_brgy').textContent = data.brgy_name;
        const avatar = document.getElementById('phv_avatar');
        avatar.innerHTML = '';
        if (data.farmer_photo) {
            const img = document.createElement('img');
            img.src = 'uploads/' + data.farmer_photo;
            img.className = 'farmer-avatar';
            avatar.appendChild(img);
        } else {
            avatar.textContent = (data.farmer_name || '?').charAt(0).toUpperCase();
        }

        // Toggle between the Planting/Harvesting field set and the Growth/Damage
        // (photo + GPS + notes) field set.
        document.getElementById('phv_ph_fields').classList.toggle('hidden', isFieldReport);
        document.getElementById('phv_field_fields').classList.toggle('hidden', !isFieldReport);

        if (isFieldReport) {
            document.getElementById('phv_field_type').textContent = typeLabel;
            document.getElementById('phv_field_submitted').textContent = data.submitted_at ? new Date(data.submitted_at).toLocaleString() : '—';
            document.getElementById('phv_description').textContent = data.description || 'No notes provided.';

            const photoWrap = document.getElementById('phv_photo_wrap');
            if (data.photo) {
                document.getElementById('phv_photo').src = 'uploads/' + data.photo;
                photoWrap.classList.remove('hidden');
            } else {
                photoWrap.classList.add('hidden');
            }

            const gpsWrap = document.getElementById('phv_gps_wrap');
            if (data.latitude && data.longitude) {
                const lat = parseFloat(data.latitude), lng = parseFloat(data.longitude);
                const link = document.getElementById('phv_gps_link');
                link.href = `https://www.google.com/maps?q=${lat},${lng}`;
                link.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                document.getElementById('phv_gps_accuracy').textContent = data.gps_accuracy ? ` (accurate to about ${Math.round(parseFloat(data.gps_accuracy))}m)` : '';
                gpsWrap.classList.remove('hidden');
            } else {
                gpsWrap.classList.add('hidden');
            }
        } else {
            document.getElementById('phv_type').textContent = typeLabel;
            document.getElementById('phv_area').textContent = data.area_hectares ? (parseFloat(data.area_hectares).toFixed(2) + ' ha') : '—';
            document.getElementById('phv_variety').textContent = data.variety || '—';
            document.getElementById('phv_stage').textContent = data.planting_stage || '—';
            document.getElementById('phv_submitted').textContent = data.submitted_at ? new Date(data.submitted_at).toLocaleString() : '—';
        }

        document.getElementById('phv_remarks').value = data.remarks || '';
        document.getElementById('phv_report_id').value = data.report_id;

        const pill = document.getElementById('phv_status_pill');
        const pillClasses = {
            verified: 'bg-blue-100 text-blue-700',
            pending: 'bg-purple-100 text-purple-700'
        };
        pill.className = 'sev-badge ml-auto ' + (pillClasses[data.status] || pillClasses.pending);
        pill.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);

        const actionRow = document.getElementById('phv_action_row');
        const lockedNote = document.getElementById('phv_locked_note');
        if (data.status === 'pending') {
            actionRow.classList.remove('hidden');
            lockedNote.classList.add('hidden');
        } else {
            actionRow.classList.add('hidden');
            lockedNote.classList.remove('hidden');
        }

        document.getElementById('phViewModal').classList.remove('hidden');
        document.getElementById('phViewModal').classList.add('flex');
    }

    // ── Accept / Reject — handled over fetch so the page never has to do a
    // full reload just to review one report. Works the same way for every
    // report type (planting, harvesting, damage, growth), since it only
    // ever acts on a report_id.
    function phSetReviewError(msg) {
        const el = document.getElementById('phv_error');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.toggle('hidden', !msg);
    }

    function phFindReportIndex(reportId) {
        return phAllReports.findIndex(r => Number(r.report_id) === Number(reportId));
    }

    // Removes a report from the in-memory list + re-renders everything that
    // depends on it (filter chips and the table).
    function phRemoveReportLocally(reportId) {
        const idx = phFindReportIndex(reportId);
        if (idx !== -1) phAllReports.splice(idx, 1);
        buildPHTypeTabs();
        buildPHFilterChips();
        renderPHRows();
    }

    // Applies a status change (e.g. Verified) to the in-memory list without
    // needing to refetch anything from the server.
    function phUpdateReportLocally(reportId, newStatus, remarks) {
        const idx = phFindReportIndex(reportId);
        if (idx !== -1) {
            phAllReports[idx].status = newStatus;
            phAllReports[idx].remarks = remarks;
        }
        buildPHFilterChips();
        renderPHRows();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPHReportsList();

        const phAddForm = document.getElementById('phAddForm');
        if (phAddForm) {
            phAddForm.addEventListener('submit', function (e) {
                const type = document.getElementById('phAddTypeInput')?.value || 'planting';
                const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
                if (!confirm('Save this ' + typeLabel + ' report? It will be logged as Pending for review.')) {
                    e.preventDefault();
                }
            });
        }

        const phvForm = document.getElementById('phv_form');
        if (!phvForm) return;

        phvForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // e.submitter is whichever button (Accept or Reject) was actually
            // clicked — required since a form can have more than one submit
            // button and FormData alone won't tell us which one fired.
            const submitter = e.submitter || document.activeElement;
            if (!submitter || !submitter.name) return;

            const isReject = submitter.id === 'phv_reject_btn';
            const confirmMsg = isReject
                ? 'Reject this report? Rejected reports are permanently deleted and cannot be recovered.'
                : 'Accept and verify this report? Once verified it will be locked and can no longer be changed.';
            if (!confirm(confirmMsg)) {
                return;
            }

            phSetReviewError('');

            const verifyBtn = document.getElementById('phv_verify_btn');
            const rejectBtn = document.getElementById('phv_reject_btn');
            const originalLabel = submitter.textContent;
            [verifyBtn, rejectBtn].forEach(b => { if (b) b.disabled = true; });
            submitter.textContent = isReject ? 'Rejecting…' : 'Saving…';

            const formData = new FormData(phvForm);
            formData.set('ph_new_status', submitter.value);

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.deleted) {
                            phRemoveReportLocally(data.report_id);
                        } else {
                            phUpdateReportLocally(data.report_id, data.new_status, data.remarks);
                        }
                        closePHModal('phViewModal');
                    } else {
                        // Report vanished from under us (already reviewed elsewhere,
                        // bad id, etc.) — drop it locally too so the UI can't get
                        // stuck pointing at a row that no longer exists.
                        if (data.gone) {
                            phRemoveReportLocally(data.report_id);
                            closePHModal('phViewModal');
                        }
                        phSetReviewError(data.message || 'Something went wrong. Please try again.');
                    }
                })
                .catch(() => {
                    phSetReviewError('Network error — please check your connection and try again.');
                })
                .finally(() => {
                    [verifyBtn, rejectBtn].forEach(b => { if (b) b.disabled = false; });
                    submitter.textContent = originalLabel;
                });
        });
    });
    </script>
    <?php
}