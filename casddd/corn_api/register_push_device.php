<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
try {
    require_once __DIR__ . '/push_common.php';
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    // Apache may expose the header through getallheaders rather than $_SERVER.
    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) { $auth = $value; break; }
        }
    }
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/', $auth, $match)) { http_response_code(401); exit; }
    $hash = hash('sha256', $match[1]);
    $stmt = $conn->prepare('SELECT farmer_id FROM push_sessions WHERE token_hash=? AND expires_at>UTC_TIMESTAMP()');
    $stmt->bind_param('s', $hash); $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    if (!$session) { http_response_code(401); exit; }
    $farmer = (int)$session['farmer_id'];
    $token = $data['token'] ?? '';
    if (!is_string($token) || strlen($token)<20 || strlen($token)>4096) { http_response_code(400); exit; }
    $tokenHash = hash('sha256', $token);
    if (($data['action'] ?? '') === 'unregister') {
        $stmt = $conn->prepare('UPDATE push_devices SET enabled=0 WHERE token_hash=? AND farmer_id=?');
        $stmt->bind_param('si', $tokenHash, $farmer);
    } else {
        $enabled = ($data['enabled'] ?? true) ? 1 : 0;
        $stmt = $conn->prepare('INSERT INTO push_devices(token_hash,token,farmer_id,enabled,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE farmer_id=VALUES(farmer_id),enabled=VALUES(enabled),updated_at=UTC_TIMESTAMP()');
        $stmt->bind_param('ssii', $tokenHash, $token, $farmer, $enabled);
    }
    $stmt->execute();
    echo json_encode(['success'=>true]);
} catch (Throwable $e) { error_log('Push registration failed: '.$e->getMessage()); http_response_code(500); echo json_encode(['success'=>false]); }
