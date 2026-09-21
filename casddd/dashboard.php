<?php
$pageTitle = "Dashboard / Map";
include "includes/layout.php";

// ── DATABASE CONNECTION ──
$conn = new mysqli('localhost', 'root', '882372', 'corncasd_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4'); // so names like "Bañadero" come through intact

// ── STAT: Total Farmers ──
$totalFarmers = 0;
$f = $conn->query("SELECT COUNT(*) AS cnt FROM farmers WHERE status = 'active'");
if ($f) { $totalFarmers = $f->fetch_assoc()['cnt']; }


// ── STAT: Active Reports (pending + verified) — manual reports only; AI scans
// don't need review, so they're excluded here and counted separately below. ──
$totalActiveReports = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt FROM disease_cases WHERE status IN ('pending','verified') AND `source` = 'manual_report'");
if ($r) { $totalActiveReports = $r->fetch_assoc()['cnt']; }

// ── STAT: Verified Cases (manual reports only) ──
$totalVerifiedCases = 0;
$v = $conn->query("SELECT COUNT(*) AS cnt FROM disease_cases WHERE status = 'verified' AND `source` = 'manual_report'");
if ($v) { $totalVerifiedCases = $v->fetch_assoc()['cnt']; }

// ── STAT: AI Scans — auto-classified cases that don't require review ──
$totalAiScans = 0;
$as = $conn->query("SELECT COUNT(*) AS cnt FROM disease_cases WHERE `source` = 'scan' AND status IN ('pending','verified','resolved')");
if ($as) { $totalAiScans = $as->fetch_assoc()['cnt']; }

// ── BUILD BARANGAY DATA ──
$barangayData = [];

$brgy_res = $conn->query("SELECT id, name FROM barangays ORDER BY name");
if ($brgy_res) {
    while ($row = $brgy_res->fetch_assoc()) {
        $brgy_id   = (int)$row['id'];
        $brgy_name = $row['name'];

        // Single query for both the counts and the case list — AI-scanned cases
        // (source = 'scan') don't need review, so they're tallied separately as
        // ai_scan_count instead of being folded into pending/verified/resolved.
        $cases_res = $conn->query("
            SELECT dc.case_id, dc.reference_id, dc.status, dc.report_date,
                   dc.severity, dc.plants_affected, dc.total_plants,
                   dc.infection_percentage, dc.description, dc.photo_evidence,
                   dc.source, dc.farmer_id, f.farmer_name AS farmer_name_db, d.disease_name
            FROM disease_cases dc
            LEFT JOIN diseases d ON dc.disease_id = d.disease_id
            LEFT JOIN farmers f  ON f.farmer_id = dc.farmer_id
            WHERE dc.barangay_id = $brgy_id
              AND dc.status IN ('pending','verified','resolved')
            ORDER BY dc.report_date DESC
        ");

        $pending_count  = 0;
        $verified_count = 0;
        $resolved_count = 0;
        $ai_scan_count  = 0;
        $cases = [];
        if ($cases_res) {
            while ($c = $cases_res->fetch_assoc()) {
                $isAiScan = ($c['source'] === 'scan');

                if ($isAiScan) {
                    $ai_scan_count++;
                } else {
                    if ($c['status'] === 'pending')  $pending_count++;
                    if ($c['status'] === 'verified') $verified_count++;
                    if ($c['status'] === 'resolved') $resolved_count++;
                }

                $farmerName = null;
                if (preg_match('/^\[FARMER:(.+?)\]\n?/s', $c['description'] ?? '', $m)) {
                    $farmerName = trim($m[1]);
                } elseif (!empty($c['farmer_name_db'])) {
                    $farmerName = $c['farmer_name_db'];
                }
                $c['farmer_name'] = $farmerName;
                $c['description'] = preg_replace('/^\[FARMER:.+?\]\n?/s', '', $c['description'] ?? '');

                // AI scans carry the detected label + confidence in the description text
                // (e.g. "AI scan: Corn___Common_Rust detected. Confidence: 92.2%") instead
                // of a disease_id, so pull it out for display.
                $c['ai_label']      = null;
                $c['ai_confidence'] = null;
                if ($isAiScan) {
                    if (preg_match('/AI scan:\s*(.+?)\s+detected\b/i', (string)$c['description'], $lm)) {
                        $c['ai_label'] = trim(str_replace('_', ' ', preg_replace('/^Corn_+/i', '', trim($lm[1]))));
                    }
                    if (preg_match('/Confidence:\s*([\d.]+)\s*%/i', (string)$c['description'], $cm)) {
                        $c['ai_confidence'] = rtrim(rtrim(number_format((float)$cm[1], 1), '0'), '.') . '%';
                    }
                }

                $c['is_ai_scan']  = $isAiScan;
                $cases[] = $c;
            }
        }

        $total_active = $pending_count + $verified_count;
        $total_all    = $total_active + $resolved_count + $ai_scan_count;

        // Highlight state is driven only by cases that actually need review —
        // AI scans never push a barangay into the "waiting for review" glow.
        // Pending takes priority over verified: a barangay with any pending case
        // still needs attention, so there's no separate "mixed" state.
        $highlight = 'none';
        if ($pending_count > 0)       $highlight = 'pending';
        elseif ($verified_count > 0)  $highlight = 'verified';

        $barangayData[$brgy_id] = [
            'name'            => $brgy_name,
            'highlight'       => $highlight,
            'pending_count'   => $pending_count,
            'verified_count'  => $verified_count,
            'resolved_count'  => $resolved_count,
            'ai_scan_count'   => $ai_scan_count,
            'total_active'    => $total_active,
            'total_all'       => $total_all,
            'has_report'      => ($total_active > 0 || $ai_scan_count > 0),
            'cases'           => $cases,
        ];
    }
}

// ── STAT: Pending Cases (manual reports only — AI scans never need review) ──
$totalPendingCases = 0;
$pq = $conn->query("SELECT COUNT(*) AS cnt FROM disease_cases WHERE status = 'pending' AND `source` = 'manual_report'");
if ($pq) { $totalPendingCases = $pq->fetch_assoc()['cnt']; }

$conn->close();
?>

<link rel="stylesheet" href="map.css">

<style>
body { font-family: 'Inter', sans-serif; background-color: #f8fafc; overflow: hidden; }
.sidebar-gradient { background: linear-gradient(180deg, #064e3b 0%, #022c22 100%); }
.glass-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
main { display: flex; width: 100%; height: 100vh; }
.content-area { flex-grow: 1; overflow-y: auto; overflow-x: hidden; background-color: #f8fafc; }

/* ── SVG PATH BASE ── */
#map-3d-wrap svg path {
    cursor: pointer;
    stroke: rgba(255,255,255,0.15);
    stroke-width: 0.4px;
    transition: transform 0.25s cubic-bezier(0.2,0.8,0.2,1),
                filter   0.25s cubic-bezier(0.2,0.8,0.2,1),
                stroke   0.25s ease,
                stroke-width 0.25s ease;
    transform-box: fill-box;
    transform-origin: center;
}
#map-3d-wrap svg path:hover {
    stroke: #ffffff;
    stroke-width: 1.8px;
    transform: translateY(-10px) scale(1.04);
    filter: brightness(1.55)
            drop-shadow(0 10px 18px rgba(0,0,0,0.75))
            drop-shadow(0 4px 6px rgba(0,0,0,0.5))
            drop-shadow(0 0 10px rgba(255,255,255,0.18));
}

/* ── PENDING BLINK ANIMATION ── */
@keyframes pendingBlink {
    0%,100% {
        filter: drop-shadow(0 0 5px rgba(249,115,22,1))
                drop-shadow(0 0 14px rgba(249,115,22,0.85))
                drop-shadow(0 0 28px rgba(249,115,22,0.5));
        stroke: rgba(249,115,22,1);
        opacity: 1;
    }
    50% {
        filter: drop-shadow(0 0 2px rgba(249,115,22,0.25));
        stroke: rgba(249,115,22,0.35);
        opacity: 0.7;
    }
}

/* ── HIGHLIGHT STATES ── */
#map-3d-wrap svg path.status-pending {
    animation: pendingBlink 1.1s ease-in-out infinite;
    stroke-width: 1.2px;
}
#map-3d-wrap svg path.status-verified {
    filter: drop-shadow(0 0 6px rgba(59,130,246,0.9)) drop-shadow(0 0 14px rgba(59,130,246,0.6));
    stroke: rgba(59,130,246,0.8);
    stroke-width: 1px;
}
#map-3d-wrap svg path.status-pending:hover {
    animation: none;
    filter: brightness(1.6)
            drop-shadow(0 10px 18px rgba(249,115,22,0.8))
            drop-shadow(0 0 20px rgba(249,115,22,0.5));
    stroke: #fdba74;
}
.brgy-badge {
    position: absolute;
    background: #ef4444;
    color: #fff;
    font-size: 9px;
    font-weight: 900;
    border-radius: 999px;
    padding: 1px 5px;
    pointer-events: none;
    z-index: 100;
    border: 1px solid rgba(255,255,255,0.5);
    transform: translate(-50%, -50%);
}

/* ── TOOLTIP ── */
#brgy-label {
    position: absolute;
    background: linear-gradient(135deg, rgba(6,20,12,0.97) 0%, rgba(10,26,18,0.97) 100%);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(34,197,94,0.55);
    padding: 10px 18px;
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    pointer-events: none;
    display: none;
    z-index: 1000;
    box-shadow:
        0 0 0 1px rgba(34,197,94,0.15),
        0 4px 8px rgba(0,0,0,0.4),
        0 12px 32px rgba(0,0,0,0.5),
        0 0 20px rgba(34,197,94,0.2);
    white-space: nowrap;
    transform: perspective(400px) rotateX(-4deg) translateZ(0);
    transition: transform 0.15s ease, opacity 0.15s ease;
    transform-origin: top left;
}

/* ── POPUP OVERLAY ── */
#brgy-popup-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    z-index: 9990;
    backdrop-filter: blur(5px);
}

/* ── POPUP — white card, black & white theme, flex column so inner area scrolls ── */
#brgy-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    padding: 30px 32px 26px;
    width: min(600px, 92vw);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 9999;
    box-shadow: 0 30px 70px rgba(0,0,0,0.4);
    animation: popIn .2s ease;
}
@keyframes popIn {
    from { opacity: 0; transform: translate(-50%, -48%) scale(.95); }
    to   { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}

/* ── CUSTOM SCROLLBAR inside popup (light theme) ── */
#brgy-popup ::-webkit-scrollbar {
    width: 5px;
}
#brgy-popup ::-webkit-scrollbar-track {
    background: rgba(15,23,42,0.05);
    border-radius: 999px;
}
#brgy-popup ::-webkit-scrollbar-thumb {
    background: rgba(15,23,42,0.22);
    border-radius: 999px;
    transition: background 0.2s;
}
#brgy-popup ::-webkit-scrollbar-thumb:hover {
    background: rgba(15,23,42,0.4);
}

/* ── STATUS TABS (All / Pending / Verified / Resolved, with counts) ── */
.brgy-status-tabs {
    display: flex;
    align-items: stretch;
    gap: 0;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px;
    margin: 16px 0 12px;
    flex-shrink: 0;
}
.brgy-status-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    padding: 8px 6px;
    border-radius: 9px;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    transition: background .15s ease, color .15s ease;
}
.brgy-status-tab-count {
    font-size: 0.68rem;
    font-weight: 900;
}
.brgy-status-tab.active-status-tab {
    background: #0f172a;
    color: #ffffff;
}

