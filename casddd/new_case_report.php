<?php
/**
 * NEW FIELD CASE REPORT
 * ----------------------------------------------------------------------
 * Self-contained module for the "New Field Case Report" create form.
 * Split out of reports.php to keep that file lighter.
 *
 * Contains:
 *   1. POST handler — runs immediately when this file is require_once'd,
 *      BEFORE any HTML has been rendered (same position the old handler
 *      used to occupy in reports.php).
 *   2. render_new_case_report_modal() — outputs the form markup + JS as a
 *      popup modal (like the original). Call this where the modal should
 *      physically sit in the DOM; a button toggles it open/closed.
 *
 * Format for this form (single page, no step wizard):
 *   Farmer -> Barangay -> Disease (click to reveal, select up to 3, optional)
 *   -> Disease Pictures (any number of photos) -> Severity Level
 *   -> Date Planted -> Field Observation -> Submit
 *
 * Because `disease_cases.disease_id` is a single value per row, selecting
 * multiple diseases inserts one row per disease, all sharing the same
 * reference_id, farmer/barangay/severity/date/description/photos, so they
 * read back as one grouped case report. If no disease is selected, a single
 * row is inserted using the "Other / Unidentified" disease (id 999) so the
 * report still has somewhere to live in the schema.
 *
 * Photos: at least ONE image is required (any number can be attached). Since
 * `photo_evidence` is a single varchar column, filenames are stored as a
 * comma-separated list.
 * ----------------------------------------------------------------------
 */

