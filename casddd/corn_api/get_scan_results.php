<?php
// corn_api/get_scan_results.php
// Retrieves AI scan results for a specific farmer.
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}



if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Get farmer ID
$farmer_id = isset($_GET['farmer_id'])
    ? intval($_GET['farmer_id'])
    : 0;

if ($farmer_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid farmer_id'
    ]);
    $conn->close();
    exit();
}

// Get scans belonging to this farmer
$sql = "SELECT
            dc.case_id,
            dc.reference_id,
            dc.farmer_id,
            dc.report_date,
            dc.latitude,
            dc.longitude,
            dc.photo_evidence,
            dc.status,
            dc.description,
            dc.source,
            dc.created_at,
            dc.updated_at,
            d.disease_name
        FROM disease_cases dc
        LEFT JOIN diseases d
            ON dc.disease_id = d.disease_id
        WHERE dc.farmer_id = ?
          AND dc.source = 'scan'
        ORDER BY dc.report_date DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $conn->error
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $farmer_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $stmt->error
    ]);
    $stmt->close();
    $conn->close();
    exit();
}

$result = $stmt->get_result();

$scans = [];

while ($row = $result->fetch_assoc()) {

    // Default disease name
    $diseaseName = $row['disease_name'];

// If the diseases table doesn't contain the disease,
// get the original AI class from the description.
if (empty($diseaseName)) {

    if (stripos($row['description'], 'Healthy corn detected') !== false) {
        $diseaseName = 'Corn___Healthy';
    } else {
        // Example:
        // AI scan: Corn___Common_Rust detected. Confidence: 93.8%

        if (preg_match(
            '/AI scan:\s*(.+?)\s+detected\./i',
            $row['description'],
            $matches
        )) {
            $diseaseName = trim($matches[1]);
        } else {
            $diseaseName = 'Other';
        }
    }

    }

    // Extract confidence from description.
    // Example:
    // "AI scan: Corn___Common_Rust detected. Confidence: 93.8%"
    $confidence = null;

    if (preg_match(
        '/Confidence:\s*([0-9]+(?:\.[0-9]+)?)%/i',
        $row['description'],
        $matches
    )) {
        // Convert percentage to decimal.
        // 93.8% becomes 0.938
        $confidence = floatval($matches[1]) / 100;
    }

    $scans[] = [
        'case_id' => (int) $row['case_id'],
        'reference_id' => $row['reference_id'],
        'farmer_id' => (int) $row['farmer_id'],

        'disease_name' => $diseaseName,
        'confidence' => $confidence,

        'report_date' => $row['report_date'],
        'latitude' => $row['latitude'] !== null
            ? floatval($row['latitude'])
            : null,
        'longitude' => $row['longitude'] !== null
            ? floatval($row['longitude'])
            : null,

        'photo_evidence' => $row['photo_evidence'],
        'status' => $row['status'],
        'description' => $row['description'],
        'source' => $row['source'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($scans),
    'data' => $scans
]);

$stmt->close();
$conn->close();