/* ── SEARCH + DATE ROW ── */
.brgy-search-row {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-shrink: 0;
}
.brgy-search-wrap, .brgy-date-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.brgy-search-wrap { flex: 1; min-width: 0; }
.brgy-search-wrap svg, .brgy-date-wrap svg {
    position: absolute;
    left: 11px;
    width: 14px;
    height: 14px;
    color: #94a3b8;
    pointer-events: none;
}
.brgy-search-input, .brgy-date-input {
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 9px 12px 9px 32px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #0f172a;
    font-family: inherit;
}
.brgy-search-input:focus, .brgy-date-input:focus { outline: none; border-color: #94a3b8; }
.brgy-date-wrap { flex-shrink: 0; width: 140px; }

/* ── REPORT LIST + CARDS ── */
.brgy-report-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.brgy-report-card {
    display: flex;
    gap: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 12px 14px;
}
.brgy-report-icon {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.brgy-report-icon svg { width: 14px; height: 14px; }
.brgy-no-match, .brgy-no-cases {
    color: #94a3b8;
    font-size: 0.82rem;
    padding: 20px 0;
    text-align: center;
}

/* ── RESPONSIVE: smaller popup padding & controls on narrow screens ── */
@media (max-width: 480px) {
    #brgy-popup { padding: 22px 18px 20px; }
    .brgy-status-tab { font-size: 0.6rem; padding: 7px 4px; }
    .brgy-search-row { flex-direction: column; }
    .brgy-date-wrap { width: 100%; }
}

/* ── DISEASE CASE PHOTO LIGHTBOX ── */
#brgy-photo-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10010;
    background: rgba(0,0,0,0.9);
    align-items: center;
    justify-content: center;
    cursor: zoom-out;
}
#brgy-photo-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; box-shadow: 0 30px 70px rgba(0,0,0,0.6); }
#brgy-photo-lightbox button { position:absolute; top:22px; right:26px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); color:#fff; border-radius:8px; padding:6px 12px; font-size:1rem; cursor:pointer; }

/* ── LEGEND ── */
.map-legend {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
    font-size: 11px;
    font-weight: 700;
    color: rgba(255,255,255,0.8);
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
    flex-shrink: 0;
}

/* ── STAT CARDS ── */
@keyframes livepulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.5)} }
.dcard {
    position: relative;
    background: white;
    border: 1px solid var(--accent-border);
    border-radius: 16px;
    padding: 18px 18px 14px;
    overflow: hidden;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    cursor: default;
}
.dcard.filterable { cursor: pointer; }
.dcard.filterable:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,0.1), 0 0 0 1px var(--accent-border); }
.dcard.filter-active {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 16px 40px rgba(0,0,0,0.15), 0 0 0 2px var(--accent-color);
    border-color: var(--accent-color) !important;
}
.dcard.filter-active .dcard-shine { opacity: 2; }
.dcard-shine { position: absolute; inset: 0; background: var(--accent-bg); pointer-events: none; }
.dcard-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.dcard-iconbox { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.dcard-iconbox svg { width: 18px; height: 18px; }
.dcard-badge { font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; transition: all .2s; }
.dcard.filter-active .dcard-badge { color: #fff !important; background: var(--accent-color) !important; }
.dcard-num { display: block; font-size: 2rem; font-weight: 900; color: #0f172a; line-height: 1; letter-spacing: -0.03em; }
.dcard-label { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin: 4px 0 12px; }
.dcard-bar { height: 3px; background: rgba(0,0,0,0.06); border-radius: 999px; overflow: hidden; }
.dcard-bar div { height: 100%; border-radius: 999px; transition: width 1s ease; }

@media (max-width: 1100px) {
    #dashboard-stat-grid { grid-template-columns: repeat(3,1fr) !important; }
}
@media (max-width: 640px) {
    #dashboard-stat-grid { grid-template-columns: repeat(2,1fr) !important; }
}

/* ── MAP FILTER STATES ── */
#map-3d-wrap svg path.map-dimmed {
    opacity: 0.18;
    filter: grayscale(1) brightness(0.5) !important;
    animation: none !important;
}
#map-3d-wrap svg path.map-highlighted-active {
    fill: #16a34a !important;
    filter: drop-shadow(0 0 8px rgba(22,163,74,1))
            drop-shadow(0 0 20px rgba(22,163,74,0.85))
            drop-shadow(0 0 40px rgba(22,163,74,0.5)) !important;
    stroke: #86efac !important;
    stroke-width: 1.5px !important;
    opacity: 1 !important;
    animation: none !important;
}
#map-3d-wrap svg path.map-highlighted-pending {
    fill: #f97316 !important;
    filter: drop-shadow(0 0 8px rgba(249,115,22,1))
            drop-shadow(0 0 20px rgba(249,115,22,0.85))
            drop-shadow(0 0 40px rgba(249,115,22,0.5)) !important;
    stroke: #fdba74 !important;
    stroke-width: 1.5px !important;
    opacity: 1 !important;
}
#map-3d-wrap svg path.map-highlighted-verified {
    fill: #3b82f6 !important;
    filter: drop-shadow(0 0 8px rgba(59,130,246,1))
            drop-shadow(0 0 20px rgba(59,130,246,0.85))
            drop-shadow(0 0 40px rgba(59,130,246,0.5)) !important;
    stroke: #93c5fd !important;
    stroke-width: 1.5px !important;
    opacity: 1 !important;
}
#map-3d-wrap svg path.map-highlighted-affected {
    fill: #ff6600 !important;
    filter: drop-shadow(0 0 8px rgba(255,102,0,1))
            drop-shadow(0 0 20px rgba(255,102,0,0.85))
            drop-shadow(0 0 40px rgba(255,102,0,0.5)) !important;
    stroke: #ffaa44 !important;
    stroke-width: 1.5px !important;
    opacity: 1 !important;
}
#map-3d-wrap svg path.map-highlighted-scan {
    fill: #8b5cf6 !important;
    filter: drop-shadow(0 0 8px rgba(139,92,246,1))
            drop-shadow(0 0 20px rgba(139,92,246,0.85))
            drop-shadow(0 0 40px rgba(139,92,246,0.5)) !important;
    stroke: #c4b5fd !important;
    stroke-width: 1.5px !important;
    opacity: 1 !important;
    animation: none !important;
}
#map-3d-wrap svg path.map-highlighted-active:hover,
#map-3d-wrap svg path.map-highlighted-pending:hover,
#map-3d-wrap svg path.map-highlighted-verified:hover,
#map-3d-wrap svg path.map-highlighted-affected:hover,
#map-3d-wrap svg path.map-highlighted-scan:hover {
    transform: translateY(-12px) scale(1.05) !important;
    animation: none !important;
}
</style>

<!-- ── DASHBOARD HEADER ── -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-3">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Monitoring
        </div>
        <h2 class="text-4xl font-black text-slate-900 tracking-tighter leading-none">
            Disease Map <span class="text-emerald-600">Dashboard</span>
        </h2>
        <p class="text-slate-400 font-bold text-xs mt-2 uppercase tracking-tight">Real-time crop health monitoring &mdash; Calamba City</p>
    </div>
    <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm text-slate-600 text-xs font-bold">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span id="live-date"></span>
    </div>
</div>

<!-- ── STAT CARDS ── -->
<div id="dashboard-stat-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px;">

    <!-- Farmers -->
    <div class="dcard" style="--accent:#10b981;--accent-bg:rgba(16,185,129,0.08);--accent-border:rgba(16,185,129,0.2);">
        <div class="dcard-shine"></div>
        <div class="dcard-top">
            <div class="dcard-iconbox" style="background:rgba(16,185,129,0.12);color:#10b981;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span class="dcard-badge" style="color:#10b981;background:rgba(16,185,129,0.1);">Registered</span>
        </div>
        <strong class="dcard-num"><?php echo number_format($totalFarmers); ?></strong>
        <p class="dcard-label">Total Farmers</p>
        <div class="dcard-bar"><div style="width:<?php echo min(100,($totalFarmers/200)*100); ?>%;background:#10b981;"></div></div>
    </div>

    <!-- Pending Cases -->
    <div class="dcard filterable" data-filter="pending" style="--accent:#f97316;--accent-bg:rgba(249,115,22,0.08);--accent-border:rgba(249,115,22,0.2);--accent-color:#f97316;">
        <div class="dcard-shine"></div>
        <div class="dcard-top">
            <div class="dcard-iconbox" style="background:rgba(249,115,22,0.12);color:#f97316;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="dcard-badge" style="color:#f97316;background:rgba(249,115,22,0.1);">Awaiting</span>
        </div>
        <strong class="dcard-num"><?php echo number_format($totalPendingCases); ?></strong>
        <p class="dcard-label">Pending Cases</p>
        <div class="dcard-bar"><div style="width:<?php echo $totalActiveReports > 0 ? min(100,($totalPendingCases/$totalActiveReports)*100) : 0; ?>%;background:#f97316;"></div></div>
        <p style="font-size:9px;color:#f97316;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;margin-top:8px;opacity:0.7;">▼ Click to filter map</p>
    </div>

    <!-- Verified Cases -->
    <div class="dcard filterable" data-filter="verified" style="--accent:#3b82f6;--accent-bg:rgba(59,130,246,0.08);--accent-border:rgba(59,130,246,0.2);--accent-color:#3b82f6;">
        <div class="dcard-shine"></div>
        <div class="dcard-top">
            <div class="dcard-iconbox" style="background:rgba(59,130,246,0.12);color:#3b82f6;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="dcard-badge" style="color:#3b82f6;background:rgba(59,130,246,0.1);">Confirmed</span>
        </div>
        <strong class="dcard-num"><?php echo number_format($totalVerifiedCases); ?></strong>
        <p class="dcard-label">Verified Cases</p>
        <div class="dcard-bar"><div style="width:<?php echo $totalActiveReports > 0 ? min(100,($totalVerifiedCases/$totalActiveReports)*100) : 0; ?>%;background:#3b82f6;"></div></div>
        <p style="font-size:9px;color:#3b82f6;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;margin-top:8px;opacity:0.7;">▼ Click to filter map</p>
    </div>

    <!-- Active Reports -->
    <div class="dcard filterable" data-filter="active" style="--accent:#16a34a;--accent-bg:rgba(22,163,74,0.08);--accent-border:rgba(22,163,74,0.2);--accent-color:#16a34a;">
        <div class="dcard-shine"></div>
        <div class="dcard-top">
            <div class="dcard-iconbox" style="background:rgba(22,163,74,0.12);color:#16a34a;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <span class="dcard-badge" style="color:#16a34a;background:rgba(22,163,74,0.1);">Needs Review</span>
        </div>
        <strong class="dcard-num"><?php echo number_format($totalActiveReports); ?></strong>
        <p class="dcard-label">Active Reports</p>
        <div class="dcard-bar"><div style="width:<?php echo min(100,($totalActiveReports/50)*100); ?>%;background:#16a34a;"></div></div>
        <p style="font-size:9px;color:#16a34a;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;margin-top:8px;opacity:0.7;">▼ Click to filter map</p>
    </div>

    <!-- AI Scans -->
    <div class="dcard filterable" data-filter="ai_scan" style="--accent:#8b5cf6;--accent-bg:rgba(139,92,246,0.08);--accent-border:rgba(139,92,246,0.2);--accent-color:#8b5cf6;">
        <div class="dcard-shine"></div>
        <div class="dcard-top">
            <div class="dcard-iconbox" style="background:rgba(139,92,246,0.12);color:#8b5cf6;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M12 8v8m-4-4h8"/></svg>
            </div>
        </div>
        <strong class="dcard-num"><?php echo number_format($totalAiScans); ?></strong>
        <p class="dcard-label">AI Scans</p>
        <div class="dcard-bar"><div style="width:<?php echo min(100,($totalAiScans/50)*100); ?>%;background:#8b5cf6;"></div></div>
        <p style="font-size:9px;color:#8b5cf6;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;margin-top:8px;opacity:0.7;">▼ Click to filter map</p>
    </div>

</div>

