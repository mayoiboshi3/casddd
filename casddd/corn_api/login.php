<?php
header("Content-Type: application/json");

require_once __DIR__ . '/push_common.php';
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed"]));
}

$input = json_decode(file_get_contents("php://input"), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM farmers WHERE email = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $conn->error]);
    $conn->close();
    exit();
}

if (!$stmt->bind_param("s", $username)) {
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

    // DEBUG: log what's in the DB vs what was sent

    // Try both plain text AND hashed
    $passwordMatch = ($password === $row['password']) || password_verify($password, $row['password']);

    if ($passwordMatch) {
        $pushSession = push_issue_session($conn, (int)$row['farmer_id']);
        echo json_encode([
            "status" => "success",
            "push_session" => $pushSession,
            "user" => [
                "user_id"     => (int)$row['farmer_id'],
                "farmer_id"   => (int)$row['farmer_id'],  // ✅ ADD THIS LINE
                "employee_id" => $row['farmer_id'],
                "username"    => $row['email'],
                "full_name"   => $row['farmer_name'],
                "role"        => "farmer"
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Wrong password"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    echo ('Saved farmer_id: ' . ($row['farmer_id'] ?? 'Not found'));
}

$stmt->close();
$conn->close();
?>