// ── DATABASE CONNECTION GUARD ───────────────────────────────────────────
// This file is a partial that reports.php pulls in with require_once. It uses
// $conn (schema checks + POST handlers) but never defined it — it relied on
// reports.php having already loaded src/db_config.php. This guard makes the
// file safe regardless of how it is loaded:
//   1. $conn already in scope            -> nothing to do (normal case)
//   2. $conn exists in the global scope  -> pull it in (e.g. file included
//                                           from inside a function)
//   3. otherwise                         -> load db_config.php ourselves
// The @var line also stops IDEs (Intelephense / PhpStorm) from flagging
// "Undefined variable $conn" on this file.
/** @var mysqli $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
    } else {
        require_once __DIR__ . '/src/db_config.php';
    }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    die('Database connection ($conn) is not available in ' . basename(__FILE__) . '.');
}

// Personnel log helpers — who is signed in, so the case records its creator.
require_once __DIR__ . '/personnel_log.php';

// ── 1. HANDLE DATABASE INSERT (up to 3 diseases optional, 1+ photos required) ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_report'])) {

    $disease_ids = isset($_POST['disease_ids']) && is_array($_POST['disease_ids']) ? $_POST['disease_ids'] : [];

    // Server-side guard — cap at 3, selection itself is optional
    if (count($disease_ids) > 3) {
        echo "<script>alert('You can select at most 3 diseases.'); window.history.back();</script>";
        exit;
    }
    if (count($disease_ids) === 0) {
        // No disease specified — fall back to "Other / Unidentified" (disease_id 999)
        $disease_ids = [999];
    }

    // "Other / Unidentified" is mutually exclusive with real diseases — a case can't
    // be both "we don't know what this is" and "we identified 2 specific diseases" at
    // once. If 999 slipped through alongside other picks (e.g. JS was bypassed),
    // collapse the selection down to just 999 so only ONE row gets inserted instead
    // of duplicating the report across an unidentified row + identified rows.
    if (in_array('999', $disease_ids, true) || in_array(999, $disease_ids, true)) {
        $disease_ids = [999];
    }

    $ref_id       = "REF-" . date("Y") . "-" . strtoupper(substr(md5(time()), 0, 4));
    // Personnel log: the staff member creating this case by hand (0 = could not be determined).
    $reported_by  = (int) personnel_log_current_user_id($conn);
    $farmer_name  = mysqli_real_escape_string($conn, $_POST['farmer_name']);
    $brgy_id      = mysqli_real_escape_string($conn, $_POST['brgy_id']);
    $stage        = mysqli_real_escape_string($conn, $_POST['growth_stage']);
    $date_planted = mysqli_real_escape_string($conn, $_POST['date_planted']);
    $desc         = mysqli_real_escape_string($conn, $_POST['description']);
    $severity     = mysqli_real_escape_string($conn, $_POST['severity']);

    $full_desc = "[FARMER:" . $farmer_name . "]\n" . $desc;
    $full_desc = mysqli_real_escape_string($conn, $full_desc);

    // ── Handle any number of uploaded photos, store as comma-separated filenames ──
    $allowed_types  = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $saved_filenames = [];
    if (!empty($_FILES['disease_photos']) && is_array($_FILES['disease_photos']['name'])) {
        $fileCount = count($_FILES['disease_photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['disease_photos']['name'][$i])) continue;
            if ($_FILES['disease_photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['disease_photos']['type'][$i], $allowed_types)) continue;

            $ext      = pathinfo($_FILES['disease_photos']['name'][$i], PATHINFO_EXTENSION);
            $filename = time() . '_evidence_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
            $dest     = 'uploads/' . $filename;
            if (move_uploaded_file($_FILES['disease_photos']['tmp_name'][$i], $dest)) {
                $saved_filenames[] = $filename;
            }
        }
    }
    // Photo evidence is REQUIRED — at least one image must have uploaded successfully
    // (a file that was the wrong type or failed to upload doesn't count).
    if (empty($saved_filenames)) {
        echo "<script>alert('A photo is required. Please attach at least one valid image (JPG, PNG, GIF or WEBP) of the affected crop.'); window.history.back();</script>";
        exit;
    }
    $photo_evidence_val = "'" . mysqli_real_escape_string($conn, implode(',', $saved_filenames)) . "'";

    $insertedCount = 0;
    foreach ($disease_ids as $raw_disease_id) {
        $d_id = mysqli_real_escape_string($conn, $raw_disease_id);

        $insertQuery = "INSERT INTO disease_cases
            (reference_id, disease_id, farm_id, farmer_id, barangay_id, reported_by, growth_stage, date_planted, description, severity, photo_evidence, status, report_date)
            VALUES ('$ref_id', '$d_id', 0, 0, '$brgy_id', $reported_by, '$stage', '$date_planted', '$full_desc', '$severity', $photo_evidence_val, 'pending', NOW())";

        if (mysqli_query($conn, $insertQuery)) {
            $insertedCount++;
        }
    }

    if ($insertedCount > 0) {
        echo "<script>alert('Report Successfully Submitted for Review! ($insertedCount disease(s) logged under $ref_id)'); window.location='reports.php?tab=pending';</script>";
        exit;
    } else {
        echo "<script>alert('Something went wrong while saving the report. Please try again.'); window.history.back();</script>";
        exit;
    }
}

/**
 * Renders the "New Field Case Report" form as a popup modal — single page,
 * no step wizard. The disease checklist stays collapsed until clicked open.
 */
