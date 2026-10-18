<?php
// corn_api/save_scan_result.php
// Saves an AI scan result into disease_cases with source = 'scan'

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// ── DB Connection ─────────────────────────────────────────────────────────────
require_once 'config.php';
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit();
}

// ── Parse POST fields ─────────────────────────────────────────────────────────
$farmer_id  = isset($_POST['farmer_id'])  ? intval($_POST['farmer_id'])          : null;
$farm_id    = isset($_POST['farm_id'])    ? intval($_POST['farm_id'])             : null;
$class_name = isset($_POST['class_name']) ? trim($_POST['class_name'])            : null;
$confidence = isset($_POST['confidence']) ? floatval($_POST['confidence'])        : null;
$is_healthy = isset($_POST['is_healthy']) && $_POST['is_healthy'] === 'true';
$latitude   = isset($_POST['latitude'])   ? floatval($_POST['latitude'])          : null;
$longitude  = isset($_POST['longitude'])  ? floatval($_POST['longitude'])         : null;

// Validate required fields
if (!$farmer_id || !$class_name || $confidence === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// ── Get barangay_id from farms table ──────────────────────────────────────────
$barangay_id = null;
if ($farm_id) {
    $stmt = $conn->prepare("SELECT barangay_id FROM farmers WHERE farmer_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $conn->error]);
        $conn->close();
        exit();
    }

    if (!$stmt->bind_param("i", $farm_id)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->bind_result($barangay_id);
    $stmt->fetch();
    $stmt->close();
}

// ── Get disease_id from diseases table ────────────────────────────────────────
// Adjust 'class_name' below to whatever column your diseases table uses
$disease_id = null;
if (!$is_healthy && $class_name !== 'Other') {
    $stmt = $conn->prepare("SELECT disease_id FROM diseases WHERE disease_name = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $conn->error]);
        $conn->close();
        exit();
    }

    if (!$stmt->bind_param("s", $class_name)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->bind_result($disease_id);
    $stmt->fetch();
    $stmt->close();
}

// ── Handle photo upload ───────────────────────────────────────────────────────
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/uploads/scan_results/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'scan_' . $farmer_id . '_' . time() . '.' . $ext;
    $dest     = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        $photo_path = 'uploads/scan_results/' . $filename;
    }
}

// ── Generate reference_id ─────────────────────────────────────────────────────
$result = $conn->query("SELECT COUNT(*) as total FROM disease_cases WHERE source = 'scan'");
$row = $result->fetch_assoc();
$next_num = $row['total'] + 1;
$reference_id = 'SCN-REF-' . $next_num;

// ── Build description ─────────────────────────────────────────────────────────
$confidence_pct = round($confidence * 100, 1);
$description = $is_healthy
    ? "AI scan: Healthy corn detected. Confidence: {$confidence_pct}%"
    : "AI scan: {$class_name} detected. Confidence: {$confidence_pct}%";

// Status: healthy = verified automatically, disease = pending for CASD review
$status = $is_healthy ? 'verified' : 'pending';

// ── Insert into disease_cases ─────────────────────────────────────────────────
$sql = "INSERT INTO disease_cases (
            reference_id,
            farmer_id,
            barangay_id,
            disease_id,
            reported_by,
            report_date,
            latitude,
            longitude,
            photo_evidence,
            status,
            description,
            source,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'scan', NOW(), NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $conn->error]);
    $conn->close();
    exit();
}

if (!$stmt->bind_param(
    "siiiiddsss",
    $reference_id,   // s
    $farmer_id,      // i
    $barangay_id,    // i
    $disease_id,     // i
    $farmer_id,      // i  (reported_by = farmer)
    $latitude,       // d
    $longitude,      // d
    $photo_path,     // s
    $status,         // s
    $description     // s
)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

$case_id = $conn->insert_id;
echo json_encode([
    'success'      => true,
    'message'      => 'Scan result saved successfully',
    'case_id'      => $case_id,
    'reference_id' => $reference_id,
]);

$stmt->close();
$conn->close();