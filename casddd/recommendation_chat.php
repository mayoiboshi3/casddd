<?php
/**
 * RECOMMENDATION CHAT BOX (MESSENGER POPUP)
 * -----------------------------------------------------------------------
 * The dedicated messenger-style popup used to send disease recommendations
 * to a farmer and hold the back-and-forth conversation for a case, including
 * a pinned "Field Inspection" banner (verified/resolved cases only) that's
 * always visible as a pin under the header — amber "Scheduled | date @ time"
 * with a Cancel action once disease_cases.follow_up_date is set, or a neutral
 * "Not yet scheduled" pin with a Schedule action (opening an inline date/time
 * picker) otherwise. Split out of review.php so the case-review modal and the
 * recommendation chat box can be worked on independently, the same way
 * review.php itself was split out of reports.php.
 *
 * Usage from review.php (inside render_review_modal(), right where the
 * messenger popup used to sit in the markup):
 *     render_recommendation_chat_modal();
 *
 * Depends on globals defined by review.php's own <script> block:
 *   - window._currentViewData, window._currentCaseStatus (set by openViewModal)
 *   - showAppToast() (success/error toast, defined in review.php)
 * review.php's openViewModal() also calls into this file's renderChatThread(),
 * renderRecInstructions() and populateInspectionTimeOptions() to pre-populate
 * the chat box the moment a case is opened, before the popup itself is shown.
 * renderChatThread() ends by calling this file's own updateInspectionBanner(),
 * which reads window._currentViewData.follow_up_date + window._currentCaseStatus
 * to decide the banner's state — review.php does not need to touch any
 * inspection-related element directly.
 * Because both files print plain <script> tags onto the same page, this only
 * works as long as review.php requires this file (which it does, at the top)
 * so this script block is on the page before it's needed.
 */
