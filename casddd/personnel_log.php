<?php
/**
 * PERSONNEL LOG
 * ----------------------------------------------------------------------
 * Tracks WHICH STAFF MEMBER did what to a disease case report:
 *
 *   - Created   -> who entered the report manually (disease_cases.reported_by).
 *                  Reports that arrive from a farmer scan (source = 'scan')
 *                  have no staff creator and are labelled as such.
 *   - Verified  -> who moved the case Pending -> Verified
 *                  (disease_cases.verified_by / verified_at)
 *   - Rejected  -> who moved the case Pending -> Rejected
 *                  (disease_cases.rejected_by / rejected_at)
 *   - Resolved  -> who moved the case Verified -> Resolved
 *                  (disease_cases.resolved_by / resolved_at)
 *
 * Farm reports (planting_harvesting_reports) use personnel_log_build_farm() at the
 * bottom of this file: created_by / verified_by / rejected_by (+ verified_at, rejected_at).
 *
 * Used by:
 *   - new_case_report.php  -> personnel_log_current_user_id() on insert
 *   - reports.php          -> stamps verified/rejected on status change,
 *                             and joins the users table into the case queries
 *   - review.php           -> draws the "Personnel Log" card in the review popup
 *
 * Requires these columns on disease_cases (added manually in the database):
 *   reported_by INT, verified_by INT, verified_at DATETIME,
 *   rejected_by INT, rejected_at DATETIME,
 *   resolved_by INT, resolved_at DATETIME
 * ----------------------------------------------------------------------
 */

// ── WHO IS LOGGED IN RIGHT NOW? ──
// Returns the users.user_id of the signed-in staff member, or 0 if it can't
// be determined. Looks at the usual session keys; if only a username is in
// the session, it is resolved against the users table.
function personnel_log_current_user_id($conn) {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    foreach (['user_id', 'id', 'userid', 'uid'] as $key) {
        if (!empty($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
            return (int)$_SESSION[$key];
        }
    }
    // Some apps keep the whole user record in $_SESSION['user']
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        foreach (['user_id', 'id'] as $key) {
            if (!empty($_SESSION['user'][$key]) && (int)$_SESSION['user'][$key] > 0) {
                return (int)$_SESSION['user'][$key];
            }
        }
    }
    if (!empty($_SESSION['username'])) {
        $uname = mysqli_real_escape_string($conn, (string)$_SESSION['username']);
        $q = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$uname' LIMIT 1");
        if ($q && ($r = mysqli_fetch_assoc($q))) { return (int)$r['user_id']; }
    }
    return 0;
}

// users.role enum('agri1','agri2','admin') -> readable label
function personnel_log_role_label($role) {
    return match ($role) {
        'agri1' => 'Agriculturist I',
        'agri2' => 'Agriculturist II',
        'admin' => 'Administrator',
        default => null,
    };
}

// ── SQL FRAGMENTS: pull staff names into the case queries ──
// Add personnel_log_select_sql() to the SELECT list and personnel_log_join_sql()
// after the other JOINs of any query that aliases disease_cases as `dc`.
function personnel_log_select_sql() {
    return "uc.full_name AS creator_name,  uc.role AS creator_role,
            uv.full_name AS verifier_name, uv.role AS verifier_role,
            ur.full_name AS rejecter_name, ur.role AS rejecter_role,
            urv.full_name AS resolver_name, urv.role AS resolver_role";
}
function personnel_log_join_sql() {
    return "LEFT JOIN users uc ON dc.reported_by = uc.user_id
            LEFT JOIN users uv ON dc.verified_by = uv.user_id
            LEFT JOIN users ur ON dc.rejected_by = ur.user_id
            LEFT JOIN users urv ON dc.resolved_by = urv.user_id";
}

