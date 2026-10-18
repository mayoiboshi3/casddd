<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON input"
    ]);
    exit;
}

$farmer_id = intval($data['farmer_id'] ?? 0);
$disease_id = intval($data['disease_id'] ?? 0);
$confidence = isset($data['confidence']) ? floatval($data['confidence']) : null;

if ($farmer_id <= 0 || $disease_id <= 0 || $confidence === null) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "farmer_id, disease_id and confidence are required"
    ]);
    exit;
}

$sql = "INSERT INTO disease_cases (farmer_id, disease_id, confidence)
        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed"
    ]);
    exit;
}

$stmt->bind_param("iid", $farmer_id, $disease_id, $confidence);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Disease case saved successfully",
        "case_id" => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database insert failed"
    ]);
}

$stmt->close();
$conn->close();

?>