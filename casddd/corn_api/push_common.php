<?php
// Shared by the authenticated endpoint and CLI worker.
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
function push_schema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS push_sessions (token_hash CHAR(64) PRIMARY KEY, farmer_id INT NOT NULL, expires_at DATETIME NOT NULL)");
    $db->query("CREATE TABLE IF NOT EXISTS push_devices (id BIGINT AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) UNIQUE NOT NULL, token TEXT NOT NULL, farmer_id INT NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL)");
    $db->query("CREATE TABLE IF NOT EXISTS push_case_state (case_id INT PRIMARY KEY, status VARCHAR(64) NOT NULL, revision INT NOT NULL DEFAULT 0)");
    $db->query("CREATE TABLE IF NOT EXISTS push_queue (id BIGINT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, revision INT NOT NULL, device_id BIGINT NOT NULL, farmer_id INT NOT NULL, status VARCHAR(64) NOT NULL, reference_id VARCHAR(255) NOT NULL, sent_at DATETIME NULL, attempts INT NOT NULL DEFAULT 0, next_attempt DATETIME NOT NULL, UNIQUE KEY event_device(case_id, revision, device_id))");
}
function push_issue_session(mysqli $db, int $farmer): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $db->prepare('INSERT INTO push_sessions VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
    $stmt->bind_param('si', $hash, $farmer);
    $stmt->execute();
    return $token;
}