// ── BUILD THE LOG ENTRIES FOR ONE CASE ROW ──
// $row = a disease_cases row that was fetched with the SQL fragments above.
// Returns a list of entries the review popup renders as a timeline:
//   ['action' => 'created'|'verified'|'rejected'|'resolved',
//    'name'   => staff full name, or a system label, or null (= not recorded),
//    'role'   => readable role label or null,
//    'at'     => formatted date/time or null,
//    'system' => true when there is no staff member by design (farmer scan)]
function personnel_log_build($row) {
    $fmt = function ($value) {
        $t = $value ? strtotime($value) : false;
        return $t ? date('M d, Y \a\t g:i A', $t) : null;
    };
    // verified_date is a plain DATE column — show it without a made-up time
    $fmtDate = function ($value) {
        $t = $value ? strtotime($value) : false;
        return $t ? date('M d, Y', $t) : null;
    };
    // Name lookup that also copes with a staff account that was later deleted
    $who = function ($id, $name) {
        if (!empty($name)) { return $name; }
        return ((int)$id > 0) ? 'Former staff (ID ' . (int)$id . ')' : null;
    };

    $status = $row['status'] ?? 'pending';
    $log    = [];

    // ── Created ──
    if (($row['source'] ?? 'manual_report') === 'scan') {
        $log[] = [
            'action' => 'created',
            'name'   => 'Farmer scan (automatic)',
            'role'   => null,
            'at'     => $fmt($row['report_date'] ?? null),
            'system' => true,
        ];
    } else {
        $log[] = [
            'action' => 'created',
            'name'   => $who($row['reported_by'] ?? 0, $row['creator_name'] ?? null),
            'role'   => personnel_log_role_label($row['creator_role'] ?? null),
            'at'     => $fmt($row['report_date'] ?? null),
            'system' => false,
        ];
    }

    // ── Verified (also shown once resolved — a resolved case was verified first) ──
    if (in_array($status, ['verified', 'resolved'], true) || !empty($row['verified_by'])) {
        $log[] = [
            'action' => 'verified',
            'name'   => $who($row['verified_by'] ?? 0, $row['verifier_name'] ?? null),
            'role'   => personnel_log_role_label($row['verifier_role'] ?? null),
            'at'     => $fmt($row['verified_at'] ?? null) ?? $fmtDate($row['verified_date'] ?? null),
            'system' => false,
        ];
    }

    // ── Rejected ──
    if ($status === 'rejected' || !empty($row['rejected_by'])) {
        $log[] = [
            'action' => 'rejected',
            'name'   => $who($row['rejected_by'] ?? 0, $row['rejecter_name'] ?? null),
            'role'   => personnel_log_role_label($row['rejecter_role'] ?? null),
            'at'     => $fmt($row['rejected_at'] ?? null),
            'system' => false,
        ];
    }

    // ── Resolved ──
    if ($status === 'resolved' || !empty($row['resolved_by'])) {
        $log[] = [
            'action' => 'resolved',
            'name'   => $who($row['resolved_by'] ?? 0, $row['resolver_name'] ?? null),
            'role'   => personnel_log_role_label($row['resolver_role'] ?? null),
            'at'     => $fmt($row['resolved_at'] ?? null),
            'system' => false,
        ];
    }

    return $log;
}

// ── FARM REPORTS (planting_harvesting_reports) ──
// Same idea as personnel_log_build() above, for the Farm Reports section.
// $row = a planting_harvesting_reports row fetched together with the users joins
// (creator_name/role, verifier_name/role, rejecter_name/role — see ph_report_select_sql()
// in planting_harvesting.php). Returns entries in the same shape the popup renders:
//   created  -> the staff member who added it by hand, or "Farmer app" for farmer submissions
//   verified -> who accepted it
//   rejected -> who rejected it (the reason itself lives in the report's remarks)
function personnel_log_build_farm($row) {
    $fmt = function ($value) {
        $t = $value ? strtotime($value) : false;
        return $t ? date('M d, Y \a\t g:i A', $t) : null;
    };
    $who = function ($id, $name) {
        if (!empty($name)) { return $name; }
        return ((int)$id > 0) ? 'Former staff (ID ' . (int)$id . ')' : null;
    };

    $status = $row['status'] ?? '';
    if ($status === '' || $status === null || $status === 'received') { $status = 'pending'; }

    $log = [];

    // ── Created ──
    if (($row['source'] ?? 'farmer') === 'farmer') {
        $log[] = [
            'action' => 'created',
            'name'   => 'Farmer app (submitted by the farmer)',
            'role'   => null,
            'at'     => $fmt($row['submitted_at'] ?? null),
            'system' => true,
        ];
    } else {
        $log[] = [
            'action' => 'created',
            'name'   => $who($row['created_by'] ?? 0, $row['creator_name'] ?? null),
            'role'   => personnel_log_role_label($row['creator_role'] ?? null),
            'at'     => $fmt($row['submitted_at'] ?? null),
            'system' => false,
        ];
    }

    // ── Verified ──
    if ($status === 'verified' || !empty($row['verified_by'])) {
        $log[] = [
            'action' => 'verified',
            'name'   => $who($row['verified_by'] ?? 0, $row['verifier_name'] ?? null),
            'role'   => personnel_log_role_label($row['verifier_role'] ?? null),
            'at'     => $fmt($row['verified_at'] ?? null),
            'system' => false,
        ];
    }

    // ── Rejected ──
    if ($status === 'rejected' || !empty($row['rejected_by'])) {
        $log[] = [
            'action' => 'rejected',
            'name'   => $who($row['rejected_by'] ?? 0, $row['rejecter_name'] ?? null),
            'role'   => personnel_log_role_label($row['rejecter_role'] ?? null),
            'at'     => $fmt($row['rejected_at'] ?? null),
            'system' => false,
        ];
    }

    return $log;
}