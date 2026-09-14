<?php
/**
 * accounts.php — Account Manager. ADMIN ONLY.
 *
 * The guard below runs first, before anything else in this file — including
 * the API branch. Previously the API handlers (list/update/delete/create)
 * ran before includes/layout.php was reached, which was the only thing
 * checking login. That meant the API could be called by anyone, logged in
 * or not, admin or not. Checking here first closes that hole for both the
 * page view and every API action.
 */
require_once __DIR__ . "/src/session_guard.php"; // starts session, redirects to index.php if not logged in

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

if (!$isAdmin) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Access denied. Admins only.']);
        exit;
    }
    // Employees never see this page — send them back to the dashboard.
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . "/src/db_config.php";

/* ============================================================
   API HANDLER
============================================================ */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    // The currently logged-in admin's own account. It only ever appears in
    // the header (see header.php) — never as a row to manage/edit/delete
    // here, so an admin can't accidentally demote, disable, or delete
    // themselves through this screen.
    $currentAdminId = (int)($_SESSION['user_id'] ?? 0);

    /**
     * Re-authentication helper for state-changing actions (update/delete/create).
     * The admin must re-enter their OWN current password before any change is
     * persisted. This protects against someone using an admin's already-open,
     * unattended session to alter accounts. Returns true and lets the caller
     * proceed only if the supplied password matches the logged-in admin's
     * stored hash; otherwise it emits an error response and returns false.
     */
    function verifyAdminPassword($conn, $currentAdminId, $suppliedPassword) {
        if (!$currentAdminId || empty($suppliedPassword)) {
            echo json_encode(['ok' => false, 'msg' => 'Please enter your password to confirm this change.']);
            return false;
        }
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $currentAdminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($suppliedPassword, $row['password'])) {
            echo json_encode(['ok' => false, 'msg' => 'Incorrect password. No changes were made.']);
            return false;
        }
        return true;
    }

    // --- LIST USERS ---
    if ($_GET['api'] === 'list') {
        $rows = [];
        $res = $conn->query("SELECT user_id, username, full_name, email, role, profile_photo, phone_number, status, created_at FROM users WHERE user_id != $currentAdminId ORDER BY role ASC, full_name ASC");
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        echo json_encode(['users' => $rows]);
        exit;
    }

    // --- UPDATE USER ---
    if ($_GET['api'] === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['user_id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Invalid user ID']); exit; }
        if ($id === $currentAdminId) { echo json_encode(['ok' => false, 'msg' => 'You cannot edit your own account from here.']); exit; }

        if (!verifyAdminPassword($conn, $currentAdminId, $body['admin_password'] ?? '')) { exit; }

        $full_name    = $conn->real_escape_string(trim($body['full_name']    ?? ''));
        $email        = $conn->real_escape_string(trim($body['email']        ?? ''));
        $username     = $conn->real_escape_string(trim($body['username']     ?? ''));
        $phone_number = $conn->real_escape_string(trim($body['phone_number'] ?? ''));
        $status       = in_array($body['status'] ?? '', ['active','inactive'])          ? $body['status'] : 'active';
        $role         = in_array($body['role']   ?? '', ['agri1','agri2','admin'])      ? $body['role']   : 'agri1';

        $setParts = [
            "full_name='$full_name'",
            "email='$email'",
            "username='$username'",
            "phone_number=" . ($phone_number ? "'$phone_number'" : "NULL"),
            "status='$status'",
            "role='$role'"
        ];

        if (!empty($body['new_password'])) {
            $hashed = password_hash($body['new_password'], PASSWORD_BCRYPT);
            $hashed = $conn->real_escape_string($hashed);
            $setParts[] = "password='$hashed'";
        }

        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE user_id=$id";
        $ok  = $conn->query($sql);
        echo json_encode(['ok' => (bool)$ok, 'msg' => $ok ? 'Account updated successfully.' : $conn->error]);
        exit;
    }

    // --- DELETE USER ---
    if ($_GET['api'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $id   = (int)($body['user_id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }
        if ($id === $currentAdminId) { echo json_encode(['ok' => false, 'msg' => 'You cannot delete your own account from here.']); exit; }

        if (!verifyAdminPassword($conn, $currentAdminId, $body['admin_password'] ?? '')) { exit; }

        $ok = $conn->query("DELETE FROM users WHERE user_id=$id");
        echo json_encode(['ok' => (bool)$ok, 'msg' => $ok ? 'User deleted.' : $conn->error]);
        exit;
    }

    // --- CREATE USER ---
    if ($_GET['api'] === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true);

        if (!verifyAdminPassword($conn, $currentAdminId, $body['admin_password'] ?? '')) { exit; }

        $username = $conn->real_escape_string(trim($body['username']     ?? ''));
        $fullname = $conn->real_escape_string(trim($body['full_name']    ?? ''));
        $email    = $conn->real_escape_string(trim($body['email']        ?? ''));
        $phone    = $conn->real_escape_string(trim($body['phone_number'] ?? ''));
        $role     = in_array($body['role'] ?? '', ['agri1','agri2','admin']) ? $body['role'] : 'agri1';
        $pwd      = $body['password'] ?? '';
        if (!$username || !$fullname || !$email || !$pwd) {
            echo json_encode(['ok' => false, 'msg' => 'All required fields must be filled.']); exit;
        }
        $hashed = $conn->real_escape_string(password_hash($pwd, PASSWORD_BCRYPT));
        $ok = $conn->query("INSERT INTO users (username, password, full_name, email, phone_number, role, status) VALUES ('$username','$hashed','$fullname','$email'," . ($phone ? "'$phone'" : "NULL") . ",'$role','active')");
        echo json_encode(['ok' => (bool)$ok, 'msg' => $ok ? 'Account created successfully.' : $conn->error]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown API action']);
    exit;
}

$pageTitle = "Account Management";
include "includes/layout.php";
?>

<style>
:root { --em: #10b981; --em-dk: #064e3b; --slate: #0f172a; }

.acct-card { background:#fff; border:1px solid #f1f5f9; border-radius:2rem; box-shadow:0 10px 25px -5px rgba(0,0,0,.05); }

.acct-th { padding:1rem 1.1rem; color:#94a3b8; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; white-space:nowrap; }
.acct-td { padding:.9rem 1.1rem; font-size:.82rem; color:#334155; font-weight:700; border-top:1px solid #f8fafc; }
#userTableBody tr { cursor:pointer; transition:background .15s, box-shadow .15s; }
#userTableBody tr:hover { background:#f0fdf4 !important; box-shadow:inset 3px 0 0 var(--em); }

.badge { display:inline-flex; align-items:center; gap:5px; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.07em; padding:4px 12px; border-radius:999px; }
.badge-active   { background:#d1fae5; color:#065f46; }
.badge-inactive { background:#fee2e2; color:#991b1b; }
.badge-admin    { background:#ede9fe; color:#5b21b6; }
.badge-agri1    { background:#dbeafe; color:#1e40af; }
.badge-agri2    { background:#d1fae5; color:#047857; }

.einput { background:#f8fafc; border:2px solid transparent; border-radius:1rem; padding:.75rem 1rem; font-weight:800; font-size:.875rem; color:#1e293b; width:100%; transition:all .2s; outline:none; font-family:inherit; }
.einput:focus { border-color:var(--em); background:#fff; }
.einput.err   { border-color:#fca5a5 !important; background:#fff5f5 !important; }

.lbl { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.12em; color:#94a3b8; display:block; margin-bottom:5px; }

.sec-label { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.15em; color:var(--em); display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.sec-label::after { content:''; flex:1; height:1px; background:#f1f5f9; }

#drawerOverlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(4px); z-index:400; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

#drawerPanel { position:fixed; top:0; right:0; bottom:0; width:100%; max-width:480px; background:#fff; z-index:500; overflow-y:auto; box-shadow:-20px 0 60px rgba(0,0,0,.15); border-radius:2rem 0 0 2rem; transform:translateX(100%); transition:transform .35s cubic-bezier(.4,0,.2,1); display:flex; flex-direction:column; }
#drawerPanel.open { transform:translateX(0); }

.avatar-ring { width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,#064e3b,#10b981); display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:900; color:#fff; flex-shrink:0; }

.toggle-wrap { display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
.toggle-track { width:44px; height:24px; border-radius:999px; background:#e2e8f0; position:relative; transition:background .2s; flex-shrink:0; }
.toggle-track.on { background:var(--em); }
.toggle-thumb { width:18px; height:18px; border-radius:50%; background:#fff; position:absolute; top:3px; left:3px; transition:transform .2s cubic-bezier(.4,0,.2,1); box-shadow:0 1px 4px rgba(0,0,0,.2); }
.toggle-track.on .toggle-thumb { transform:translateX(20px); }

#toast { position:fixed; bottom:2rem; right:2rem; z-index:9999; transform:translateY(140%); transition:transform .4s cubic-bezier(.34,1.56,.64,1); pointer-events:none; }
#toast.show { transform:translateY(0); }

.kpi-card { transition:transform .3s cubic-bezier(.4,0,.2,1), box-shadow .3s; }
.kpi-card:hover { transform:translateY(-4px); box-shadow:0 20px 40px -10px rgba(0,0,0,.12); }

.search-box { background:#f8fafc; border:2px solid transparent; border-radius:1rem; padding:.65rem 1rem .65rem 2.5rem; font-weight:800; font-size:.82rem; color:#1e293b; outline:none; transition:border-color .2s; width:220px; }
.search-box:focus { border-color:var(--em); background:#fff; }

#confirmOverlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.7); backdrop-filter:blur(6px); z-index:600; align-items:center; justify-content:center; }
#confirmOverlay.show { display:flex; }

::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:99px; }

.pwd-wrap { position:relative; }
.pwd-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94a3b8; transition:color .15s; background:none; border:none; padding:4px; }
.pwd-eye:hover { color:var(--em); }

.confirm-pwd-box { background:#fffbeb; border:1px solid #fde68a; border-radius:1rem; padding:1rem; }
</style>

<div class="p-4 md:p-10">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-10">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-widest mb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Account Management
            </div>
            <h2 class="text-4xl font-black text-slate-900 tracking-tighter leading-none">
                User <span class="text-emerald-600">Accounts</span>
            </h2>
            <p class="text-slate-400 font-bold text-xs mt-2 uppercase tracking-tight">Manage CASD &amp; Admin System Accounts</p>
        </div>
        <button onclick="openCreate()" class="bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg active:scale-95 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Account
        </button>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-5 mb-10">
        <div class="kpi-card acct-card p-6">
            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Total Accounts</p>
            <h4 id="kpiTotal" class="text-4xl font-black mt-1 text-gray-800">—</h4>
        </div>
        <div class="kpi-card bg-emerald-600 p-6 rounded-[2rem] shadow-lg">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Active</p>
            <h4 id="kpiActive" class="text-4xl font-black mt-1 text-white">—</h4>
        </div>
        <div class="kpi-card bg-violet-600 p-6 rounded-[2rem] shadow-lg">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Admins</p>
            <h4 id="kpiAdmin" class="text-4xl font-black mt-1 text-white">—</h4>
        </div>
        <div class="kpi-card bg-blue-600 p-6 rounded-[2rem] shadow-lg">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Agriculturist I</p>
            <h4 id="kpiAgri1" class="text-4xl font-black mt-1 text-white">—</h4>
        </div>
        <div class="kpi-card bg-teal-600 p-6 rounded-[2rem] shadow-lg">
            <p class="text-[9px] font-black uppercase tracking-widest text-white/70">Agriculturist II</p>
            <h4 id="kpiAgri2" class="text-4xl font-black mt-1 text-white">—</h4>
        </div>
    </div>

    <!-- Table Card -->
    <div class="acct-card overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-8 pt-8 pb-5 border-b border-gray-50">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-5 bg-emerald-500 rounded-full"></span> All Accounts
            </h3>
            <div class="flex items-center gap-3 flex-wrap">
                <select id="roleFilter" onchange="filterTable()" class="einput" style="width:auto;padding:.5rem .9rem;font-size:10px;">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="agri1">Agriculturist I</option>
                    <option value="agri2">Agriculturist II</option>
                </select>
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
                    <input id="searchBox" type="text" placeholder="Search accounts…" class="search-box" oninput="filterTable()">
                </div>
                <button onclick="loadUsers()" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-50 hover:text-emerald-700 transition-all">
                    ↻ Refresh
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="acct-th text-left">#</th>
                        <th class="acct-th text-left">User</th>
                        <th class="acct-th text-left">Username</th>
                        <th class="acct-th text-left">Role</th>
                        <th class="acct-th text-left">Status</th>
                        <th class="acct-th text-left">Created</th>
                        <th class="acct-th text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <tr>
                        <td colspan="7" class="acct-td text-center py-16">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                                <span class="text-xs font-black uppercase tracking-widest text-gray-300">Loading accounts…</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-8 py-4 border-t border-gray-50 flex items-center justify-between">
            <p id="tableCount" class="text-[10px] font-black uppercase tracking-widest text-gray-300">—</p>
            <p class="text-[9px] font-bold text-gray-200 uppercase tracking-widest">CORNCASD System &middot; <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<!-- DRAWER OVERLAY -->
<div id="drawerOverlay" onclick="closeDrawer()"></div>

<!-- DRAWER PANEL -->
<div id="drawerPanel">
    <div class="flex items-start justify-between px-8 pt-8 pb-6 border-b border-gray-50 flex-shrink-0">
        <div class="flex items-center gap-4">
            <div id="drawerAvatar" class="avatar-ring">?</div>
            <div>
                <p id="drawerMode" class="text-[9px] font-black uppercase tracking-widest text-emerald-500 mb-1">Edit Account</p>
                <h3 id="drawerTitle" class="text-xl font-black text-slate-900 leading-tight">—</h3>
                <p id="drawerSub" class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mt-0.5"></p>
            </div>
        </div>
        <button onclick="closeDrawer()" class="bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-xl p-2 transition-colors flex-shrink-0 ml-4">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6 space-y-6">
        <input type="hidden" id="editId">

        <!-- Personal Info -->
        <div>
            <div class="sec-label">Personal Information</div>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="lbl">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" id="editFullName" class="einput" placeholder="e.g. Juan Dela Cruz">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">Email <span class="text-red-400">*</span></label>
                        <input type="email" id="editEmail" class="einput" placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="lbl">Phone Number</label>
                        <input type="text" id="editPhone" class="einput" placeholder="09XX XXX XXXX">
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Credentials -->
        <div>
            <div class="sec-label">Account Credentials</div>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="lbl">Username <span class="text-red-400">*</span></label>
                    <input type="text" id="editUsername" class="einput" placeholder="username">
                </div>
                <div>
                    <label class="lbl" id="pwdLabel">New Password <span class="text-gray-300 font-bold normal-case">(leave blank to keep)</span></label>
                    <div class="pwd-wrap">
                        <input type="password" id="editPassword" class="einput" placeholder="••••••••" style="padding-right:2.5rem;">
                        <button type="button" class="pwd-eye" onclick="togglePwd('editPassword', this)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role & Status -->
        <div>
            <div class="sec-label">Access &amp; Status</div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Role <span class="text-red-400">*</span></label>
                    <select id="editRole" class="einput">
                        <option value="agri1">Agriculturist I</option>
                        <option value="agri2">Agriculturist II</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="lbl">Account Status</label>
                    <div class="einput flex items-center justify-between" style="cursor:default;">
                        <span id="statusLabel" class="font-black text-sm text-gray-700">Active</span>
                        <div class="toggle-wrap" onclick="toggleStatus()">
                            <div id="toggleTrack" class="toggle-track on">
                                <div class="toggle-thumb"></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="editStatus" value="active">
                </div>
            </div>
        </div>

        <!-- Confirm Admin Password -->
        <div class="confirm-pwd-box">
            <label class="lbl" style="color:#b45309;">Confirm Your Admin Password <span class="text-red-400">*</span></label>
            <div class="pwd-wrap">
                <input type="password" id="editAdminPassword" class="einput" placeholder="Enter your account password to save" style="padding-right:2.5rem;" autocomplete="current-password">
                <button type="button" class="pwd-eye" onclick="togglePwd('editAdminPassword', this)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <p class="text-[10px] font-bold text-amber-600 mt-2">Required to save any change to this account.</p>
        </div>
    </div>

    <!-- Drawer Footer -->
    <div class="flex-shrink-0 px-8 py-6 border-t border-gray-50 flex items-center gap-3">
        <button id="deleteBtn" onclick="confirmDelete()" class="bg-red-50 text-red-500 hover:bg-red-100 px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all">
            Delete
        </button>
        <div class="flex-1"></div>
        <button onclick="closeDrawer()" class="bg-gray-100 text-gray-600 hover:bg-gray-200 px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all">
            Cancel
        </button>
        <button id="saveBtn" onclick="saveUser()" class="bg-gray-900 text-white hover:bg-emerald-600 px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-lg active:scale-95">
            Save Changes
        </button>
    </div>
</div>

<!-- CONFIRM DELETE -->
<div id="confirmOverlay">
    <div class="bg-white rounded-[2rem] p-10 max-w-sm w-full mx-4 text-center shadow-2xl">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <h3 class="text-xl font-black text-slate-900 mb-2">Delete Account?</h3>
        <p class="text-sm font-bold text-gray-400 mb-1">You are about to delete:</p>
        <p id="confirmName" class="text-base font-black text-slate-900 mb-3">—</p>
        <p class="text-xs text-red-400 font-bold mb-5">This action cannot be undone.</p>
        <div class="text-left mb-6">
            <label class="lbl" style="color:#b45309;">Confirm Your Admin Password <span class="text-red-400">*</span></label>
            <div class="pwd-wrap">
                <input type="password" id="deleteAdminPassword" class="einput" placeholder="Enter your account password" style="padding-right:2.5rem;" autocomplete="current-password">
                <button type="button" class="pwd-eye" onclick="togglePwd('deleteAdminPassword', this)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
        </div>
        <div class="flex gap-3">
            <button onclick="closeConfirmDelete()" class="flex-1 bg-gray-100 text-gray-600 hover:bg-gray-200 px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all">
                Cancel
            </button>
            <button id="confirmDeleteBtn" class="flex-1 bg-red-600 text-white hover:bg-red-700 px-5 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all">
                Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="flex items-center gap-4 bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl min-w-[260px]">
    <div id="toastIcon" class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 bg-emerald-500">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    </div>
    <p id="toastMsg" class="font-black text-sm">Done.</p>
</div>

<script>
let allUsers  = [];
let editMode  = 'edit';
let currentId = null;

function initials(name) {
    return (name || '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
}
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}
function showToast(msg, ok = true) {
    const t  = document.getElementById('toast');
    const ic = document.getElementById('toastIcon');
    document.getElementById('toastMsg').innerText = msg;
    ic.className = `w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 ${ok ? 'bg-emerald-500' : 'bg-red-500'}`;
    ic.innerHTML = ok
        ? `<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>`
        : `<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

async function loadUsers() {
    try {
        const res  = await fetch('accounts.php?api=list');
        const data = await res.json();
        allUsers = data.users || [];
        updateKPIs();
        renderTable(allUsers);
    } catch(e) {
        showToast('Failed to load accounts.', false);
    }
}

function updateKPIs() {
    document.getElementById('kpiTotal').innerText  = allUsers.length;
    document.getElementById('kpiActive').innerText = allUsers.filter(u => u.status === 'active').length;
    document.getElementById('kpiAdmin').innerText  = allUsers.filter(u => u.role === 'admin').length;
    document.getElementById('kpiAgri1').innerText  = allUsers.filter(u => u.role === 'agri1').length;
    document.getElementById('kpiAgri2').innerText  = allUsers.filter(u => u.role === 'agri2').length;
}

// Human-readable labels for role codes stored in the DB
const ROLE_LABELS = { admin: 'Admin', agri1: 'Agriculturist I', agri2: 'Agriculturist II' };

function renderTable(users) {
    const tbody = document.getElementById('userTableBody');
    document.getElementById('tableCount').innerText = `Showing ${users.length} of ${allUsers.length} accounts`;
    if (!users.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="acct-td text-center py-14 text-gray-300"><p class="font-black text-xs uppercase tracking-widest">No accounts found</p></td></tr>`;
        return;
    }
    tbody.innerHTML = users.map((u, i) => `
        <tr onclick="openEdit(${u.user_id})" class="${i % 2 === 0 ? '' : 'bg-gray-50/40'}">
            <td class="acct-td text-gray-300 font-black text-xs">${u.user_id}</td>
            <td class="acct-td">
                <div class="flex items-center gap-3">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#064e3b,#10b981);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:900;flex-shrink:0;">
                        ${initials(u.full_name)}
                    </div>
                    <div>
                        <p class="font-black text-slate-900 text-sm">${u.full_name}</p>
                        <p style="font-size:10px;font-weight:700;color:#94a3b8;">${u.email}</p>
                    </div>
                </div>
            </td>
            <td class="acct-td font-black text-slate-700">@${u.username}</td>
            <td class="acct-td"><span class="badge badge-${u.role}">${ROLE_LABELS[u.role] || u.role.toUpperCase()}</span></td>
            <td class="acct-td">
                <span class="badge ${u.status === 'active' ? 'badge-active' : 'badge-inactive'}">
                    <span style="width:6px;height:6px;border-radius:50%;background:${u.status === 'active' ? '#10b981' : '#f87171'};"></span>
                    ${u.status.charAt(0).toUpperCase() + u.status.slice(1)}
                </span>
            </td>
            <td class="acct-td" style="color:#94a3b8;font-size:11px;">${fmtDate(u.created_at)}</td>
            <td class="acct-td">
                <button onclick="event.stopPropagation();openEdit(${u.user_id})"
                    class="bg-gray-100 hover:bg-emerald-100 hover:text-emerald-700 text-gray-500 px-3 py-1.5 rounded-lg font-black uppercase tracking-widest transition-all" style="font-size:10px;">
                    Edit
                </button>
            </td>
        </tr>
    `).join('');
}

function filterTable() {
    const q    = document.getElementById('searchBox').value.toLowerCase().trim();
    const role = document.getElementById('roleFilter').value;
    const filtered = allUsers.filter(u => {
        const matchRole = !role || u.role === role;
        const matchQ    = !q || u.full_name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q) || (u.email||'').toLowerCase().includes(q);
        return matchRole && matchQ;
    });
    renderTable(filtered);
}

function openEdit(id) {
    const u = allUsers.find(x => x.user_id == id);
    if (!u) return;
    editMode  = 'edit';
    currentId = id;

    document.getElementById('editId').value       = u.user_id;
    document.getElementById('editFullName').value = u.full_name;
    document.getElementById('editEmail').value    = u.email;
    document.getElementById('editPhone').value    = u.phone_number || '';
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editRole').value     = u.role;
    document.getElementById('editPassword').value = '';
    document.getElementById('editAdminPassword').value = '';
    document.getElementById('editAdminPassword').classList.remove('err');

    setStatus(u.status === 'active');

    document.getElementById('drawerAvatar').innerText = initials(u.full_name);
    document.getElementById('drawerMode').innerText   = 'Edit Account';
    document.getElementById('drawerTitle').innerText  = u.full_name;
    document.getElementById('drawerSub').innerText    = `@${u.username} · ${ROLE_LABELS[u.role] || u.role.toUpperCase()}`;
    document.getElementById('pwdLabel').innerHTML     = 'New Password <span style="color:#94a3b8;font-weight:700;text-transform:none;letter-spacing:0;">(leave blank to keep)</span>';
    document.getElementById('deleteBtn').style.display = '';
    document.getElementById('saveBtn').innerText      = 'Save Changes';

    showDrawer();
}

function openCreate() {
    editMode  = 'create';
    currentId = null;

    ['editFullName','editEmail','editPhone','editUsername','editPassword','editAdminPassword'].forEach(id => {
        document.getElementById(id).value = '';
        document.getElementById(id).classList.remove('err');
    });
    document.getElementById('editRole').value = 'agri1';
    setStatus(true);

    document.getElementById('drawerAvatar').innerText  = '+';
    document.getElementById('drawerMode').innerText    = 'New Account';
    document.getElementById('drawerTitle').innerText   = 'Create Account';
    document.getElementById('drawerSub').innerText     = 'Fill in the details below';
    document.getElementById('pwdLabel').innerHTML      = 'Password <span style="color:#f87171;">*</span>';
    document.getElementById('deleteBtn').style.display = 'none';
    document.getElementById('saveBtn').innerText       = 'Create Account';

    showDrawer();
}

function showDrawer() {
    document.getElementById('drawerOverlay').style.display = 'block';
    setTimeout(() => document.getElementById('drawerPanel').classList.add('open'), 10);
}

function closeDrawer() {
    document.getElementById('drawerPanel').classList.remove('open');
    setTimeout(() => document.getElementById('drawerOverlay').style.display = 'none', 350);
}

function setStatus(active) {
    const track = document.getElementById('toggleTrack');
    const label = document.getElementById('statusLabel');
    const input = document.getElementById('editStatus');
    if (active) { track.classList.add('on'); label.innerText = 'Active'; input.value = 'active'; }
    else { track.classList.remove('on'); label.innerText = 'Inactive'; input.value = 'inactive'; }
}
function toggleStatus() { setStatus(document.getElementById('editStatus').value !== 'active'); }

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    inp.type  = inp.type === 'password' ? 'text' : 'password';
}

async function saveUser() {
    const full_name      = document.getElementById('editFullName').value.trim();
    const email          = document.getElementById('editEmail').value.trim();
    const username       = document.getElementById('editUsername').value.trim();
    const phone_number   = document.getElementById('editPhone').value.trim();
    const role           = document.getElementById('editRole').value;
    const status         = document.getElementById('editStatus').value;
    const password       = document.getElementById('editPassword').value;
    const admin_password = document.getElementById('editAdminPassword').value;

    let ok = true;
    [['editFullName', full_name], ['editEmail', email], ['editUsername', username]].forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (!val) { el.classList.add('err'); ok = false; } else el.classList.remove('err');
    });
    if (editMode === 'create' && !password) {
        document.getElementById('editPassword').classList.add('err'); ok = false;
    } else if (password || editMode !== 'create') {
        document.getElementById('editPassword').classList.remove('err');
    }
    // The admin must re-confirm their own password before any account change is saved.
    const adminPwdEl = document.getElementById('editAdminPassword');
    if (!admin_password) { adminPwdEl.classList.add('err'); ok = false; } else adminPwdEl.classList.remove('err');

    if (!ok) { showToast('Please fill all required fields.', false); return; }

    const btn = document.getElementById('saveBtn');
    const origText = btn.innerText;
    btn.innerText = '…'; btn.disabled = true;

    const endpoint = editMode === 'create' ? 'accounts.php?api=create' : 'accounts.php?api=update';
    const payload  = { full_name, email, username, phone_number, role, status, admin_password };
    if (editMode === 'edit')   { payload.user_id = currentId; if (password) payload.new_password = password; }
    if (editMode === 'create') { payload.password = password; }

    try {
        const res  = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
        const data = await res.json();
        showToast(data.msg, data.ok);
        if (data.ok) {
            closeDrawer(); loadUsers();
        } else {
            // Wrong password (or other failure) — highlight the field and keep the drawer open.
            document.getElementById('editAdminPassword').classList.add('err');
        }
    } catch(e) {
        showToast('Server error. Try again.', false);
    }

    btn.innerText = origText; btn.disabled = false;
}

function confirmDelete() {
    const u = allUsers.find(x => x.user_id == currentId);
    document.getElementById('confirmName').innerText = u ? u.full_name : '—';
    document.getElementById('deleteAdminPassword').value = '';
    document.getElementById('deleteAdminPassword').classList.remove('err');
    document.getElementById('confirmOverlay').classList.add('show');

    document.getElementById('confirmDeleteBtn').onclick = async () => {
        const admin_password = document.getElementById('deleteAdminPassword').value;
        if (!admin_password) {
            document.getElementById('deleteAdminPassword').classList.add('err');
            showToast('Please enter your password to confirm deletion.', false);
            return;
        }
        try {
            const res  = await fetch('accounts.php?api=delete', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ user_id: currentId, admin_password }) });
            const data = await res.json();
            showToast(data.msg, data.ok);
            if (data.ok) {
                closeConfirmDelete(); closeDrawer(); loadUsers();
            } else {
                document.getElementById('deleteAdminPassword').classList.add('err');
            }
        } catch(e) { showToast('Delete failed.', false); }
    };
}

function closeConfirmDelete() {
    document.getElementById('confirmOverlay').classList.remove('show');
}

window.onload = loadUsers;
</script>

<?php include "includes/layout-end.php"; ?>