<!-- ── 3D MAP ── -->
<div class="bg-gradient-to-br from-emerald-900 via-green-700 to-yellow-500 rounded-3xl p-4 shadow-2xl flex flex-col"
     style="position:relative;height:620px;max-height:620px;">

    <!-- Legend -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-shrink:0;">
        <div class="map-legend">
            <span><span class="legend-dot" style="background:#f97316;box-shadow:0 0 6px #f97316;"></span>Pending Cases</span>
            <span><span class="legend-dot" style="background:#3b82f6;box-shadow:0 0 6px #3b82f6;"></span>Verified Cases</span>
            <span><span class="legend-dot" style="background:#8b5cf6;box-shadow:0 0 6px #8b5cf6;"></span>AI Scans</span>
            <span><span class="legend-dot" style="background:#22c55e;"></span>No Active Cases</span>
        </div>
        <div style="font-size:10px;color:rgba(255,255,255,0.6);font-weight:700;">Click a barangay for details</div>
    </div>

    <!-- Map viewport -->
    <div id="map-viewport" style="position:relative;flex:1;overflow:hidden;display:flex;justify-content:center;align-items:center;border-radius:16px;">
        <div id="map-container" style="width:100%;height:100%;display:flex;justify-content:center;align-items:center;perspective:1000px;">
            <div id="map-3d-wrap" style="transform:rotateX(28deg) rotateZ(-8deg);transition:transform 0.5s cubic-bezier(0.2,0.8,0.2,1);filter:drop-shadow(0 30px 50px rgba(0,0,0,0.85));transform-style:preserve-3d;line-height:0;">
            <svg viewBox="0 0 531 338" fill="none" style="width:min(90vw,780px);height:auto;display:block;">
                <g id="Frame" clip-path="url(#clip0_3_3)">
                <path id="MABATO" d="M16.2256 213C14.2648 222.2 7.23866 233.167 3.97069 237.5L0.539314 246.5L3.97069 254.5V263L11.3236 272.5L16.2256 280.5L33.3825 272.5H50.5393V263L48.5785 258L46.1275 218L44.1668 215L39.755 218L36.8138 222.5V227V231.5L30.9315 229.5L26.0295 231.5L24.0687 222.5L28.4805 220.5L30.9315 215L26.0295 213H16.2256Z" fill="#06402B"/>
                <path id="BUNGGO" d="M79.0393 272.5L87.0393 270.5H158.539V265.5C158.539 264.7 159.206 260.833 159.539 259L162.539 255.5L156.539 251L151.539 253H143.539L134.039 249H125.539L119.039 252L116.539 250L114.539 245L106.039 242.5L101.539 241.5L95.0393 240.5L91.0393 238.5L82.0393 222.5L79.0393 225V231.5V235V238.5L77.0393 240.5L74.5393 245L72.5393 249L71.0393 252L67.0393 255.5V262L64.5393 263L63.5393 265.5L62.5393 272.5H79.0393Z" fill="#0B5345"/>
                <path id="CANLUBANG" d="M50.5393 263V272.5H62.5393L63.5393 265.5L64.5393 263L67.0393 262V255.5L71.0393 252L72.5393 249L74.5393 245L77.0393 240.5L79.0393 238.5V235V231.5V225L82.0393 222.5V219.5L83.0393 217L84.5393 214.5L88.0393 209L89.0393 207.5L89.5393 205.5V203.5L90.5393 201.5V199L91.5393 195.5L92.5393 194L93.5393 192L94.5393 191H95.5393L97.5393 190H99.0393H99.5393V180.5V179L100.539 178H102.039H103.539H105.039L106.539 178.5L166.039 195.5V204L167.039 205.5H168.539L171.839 204.5L172.039 204L173.539 202C174.039 201.833 175.339 201.3 176.539 200.5C178.039 199.5 178.539 200.5 179.539 200C180.339 199.6 180.873 198.5 181.039 198L182.039 193L184.039 192L186.539 191L189.539 188L192.539 183L191.039 180V177L189.039 174L187.539 172L186.539 169L189.539 166C189.939 165.6 191.706 164.167 192.539 163.5L194.539 162.5L197.039 161.5L199.039 159.5L200.539 158V157L190.039 133.5C189.873 133.167 189.539 132.4 189.539 132C189.539 131.6 189.206 131.167 189.039 131L188.539 129L186.539 121.5V119L189.039 117.5L194.039 115L196.539 113.5L200.039 111L203.539 109L208.039 106.5L210.539 105.5C211.539 104.833 213.539 103.4 213.539 103C213.539 102.5 215.539 102 216.539 101.5C217.539 101 218.539 100.5 220.039 100C221.239 99.6 221.873 98.8333 222.039 98.5L225.539 97.5C226.706 97 229.139 95.9 229.539 95.5C229.939 95.1 231.039 94.6667 231.539 94.5L239.539 89.5L241.539 89H244.539H248.039H251.039L254.039 89.5L261.539 90L267.039 90.5C268.373 90.6667 271.339 91 272.539 91C273.739 91 275.039 90.6667 275.539 90.5L277.039 89.5L277.539 87V82.5V80L278.539 75.5L283.969 71.5L280.091 66L273.039 56H272.039L263.539 62H260.039L257.539 59V56L263.539 55.5L266.039 53V47.5L268.539 45.5L275.039 50L285.539 47.5L287.539 46.5L287.039 44.5L285.539 43H278.539L266.539 33L265.039 33.5L258.539 34L256.539 35.5H247.539V43.5L238.039 44L234.039 48L231.539 46.5L220.039 47L216.039 52H210.039L209.039 50.5L209.539 47L204.539 39L176.539 43.5L162.039 52L148.539 57.5L143.539 59L141.039 62.5L139.539 64.5L132.039 65.5L130.539 66.5L124.039 72.5L117.539 75L103.039 76.5L100.539 77.5L88.5393 87L84.0393 88L72.5393 97.5L65.5393 98.5L44.0393 114L39.0393 115L30.5393 116.5L25.5393 120L21.0393 125.5L17.5393 131.5L15.0393 137V146.5L15.5393 157.5V162.5L12.0393 168.5L11.0393 173L10.5393 179L11.0393 184.5L13.0393 190.5L15.0393 196V201L13.5393 209.5L15.5393 211L16.2256 213H26.0295L30.9315 215L28.4805 220.5L24.0687 222.5L26.0295 231.5L30.9315 229.5L36.8138 231.5V227V222.5L39.755 218L44.1668 215L46.1275 218L48.5785 258L50.5393 263Z" fill="#145A32"/>
                <path id="HORNALAN" d="M156.539 251L162.539 255.5V255L164.539 252L167.039 249L168.039 247L170.039 245L172.539 240L174.539 238V236L173.539 215C173.206 213.667 172.539 210.9 172.539 210.5C172.539 210.1 171.539 207.667 171.039 206.5L171.839 204.5L168.539 205.5H167.039L166.039 204V195.5L106.539 178.5L105.039 178H103.539H102.039H100.539L99.5393 179V180.5V190H99.0393H97.5393L95.5393 191H94.5393L93.5393 192L92.5393 194L91.5393 195.5L90.5393 199V201.5L89.5393 203.5V205.5L89.0393 207.5L88.0393 209L84.5393 214.5L83.0393 217L82.0393 219.5V222.5L91.0393 238.5L95.0393 240.5L101.539 241.5L106.039 242.5L114.539 245L116.539 250L119.039 252L125.539 249H134.039L143.539 253H151.539L156.539 251Z" fill="#06402B"/>
                <path id="MAYAPA" d="M293.539 102L300.539 95L283.969 71.5L278.539 75.5L277.539 80V82.5V87L277.039 89.5L275.539 90.5C275.039 90.6667 273.739 91 272.539 91C271.339 91 268.373 90.6667 267.039 90.5L261.539 90L254.039 89.5L251.039 89H248.039H244.539H241.539V92L239.539 96.5L236.039 100L233.539 106.5L236.539 110V112.5V115.5V119C236.373 119 236.139 119.2 236.539 120C236.939 120.8 237.039 122 237.039 122.5L238.039 123.5C238.206 123.5 238.539 123.7 238.539 124.5C238.539 125.5 238.039 125.5 238.539 127C239.039 128.5 239.039 128 239.039 129.5V133.5V134.5H243.039L246.039 130.5L246.539 130L250.039 129.5L252.539 128.5L254.039 127.5L259.539 121L261.039 121.5L262.539 122.5L275.539 113H276.039L280.039 109.5L283.539 108.5L286.039 107L288.039 104.5L290.539 103L293.539 102Z" fill="#006400"/>
                <path id="MAJADA LABAS" d="M197.539 125L190.039 133.5L200.539 157V158L199.039 159.5L197.039 161.5L194.539 162.5L192.539 163.5C191.706 164.167 189.939 165.6 189.539 166L186.539 169L187.539 172L189.039 174L191.039 177V180L192.539 183L197.039 180L201.539 174.5C202.539 174 204.739 172.8 205.539 172C206.339 171.2 207.873 170.333 208.539 170L207.539 167.5L208.539 164.5C209.039 163.833 210.039 162.3 210.039 161.5C210.039 160.7 211.039 159.833 211.539 159.5L212.539 157.5L213.539 156L217.039 154L222.039 151.5L226.039 148L228.539 145.5L233.039 143.5L235.039 139.5L238.539 137L239.039 134.5V133.5V129.5C239.039 128 239.039 128.5 238.539 127C238.039 125.5 238.539 125.5 238.539 124.5C238.539 123.7 238.206 123.5 238.039 123.5L237.039 122.5C237.039 122 236.939 120.8 236.539 120C236.139 119.2 236.373 119 236.539 119V115.5V112.5V110L233.539 106.5L232.539 106H230.539L228.539 107.5C227.873 107.833 226.439 108.6 226.039 109C225.639 109.4 224.206 110.5 223.539 111L221.039 113.5L217.539 116H215.539H213.539L210.039 118L207.039 121L204.539 123L202.539 124.5L200.539 125.5L199.039 125H197.539Z" fill="#2E7D32"/>
                <path id="PALO-ALTO" d="M259.039 176.5L260.039 173.5L260.539 170.5L262.039 169L263.539 167C263.873 166.5 264.539 165.4 264.539 165C264.539 164.6 265.206 164.167 265.539 164H267.539C268.039 163.833 269.139 163.4 269.539 163C270.039 162.5 271.039 161.5 271.039 161C271.039 160.6 271.706 159.5 272.039 159V155V150L271.539 145.5L272.539 141L272.039 137.5L272.539 126L273.539 122.5L275.539 120.5L276.039 113H275.539L262.539 122.5L261.039 121.5L259.539 121L254.039 127.5L252.539 128.5L250.039 129.5L246.539 130L246.039 130.5L243.039 134.5H239.039L238.539 137L235.039 139.5L233.039 143.5L228.539 145.5L226.039 148L222.039 151.5L217.039 154L213.539 156L212.539 157.5L211.539 159.5C211.039 159.833 210.039 160.7 210.039 161.5C210.039 162.3 209.039 163.833 208.539 164.5L207.539 167.5L208.539 170L209.039 172.5L209.539 174L210.039 176.5L209.039 178.5L208.039 181C208.206 181.333 208.639 182.1 209.039 182.5C209.439 182.9 209.539 183.333 209.539 183.5L209.039 185L208.539 188C208.873 188.167 209.539 188.6 209.539 189C209.539 189.4 210.539 190.167 211.039 190.5L213.039 193.5L213.789 199.75L214.539 198.5L217.039 198L219.539 197.5L220.039 196.5L221.039 195.5L222.039 194L223.039 192.5L224.539 191.5L227.039 191H229.039H233.039L236.539 190.5L238.039 190L239.039 189L240.039 187.5L241.039 186.5L241.539 185L242.539 184.5L244.039 183.5H244.539C244.939 183.5 245.706 183.167 246.039 183H247.039L247.789 182.25L248.539 181.5C249.373 181.5 251.039 181.4 251.039 181C251.039 180.6 252.706 179.833 253.539 179.5C253.873 179.167 254.639 178.5 255.039 178.5C255.439 178.5 256.539 177.833 257.039 177.5L259.039 176.5Z" fill="#388E3C"/>
                <path id="LAGUERTA" d="M208.539 170C207.873 170.333 206.339 171.2 205.539 172C204.739 172.8 202.539 174 201.539 174.5L197.039 180L192.539 183L189.539 188L186.539 191L184.039 192L182.039 193L181.039 198C180.873 198.5 180.339 199.6 179.539 200C178.539 200.5 178.039 199.5 176.539 200.5C175.339 201.3 174.039 201.833 173.539 202L172.039 204L171.839 204.5L171.039 206.5C171.539 207.667 172.539 210.1 172.539 210.5C172.539 210.9 173.206 213.667 173.539 215L174.539 236V238L178.539 235L181.039 234H183.039L187.039 230.5L189.539 228.5L191.539 227L193.539 226.5L195.539 225L199.039 221.5L202.539 221L203.539 220.5L205.039 220V218L205.539 215.5L206.039 211.5L207.039 209.5C207.206 208.5 207.639 206.5 208.039 206.5C208.439 206.5 209.539 205.167 210.039 204.5L211.039 203L212.039 202L213.039 201L213.789 199.75L213.039 193.5L211.039 190.5C210.539 190.167 209.539 189.4 209.539 189C209.539 188.6 208.873 188.167 208.539 188L209.039 185L209.539 183.5C209.539 183.333 209.439 182.9 209.039 182.5C208.639 182.1 208.206 181.333 208.039 181L209.039 178.5L210.039 176.5L209.539 174L209.039 172.5L208.539 170Z" fill="#2ECC71"/>
                <path id="BUBUYAN" d="M187.039 230.5L183.039 234C183.206 234.333 183.639 235.1 184.039 235.5C184.439 235.9 184.539 237 184.539 237.5L185.539 240V242V245L187.539 246.5L188.539 247.5L189.539 247L190.539 246L192.039 245H193.539L194.539 246L196.039 247.5L197.039 249L198.539 250.5L199.039 251.5L199.539 252.5L200.039 253.5L200.539 256.5L201.039 259L200.539 262.5C200.706 263.333 201.139 265.2 201.539 266C201.939 266.8 202.373 267 202.539 267V268.5L202.039 275.5H204.539H207.539C207.373 274.667 207.039 272.9 207.039 272.5C207.039 272.1 207.373 270.667 207.539 270C207.706 269.667 208.039 268.9 208.039 268.5C208.039 268 207.539 266.5 207.539 266C207.539 265.6 207.873 264.5 208.039 264V262.5L211.039 261L213.039 260L215.539 257.5L217.539 256L218.539 255L219.539 254.5L221.539 253.5L222.539 252.5L224.039 252V246.5L224.539 245.5C224.539 245.333 224.639 245 225.039 245H227.039L230.039 243L232.039 242L233.039 241L235.039 240.5H238.539L237.539 239L236.539 237.5C236.206 236.667 235.539 234.9 235.539 234.5C235.539 234 234.539 232.5 234.539 232C234.539 231.6 233.873 230.5 233.539 230V227.5L235.039 225.5L237.039 223.5L239.539 222L241.539 221.5L242.039 221L244.039 219.5L245.539 218L247.539 216.5L248.539 215L250.039 213L250.539 211.5V210.5V208V205.5V204.5C250.539 204.1 251.206 203.667 251.539 203.5L252.039 202.5L251.539 201L251.039 199.5L250.039 198L249.539 196.5V195L250.039 193L250.539 192V190.5L250.039 189L249.039 188L248.539 186.5L248.039 184.5L247.789 182.25L247.039 183H246.039C245.706 183.167 244.939 183.5 244.539 183.5H244.039L242.539 184.5L241.539 185L241.039 186.5L240.039 187.5L239.039 189L238.039 190L236.539 190.5L233.039 191H229.039H227.039L224.539 191.5L223.039 192.5L222.039 194L221.039 195.5L220.039 196.5L219.539 197.5L217.039 198L214.539 198.5L213.789 199.75L213.039 201L212.039 202L211.039 203L210.039 204.5C209.539 205.167 208.439 206.5 208.039 206.5C207.639 206.5 207.206 208.5 207.039 209.5L206.039 211.5L205.539 215.5L205.039 218V220L203.539 220.5L202.539 221L199.039 221.5L195.539 225L193.539 226.5L191.539 227L189.539 228.5L187.039 230.5Z" fill="#27AE60"/>
                <path id="BUROL" d="M158.539 265.5V270.5L177.539 272L185.039 272.5L191.039 274.5L196.039 275.5H202.039L202.539 268.5V267C202.373 267 201.939 266.8 201.539 266C201.139 265.2 200.706 263.333 200.539 262.5L201.039 259L200.539 256.5L200.039 253.5L199.539 252.5L199.039 251.5L198.539 250.5L197.039 249L196.039 247.5L194.539 246L193.539 245H192.039L190.539 246L189.539 247L188.539 247.5L187.539 246.5L185.539 245V242V240L184.539 237.5C184.539 237 184.439 235.9 184.039 235.5C183.639 235.1 183.206 234.333 183.039 234H181.039L178.539 235L174.539 238L172.539 240L170.039 245L168.039 247L167.039 249L164.539 252L162.539 255V255.5L159.539 259C159.206 260.833 158.539 264.7 158.539 265.5Z" fill="#06402B"/>
                <path id="KAY-ANLOG" d="M240.039 241L238.539 240.5H235.039L233.039 241L232.039 242L230.039 243L227.039 245H225.039C224.639 245 224.539 245.333 224.539 245.5L224.039 246.5V252L222.539 252.5L221.539 253.5L219.539 254.5L218.539 255L217.539 256L215.539 257.5L213.039 260L211.039 261L208.039 262.5V264C207.873 264.5 207.539 265.6 207.539 266C207.539 266.5 208.039 268 208.039 268.5C208.039 268.9 207.706 269.667 207.539 270C207.373 270.667 207.039 272.1 207.039 272.5C207.039 272.9 207.373 274.667 207.539 275.5H213.039L223.539 276L227.539 279L238.539 287L240.039 287.5C240.373 286.5 241.039 284.4 241.039 284C241.039 283.6 241.706 282.5 242.039 282L245.539 279L249.539 276L253.539 274.5L259.039 271.5L269.039 266L271.539 264.5L275.039 262L279.539 260L282.539 258.5L287.539 256.5L289.039 255L290.539 253.5L292.039 251.5L297.539 248V246L296.539 235.5L297.039 231.5C296.373 231.167 294.939 230.5 294.539 230.5H293.539C293.039 230.333 291.939 229.9 291.539 229.5L290.539 228.5L290.039 223.5L289.039 222L287.539 219.5L286.539 218L284.539 217.5L280.539 218L280.039 219L278.539 220H276.539H274.039L272.039 221L271.039 222.5L270.039 224L269.039 226.5L268.039 228.5L266.039 231L264.539 232C264.206 232 263.439 231.9 263.039 231.5L262.539 231L262.039 230.5H261.039L257.039 233.5L256.039 235V236L255.539 237.5L254.539 238L253.039 238.5H251.039L249.539 238H248.539H247.039L245.539 238.5L244.539 239L243.539 240L242.539 241H241.039H240.039Z" fill="#39FF14"/>
                <path id="MAKILING" d="M351.039 224.5L297.539 248L298.539 249.5L300.039 251.5L301.039 254.5L302.539 256.5L303.039 280L302.539 283L301.039 288L300.539 290.5L299.039 293.5L298.039 297L295.039 301L290.039 306.5L289.539 311.5L290.039 314.5L290.539 319L294.039 321.5H300.039L304.539 319L308.039 316L314.039 314H319.539L327.039 317L338.039 321.5L339.539 319.5V316.5L340.039 315L340.539 313L341.039 310.5V308.5C340.706 308.167 340.039 307.4 340.039 307C340.039 306.6 339.706 304.833 339.539 304L339.039 301L338.539 297.5V295L339.039 293V291L339.539 289.5V287L340.039 285.5V284V282.5L340.539 280.5L341.539 278L343.039 274.5L346.039 268.5L348.539 264.5L350.539 262L352.039 258.5L356.039 253.5L358.039 250.5L361.539 247L364.539 244L365.539 242.5L361.539 242L359.539 240.5L357.039 237.5L356.039 235.5L355.539 233L355.039 230L354.539 228.5L353.039 225.5L351.039 224.5Z" fill="#00FF7F"/>
                <path id="SAIMSIM" d="M338.039 321.5L352.039 327V326.5V323.5L354.039 320L355.539 317C356.206 316 357.639 313.9 358.039 313.5C358.439 313.1 359.873 311.333 360.539 310.5L365.039 305L368.539 301L371.539 296.5L373.539 292.5V289L374.539 286L377.539 278L379.039 274L380.039 270.5L381.039 269L383.539 267L386.039 265.5V262.5L385.039 260.5L382.039 258L379.539 255L377.539 252.5L373.539 251L369.039 250.5L368.539 248V246L367.539 244L365.539 242.5L364.539 244L361.539 247L358.039 250.5L356.039 253.5L352.039 258.5L350.539 262L348.539 264.5L346.039 268.5L343.039 274.5L341.539 278L340.539 280.5L340.039 282.5V284V285.5L339.539 287V289.5L339.039 291V293L338.539 295V297.5L339.039 301L339.539 304C339.706 304.833 340.039 306.6 340.039 307C340.039 307.4 340.706 308.167 341.039 308.5V310.5L340.539 313L340.039 315L339.539 316.5V319.5L338.039 321.5Z" fill="#4ADE80"/>
                <path id="CAMALIGAN" d="M450.039 311L447.039 310L439.039 304.5L437.039 302.5L435.539 301L434.039 297.5L432.539 296.5L423.539 293.5L418.039 291.5L414.539 290L411.039 288L407.039 285L404.539 282.5L400.539 281L396.539 278L393.039 275L391.539 272L390.539 270.5L390.039 269L386.039 265.5L383.539 267L381.039 269L380.039 270.5L379.039 274L377.539 278L374.539 286L373.539 289V292.5L371.539 296.5L368.539 301L365.039 305L360.539 310.5C359.873 311.333 358.439 313.1 358.039 313.5C357.639 313.9 356.206 316 355.539 317L354.039 320L352.039 323.5V326.5V327H360.539L367.539 324L369.539 322.5H372.539L375.539 323L379.039 324L382.039 324.5L384.539 325.5L389.039 328L390.539 327.5L393.539 327L397.039 326.5C398.206 326.833 400.639 327.5 401.039 327.5C401.439 327.5 403.206 328.167 404.039 328.5L430.039 337L450.039 311Z" fill="#22C55E"/>
                <path id="PUTING LUPA" d="M501.539 223.5L497.039 222.5L478.039 217.5L461.539 212.5L448.039 207.5L444.039 212.5L440.539 216L437.539 217.5L434.539 220L429.539 225L425.539 229L421.539 233L419.039 235L417.039 237V238L416.539 240.5L410.539 246L407.539 248.5L405.539 249.5L404.039 250.5L403.039 252.5V255L402.539 256.5L401.039 258.5L399.539 260.5L398.039 262L395.039 264L392.539 266.5L390.039 269L390.539 270.5L391.539 272L393.039 275L396.539 278L400.539 281L404.539 282.5L407.039 285L411.039 288L414.539 290L418.039 291.5L423.539 293.5L432.539 296.5L434.039 297.5L435.539 301L437.039 302.5L439.039 304.5L447.039 310L450.039 311L453.039 308.5L458.539 301L472.539 283L481.539 271.5L486.039 266L493.539 256.5L495.039 255L497.539 252.5L501.039 249.5L505.039 245.5L503.039 243.5L501.539 240.5L501.039 238L501.539 224V223.5Z" fill="#16A34A"/>
                <path id="MAUNONG" d="M390.039 269L392.539 266.5L395.039 264L398.039 262L399.539 260.5L401.039 258.5L402.539 256.5L403.039 255V252.5L404.039 250.5L405.539 249.5L407.539 248.5L410.539 246L416.539 240.5L417.039 238V237L419.039 235L421.539 233L425.539 229L429.539 225L434.539 220L437.539 217.5L440.539 216L444.039 212.5L448.039 207.5L440.539 202L437.539 200L435.539 198L433.039 196L431.539 194.5L431.039 193L429.539 190.5L428.539 188.5L427.039 186.5L424.539 185.5L423.539 185L415.039 176L413.039 178.5L410.539 181.5L408.539 183L405.539 184L396.039 187L380.039 192.5L346.393 203.5L353.039 225.5L354.539 228.5L355.039 230L355.539 233L356.039 235.5L357.039 237.5L359.539 240.5L361.539 242L365.539 242.5L367.539 244L368.539 246V248L369.039 250.5L373.539 251L377.539 252.5L379.539 255L382.039 258L385.039 260.5L386.039 262.5V265.5L390.039 269Z" fill="#ADFF2F"/>
                <path id="SUCOL" d="M479.039 175.5C478.373 175.167 477.039 174.4 477.039 174L448.039 207.5L461.539 212.5L478.039 217.5L497.039 222.5L501.539 223.5V221L501.039 214.5L500.539 210L500.039 205L500.539 201.5L501.789 198L501.039 196.5V194L501.539 191L502.539 188L504.539 183.5L503.039 182.5L499.539 180.5L495.539 176.5L494.039 173.5L492.539 171V169.5L490.539 168H489.039L487.539 168.5L487.039 169.5V170.5V172V173.5C487.039 173.9 486.373 174.667 486.039 175L485.539 177L482.539 177.5L480.039 177L479.039 175.5Z" fill="#CCFF00"/>
                <path id="BAGONG KALSADA" d="M528.039 206.5H523.539H519.039L515.539 206L512.539 204L504.539 200.5L502.539 199.5L501.789 198L500.539 201.5L500.039 205L500.539 210L501.039 214.5L501.539 221V223.5V224L501.039 238L501.539 240.5L503.039 243.5L505.039 245.5L509.039 241.5L512.039 237.5L515.539 233.5L519.039 230L523.539 225.5L525.039 222.5L526.539 218.5L528.039 212.5V206.5Z" fill="#9ACD32"/>
                <path id="MASILI" d="M507.039 185L504.539 183.5L502.539 188L501.539 191L501.039 194V196.5L501.789 198L502.539 199.5L504.539 200.5L512.539 204L515.539 206L519.039 206.5H523.539H528.039L529.039 205L529.539 202L529.039 182L527.039 183.5L524.039 185L521.039 186.5C519.539 186.667 516.439 187 516.039 187C515.639 187 513.539 186.667 512.539 186.5C511.706 186.333 509.939 186 509.539 186C509.139 186 507.706 185.333 507.039 185Z" fill="#A3E635"/>
                <path id="PANSOL" d="M460.039 158C460.039 157.6 459.039 157.167 458.539 157L423.539 185L424.539 185.5L427.039 186.5L428.539 188.5L429.539 190.5L431.039 193L431.539 194.5L433.039 196L435.539 198L437.539 200L440.539 202L448.039 207.5L477.039 174C477.039 173.6 476.373 173.167 476.039 173L475.039 171.5L474.039 171L472.539 171.5L471.539 172L470.539 173H468.539H467.539L466.539 171.5L465.539 170L463.539 167.5L462.539 166L461.539 163.5L460.539 160C460.373 159.5 460.039 158.4 460.039 158Z" fill="#BEF264"/>
                <path id="BUCAL" d="M456.039 154L454.539 152.5C454.373 153 453.939 154 453.539 154C453.039 154 451.039 154.5 450.539 154.5C450.139 154.5 448.039 154.833 447.039 155H444.539L442.539 154.5C442.039 154.167 440.939 153.5 440.539 153.5C440.139 153.5 438.706 152.833 438.039 152.5L436.539 152L434.039 153L432.539 153.5L431.039 153L429.539 152.5L427.539 152L426.539 151.5H422.539L421.039 152L419.249 153C419.012 153.167 418.439 153.6 418.039 154C417.639 154.4 415.873 154.167 415.039 154L412.539 153.5L410.539 152.5L410.039 151C410.039 150.667 409.939 149.9 409.539 149.5L408.539 148.5L407.539 146.5L406.539 145L404.039 138.5L404.539 137.5L401.039 139L398.039 142L395.539 144.5L393.539 147.5L391.539 149.5L393.039 150.5L395.539 152.5L415.039 176L423.539 185L458.539 157L457.039 155L456.039 154Z" fill="#D9F99D"/>
                <path id="LAMESA" d="M376.039 147L365.539 143L364.539 142.5L361.539 144L359.039 145L356.039 146L355.039 146.5L353.539 147L351.039 148L349.539 149L349.039 149.5V151V153.5V155.5L348.539 158L348.039 160L347.539 161L346.039 163L343.539 166L340.539 173L338.539 177.5L346.393 203.5L380.039 192.5L396.039 187L405.539 184L408.539 183L410.539 181.5L413.039 178.5L415.039 176L395.539 152.5L393.039 150.5L391.539 149.5L390.039 150.5L386.539 150L383.539 149.5L381.039 149L378.539 148.5L376.039 147Z" fill="#84CC16"/>
                <path id="MILAGROSA" d="M308.039 182.5L301.039 192L286.539 218L287.539 219.5L289.039 222L290.039 223.5L290.539 228.5L291.539 229.5C291.939 229.9 293.039 230.333 293.539 230.5H294.539C294.939 230.5 296.373 231.167 297.039 231.5L296.539 235.5L297.539 246V248L351.039 224.5L353.039 225.5L346.393 203.5L338.539 177.5H337.039H330.539L327.039 178.5L323.539 181L320.039 182.5H308.039Z" fill="#65A30D"/>
                <path id="PUNTA" d="M301.039 192L286.539 178.5L285.539 179.5C285.539 179.667 285.439 180.1 285.039 180.5C284.639 180.9 283.873 181.333 283.539 181.5C282.873 182 281.539 182.9 281.539 182.5C281.539 182 280.539 181 280.039 181C279.639 181 277.873 180.667 277.039 180.5L274.539 182.5H272.039C271.239 182.5 269.706 181.833 269.039 181.5H267.039C265.539 181.5 266.039 181.5 265.039 181C264.239 180.6 263.373 179.5 263.039 179C262.373 178.833 260.939 178.4 260.539 178L259.039 176.5L257.039 177.5C256.539 177.833 255.439 178.5 255.039 178.5C254.639 178.5 253.873 179.167 253.539 179.5C252.706 179.833 251.039 180.6 251.039 181C251.039 181.4 249.373 181.5 248.539 181.5L247.789 182.25L248.039 184.5L248.539 186.5L249.039 188L250.039 189L250.539 190.5V192L250.039 193L249.539 195V196.5L250.039 198L251.039 199.5L251.539 201L252.039 202.5L251.539 203.5C251.206 203.667 250.539 204.1 250.539 204.5V205.5V208V210.5V211.5L250.039 213L248.539 215L247.539 216.5L245.539 218L244.039 219.5L242.039 221L241.539 221.5L239.539 222L237.039 223.5L235.039 225.5L233.539 227.5V230C233.873 230.5 234.539 231.6 234.539 232C234.539 232.5 235.539 234 235.539 234.5C235.539 234.9 236.206 236.667 236.539 237.5L237.539 239L238.539 240.5L240.039 241H241.039H242.539L243.539 240L244.539 239L245.539 238.5L247.039 238H248.539H249.539L251.039 238.5H253.039L254.539 238L255.539 237.5L256.039 236V235L257.039 233.5L261.039 230.5H262.039L262.539 231L263.039 231.5C263.439 231.9 264.206 232 264.539 232L266.039 231L268.039 228.5L269.039 226.5L270.039 224L271.039 222.5L272.039 221L274.039 220H276.539H278.539L280.039 219L280.539 218L284.539 217.5L286.539 218L301.039 192Z" fill="#4D7C0F"/>
                <path id="BARANDAL" d="M293.539 109V102L290.539 103L288.039 104.5L286.039 107L283.539 108.5L280.039 109.5L276.039 113L275.539 120.5L273.539 122.5L272.539 126L272.039 137.5L272.539 141L271.539 145.5L272.039 150V155V159C271.706 159.5 271.039 160.6 271.039 161C271.039 161.5 270.039 162.5 269.539 163C269.139 163.4 268.039 163.833 267.539 164H265.539C265.206 164.167 264.539 164.6 264.539 165C264.539 165.4 263.873 166.5 263.539 167L262.039 169L260.539 170.5L260.039 173.5L259.039 176.5L260.539 178C260.939 178.4 262.373 178.833 263.039 179C263.373 179.5 264.239 180.6 265.039 181C266.039 181.5 265.539 181.5 267.039 181.5H269.039C269.706 181.833 271.239 182.5 272.039 182.5H274.539L277.039 180.5C277.873 180.667 279.639 181 280.039 181C280.539 181 281.539 182 281.539 182.5C281.539 182.9 282.873 182 283.539 181.5C283.873 181.333 284.639 180.9 285.039 180.5C285.439 180.1 285.539 179.667 285.539 179.5L286.539 178.5L301.039 192L308.039 182.5L309.039 174L309.539 167L310.539 163L313.039 159.5L314.039 156L317.539 151.5L319.039 148.5L317.539 147L314.539 146L312.539 143L309.539 141.5L306.539 139H302.539L297.539 137L292.039 131.5L286.539 123.5L287.539 120.5L291.539 116L293.539 109Z" fill="#556B2F"/>
                <path id="TURBINA" d="M363.539 142L361.539 139L347.539 133.5L345.539 130.5L344.539 128.5L340.539 135H339.039L337.539 136L336.039 138L334.039 139V142L331.539 143L328.039 144L324.039 146L319.039 148.5L317.539 151.5L314.039 156L313.039 159.5L310.539 163L309.539 167L309.039 174L308.039 182.5H320.039L323.539 181L327.039 178.5L330.539 177.5H337.039H338.539L340.539 173L343.539 166L346.039 163L347.539 161L348.039 160L348.539 158L349.039 155.5V153.5V151V149.5L349.539 149L351.039 148L353.539 147L355.039 146.5L356.039 146L359.039 145L361.539 144L364.539 142.5L363.539 142Z" fill="#6B8E23"/>
                <path id="LECHERIA" d="M414.039 100L398.039 105V105.5L390.539 110.5L388.539 112.5L384.039 121.5L380.539 133.5L378.039 138L376.039 147L378.539 148.5L381.039 149L383.539 149.5L386.539 150L390.039 150.5L391.539 149.5L393.539 147.5L395.539 144.5L398.039 142L401.039 139L404.539 137.5L407.539 137L410.539 136L414.039 134.5L416.039 134L418.039 133.5L420.039 133L423.039 132.5H426.039C426.373 132.333 427.139 132 427.539 132C427.939 132 429.373 131.333 430.039 131L431.539 130L433.039 129.5L434.539 129H437.039L439.039 129.5L442.039 130.5L445.539 131.5L448.039 132.5L451.539 134.5L453.539 136L454.539 137.5L456.039 137L458.539 136L464.539 136.5L469.539 137.5H473.039L474.039 128.5L469.539 118.5L470.039 112.5L447.539 113L445.539 110L438.676 104L422.539 105V102L414.039 100Z" fill="#06402B"/>
                <path id="REAL" d="M398.039 105L393.539 96.5L391.039 98L388.539 98.5L387.039 99.5L385.039 101L383.039 102L381.539 104L380.539 106L379.039 108L377.039 110.5L375.539 109V106L374.039 104L369.539 101L362.539 108V113V118.5L359.039 121.5L355.039 123.5L350.039 124.5L346.539 125.5L344.539 126.5V128.5L345.539 130.5L347.539 133.5L361.539 139L363.539 142L364.539 142.5L365.539 143L376.039 147L378.039 138L380.539 133.5L384.039 121.5L388.539 112.5L390.539 110.5L398.039 105.5V105Z" fill="#808000"/>
                <path id="HALANG" d="M453.539 140L454.539 137.5L453.539 136L451.539 134.5L448.039 132.5L445.539 131.5L442.039 130.5L439.039 129.5L437.039 129H434.539L433.039 129.5L431.539 130L430.039 131C429.373 131.333 427.939 132 427.539 132C427.139 132 426.373 132.333 426.039 132.5H423.039L420.039 133L418.039 133.5L416.039 134L414.039 134.5L410.539 136L407.539 137L404.539 137.5L404.039 138.5L406.539 145L407.539 146.5L408.539 148.5L409.539 149.5C409.939 149.9 410.039 150.667 410.039 151L410.539 152.5L412.539 153.5L415.039 154C415.873 154.167 417.639 154.4 418.039 154C418.439 153.6 419.012 153.167 419.249 153L421.039 152L422.539 151.5H426.539L427.539 152L429.539 152.5L431.039 153L432.539 153.5L434.039 153L436.539 152L438.039 152.5C438.706 152.833 440.139 153.5 440.539 153.5C440.939 153.5 442.039 154.167 442.539 154.5L444.539 155H447.039C448.039 154.833 450.139 154.5 450.539 154.5C451.039 154.5 453.039 154 453.539 154C453.939 154 454.373 153 454.539 152.5L454.039 152L452.539 149.5L451.539 147L451.039 146V144.5L452.039 143.5L452.539 142L453.539 140Z" fill="#8FBC8F"/>
                <path id="LINGGA" d="M452.039 75.5L449.039 88.5L447.539 96.5L445.539 104V110L447.539 113L470.039 112.5L471.039 91.5L475.539 86L477.539 82L458.324 57.5L457.539 56.5V61L452.039 75.5Z" fill="#2F4F4F"/>
                <path id="SAN JOSE" d="M424.039 85L422.539 102V105L438.676 104L445.539 110V104L447.539 96.5L449.039 88.5L452.039 75.5C443.039 77.5 424.839 85 424.039 85Z" fill="#556B2F"/>
                <path id="BARANGAY 5" d="M429.539 67L426.039 69L415.539 71.5L411.539 74L410.039 78.5L412.039 88.5L414.039 100L422.539 102L424.039 85L429.539 71.5V67Z" fill="#A2AD91"/>
                <path id="SAN JUAN" d="M440.539 53.5L436.539 59.5L432.539 64.5L429.539 67V71.5L424.039 85C424.839 85 443.039 77.5 452.039 75.5L457.539 61V56.5L451.551 45.0216C451.487 45.0541 451.425 45.0853 451.367 45.115L450.039 46L440.539 50V53.5Z" fill="#8A9A5B"/>
                <path id="PANLINGON" d="M480.539 43.5L477.039 45.115L474.539 46.5L472.539 47.5L471.039 49L469.039 51L467.539 53L466.039 55L461.539 55.5L458.324 57.5L477.539 82L482.539 82.5L492.539 75.5L493.039 73.5L491.539 72L490.539 69.5L487.039 66L485.039 64C484.873 63.5 484.539 62.4 484.539 62C484.539 61.6 484.206 60.8333 484.039 60.5L483.539 58.5L481.539 57.5L480.539 57L481.039 55V51.5V48.5C481.039 48.1 481.373 47 481.539 46.5L480.539 43.5Z" fill="#98FB98"/>
                <path id="SAMPIRUHAN" d="M477.539 32.5L473.039 34L464.039 38.5C461.539 39.6667 456.439 42.1 456.039 42.5C455.656 42.8831 452.996 44.2845 451.551 45.0216L457.539 56.5L458.324 57.5L461.539 55.5L466.039 55L467.539 53L469.039 51L471.039 49L472.539 47.5L474.539 46.5L477.039 45.115L480.539 43.5L479.039 38.5L477.539 32.5Z" fill="#00FA9A"/>
                <path id="LOOC" d="M437.226 40.7692L438.676 43.5L440.539 50L450.039 46L451.367 45.115L451.539 45L451.551 45.0216C452.996 44.2845 455.656 42.8831 456.039 42.5C456.439 42.1 461.539 39.6667 464.039 38.5L473.039 34L477.539 32.5L478.539 30.5L479.039 25.5L477.539 23L476.539 22L475.039 19.5L470.539 16L469.039 14.5V9.5V7L468.039 6L466.039 5.5L461.539 5L455.039 3.5L453.289 3L453.039 9.5L451.039 12.5L448.539 14L442.539 18L430.039 24.5L433.539 30.5L437.226 40.7692Z" fill="#3EB489"/>
                <path id="UWISAN" d="M425.539 5.5L422.039 6.5L421.289 7L430.039 24.5L442.539 18L448.539 14L451.039 12.5L453.039 9.5L453.289 3L451.539 2.5L450.039 2L444.539 1L441.039 0.5H438.039L434.539 2.5C433.039 3.16667 429.839 4.6 429.039 5C428.239 5.4 426.373 5.5 425.539 5.5Z" fill="#9ACD32"/>
                <path id="BANADERO" d="M404.039 47.5L398.039 53.5H397.618L398.039 75.5C400.039 75.3333 404.239 75 405.039 75C405.839 75 409.706 74.3333 411.539 74L415.539 71.5L426.039 69L429.539 67L432.539 64.5L436.539 59.5L440.539 53.5V50L437.226 40.7692L436.039 38H427.539L418.539 38.5L411.039 41.5L404.039 47.5Z" fill="#CCFF00"/>
                <path id="PARIAN" d="M336.039 59.5C335.639 59.5 332.539 60.5 331.039 61L331.155 64L369.539 101L371.539 98.5L386.039 91V89.5V89L387.039 81L393.539 77.5L398.039 75.5L397.618 53.5H393.539L388.539 55L380.039 61L374.039 64.5C370.142 60.6029 365.85 60.4419 362.039 61.7206C356.071 63.723 351.283 69.2559 351.039 69.5C350.639 69.9 349.539 69.6667 349.039 69.5L347.539 59.5L346.539 57.5L344.039 56.5L340.539 57.5C339.206 58.1667 336.439 59.5 336.039 59.5Z" fill="#00A36C"/>
                <path id="BARANGAY 1" d="M388.539 90.5L386.039 89V89.5V91L371.539 98.5L369.539 101L374.039 104L375.539 106V109L377.039 110.5L379.039 108L380.539 106L381.539 104L383.039 102L385.039 101L387.039 99.5L388.539 98.5L391.039 98L393.539 96.5L388.539 90.5Z" fill="#2AAA8A"/>
                <path id="BARANGAY 2" d="M399.039 81L388.539 90.5L393.539 96.5L397.618 94.0526V86L399.039 81Z" fill="#C8E6C9"/>
                <path id="BARANGAY 3" d="M412.039 88.5L406.039 89L397.618 94.0526L393.539 96.5L398.039 105L414.039 100L412.039 88.5Z" fill="#DCEDC8"/>
                <path id="BARANGAY 4" d="M410 78.5L412 88.5238L406 89L404 78.5H410Z" fill="#E8F5E9"/>
                <path id="BARANGAY 5" d="M429.539 67L426.039 69L415.539 71.5L411.539 74L410.039 78.5L412.039 88.5L414.039 100L422.539 102L424.039 85L429.539 71.5V67Z" fill="#A2AD91"/>
                <path id="BARANGAY 6" d="M411.539 74C409.706 74.3333 405.839 75 405.039 75C404.239 75 400.039 75.3333 398.039 75.5L393.539 77.5L387.039 81L386.039 89L388.539 90.5L399.039 81L404.039 78.5H410.039L411.539 74Z" fill="#17B169"/>
                <path id="BARANGAY 7" d="M397.5 86L399 81L404 78.5L406 89.5L397.5 94V86Z" fill="#E8F5E9"/>
                <path id="LAWA" d="M331.539 74L328.539 77.5L329.098 78.0995L342.539 101L347.539 103L351.039 105L352.039 109V113L353.539 116L355.039 114L356.539 113H359.039H362.539V108L369.539 101L331.155 64L331.539 74Z" fill="#F0FFF0"/>
                <path id="BATINO" d="M313.539 87.5L300.539 95L293.539 102V109L291.539 116L287.539 120.5L286.539 123.5L292.039 131.5V129.5L297.539 126L298.539 124L299.039 122L300.539 121L304.539 120L306.539 118.5L309.039 117.5L311.539 116.5L314.539 115L317.539 113.5L325.039 108L328.039 107.5C329.039 107.333 331.139 107 331.539 107C331.939 107 333.706 106 334.539 105.5L335.539 104.5C336.373 104.5 338.139 104.4 338.539 104C338.939 103.6 341.373 101.833 342.539 101L329.098 78.0995L328.539 77.5C327.706 77.6667 325.939 78 325.539 78C325.139 78 321.706 83.6667 320.039 86.5L313.539 87.5Z" fill="#22C55E"/>
                <path id="PRINZA" d="M344.539 128.5V126.5L346.539 125.5L350.039 124.5L355.039 123.5L359.039 121.5L362.539 118.5V113H359.039H356.539L355.039 114L353.539 116L352.039 113V109L351.039 105L347.539 103L342.539 101C341.373 101.833 338.939 103.6 338.539 104C338.139 104.4 336.373 104.5 335.539 104.5L334.539 105.5C333.706 106 331.939 107 331.539 107C331.139 107 329.039 107.333 328.039 107.5L325.039 108L317.539 113.5L314.539 115L311.539 116.5L309.039 117.5L306.539 118.5L304.539 120L300.539 121L299.039 122L298.539 124L297.539 126L292.039 129.5V131.5L297.539 137L302.539 139H306.539L309.539 141.5L312.539 143L314.539 146L317.539 147L319.039 148.5L324.039 146L328.039 144L331.539 143L334.039 142V139L336.039 138L337.539 136L339.039 135H340.539L344.539 128.5Z" fill="#06402B"/>
                <path id="BANLIC" d="M374.039 64.5L380.039 61L388.539 55L393.539 53.5H397.618H398.039L404.039 47.5L411.039 41.5L418.539 38.5L427.539 38H436.039L437.226 40.7692L433.539 30.5L430.039 24.5L421.289 7L420.539 7.5L418.539 9.5L417.039 10.5H412.539H408.539L406.039 12L402.039 13L396.539 14L391.039 15.5L386.539 17.5L381.039 19.5C379.206 20.1667 375.439 21.5 375.039 21.5C374.639 21.5 372.873 22.5 372.039 23L367.539 24.5C366.206 25.1667 363.439 26.5 363.039 26.5C362.639 26.5 360.539 27.8333 359.539 28.5L355.039 29.5L351.039 30H350.539L362.039 61.7206C365.85 60.4419 370.142 60.6029 374.039 64.5Z" fill="#06402B"/>
                <path id="ULANGO" d="M297.539 248L292.039 251.5L290.539 253.5L289.039 255L287.539 256.5L282.539 258.5L279.539 260L275.039 262L271.539 264.5L269.039 266L259.039 271.5L253.539 274.5L249.539 276L245.539 279L242.039 282C241.706 282.5 241.039 283.6 241.039 284C241.039 284.4 240.373 286.5 240.039 287.5L245.539 287L249.039 288L252.039 290H260.039L263.039 290.5L267.039 295.5L269.539 299L274.539 303.5L277.039 305L280.539 306L282.539 307.5L283.539 310L289.539 311.5L290.039 306.5L295.039 301L298.039 297L299.039 293.5L300.539 290.5L301.039 288L302.539 283L303.039 280L302.539 256.5L301.039 254.5L300.039 251.5L298.539 249.5L297.539 248Z" fill="#06402B"/>
                <path id="MAPAGONG" d="M204.539 39L209.539 47L209.039 50.5L210.039 52H216.039L220.039 47L231.539 46.5L234.039 48L238.039 44L247.539 43.5V35.5H256.539L258.539 34L265.039 33.5L266.539 33L278.539 43H285.539L287.039 44.5L287.539 46.5L285.539 47.5L275.039 50L268.539 45.5L266.039 47.5V53L263.539 55.5L257.539 56V59L260.039 62H263.539L272.039 56H273.039L280.091 66L280.539 63.5V61L281.539 58.5C281.873 58.3333 282.939 58.2 284.539 59C286.139 59.8 286.539 60.3333 286.539 60.5L290.539 61.5L292.539 62.5C293.539 62.5 295.539 62.4 295.539 62C295.539 61.6 296.539 61.1667 297.039 61L298.039 60L299.039 60.5H302.039H305.039L305.539 58.5L305.039 56.5L305.539 54L308.539 52L310.039 51.5L311.539 52.5C311.873 53.1667 312.539 54.6 312.539 55C312.539 55.4 313.206 56.1667 313.539 56.5L314.373 57.5L315.539 56L316.539 54V51L313.039 47.5L309.539 45.5L308.039 44.5L306.539 43.5L306.039 42C306.206 41.5 306.539 40.4 306.539 40C306.539 39.6 307.873 38.8333 308.539 38.5V36.5L306.539 34.5L306.039 33V31L307.539 28L308.039 26.5V25L305.039 22.5L303.539 22L301.039 21L297.539 19.5L294.039 18L287.539 13.5L284.539 12.5L280.539 11.5H276.039L274.039 12L272.039 13.5L265.039 16H261.539L258.539 15L256.039 14L254.039 13L251.539 12H250.039L248.539 12.5L247.039 13L245.539 14L245.039 15L244.539 16L243.539 18L242.039 19.5L241.039 21L239.539 22.5L238.039 24.5L236.039 26L234.039 27.5L233.039 28.5C232.039 29.1667 229.939 30.5 229.539 30.5C229.139 30.5 227.039 32.1667 226.039 33L223.539 34.5L219.539 35.5L215.039 36.5L210.039 38L204.539 39Z" fill="#1B5E20"/>
                <path id="SIRANG LUPA" d="M241.539 89L239.539 89.5L231.539 94.5C231.039 94.6667 229.939 95.1 229.539 95.5C229.139 95.9 226.706 97 225.539 97.5L222.039 98.5C221.873 98.8333 221.239 99.6 220.039 100C218.539 100.5 217.539 101 216.539 101.5C215.539 102 213.539 102.5 213.539 103C213.539 103.4 211.539 104.833 210.539 105.5L208.039 106.5L203.539 109L200.039 111L196.539 113.5L194.039 115L189.039 117.5L186.539 119V121.5L188.539 129L189.039 131C189.206 131.167 189.539 131.6 189.539 132C189.539 132.4 189.873 133.167 190.039 133.5L197.539 125H199.039L200.539 125.5L202.539 124.5L204.539 123L207.039 121L210.039 118L213.539 116H215.539H217.539L221.039 113.5L223.539 111C224.206 110.5 225.639 109.4 226.039 109C226.439 108.6 227.873 107.833 228.539 107.5L230.539 106H232.539L233.539 106.5L236.039 100L239.539 96.5L241.539 92V89Z" fill="#228B22"/>
                <path id="SAN CRISTOBAL" d="M329.039 59L331.039 61C332.539 60.5 335.639 59.5 336.039 59.5C336.439 59.5 339.206 58.1667 340.539 57.5L344.039 56.5L346.539 57.5L347.539 59.5L349.039 69.5C349.539 69.6667 350.639 69.9 351.039 69.5C351.283 69.2559 356.071 63.723 362.039 61.7206L350.539 30H349.539L346.539 29.5L344.539 30H343.039L341.039 31L337.539 33L335.539 33.5L332.539 35L328.539 36.5L325.539 37L323.039 38L318.539 38.5H316.539H313.539L310.539 38L308.539 36.5V38.5C307.873 38.8333 306.539 39.6 306.539 40C306.539 40.4 306.206 41.5 306.039 42L306.539 43.5L308.039 44.5L309.539 45.5L313.039 47.5L316.539 51V54L315.539 56L314.373 57.5L316.039 59.5L321.039 62L323.539 61.5C323.873 61.1667 324.639 60.5 325.039 60.5C325.439 60.5 326.206 60.1667 326.539 60L329.039 59Z" fill="#16A34A"/>
                <path id="PACIANO RIZAL" d="M300.539 95L313.539 87.5L320.039 86.5C321.706 83.6667 325.139 78 325.539 78C325.939 78 327.706 77.6667 328.539 77.5L331.539 74L331.155 64L331.039 61L329.039 59L326.539 60C326.206 60.1667 325.439 60.5 325.039 60.5C324.639 60.5 323.873 61.1667 323.539 61.5L321.039 62L316.039 59.5L314.373 57.5L313.539 56.5C313.206 56.1667 312.539 55.4 312.539 55C312.539 54.6 311.873 53.1667 311.539 52.5L310.039 51.5L308.539 52L305.539 54L305.039 56.5L305.539 58.5L305.039 60.5H302.039H299.039L298.039 60L297.039 61C296.539 61.1667 295.539 61.6 295.539 62C295.539 62.4 293.539 62.5 292.539 62.5L290.539 61.5L286.539 60.5C286.539 60.3333 286.139 59.8 284.539 59C282.939 58.2 281.873 58.3333 281.539 58.5L280.539 61V63.5L280.091 66L283.969 71.5L300.539 95Z" fill="#ADFF2F"/>
                </g>
                <defs>
                    <clipPath id="clip0_3_3"><rect width="531" height="338" fill="white"/></clipPath>
                </defs>
            </svg>
        </div>
            </div>
        </div>
        <div id="brgy-label"></div>
    </div>