function render_recommendation_chat_modal() {
?>
<style>
    /* Messenger-style conversation thread (case recommendation <-> farmer reply) */
    .chat-row { display:flex; flex-direction:column; max-width:82%; }
    .chat-row.staff  { align-self:flex-end; align-items:flex-end; margin-left:auto; }
    .chat-row.farmer { align-self:flex-start; align-items:flex-start; margin-right:auto; }
    .chat-bubble { padding:9px 13px; border-radius:15px; font-size:0.78rem; line-height:1.5; white-space:pre-wrap; word-wrap:break-word; }
    .chat-bubble.staff  { background:linear-gradient(135deg,#0d9488,#0f766e); color:#fff; border-bottom-right-radius:4px; }
    .chat-bubble.farmer { background:rgba(255,255,255,0.09); color:#e2e8f0; border:1px solid rgba(255,255,255,0.14); border-bottom-left-radius:4px; }
    .chat-meta { font-size:0.55rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; margin-top:4px; color:#475569; }

    /* Selectable treatment-instruction checklist buttons (suggested replies) */
    .rec-instruction-btn {
        display:flex; align-items:flex-start; gap:12px; width:100%; text-align:left;
        background:rgba(255,255,255,0.035); border:1.5px solid rgba(255,255,255,0.1); border-radius:12px;
        padding:13px 14px; cursor:pointer; transition:all 0.15s ease; margin-bottom:9px;
    }
    .rec-instruction-btn:hover { border-color:rgba(16,185,129,0.45); background:rgba(16,185,129,0.07); transform:translateY(-1px); }
    .rec-instruction-btn.selected { background:rgba(16,185,129,0.14); border-color:#10b981; box-shadow:0 0 0 1px rgba(16,185,129,0.25) inset; }
    .rec-instruction-btn .rec-check {
        flex-shrink:0; width:24px; height:24px; margin-top:1px; border-radius:999px;
        border:2px solid rgba(255,255,255,0.28); display:flex; align-items:center; justify-content:center;
        font-size:0.72rem; font-weight:900; color:rgba(255,255,255,0.6); transition:all 0.15s ease;
        background:rgba(255,255,255,0.04);
    }
    .rec-instruction-btn.selected .rec-check { background:#10b981; border-color:#10b981; color:#fff; }
    .rec-instruction-btn .rec-instruction-text { color:#e2e8f0; font-size:0.82rem; line-height:1.55; padding-top:1px; }
    .rec-instruction-btn.selected .rec-instruction-text { color:#fff; }

    /* Field Inspection — highlighted chat bubble (pinned banner styles below) */
    .chat-bubble.inspection {
        background:linear-gradient(135deg, rgba(249,115,22,0.24), rgba(194,65,12,0.12));
        border:1.5px solid #f97316; box-shadow:0 0 0 1px rgba(249,115,22,0.18) inset;
    }
    .chat-bubble.inspection .insp-tag {
        display:flex; align-items:center; gap:5px; font-size:0.6rem; font-weight:900;
        text-transform:uppercase; letter-spacing:0.08em; color:#fb923c; margin-bottom:5px;
    }
    /* Pinned "Field Inspection" banner — sits below the modal header, above the
       scrollable chat thread, for verified/resolved cases only. Two states:
       amber "Scheduled" (with Cancel) once disease_cases.follow_up_date is set,
       or a neutral "Not yet scheduled" pin (with a Schedule action) otherwise —
       always visible as a pin either way, never just absent. */
    .inspection-top-banner {
        display:none; align-items:center; gap:10px; flex-shrink:0;
        margin:0; padding:11px 20px;
    }
    .inspection-top-banner.state-scheduled {
        background:rgba(245,158,11,0.15); border-bottom:1px solid rgba(245,158,11,0.4);
    }
    .inspection-top-banner.state-scheduled .insp-label { color:#fbbf24; }
    .inspection-top-banner.state-empty {
        background:rgba(148,163,184,0.08); border-bottom:1px dashed rgba(148,163,184,0.3);
    }
    .inspection-top-banner.state-empty .insp-label { color:#94a3b8; }
    .inspection-top-banner .insp-icon { flex-shrink:0; font-size:0.9rem; }
    .inspection-top-banner .insp-label {
        flex:1; min-width:0; font-size:0.68rem; font-weight:900;
        text-transform:uppercase; letter-spacing:0.04em; line-height:1.4;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    /* Rectangular "Cancel" button — amber, shown on the scheduled-state banner */
    .inspection-cancel-btn {
        flex-shrink:0; display:flex; align-items:center; justify-content:center;
        background:rgba(245,158,11,0.12); border:1.5px solid rgba(245,158,11,0.5); border-radius:8px;
        color:#fbbf24; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.05em;
        line-height:1; cursor:pointer; transition:all 0.15s ease; padding:7px 11px; white-space:nowrap;
    }
    .inspection-cancel-btn:hover { background:rgba(245,158,11,0.24); border-color:#fbbf24; }
    /* Rectangular "Schedule" button — neutral, shown on the empty-state banner */
    .inspection-schedule-btn {
        flex-shrink:0; display:flex; align-items:center; justify-content:center;
        background:rgba(148,163,184,0.12); border:1.5px solid rgba(148,163,184,0.45); border-radius:8px;
        color:#cbd5e1; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.05em;
        line-height:1; cursor:pointer; transition:all 0.15s ease; padding:7px 11px; white-space:nowrap;
    }
    .inspection-schedule-btn:hover { background:rgba(148,163,184,0.22); border-color:#e2e8f0; }
    .chat-bubble.inspection.cancelled {
        background:linear-gradient(135deg, rgba(148,163,184,0.18), rgba(71,85,105,0.1));
        border:1.5px solid #64748b; box-shadow:0 0 0 1px rgba(100,116,139,0.15) inset;
    }
    .chat-bubble.inspection.cancelled .insp-tag { color:#94a3b8; }

    /* Inline date/time panel — opens directly under the banner when "Schedule" is
       clicked on the empty-state banner. Collapsed by default. */
    .inspection-panel {
        display:none; flex-direction:column; gap:10px; flex-shrink:0;
        margin:0 20px 14px; padding:14px;
        background:rgba(249,115,22,0.05); border:1px dashed rgba(249,115,22,0.3); border-radius:12px;
    }
    .inspection-panel label { display:block; color:#94a3b8; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:5px; }
    .inspection-panel input[type="date"], .inspection-panel select {
        width:100%; padding:9px 12px; border-radius:9px; background:rgba(255,255,255,0.06);
        border:1px solid rgba(255,255,255,0.14); color:#e2e8f0; font-size:0.8rem; outline:none;
        box-sizing:border-box; font-family:inherit;
    }
    .inspection-panel .insp-row { display:flex; gap:10px; }
    .inspection-panel .insp-row > div { flex:1; min-width:0; }
    .inspection-panel-header { display:flex; align-items:center; justify-content:space-between; }
    .inspection-panel-title { color:#94a3b8; font-size:0.6rem; font-weight:900; text-transform:uppercase; letter-spacing:0.08em; }
    .inspection-panel-close { background:none; border:none; color:#64748b; cursor:pointer; font-size:0.85rem; line-height:1; padding:0; }
    .inspection-panel-close:hover { color:#e2e8f0; }
    .inspection-confirm-btn {
        background:linear-gradient(135deg,#f97316,#c2410c); border:none; color:#fff; font-weight:900;
        font-size:0.7rem; text-transform:uppercase; letter-spacing:0.06em; padding:10px 14px; border-radius:9px;
        cursor:pointer; transition:opacity 0.15s ease;
    }
    .inspection-confirm-btn:disabled { opacity:0.4; cursor:not-allowed; }
</style>


<!-- ═══ MESSENGER POPUP — dedicated chat window for the farmer conversation ═══ -->
<div id="messageModal" class="hidden fixed inset-0 z-[120]" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);" onclick="if(event.target===this) closeMessageModal()">
    <div id="messageModalBox" style="
        position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
        background:#0b1a14;
        border:1px solid rgba(165,180,252,0.28);
        border-radius:22px;
        width:min(560px,96vw);
        height:min(760px,92vh);
        display:flex;
        flex-direction:column;
        overflow:hidden;
        z-index:121;
        box-shadow:0 30px 70px rgba(0,0,0,0.85);
        animation:reportPopIn .22s ease;
    ">
        <!-- Messenger header -->
        <div style="flex-shrink:0;display:flex;align-items:center;gap:12px;padding:18px 22px;background:linear-gradient(135deg,rgba(165,180,252,0.14),rgba(99,102,241,0.05));border-bottom:1px solid rgba(255,255,255,0.08);">
            <div id="msg_farmer_avatar" style="width:44px;height:44px;border-radius:999px;background:rgba(165,180,252,0.18);border:2px solid rgba(165,180,252,0.4);display:flex;align-items:center;justify-content:center;font-size:1.05rem;font-weight:900;color:#a5b4fc;flex-shrink:0;overflow:hidden;"></div>
            <div style="min-width:0;flex:1;">
                <div id="msg_farmer_name" style="color:#fff;font-size:1rem;font-weight:900;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span id="msg_farmer_brgy" style="color:#64748b;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;"></span>
                    <span id="msg_header_badge" style="display:none;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.35);color:#34d399;font-size:0.55rem;font-weight:900;text-transform:uppercase;letter-spacing:0.06em;padding:2px 7px;border-radius:999px;">Sent ✓</span>
                </div>
            </div>
            <button onclick="closeMessageModal()" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:#64748b;border-radius:9px;padding:6px 12px;cursor:pointer;font-size:1.1rem;line-height:1;flex-shrink:0;">&#10005;</button>
        </div>

        <!-- Pinned "Field Inspection" banner — sits directly below the header,
             above the scrollable chat thread, for verified/resolved cases only
             (hidden entirely for pending cases — nothing confirmed to inspect
             yet). Always shown as a pin: amber "Scheduled | date @ time" with a
             Cancel action once disease_cases.follow_up_date is set, or a
             neutral "Not yet scheduled" pin with a Schedule action otherwise.
             Content, state class, and action button are all set dynamically by
             updateInspectionBanner(). -->
        <div id="view_inspection_top_banner" class="inspection-top-banner">
            <span class="insp-icon">📌</span>
            <span class="insp-label" id="view_inspection_top_text"></span>
            <button type="button" id="view_inspection_top_action"></button>
        </div>

        <!-- Inline date/time picker — collapsed by default, opened by the
             empty-state banner's "Schedule" action. -->
        <div id="view_inspection_panel" class="inspection-panel">
            <div class="inspection-panel-header">
                <span class="inspection-panel-title">Schedule Field Inspection</span>
                <button type="button" onclick="toggleInspectionPanel()" class="inspection-panel-close" title="Close">&#10005;</button>
            </div>
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

        <div id="view_chat_scroll_wrap" style="flex:1;display:flex;flex-direction:column;overflow-y:auto;scrollbar-width:thin;min-height:0;">
            <!-- Message thread -->
            <div id="view_chat_thread" style="flex:1;display:flex;flex-direction:column;gap:12px;padding:20px;"></div>
        </div>
        <p id="view_chat_empty" style="display:none;color:#475569;font-size:0.78rem;font-style:italic;text-align:center;padding:0 20px 14px;">No recommendation sent yet for this case.</p>
        <p id="view_rec_sent_note" style="display:none;color:#475569;font-size:0.64rem;font-weight:700;text-align:center;padding:0 20px 10px;flex-shrink:0;"></p>

        <!-- Suggested replies (from the diseases table's recommended_treatment) -->
        <div style="flex-shrink:0;border-top:1px solid rgba(255,255,255,0.06);padding:14px 20px 0;">
            <button type="button" onclick="toggleSuggested()" id="msg_suggested_toggle" style="background:rgba(165,180,252,0.08);border:1px solid rgba(165,180,252,0.2);color:#a5b4fc;font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;cursor:pointer;padding:8px 12px;border-radius:10px;margin:0 0 10px;display:flex;align-items:center;gap:6px;width:100%;justify-content:space-between;">
                <span>💊 Suggested treatment replies</span> <span style="font-size:0.75rem;">▾</span>
            </button>
            <div id="view_rec_instructions" style="display:none;flex-direction:column;gap:6px;margin-bottom:8px;max-height:200px;overflow-y:auto;scrollbar-width:thin;"></div>
            <button type="button" onclick="togglePrevention()" style="background:none;border:none;color:#6ee7b7;font-size:0.64rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;cursor:pointer;padding:0 0 10px;display:none;" id="msg_prevention_toggle">+ Show prevention tips</button>
            <div id="view_rec_prevention" style="display:none;margin-bottom:10px;padding:12px 14px;background:rgba(255,255,255,0.03);border:1px dashed rgba(255,255,255,0.1);border-radius:10px;color:#94a3b8;font-size:0.78rem;line-height:1.55;"></div>
        </div>

        <!-- Messenger-style bottom input bar -->
        <div style="flex-shrink:0;display:flex;align-items:flex-end;gap:10px;padding:14px 20px 18px;background:rgba(255,255,255,0.02);border-top:1px solid rgba(255,255,255,0.06);">
            <textarea id="view_rec_text" rows="1" oninput="autoGrowMsgBox(this);updateRecSendState()"
                style="flex:1;resize:none;max-height:130px;padding:12px 16px;border-radius:22px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.14);color:#e2e8f0;font-size:0.86rem;line-height:1.45;outline:none;box-sizing:border-box;font-family:inherit;"
                placeholder="Type a message or pick a suggested reply…"></textarea>

            <button type="button" id="view_rec_cancel_btn" onclick="cancelRecommendationCompose()" title="Discard this message" disabled
                style="flex-shrink:0;width:44px;height:44px;border-radius:999px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.14);color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.05rem;transition:opacity 0.15s ease;">
                &#10005;
            </button>

            <button type="button" id="view_rec_send_btn" onclick="sendRecommendation()" title="Send this message to the farmer"
                style="flex-shrink:0;width:44px;height:44px;border-radius:999px;background:linear-gradient(135deg,#0d9488,#0f766e);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.15rem;box-shadow:0 6px 16px rgba(13,148,136,0.4);">
                ➤
            </button>
        </div>
        <p style="flex-shrink:0;color:#334155;font-size:0.6rem;font-weight:600;text-align:center;padding:0 20px 12px;line-height:1.4;">Sends this recommendation straight to the farmer's case file.</p>
    </div>
</div>


<script>
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

    // Safety net — re-sync the pinned banner here too, in case this modal is
    // opened without a fresh renderChatThread() call in between.
    updateInspectionBanner();

    document.getElementById('messageModal').classList.remove('hidden');
    const box = document.getElementById('messageModalBox');
    box.style.animation = 'none';
    box.offsetHeight;
    box.style.animation = 'reportPopIn .22s ease';

    // Thread was already rendered by openViewModal — just scroll to the latest message
    const scrollWrap = document.getElementById('view_chat_scroll_wrap');
    scrollWrap.scrollTop = scrollWrap.scrollHeight;
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

    messages.forEach(m => {
        const isFarmer = m.sender === 'farmer';
        // Field-inspection messages are tagged with this marker so they can be
        // highlighted in the thread — no schema change needed to detect them.
        const isInspection = !isFarmer && m.message.indexOf('FIELD INSPECTION') === 0;
        const isCancelled  = isInspection && m.message.indexOf('FIELD INSPECTION CANCELLED') === 0;

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

        thread.appendChild(row);
    });

    const scrollWrap = document.getElementById('view_chat_scroll_wrap');
    scrollWrap.scrollTop = scrollWrap.scrollHeight;

    // Now that the thread is in the DOM, sync the pinned "Field Inspection:
    // Scheduled" banner up top against it.
    updateInspectionBanner();
}

// ── PINNED "FIELD INSPECTION" BANNER ──
// Shown for verified/resolved cases only (a still-pending case has nothing
// confirmed to inspect yet). Two states:
//   - Scheduled: disease_cases.follow_up_date is set — amber banner with the
//     date, a Cancel action, driven by window._currentViewData.follow_up_date
//     (set by review.php's openViewModal).
//   - Empty: no active date — neutral banner with a Schedule action that
//     opens the inline date/time panel below it.
// follow_up_date is DATE-only, so for the nicer "@ 1:00 PM" portion on the
// scheduled state we pull the time back out of the most recent non-cancelled
// FIELD INSPECTION chat bubble — the same text scheduleFieldInspection() /
// cancelFieldInspection() already write into the thread. If a cancellation is
// the most recent inspection event, or no time can be found, it falls back to
// date-only.
function updateInspectionBanner() {
    const data   = window._currentViewData;
    const status = window._currentCaseStatus;
    const isVerifiedOrResolved = (status === 'verified' || status === 'resolved');

    const banner    = document.getElementById('view_inspection_top_banner');
    const textEl    = document.getElementById('view_inspection_top_text');
    const actionBtn = document.getElementById('view_inspection_top_action');
    const panel     = document.getElementById('view_inspection_panel');
    if (!banner || !textEl || !actionBtn) return;

    // Reset the inline scheduling panel each time this runs (new case opened,
    // or the thread just changed) so a stale open panel never carries over.
    if (panel) panel.style.display = 'none';

    const followUpDate = data && data.follow_up_date ? data.follow_up_date : '';
    window._currentFollowUpDate = followUpDate || null;

    if (!isVerifiedOrResolved) {
        banner.style.display = 'none';
        return;
    }
    banner.style.display = 'flex';

    if (followUpDate) {
        // Look for a time on the last active (non-cancelled) inspection bubble
        let timeLabel = null;
        const bubbles = document.querySelectorAll('#view_chat_thread .chat-bubble.inspection');
        if (bubbles.length) {
            const last = bubbles[bubbles.length - 1];
            if (!last.classList.contains('cancelled')) {
                const match = (last.textContent || '').match(/at (\d{1,2}:\d{2}\s?[AP]M)/i);
                if (match) timeLabel = match[1].toUpperCase();
            }
        }

        const [y, m, d] = followUpDate.split('-').map(Number);
        const dateText  = new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

        banner.className = 'inspection-top-banner state-scheduled';
        textEl.textContent = ('Field Inspection: Scheduled | ' + (timeLabel ? `${dateText} @ ${timeLabel}` : dateText)).toUpperCase();
        actionBtn.className = 'inspection-cancel-btn';
        actionBtn.textContent = 'Cancel';
        actionBtn.title = 'Cancel this inspection';
        actionBtn.onclick = cancelFieldInspection;
    } else {
        banner.className = 'inspection-top-banner state-empty';
        textEl.textContent = 'Field Inspection: Not Yet Scheduled';
        actionBtn.className = 'inspection-schedule-btn';
        actionBtn.textContent = 'Schedule';
        actionBtn.title = 'Schedule a field inspection';
        actionBtn.onclick = toggleInspectionPanel;
    }
}

// ── FIELD INSPECTION SCHEDULING ──
function todayIsoDate() {
    const d = new Date();
    const tz = d.getTimezoneOffset() * 60000;
    return new Date(d - tz).toISOString().slice(0, 10);
}

// Builds the "convenient" time dropdown — fixed 30-minute slots (7:00 AM, 7:30 AM, 8:00 AM…)
// instead of a free-entry time field, covering typical field-work hours.
function populateInspectionTimeOptions() {
    const select = document.getElementById('view_inspection_time');
    if (!select) return;
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

// Opens/closes the inline date+time picker under the banner — reached only
// from the empty-state banner's "Schedule" action (or its own close button).
function toggleInspectionPanel() {
    const panel = document.getElementById('view_inspection_panel');
    if (!panel) return;
    const show = panel.style.display !== 'flex';
    panel.style.display = show ? 'flex' : 'none';
    if (show) {
        const dateInput = document.getElementById('view_inspection_date');
        dateInput.value = '';
        dateInput.min   = todayIsoDate(); // can't schedule an inspection in the past
        populateInspectionTimeOptions();
        document.getElementById('view_inspection_confirm_btn').disabled = true;
        dateInput.focus();
    }
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

    // Friendly formatted date/time for the confirmation prompt + the farmer-facing message
    const dateObj  = new Date(dateVal + 'T' + timeVal);
    const niceDate = dateObj.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    const niceTime = dateObj.toLocaleTimeString(undefined, { hour:'numeric', minute:'2-digit' });

    if (!confirm(`Schedule a field inspection for ${niceDate} at ${niceTime}?\n\nThis will be sent to the farmer as a message and saved as this case's follow-up date.`)) return;

    const messageText = `FIELD INSPECTION SCHEDULED\nA field inspection has been scheduled for your farm on ${niceDate} at ${niceTime}. Please be available on-site at that time.`;

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

// Cancels the currently scheduled inspection: asks for a short reason (defaults to
// "unforeseen circumstances" if left blank), sends a notice into the chat thread,
// and clears disease_cases.follow_up_date via the clear_follow_up_date flag.
function cancelFieldInspection() {
    if (!window._currentFollowUpDate) return;

    const reasonInput = prompt(
        'Cancel the scheduled field inspection?\n\nOptionally add a reason the farmer will see (leave blank for a generic note):',
        ''
    );
    if (reasonInput === null) return; // reviewer backed out of the prompt

    const reason = reasonInput.trim() || 'unforeseen circumstances';

    const [y, m, d] = window._currentFollowUpDate.split('-').map(Number);
    const niceDate = new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });

    if (!confirm(`Cancel the field inspection scheduled for ${niceDate}? The farmer will be notified.`)) return;

    const messageText = `FIELD INSPECTION CANCELLED\nThe field inspection scheduled for your farm on ${niceDate} has been cancelled due to ${reason}. We will notify you once it is rescheduled.`;

    document.getElementById('view_rec_case_id').value            = document.getElementById('view_case_id_hidden').value;
    document.getElementById('view_rec_hidden_text').value        = messageText;
    document.getElementById('view_rec_return_tab').value         = window._currentCaseStatus || 'verified';
    document.getElementById('view_rec_has_recommendation').value = '0';
    document.getElementById('view_rec_followup_date').value      = '';
    document.getElementById('view_rec_clear_followup').value     = '1';
    document.getElementById('view_rec_form').submit();
}

</script>
<?php
}