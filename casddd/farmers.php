<?php
// Same duplicate-folder situation as process_farmer.php: this project has
// files sitting in both C:\xampp\htdocs\casddd\ and C:\xampp\htdocs\casddd\src\,
// so check a couple of likely spots instead of assuming one fixed path.
foreach ([__DIR__ . '/src/db_config.php', __DIR__ . '/db_config.php', dirname(__DIR__) . '/src/db_config.php'] as $__dbConfigPath) {
    if (is_file($__dbConfigPath)) { require_once $__dbConfigPath; break; }
}
unset($__dbConfigPath);
$pageTitle = "Farmer Records";
include "includes/layout.php";

$barangay_list = mysqli_query($conn, "SELECT id, name FROM barangays ORDER BY name ASC");
$barangays = [];
while ($b = mysqli_fetch_assoc($barangay_list)) { $barangays[] = $b; }

$stats_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM farmers");
$stats   = mysqli_fetch_assoc($stats_q);

$success = htmlspecialchars($_GET['success'] ?? '');
$errMsg  = htmlspecialchars($_GET['error']   ?? '');
?>
<!DOCTYPE html><!-- layout already opened, just ensure head is loaded -->
<style>
/* ── Base ─────────────────────────────────────────── */
.report-card{background:#fff;border:1px solid #f1f5f9;border-radius:2rem;box-shadow:0 10px 25px -5px rgba(0,0,0,.05)}
.btn-dark{background:#111827;border:none;cursor:pointer;transition:all .25s}
.btn-dark:hover{background:#064e3b;transform:translateY(-1px);box-shadow:0 8px 20px -8px rgba(6,78,59,.5)}
.btn-green{background:#064e3b;border:none;cursor:pointer;transition:all .25s}
.btn-green:hover{background:#065f46;transform:translateY(-1px)}
.input-flat{background:#f8fafc;border:2px solid transparent;border-radius:.875rem;padding:.7rem 1rem;font-weight:700;font-size:.85rem;transition:all .2s;width:100%}
.input-flat:focus{border-color:#10b981;background:#fff;outline:none}
.farmer-table th{padding:1.1rem 1rem;color:#94a3b8;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.1em}

/* ── Toast ──────────────────────────────────────────── */
#toast{position:fixed;bottom:2rem;right:2rem;z-index:9999;transform:translateY(140%);transition:transform .4s cubic-bezier(.34,1.56,.64,1);pointer-events:none}
#toast.show{transform:translateY(0)}

/* ── Filter ─────────────────────────────────────────── */
.filter-dropdown{position:relative}
.filter-menu{position:absolute;top:calc(100% + 10px);right:0;background:#fff;border-radius:1.5rem;box-shadow:0 25px 60px -10px rgba(0,0,0,.18);border:1px solid #f1f5f9;min-width:270px;z-index:200;overflow:hidden;opacity:0;transform:translateY(-10px) scale(.96);pointer-events:none;transition:all .2s cubic-bezier(.34,1.56,.64,1)}
.filter-menu.open{opacity:1;transform:translateY(0) scale(1);pointer-events:all}
.fopt{display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:#475569;transition:background .12s;user-select:none}
.fopt:hover{background:#f8fafc}
.fopt.active{background:#ecfdf5;color:#065f46}
.fopt .chk{width:16px;height:16px;border-radius:5px;border:2px solid #cbd5e1;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s}
.fopt.active .chk{background:#064e3b;border-color:#064e3b}
.fopt .chk svg{display:none}
.fopt.active .chk svg{display:block}
.f-chip{display:inline-flex;align-items:center;gap:4px;background:#064e3b;color:#fff;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:900;text-transform:uppercase;cursor:pointer;transition:background .15s;white-space:nowrap}
.f-chip:hover{background:#dc2626}

/* ── Clickable Row ───────────────────────────────────── */
#farmerTableBody tr{cursor:pointer;transition:background .15s,box-shadow .15s}
#farmerTableBody tr:hover{background:#ecfdf5 !important;box-shadow:inset 3px 0 0 #10b981}

/* ── Profile / Update Modal (formal record layout) ───── */
.lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;display:block;margin-bottom:4px}
.val{font-size:.875rem;font-weight:600;color:#1e293b}
.fbox{background:transparent;padding:0}
.einput{background:#f8fafc;border:1px solid #cbd5e1;border-radius:.5rem;padding:.7rem 1rem;font-weight:600;font-size:.875rem;color:#1e293b;width:100%;transition:border-color .15s;outline:none}
.einput:focus{border-color:#3f6b52;background:#fff}
.cred-box{background:#1e293b;border-radius:.75rem;padding:1.25rem 1.5rem}
.section-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#3f6b52;display:flex;align-items:center;gap:6px;margin-bottom:12px}
.section-label::after{content:'';flex:1;height:1px;background:#e2e8f0}
.um-record{border:1px solid #e2e8f0;border-radius:.5rem;overflow:hidden}
.um-record>div{padding:.75rem 1rem}
.um-record>div+div{border-top:1px solid #eef2f0}
.um-btn-dark{background:#1e293b;border:none;cursor:pointer;transition:background .2s}
.um-btn-dark:hover{background:#1e3a34}
.um-btn-green{background:#1e3a34;border:none;cursor:pointer;transition:background .2s}
.um-btn-green:hover{background:#28493f}

/* ── Confirm Overlay ────────────────────────────────── */
.confirm-overlay{display:none;position:absolute;inset:0;background:rgba(15,23,42,.92);border-radius:.75rem;z-index:20;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem}
.confirm-overlay.show{display:flex}

/* ── Enrollment Card ────────────────────────────────── */
.enroll-card{background:#fff;border:2px solid #f1f5f9;border-radius:1.75rem;overflow:hidden;box-shadow:0 4px 20px -5px rgba(0,0,0,.08);transition:border-color .2s}
.enroll-card:hover{border-color:#d1fae5}
.field-err{border-color:#fca5a5!important;background:#fff5f5!important}
.field-ok {border-color:#6ee7b7!important}

/* ── Scrollbar thin ─────────────────────────────────── */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:99px}

/* ── Modal Tabs ─────────────────────────────────────── */
.modal-tab-bar{display:flex;gap:4px;background:#f1f5f9;border-radius:.5rem;padding:4px;flex-shrink:0}
.modal-tab{flex:1;padding:7px 14px;border-radius:.375rem;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;cursor:pointer;transition:all .15s;border:none;background:transparent;display:flex;align-items:center;justify-content:center;gap:5px}
.modal-tab.active{background:#fff;color:#1e3a34;box-shadow:0 1px 4px rgba(15,23,42,.1)}
.modal-tab:hover:not(.active){color:#475569}

/* ── Report history cards ───────────────────────────── */
.rpt-card{background:#f8fafc;border:1.5px solid #f1f5f9;border-radius:1.25rem;padding:1rem 1.1rem;transition:border-color .15s}
.rpt-card:hover{border-color:#d1fae5}
.sev-low{background:#dcfce7;color:#166534}
.sev-moderate{background:#fef9c3;color:#854d0e}
.sev-high{background:#fee2e2;color:#991b1b}
.sev-critical{background:#ffe4e6;color:#9f1239}
.st-pending{background:#f1f5f9;color:#475569}
.st-verified{background:#ecfdf5;color:#065f46}
.st-rejected{background:#fef2f2;color:#dc2626}
.st-resolved{background:#eff6ff;color:#1d4ed8}

/* ── Report detail: chat with farmer ─────────────────── */
.chat-thread{max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:10px}
.chat-row{display:flex;flex-direction:column;max-width:85%}
.chat-row.staff{align-self:flex-end;align-items:flex-end;margin-left:auto}
.chat-row.farmer{align-self:flex-start;align-items:flex-start;margin-right:auto}
.chat-bubble{padding:9px 13px;border-radius:16px;font-size:12px;font-weight:700;line-height:1.5;white-space:pre-wrap;word-wrap:break-word}
.chat-bubble.staff{background:#064e3b;color:#fff;border-bottom-right-radius:4px}
.chat-bubble.farmer{background:#eef2f7;color:#334155;border-bottom-left-radius:4px}
.chat-meta{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;margin-top:3px;color:#94a3b8}
#rd_chat_input{transition:all .2s}
#rd_chat_input:focus{border-color:#10b981;background:#fff}
#rd_chat_send_btn:disabled{opacity:.4;cursor:not-allowed}
</style>

<!-- ══ TOAST ══════════════════════════════════════════════════ -->
<?php if ($success || $errMsg): ?>
<div id="toast" class="<?= $errMsg ? 'bg-rose-600' : 'bg-emerald-600' ?> text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 font-black text-xs uppercase tracking-widest max-w-xs">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <?php if($errMsg): ?>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>
        <?php else: ?>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
        <?php endif; ?>
    </svg>
    <span><?= $errMsg ?: ($success === 'updated' ? 'Farmer updated successfully!' : 'Records created successfully!') ?></span>
</div>
<script>
window.addEventListener('DOMContentLoaded',()=>{
    const t=document.getElementById('toast');
    setTimeout(()=>t.classList.add('show'),120);
    setTimeout(()=>t.classList.remove('show'),4000);
});
</script>
<?php endif; ?>

<!-- ══ PAGE HEADER ════════════════════════════════════════════ -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-3">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Staff Use Only
        </div>
        <h2 class="text-4xl font-black text-slate-900 tracking-tighter leading-none">Farmer <span class="text-emerald-600">Records</span></h2>
        <p class="text-slate-400 font-bold text-xs mt-2 uppercase tracking-tight">List of All Registered Farmers</p>
    </div>
    <div class="flex items-center gap-3">

        <!-- ── Redesigned Total Registered card ── -->
        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-800 to-emerald-600 px-7 py-4 rounded-2xl shadow-xl flex items-center gap-4">
            <!-- decorative rings -->
            <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-white/10 pointer-events-none"></div>
            <div class="absolute -right-1 -bottom-5 w-12 h-12 rounded-full bg-white/5 pointer-events-none"></div>
            <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                             M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857
                             m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-[9px] font-black text-emerald-200 uppercase tracking-widest leading-none mb-1">Total Registered</p>
                <p class="text-2xl font-black text-white leading-none"><?= (int)$stats['total'] ?></p>
            </div>
        </div>

        <button onclick="openEnrollModal(1)" class="btn-dark text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl">
            + Create Accounts
        </button>
    </div>
</div>

<!-- ══ MAIN CARD ══════════════════════════════════════════════ -->
<div class="report-card p-8">

    <!-- Search + Filter -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div class="relative w-full md:w-96">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" id="farmerSearch" oninput="runFilter()" placeholder="SEARCH BY NAME OR BARANGAY…"
                   class="w-full pl-12 pr-4 py-4 bg-slate-50 border-none rounded-2xl focus:ring-2 focus:ring-emerald-500 font-black text-[10px] uppercase tracking-widest outline-none">
        </div>
        <div class="flex items-center gap-3 flex-wrap justify-end">
            <div id="chipWrap" class="flex gap-2 flex-wrap items-center"></div>
            <div class="filter-dropdown" id="filterDropdown">
                <button onclick="toggleFilterMenu(event)" class="flex items-center gap-2 px-5 py-4 bg-slate-50 hover:bg-slate-100 border-2 border-transparent hover:border-slate-200 rounded-2xl font-black text-[10px] uppercase tracking-widest text-slate-600 transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                    Filter
                    <span id="filterBadge" style="display:none" class="min-w-[18px] h-[18px] bg-emerald-600 text-white rounded-full text-[8px] font-black items-center justify-center px-1">0</span>
                </button>
                <div class="filter-menu" id="filterMenu">
                    <div class="px-5 pt-4 pb-3 flex items-center justify-between border-b border-slate-50">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Filters</span>
                        <button onclick="clearAllFilters()" class="text-[8px] font-black text-rose-400 hover:text-rose-600 uppercase tracking-widest">Clear All</button>
                    </div>
                    <div class="px-4 pt-4 pb-3">
                        <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest px-2 mb-2">By Gender</p>
                        <div class="flex gap-2">
                            <div class="fopt flex-1 justify-center rounded-xl border border-slate-100" style="padding:8px" data-type="gender" data-value="Male" onclick="toggleFilter(this)">
                                <div class="chk"><svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg></div>Male
                            </div>
                            <div class="fopt flex-1 justify-center rounded-xl border border-slate-100" style="padding:8px" data-type="gender" data-value="Female" onclick="toggleFilter(this)">
                                <div class="chk"><svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg></div>Female
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-slate-50">
                        <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest px-6 pt-4 pb-2">By Barangay</p>
                        <div class="max-h-52 overflow-y-auto pb-2" id="brgyList">
                            <?php foreach ($barangays as $b): ?>
                            <div class="fopt" data-type="barangay" data-value="<?= strtolower($b['name']) ?>" data-label="<?= htmlspecialchars($b['name']) ?>" onclick="toggleFilter(this)">
                                <div class="chk"><svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 13l4 4L19 7"/></svg></div>
                                <?= htmlspecialchars($b['name']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="p-4 border-t border-slate-50">
                        <button onclick="toggleFilterMenu(event)" class="btn-green w-full text-white py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-lg">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full farmer-table">
            <thead>
                <tr class="border-b border-slate-50">
                    <th class="text-left">ID</th>
                    <th class="text-left">Farmer Info</th>
                    <th>Barangay</th>
                    <th>Gender</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="farmerTableBody">
            <?php
            $result = mysqli_query($conn,
                "SELECT f.*, b.name AS bname FROM farmers f
                 LEFT JOIN barangays b ON f.barangay_id = b.id
                 ORDER BY f.created_at DESC");
            $rowNum = 0;
            while ($row = mysqli_fetch_assoc($result)):
                $rowNum++;
                $img    = !empty($row['profile_farmers']) ? 'uploads/' . $row['profile_farmers'] : 'assets/default-user.png';
                $gender = $row['gender'] ?? '';
                $status = $row['status'] ?? 'active';
                $modal_data = [
                    'farmer_name'    => $row['farmer_name'],
                    'contact_number' => $row['contact_number'],
                    'age'            => $row['age'],
                    'gender'         => $row['gender'],
                    'years_farming'  => $row['years_farming'],
                    'registered_year'=> $row['years_farming'] ?: ($row['created_at'] ? date('Y', strtotime($row['created_at'])) : null),
                    'barangay_id'    => $row['barangay_id'],
                    'bname'          => $row['bname'],
                    'email'          => $row['email'],
                    'status'         => $row['status'],
                    'profile_farmers'=> $row['profile_farmers'],
                    'row_num'        => $rowNum,
                ];
            ?>
            <tr class="border-b border-slate-50 group select-none"
                data-name="<?= strtolower(htmlspecialchars($row['farmer_name'])) ?>"
                data-barangay="<?= strtolower($row['bname'] ?? '') ?>"
                data-gender="<?= htmlspecialchars($gender) ?>"
                onclick='openUpdateModal(<?= htmlspecialchars(json_encode($modal_data), ENT_QUOTES) ?>)'>

                <!-- ID -->
                <td class="px-4 py-5">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 text-[9px] font-black uppercase tracking-widest">#<?= $rowNum ?></span>
                </td>

                <!-- Personnel -->
                <td class="px-4 py-5">
                    <div class="flex items-center gap-4">
                        <img src="<?= htmlspecialchars($img) ?>" class="w-11 h-11 rounded-xl object-cover border-2 border-white shadow-md group-hover:scale-110 transition-transform">
                        <div>
                            <div class="font-black text-slate-800 text-sm uppercase"><?= htmlspecialchars($row['farmer_name']) ?></div>
                            <div class="text-[10px] font-bold text-slate-400 tracking-widest italic"><?= htmlspecialchars($row['contact_number']) ?></div>
                            <div class="text-[9px] font-bold text-emerald-600 tracking-widest"><?= htmlspecialchars($row['email'] ?? '—') ?></div>
                        </div>
                    </div>
                </td>

                <!-- Barangay -->
                <td class="px-4 py-5 text-center">
                    <span class="inline-flex items-center px-3 py-1 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase"><?= htmlspecialchars($row['bname'] ?? '—') ?></span>
                </td>

                <!-- Gender -->
                <td class="px-4 py-5 text-center">
                    <?php if ($gender): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-lg text-[9px] font-black uppercase <?= $gender === 'Female' ? 'bg-pink-50 text-pink-500' : 'bg-sky-50 text-sky-500' ?>"><?= $gender ?></span>
                    <?php else: ?><span class="text-slate-300 text-xs font-black">—</span><?php endif; ?>
                </td>

                <!-- Status -->
                <td class="px-4 py-5 text-center">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $status === 'active' ? 'bg-emerald-500' : 'bg-rose-400' ?>"></span>
                        <?= htmlspecialchars($status) ?>
                    </span>
                </td>

            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <div id="emptyState" class="hidden py-20 text-center">
            <div class="w-14 h-14 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <p class="font-black text-slate-400 text-xs uppercase tracking-widest">No results found</p>
            <button onclick="clearAllFilters()" class="mt-3 text-emerald-600 font-black text-[10px] uppercase tracking-widest hover:underline">Clear Filters</button>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     UPDATE MODAL — edit farmer + account credentials + photo
═════════════════════════════════════════════════════════════ -->
<div id="updateModal" class="hidden fixed inset-0 bg-slate-900/80 backdrop-blur-md z-[100] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-xl overflow-hidden shadow-lg flex flex-col max-h-[92vh] relative">

        <!-- Confirm overlay -->
        <div id="updConfirmOverlay" class="confirm-overlay">
            <div class="text-center px-10">
                <div class="w-14 h-14 bg-[#3f6b52] rounded-lg flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h4 class="text-white font-bold text-xl mb-2">Save Changes?</h4>
                <p class="text-slate-300 text-xs font-semibold uppercase tracking-wide mb-6">All edits will be written to the database.</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="submitUpdateForm()" class="bg-[#3f6b52] hover:bg-[#7fa393] text-white px-8 py-3 rounded-lg font-bold text-xs uppercase tracking-wide transition-colors">Yes, Save</button>
                    <button onclick="document.getElementById('updConfirmOverlay').classList.remove('show')" class="bg-white/10 hover:bg-white/20 text-white px-8 py-3 rounded-lg font-bold text-xs uppercase tracking-wide">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Modal Header -->
        <div class="p-6 border-b border-slate-200 bg-slate-50 flex-shrink-0">
            <div class="flex items-center gap-4">
                <!-- Photo -->
                <div class="relative flex-shrink-0">
                    <div class="w-16 h-16 rounded-lg bg-slate-200 overflow-hidden border border-slate-300">
                        <img id="upd_photo_preview" src="" class="w-full h-full object-cover">
                    </div>
                    <label id="upd_photo_label" class="hidden absolute -bottom-1.5 -right-1.5 cursor-pointer bg-slate-900 hover:bg-[#1e3a34] text-white w-6 h-6 rounded-md shadow-sm transition-colors items-center justify-center">
                        <svg class="w-3 h-3 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <input type="file" id="upd_photo_file" accept="image/*" class="hidden" onchange="previewUpdPhoto(this)">
                    </label>
                </div>
                <div class="flex-grow min-w-0">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Farmer Details</p>
                    <p class="font-bold text-slate-900 text-lg truncate leading-tight" id="upd_header_name">—</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-semibold text-slate-400" id="upd_header_sub">—</span>
                        <span id="upd_header_status" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[9px] font-bold uppercase"></span>
                    </div>
                </div>
                <button onclick="closeUpdateModal()" class="w-9 h-9 bg-white hover:bg-rose-50 border border-slate-200 text-slate-400 hover:text-rose-500 rounded-lg flex items-center justify-center transition-colors flex-shrink-0">✕</button>
            </div>

            <!-- Tab Bar -->
            <div class="mt-5 modal-tab-bar">
                <button type="button" class="modal-tab active" id="tabBtnInfo" onclick="switchModalTab('info')">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Farmer Info
                </button>
                <button type="button" class="modal-tab" id="tabBtnReports" onclick="switchModalTab('reports')">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Report History
                    <span id="rptBadge" class="hidden min-w-[18px] h-[18px] bg-[#1e3a34] text-white rounded-full text-[8px] font-bold inline-flex items-center justify-center px-1">0</span>
                </button>
            </div>
        </div>

        <!-- Scrollable panels -->
        <div class="overflow-y-auto flex-grow">

        <!-- ───────────── PANEL: Farmer Info ───────────── -->
        <div id="panelInfo">
        <form id="updateFarmerForm" action="process_farmer.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_farmer_info" value="1">
                <input type="hidden" name="farmer_name_key"   id="upd_id">
                <input type="file"   name="photo" id="upd_form_photo" accept="image/*" class="hidden">

                <div class="p-7 space-y-6">

                    <!-- Personal Info + Location, combined as one formal record block -->
                    <div>
                        <p class="section-label">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Personal Information
                        </p>
                        <div class="um-record">
                            <div>
                                <span class="lbl">Full Name</span>
                                <div id="v_name" class="fbox val"></div>
                                <input type="text" name="farmer_name" id="e_name" class="einput hidden mt-1" required maxlength="100" placeholder="Full name">
                            </div>
                            <div class="grid grid-cols-2 divide-x divide-[#eef2f0]">
                                <div>
                                    <span class="lbl">Contact Number</span>
                                    <div id="v_contact" class="fbox val"></div>
                                    <input type="tel"  name="contact_number" id="e_contact" class="einput hidden mt-1" required
                                           pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX"
                                           oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)">
                                </div>
                                <div class="pl-4">
                                    <span class="lbl">Age</span>
                                    <div id="v_age" class="fbox val"></div>
                                    <input type="number" name="age" id="e_age" class="einput hidden mt-1" required min="12" max="120" placeholder="Age">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 divide-x divide-[#eef2f0]">
                                <div>
                                    <span class="lbl">Gender</span>
                                    <div id="v_gender" class="fbox val"></div>
                                    <select name="gender" id="e_gender" class="einput hidden mt-1" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="pl-4">
                                    <span class="lbl">Year Registered</span>
                                    <div id="v_years" class="fbox val"></div>
                                    <select name="years_farming" id="e_years" class="einput hidden mt-1" required>
                                        <?php $curYr = (int)date('Y'); for ($y = $curYr; $y >= $curYr - 60; $y--): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <span class="lbl">Barangay</span>
                                <div id="v_barangay" class="fbox val"></div>
                                <select name="barangay_id" id="e_barangay" class="einput hidden mt-1" required>
                                    <?php foreach ($barangays as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Account Credentials -->
                    <div>
                        <p class="section-label">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            Login Account
                        </p>
                        <div class="cred-box space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-[9px] font-bold text-[#a9c2b5] uppercase tracking-wide block mb-2">Username</span>
                                    <div id="v_email" class="text-white font-bold text-sm bg-white/10 rounded-md px-4 py-3 break-all"></div>
                                    <input type="text" name="email" id="e_email" class="hidden w-full bg-white/10 text-white font-bold text-sm rounded-md px-4 py-3 border border-white/20 outline-none focus:border-[#a9c2b5] transition-colors" placeholder="Username or email" maxlength="100">
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-[#a9c2b5] uppercase tracking-wide block mb-2">Status</span>
                                    <div id="v_status" class="text-white font-bold text-sm bg-white/10 rounded-md px-4 py-3 uppercase tracking-wide"></div>
                                    <select name="status" id="e_status" class="hidden w-full bg-white/10 text-white font-bold text-sm rounded-md px-4 py-3 border border-white/20 outline-none focus:border-[#a9c2b5] transition-colors">
                                        <option value="active" style="background:#1e293b;color:#fff;">Active</option>
                                        <option value="inactive" style="background:#1e293b;color:#fff;">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <span class="text-[9px] font-bold text-[#a9c2b5] uppercase tracking-wide block mb-2">New Password <span class="text-[#7fa393]/60 normal-case font-bold">(leave blank to keep current)</span></span>
                                <div id="v_password" class="text-white font-bold text-sm bg-white/10 rounded-md px-4 py-3 tracking-[.3em] select-none">••••••••</div>
                                <div class="hidden relative" id="e_password_wrap">
                                    <input type="password" name="new_password" id="e_password" class="w-full bg-white/10 text-white font-bold text-sm rounded-md pl-4 pr-11 py-3 border border-white/20 outline-none focus:border-[#a9c2b5] transition-colors" placeholder="New password (optional)" maxlength="100">
                                    <button type="button" id="e_password_toggle" onclick="togglePasswordVisibility()" title="Show password"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center text-[#cddbd3] hover:text-white rounded-md transition-colors">
                                        <svg id="e_password_eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /p-7 -->

                <!-- Footer -->
                <div class="px-7 pb-7 flex gap-3 border-t border-slate-100 pt-5">
                    <button type="button" id="updEditBtn" onclick="toggleUpdEdit()"
                        class="um-btn-dark text-white px-6 py-3 rounded-lg font-bold text-[9px] uppercase tracking-wide flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        <span id="updEditLabel">Edit Record</span>
                    </button>
                    <button type="button" id="updSaveBtn" onclick="confirmUpdSave()"
                        class="hidden um-btn-green flex-1 text-white py-3 rounded-lg font-bold text-[9px] uppercase tracking-wide">
                        Save Changes
                    </button>
                </div>

            </form>
        </div><!-- /panelInfo -->

        <!-- ───────────── PANEL: Report History ───────────── -->
        <div id="panelReports" class="hidden p-7">
            <div id="rptSummary" class="grid grid-cols-4 gap-3 mb-6"></div>
            <div id="rptList" class="space-y-3">
                <div id="rptLoading" class="py-16 text-center">
                    <div class="w-10 h-10 border-4 border-[#cddbd3] border-t-[#1e3a34] rounded-full animate-spin mx-auto mb-4"></div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Loading reports…</p>
                </div>
            </div>
        </div><!-- /panelReports -->

        </div><!-- /overflow-y-auto -->
    </div><!-- /modal inner -->
</div><!-- /updateModal -->


<!-- ══════════════════════════════════════════════════════════
     ENROLLMENT MODAL
═════════════════════════════════════════════════════════════ -->
<div id="enrollModal" class="hidden fixed inset-0 bg-slate-900/80 backdrop-blur-md z-[100] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-4xl rounded-[2.5rem] overflow-hidden shadow-2xl flex flex-col max-h-[93vh]">

        <div class="p-7 border-b border-slate-50 bg-slate-50/60 flex justify-between items-center flex-shrink-0">
            <div>
                <h3 class="font-black text-2xl text-slate-900 tracking-tighter uppercase">Add <span class="text-emerald-600">New Farmer</span></h3>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">Fill in the farmer's details — a username and password will be created automatically</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" id="addMoreBtn" onclick="addEnrollCard()"
                    class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-4 py-2.5 rounded-xl font-black text-[9px] uppercase tracking-widest transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                    Add More
                </button>
                <span id="enrollCountLabel" class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-slate-100 px-3 py-2 rounded-xl">1 / 5</span>
                <button onclick="closeEnrollModal()" class="w-10 h-10 bg-white hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded-2xl shadow-sm flex items-center justify-center transition-all">✕</button>
            </div>
        </div>

        <div class="overflow-y-auto flex-grow p-7">
            <form id="enrollForm" action="process_farmer.php" method="POST" enctype="multipart/form-data" onsubmit="return prepareEnrollSubmit(event)">
                <input type="hidden" name="bulk_save" value="1">
                <div id="enrollCards" class="space-y-6"></div>

                <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end gap-3">
                    <button type="button" onclick="closeEnrollModal()" class="px-8 py-4 rounded-2xl text-xs font-black uppercase tracking-widest text-slate-400 hover:bg-slate-100 transition-all">Cancel</button>
                    <button type="submit" class="btn-green text-white px-12 py-4 rounded-2xl font-black uppercase text-xs tracking-widest shadow-xl">
                        Submit All Records
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     REPORT DETAIL MODAL
═════════════════════════════════════════════════════════════ -->
<div id="reportDetailModal" class="hidden fixed inset-0 bg-slate-900/85 backdrop-blur-md z-[200] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-lg rounded-[2rem] overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">

        <div class="p-6 border-b border-slate-100 bg-slate-50/70 flex-shrink-0">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3 flex-grow min-w-0">
                    <div id="rd_farmer_avatar" class="flex-shrink-0 w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 font-black text-base overflow-hidden border-2 border-white shadow-md"></div>
                    <div class="flex-grow min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span id="rd_reference" class="text-[9px] font-black text-slate-400 uppercase tracking-widest"></span>
                            <span id="rd_sev_badge"  class="inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase"></span>
                            <span id="rd_st_badge"   class="inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase"></span>
                        </div>
                        <h4 id="rd_disease_name" class="font-black text-slate-900 text-lg uppercase tracking-tight leading-tight"></h4>
                        <p  id="rd_disease_type" class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mt-0.5"></p>
                    </div>
                </div>
                <button onclick="closeReportDetail()" class="w-9 h-9 bg-white hover:bg-rose-50 text-slate-400 hover:text-rose-500 rounded-xl shadow-sm flex items-center justify-center transition-all flex-shrink-0">&#10005;</button>
            </div>
        </div>

        <div class="overflow-y-auto flex-grow p-6 space-y-5">

            <div id="rd_photo_wrap" class="hidden">
                <img id="rd_photo" src="" alt="Evidence photo" class="w-full rounded-2xl object-cover max-h-52 border border-slate-100 shadow-sm">
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
                    <div id="rd_pct" class="text-3xl font-black text-slate-800"></div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Infection %</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
                    <div id="rd_affected" class="text-3xl font-black text-slate-800"></div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Plants Affected</div>
                </div>
                <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
                    <div id="rd_total" class="text-3xl font-black text-slate-800"></div>
                    <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Total Plants</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Report Date</div>
                    <div id="rd_report_date" class="text-xs font-black text-slate-700"></div>
                </div>
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Date Planted</div>
                    <div id="rd_date_planted" class="text-xs font-black text-slate-700"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Growth Stage</div>
                    <div id="rd_growth" class="text-xs font-black text-slate-700"></div>
                </div>
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                    <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Filed On</div>
                    <div id="rd_created" class="text-xs font-black text-slate-700"></div>
                </div>
            </div>

            <div id="rd_desc_wrap" class="hidden">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">&#128203; Description</p>
                <p id="rd_desc" class="text-sm font-bold text-slate-700 leading-relaxed bg-slate-50 rounded-xl p-3 border border-slate-100"></p>
            </div>

            <div id="rd_treat_wrap" class="hidden">
                <p class="text-[9px] font-black text-emerald-700 uppercase tracking-widest mb-2">&#128138; Treatment Recommendation</p>
                <p id="rd_treat" class="text-sm font-bold text-slate-700 leading-relaxed bg-emerald-50 rounded-xl p-3 border border-emerald-100"></p>
            </div>

            <div id="rd_remarks_wrap" class="hidden">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">&#128221; Remarks</p>
                <p id="rd_remarks" class="text-sm font-bold text-slate-600 leading-relaxed bg-slate-50 rounded-xl p-3 border border-slate-100"></p>
            </div>

            <div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">&#128172; Message Farmer</p>
                <div id="rd_chat_thread" class="chat-thread bg-slate-50 rounded-xl p-3 border border-slate-100"></div>
                <div id="rd_chat_empty" class="hidden text-center py-6 text-[10px] font-black text-slate-300 uppercase tracking-widest bg-slate-50 rounded-xl border border-slate-100">No messages yet</div>
            </div>

        </div>

        <div class="border-t border-slate-100 flex-shrink-0">
            <div class="px-6 pt-4 flex items-end gap-2">
                <textarea id="rd_chat_input" rows="1" oninput="autoGrowChatBox(this)" onkeydown="handleChatKeydown(event)"
                    placeholder="Type a message to the farmer…"
                    class="flex-1 resize-none max-h-28 rounded-2xl bg-slate-50 border-2 border-transparent outline-none px-4 py-3 text-xs font-bold text-slate-700"></textarea>
                <button type="button" id="rd_chat_send_btn" onclick="sendFarmerChatMessage()" title="Send message"
                    class="flex-shrink-0 w-11 h-11 rounded-full btn-green text-white flex items-center justify-center shadow-lg text-base">&#10148;</button>
            </div>
            <div class="px-6 py-4">
                <button onclick="closeReportDetail()" class="w-full btn-dark text-white py-3 rounded-2xl font-black text-[9px] uppercase tracking-widest">Close</button>
            </div>
        </div>

    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════
   GLOBALS
═══════════════════════════════════════════════════ */
const BARANGAYS = <?= json_encode($barangays) ?>;
const MAX_CARDS = 5;
let enrollCount = 0;

// Builds a simple year picker (like flipping through a calendar year by year)
// used for "Year Registered" instead of typing a number of years.
function buildYearOptions() {
    const thisYear = new Date().getFullYear();
    let out = '';
    for (let y = thisYear; y >= thisYear - 60; y--) {
        out += `<option value="${y}"${y === thisYear ? ' selected' : ''}>${y}</option>`;
    }
    return out;
}
let updEditMode = false;

/* ═══════════════════════════════════════════════════
   FILTER
═══════════════════════════════════════════════════ */
const activeFilters = { barangay: new Set(), gender: new Set() };

function toggleFilterMenu(e) { e.stopPropagation(); document.getElementById('filterMenu').classList.toggle('open'); }
document.addEventListener('click', () => document.getElementById('filterMenu').classList.remove('open'));

function toggleFilter(el) {
    const {type, value} = el.dataset, set = activeFilters[type];
    set.has(value) ? (set.delete(value), el.classList.remove('active')) : (set.add(value), el.classList.add('active'));
    renderChips(); runFilter();
}
function clearAllFilters() {
    activeFilters.barangay.clear(); activeFilters.gender.clear();
    document.querySelectorAll('.fopt').forEach(el => el.classList.remove('active'));
    renderChips(); runFilter();
}
function removeChip(type, val) {
    activeFilters[type].delete(val);
    document.querySelectorAll(`.fopt[data-type="${type}"][data-value="${val}"]`).forEach(el => el.classList.remove('active'));
    renderChips(); runFilter();
}
function renderChips() {
    const wrap = document.getElementById('chipWrap'), badge = document.getElementById('filterBadge');
    wrap.innerHTML = '';
    activeFilters.barangay.forEach(val => {
        const label = document.querySelector(`#brgyList .fopt[data-value="${val}"]`)?.dataset.label || val;
        wrap.innerHTML += `<span class="f-chip" onclick="removeChip('barangay','${CSS.escape(val)}')">${label} <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M6 18L18 6M6 6l12 12"/></svg></span>`;
    });
    activeFilters.gender.forEach(val => {
        wrap.innerHTML += `<span class="f-chip" onclick="removeChip('gender','${val}')">${val} <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M6 18L18 6M6 6l12 12"/></svg></span>`;
    });
    const total = activeFilters.barangay.size + activeFilters.gender.size;
    badge.textContent = total; badge.style.display = total ? 'inline-flex' : 'none';
}
function runFilter() {
    const q = document.getElementById('farmerSearch').value.toLowerCase().trim();
    let vis = 0;
    document.querySelectorAll('#farmerTableBody tr').forEach(row => {
        const ok = (!q || row.dataset.name.includes(q) || row.dataset.barangay.includes(q))
                && (activeFilters.barangay.size === 0 || activeFilters.barangay.has(row.dataset.barangay))
                && (activeFilters.gender.size   === 0 || activeFilters.gender.has(row.dataset.gender));
        row.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('emptyState').style.display = vis === 0 ? 'block' : 'none';
}

/* ═══════════════════════════════════════════════════
   UPDATE MODAL
═══════════════════════════════════════════════════ */
function openUpdateModal(data) {
    updEditMode = false;
    setUpdView();

    _currentModalFarmerName = data.farmer_name;
    _currentModalFarmerPhoto = data.profile_farmers || null;
    _reportsLoaded = false;
    switchModalTab('info');

    const img = data.profile_farmers ? 'uploads/' + data.profile_farmers : 'assets/default-user.png';
    document.getElementById('upd_photo_preview').src = img;
    document.getElementById('upd_header_name').textContent = data.farmer_name;
    document.getElementById('upd_header_sub').textContent =
        '#' + data.row_num + ' · ' + (data.bname || '—') + (data.age ? ' · ' + data.age + ' yrs' : '');

    const isActive = (data.status || 'active') === 'active';
    document.getElementById('upd_header_status').className =
        'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[9px] font-bold uppercase ' +
        (isActive ? 'bg-[#e3ebe6] text-[#16302a]' : 'bg-rose-100 text-rose-600');
    document.getElementById('upd_header_status').innerHTML =
        `<span class="w-1.5 h-1.5 rounded-full ${isActive ? 'bg-[#3f6b52]' : 'bg-rose-400'}"></span>${data.status || 'active'}`;

    document.getElementById('upd_id').value = data.farmer_name;

    document.getElementById('v_name').textContent    = data.farmer_name;
    document.getElementById('v_contact').textContent = data.contact_number;
    document.getElementById('v_age').textContent     = data.age || '—';
    document.getElementById('v_gender').textContent  = data.gender || '—';
    document.getElementById('v_years').textContent   = data.registered_year || '—';
    document.getElementById('v_barangay').textContent= data.bname || '—';
    document.getElementById('v_email').textContent   = data.email || '—';
    document.getElementById('v_status').textContent  = data.status || 'active';

    document.getElementById('e_name').value     = data.farmer_name;
    document.getElementById('e_contact').value  = data.contact_number;
    document.getElementById('e_age').value      = data.age;
    document.getElementById('e_gender').value   = data.gender || 'Male';
    document.getElementById('e_barangay').value = data.barangay_id;
    // Year Registered is a required, single-choice select (no blank option) —
    // fall back to the same registered_year shown in the read-only view, and
    // finally to the current year, so there's always a valid year selected.
    document.getElementById('e_years').value    = data.years_farming || data.registered_year || new Date().getFullYear();
    document.getElementById('e_email').value    = data.email || '';
    document.getElementById('e_status').value   = data.status || 'active';
    document.getElementById('e_password').value = '';
    resetPasswordVisibility();

    document.getElementById('updateModal').classList.remove('hidden');
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.add('hidden');
    document.getElementById('updConfirmOverlay').classList.remove('show');
}

function toggleUpdEdit() {
    updEditMode = !updEditMode;
    updEditMode ? setUpdEdit() : setUpdView();
}

const UPD_VIEW_IDS = ['v_name','v_contact','v_age','v_gender','v_barangay','v_years','v_email','v_status','v_password'];
const UPD_EDIT_IDS = ['e_name','e_contact','e_age','e_gender','e_barangay','e_years','e_email','e_status','e_password_wrap'];

function setUpdView() {
    UPD_VIEW_IDS.forEach(id => document.getElementById(id).classList.remove('hidden'));
    UPD_EDIT_IDS.forEach(id => document.getElementById(id).classList.add('hidden'));
    document.getElementById('upd_photo_label').style.display = 'none';
    document.getElementById('updEditLabel').textContent = 'Edit Record';
    document.getElementById('updSaveBtn').classList.add('hidden');
}
function setUpdEdit() {
    UPD_VIEW_IDS.forEach(id => document.getElementById(id).classList.add('hidden'));
    UPD_EDIT_IDS.forEach(id => document.getElementById(id).classList.remove('hidden'));
    document.getElementById('upd_photo_label').style.display = 'flex';
    document.getElementById('updEditLabel').textContent = 'Cancel';
    document.getElementById('updSaveBtn').classList.remove('hidden');
    resetPasswordVisibility();
}

function resetPasswordVisibility() {
    const input = document.getElementById('e_password');
    const eye   = document.getElementById('e_password_eye');
    const btn   = document.getElementById('e_password_toggle');
    input.type  = 'password';
    btn.title   = 'Show password';
    eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
}

function togglePasswordVisibility() {
    const input = document.getElementById('e_password');
    const eye   = document.getElementById('e_password_eye');
    const btn   = document.getElementById('e_password_toggle');
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.title  = showing ? 'Show password' : 'Hide password';
    eye.innerHTML = showing
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-1.563 2.902M6.423 6.423A9.98 9.98 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.958 9.958 0 004.638-1.145"/>';
}

function confirmUpdSave() {
    const contact = document.getElementById('e_contact').value;
    if (!/^09[0-9]{9}$/.test(contact)) {
        showInlineToast('Contact must start with 09 and be 11 digits.', true); return;
    }
    const age = parseInt(document.getElementById('e_age').value);
    if (isNaN(age) || age < 12 || age > 120) {
        showInlineToast('Age must be between 12 and 120.', true); return;
    }
    document.getElementById('updConfirmOverlay').classList.add('show');
}

function submitUpdateForm() {
    document.getElementById('updConfirmOverlay').classList.remove('show');
    document.getElementById('updateFarmerForm').submit();
}

function previewUpdPhoto(input) {
    if (!input.files[0]) return;
    const dt = new DataTransfer();
    dt.items.add(input.files[0]);
    document.getElementById('upd_form_photo').files = dt.files;
    const r = new FileReader();
    r.onload = e => document.getElementById('upd_photo_preview').src = e.target.result;
    r.readAsDataURL(input.files[0]);
}

/* ═══════════════════════════════════════════════════
   ENROLLMENT MODAL
═══════════════════════════════════════════════════ */
function openEnrollModal(startCount) {
    enrollCount = 0;
    document.getElementById('enrollCards').innerHTML = '';
    for (let i = 0; i < startCount; i++) addEnrollCard();
    document.getElementById('enrollModal').classList.remove('hidden');
}

function closeEnrollModal() {
    document.getElementById('enrollModal').classList.add('hidden');
}

function updateEnrollCounter() {
    document.getElementById('enrollCountLabel').textContent = enrollCount + ' / ' + MAX_CARDS;
    document.getElementById('addMoreBtn').disabled = enrollCount >= MAX_CARDS;
    document.getElementById('addMoreBtn').classList.toggle('opacity-40', enrollCount >= MAX_CARDS);
}

function addEnrollCard() {
    if (enrollCount >= MAX_CARDS) return;
    enrollCount++;
    const idx = enrollCount;
    const opts = BARANGAYS.map(b => `<option value="${b.id}">${b.name}</option>`).join('');

    const card = document.createElement('div');
    card.className = 'enroll-card';
    card.id = 'card_' + idx;
    card.innerHTML = `
        <div class="flex items-center justify-between px-7 py-4 border-b border-slate-50 bg-slate-50/60">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-900 text-white rounded-xl flex items-center justify-center font-black text-xs">${idx}</div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Farmer #${idx}</span>
            </div>
            ${idx > 1 ? `<button type="button" onclick="removeEnrollCard(${idx})" class="text-slate-300 hover:text-rose-500 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>` : ''}
        </div>

        <div class="p-7 grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="flex flex-col items-center">
                <label class="cursor-pointer group w-full">
                    <div id="pv_${idx}" class="w-28 h-28 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center mb-3 overflow-hidden border-4 border-white shadow-md group-hover:scale-105 transition-transform">
                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    </div>
                    <p class="text-[9px] font-black text-emerald-600 uppercase text-center tracking-widest">Upload Photo</p>
                    <input type="file" name="photo[]" accept="image/*" class="hidden" onchange="previewEnrollImg(this,'pv_${idx}')">
                </label>
            </div>

            <div class="space-y-3">
                <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest mb-2">Personal Info</p>
                <input type="text" name="name[]" id="ename_${idx}"
                    oninput="autoGenCreds(this,${idx})"
                    class="input-flat bg-white" placeholder="FULL NAME" required maxlength="100">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <input type="number" name="age[]" id="eage_${idx}"
                            class="input-flat bg-white" placeholder="AGE" required min="12" max="120"
                            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,3)">
                        <p id="age_err_${idx}" class="hidden text-rose-500 text-[8px] font-black mt-1">12–120 only</p>
                    </div>
                    <select name="gender[]" class="input-flat bg-white text-slate-800" style="color:#1e293b">
                        <option value="Male" selected>Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <input type="tel" name="phone[]" id="ephone_${idx}"
                    class="input-flat bg-white" placeholder="09XXXXXXXXX" required
                    pattern="09[0-9]{9}" maxlength="11"
                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)">
                <p id="phone_err_${idx}" class="hidden text-rose-500 text-[8px] font-black -mt-1">Must start with 09 · 11 digits</p>
            </div>

            <div class="space-y-3">
                <p class="text-[8px] font-black text-slate-300 uppercase tracking-widest mb-2">Farm Info</p>
                <select name="barangay_id[]" class="input-flat bg-white" required>
                    <option value="">SELECT BARANGAY</option>${opts}
                </select>
                <select name="experience[]" id="eyear_${idx}" class="input-flat bg-white text-slate-800" style="color:#1e293b">
                    <option value="">YEAR REGISTERED</option>${buildYearOptions()}
                </select>
            </div>
        </div>

        <div class="mx-7 mb-7 bg-gradient-to-r from-emerald-800 to-emerald-600 rounded-2xl p-5 shadow-inner">
            <p class="text-[8px] font-black text-emerald-200 uppercase tracking-widest mb-3 flex items-center gap-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                Farmer's Login (Created Automatically)
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-[8px] font-black text-emerald-300 uppercase tracking-widest mb-1">Username</p>
                    <input type="text" id="user_${idx}" name="username[]" readonly
                        class="w-full bg-white/10 text-white font-black text-xs rounded-xl px-3 py-2.5 border border-white/20 outline-none"
                        placeholder="Fills in once you type the name">
                </div>
                <div>
                    <p class="text-[8px] font-black text-emerald-300 uppercase tracking-widest mb-1">Password</p>
                    <input type="text" id="pass_${idx}" name="password[]" readonly
                        class="w-full bg-white/10 text-white font-black text-xs rounded-xl px-3 py-2.5 border border-white/20 outline-none"
                        placeholder="Fills in once you type the name">
                </div>
            </div>
        </div>
    `;

    document.getElementById('enrollCards').appendChild(card);
    card.querySelector(`#eage_${idx}`).addEventListener('blur', function() {
        const v = parseInt(this.value);
        const err = document.getElementById('age_err_' + idx);
        const bad = isNaN(v) || v < 12 || v > 120;
        this.classList.toggle('field-err', bad);
        err.classList.toggle('hidden', !bad);
    });
    card.querySelector(`#ephone_${idx}`).addEventListener('blur', function() {
        const bad = !/^09[0-9]{9}$/.test(this.value);
        this.classList.toggle('field-err', bad);
        document.getElementById('phone_err_' + idx).classList.toggle('hidden', !bad);
    });

    updateEnrollCounter();
}

function removeEnrollCard(idx) {
    document.getElementById('card_' + idx)?.remove();
    enrollCount--;
    updateEnrollCounter();
}

function autoGenCreds(input, idx) {
    const fn = input.value.trim().split(' ')[0];
    if (!fn) return;
    const cap = fn.charAt(0).toUpperCase() + fn.slice(1).toLowerCase();
    if (!input.dataset.rand) input.dataset.rand = Math.floor(1000 + Math.random() * 9000);
    document.getElementById(`user_${idx}`).value = cap.toLowerCase() + '_' + input.dataset.rand.slice(-2);
    document.getElementById(`pass_${idx}`).value = cap + '@' + input.dataset.rand;
}

// Safety net: before the records are actually saved, make sure every farmer
// on this form has a username and password filled in (in case the name box
// was filled in a way that didn't trigger the auto-fill above). Blocks the
// save and tells the office user which farmer is missing a name if a valid
// username/password still can't be created.
function prepareEnrollSubmit(e) {
    const cards = document.querySelectorAll('#enrollCards > .enroll-card');
    for (const card of cards) {
        const idx = card.id.replace('card_', '');
        const nameInput = document.getElementById(`ename_${idx}`);
        const userInput = document.getElementById(`user_${idx}`);
        const passInput = document.getElementById(`pass_${idx}`);
        if (!userInput.value.trim() || !passInput.value.trim()) {
            if (nameInput && nameInput.value.trim()) {
                autoGenCreds(nameInput, idx);
            }
        }
        if (!userInput.value.trim() || !passInput.value.trim()) {
            e.preventDefault();
            alert(`Farmer #${idx}: please enter the farmer's full name so a username and password can be created before saving.`);
            nameInput?.focus();
            return false;
        }
    }
    return true;
}

function previewEnrollImg(input, targetId) {
    if (!input.files[0]) return;
    const r = new FileReader();
    r.onload = e => {
        document.getElementById(targetId).innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
    };
    r.readAsDataURL(input.files[0]);
}

/* ═══════════════════════════════════════════════════
   MODAL TABS
═══════════════════════════════════════════════════ */
let _currentModalFarmerName = null;
let _currentModalFarmerPhoto = null;
let _reportsLoaded = false;

function switchModalTab(tab) {
    const isInfo = tab === 'info';
    document.getElementById('panelInfo').classList.toggle('hidden', !isInfo);
    document.getElementById('panelReports').classList.toggle('hidden', isInfo);
    document.getElementById('tabBtnInfo').classList.toggle('active', isInfo);
    document.getElementById('tabBtnReports').classList.toggle('active', !isInfo);

    if (!isInfo && !_reportsLoaded) {
        loadFarmerReports(_currentModalFarmerName);
    }
}

/* ═══════════════════════════════════════════════════
   FETCH FARMER REPORTS
═══════════════════════════════════════════════════ */
function loadFarmerReports(farmerName) {
    _reportsLoaded = false;
    const list    = document.getElementById('rptList');
    const summary = document.getElementById('rptSummary');

    list.innerHTML = `
        <div id="rptLoading" class="py-16 text-center">
            <div class="w-10 h-10 border-4 border-[#cddbd3] border-t-[#1e3a34] rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Loading reports…</p>
        </div>`;
    summary.innerHTML = '';

    fetch('get_farmers_reports.php?farmer_name=' + encodeURIComponent(farmerName))
        .then(r => r.json())
        .then(data => {
            _reportsLoaded = true;
            if (!data.success) {
                list.innerHTML = `<div class="py-12 text-center text-rose-500 font-black text-xs uppercase tracking-widest">${data.message || 'Error loading reports.'}</div>`;
                return;
            }
            renderReports(data.reports, data.stats);
        })
        .catch(() => {
            list.innerHTML = `<div class="py-12 text-center text-rose-500 font-black text-xs uppercase tracking-widest">Failed to fetch report data.</div>`;
        });
}

function renderReports(reports, stats) {
    const list    = document.getElementById('rptList');
    const summary = document.getElementById('rptSummary');
    const badge   = document.getElementById('rptBadge');

    badge.textContent = reports.length;
    badge.classList.toggle('hidden', reports.length === 0);

    const statItems = [
        { label: 'Total',    val: stats.total,    icon: '📋', cls: 'bg-slate-100 text-slate-700' },
        { label: 'Pending',  val: stats.pending,  icon: '⏳', cls: 'bg-amber-50 text-amber-700' },
        { label: 'Verified', val: stats.verified, icon: '✅', cls: 'bg-[#f0f4f2] text-[#16302a]' },
        { label: 'Resolved', val: stats.resolved, icon: '🔵', cls: 'bg-blue-50 text-blue-700' },
    ];
    summary.innerHTML = statItems.map(s => `
        <div class="rounded-xl ${s.cls} px-4 py-3 text-center border border-white/40 shadow-sm">
            <div class="text-lg font-black">${s.val}</div>
            <div class="text-[8px] font-black uppercase tracking-widest opacity-70">${s.icon} ${s.label}</div>
        </div>`).join('');

    if (reports.length === 0) {
        list.innerHTML = `
            <div class="py-16 text-center">
                <div class="w-14 h-14 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="font-black text-slate-400 text-xs uppercase tracking-widest">No reports filed yet</p>
            </div>`;
        return;
    }

    const sevCls = { low:'sev-low', moderate:'sev-moderate', high:'sev-high', critical:'sev-critical' };
    const stCls  = { pending:'st-pending', verified:'st-verified', rejected:'st-rejected', resolved:'st-resolved' };

    window._rptData = reports;

    list.innerHTML = reports.map((r, idx) => {
        const sev  = r.severity || 'moderate';
        const st   = r.status   || 'pending';
        const pct  = r.infection_percentage ? parseFloat(r.infection_percentage).toFixed(1) + '%' : '—';
        const date = r.report_date ? new Date(r.report_date).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—';
        const photoHtml = r.photo_evidence
            ? `<img src="uploads/${escHtml(r.photo_evidence)}" alt="evidence" class="w-14 h-14 object-cover rounded-xl border-2 border-white shadow-sm flex-shrink-0" onerror="this.style.display='none'">`
            : '';
        return `
        <div class="rpt-card cursor-pointer hover:border-[#a9c2b5] hover:shadow-md transition-all" onclick="openReportDetail(${idx})">
            <div class="flex items-start gap-4">
                ${photoHtml}
                <div class="flex-grow min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">${escHtml(r.reference_id)}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase ${sevCls[sev] || 'sev-moderate'}">${sev}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase ${stCls[st] || 'st-pending'}">${st}</span>
                    </div>
                    <p class="font-black text-slate-800 text-sm truncate">${escHtml(r.disease_name || '—')}</p>
                    <p class="text-[10px] font-bold text-slate-400 mt-0.5">${escHtml(r.growth_stage || '—')} · ${date}</p>
                    ${r.description ? `<p class="text-[10px] font-bold text-slate-500 mt-1.5 line-clamp-2">${escHtml(r.description.replace(/^\[FARMER:.+?\]\n?/, ''))}</p>` : ''}
                </div>
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <div class="text-right">
                        <div class="text-[8px] font-black text-slate-400 uppercase">Infection</div>
                        <div class="text-lg font-black ${sev === 'critical' ? 'text-rose-600' : sev === 'high' ? 'text-orange-500' : 'text-slate-700'}">${pct}</div>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[8px] font-black text-[#1e3a34] uppercase tracking-widest bg-[#f0f4f2] px-2.5 py-1 rounded-lg border border-[#e3ebe6]">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Details
                    </span>
                </div>
            </div>
        </div>`;
    }).join('');
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ═══════════════════════════════════════════════════
   REPORT DETAIL MODAL
═══════════════════════════════════════════════════ */
function fmtDate(val) {
    if (!val) return '—';
    const d = new Date(val);
    return isNaN(d) ? val : d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

function openReportDetail(idx) {
    const r = (window._rptData || [])[idx];
    if (!r) return;

    const sevCls = { low:'sev-low', moderate:'sev-moderate', high:'sev-high', critical:'sev-critical' };
    const stCls  = { pending:'st-pending', verified:'st-verified', rejected:'st-rejected', resolved:'st-resolved' };
    const sev    = r.severity || 'moderate';
    const st     = r.status   || 'pending';

    document.getElementById('rd_reference').textContent    = r.reference_id || '';
    document.getElementById('rd_disease_name').textContent = r.disease_name || '—';
    document.getElementById('rd_disease_type').textContent = r.disease_type ? '🦠 ' + r.disease_type : '';

    const sevBadge = document.getElementById('rd_sev_badge');
    sevBadge.textContent  = sev;
    sevBadge.className    = 'inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase ' + (sevCls[sev] || 'sev-moderate');

    const stBadge = document.getElementById('rd_st_badge');
    stBadge.textContent = st;
    stBadge.className   = 'inline-flex items-center px-2 py-0.5 rounded-lg text-[8px] font-black uppercase ' + (stCls[st] || 'st-pending');

    const avatarEl = document.getElementById('rd_farmer_avatar');
    const farmerInitial = (_currentModalFarmerName || '?').charAt(0).toUpperCase();
    if (_currentModalFarmerPhoto) {
        avatarEl.innerHTML = `<img src="uploads/${escHtml(_currentModalFarmerPhoto)}" alt="Farmer"
            style="width:100%;height:100%;object-fit:cover;"
            onerror="this.parentElement.innerHTML='${farmerInitial}'">`;
    } else {
        avatarEl.textContent = farmerInitial;
    }

    const photoWrap = document.getElementById('rd_photo_wrap');
    if (r.photo_evidence) {
        document.getElementById('rd_photo').src = 'uploads/' + escHtml(r.photo_evidence);
        photoWrap.classList.remove('hidden');
    } else {
        photoWrap.classList.add('hidden');
    }

    document.getElementById('rd_pct').textContent      = r.infection_percentage ? parseFloat(r.infection_percentage).toFixed(1) + '%' : '—';
    document.getElementById('rd_affected').textContent = r.plants_affected ?? '—';
    document.getElementById('rd_total').textContent    = r.total_plants    ?? '—';

    document.getElementById('rd_report_date').textContent  = fmtDate(r.report_date);
    document.getElementById('rd_date_planted').textContent = fmtDate(r.date_planted);

    document.getElementById('rd_growth').textContent  = r.growth_stage || '—';
    document.getElementById('rd_created').textContent = fmtDate(r.created_at);

    function setOptional(wrapId, fieldId, val) {
        const wrap = document.getElementById(wrapId);
        if (val) { document.getElementById(fieldId).textContent = val; wrap.classList.remove('hidden'); }
        else      { wrap.classList.add('hidden'); }
    }
    setOptional('rd_desc_wrap',    'rd_desc',    r.description);
    setOptional('rd_treat_wrap',   'rd_treat',   r.treatment_recommendation);
    setOptional('rd_remarks_wrap', 'rd_remarks', r.remarks);

    // ── Chat with farmer ──
    window._currentChatCaseId = r.case_id;
    window._currentChatIdx    = idx;
    renderChatThread(r.messages || []);
    const chatInput = document.getElementById('rd_chat_input');
    chatInput.value = '';
    chatInput.style.height = 'auto';

    document.getElementById('reportDetailModal').classList.remove('hidden');
}

/* ═══════════════════════════════════════════════════
   CHAT WITH FARMER (report detail modal)
═══════════════════════════════════════════════════ */
function renderChatThread(messages) {
    const thread = document.getElementById('rd_chat_thread');
    const empty  = document.getElementById('rd_chat_empty');

    if (!messages || messages.length === 0) {
        thread.innerHTML = '';
        thread.classList.add('hidden');
        empty.classList.remove('hidden');
        return;
    }
    thread.classList.remove('hidden');
    empty.classList.add('hidden');

    thread.innerHTML = messages.map(m => {
        const isFarmer = m.sender === 'farmer';
        const label = isFarmer ? '👨‍🌾 Farmer' : 'You';
        const meta  = m.created_at ? `${label} · ${escHtml(m.created_at)}` : label;
        return `
        <div class="chat-row ${isFarmer ? 'farmer' : 'staff'}">
            <div class="chat-bubble ${isFarmer ? 'farmer' : 'staff'}">${escHtml(m.message)}</div>
            <div class="chat-meta">${meta}</div>
        </div>`;
    }).join('');

    thread.scrollTop = thread.scrollHeight;
}

function handleChatKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendFarmerChatMessage();
    }
}

function autoGrowChatBox(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 112) + 'px';
}

function sendFarmerChatMessage() {
    const input   = document.getElementById('rd_chat_input');
    const sendBtn = document.getElementById('rd_chat_send_btn');
    const caseId  = window._currentChatCaseId;
    const text    = input.value.trim();

    if (!text) return;
    if (!caseId) { showInlineToast('No case selected.', true); return; }

    sendBtn.disabled = true;

    fetch('send_farmer_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'case_id=' + encodeURIComponent(caseId) + '&message=' + encodeURIComponent(text)
    })
    .then(r => r.json())
    .then(data => {
        sendBtn.disabled = false;
        if (!data.success) {
            showInlineToast(data.message || 'Failed to send message.', true);
            return;
        }

        // Clear the compose box now that the send actually succeeded (this is a
        // plain fetch(), not a form submit + page reload, so this sticks).
        input.value = '';
        input.style.height = 'auto';

        // Keep window._rptData in sync so reopening this report without a refetch
        // still shows the message that was just sent.
        const idx = window._currentChatIdx;
        if (window._rptData && window._rptData[idx]) {
            window._rptData[idx].messages = window._rptData[idx].messages || [];
            window._rptData[idx].messages.push(data.message_row);
            renderChatThread(window._rptData[idx].messages);
        }
    })
    .catch(() => {
        sendBtn.disabled = false;
        showInlineToast('Failed to send message.', true);
    });
}

function closeReportDetail() {
    document.getElementById('reportDetailModal').classList.add('hidden');
}

function showInlineToast(msg, isError = false) {
    document.getElementById('inlineToast')?.remove();
    const el = document.createElement('div');
    el.id = 'inlineToast';
    el.className = `fixed bottom-6 right-6 z-[9999] px-5 py-3 rounded-2xl shadow-2xl font-black text-xs uppercase tracking-widest flex items-center gap-2 text-white transform translate-y-20 transition-transform duration-300 ${isError ? 'bg-rose-600' : 'bg-emerald-600'}`;
    el.innerHTML = `<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${isError ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>'}</svg><span>${msg}</span>`;
    document.body.appendChild(el);
    setTimeout(() => el.style.transform = 'translateY(0)', 50);
    setTimeout(() => { el.style.transform = 'translateY(100px)'; setTimeout(() => el.remove(), 400); }, 3500);
}
</script>