</div>

<!-- ── POPUP OVERLAY ── -->
<div id="brgy-popup-overlay">
    <div id="brgy-popup"></div>
</div>

<!-- ── DISEASE CASE PHOTO LIGHTBOX ── -->
<div id="brgy-photo-lightbox" onclick="closeBrgyLightbox()">
    <button onclick="event.stopPropagation();closeBrgyLightbox();">&#10005;</button>
    <img id="brgyLightboxImg" src="" alt="Evidence photo, full size">
</div>

<script>
const barangayData = <?php echo json_encode($barangayData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE); ?>;

// ── Shared state for the currently-open barangay popup's report list ──
let currentBrgyCases = [];
let currentBrgyName  = '';
let brgyFilterState  = { status: 'all', search: '', date: '' };

function normalizeName(str) {
    // Strip accents so "Bañadero" (DB) matches the SVG path id "BANADERO".
    // Also repairs the common mojibake form of ñ/Ñ ("Ã±" / "Ã‘") just in case.
    return String(str)
        .replace(/Ã±/g, 'n').replace(/Ã‘/g, 'N')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toUpperCase();
}

function findBarangayByPathId(pathId) {
    const needle = normalizeName(pathId);
    for (const id in barangayData) {
        if (normalizeName(barangayData[id].name) === needle) {
            return { id, data: barangayData[id] };
        }
    }
    return null;
}