function render_new_case_report_modal($conn, $today, $minDate) {

    // Corn growth stages, needed only for this form's auto growth-stage calculator
    $stages_data  = [];
    $stages_query = mysqli_query($conn, "SELECT * FROM corn_stages ORDER BY min_days_white ASC");
    while ($s = mysqli_fetch_assoc($stages_query)) {
        $stages_data[] = $s;
    }
    ?>

    <style>
        .disease-check-card { cursor:pointer; }
        .disease-check-card input { position:absolute; opacity:0; pointer-events:none; }
        .disease-check-card .card-body {
            border:2px solid #e5e7eb; border-radius:1rem; padding:0.9rem 1rem;
            background:#fff; transition:all 0.2s ease; display:flex; align-items:center; gap:10px;
        }
        .disease-check-card input:checked + .card-body {
            border-color:#10b981; background:#f0fdf4; box-shadow:0 4px 10px -2px rgba(16,185,129,0.25);
        }
        .disease-check-card.disabled .card-body { opacity:0.4; cursor:not-allowed; }
        .disease-check-card .check-dot {
            width:20px; height:20px; border-radius:6px; border:2px solid #d1d5db; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; transition:all 0.2s ease;
        }
        .disease-check-card input:checked + .card-body .check-dot { background:#10b981; border-color:#10b981; }
        .disease-check-card .check-dot svg { width:12px; height:12px; color:#fff; opacity:0; transition:opacity 0.15s ease; }
        .disease-check-card input:checked + .card-body .check-dot svg { opacity:1; }

        /* "Other / Unidentified" card — visually separated + amber accent so it
           reads as a distinct, mutually-exclusive choice rather than one more
           disease in the list. */
        .disease-check-card.other-unidentified .card-body { border-style:dashed; }
        .disease-check-card.other-unidentified input:checked + .card-body {
            border-color:#f59e0b; background:#fffbeb; box-shadow:0 4px 10px -2px rgba(245,158,11,0.25);
        }
        .disease-check-card.other-unidentified input:checked + .card-body .check-dot { background:#f59e0b; border-color:#f59e0b; }

        .ncr-upload-zone {
            border:2px dashed #d1d5db; border-radius:1rem; padding:1.75rem;
            text-align:center; cursor:pointer; transition:all 0.25s ease; background:#f9fafb; position:relative;
        }
        .ncr-upload-zone:hover { border-color:#10b981; background:#f0fdf4; }
        .ncr-upload-zone.ncr-upload-error { border-color:#ef4444; background:#fef2f2; }
        .ncr-upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        #ncrPhotoGrid { display:grid; grid-template-columns:repeat(auto-fill, minmax(110px, 1fr)); gap:10px; margin-top:0.85rem; }
        .ncr-photo-thumb { position:relative; border-radius:0.75rem; overflow:hidden; border:1.5px solid #d1d5db; aspect-ratio:1/1; }
        .ncr-photo-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .ncr-photo-thumb button {
            position:absolute; top:4px; right:4px; width:20px; height:20px; border-radius:6px;
            background:rgba(15,23,42,0.75); color:#fff; border:none; cursor:pointer; font-size:0.7rem;
            display:flex; align-items:center; justify-content:center; line-height:1;
        }

        /* Collapsible disease checklist — hidden until the toggle is clicked */
        #ncrDiseaseGrid { display:none; }
        #ncrDiseaseGrid.open { display:grid; }
        .ncr-disease-toggle {
            width:100%; display:flex; align-items:center; justify-content:space-between;
            background:none; border:none; padding:0; cursor:pointer; text-align:left;
        }
        .ncr-disease-toggle .chevron { transition:transform 0.2s ease; }
        .ncr-disease-toggle.open .chevron { transform:rotate(180deg); }
    </style>

    <!-- ═══ CREATE / MANUAL FIELD REPORT MODAL (popup, single page) ═══ -->
    <div id="createModal" class="hidden fixed inset-0 bg-slate-900/90 backdrop-blur-md z-[100] flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl modal-container shadow-2xl">

            <!-- Modal Header -->
            <div class="p-7 border-b bg-gray-50 flex justify-between items-center flex-shrink-0">
                <div>
                    <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest mb-1">CASD Office — Manual Case Encoding</p>
                    <h3 class="font-black text-gray-900 text-xl tracking-tight">New Field <span class="text-emerald-600">Case Report</span></h3>
                </div>
                <button onclick="closeCreateModal()" class="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 text-gray-400 hover:bg-rose-50 hover:text-rose-500 text-2xl font-light transition-colors">&times;</button>
            </div>

            <div class="modal-scroll-area">
                <form id="ncrForm" action="" method="POST" enctype="multipart/form-data" class="space-y-5">

                    <!-- Farmer & Barangay -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-4">— Farmer & Location</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label">Farmer</label>
                                <select name="farmer_name" required class="field-input">
                                    <option value="" disabled selected>— Select farmer —</option>
                                    <?php
                                    $farmers = mysqli_query($conn, "SELECT farmer_name FROM farmers ORDER BY farmer_name ASC");
                                    while ($f = mysqli_fetch_assoc($farmers)) {
                                        echo "<option value='" . htmlspecialchars($f['farmer_name'], ENT_QUOTES) . "'>" . htmlspecialchars($f['farmer_name']) . "</option>";
                                    } ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Barangay / Sector</label>
                                <select name="brgy_id" required class="field-input">
                                    <option value="" disabled selected>— Select barangay —</option>
                                    <?php
                                    $brgy_q = mysqli_query($conn, "SELECT id, name FROM barangays ORDER BY name ASC");
                                    while ($b = mysqli_fetch_assoc($brgy_q)) {
                                        echo "<option value='{$b['id']}'>" . htmlspecialchars($b['name']) . "</option>";
                                    } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Disease multi-select — collapsed, only appears when clicked -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <button type="button" class="ncr-disease-toggle" id="ncrDiseaseToggleBtn" onclick="ncrToggleDiseaseList()">
                            <span>
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">— Identified Diseases</span>
                                <span class="text-[10px] font-bold text-gray-300">Optional — click to select up to 3 diseases observed in the field</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span id="ncrDiseaseCount" class="text-[10px] font-black text-gray-400 uppercase tracking-wide whitespace-nowrap">0 / 3 selected</span>
                                <svg class="chevron w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </button>
                        <div class="grid grid-cols-2 gap-2.5 mt-4" id="ncrDiseaseGrid">
                            <?php
                            $diseases = mysqli_query($conn, "SELECT disease_id, disease_name FROM diseases WHERE disease_id != 999 ORDER BY disease_name ASC");
                            while ($d = mysqli_fetch_assoc($diseases)) { ?>
                            <label class="disease-check-card">
                                <input type="checkbox"
                                       class="ncr-disease-checkbox"
                                       name="disease_ids[]"
                                       value="<?= (int)$d['disease_id'] ?>"
                                       data-name="<?= htmlspecialchars($d['disease_name'], ENT_QUOTES) ?>"
                                       onchange="ncrUpdateDiseaseCount()">
                                <div class="card-body">
                                    <div class="check-dot">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="font-black text-gray-700 text-[11px] uppercase tracking-wide"><?= htmlspecialchars($d['disease_name']) ?></span>
                                </div>
                            </label>
                            <?php } ?>

                            <!-- "Other / Unidentified" — a distinct, mutually-exclusive 5th choice.
                                 Picking it clears + locks out every real disease above (and vice
                                 versa), so a report never ends up inserted as BOTH "unidentified"
                                 AND one or more named diseases — that combo is what caused the
                                 duplicate-looking rows under the same reference ID. -->
                            <label class="disease-check-card other-unidentified" style="grid-column:1 / -1;">
                                <input type="checkbox"
                                       class="ncr-disease-checkbox ncr-other-checkbox"
                                       id="ncrOtherCheckbox"
                                       name="disease_ids[]"
                                       value="999"
                                       data-name="Other / Unidentified"
                                       onchange="ncrHandleOtherToggle()">
                                <div class="card-body">
                                    <div class="check-dot">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <span class="font-black text-gray-700 text-[11px] uppercase tracking-wide">Other / Unidentified</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Disease Pictures (any number, general upload) -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">— Disease Pictures</p>
                            <span class="text-[9px] font-black text-red-500 uppercase tracking-widest">(Required · at least 1 photo)</span>
                        </div>
                        <p class="text-[10px] font-bold text-gray-300 mb-4">Attach as many field photos as needed to document the affected crop</p>

                        <div class="ncr-upload-zone" id="ncr_upload_zone" onclick="document.getElementById('ncr_photo_input').click()">
                            <input type="file" id="ncr_photo_input" name="disease_photos[]" accept="image/*" multiple required onchange="ncrHandlePhotoSelect(event)">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="text-xs font-black text-gray-400 uppercase tracking-wide">Click to upload photos <span class="text-red-500">*</span></p>
                            <p class="text-[10px] font-bold text-gray-300 mt-1">JPG · PNG · WEBP — max 5MB each, add as many as you like</p>
                        </div>
                        <div id="ncrPhotoGrid"></div>
                    </div>

                    <!-- Severity -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <label class="field-label">Severity Level</label>
                        <div class="grid grid-cols-4 gap-2 mt-1">
                            <?php
                            $sev_opts = [
                                'low'      => ['#d1fae5', '#065f46', 'Low'],
                                'moderate' => ['#fef3c7', '#92400e', 'Moderate'],
                                'high'     => ['#fee2e2', '#991b1b', 'High'],
                                'critical' => ['#fce7f3', '#9d174d', 'Critical'],
                            ];
                            foreach ($sev_opts as $val => [$bg, $color, $label]) { ?>
                            <label style="cursor:pointer;">
                                <input type="radio" name="severity" value="<?= $val ?>" required class="sr-only peer">
                                <div class="text-center py-2.5 px-1 rounded-xl border-2 border-transparent font-black text-[10px] uppercase tracking-wide transition-all peer-checked:scale-105 peer-checked:border-current peer-checked:shadow-md"
                                     style="background:<?= $bg ?>;color:<?= $color ?>;">
                                    <?= $label ?>
                                </div>
                            </label>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Date Planted / Growth Stage -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-4">— Crop Stage</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="field-label" style="color:#059669;">Date Planted</label>
                                <input type="date"
                                       name="date_planted"
                                       id="ncr_input_date_planted"
                                       onchange="ncrCalculateGrowth()"
                                       required
                                       min="<?= $minDate ?>"
                                       max="<?= $today ?>"
                                       class="field-input"
                                       style="background:#f0fdf4;border-color:#a7f3d0;">
                                <p class="mt-1 text-[9px] font-bold text-gray-400 uppercase tracking-wide">
                                    Within last 130 days · No future dates
                                </p>
                            </div>
                            <div>
                                <label class="field-label">Auto-Computed Growth Stage</label>
                                <input type="hidden" name="growth_stage" id="ncr_hidden_growth_stage">
                                <div id="ncr_display_growth_stage" class="field-input text-[11px] uppercase" style="background:#f1f5f9;color:#94a3b8;cursor:default;">
                                    — Enter planting date first —
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Field Observations -->
                    <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-4">— Field Observations</p>
                        <label class="field-label">Observation / Incident Notes</label>
                        <textarea name="description" rows="4" class="field-input"
                                  placeholder="Describe visible symptoms, spread pattern, affected area size, field conditions..."></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="create_report"
                            class="btn-intel w-full bg-emerald-600 text-white font-black py-5 rounded-2xl uppercase text-xs tracking-widest shadow-xl flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Submit Report for Review
                    </button>

                </form>
            </div>
        </div>
    </div>

    <script>
    const ncrCornStages = <?= json_encode($stages_data) ?>;
    const NCR_MAX_DISEASES = 3;
    let ncrSelectedPhotoFiles = []; // accumulated across multiple picks, since <input multiple> replaces the file list each time

    function openCreateModal()  { document.getElementById('createModal').classList.remove('hidden'); }
    function closeCreateModal() { document.getElementById('createModal').classList.add('hidden'); }

    document.getElementById('createModal').addEventListener('click', function(e) {
        if (e.target === this) closeCreateModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('createModal').classList.contains('hidden')) {
            closeCreateModal();
        }
    });

    // ── DISEASE CHECKLIST — collapsed by default, appears only when clicked ──
    function ncrToggleDiseaseList() {
        document.getElementById('ncrDiseaseGrid').classList.toggle('open');
        document.getElementById('ncrDiseaseToggleBtn').classList.toggle('open');
    }

    // "Other / Unidentified" is mutually exclusive with real diseases. Checking it
    // clears and locks every named-disease checkbox (a case can't be both
    // "unidentified" and "identified as X"); checking any named disease locks
    // the "Other / Unidentified" box back off. This is what stops a submission
    // from inserting one row per disease PLUS a separate "unidentified" row for
    // the same report — the duplicate rows under one reference ID mentioned above.
    function ncrHandleOtherToggle() {
        const otherBox   = document.getElementById('ncrOtherCheckbox');
        const namedBoxes = document.querySelectorAll('.ncr-disease-checkbox:not(.ncr-other-checkbox)');

        if (otherBox.checked) {
            namedBoxes.forEach(cb => {
                cb.checked  = false;
                cb.disabled = true;
                cb.closest('.disease-check-card').classList.add('disabled');
            });
        } else {
            namedBoxes.forEach(cb => {
                cb.disabled = false;
                cb.closest('.disease-check-card').classList.remove('disabled');
            });
        }
        ncrUpdateDiseaseCount();
    }

    function ncrUpdateDiseaseCount() {
        const otherBox = document.getElementById('ncrOtherCheckbox');
        const boxes    = document.querySelectorAll('.ncr-disease-checkbox:not(.ncr-other-checkbox)');
        const checked  = document.querySelectorAll('.ncr-disease-checkbox:not(.ncr-other-checkbox):checked');
        const countEl  = document.getElementById('ncrDiseaseCount');
        countEl.innerText = checked.length + ' / ' + NCR_MAX_DISEASES + ' selected';

        // Cap selection at NCR_MAX_DISEASES — disable the rest once reached
        boxes.forEach(cb => {
            const card = cb.closest('.disease-check-card');
            if (!cb.checked && checked.length >= NCR_MAX_DISEASES) {
                cb.disabled = true;
                card.classList.add('disabled');
            } else if (!otherBox.checked) {
                cb.disabled = false;
                card.classList.remove('disabled');
            }
        });

        // Picking any named disease locks "Other / Unidentified" back off, same
        // mutual-exclusivity rule as ncrHandleOtherToggle() above.
        const otherCard = otherBox.closest('.disease-check-card');
        if (checked.length > 0) {
            otherBox.disabled = true;
            otherCard.classList.add('disabled');
        } else {
            otherBox.disabled = false;
            otherCard.classList.remove('disabled');
        }
    }

    // ── MULTI-PHOTO UPLOAD (unlimited photos, accumulated across picks) ──
    function ncrHandlePhotoSelect(event) {
        const newFiles = Array.from(event.target.files);
        ncrSelectedPhotoFiles = ncrSelectedPhotoFiles.concat(newFiles);
        ncrRenderPhotoGrid();
        ncrSyncPhotoInput();
        if (ncrSelectedPhotoFiles.length > 0) {
            document.getElementById('ncr_upload_zone').classList.remove('ncr-upload-error');
        }
    }

    // The photo input is `required`: when the browser blocks a submit because no photo
    // is attached, turn the upload zone red so it's obvious what's missing.
    document.getElementById('ncr_photo_input').addEventListener('invalid', function () {
        document.getElementById('ncr_upload_zone').classList.add('ncr-upload-error');
    });

    function ncrRenderPhotoGrid() {
        const grid = document.getElementById('ncrPhotoGrid');
        grid.innerHTML = '';
        ncrSelectedPhotoFiles.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = e => {
                const thumb = document.createElement('div');
                thumb.className = 'ncr-photo-thumb';
                thumb.innerHTML = `<img src="${e.target.result}" alt="Photo ${idx + 1}">
                    <button type="button" onclick="ncrRemovePhoto(${idx})">&times;</button>`;
                grid.appendChild(thumb);
            };
            reader.readAsDataURL(file);
        });
    }

    function ncrRemovePhoto(idx) {
        ncrSelectedPhotoFiles.splice(idx, 1);
        ncrRenderPhotoGrid();
        ncrSyncPhotoInput();
    }

    // Rebuild the actual <input type=file> FileList from our accumulated array
    // so removals/additions are correctly reflected in the submitted form data.
    function ncrSyncPhotoInput() {
        const dt = new DataTransfer();
        ncrSelectedPhotoFiles.forEach(file => dt.items.add(file));
        document.getElementById('ncr_photo_input').files = dt.files;
    }

    // ── GROWTH STAGE CALCULATOR ──
    function ncrCalculateGrowth() {
        const dateInput = document.getElementById('ncr_input_date_planted').value;
        if (!dateInput) return;

        const today   = new Date();
        today.setHours(0, 0, 0, 0);
        const planted = new Date(dateInput);
        planted.setHours(0, 0, 0, 0);

        const diffDays = Math.ceil((today - planted) / (1000 * 60 * 60 * 24));
        if (diffDays < 0) {
            document.getElementById('ncr_display_growth_stage').innerText = '⚠ Future date not allowed';
            document.getElementById('ncr_hidden_growth_stage').value = '';
            return;
        }
        if (diffDays > 130) {
            document.getElementById('ncr_display_growth_stage').innerText = '⚠ Date exceeds 130-day limit';
            document.getElementById('ncr_hidden_growth_stage').value = '';
            return;
        }

        let stage = "R6: Physiological Maturity";
        for (let s of ncrCornStages) {
            if (diffDays <= s.max_days_yellow) {
                stage = `${s.stage_level}: ${s.stage_name} (Day ${diffDays})`;
                break;
            }
        }
        document.getElementById('ncr_display_growth_stage').innerText = stage;
        document.getElementById('ncr_hidden_growth_stage').value = stage;
    }
    </script>
    <?php
}