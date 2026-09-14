<?php
/**
 * REVIEW / CASE REPORT POPUP
 * -----------------------------------------------------------------------
 * The "Review File" popup (view modal + messenger popup + photo lightbox)
 * used by reports.php. Split out of reports.php to keep that file lighter,
 * the same way new_case_report.php holds the New Field Entry modal.
 *
 * Usage from reports.php:
 *     require_once __DIR__ . "/review.php";
 *     ...
 *     render_review_modal($STATUS_FLOW, $allDiseasesForPicker);
 *
 * render_review_modal() prints the modal CSS, HTML and the JS that drives
 * it (openViewModal, openMessageModal, status pills, treatment checklist,
 * chat thread, GPS copy button, photo lightbox, etc). $STATUS_FLOW is the
 * same one-way status flow array reports.php enforces server-side, passed
 * through so the client-side status pills stay in sync with it.
 *
 * $allDiseasesForPicker is a plain array of ['disease_id'=>..., 'disease_name'=>...]
 * for every real disease (i.e. excluding "Other / Unidentified"), used to
 * populate the manual disease-verification picker.
 */
function render_review_modal($STATUS_FLOW, $allDiseasesForPicker = []) {
?>
<style>
    /* Messenger-style conversation thread (case recommendation <-> farmer reply) */
    .chat-row { display:flex; flex-direction:column; max-width:82%; }
    .chat-row.staff  { align-self:flex-end; align-items:flex-end; margin-left:auto; }
    .chat-row.farmer { align-self:flex-start; align-items:flex-start; margin-right:auto; }
    .chat-bubble { padding:9px 13px; border-radius:15px; font-size:0.78rem; line-height:1.5; white-space:pre-wrap; word-wrap:break-word; }
    .chat-bubble.staff  { background:linear-gradient(135deg,#0d9488,#0f766e); color:#fff; border-bottom-right-radius:4px; }
    .chat-bubble.farmer { background:rgba(15,23,42,0.09); color:#1e293b; border:1px solid rgba(15,23,42,0.14); border-bottom-left-radius:4px; }
    .chat-meta { font-size:0.55rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; margin-top:4px; color:#475569; }
    .msg-cancel-btn {
        background:none; border:none; color:#64748b; font-size:0.56rem; font-weight:800;
        text-transform:uppercase; letter-spacing:0.05em; text-decoration:underline; cursor:pointer;
        padding:0; margin-top:3px;
    }
    .msg-cancel-btn:hover { color:#b91c1c; }
    .msg-cancel-btn.inspection-variant {
        text-decoration:none; border:1px solid rgba(248,113,113,0.35); background:rgba(248,113,113,0.08);
        color:#b91c1c; padding:5px 10px; border-radius:8px; margin-top:6px;
    }
    .msg-cancel-btn.inspection-variant:hover { background:rgba(248,113,113,0.16); border-color:#f87171; color:#b91c1c; }
    @keyframes reportPopIn {
        from { opacity:0; transform:translate(-50%,-48%) scale(.95); }
        to   { opacity:1; transform:translate(-50%,-50%) scale(1); }
    }

    /* ── VIEW MODAL: two-column convenience layout ── */
    .modal-flex-body { display:flex; gap:18px; flex:1; min-height:0; }
    .modal-left-col  { flex:1 1 58%; min-width:0; overflow-y:auto; padding-right:6px; scrollbar-width:thin; scrollbar-color:rgba(16,185,129,0.3) transparent; }
    .modal-right-col { flex:0 0 250px; overflow-y:auto; padding-left:18px; border-left:1px solid rgba(15,23,42,0.08); display:flex; flex-direction:column; }
    @media (max-width: 680px) {
        .modal-flex-body { flex-direction:column; overflow-y:auto; }
        .modal-left-col  { overflow:visible; padding-right:0; }
        .modal-right-col { flex:none; padding-left:0; border-left:none; border-top:1px solid rgba(15,23,42,0.08); padding-top:14px; margin-top:4px; }
    }

    /* Quick status pill selector — replaces multi-step dropdown */
    .status-quick-select { display:flex; flex-direction:column; gap:8px; }
    .status-pill {
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 14px; border-radius:10px; cursor:pointer; user-select:none;
        font-size:0.68rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em;
        background:rgba(15,23,42,0.04); border:1.5px solid rgba(15,23,42,0.1); color:#94a3b8;
        transition:all 0.18s ease;
    }
    .status-pill:hover { border-color:rgba(15,23,42,0.25); color:#1e293b; }
    .status-pill .dot { width:8px; height:8px; border-radius:999px; background:currentColor; opacity:0.5; flex-shrink:0; margin-left:8px; }
    .status-pill[data-value="pending"].active  { background:rgba(245,158,11,0.15); border-color:#f59e0b; color:#92400e; }
    .status-pill[data-value="verified"].active { background:rgba(37,99,235,0.15);  border-color:#2563eb; color:#1e40af; }
    .status-pill[data-value="resolved"].active { background:rgba(16,185,129,0.15); border-color:#10b981; color:#065f46; }
    .status-pill[data-value="rejected"].active { background:rgba(239,68,68,0.15);  border-color:#ef4444; color:#b91c1c; }
    .status-pill.active .dot { opacity:1; }
    .status-pill.disabled { opacity:0.32; cursor:not-allowed; pointer-events:none; }
    .status-flow-hint { color:#475569; font-size:0.58rem; font-weight:700; line-height:1.5; margin-top:6px; }

    /* Selectable treatment-instruction checklist buttons */
    .rec-instruction-btn {
        display:flex; align-items:flex-start; gap:12px; width:100%; text-align:left;
        background:rgba(15,23,42,0.035); border:1.5px solid rgba(15,23,42,0.1); border-radius:12px;
        padding:13px 14px; cursor:pointer; transition:all 0.15s ease; margin-bottom:9px;
    }
    .rec-instruction-btn:hover { border-color:rgba(16,185,129,0.45); background:rgba(16,185,129,0.07); transform:translateY(-1px); }
    .rec-instruction-btn.selected { background:rgba(16,185,129,0.14); border-color:#10b981; box-shadow:0 0 0 1px rgba(16,185,129,0.25) inset; }
    .rec-instruction-btn .rec-check {
        flex-shrink:0; width:24px; height:24px; margin-top:1px; border-radius:999px;
        border:2px solid rgba(15,23,42,0.28); display:flex; align-items:center; justify-content:center;
        font-size:0.72rem; font-weight:900; color:rgba(15,23,42,0.6); transition:all 0.15s ease;
        background:rgba(15,23,42,0.04);
    }
    .rec-instruction-btn.selected .rec-check { background:#10b981; border-color:#10b981; color:#fff; }
    .rec-instruction-btn .rec-instruction-text { color:#1e293b; font-size:0.82rem; line-height:1.55; padding-top:1px; }
    .rec-instruction-btn.selected .rec-instruction-text { color:#065f46; }

    /* Sticky save action at the bottom of the sidebar so it's always reachable */
    .sidebar-save-wrap { margin-top:auto; padding-top:14px; }

    .gps-copy-btn { background:rgba(15,23,42,0.06); border:1px solid rgba(15,23,42,0.12); color:#94a3b8; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; padding:6px 9px; border-radius:7px; cursor:pointer; transition:all 0.15s ease; }
    .gps-copy-btn:hover { color:#0f172a; border-color:rgba(15,23,42,0.3); }
    .gps-copy-btn.copied { background:rgba(16,185,129,0.15); border-color:#10b981; color:#065f46; }

    /* Field Inspection scheduling — highlighted chat bubble + toggle button + date/time panel */
    .chat-bubble.inspection {
        background:linear-gradient(135deg, rgba(249,115,22,0.24), rgba(194,65,12,0.12));
        border:1.5px solid #f97316; box-shadow:0 0 0 1px rgba(249,115,22,0.18) inset;
    }
    .chat-bubble.inspection .insp-tag {
        display:flex; align-items:center; gap:5px; font-size:0.6rem; font-weight:900;
        text-transform:uppercase; letter-spacing:0.08em; color:#c2410c; margin-bottom:5px;
    }
    .inspection-toggle-btn {
        background:rgba(249,115,22,0.08); border:1px solid rgba(249,115,22,0.25); color:#c2410c;
        font-size:0.66rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em;
        cursor:pointer; padding:8px 12px; border-radius:10px; margin:0 0 10px;
        display:flex; align-items:center; gap:6px; width:100%; justify-content:space-between;
    }
    .inspection-panel {
        display:none; flex-direction:column; gap:10px; margin-bottom:10px;
        padding:14px; background:rgba(249,115,22,0.05); border:1px dashed rgba(249,115,22,0.3); border-radius:12px;
    }
    .inspection-panel label { display:block; color:#94a3b8; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:5px; }
    .inspection-panel input[type="date"], .inspection-panel select {
        width:100%; padding:9px 12px; border-radius:9px; background:rgba(15,23,42,0.06);
        border:1px solid rgba(15,23,42,0.14); color:#1e293b; font-size:0.8rem; outline:none;
        box-sizing:border-box; font-family:inherit;
    }
    .inspection-panel .insp-row { display:flex; gap:10px; }
    .inspection-panel .insp-row > div { flex:1; min-width:0; }
    .inspection-confirm-btn {
        background:linear-gradient(135deg,#f97316,#c2410c); border:none; color:#fff; font-weight:900;
        font-size:0.7rem; text-transform:uppercase; letter-spacing:0.06em; padding:10px 14px; border-radius:9px;
        cursor:pointer; transition:opacity 0.15s ease;
    }
    .inspection-confirm-btn:disabled { opacity:0.4; cursor:not-allowed; }
    .inspection-next-chip {
        display:inline-flex; align-items:center; gap:6px; background:rgba(249,115,22,0.12);
        border:1px solid rgba(249,115,22,0.35); color:#c2410c; font-size:0.62rem; font-weight:900;
        text-transform:uppercase; letter-spacing:0.06em; padding:4px 10px; border-radius:999px;
    }
    .inspection-edit-link {
        background:none; border:none; color:#94a3b8; font-size:0.62rem; font-weight:800;
        text-transform:uppercase; letter-spacing:0.06em; text-decoration:underline; cursor:pointer; padding:0;
    }
    .inspection-edit-link:hover { color:#c2410c; }
    .inspection-cancel-x {
        flex-shrink:0; margin-left:auto; width:22px; height:22px; border-radius:999px;
        background:rgba(148,163,184,0.1); border:1px solid rgba(148,163,184,0.3); color:#94a3b8;
        font-size:0.62rem; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center;
        transition:all 0.15s ease;
    }
    .inspection-cancel-x:hover { background:rgba(248,113,113,0.15); border-color:#f87171; color:#b91c1c; }
    .chat-bubble.inspection.cancelled {
        background:linear-gradient(135deg, rgba(148,163,184,0.18), rgba(71,85,105,0.1));
        border:1.5px solid #64748b; box-shadow:0 0 0 1px rgba(100,116,139,0.15) inset;
    }
    .chat-bubble.inspection.cancelled .insp-tag { color:#94a3b8; }

    /* Cancel-inspection confirmation — an in-app dialog card (not a native prompt/
       confirm, not a full red alarm screen) that slides over the messenger popup.
       IMPORTANT: display is controlled here, not via an inline style + the generic
       "hidden" utility class — an inline display:flex would always beat that class
       toggle and leave the panel stuck open. Toggle the .open class instead. */
    #inspectionCancelOverlay {
        display:none; position:absolute; inset:0; z-index:30;
        background:rgba(4,10,8,0.72); backdrop-filter:blur(4px);
        align-items:center; justify-content:center; padding:20px;
    }
    #inspectionCancelOverlay.open { display:flex; }
    .icc-card {
        width:100%; max-width:360px; text-align:left;
        background:#ffffff; border:1px solid #e2e8f0; border-radius:16px;
        box-shadow:0 24px 60px rgba(0,0,0,0.25); padding:20px 20px 16px;
        animation:reportPopIn .18s ease;
    }
    .icc-header { display:flex; align-items:flex-start; gap:12px; }
    .icc-icon {
        flex-shrink:0; width:36px; height:36px; border-radius:10px;
        background:rgba(248,113,113,0.14); border:1px solid rgba(248,113,113,0.35);
        display:flex; align-items:center; justify-content:center; color:#b91c1c; font-size:1rem;
    }
    .icc-title { color:#0f172a; font-size:0.94rem; font-weight:800; letter-spacing:-0.01em; margin:0 0 3px; }
    .icc-sub   { color:#94a3b8; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; margin:0; }
    .icc-desc  { color:#334155; font-size:0.76rem; line-height:1.55; margin:13px 0 14px; }
    .icc-field label {
        display:block; color:#94a3b8; font-size:0.62rem; font-weight:700;
        text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px;
    }
    .icc-field textarea {
        width:100%; resize:none; padding:9px 12px; border-radius:9px;
        background:rgba(15,23,42,0.05); border:1px solid rgba(15,23,42,0.14);
        color:#1e293b; font-size:0.8rem; line-height:1.45; outline:none;
        box-sizing:border-box; font-family:inherit; transition:all 0.15s ease;
    }
    .icc-field textarea:focus { border-color:#f87171; background:rgba(15,23,42,0.08); }
    .icc-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
    .icc-btn {
        padding:9px 16px; border-radius:9px; font-weight:700; font-size:0.72rem;
        cursor:pointer; border:1px solid transparent; transition:all 0.15s ease;
    }
    .icc-btn-secondary { background:rgba(15,23,42,0.06); border-color:rgba(15,23,42,0.16); color:#1e293b; }
    .icc-btn-secondary:hover { background:rgba(15,23,42,0.12); }
    .icc-btn-danger { background:#dc2626; color:#fff; box-shadow:0 6px 16px rgba(220,38,38,0.3); }
    .icc-btn-danger:hover { background:#b91c1c; }
    .icc-btn-danger:disabled { opacity:0.55; cursor:not-allowed; box-shadow:none; }

    /* Photo lightbox — view evidence full-size without leaving the case file */
    #photoLightbox { display:none; position:fixed; inset:0; z-index:110; background:rgba(0,0,0,0.88); align-items:center; justify-content:center; cursor:zoom-out; animation:reportPopIn .18s ease; }
    #photoLightbox img { max-width:92vw; max-height:88vh; border-radius:12px; box-shadow:0 30px 70px rgba(0,0,0,0.6); }
    #photoLightbox button { position:absolute; top:22px; right:26px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#fff; border-radius:8px; padding:6px 12px; font-size:1rem; cursor:pointer; }

    /* Success toast popup — replaces plain browser alert() for save confirmations */
    @keyframes toastSlideIn { from { opacity:0; transform:translate(-50%,-14px); } to { opacity:1; transform:translate(-50%,0); } }
    @keyframes toastSlideOut { from { opacity:1; transform:translate(-50%,0); } to { opacity:0; transform:translate(-50%,-14px); } }
    #appToast {
        display:none; position:fixed; top:26px; left:50%; z-index:200;
        max-width:min(420px, 90vw); align-items:center; gap:12px;
        background:linear-gradient(135deg, rgba(6,20,14,0.97), rgba(10,26,18,0.97));
        border:1.5px solid rgba(16,185,129,0.45); border-radius:14px;
        padding:14px 18px; box-shadow:0 20px 45px rgba(0,0,0,0.5), 0 0 0 1px rgba(16,185,129,0.08);
        animation:toastSlideIn .25s ease;
    }
    #appToast.hide { animation:toastSlideOut .2s ease forwards; }
    #appToast .toast-icon {
        flex-shrink:0; width:30px; height:30px; border-radius:999px; background:rgba(16,185,129,0.18);
        border:1.5px solid #10b981; color:#34d399; display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:900;
    }
    #appToast .toast-text { color:#e2e8f0; font-size:0.82rem; font-weight:700; line-height:1.4; }
    #appToast.error .toast-icon { background:rgba(239,68,68,0.18); border-color:#ef4444; color:#f87171; }
    #appToast.error { border-color:rgba(239,68,68,0.45); }
    #appToast button.toast-close { flex-shrink:0; margin-left:auto; background:none; border:none; color:#64748b; cursor:pointer; font-size:0.95rem; line-height:1; padding:2px; }
    #appToast button.toast-close:hover { color:#e2e8f0; }
</style>
</style>

<!-- ═══ VIEW / CASE REPORT MODAL ═══ -->
<div id="viewModal" class="hidden fixed inset-0 z-[100]" style="background:rgba(15,23,42,0.6);backdrop-filter:blur(6px);">
    <div id="viewModalBox" style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:20px;
        overflow:hidden;
        padding:26px;
        width:min(860px,96vw);
        max-height:90vh;
        display:flex;
        flex-direction:column;
        z-index:101;
        box-shadow:0 30px 70px rgba(0,0,0,0.4);
        animation:reportPopIn .22s ease;
    ">
        <!-- Header -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div id="view_farmer_avatar" style="width:46px;height:46px;border-radius:12px;background:rgba(16,185,129,0.15);border:2px solid rgba(16,185,129,0.4);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:900;color:#10b981;flex-shrink:0;"></div>
                <div>
                    <p style="color:#10b981;font-size:0.58rem;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;margin-bottom:3px;">Farmer's Report</p>
                    <h3 id="view_ref" style="color:#0f172a;font-size:1.1rem;font-weight:900;margin:0 0 3px;"></h3>
                    <p id="view_date" style="color:#475569;font-size:0.65rem;font-weight:700;text-transform:uppercase;"></p>
                </div>
            </div>
            <button onclick="closeModal()" style="background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.1);color:#64748b;border-radius:8px;padding:5px 10px;cursor:pointer;font-size:1rem;line-height:1;flex-shrink:0;margin-left:8px;">&#10005;</button>
        </div>

        <div class="modal-flex-body">
        <!-- ══ LEFT: scrollable case details ══ -->
        <div class="modal-left-col">

            <!-- Farmer + Barangay + Severity chips -->
            <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:10px;padding:8px 14px;flex:1;min-width:120px;">
                    <div style="color:#475569;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:2px;">Farmer</div>
                    <div id="view_farmer_display" style="color:#0f172a;font-size:0.85rem;font-weight:900;"></div>
                </div>
                <div style="background:rgba(15,23,42,0.04);border:1px solid rgba(15,23,42,0.08);border-radius:10px;padding:8px 14px;flex:1;min-width:100px;">
                    <div style="color:#475569;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:2px;">Barangay</div>
                    <div id="view_brgy_display" style="color:#0f172a;font-size:0.85rem;font-weight:900;"></div>
                </div>
                <div id="view_severity_chip" style="background:rgba(15,23,42,0.04);border:1px solid rgba(15,23,42,0.08);border-radius:10px;padding:8px 14px;min-width:80px;">
                    <div id="view_severity_title" style="color:#475569;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Severity</div>
                    <div id="view_severity_label" style="font-size:0.8rem;font-weight:900;text-transform:uppercase;letter-spacing:0.05em;"></div>
                </div>
            </div>

            <!-- Identified Disease — highlighted, with its reference description right below -->
            <div style="background:linear-gradient(135deg, rgba(16,185,129,0.20), rgba(5,150,105,0.07));border:1.5px solid rgba(16,185,129,0.45);border-radius:14px;padding:16px 18px;margin-bottom:14px;">
                <div style="color:#047857;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:6px;">🌽 Identified Disease</div>
                <div id="view_disease_name" style="color:#0f172a;font-size:1.35rem;font-weight:900;line-height:1.2;margin-bottom:8px;"></div>
                <div id="view_disease_description" style="color:#334155;font-size:0.8rem;line-height:1.65;"></div>

                <!-- Manual disease verification — only shown for "Other / Unidentified" reports
                     while the case is still Pending. Lets CASD pick the real disease from the
                     database so the report's disease info (name, description, treatment) gets
                     properly filled in before the case moves forward. -->
                <div id="view_reassign_disease_wrap" style="display:none;margin-top:12px;">
                    <button type="button" onclick="openDiseasePicker()"
                        style="width:100%;background:rgba(245,158,11,0.14);border:1.5px solid rgba(245,158,11,0.4);color:#92400e;font-size:0.68rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;padding:10px 14px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.15s ease;"
                        onmouseover="this.style.background='rgba(245,158,11,0.22)'" onmouseout="this.style.background='rgba(245,158,11,0.14)'">
                        🔎 Choose the Correct Disease
                    </button>
                    <p style="color:#78716c;font-size:0.58rem;font-weight:700;text-align:center;margin-top:6px;line-height:1.5;">Marked as "Other / Unidentified" — pick the correct disease from the list to confirm this report.</p>
                </div>
            </div>

            <!-- Photo Evidence — shown right under the disease so reviewers see it first -->
            <div id="view_photo_wrap" style="display:none;margin-bottom:14px;">
                <div style="color:#94a3b8;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:8px;">📸 Photo Evidence</div>
                <div id="view_photo_grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(110px, 1fr));gap:8px;"></div>
                <p style="color:#475569;font-size:0.6rem;font-weight:700;text-align:center;margin-top:5px;">Click a photo to enlarge</p>
            </div>
            <div id="view_no_photo" style="display:none;margin-bottom:14px;padding:10px 16px;background:rgba(15,23,42,0.02);border:1px dashed rgba(15,23,42,0.08);border-radius:12px;text-align:center;">
                <p style="color:#334155;font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;">No Photo Evidence Attached</p>
            </div>

            <!-- Growth Stage -->
            <div style="background:rgba(15,23,42,0.04);border:1px solid rgba(15,23,42,0.08);border-radius:12px;padding:12px 16px;margin-bottom:14px;">
                <div style="color:#10b981;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:4px;">Growth Stage</div>
                <div id="view_growth_full" style="color:#0f172a;font-size:1.1rem;font-weight:900;"></div>
            </div>

            <!-- Send Recommendation — condensed preview card. Tapping it opens the
                 dedicated popup (#messageModal) where the log and compose box live. -->
            <div id="view_chat_preview" onclick="openMessageModal()" style="cursor:pointer;background:rgba(15,23,42,0.03);border:1px solid rgba(15,23,42,0.08);border-radius:14px;padding:14px 16px;margin-bottom:14px;transition:border-color 0.15s ease,background 0.15s ease;" onmouseover="this.style.borderColor='rgba(165,180,252,0.4)'" onmouseout="this.style.borderColor='rgba(15,23,42,0.08)'">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <div style="width:34px;height:34px;border-radius:999px;background:rgba(165,180,252,0.15);border:1px solid rgba(165,180,252,0.3);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">💊</div>
                        <div style="min-width:0;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="color:#4338ca;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;">Recommendation</span>
                                <span id="view_rec_sent_badge" style="display:none;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.35);color:#065f46;font-size:0.52rem;font-weight:900;text-transform:uppercase;letter-spacing:0.06em;padding:2px 7px;border-radius:999px;flex-shrink:0;">Sent ✓</span>
                            </div>
                            <p id="view_chat_preview_text" style="color:#94a3b8;font-size:0.75rem;margin:4px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px;">No recommendation sent yet — tap to send one.</p>
                        </div>
                    </div>
                    <span style="color:#475569;font-size:0.85rem;flex-shrink:0;">&#8250;</span>
                </div>
            </div>

            <!-- Hidden form: send recommendation to farmer (adds a staff bubble) -->
            <form action="" method="POST" id="view_rec_form" style="display:none;">
                <input type="hidden" name="case_id_hidden" id="view_rec_case_id">
                <input type="hidden" name="recommendation_text" id="view_rec_hidden_text">
                <input type="hidden" name="return_tab" id="view_rec_return_tab">
                <input type="hidden" name="disease_name" id="view_rec_disease_name">
                <input type="hidden" name="disease_description" id="view_rec_disease_description">
                <input type="hidden" name="case_description" id="view_rec_case_description">
                <input type="hidden" name="has_recommendation" id="view_rec_has_recommendation" value="0">
                <input type="hidden" name="follow_up_date" id="view_rec_followup_date" value="">
                <input type="hidden" name="clear_follow_up_date" id="view_rec_clear_followup" value="0">
                <input type="hidden" name="send_recommendation" value="1">
            </form>

            <!-- GPS Location -->
            <div id="view_location_wrap" style="display:none;background:rgba(15,23,42,0.04);border:1px solid rgba(15,23,42,0.08);border-radius:12px;padding:12px 16px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div>
                        <div style="color:#10b981;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:4px;">📍 GPS Location</div>
                        <div id="view_location_text" style="color:#0f172a;font-size:0.85rem;font-weight:900;"></div>
                        <div id="view_location_accuracy" style="color:#475569;font-size:0.62rem;font-weight:700;margin-top:2px;"></div>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <button type="button" id="view_gps_copy_btn" onclick="copyGpsCoords()" class="gps-copy-btn">Copy</button>
                        <a id="view_map_link" href="#" target="_blank" rel="noopener"
                           style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#047857;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;padding:8px 12px;border-radius:8px;text-decoration:none;white-space:nowrap;">
                            View on Map
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reporter's Field Observations -->
            <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                <div style="color:#10b981;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:6px;">Field Observations</div>
                <div id="view_desc" style="color:#334155;font-size:0.82rem;font-style:italic;line-height:1.6;"></div>
            </div>

            <!-- Existing Remarks display -->
            <div id="view_remarks_wrap" style="display:none;background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.18);border-radius:12px;padding:12px 16px;margin-bottom:14px;">
                <div style="color:#f59e0b;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:6px;">Office Notes</div>
                <div id="view_remarks_text" style="color:#334155;font-size:0.82rem;line-height:1.6;"></div>
            </div>

            <!-- Export (verified / resolved only) -->
            <button id="view_export_btn" onclick="exportSingleReport()"
                style="display:none;width:100%;background:linear-gradient(135deg,#059669,#047857);color:#fff;font-size:0.7rem;font-weight:900;padding:13px;border-radius:10px;border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;box-shadow:0 8px 20px rgba(5,150,105,0.35);margin-bottom:4px;align-items:center;justify-content:center;gap:8px;">
                <svg style="width:14px;height:14px;display:inline-block;vertical-align:-2px;margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H8a2 2 0 01-2-2V5a2 2 0 012-2h6l6 6v11a2 2 0 01-2 2z"/></svg>
                Export This Report (PDF)
            </button>
        </div>

        <!-- ══ RIGHT: always-visible quick status panel ══ -->
        <div class="modal-right-col">
            <p style="color:#475569;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.15em;margin-bottom:12px;">Update Report Status</p>
            <form action="" method="POST" id="view_update_form">
                <input type="hidden" name="update_case" value="1">
                <input type="hidden" name="case_id_hidden" id="view_case_id_hidden">
                <input type="hidden" name="status" id="view_status">

                <div class="status-quick-select" id="status_quick_select">
                    <label class="status-pill" data-value="pending"  onclick="selectStatusPill('pending')">Pending<span class="dot"></span></label>
                    <label class="status-pill" data-value="verified" onclick="selectStatusPill('verified')">Verified<span class="dot"></span></label>
                    <label class="status-pill" data-value="resolved" onclick="selectStatusPill('resolved')">Resolved<span class="dot"></span></label>
                    <label class="status-pill" data-value="rejected" onclick="selectStatusPill('rejected')">Rejected<span class="dot"></span></label>
                </div>
                <p id="status_flow_hint" class="status-flow-hint"></p>

                <!-- Rejection reason — only shown/required when the reviewer actively
                     picks "Rejected" for a case that isn't already rejected (see
                     selectStatusPill's isUserAction flag). Its value is copied into
                     the hidden "remarks" field on submit, so it's saved as this
                     report's Office Notes / rejection reason. -->
                <div id="view_rejection_reason_wrap" style="display:none;margin-top:10px;">
                    <label for="view_rejection_reason" style="display:block;color:#b91c1c;font-size:0.6rem;font-weight:900;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:6px;">
                        Reason for Rejection <span style="color:#ef4444;">*</span>
                    </label>
                    <textarea id="view_rejection_reason" rows="3" oninput="updateSaveButtonState()"
                        placeholder="Explain why this report is being rejected (e.g. duplicate, invalid photo, unverifiable location)…"
                        style="width:100%;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,0.05);border:1.5px solid rgba(239,68,68,0.3);color:#1e293b;font-size:0.78rem;line-height:1.5;font-family:inherit;resize:vertical;box-sizing:border-box;outline:none;"></textarea>
                    <p style="color:#94a3b8;font-size:0.56rem;font-weight:700;margin-top:5px;line-height:1.4;">This is saved as the report's Office Notes and required before you can reject.</p>
                </div>

                <!-- Remarks are shown read-only in the left column (Reviewer Remarks on
                     File); this hidden field just carries that value through unchanged
                     when the status is saved, so a status update never wipes it out.
                     Exception: when rejecting, reviewHandleFormSubmission() overwrites
                     it with the typed rejection reason above. -->
                <input type="hidden" name="remarks" id="view_remarks">

                <!-- Severity override — only meaningful for "Other / Unidentified" reports
                     while still Pending (see the editable dropdown in the "Severity" chip,
                     left column). Mirrored here so it rides along with the status save.
                     The Infection Percentage Rate chip (Common Rust / Northern Leaf Blight /
                     Gray Leaf Spot / Healthy Corn) is read-only and sourced from the database
                     — there is no equivalent editable field for it. -->
                <input type="hidden" name="severity_override" id="view_severity_hidden" value="">

                <div class="sidebar-save-wrap">
                    <button type="button" id="view_save_status_btn" onclick="reviewShowStatusConfirm()"
                        style="width:100%;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:0.7rem;font-weight:900;padding:13px;border-radius:10px;border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;box-shadow:0 8px 20px rgba(16,185,129,0.35);transition:opacity 0.15s ease;">
                        Save Status Update
                    </button>
                </div>
            </form>
        </div>
        </div>

        <!-- ═══ STATUS-CHANGE CONFIRMATION OVERLAY ═══ -->
        <div id="reviewConfirmMode"
             class="hidden absolute inset-0 z-20 flex-col items-center justify-center gap-5"
             style="background: rgba(15,23,42,0.87); backdrop-filter: blur(6px); border-radius:20px;">
            <div class="w-16 h-16 bg-emerald-500 rounded-3xl flex items-center justify-center shadow-xl"
                 style="box-shadow: 0 20px 40px -10px rgba(16,185,129,0.4);">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="text-center px-8">
                <p class="text-white font-black text-xl tracking-tighter mb-1">Save Changes?</p>
                <p id="confirm_status_msg" class="text-slate-300 text-[10px] font-bold uppercase tracking-widest">This change will be saved right away.</p>
            </div>
            <div class="flex gap-3 justify-center">
                <button type="button" onclick="reviewHandleFormSubmission()"
                        class="px-8 py-3 bg-emerald-500 hover:bg-emerald-400 text-white rounded-2xl font-black text-xs uppercase tracking-widest transition-all active:scale-95 shadow-lg"
                        style="box-shadow: 0 8px 20px -6px rgba(16,185,129,0.5);">
                    Yes, Save
                </button>
                <button type="button" onclick="reviewBackToEdit()"
                        class="px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest transition-all active:scale-95 text-white"
                        style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.15);"
                        onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MESSENGER POPUP — dedicated chat window for the farmer conversation ═══ -->
<div id="messageModal" class="hidden fixed inset-0 z-[120]" style="background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);" onclick="if(event.target===this) closeMessageModal()">
    <div id="messageModalBox" style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:22px;
        width:min(560px,96vw);
        height:min(760px,92vh);
        display:flex;
        flex-direction:column;
        overflow:hidden;
        z-index:121;
        box-shadow:0 30px 70px rgba(0,0,0,0.4);
        animation:reportPopIn .22s ease;
    ">
        <!-- Messenger header -->
        <div style="flex-shrink:0;display:flex;align-items:center;gap:12px;padding:18px 22px;background:linear-gradient(135deg,rgba(165,180,252,0.14),rgba(99,102,241,0.05));border-bottom:1px solid rgba(15,23,42,0.08);">
            <div id="msg_farmer_avatar" style="width:44px;height:44px;border-radius:999px;background:rgba(165,180,252,0.18);border:2px solid rgba(165,180,252,0.4);display:flex;align-items:center;justify-content:center;font-size:1.05rem;font-weight:900;color:#4338ca;flex-shrink:0;overflow:hidden;"></div>
            <div style="min-width:0;flex:1;">
                <div id="msg_farmer_name" style="color:#0f172a;font-size:1rem;font-weight:900;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span id="msg_farmer_brgy" style="color:#64748b;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;"></span>
                    <span id="msg_header_badge" style="display:none;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.35);color:#065f46;font-size:0.55rem;font-weight:900;text-transform:uppercase;letter-spacing:0.06em;padding:2px 7px;border-radius:999px;">Sent ✓</span>
                </div>
            </div>
            <button onclick="closeMessageModal()" style="background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.1);color:#64748b;border-radius:9px;padding:6px 12px;cursor:pointer;font-size:1.1rem;line-height:1;flex-shrink:0;">&#10005;</button>
        </div>

        <!-- Field Inspection Scheduler — pinned at the top of the popup, directly
             under the header and above the scrollable chat thread, for verified/
             resolved cases only. Sends a highlighted bubble into the chat thread
             and saves the picked date to disease_cases.follow_up_date. -->
        <div id="view_inspection_section" style="display:none;flex-shrink:0;border-bottom:1px solid rgba(15,23,42,0.06);padding:14px 20px;">
            <!-- Upper part: shows only when an inspection is already scheduled/sent. Hides the
                 "Schedule field inspection" toggle below so reviewers aren't prompted to
                 schedule a second one on top of an active one. -->
            <div id="view_inspection_next_wrap" style="display:none;align-items:center;gap:8px;margin-bottom:10px;">
                <span class="inspection-next-chip">Next inspection: <span id="view_inspection_next_text"></span></span>
                <button type="button" onclick="openInspectionPanelForEdit()" class="inspection-edit-link">Adjust</button>
                <button type="button" onclick="cancelFieldInspection()" class="inspection-cancel-x" title="Cancel this scheduled inspection">&#10005;</button>
            </div>
            <button type="button" onclick="toggleInspectionPanel()" id="msg_inspection_toggle" class="inspection-toggle-btn">
                <span>Schedule field inspection</span> <span style="font-size:0.75rem;">▾</span>
            </button>
            <div id="view_inspection_panel" class="inspection-panel">
                <div class="insp-row">
                    <div>
                        <label>Inspection Date</label>
                        <input type="date" id="view_inspection_date" oninput="updateInspectionBtnState()">
                    </div>
                    <div>
                        <label>Inspection Time</label>
                        <select id="view_inspection_time" onchange="updateInspectionBtnState()">
                            <option value="">Select a time…</option>
                        </select>
                    </div>
                </div>
                <button type="button" id="view_inspection_confirm_btn" class="inspection-confirm-btn" onclick="scheduleFieldInspection()" disabled>
                    Schedule &amp; Notify Farmer
                </button>
            </div>
        </div>

        <!-- Message thread (scrollable) -->
        <div id="view_chat_thread" style="flex:1;display:flex;flex-direction:column;gap:12px;overflow-y:auto;padding:20px;scrollbar-width:thin;"></div>
        <p id="view_chat_empty" style="display:none;color:#475569;font-size:0.78rem;font-style:italic;text-align:center;padding:0 20px 14px;">No recommendation sent yet for this case.</p>
        <p id="view_rec_sent_note" style="display:none;color:#475569;font-size:0.64rem;font-weight:700;text-align:center;padding:0 20px 10px;flex-shrink:0;"></p>

        <!-- Suggested replies (from the diseases table's recommended_treatment) — the "+" section, now below the pinned schedule -->
        <div style="flex-shrink:0;border-top:1px solid rgba(15,23,42,0.06);padding:14px 20px 0;">
            <button type="button" onclick="toggleSuggested()" id="msg_suggested_toggle" style="background:rgba(165,180,252,0.08);border:1px solid rgba(165,180,252,0.2);color:#4338ca;font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;cursor:pointer;padding:8px 12px;border-radius:10px;margin:0 0 10px;display:flex;align-items:center;gap:6px;width:100%;justify-content:space-between;">
                <span>💊 Suggested treatment replies</span> <span style="font-size:0.75rem;">▾</span>
            </button>
            <div id="view_rec_instructions" style="display:none;flex-direction:column;gap:6px;margin-bottom:8px;max-height:200px;overflow-y:auto;scrollbar-width:thin;"></div>
            <button type="button" onclick="togglePrevention()" style="background:none;border:none;color:#047857;font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;cursor:pointer;padding:0 0 10px;display:none;" id="msg_prevention_toggle">+ Show prevention tips</button>
            <div id="view_rec_prevention" style="display:none;margin-bottom:10px;padding:12px 14px;background:rgba(15,23,42,0.03);border:1px dashed rgba(15,23,42,0.1);border-radius:10px;color:#94a3b8;font-size:0.78rem;line-height:1.55;"></div>
        </div>

        <!-- Messenger-style bottom input bar -->
        <div style="flex-shrink:0;display:flex;align-items:flex-end;gap:10px;padding:14px 20px 18px;background:rgba(15,23,42,0.02);border-top:1px solid rgba(15,23,42,0.06);">
            <textarea id="view_rec_text" rows="1" oninput="autoGrowMsgBox(this);updateRecSendState()"
                style="flex:1;resize:none;max-height:130px;padding:12px 16px;border-radius:22px;background:rgba(15,23,42,0.07);border:1px solid rgba(15,23,42,0.14);color:#1e293b;font-size:0.86rem;line-height:1.45;outline:none;box-sizing:border-box;font-family:inherit;"
                placeholder="Type a message or pick a suggested reply…"></textarea>

            <button type="button" id="view_rec_cancel_btn" onclick="cancelRecommendationCompose()" title="Discard this message" disabled
                style="flex-shrink:0;width:44px;height:44px;border-radius:999px;background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.14);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.05rem;transition:opacity 0.15s ease;">
                &#10005;
            </button>

            <button type="button" id="view_rec_send_btn" onclick="sendRecommendation()" title="Send this message to the farmer"
                style="flex-shrink:0;width:44px;height:44px;border-radius:999px;background:linear-gradient(135deg,#0d9488,#0f766e);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.15rem;box-shadow:0 6px 16px rgba(13,148,136,0.4);">
                ➤
            </button>
        </div>
        <p style="flex-shrink:0;color:#334155;font-size:0.6rem;font-weight:600;text-align:center;padding:0 20px 12px;line-height:1.4;">Sends this recommendation straight to the farmer's case file.</p>

        <!-- ═══ CANCEL-INSPECTION CONFIRMATION ═══
             An in-app dialog card (not a native prompt/confirm) that slides over the
             messenger popup. Both the pinned "Next inspection" chip's ✕ and the
             in-thread "Cancel Inspection" button open this. Wording is explicit about
             what happens ("Confirm Cancellation" / "Go Back") rather than a bare
             "Cancel this inspection?" question paired with a "Cancel" action, which
             read as ambiguous about which button actually cancels what. -->
        <div id="inspectionCancelOverlay">
            <div class="icc-card">
                <div class="icc-header">
                    <div class="icc-icon">&#9888;</div>
                    <div>
                        <p class="icc-title">Confirm Inspection Cancellation</p>
                        <p class="icc-sub" id="inspectionCancelDateText"></p>
                    </div>
                </div>
                <p class="icc-desc">This will notify the farmer that the visit is off and clear it from the case file. You can schedule a new inspection afterward.</p>
                <div class="icc-field">
                    <label for="inspectionCancelReason">Reason (optional, shown to the farmer)</label>
                    <textarea id="inspectionCancelReason" rows="3" maxlength="200"
                        placeholder="e.g., Rescheduling due to weather"></textarea>
                </div>
                <div class="icc-actions">
                    <button type="button" onclick="closeCancelInspectionOverlay()" class="icc-btn icc-btn-secondary">
                        Go Back
                    </button>
                    <button type="button" id="inspectionCancelConfirmBtn" onclick="confirmCancelInspection()" class="icc-btn icc-btn-danger">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ PHOTO LIGHTBOX ═══ -->
<div id="photoLightbox" onclick="closeLightbox()">
    <button onclick="event.stopPropagation();closeLightbox()">&#10005;</button>
    <img id="photoLightboxImg" src="" alt="Evidence photo, full size">
</div>


<!-- Hidden form: reassign an "Other / Unidentified" case to a real disease from the DB -->
<form action="" method="POST" id="view_reassign_form" style="display:none;">
    <input type="hidden" name="reassign_disease" value="1">
    <input type="hidden" name="case_id_hidden" id="view_reassign_case_id">
    <input type="hidden" name="new_disease_id" id="view_reassign_new_disease_id">
</form>

<!-- ═══ DISEASE PICKER — CASD manually verifies an "Other / Unidentified" report ═══ -->
<div id="diseasePickerModal" class="hidden fixed inset-0 z-[130]" style="background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);" onclick="if(event.target===this) closeDiseasePicker()">
    <div style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:20px;
        padding:24px;
        width:min(480px,94vw);
        max-height:82vh;
        display:flex;
        flex-direction:column;
        z-index:131;
        box-shadow:0 30px 70px rgba(0,0,0,0.4);
        animation:reportPopIn .22s ease;
    ">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;flex-shrink:0;">
            <div>
                <p style="color:#92400e;font-size:0.58rem;font-weight:900;letter-spacing:0.15em;text-transform:uppercase;margin-bottom:3px;">Confirm the Disease</p>
                <h3 style="color:#0f172a;font-size:1.05rem;font-weight:900;margin:0;">Choose the Correct Disease</h3>
            </div>
            <button type="button" onclick="closeDiseasePicker()" style="background:rgba(15,23,42,0.06);border:1px solid rgba(15,23,42,0.1);color:#64748b;border-radius:8px;padding:5px 10px;cursor:pointer;font-size:1rem;line-height:1;flex-shrink:0;margin-left:8px;">&#10005;</button>
        </div>
        <p style="color:#78716c;font-size:0.65rem;font-weight:700;margin-bottom:6px;line-height:1.5;">Select up to 3 diseases below — they'll be combined into this report's disease info (name, description, treatment) as one file, clearing its "Other / Unidentified" status.</p>
        <p id="diseasePickerCount" style="color:#92400e;font-size:0.62rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;">0 / 3 selected</p>
        <div id="diseasePickerList" style="overflow-y:auto;flex-grow:1;scrollbar-width:thin;"></div>
        <div style="flex-shrink:0;margin-top:14px;padding-top:14px;border-top:1px solid rgba(15,23,42,0.08);">
            <button type="button" id="diseasePickerConfirmBtn" onclick="confirmReassignDisease()" disabled
                style="width:100%;background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:#fff;font-weight:900;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;padding:12px 14px;border-radius:10px;cursor:pointer;opacity:0.4;transition:opacity 0.15s ease;">
                Confirm Selection
            </button>
        </div>
    </div>
</div>

<!-- ═══ SUCCESS / ERROR TOAST — replaces plain alert() popups for save confirmations ═══ -->
<div id="appToast" role="status">
    <span class="toast-icon">&#10003;</span>
    <span class="toast-text" id="appToastText"></span>
    <button type="button" class="toast-close" onclick="hideAppToast()">&#10005;</button>
</div>

<script>
// One-way status flow (kept identical to the server-side $STATUS_FLOW rules)
const STATUS_FLOW = <?= json_encode($STATUS_FLOW) ?>;
const STATUS_LABELS = { pending: 'Pending', verified: 'Verified', resolved: 'Resolved', rejected: 'Rejected' };

// Every real disease (excludes "Other / Unidentified") — powers the manual
// verification picker for reports still marked Other / Unidentified.
const ALL_DISEASES = <?= json_encode(array_values($allDiseasesForPicker)) ?>;

// These 4 diseases track an Infection Percentage Rate instead of a severity
// level — the "Severity" chip swaps to "Infection Rate" for these, editable
// only while the report is still Pending.
const INFECTION_PCT_DISEASES = ['Common Rust', 'Northern Leaf Blight', 'Gray Leaf Spot', 'Healthy Corn'];

// ── SEVERITY CHIP STYLES FOR VIEW MODAL ──
const sevStyles = {
    low:      { bg:'#d1fae5', border:'#6ee7b7', color:'#065f46' },
    moderate: { bg:'#fef3c7', border:'#fcd34d', color:'#92400e' },
    high:     { bg:'#fee2e2', border:'#fca5a5', color:'#991b1b' },
    critical: { bg:'#fce7f3', border:'#f9a8d4', color:'#9d174d' },
};

function openViewModal(data) {
    window._currentViewData = data; // stash for single-report PDF export

    document.getElementById('view_case_id_hidden').value = data.case_id;
    document.getElementById('view_ref').innerText         = "CASE: " + data.reference_id;
    document.getElementById('view_date').innerText        = "LOGGED: " + data.report_date;
    document.getElementById('view_growth_full').innerText = data.growth_stage || '—';

    // Identified disease + its reference description
    document.getElementById('view_disease_name').innerText        = data.disease_name || '— Unidentified —';
    document.getElementById('view_disease_description').innerText = data.disease_description || 'No description on file for this disease.';

    const farmerName = data.farmer_name || '— Unassigned —';
    document.getElementById('view_farmer_display').innerText = farmerName;
    document.getElementById('view_brgy_display').innerText   = data.brgy_name || '—';

    // Avatar
    const avatarEl = document.getElementById('view_farmer_avatar');
    if (data.farmer_photo) {
        avatarEl.innerHTML = `<img src="uploads/${data.farmer_photo}" alt="${farmerName}"
            style="width:100%;height:100%;object-fit:cover;border-radius:10px;"
            onerror="this.parentElement.innerHTML='${farmerName.charAt(0).toUpperCase()}'">`;
        avatarEl.style.padding  = '0';
        avatarEl.style.overflow = 'hidden';
    } else {
        avatarEl.innerHTML      = farmerName.charAt(0).toUpperCase();
        avatarEl.style.padding  = '';
        avatarEl.style.overflow = '';
    }

    document.getElementById('view_desc').innerText   = data.description || 'No field observations recorded.';
    document.getElementById('view_rec_disease_name').value        = data.disease_name || '';
    document.getElementById('view_rec_disease_description').value = data.disease_description || '';
    document.getElementById('view_rec_case_description').value    = data.description   || '';
    selectStatusPill(data.status || 'pending', false); // false = init load, not a reviewer click — don't pop the rejection-reason box for an already-rejected case
    applyStatusFlow(data.status || 'pending');
    reviewBackToEdit(); // make sure a stale confirmation overlay isn't left open from a previous case

    // ── Disease recommendation (sourced from the diseases table) ──
    // Previously-sent text (if any) is used to restore which instructions were picked;
    // otherwise nothing is pre-selected and the reviewer chooses per farmer.
    renderRecInstructions(data.recommended_treatment, data.recommendation_text);

    const preventionEl = document.getElementById('view_rec_prevention');
    preventionEl.innerText = data.prevention_measures || 'No prevention notes on file.';
    preventionEl.style.display = 'none';
    document.getElementById('msg_prevention_toggle').textContent = '+ Show prevention tips';

    const sentBadge   = document.getElementById('view_rec_sent_badge');
    const sentNote    = document.getElementById('view_rec_sent_note');
    window._recAlreadySent = (data.recommendation_sent == 1);
    if (window._recAlreadySent) {
        sentBadge.style.display = 'inline-block';
        sentNote.style.display  = 'block';
        sentNote.innerText      = data.recommendation_sent_at ? `Last sent: ${data.recommendation_sent_at}` : 'Already sent to the farmer.';
    } else {
        sentBadge.style.display = 'none';
        sentNote.style.display  = 'none';
    }
    // Reset the chat input box's height (its value is already set by renderRecInstructions above)
    document.getElementById('view_rec_text').style.height = 'auto';
    updateRecSendState();

    // Remarks
    const existingRemarks = data.remarks || '';
    document.getElementById('view_remarks').value = existingRemarks;
    const remarksWrap = document.getElementById('view_remarks_wrap');
    if (existingRemarks.trim()) {
        document.getElementById('view_remarks_text').innerText = existingRemarks;
        remarksWrap.style.display = 'block';
    } else {
        remarksWrap.style.display = 'none';
    }

    // Conversation thread — every sent recommendation and logged farmer reply, as chat bubbles.
    // Rendered now (into the hidden messenger popup) so it's ready the instant it's opened.
    renderChatThread(data.messages || []);

    // Preview card — last message snippet + badge, shown on the case file itself
    const msgs = data.messages || [];
    const previewEl = document.getElementById('view_chat_preview_text');
    if (msgs.length > 0) {
        const last = msgs[msgs.length - 1];
        const text = last.message.length > 60 ? last.message.slice(0, 60) + '…' : last.message;
        previewEl.innerText = 'Sent: ' + text;
    } else {
        previewEl.innerText = 'No recommendation sent yet — tap to send one.';
    }
    document.getElementById('msg_header_badge').style.display = (data.recommendation_sent == 1) ? 'inline-block' : 'none';

    // Recommendation card — only relevant once a case has been verified (or
    // resolved). A still-pending case has no confirmed disease yet, so there's
    // nothing to recommend — hide the whole card rather than let it open a
    // message modal that will just block the send.
    const chatPreview = document.getElementById('view_chat_preview');
    chatPreview.style.display = (data.status === 'verified' || data.status === 'resolved') ? 'block' : 'none';

    // Field Inspection scheduler — only for verified/resolved cases (mirrors the
    // recommendation card above). Reset the picker each time a case is opened,
    // and restore the chip if a follow-up/inspection date is already on file.
    const inspSection = document.getElementById('view_inspection_section');
    inspSection.style.display = (data.status === 'verified' || data.status === 'resolved') ? 'block' : 'none';
    document.getElementById('view_inspection_panel').style.display = 'none';
    document.getElementById('msg_inspection_toggle').innerHTML = 'Schedule field inspection <span style="font-size:0.75rem;">▾</span>';
    const inspDateInput = document.getElementById('view_inspection_date');
    inspDateInput.value = '';
    inspDateInput.min = todayIsoDate(); // can't schedule an inspection in the past
    populateInspectionTimeOptions();
    document.getElementById('view_inspection_confirm_btn').disabled = true;
    document.getElementById('view_inspection_confirm_btn').textContent = 'Schedule & Notify Farmer';
    document.getElementById('view_rec_clear_followup').value = '0';
    window._inspectionEditMode = false;
    const nextWrap    = document.getElementById('view_inspection_next_wrap');
    const inspToggle  = document.getElementById('msg_inspection_toggle');
    window._currentFollowUpDate = data.follow_up_date || null;
    if (data.follow_up_date) {
        // An inspection is already scheduled/sent — show the "Next inspection" chip up
        // top and hide the schedule toggle/date picker so it can't be double-booked.
        document.getElementById('view_inspection_next_text').innerText = data.follow_up_date;
        nextWrap.style.display   = 'flex';
        inspToggle.style.display = 'none';
    } else {
        // No active inspection (none sent yet, or the last one was cancelled) — hide the
        // chip and bring the "Schedule field inspection" date/time picker back.
        nextWrap.style.display   = 'none';
        inspToggle.style.display = 'flex';
    }

    // Photo evidence — photo_evidence may hold multiple comma-separated filenames
    const photoWrap   = document.getElementById('view_photo_wrap');
    const noPhotoWrap = document.getElementById('view_no_photo');
    const photoGrid   = document.getElementById('view_photo_grid');
    const photoList   = (data.photo_evidence || '')
        .split(',')
        .map(f => f.trim())
        .filter(f => f.length > 0);

    if (photoList.length > 0) {
        photoGrid.innerHTML = photoList.map(filename => `
            <img src="uploads/${filename}" alt="Evidence Photo"
                 style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:12px;border:1px solid rgba(16,185,129,0.3);cursor:zoom-in;"
                 onclick="openLightbox('uploads/${filename}')"
                 onerror="this.style.display='none'"
                 title="Click to enlarge">
        `).join('');
        photoWrap.style.display   = 'block';
        noPhotoWrap.style.display = 'none';
    } else {
        photoGrid.innerHTML = '';
        photoWrap.style.display   = 'none';
        noPhotoWrap.style.display = 'block';
    }

    // GPS location
    const locWrap = document.getElementById('view_location_wrap');
    if (data.latitude != null && data.longitude != null && data.latitude !== '' && data.longitude !== '') {
        const lat = parseFloat(data.latitude), lng = parseFloat(data.longitude);
        document.getElementById('view_location_text').innerText     = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        document.getElementById('view_location_accuracy').innerText = data.gps_accuracy ? `±${parseFloat(data.gps_accuracy).toFixed(1)}m accuracy` : '';
        document.getElementById('view_map_link').href = `https://www.google.com/maps?q=${lat},${lng}`;
        window._currentGpsCoords = `${lat}, ${lng}`;
        const copyBtn = document.getElementById('view_gps_copy_btn');
        copyBtn.textContent = 'Copy';
        copyBtn.classList.remove('copied');
        locWrap.style.display = 'block';
    } else {
        locWrap.style.display = 'none';
    }

    // ── Severity chip ⇄ Infection Rate chip ──
    // Common Rust, Northern Leaf Blight, Gray Leaf Spot and Healthy Corn track an
    // Infection Percentage Rate — this is READ-ONLY here, sourced straight from the
    // database (populated elsewhere, e.g. the field scan/report itself), never typed
    // in by CASD from this screen.
    //
    // "Other / Unidentified" reports instead get an editable Severity dropdown
    // (Low / Moderate / High / Critical) — since there's no confirmed disease yet,
    // CASD can classify/correct the severity while verifying. Editable only while
    // the case is still Pending; every other disease just shows its severity as
    // plain, locked text (set once at submission).
    const sevChip     = document.getElementById('view_severity_chip');
    const sevTitle    = document.getElementById('view_severity_title');
    const sevLabel    = document.getElementById('view_severity_label');
    const isPendingCase       = (data.status === 'pending');
    const isInfectionDisease  = INFECTION_PCT_DISEASES.includes(data.disease_name);
    const isUnidentifiedCase  = (data.disease_name === 'Other / Unidentified');
    const severityHiddenInput = document.getElementById('view_severity_hidden');
    severityHiddenInput.value = ''; // only populated below when the dropdown is actually shown

    if (isInfectionDisease) {
        sevTitle.innerText = 'Infection Rate';
        sevChip.style.background  = 'rgba(16,185,129,0.1)';
        sevChip.style.borderColor = 'rgba(16,185,129,0.3)';

        const hasRate = (data.infection_percentage !== null && data.infection_percentage !== undefined && data.infection_percentage !== '');
        sevLabel.style.color = '#047857';
        sevLabel.innerText   = hasRate ? (parseFloat(data.infection_percentage).toFixed(2) + '%') : 'No data on file';
    } else if (isUnidentifiedCase && isPendingCase) {
        sevTitle.innerText = 'Severity';
        sevChip.style.background  = 'rgba(15,23,42,0.04)';
        sevChip.style.borderColor = 'rgba(15,23,42,0.08)';

        const currentSev = (data.severity || 'moderate').toLowerCase();
        const sevOptions = ['low', 'moderate', 'high', 'critical'];
        sevLabel.innerHTML = `<select id="view_severity_select"
                oninput="document.getElementById('view_severity_hidden').value=this.value; updateSaveButtonState();"
                onchange="document.getElementById('view_severity_hidden').value=this.value; updateSaveButtonState();"
                style="background:rgba(15,23,42,0.07);border:1px solid rgba(15,23,42,0.18);border-radius:6px;color:#1e293b;font-size:0.7rem;font-weight:900;text-transform:uppercase;padding:3px 6px;outline:none;">
            ${sevOptions.map(v => `<option value="${v}" ${v === currentSev ? 'selected' : ''}>${v.charAt(0).toUpperCase() + v.slice(1)}</option>`).join('')}
        </select>`;
        severityHiddenInput.value = currentSev;
    } else {
        sevTitle.innerText = 'Severity';
        const sev = (data.severity || 'moderate').toLowerCase();
        const s   = sevStyles[sev] || sevStyles.moderate;
        sevChip.style.background  = s.bg;
        sevChip.style.borderColor = s.border;
        sevLabel.style.color      = s.color;
        sevLabel.innerText        = sev.charAt(0).toUpperCase() + sev.slice(1);
    }

    // Baseline snapshot — used to detect "nothing actually changed" so the save
    // button can't fire an update that would just rewrite the same status/severity.
    // Must be set AFTER the severity chip block above (severityHiddenInput.value
    // is only finalized there) and AFTER selectStatusPill() was called earlier,
    // which is why this sits here rather than up near the top of the function.
    window._initialStatus   = data.status || 'pending';
    window._initialSeverity = severityHiddenInput.value; // '' when no severity dropdown is shown
    updateSaveButtonState();

    // ── Manual disease verification button — Other/Unidentified + Pending only ──
    document.getElementById('view_reassign_disease_wrap').style.display =
        (isUnidentifiedCase && isPendingCase) ? 'block' : 'none';

    // Export button — only for verified / resolved cases
    const exportBtn = document.getElementById('view_export_btn');
    exportBtn.style.display = (data.status === 'verified' || data.status === 'resolved') ? 'flex' : 'none';

    // Show modal + re-trigger animation
    document.getElementById('viewModal').classList.remove('hidden');
    const box = document.getElementById('viewModalBox');
    box.style.animation = 'none';
    box.offsetHeight;
    box.style.animation = 'reportPopIn .22s ease';
}

function closeModal() { document.getElementById('viewModal').classList.add('hidden'); }

// ── MANUAL DISEASE VERIFICATION — picker for "Other / Unidentified" reports ──
// Lets CASD tick up to 3 diseases (checklist, not instant-submit). Confirming
// sends all picked ids as one comma-separated list to the reassign_disease
// handler, which combines their name/description/treatment into ONE report
// (see combineDiseaseFields() in reports.php) instead of picking just one.
const MAX_REASSIGN_DISEASES = 3;
let _diseasePickerSelected = [];

function openDiseasePicker() {
    _diseasePickerSelected = [];
    renderDiseasePickerList();
    document.getElementById('diseasePickerModal').classList.remove('hidden');
}

function renderDiseasePickerList() {
    const listEl = document.getElementById('diseasePickerList');
    listEl.innerHTML = ALL_DISEASES.map(d => {
        const isSelected = _diseasePickerSelected.includes(d.disease_id);
        const isMaxedOut = !isSelected && _diseasePickerSelected.length >= MAX_REASSIGN_DISEASES;
        return `
        <button type="button" onclick="toggleReassignDisease(${d.disease_id})" class="rec-instruction-btn${isSelected ? ' selected' : ''}"
            style="width:100%;text-align:left;${isMaxedOut ? 'opacity:0.35;cursor:not-allowed;' : ''}" ${isMaxedOut ? 'disabled' : ''}>
            <div class="rec-check">${isSelected ? '✓' : '🌽'}</div>
            <div class="rec-instruction-text"><strong>${d.disease_name}</strong></div>
        </button>
    `;
    }).join('') || '<p style="color:#475569;font-size:0.7rem;font-weight:700;text-align:center;padding:20px;">No diseases on file.</p>';

    document.getElementById('diseasePickerCount').innerText = `${_diseasePickerSelected.length} / ${MAX_REASSIGN_DISEASES} selected`;
    document.getElementById('diseasePickerConfirmBtn').disabled = _diseasePickerSelected.length === 0;
    document.getElementById('diseasePickerConfirmBtn').style.opacity = _diseasePickerSelected.length === 0 ? '0.4' : '1';
}

function toggleReassignDisease(diseaseId) {
    const idx = _diseasePickerSelected.indexOf(diseaseId);
    if (idx !== -1) {
        _diseasePickerSelected.splice(idx, 1);
    } else {
        if (_diseasePickerSelected.length >= MAX_REASSIGN_DISEASES) return;
        _diseasePickerSelected.push(diseaseId);
    }
    renderDiseasePickerList();
}

function closeDiseasePicker() {
    document.getElementById('diseasePickerModal').classList.add('hidden');
}

function confirmReassignDisease() {
    const data = window._currentViewData;
    if (!data || _diseasePickerSelected.length === 0) return;
    document.getElementById('view_reassign_case_id').value        = data.case_id;
    document.getElementById('view_reassign_new_disease_id').value = _diseasePickerSelected.join(',');
    document.getElementById('view_reassign_form').submit();
}

// ── OPEN THE DEDICATED MESSENGER POPUP ──
function openMessageModal() {
    const data = window._currentViewData;
    if (!data) return;

    const farmerName = data.farmer_name || '— Unassigned —';
    document.getElementById('msg_farmer_name').innerText = farmerName;
    document.getElementById('msg_farmer_brgy').innerText = data.brgy_name || '—';

    const avatarEl = document.getElementById('msg_farmer_avatar');
    if (data.farmer_photo) {
        avatarEl.innerHTML = `<img src="uploads/${data.farmer_photo}" alt="${farmerName}"
            style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='${farmerName.charAt(0).toUpperCase()}'">`;
    } else {
        avatarEl.innerHTML = farmerName.charAt(0).toUpperCase();
    }

    // Prevention tips toggle is always available once a disease is on file
    document.getElementById('msg_prevention_toggle').style.display = 'block';

    // Always start with the cancel-inspection overlay closed, even if it was left
    // open from a previous case (shouldn't happen, but cheap to be sure).
    closeCancelInspectionOverlay();

    document.getElementById('messageModal').classList.remove('hidden');
    const box = document.getElementById('messageModalBox');
    box.style.animation = 'none';
    box.offsetHeight;
    box.style.animation = 'reportPopIn .22s ease';

    // Thread was already rendered by openViewModal — just scroll to the latest message
    const thread = document.getElementById('view_chat_thread');
    thread.scrollTop = thread.scrollHeight;
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.add('hidden');
}

// ── SUGGESTED REPLIES TOGGLE — collapsed by default to keep the popup chat-first ──
function toggleSuggested() {
    const el  = document.getElementById('view_rec_instructions');
    const btn = document.getElementById('msg_suggested_toggle');
    const show = el.style.display === 'none';
    el.style.display = show ? 'flex' : 'none';
    btn.innerHTML = show
        ? '💊 Suggested treatment replies <span style="font-size:0.7rem;">▴</span>'
        : '💊 Suggested treatment replies <span style="font-size:0.7rem;">▾</span>';
}

// ── AUTO-GROW THE MESSAGE TEXTAREA LIKE A CHAT INPUT ──
function autoGrowMsgBox(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 130) + 'px';
}

// ── QUICK STATUS PILLS — one click sets the hidden form field + active style ──
// isUserAction defaults to true (a real click); openViewModal passes false when
// it programmatically selects the case's current status on load, so re-opening
// an already-rejected case doesn't pop the "reason for rejection" box back open.
function selectStatusPill(value, isUserAction = true) {
    const pill = document.querySelector(`#status_quick_select .status-pill[data-value="${value}"]`);
    if (pill && pill.classList.contains('disabled')) return; // one-way flow: ignore illegal jumps
    document.getElementById('view_status').value = value;
    document.querySelectorAll('#status_quick_select .status-pill').forEach(p => {
        p.classList.toggle('active', p.dataset.value === value);
    });

    // Rejection reason box: prompt for a reason only when the reviewer is
    // actively choosing to reject a case right now.
    const reasonWrap = document.getElementById('view_rejection_reason_wrap');
    const showReason  = isUserAction && value === 'rejected';
    if (reasonWrap) {
        reasonWrap.style.display = showReason ? 'block' : 'none';
        if (!showReason) {
            document.getElementById('view_rejection_reason').value = '';
        }
    }

    updateSaveButtonState();
}

// ── SAVE BUTTON GATING — "Save Status Update" is disabled whenever the picked
// status and (where applicable) picked severity are identical to what the case
// already had when the modal was opened. Prevents a no-op save from re-writing
// updated_at / remarks for a case nothing was actually changed on. ──
function updateSaveButtonState() {
    const btn = document.getElementById('view_save_status_btn');
    if (!btn) return;
    const statusVal   = document.getElementById('view_status').value;
    const severityVal = document.getElementById('view_severity_hidden').value;
    const noChange = (statusVal === window._initialStatus) && (severityVal === (window._initialSeverity || ''));

    // Rejecting requires a reason to be typed in first.
    const reasonEl      = document.getElementById('view_rejection_reason');
    const missingReason = statusVal === 'rejected' && reasonEl && !reasonEl.value.trim();

    const disabled = noChange || missingReason;
    btn.disabled      = disabled;
    btn.style.opacity = disabled ? '0.4' : '1';
    btn.style.cursor  = disabled ? 'not-allowed' : 'pointer';
    btn.title         = missingReason ? 'Enter a reason for rejection first' : (noChange ? 'No changes to save' : '');
}

// ── STATUS-CHANGE CONFIRMATION OVERLAY ──
// "Save Status Update" no longer submits straight away — it opens this overlay
// first so a reviewer can't move a case from Pending → Verified (etc.) by accident.
function reviewShowStatusConfirm() {
    const statusVal = document.getElementById('view_status').value;
    if (!statusVal) { alert('Pick a status first.'); return; }

    // Rejecting a case requires a reason — belt-and-suspenders alongside the
    // disabled save button, in case that state ever goes stale.
    if (statusVal === 'rejected') {
        const reasonEl = document.getElementById('view_rejection_reason');
        if (!reasonEl || !reasonEl.value.trim()) {
            alert('Please enter a reason for rejecting this report before saving.');
            if (reasonEl) reasonEl.focus();
            return;
        }
    }

    // Belt-and-suspenders: even if the button's disabled state ever goes stale
    // (e.g. cached back/forward navigation), never open the confirm overlay for
    // a no-op save — status and severity both match what the case already had.
    const severityVal = document.getElementById('view_severity_hidden').value;
    const noChange = (statusVal === window._initialStatus) && (severityVal === (window._initialSeverity || ''));
    if (noChange) { return; }

    const current = window._currentCaseStatus || 'pending';
    const msgEl   = document.getElementById('confirm_status_msg');
    msgEl.textContent = (statusVal === current)
        ? `Saving remarks — status stays ${STATUS_LABELS[current]}.`
        : `Move this case from ${STATUS_LABELS[current]} to ${STATUS_LABELS[statusVal]}?`;

    const overlay = document.getElementById('reviewConfirmMode');
    if (!overlay) { console.error('reviewConfirmMode element not found in DOM'); return; }
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    overlay.style.display = 'flex'; // safety net in case the utility classes aren't in the loaded CSS
}

function reviewBackToEdit() {
    const overlay = document.getElementById('reviewConfirmMode');
    if (!overlay) return;
    overlay.classList.remove('flex');
    overlay.classList.add('hidden');
    overlay.style.display = 'none'; // safety net in case the utility classes aren't in the loaded CSS
}

function reviewHandleFormSubmission() {
    // When rejecting, the typed reason becomes this report's saved remarks
    // (Office Notes) — it rides through on the existing "remarks" field the
    // server already writes to disease_cases.remarks.
    const statusVal = document.getElementById('view_status').value;
    if (statusVal === 'rejected') {
        const reasonEl = document.getElementById('view_rejection_reason');
        if (reasonEl) {
            document.getElementById('view_remarks').value = reasonEl.value.trim();
        }
    }
    document.getElementById('view_update_form').submit();
}

// ── ONE-WAY STATUS FLOW — Pending → Verified → Resolved, Rejected only from Pending ──
function applyStatusFlow(currentStatus) {
    window._currentCaseStatus = currentStatus;
    const allowed = STATUS_FLOW[currentStatus] || [currentStatus];
    document.querySelectorAll('#status_quick_select .status-pill').forEach(pill => {
        pill.classList.toggle('disabled', !allowed.includes(pill.dataset.value));
    });
    const hint = document.getElementById('status_flow_hint');
    if (allowed.length <= 1) {
        hint.textContent = `This case is ${STATUS_LABELS[currentStatus].toLowerCase()} — status is locked.`;
    } else {
        const nextOptions = allowed.filter(s => s !== currentStatus).map(s => STATUS_LABELS[s]).join(' or ');
        hint.textContent = `One-way flow: this case can only advance to ${nextOptions}.`;
    }
}

// ── TREATMENT INSTRUCTION CHECKLIST ──
// The diseases table stores recommended_treatment as several newline-separated
// instructions. Split them into individually selectable buttons so the reviewer
// picks exactly which ones apply to this farmer's case.
//
// NOTE: the compose box only ever holds the recommendation text itself (or the
// reviewer's own free-typed message) — never the disease name/field observation.
// That "findings" info is sent automatically by the server as its own separate
// message, and only when an actual recommendation was picked (see
// sendRecommendation() and reports.php's send_recommendation handler). A plain
// free-typed message (no instruction selected) never gets the disease info
// attached.

function renderRecInstructions(rawTreatment, previouslySentText) {
    const wrap = document.getElementById('view_rec_instructions');
    wrap.style.display = 'none'; // collapsed by default, keeps the popup chat-first
    const toggleBtn = document.getElementById('msg_suggested_toggle');
    if (toggleBtn) toggleBtn.innerHTML = '<span>💊 Suggested treatment replies</span> <span style="font-size:0.75rem;">▾</span>';
    const sentences = (rawTreatment || '')
        .split(/\r?\n/)
        .map(s => s.trim())
        .filter(s => s.length > 0);

    window._recInstructions = sentences;
    window._recSelected     = new Set();

    // The findings block may already be sitting at the top of a previously-sent
    // message — strip it off before trying to match which instructions were picked.
    const findingsPattern = /^🌽[^\n]*\n[^\n]*\n\n/;
    const cleanPrevText = previouslySentText ? previouslySentText.replace(findingsPattern, '') : '';

    if (sentences.length === 0) {
        wrap.innerHTML = '<p style="color:#475569;font-size:0.7rem;font-style:italic;">No recommendation on file for this disease.</p>';
        document.getElementById('view_rec_text').value = previouslySentText || '';
        autoGrowMsgBox(document.getElementById('view_rec_text'));
        updateRecSendState();
        return;
    }

    // Pre-select whichever instructions were part of a previously-sent recommendation
    const prevLines = cleanPrevText
        ? cleanPrevText.split(/\r?\n/)
            .map(s => s.trim())
            .filter(s => s.length > 0 && !/^Ito ang (mga )?dapat mong gawin:$/i.test(s))
            .map(s => s.replace(/^\d+\.\s*/, ''))
        : [];

    wrap.innerHTML = '';
    sentences.forEach((sentence, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rec-instruction-btn';
        btn.dataset.idx = idx;
        btn.onclick = () => toggleRecInstruction(idx);

        const check = document.createElement('span');
        check.className = 'rec-check';
        check.dataset.number = String(idx + 1);
        check.textContent = String(idx + 1);

        const label = document.createElement('span');
        label.className = 'rec-instruction-text';
        label.textContent = sentence;

        btn.appendChild(check);
        btn.appendChild(label);
        wrap.appendChild(btn);

        if (prevLines.includes(sentence)) {
            window._recSelected.add(idx);
            btn.classList.add('selected');
            check.textContent = '✓';
        }
    });

    updateRecPreview();
}

function toggleRecInstruction(idx) {
    const btn   = document.querySelector(`.rec-instruction-btn[data-idx="${idx}"]`);
    const check = btn.querySelector('.rec-check');
    if (window._recSelected.has(idx)) {
        window._recSelected.delete(idx);
        btn.classList.remove('selected');
        check.textContent = check.dataset.number;
    } else {
        window._recSelected.add(idx);
        btn.classList.add('selected');
        check.textContent = '✓';
    }
    updateRecPreview();
}

function updateRecPreview() {
    const selected  = Array.from(window._recSelected).sort((a, b) => a - b);
    const box       = document.getElementById('view_rec_text');
    if (selected.length === 0) {
        box.value = ''; // nothing picked — leave blank for the reviewer's own free-typed message
    } else if (selected.length === 1) {
        box.value = `Ito ang dapat mong gawin:\n${window._recInstructions[selected[0]]}`;
    } else {
        const lines = selected.map((i, n) => `${n + 1}. ${window._recInstructions[i]}`);
        box.value = `Ito ang mga dapat mong gawin:\n${lines.join('\n')}`;
    }
    autoGrowMsgBox(box);
    updateRecSendState();
}

function updateRecSendState() {
    const sendBtn  = document.getElementById('view_rec_send_btn');
    const hasText  = document.getElementById('view_rec_text').value.trim().length > 0;
    const hasRecommendation = !!(window._recSelected && window._recSelected.size > 0);
    const status   = window._currentCaseStatus;
    const isVerifiedOrResolved = (status === 'verified' || status === 'resolved');

    // Verified/resolved cases can send anything (plain message or a full disease
    // recommendation). Pending cases can only send a plain typed message — no
    // treatment instructions picked — since sending an actual recommendation
    // requires the report to be verified first.
    const blockedByPending = !isVerifiedOrResolved && status === 'pending' && hasRecommendation;

    // The button stays clickable even when blocked by pending status, so
    // sendRecommendation() still runs and can show the "please verify first"
    // notification — a genuinely disabled button would swallow the click.
    const canSend = hasText && (isVerifiedOrResolved || (status === 'pending' && !hasRecommendation));

    sendBtn.disabled      = !hasText;
    sendBtn.style.opacity = canSend ? '1' : '0.4';
    sendBtn.style.cursor  = hasText ? 'pointer' : 'not-allowed';

    // Cancel/discard button — only actionable once there's actually something to discard
    const cancelBtn = document.getElementById('view_rec_cancel_btn');
    const hasDraft  = hasText || hasRecommendation;
    cancelBtn.disabled      = !hasDraft;
    cancelBtn.style.opacity = hasDraft ? '1' : '0.35';
    cancelBtn.style.cursor  = hasDraft ? 'pointer' : 'not-allowed';

    // Hover tooltip: explain *why* sending is blocked when the reviewer has picked
    // recommendation instructions on a still-pending case.
    if (blockedByPending) {
        sendBtn.title = 'This case is still pending — verify it before sending the recommendation.';
    } else {
        sendBtn.title = window._recAlreadySent
            ? 'Resend this recommendation to the farmer'
            : 'Send this message to the farmer';
    }
}

// Discards whatever is currently typed/selected in the compose box — clears the
// textarea and deselects any picked treatment instructions, without sending anything.
function cancelRecommendationCompose() {
    const box = document.getElementById('view_rec_text');
    const hasDraft = box.value.trim().length > 0 || (window._recSelected && window._recSelected.size > 0);
    if (!hasDraft) return;

    if (!confirm('Discard this message? It will not be sent to the farmer.')) return;

    box.value = '';
    autoGrowMsgBox(box);
    window._recSelected = new Set();
    document.querySelectorAll('#view_rec_instructions .rec-instruction-btn.selected').forEach(btn => {
        btn.classList.remove('selected');
        const check = btn.querySelector('.rec-check');
        if (check) check.textContent = check.dataset.number;
    });
    updateRecSendState();
}

// ── PREVENTION TIPS TOGGLE ──
function togglePrevention() {
    const el  = document.getElementById('view_rec_prevention');
    const btn = document.getElementById('msg_prevention_toggle');
    const show = el.style.display === 'none';
    el.style.display = show ? 'block' : 'none';
    btn.textContent  = show ? '− Hide prevention tips' : '+ Show prevention tips';
}

// ── RENDER THE SENT-RECOMMENDATIONS LOG ──
function renderChatThread(messages) {
    const thread = document.getElementById('view_chat_thread');
    const empty  = document.getElementById('view_chat_empty');
    thread.innerHTML = '';

    if (!messages || messages.length === 0) {
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';

    // A case has at most ONE active inspection at a time (disease_cases.follow_up_date
    // is a single column). Find the most recent non-cancelled inspection message —
    // that is the only bubble that should get a live "Cancel Inspection" button.
    // Older SCHEDULED/RESCHEDULED bubbles left over from history stay inert so they
    // can't be mistaken for a working cancel control on a stale message.
    let lastActiveInspectionIdx = -1;
    messages.forEach((m, i) => {
        const insp = m.sender !== 'farmer' && m.message.indexOf('FIELD INSPECTION') === 0;
        const cancelled = insp && m.message.indexOf('FIELD INSPECTION CANCELLED') === 0;
        if (insp && !cancelled) lastActiveInspectionIdx = i;
    });

    messages.forEach((m, index) => {
        const isFarmer = m.sender === 'farmer';
        // Field-inspection messages are tagged with this marker so they can be
        // highlighted in the thread — no schema change needed to detect them.
        const isInspection = !isFarmer && m.message.indexOf('FIELD INSPECTION') === 0;
        const isCancelled  = isInspection && m.message.indexOf('FIELD INSPECTION CANCELLED') === 0;
        const isActiveInspection = isInspection && !isCancelled && index === lastActiveInspectionIdx;

        const row = document.createElement('div');
        row.className = 'chat-row ' + (isFarmer ? 'farmer' : 'staff');

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble ' + (isFarmer ? 'farmer' : 'staff') + (isInspection ? ' inspection' : '') + (isCancelled ? ' cancelled' : '');
        if (isInspection) {
            const tag = document.createElement('div');
            tag.className = 'insp-tag';
            tag.textContent = isCancelled ? 'Field Inspection Cancelled' : 'Field Inspection';
            bubble.appendChild(tag);
            const body = document.createElement('div');
            body.style.whiteSpace = 'pre-wrap';
            body.textContent = m.message.replace(/^FIELD INSPECTION [A-Z]+\n/, '');
            bubble.appendChild(body);
        } else {
            bubble.textContent = m.message;
        }

        const meta = document.createElement('div');
        meta.className = 'chat-meta';
        const label = isFarmer ? '👨‍🌾 Farmer reply' : 'Sent';
        meta.textContent = m.created_at ? `${label} · ${m.created_at}` : label;

        row.appendChild(bubble);
        row.appendChild(meta);

        // Per-message "Cancel" control.
        // - On the single active inspection bubble: wired to the real cancel-inspection
        //   flow, so it actually clears follow_up_date and posts a cancellation notice —
        //   not just a cosmetic delete of this bubble. Older inspection bubbles (already
        //   cancelled, or superseded by a later reschedule) never get this button.
        if (!isFarmer && !isCancelled && isActiveInspection) {
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'msg-cancel-btn inspection-variant';
            cancelBtn.textContent = 'Cancel Inspection';
            cancelBtn.onclick = () => cancelFieldInspection();
            row.appendChild(cancelBtn);
        }

        thread.appendChild(row);
    });

    thread.scrollTop = thread.scrollHeight;
}

// ── SEND DISEASE RECOMMENDATION TO THE FARMER ──
function sendRecommendation() {
    const text = document.getElementById('view_rec_text').value.trim();
    if (!text) { alert('There is no recommendation text to send.'); return; }

    // Only treat this as a real "recommendation" (disease name + description sent
    // as its own separate message first) when the reviewer actually picked at
    // least one suggested treatment instruction. A plain free-typed message never
    // gets the disease info attached.
    const hasRecommendation = !!(window._recSelected && window._recSelected.size > 0);
    const status = window._currentCaseStatus;
    const isVerifiedOrResolved = (status === 'verified' || status === 'resolved');

    // A full disease recommendation can only go out once the report is verified.
    // A pending report can still send, but only a plain typed message.
    if (!isVerifiedOrResolved && hasRecommendation) {
        showAppToast('This case is still pending — verify it before sending the recommendation.', 'error');
        return;
    }
    if (!isVerifiedOrResolved && status !== 'pending') {
        showAppToast('This case is still pending — verify it before sending the recommendation.', 'error');
        return;
    }

    if (!confirm('Send this recommendation to the farmer?')) return;

    document.getElementById('view_rec_case_id').value     = document.getElementById('view_case_id_hidden').value;
    document.getElementById('view_rec_hidden_text').value = text;
    document.getElementById('view_rec_return_tab').value  = status || 'verified';
    document.getElementById('view_rec_has_recommendation').value = hasRecommendation ? '1' : '0';
    document.getElementById('view_rec_followup_date').value = ''; // plain sends never touch the inspection date
    document.getElementById('view_rec_clear_followup').value = '0';
    document.getElementById('view_rec_form').submit();

    // Clear the compose box + reset picked instructions once the send is dispatched,
    // so the field doesn't sit there holding stale text if the modal stays open.
    document.getElementById('view_rec_text').value = '';
    window._recSelected = new Set();
    document.querySelectorAll('#view_rec_instructions .rec-instruction-btn.selected').forEach(btn => {
        btn.classList.remove('selected');
        const check = btn.querySelector('.rec-check');
        if (check) check.textContent = check.dataset.number;
    });
    autoGrowMsgBox(document.getElementById('view_rec_text'));
    updateRecSendState();
}

// ── FIELD INSPECTION SCHEDULER ──
function todayIsoDate() {
    const d = new Date();
    const tz = d.getTimezoneOffset() * 60000;
    return new Date(d - tz).toISOString().slice(0, 10);
}

// Builds the "convenient" time dropdown — fixed 30-minute slots (7:00 AM, 7:30 AM, 8:00 AM…)
// instead of a free-entry time field, covering typical field-work hours.
function populateInspectionTimeOptions() {
    const select = document.getElementById('view_inspection_time');
    const prevValue = select.value;
    select.innerHTML = '<option value="">Select a time…</option>';
    for (let mins = 6 * 60; mins <= 18 * 60; mins += 30) {
        const h24 = Math.floor(mins / 60);
        const m   = mins % 60;
        const value = String(h24).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        const h12 = ((h24 % 12) || 12);
        const ampm = h24 < 12 ? 'AM' : 'PM';
        const label = h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        select.appendChild(opt);
    }
    select.value = prevValue; // keep it selected if still valid, otherwise falls back to blank
}

function toggleInspectionPanel() {
    const el  = document.getElementById('view_inspection_panel');
    const btn = document.getElementById('msg_inspection_toggle');
    const show = el.style.display !== 'flex';
    el.style.display = show ? 'flex' : 'none';
    btn.innerHTML = show
        ? 'Schedule field inspection <span style="font-size:0.7rem;">▴</span>'
        : 'Schedule field inspection <span style="font-size:0.7rem;">▾</span>';
}

// Opens the panel pre-filled with the currently saved date so it can be adjusted.
// Time isn't persisted server-side (follow_up_date is a DATE column), so the
// reviewer picks the time again from the dropdown.
function openInspectionPanelForEdit() {
    window._inspectionEditMode = true;
    const panel = document.getElementById('view_inspection_panel');
    if (panel.style.display !== 'flex') toggleInspectionPanel();
    const dateInput = document.getElementById('view_inspection_date');
    dateInput.value = window._currentFollowUpDate || '';
    document.getElementById('view_inspection_time').value = '';
    document.getElementById('view_inspection_confirm_btn').textContent = 'Update & Notify Farmer';
    updateInspectionBtnState();
    dateInput.focus();
}

// Cancels the currently scheduled inspection. Both the pinned "Next inspection"
// chip's ✕ and the in-thread "Cancel Inspection" button call this — it just opens
// the in-app confirmation overlay (see confirmCancelInspection() for the part
// that actually sends the notice and clears the date).
function cancelFieldInspection() {
    if (!window._currentFollowUpDate) return;
    openCancelInspectionOverlay();
}

function openCancelInspectionOverlay() {
    const [y, m, d] = window._currentFollowUpDate.split('-').map(Number);
    const niceDate = new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    document.getElementById('inspectionCancelDateText').textContent = `Scheduled for ${niceDate}`;
    document.getElementById('inspectionCancelReason').value = '';
    const confirmBtn = document.getElementById('inspectionCancelConfirmBtn');
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Confirm Cancellation';
    document.getElementById('inspectionCancelOverlay').classList.add('open');
    document.getElementById('inspectionCancelReason').focus();
}

function closeCancelInspectionOverlay() {
    document.getElementById('inspectionCancelOverlay').classList.remove('open');
}

// Reviewer confirmed inside the dialog ("Confirm Cancellation"): builds the cancellation
// notice, sends it into the chat thread, and clears disease_cases.follow_up_date via
// the clear_follow_up_date flag. Clearing that date is what re-opens the "Schedule
// field inspection" picker — see openViewModal()/data-load above: a case can only
// have ONE active inspection at a time, and a fresh one can only be scheduled once
// the current one is cancelled (or resolved via the Adjust/reschedule flow).
function confirmCancelInspection() {
    if (!window._currentFollowUpDate) { closeCancelInspectionOverlay(); return; }

    // Guard against a double-click firing this twice while the form is submitting/
    // the page is navigating away.
    const confirmBtn = document.getElementById('inspectionCancelConfirmBtn');
    if (confirmBtn.disabled) return;
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Cancelling…';

    const [y, m, d] = window._currentFollowUpDate.split('-').map(Number);
    const niceDate = new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    const reason = document.getElementById('inspectionCancelReason').value.trim() || 'unforeseen circumstances';

    const messageText = `FIELD INSPECTION CANCELLED\nThe field inspection scheduled for your farm on ${niceDate} has been cancelled due to ${reason}. Please wait for further scheduling.`;

    document.getElementById('view_rec_case_id').value            = document.getElementById('view_case_id_hidden').value;
    document.getElementById('view_rec_hidden_text').value        = messageText;
    document.getElementById('view_rec_return_tab').value         = window._currentCaseStatus || 'verified';
    document.getElementById('view_rec_has_recommendation').value = '0';
    document.getElementById('view_rec_followup_date').value      = '';
    document.getElementById('view_rec_clear_followup').value     = '1';

    document.getElementById('view_rec_form').submit();
}

function updateInspectionBtnState() {
    const date = document.getElementById('view_inspection_date').value;
    const time = document.getElementById('view_inspection_time').value;
    // Belt-and-suspenders: block a past date even if it slipped past the native min= restriction
    const isPast = date && date < todayIsoDate();
    document.getElementById('view_inspection_confirm_btn').disabled = !(date && time) || isPast;
}

function scheduleFieldInspection() {
    const dateVal = document.getElementById('view_inspection_date').value;
    const timeVal = document.getElementById('view_inspection_time').value;
    if (!dateVal || !timeVal) { showAppToast('Pick both a date and a time first.', 'error'); return; }
    if (dateVal < todayIsoDate()) { showAppToast('Inspection date can\'t be in the past.', 'error'); return; }

    // Belt-and-suspenders: a case can only have ONE active inspection at a time.
    // The toggle/date picker is normally hidden once one is scheduled (see the
    // data-load logic in openViewModal), so this only fires if that state ever
    // goes stale — e.g. a cached back/forward navigation.
    if (window._currentFollowUpDate && !window._inspectionEditMode) {
        showAppToast('An inspection is already scheduled. Cancel it first before scheduling a new one.', 'error');
        return;
    }

    // Friendly formatted date/time for the confirmation prompt + the farmer-facing message
    const dateObj  = new Date(dateVal + 'T' + timeVal);
    const niceDate = dateObj.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    const niceTime = dateObj.toLocaleTimeString(undefined, { hour:'numeric', minute:'2-digit' });
    const isEdit   = !!window._inspectionEditMode;

    // Confirmation before anything is sent or saved
    const confirmed = confirm(
        (isEdit
            ? `Reschedule the field inspection to ${niceDate} at ${niceTime}?\n\n`
            : `Schedule a field inspection for ${niceDate} at ${niceTime}?\n\n`) +
        `This will be sent to the farmer as a message and saved as this case's follow-up date.`
    );
    if (!confirmed) return;

    const messageText = (isEdit
        ? `FIELD INSPECTION RESCHEDULED\nThe field inspection for your farm has been moved to ${niceDate} at ${niceTime}. Please be available on-site at that time.`
        : `FIELD INSPECTION SCHEDULED\nA field inspection has been scheduled for your farm on ${niceDate} at ${niceTime}. Please be available on-site at that time.`);

    // Reuse the existing recommendation-send pipeline: it posts a staff bubble
    // into the chat thread and, via the follow_up_date hidden field, saves the
    // inspection date on the case record (disease_cases.follow_up_date).
    document.getElementById('view_rec_case_id').value            = document.getElementById('view_case_id_hidden').value;
    document.getElementById('view_rec_hidden_text').value        = messageText;
    document.getElementById('view_rec_return_tab').value         = window._currentCaseStatus || 'verified';
    document.getElementById('view_rec_has_recommendation').value = '0';
    document.getElementById('view_rec_followup_date').value      = dateVal;
    document.getElementById('view_rec_clear_followup').value     = '0';
    document.getElementById('view_rec_form').submit();
}

// ── PHOTO LIGHTBOX — enlarge evidence photos without leaving the case file ──
function openLightbox(src) {
    document.getElementById('photoLightboxImg').src = src;
    document.getElementById('photoLightbox').style.display = 'flex';
}
function closeLightbox() {
    document.getElementById('photoLightbox').style.display = 'none';
}

// ── COPY GPS COORDINATES ──
function copyGpsCoords() {
    if (!window._currentGpsCoords) return;
    navigator.clipboard.writeText(window._currentGpsCoords).then(() => {
        const btn = document.getElementById('view_gps_copy_btn');
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 1500);
    });
}

// ── SUCCESS / ERROR TOAST — call this instead of alert() for save confirmations ──
// Usage: showAppToast('Intelligence Update Saved!');       // success (green)
//        showAppToast('Something went wrong.', 'error');   // error (red)
window._appToastTimer = null;
function showAppToast(message, type = 'success') {
    const toast = document.getElementById('appToast');
    const icon  = toast.querySelector('.toast-icon');
    const text  = document.getElementById('appToastText');

    toast.classList.remove('hide', 'error');
    if (type === 'error') {
        toast.classList.add('error');
        icon.innerHTML = '&#33;';
    } else {
        icon.innerHTML = '&#10003;';
    }
    text.textContent = message;
    toast.style.display = 'flex';

    clearTimeout(window._appToastTimer);
    window._appToastTimer = setTimeout(hideAppToast, 3200);
}
function hideAppToast() {
    const toast = document.getElementById('appToast');
    if (toast.style.display !== 'flex') return;
    toast.classList.add('hide');
    setTimeout(() => { toast.style.display = 'none'; toast.classList.remove('hide'); }, 200);
}

document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const lb        = document.getElementById('photoLightbox');
        const msg       = document.getElementById('messageModal');
        const confirm   = document.getElementById('reviewConfirmMode');
        const inspCancel = document.getElementById('inspectionCancelOverlay');
        if (lb.style.display === 'flex') { closeLightbox(); }
        else if (inspCancel.classList.contains('open')) { closeCancelInspectionOverlay(); }
        else if (!confirm.classList.contains('hidden')) { reviewBackToEdit(); }
        else if (!msg.classList.contains('hidden')) { closeMessageModal(); }
        else { closeModal(); }
    }
});

</script>
<?php
}