// ── APPLY HIGHLIGHT CLASSES ON LOAD ──
document.querySelectorAll('#map-3d-wrap svg path').forEach(path => {
    const match = findBarangayByPathId(path.id);
    if (match && match.data.highlight !== 'none') {
        path.classList.add('status-' + match.data.highlight);
    }
});

// ── CARD FILTER ──
let activeFilter = null;

function applyMapFilter(filterType) {
    const allPaths = document.querySelectorAll('#map-3d-wrap svg path');
    if (!filterType) {
        allPaths.forEach(path => {
            path.classList.remove('map-dimmed','map-highlighted-active','map-highlighted-pending','map-highlighted-verified','map-highlighted-affected');
        });
        return;
    }
    allPaths.forEach(path => {
        const match = findBarangayByPathId(path.id);
        const data  = match ? match.data : null;
        path.classList.remove('map-dimmed','map-highlighted-active','map-highlighted-pending','map-highlighted-verified','map-highlighted-affected','map-highlighted-scan');

        let qualifies = false;
        if (filterType === 'active'   && data && data.total_active   > 0) qualifies = true;
        if (filterType === 'pending'  && data && data.pending_count  > 0) qualifies = true;
        if (filterType === 'verified' && data && data.verified_count > 0) qualifies = true;
        if (filterType === 'affected' && data && data.total_active   > 0) qualifies = true;
        if (filterType === 'ai_scan'  && data && data.ai_scan_count  > 0) qualifies = true;

        if (qualifies) {
            const cls = filterType === 'active'   ? 'map-highlighted-active'
                      : filterType === 'pending'  ? 'map-highlighted-pending'
                      : filterType === 'verified' ? 'map-highlighted-verified'
                      : filterType === 'ai_scan'  ? 'map-highlighted-scan'
                      :                             'map-highlighted-affected';
            path.classList.add(cls);
        } else {
            path.classList.add('map-dimmed');
        }
    });
}

