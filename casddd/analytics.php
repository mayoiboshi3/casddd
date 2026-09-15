<?php 
/**
 * analytics.php requires login. The guard runs first, before the API
 * branch below — previously the API (?api=1) ran and exited before
 * includes/layout.php was ever reached, which was the only thing checking
 * login. That meant anyone, logged in or not, could pull all case/farmer
 * data straight from analytics.php?api=1. This closes that.
 */
require_once __DIR__ . "/src/session_guard.php";
require_once __DIR__ . "/src/db_config.php";

/* =========================
   EXPANDED API ENGINE
========================= */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    function q($conn, $sql) {
        $r = $conn->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    /* -------------------------------------------------------------
       DRILL-DOWN ENDPOINT
       Every clickable chart/list item on the dashboard (a barangay,
       a status, a growth stage, a disease, a month, or a single
       recent-log case) calls back here with:
           analytics.php?api=1&drilldown=<type>&value=<value>
       and gets back the matching case rows to render inside the
       detail modal. Kept as one shared endpoint + shared base query
       so every section behaves consistently instead of each chart
       needing its own bespoke handler.
    ------------------------------------------------------------- */
    if (isset($_GET['drilldown'])) {
        $type  = $_GET['drilldown'];
        $value = $_GET['value'] ?? '';

        $allowed = ['barangay', 'disease', 'status', 'stage', 'month', 'case'];
        if (!in_array($type, $allowed, true)) {
            echo json_encode(['error' => 'Invalid drilldown type']);
            exit;
        }

        $baseSql = "
            SELECT dc.reference_id,
                   IFNULL(d.disease_name,'Unknown') disease_name,
                   b.name brgy_name,
                   dc.status,
                   dc.severity,
                   dc.growth_stage,
                   dc.report_date
            FROM disease_cases dc
            LEFT JOIN diseases d ON dc.disease_id = d.disease_id
            LEFT JOIN barangays b ON dc.barangay_id = b.id
        ";

        $whereMap = [
            'barangay' => "WHERE b.name = ?",
            'disease'  => "WHERE IFNULL(d.disease_name,'Unknown') = ?",
            'status'   => "WHERE dc.status = ?",
            'stage'    => "WHERE dc.growth_stage = ?",
            'month'    => "WHERE DATE_FORMAT(dc.report_date,'%b %Y') = ?",
            'case'     => "WHERE dc.reference_id = ?",
        ];

        $sql = $baseSql . " " . $whereMap[$type] . " ORDER BY dc.report_date DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['type' => $type, 'value' => $value, 'cases' => $rows]);
        exit;
    }

    // 1. KPI Metrics
    $kpi = [
        'total_cases'    => q($conn, "SELECT COUNT(*) v FROM disease_cases")[0]['v'] ?? 0,
        'total_farmers'  => q($conn, "SELECT COUNT(*) v FROM farmers")[0]['v'] ?? 0,
        'barangays_hit'  => q($conn, "SELECT COUNT(DISTINCT barangay_id) v FROM disease_cases")[0]['v'] ?? 0,
        'pending_cases'  => q($conn, "SELECT COUNT(*) v FROM disease_cases WHERE status='pending'")[0]['v'] ?? 0,
        'critical_cases' => q($conn, "SELECT COUNT(*) v FROM disease_cases WHERE severity='critical'")[0]['v'] ?? 0,
        'resolved_cases' => q($conn, "SELECT COUNT(*) v FROM disease_cases WHERE status='resolved'")[0]['v'] ?? 0,
    ];

    // 2. Hotspot Analysis (Top 5 Barangay)
    $hotspots = q($conn, "
        SELECT b.name as brgy, COUNT(dc.case_id) as count 
        FROM disease_cases dc 
        JOIN barangays b ON dc.barangay_id = b.id 
        GROUP BY brgy ORDER BY count DESC LIMIT 5
    ");

    // 3. Growth Stage Vulnerability
    $stageVulnerability = q($conn, "
        SELECT growth_stage, COUNT(*) as count 
        FROM disease_cases 
        GROUP BY growth_stage ORDER BY count DESC
    ");

    // 4. Recent Intelligence Feed
    // NOTE: switched to LEFT JOIN so cases with a null disease_id still show up
    // (an inner JOIN here was silently dropping them from the feed).
    $recentLogs = q($conn, "
        SELECT dc.reference_id, IFNULL(d.disease_name,'Unknown') disease_name, dc.report_date 
        FROM disease_cases dc 
        LEFT JOIN diseases d ON dc.disease_id = d.disease_id 
        ORDER BY dc.report_date DESC LIMIT 5
    ");

    // 5. Existing Trends & Distribution
    $trends = q($conn, "SELECT DATE_FORMAT(report_date,'%b %Y') month, MIN(report_date) sort_date, COUNT(*) count FROM disease_cases GROUP BY month ORDER BY sort_date ASC");
    $status = q($conn, "SELECT status, COUNT(*) count FROM disease_cases WHERE status != 'rejected' GROUP BY status");
    $diseaseFreq = q($conn, "SELECT IFNULL(d.disease_name,'Unknown') d_name, COUNT(dc.case_id) count FROM disease_cases dc LEFT JOIN diseases d ON dc.disease_id = d.disease_id GROUP BY d_name ORDER BY count DESC");

    echo json_encode([
        'kpi' => $kpi,
        'trends' => $trends,
        'status' => $status,
        'diseaseFreq' => $diseaseFreq,
        'hotspots' => $hotspots,
        'vulnerability' => $stageVulnerability,
        'recentLogs' => $recentLogs
    ]);
    exit;
}

$pageTitle = "Analytics Intelligence";
include "includes/layout.php";
?>

<style>
    .analytics-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .analytics-card:hover { transform: translateY(-5px); }
    .chart-container { position: relative; height: 300px; width: 100%; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #10b981; border-radius: 10px; }
    .clickable-row { cursor: pointer; }
    .chart-clickable canvas { cursor: pointer; }
</style>

<div class="p-4 md:p-10">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Intelligence Feed
            </div>
            <h2 class="text-4xl font-black text-slate-900 tracking-tighter leading-none">
                Statistical SUPER SHY super owhhhjhjhh <span class="text-emerald-600">Analytics</span>
                Statistical SUPER SHY super owhhhjhjhhhhhhhhhhhhh <span class="text-emerald-600">Analytics</span>
            </h2>
            <p class="text-slate-400 font-bold text-xs mt-2 uppercase tracking-tight">Centralized Diagnostic & Trend Analytics</p>
        </div>
        <button onclick="loadData()" class="bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg active:scale-95">
            ↻ Refresh Database
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <div class="analytics-card bg-white p-7 rounded-[2rem] shadow-sm border border-black/5">
            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Total Incidents</p>
            <h4 id="kpi-total" class="text-4xl font-black mt-1 text-gray-800">0</h4>
        </div>
        <div class="analytics-card bg-amber-500 p-7 rounded-[2rem] shadow-lg border border-black/5">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Pending Verification</p>
            <h4 id="kpi-pending" class="text-4xl font-black mt-1 text-white">0</h4>
        </div>
        <div class="analytics-card bg-rose-600 p-7 rounded-[2rem] shadow-lg border border-black/5">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Critical Cases</p>
            <h4 id="kpi-critical" class="text-4xl font-black mt-1 text-white">0</h4>
        </div>
        <div class="analytics-card bg-emerald-600 p-7 rounded-[2rem] shadow-lg border border-black/5">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Resolved Operations</p>
            <h4 id="kpi-resolved" class="text-4xl font-black mt-1 text-white">0</h4>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl chart-clickable">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                <span class="w-2 h-5 bg-emerald-500 rounded-full"></span> Incident Timeline
            </h3>
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl chart-clickable">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-2 flex items-center gap-2">
                <span class="w-2 h-5 bg-blue-500 rounded-full"></span> Status Distribution
            </h3>
            <p class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-4 ml-4">Click a segment to inspect that status</p>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-5">Sector Hotspots</h3>
            <div id="hotspot-list" class="space-y-4">
                </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl chart-clickable">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-1 text-center">Stage Vulnerability</h3>
            <p class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-4 text-center">Click a slice to inspect that stage</p>
            <div class="chart-container">
                <canvas id="stageChart"></canvas>
            </div>
        </div>

        <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-xl text-white overflow-hidden flex flex-col">
            <h3 class="text-sm font-black text-emerald-400 uppercase tracking-widest mb-1">Intelligence Feed</h3>
            <p class="text-[9px] font-bold text-white/30 uppercase tracking-widest mb-5">Click a case to inspect it</p>
            <div id="recent-logs" class="space-y-6 custom-scrollbar overflow-y-auto pr-2">
                </div>
        </div>

    </div>

    <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-xl mt-8 chart-clickable">
        <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest mb-1 flex items-center gap-2">
            <span class="w-2 h-5 bg-slate-900 rounded-full"></span> Pathogen Frequency Analysis
        </h3>
        <p class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-4 ml-4">Click a bar to inspect that disease</p>
        <div class="chart-container" style="height: 400px;">
            <canvas id="diseaseChart"></canvas>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     DRILL-DOWN MODAL
     Shared by every clickable section (hotspots, recent logs, and
     all four charts). Opened via openDrilldown(type, value, title);
     fetches analytics.php?api=1&drilldown=<type>&value=<value> and
     lists the matching cases.
═══════════════════════════════════════ -->
<div id="drilldownModal" class="fixed inset-0 z-50 items-center justify-center p-4" style="display:none; background:rgba(15,23,42,0.6);" onclick="if(event.target===this) closeDrilldown()">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden" onclick="event.stopPropagation()">
        <div class="p-6 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <p class="text-[9px] font-black uppercase tracking-widest text-emerald-500 mb-1">Case Drill-Down</p>
                <h3 id="drilldown-title" class="text-xl font-black text-slate-900 tracking-tight">—</h3>
            </div>
            <button onclick="closeDrilldown()" class="text-gray-400 hover:text-gray-700 font-black text-xl leading-none px-2">×</button>
        </div>
        <div id="drilldown-body" class="p-6 overflow-y-auto custom-scrollbar space-y-3">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Loading…</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let charts = {};
const COLORS = {
    emerald: '#10b981',
    amber: '#f59e0b',
    rose: '#e11d48',
    slate: '#0f172a',
    blue: '#2563eb',
    indigo: '#6366f1'
};

const SEVERITY_COLORS = { critical: 'bg-rose-600', moderate: 'bg-amber-500', mild: 'bg-emerald-500' };
const STATUS_COLORS   = { pending: 'bg-amber-500', verified: 'bg-blue-600', resolved: 'bg-emerald-600', rejected: 'bg-gray-400' };

// Global Chart Defaults
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.weight = '700';
Chart.defaults.color = '#94a3b8';

async function loadData() {
    try {
        const res = await fetch("analytics.php?api=1");
        const data = await res.json();

        // Update KPIs
        document.getElementById("kpi-total").innerText = data.kpi.total_cases;
        document.getElementById("kpi-critical").innerText = data.kpi.critical_cases;
        document.getElementById("kpi-pending").innerText = data.kpi.pending_cases;
        document.getElementById("kpi-resolved").innerText = data.kpi.resolved_cases;

        // Render Lists
        buildHotspots(data.hotspots);
        buildRecentLogs(data.recentLogs);

        // Build Charts
        buildTrend(data.trends);
        buildStatus(data.status);
        buildStageChart(data.vulnerability);
        buildDisease(data.diseaseFreq);
    } catch (e) {
        console.error("Database connection failed", e);
    }
}

/* ═══════════════════════════════════════
   SHARED DRILL-DOWN CLICK WIRING
   Uses data-* attributes + addEventListener instead of inline
   onclick="...${JSON.stringify(...)}..." — JSON.stringify() wraps
   values in double quotes, which broke out of the double-quoted
   onclick="" attribute and silently killed the click handler for
   any reference_id / barangay name. escapeAttr() also guards
   against HTML injection from DB values rendered into markup.
═══════════════════════════════════════ */
function escapeAttr(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function attachDrilldownHandlers(container) {
    container.querySelectorAll('.clickable-row').forEach(el => {
        el.addEventListener('click', () => {
            openDrilldown(el.dataset.type, el.dataset.value, el.dataset.title);
        });
    });
}

function buildHotspots(hotspots) {
    const container = document.getElementById('hotspot-list');
    container.innerHTML = hotspots.map(h => `
        <div class="clickable-row flex justify-between items-center p-5 bg-gray-50 rounded-2xl border-l-4 border-emerald-500 hover:bg-emerald-50 transition-colors"
             data-type="barangay" data-value="${escapeAttr(h.brgy)}" data-title="${escapeAttr('Barangay ' + h.brgy)}">
            <span class="font-black text-[11px] text-gray-800 uppercase tracking-tight">${h.brgy}</span>
            <span class="bg-emerald-600 text-white px-3 py-1 rounded-lg text-[9px] font-black">${h.count} CASES</span>
        </div>
    `).join('');
    attachDrilldownHandlers(container);
}

function buildRecentLogs(logs) {
    const container = document.getElementById('recent-logs');
    container.innerHTML = logs.map(l => `
        <div class="clickable-row border-b border-white/5 pb-4 last:border-0 group"
             data-type="case" data-value="${escapeAttr(l.reference_id)}" data-title="${escapeAttr('Case ' + l.reference_id)}">
            <p class="text-[8px] font-black text-emerald-500 uppercase tracking-widest mb-1 group-hover:text-emerald-400">${l.reference_id}</p>
            <p class="text-xs font-black text-white uppercase mb-1 group-hover:text-emerald-300">${l.disease_name}</p>
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-white/20"></span>
                <p class="text-[9px] font-bold text-gray-500 uppercase tracking-tight">${l.report_date}</p>
            </div>
        </div>
    `).join('');
    attachDrilldownHandlers(container);
}

function buildTrend(trends){
    if(charts["trend"]) charts["trend"].destroy();
    charts["trend"] = new Chart(document.getElementById("trendChart"), {
        type: "line",
        data: {
            labels: trends.map(x=>x.month),
            datasets: [{
                label: "Cases",
                data: trends.map(x=>x.count),
                borderColor: COLORS.emerald,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 4,
                pointRadius: 4,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const label = trends[elements[0].index].month;
                openDrilldown('month', label, label + ' Cases');
            }
        }
    });
}

function buildStatus(statusData){
    if(charts["status"]) charts["status"].destroy();
    charts["status"] = new Chart(document.getElementById("statusChart"), {
        type: "doughnut",
        data: {
            labels: statusData.map(x=>x.status.toUpperCase()),
            datasets: [{
                data: statusData.map(x=>x.count),
                backgroundColor: [COLORS.amber, COLORS.blue, COLORS.emerald, COLORS.rose],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 20 } } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const raw = statusData[elements[0].index].status;
                openDrilldown('status', raw, raw.charAt(0).toUpperCase() + raw.slice(1) + ' Cases');
            }
        }
    });
}

function buildStageChart(vuln) {
    if(charts["stage"]) charts["stage"].destroy();
    charts["stage"] = new Chart(document.getElementById("stageChart"), {
        type: "polarArea",
        data: {
            labels: vuln.map(x => x.growth_stage),
            datasets: [{
                data: vuln.map(x => x.count),
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(37, 99, 235, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(225, 29, 72, 0.7)',
                    'rgba(15, 23, 42, 0.7)'
                ],
                borderWidth: 0
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: { r: { ticks: { display: false }, grid: { color: '#f1f5f9' } } },
            plugins: { legend: { display: false } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const label = vuln[elements[0].index].growth_stage;
                openDrilldown('stage', label, label + ' Stage Cases');
            }
        }
    });
}

function buildDisease(freq){
    if(charts["disease"]) charts["disease"].destroy();
    charts["disease"] = new Chart(document.getElementById("diseaseChart"), {
        type: "bar",
        data: {
            labels: freq.map(x=>x.d_name),
            datasets: [{
                label: "Frequency",
                data: freq.map(x=>x.count),
                backgroundColor: COLORS.slate,
                borderRadius: 15,
                barThickness: 50
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: { 
                y: { beginAtZero: true, grid: { color: '#f8fafc' } }, 
                x: { grid: { display: false } } 
            },
            plugins: { legend: { display: false } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                const label = freq[elements[0].index].d_name;
                openDrilldown('disease', label, label + ' Cases');
            }
        }
    });
}

/* ═══════════════════════════════════════
   DRILL-DOWN MODAL LOGIC
═══════════════════════════════════════ */
async function openDrilldown(type, value, title) {
    const modal = document.getElementById('drilldownModal');
    const body  = document.getElementById('drilldown-body');
    document.getElementById('drilldown-title').innerText = title;
    body.innerHTML = `<p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Loading…</p>`;
    modal.style.display = 'flex';

    try {
        const res = await fetch(`analytics.php?api=1&drilldown=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`);
        const data = await res.json();
        renderDrilldownResults(data.cases || []);
    } catch (e) {
        body.innerHTML = `<p class="text-xs font-bold text-rose-500 uppercase tracking-widest">Failed to load cases.</p>`;
        console.error("Drilldown fetch failed", e);
    }
}

function renderDrilldownResults(cases) {
    const body = document.getElementById('drilldown-body');
    if (!cases.length) {
        body.innerHTML = `<p class="text-xs font-bold text-gray-400 uppercase tracking-widest">No matching cases found.</p>`;
        return;
    }

    body.innerHTML = cases.map(c => {
        const sevDot  = SEVERITY_COLORS[(c.severity || '').toLowerCase()] || 'bg-gray-300';
        const statDot = STATUS_COLORS[(c.status || '').toLowerCase()] || 'bg-gray-300';
        return `
        <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100 flex items-center justify-between gap-4">
            <div>
                <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest mb-1">${c.reference_id}</p>
                <p class="text-xs font-black text-gray-800 uppercase">${c.disease_name}</p>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mt-1">${c.brgy_name || '—'} &middot; ${c.report_date}</p>
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
                <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-wide text-gray-600">
                    <span class="w-2 h-2 rounded-full ${statDot}"></span>${c.status}
                </span>
                <span class="flex items-center gap-1 text-[9px] font-black uppercase tracking-wide text-gray-600">
                    <span class="w-2 h-2 rounded-full ${sevDot}"></span>${c.severity || '—'}
                </span>
            </div>
        </div>`;
    }).join('');
}

function closeDrilldown() {
    document.getElementById('drilldownModal').style.display = 'none';
}

window.onload = loadData;
</script>

<?php include "includes/layout-end.php"; ?>