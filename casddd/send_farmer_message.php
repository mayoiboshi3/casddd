<?php
/**
 * send_farmer_message.php
 * AJAX endpoint — inserts a single staff chat message for a case_id and
 * returns it as JSON. Used by the chat box in farmers.php's report detail
 * modal. Kept deliberately separate from reports.php's send_recommendation
 * handler (that one also builds the "🌽 Natukoy na Sakit" findings message
 * and is form/page-reload driven); this one is a plain fetch()-based send
 * so the compose box can be cleared client-side without a reload.
 */

require_once __DIR__ . "/src/db_config.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$case_id = (int)($_POST['case_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($case_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid case ID.']);
    exit;
}
if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
    exit;
}

// Confirm the case actually exists before writing a message against it.
$check = $conn->prepare("SELECT case_id FROM disease_cases WHERE case_id = ? LIMIT 1");
$check->bind_param('i', $case_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'message' => 'Case not found.']);
    exit;
}
$check->close();

$stmt = $conn->prepare("INSERT INTO case_messages (case_id, sender, message_text) VALUES (?, 'staff', ?)");
$stmt->bind_param('is', $case_id, $message);

if ($stmt->execute()) {
    $stmt->close();
    // Pull the row back so the client renders the exact stored timestamp.
    $out = $conn->prepare("SELECT sender, message_text, created_at FROM case_messages WHERE message_id = ?");
    $newId = $conn->insert_id;
    $out->bind_param('i', $newId);
    $out->execute();
    $row = $out->get_result()->fetch_assoc();
    $out->close();

    echo json_encode([
        'success' => true,
        'message_row' => [
            'sender'     => $row['sender'],
            'message'    => $row['message_text'],
            'created_at' => $row['created_at'],
        ],
    ]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
}