document.querySelectorAll('.dcard.filterable').forEach(card => {
    card.addEventListener('click', function() {
        const filter = this.dataset.filter;
        if (activeFilter === filter) {
            activeFilter = null;
            this.classList.remove('filter-active');
            applyMapFilter(null);
        } else {
            document.querySelectorAll('.dcard.filter-active').forEach(c => c.classList.remove('filter-active'));
            activeFilter = filter;
            this.classList.add('filter-active');
            applyMapFilter(filter);
        }
    });
    card.title = 'Click to filter map';
});

// ── TOOLTIP ──
const label = document.getElementById('brgy-label');
document.querySelectorAll('#map-3d-wrap svg path').forEach(path => {
    path.addEventListener('mouseenter', function() {
        const match = findBarangayByPathId(this.id);
        const name = match ? match.data.name : this.id.replace(/_/g, ' ');
        const data = match ? match.data : null;
        let badge = '';

        if (activeFilter && data) {
            // A map filter is active — show the count for THAT filter only, so a barangay
            // highlighted under "Pending" reads as pending cases, not its overall total.
            const filterInfo = {
                pending:  { count: data.pending_count,  color: '#f97316', label: 'pending',  plural: false },
                verified: { count: data.verified_count, color: '#3b82f6', label: 'verified', plural: false },
                active:   { count: data.total_active,   color: '#16a34a', label: 'active',   plural: false },
                ai_scan:  { count: data.ai_scan_count,  color: '#8b5cf6', label: 'AI scan',  plural: true  },
            }[activeFilter];
            if (filterInfo && filterInfo.count > 0) {
                const suffix = (filterInfo.plural && filterInfo.count > 1) ? 's' : '';
                badge = ` <span style="font-size:10px;background:${filterInfo.color};color:#fff;padding:1px 6px;border-radius:999px;margin-left:6px;">${filterInfo.count} ${filterInfo.label}${suffix}</span>`;
            }
        } else {
            // No filter active — default to the barangay's overall status.
            const total   = data ? data.total_active  : 0;
            const scanCnt = data ? data.ai_scan_count : 0;
            const isPending = data && data.highlight === 'pending';
            if (total > 0) {
                badge = ` <span style="font-size:10px;background:${isPending?'#f97316':'#3b82f6'};color:#fff;padding:1px 6px;border-radius:999px;margin-left:6px;">${total} case${total>1?'s':''}</span>`;
            } else if (scanCnt > 0) {
                badge = ` <span style="font-size:10px;background:#8b5cf6;color:#fff;padding:1px 6px;border-radius:999px;margin-left:6px;">${scanCnt} AI scan${scanCnt>1?'s':''}</span>`;
            }
        }

        label.innerHTML = name.toUpperCase() + badge;
        label.style.display = 'block';
        label.style.opacity = '1';
    });
    path.addEventListener('mousemove', function(e) {
        const vp = document.getElementById('map-viewport').getBoundingClientRect();
        let lx = e.clientX - vp.left + 20;
        let ly = e.clientY - vp.top - 50;
        const lw = label.offsetWidth + 30;
        const lh = label.offsetHeight + 10;
        if (lx + lw > vp.width)  lx = e.clientX - vp.left - lw;
        if (ly < 0)               ly = e.clientY - vp.top + 20;
        if (ly + lh > vp.height)  ly = vp.height - lh;
        label.style.left = lx + 'px';
        label.style.top  = ly + 'px';
    });
    path.addEventListener('mouseleave', function() {
        label.style.display = 'none';
    });

    // ── CLICK: open popup ──
    path.addEventListener('click', function(e) {
        e.stopPropagation();
        const match    = findBarangayByPathId(this.id);
        const brgyName = match ? match.data.name : this.id.replace(/_/g,' ');
        const data     = match ? match.data : null;

        // Status pill (colored accent on a black & white card, like the reference design)
        let statusLabel = 'NO REPORTS YET';
        let statusColor = '#16a34a';
        if (data) {
            if (data.highlight === 'pending')  { statusLabel = 'PENDING';         statusColor = '#f97316'; }
            if (data.highlight === 'verified') { statusLabel = 'CONFIRMED CASES'; statusColor = '#3b82f6'; }
            if (data.highlight === 'none' && data.ai_scan_count > 0) { statusLabel = 'AI SCANS ONLY — NO REVIEW NEEDED'; statusColor = '#8b5cf6'; }
        }
        const statusPill = `<span style="display:inline-flex;align-items:center;gap:5px;background:${statusColor}17;color:${statusColor};font-size:9px;font-weight:900;padding:3px 10px;border-radius:999px;letter-spacing:0.1em;white-space:nowrap;">
            <span style="width:6px;height:6px;border-radius:999px;background:${statusColor};flex-shrink:0;"></span>${statusLabel}
        </span>`;

        const pendingCount  = data ? data.pending_count  : 0;
        const verifiedCount = data ? data.verified_count : 0;
        const totalActive   = data ? data.total_active   : 0;

        const resolvedCount = data ? data.resolved_count : 0;
        const aiScanCount   = data ? data.ai_scan_count  : 0;

        // ── State for this popup instance: the card list re-renders client-side as the
        // person types a search, picks a date, or switches status tabs — no reload needed.
        // If a map filter card (Pending / Verified / AI Scans) is currently active, open the
        // popup straight into that tab instead of All, so the click goes right to the
        // filtered data. "Active Reports" spans two tabs (pending+verified) so it still opens on All. ──
        const filterTabMap  = { pending: 'pending', verified: 'verified', ai_scan: 'ai_scan' };
        const initialStatus = filterTabMap[activeFilter] || 'all';
        currentBrgyCases = (data && data.cases) ? data.cases : [];
        currentBrgyName  = brgyName;
        brgyFilterState  = { status: initialStatus, search: '', date: '' };

        // ── Status tabs — All / Pending / Verified / Resolved / AI Scans, each with its live count.
        // "All" covers manual reports only — AI scans are their own tab since they never
        // move through the pending/verified/resolved review flow. ──
        const statusTabDefs = [
            { key: 'all',      label: 'All',      count: pendingCount + verifiedCount + resolvedCount },
            { key: 'pending',  label: 'Pending',  count: pendingCount },
            { key: 'verified', label: 'Verified', count: verifiedCount },
            { key: 'resolved', label: 'Resolved', count: resolvedCount },
            { key: 'ai_scan',  label: 'AI Scans', count: aiScanCount },
        ];
        const statusTabsHtml = `<div class="brgy-status-tabs">${statusTabDefs.map(t => `
            <button type="button" class="brgy-status-tab${t.key === initialStatus ? ' active-status-tab' : ''}" data-status="${t.key}" onclick="event.stopPropagation();setBrgyStatusFilter('${t.key}');">
                ${t.label}<span class="brgy-status-tab-count">&nbsp;${t.count}</span>
            </button>`).join('')}</div>`;

        // ── Search + date row ──
        const searchRowHtml = `
        <div class="brgy-search-row">
            <div class="brgy-search-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="brgy-search-input" class="brgy-search-input" placeholder="Search by farmer, report number, or crop..." onclick="event.stopPropagation();" oninput="event.stopPropagation();onBrgySearchInput(this.value);">
            </div>
            <div class="brgy-date-wrap">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <input type="date" id="brgy-date-input" class="brgy-date-input" onclick="event.stopPropagation();" onchange="event.stopPropagation();onBrgyDateInput(this.value);">
            </div>
        </div>`;

        const popup = document.getElementById('brgy-popup');
        popup.innerHTML = `
            <!-- Header -->
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:2px;flex-shrink:0;">
                <div style="min-width:0;">
                    <p style="color:#94a3b8;font-size:0.6rem;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;margin:2px 0 4px;">Crop Disease Report</p>
                    <h3 style="color:#0f172a;font-size:1.4rem;font-weight:900;margin:0 0 6px;letter-spacing:-0.01em;">${brgyName}</h3>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="color:#94a3b8;font-size:0.72rem;font-weight:700;">Barangay: ${brgyName}, Calamba City</span>
                        <span style="color:#cbd5e1;">&middot;</span>
                        ${statusPill}
                    </div>
                </div>
                <button id="close-popup" style="width:34px;height:34px;border-radius:999px;background:#fff;border:1px solid #e2e8f0;color:#94a3b8;cursor:pointer;font-size:1rem;line-height:1;flex-shrink:0;margin-left:10px;">&#10005;</button>
            </div>

            ${statusTabsHtml}
            ${searchRowHtml}

            <!-- Scrollable list of report cards -->
            <div id="brgy-report-list" class="brgy-report-list"></div>

            <!-- Footer buttons -->
            <div style="display:flex;gap:10px;margin-top:14px;flex-shrink:0;">
                ${data && data.total_all > 0 ? `
                <a href="reports.php?barangay_id=${match.id}"
                   style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;background:#0f172a;color:#fff;font-size:0.76rem;font-weight:800;padding:12px;border-radius:12px;text-decoration:none;letter-spacing:0.04em;"
                   onclick="event.stopPropagation();">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    View All Reports
                </a>` : ''}
                <button onclick="closePopup()"
                   style="${data && data.total_all > 0 ? '' : 'flex:1;'}display:flex;align-items:center;justify-content:center;gap:8px;background:#fff;border:1px solid #fecaca;color:#dc2626;font-size:0.76rem;font-weight:800;padding:12px 20px;border-radius:12px;cursor:pointer;letter-spacing:0.04em;">
                    Close
                </button>
            </div>
        `;

        renderBrgyReportList();

        document.getElementById('brgy-popup-overlay').style.display = 'block';
        document.getElementById('close-popup').addEventListener('click', closePopup);
    });
});

