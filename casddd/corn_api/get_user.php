<?php
header("Content-Type: application/json");

require_once 'config.php';
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed"]));
}

$input = json_decode(file_get_contents("php://input"), true);
$farmer_id = $input['farmer_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM farmers WHERE farmer_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $conn->error]);
    $conn->close();
    exit();
}

if (!$stmt->bind_param("i", $farmer_id)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    unset($row['password']);
    unset($row['profile_photo']); // skip longblob

    echo json_encode([
        "status" => "success",
        "data" => $row
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>