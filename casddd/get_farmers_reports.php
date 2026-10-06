<?php
/**
 * get_farmers_reports.php
 * AJAX endpoint — returns all manual disease_cases for a given farmer as JSON.
 * The farmer is identified by farmer_id (disease_cases.farmer_id).
 */

require_once __DIR__ . "/src/db_config.php";

header('Content-Type: application/json');

/* ── Validate input ──────────────────────────────── */
$farmerId = (int)($_GET['farmer_id'] ?? 0);

if ($farmerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Farmer is required.']);
    exit;
}

/* ── Query disease_cases by farmer_id ── */
$stmt = $conn->prepare("
    SELECT
        dc.case_id,
        dc.reference_id,
        dc.report_date,
        dc.growth_stage,
        dc.date_planted,
        dc.plants_affected,
        dc.total_plants,
        dc.infection_percentage,
        dc.severity,
        dc.description,
        dc.photo_evidence,
        dc.status,
        dc.treatment_recommendation,
        dc.follow_up_date,
        dc.created_at,
        dc.remarks,
        COALESCE(d.disease_name, 'Unknown Disease') AS disease_name,
        d.disease_type,
        d.severity_level AS disease_severity_level
    FROM disease_cases dc
    LEFT JOIN diseases d ON dc.disease_id = d.disease_id
    WHERE dc.source = 'manual_report'
      AND dc.farmer_id = ?
    ORDER BY dc.report_date DESC
");

$stmt->bind_param('i', $farmerId);
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
while ($row = $result->fetch_assoc()) {
    $row['description'] = $row['description'] ?? '';
    $row['messages']    = fetchCaseMessages($conn, $row['case_id']);
    $reports[] = $row;
}
$stmt->close();

/* ── Helper: load every logged message for a case, oldest first ──── */
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

/* ── Aggregate stats ─────────────────────────────── */
$stats = [
    'total'    => count($reports),
    'pending'  => 0,
    'verified' => 0,
    'rejected' => 0,
    'resolved' => 0,
];
foreach ($reports as $r) {
    $s = $r['status'] ?? 'pending';
    if (isset($stats[$s])) $stats[$s]++;
}

echo json_encode([
    'success' => true,
    'reports' => $reports,
    'stats'   => $stats,
]);