function closePopup() {
    document.getElementById('brgy-popup-overlay').style.display = 'none';
}

// ── STATUS FILTER: All / Pending / Verified / Resolved — drives both the tab row and the quick-filter pills ──
function setBrgyStatusFilter(status) {
    brgyFilterState.status = status;
    document.querySelectorAll('.brgy-status-tab').forEach(t => t.classList.toggle('active-status-tab', t.dataset.status === status));
    renderBrgyReportList();
}

function onBrgySearchInput(val) {
    brgyFilterState.search = (val || '').trim().toLowerCase();
    renderBrgyReportList();
}

function onBrgyDateInput(val) {
    brgyFilterState.date = val || '';
    renderBrgyReportList();
}

// ── Build a single report card, matching the reference design's card layout ──
function buildBrgyReportCard(c) {
    const isAiScan   = !!c.is_ai_scan;
    const isPending  = c.status === 'pending';
    const isResolved = c.status === 'resolved';

    const iconColor = isAiScan ? '#8b5cf6' : (isPending ? '#f97316' : (isResolved ? '#16a34a' : '#3b82f6'));
    const iconSvg   = isAiScan
        ? `<svg fill="none" viewBox="0 0 24 24" stroke="${iconColor}" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M12 8v8m-4-4h8"/></svg>`
        : isPending
        ? `<svg fill="none" viewBox="0 0 24 24" stroke="${iconColor}" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
        : `<svg fill="none" viewBox="0 0 24 24" stroke="${iconColor}" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`;

    // Status badge — just the current status, not the full history (e.g. a resolved
    // case shows only "Resolved", not "Verified" + "Resolved").
    // AI-scanned cases skip the review-pipeline badges entirely since they never need review.
    const badgeDefs = [];
    if (isAiScan) {
        badgeDefs.push({ text: 'AI Scanned', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)' });
    } else {
        if (isPending)       badgeDefs.push({ text: 'Waiting for Review', color: '#f97316', bg: 'rgba(249,115,22,0.1)' });
        else if (isResolved) badgeDefs.push({ text: 'Resolved', color: '#16a34a', bg: 'rgba(22,163,74,0.1)' });
        else                 badgeDefs.push({ text: 'Verified', color: '#3b82f6', bg: 'rgba(59,130,246,0.1)' });
    }
    const badgesHtml = badgeDefs.map(b => `<span style="background:${b.bg};color:${b.color};font-size:9px;font-weight:800;padding:3px 9px;border-radius:999px;white-space:nowrap;">${b.text}</span>`).join('');

    // Infection rate — falls back to plants_affected / total_plants when the percentage wasn't recorded directly.
    let infectionRate = null;
    if (c.infection_percentage !== null && c.infection_percentage !== undefined && c.infection_percentage !== '') {
        infectionRate = parseFloat(c.infection_percentage);
    } else if (c.plants_affected && c.total_plants) {
        infectionRate = (parseFloat(c.plants_affected) / parseFloat(c.total_plants)) * 100;
    }
    const rateText = (infectionRate !== null && !isNaN(infectionRate)) ? `${infectionRate.toFixed(1)}% infection rate` : 'No infection data';

    // AI scans carry their result as a parsed label + confidence rather than a disease_id / infection rate.
    const diseaseLine = isAiScan
        ? `AI Detected: ${c.ai_label || 'Unclassified'}${c.ai_confidence ? ' &bull; ' + c.ai_confidence + ' confidence' : ''}`
        : `Reported Disease: ${c.disease_name || 'Unidentified'} &bull; ${rateText}`;

    const reportId = 'PH-' + (c.reference_id || c.case_id);

    return `
    <div class="brgy-report-card">
        <div class="brgy-report-icon" style="background:${iconColor}18;border:1px solid ${iconColor}44;">${iconSvg}</div>
        <div style="min-width:0;flex:1;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px;">
                <div style="min-width:0;">
                    <span style="color:#0f172a;font-size:0.86rem;font-weight:800;">${c.farmer_name || 'Unknown Reporter'}</span>
                    <span style="color:#94a3b8;font-size:0.68rem;font-weight:700;margin-left:6px;">${reportId}</span>
                </div>
                <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end;flex-shrink:0;">${badgesHtml}</div>
            </div>
            <p style="color:#64748b;font-size:0.76rem;margin:2px 0;">${diseaseLine}</p>
            <p style="color:#64748b;font-size:0.76rem;margin:2px 0;">Barangay: ${currentBrgyName}</p>
            <p style="color:#94a3b8;font-size:0.7rem;margin:2px 0 8px;">Logged${c.report_date ? ' &bull; ' + c.report_date : ''}</p>
            <a href="reports.php?case_id=${c.case_id}&ref=${c.reference_id}"
               style="color:#2563eb;font-size:0.74rem;font-weight:800;text-decoration:none;"
               onclick="event.stopPropagation();">
                View Full Report
            </a>
        </div>
    </div>`;
}

// ── Filter currentBrgyCases against the active status / search / date state and repaint the list ──
function renderBrgyReportList() {
    const list = document.getElementById('brgy-report-list');
    if (!list) return;

    const { status, search, date } = brgyFilterState;

    const filtered = currentBrgyCases.filter(c => {
        if (status === 'ai_scan') {
            if (!c.is_ai_scan) return false;
        } else {
            if (c.is_ai_scan) return false;
            if (status !== 'all' && c.status !== status) return false;
        }

        if (search) {
            const haystack = [c.farmer_name, c.reference_id, c.case_id, c.disease_name, c.ai_label]
                .filter(Boolean).join(' ').toLowerCase();
            if (!haystack.includes(search)) return false;
        }

        if (date) {
            const parsed = c.report_date ? new Date(c.report_date) : null;
            if (parsed && !isNaN(parsed.getTime())) {
                const iso = parsed.toISOString().slice(0, 10);
                if (iso !== date) return false;
            }
        }

        return true;
    });

    if (filtered.length === 0) {
        list.innerHTML = `<p class="brgy-no-match">${currentBrgyCases.length === 0 ? 'No reports here yet.' : 'No reports match this filter.'}</p>`;
        return;
    }

    list.innerHTML = filtered.map(buildBrgyReportCard).join('');
}
document.getElementById('brgy-popup-overlay').addEventListener('click', function(e) {
    if (e.target === this) closePopup();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('brgy-photo-lightbox').style.display === 'flex') { closeBrgyLightbox(); }
        else { closePopup(); }
    }
});

// ── DISEASE CASE PHOTO LIGHTBOX ──
function openBrgyLightbox(src) {
    document.getElementById('brgyLightboxImg').src = src;
    document.getElementById('brgy-photo-lightbox').style.display = 'flex';
}
function closeBrgyLightbox() {
    document.getElementById('brgy-photo-lightbox').style.display = 'none';
}

// ── LIVE DATE ──
(function(){
    const d = new Date();
    document.getElementById('live-date').textContent =
        d.toLocaleDateString('en-PH',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
})();

// ── 3D MAP TILT ON MOUSE MOVE ──
(function(){
    const vp   = document.getElementById('map-viewport');
    const wrap = document.getElementById('map-3d-wrap');
    if (!vp || !wrap) return;

    const BASE_RX = 28, BASE_RZ = -8;
    const MAX_TILT = 8;

    vp.addEventListener('mousemove', function(e) {
        const rect = vp.getBoundingClientRect();
        const nx = (e.clientX - rect.left) / rect.width  - 0.5;
        const ny = (e.clientY - rect.top)  / rect.height - 0.5;
        const rx = BASE_RX - ny * MAX_TILT;
        const rz = BASE_RZ + nx * MAX_TILT * 0.5;
        wrap.style.transform = `rotateX(${rx}deg) rotateZ(${rz}deg)`;
    });
    vp.addEventListener('mouseleave', function() {
        wrap.style.transform = `rotateX(${BASE_RX}deg) rotateZ(${BASE_RZ}deg)`;
    });
})();
</script>

<?php include "includes/layout-end.php"; ?>