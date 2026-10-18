<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$reference_id    = trim($_POST['reference_id']    ?? '');
$farmer_id       = intval($_POST['farmer_id']     ?? 0);
$reported_by     = intval($_POST['reported_by']   ?? $farmer_id);
$report_date     = trim($_POST['report_date']     ?? date('Y-m-d H:i:s'));
$description     = trim($_POST['description']     ?? '');
$growth_stage    = trim($_POST['growth_stage']    ?? '');
$plants_affected = trim($_POST['plants_affected'] ?? '');
$planting_date   = trim($_POST['planting_date']   ?? '');

$latitude     = isset($_POST['latitude'])     && $_POST['latitude']     !== '' ? floatval($_POST['latitude'])     : null;
$longitude    = isset($_POST['longitude'])    && $_POST['longitude']    !== '' ? floatval($_POST['longitude'])    : null;
$gps_accuracy = isset($_POST['gps_accuracy']) && $_POST['gps_accuracy'] !== '' ? floatval($_POST['gps_accuracy']) : null;
$altitude     = isset($_POST['altitude'])     && $_POST['altitude']     !== '' ? floatval($_POST['altitude'])     : null;

$errors = [];
if (empty($reference_id)) $errors[] = 'reference_id is required';
if ($reported_by === 0)   $errors[] = 'reported_by is required';
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

$photo_evidence = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid photo type']);
        exit;
    }
    $filename = 'case_' . time() . '_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
        $photo_evidence = 'uploads/' . $filename;
    }
}

$report_date_fmt = date('Y-m-d H:i:s', strtotime($report_date));
$planting_date_fmt = !empty($planting_date) ? date('Y-m-d', strtotime($planting_date)) : null;

try {
    $sql = "
        INSERT INTO disease_cases (
            reference_id, farmer_id, reported_by, report_date,
            description, photo_evidence, status, growth_stage,
            plants_affected, planting_date, latitude, longitude, gps_accuracy, altitude
        ) VALUES (
            :reference_id, :farmer_id, :reported_by, :report_date,
            :description, :photo_evidence, 'pending', :growth_stage,
            :plants_affected, :planting_date, :latitude, :longitude, :gps_accuracy, :altitude
        )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reference_id'    => $reference_id,
        ':farmer_id'       => $farmer_id,
        ':reported_by'     => $reported_by,
        ':report_date'     => $report_date_fmt,
        ':description'     => !empty($description)     ? $description             : null,
        ':photo_evidence'  => $photo_evidence,
        ':growth_stage'    => !empty($growth_stage)    ? $growth_stage            : null,
        ':plants_affected' => !empty($plants_affected) ? intval($plants_affected) : null,
        ':planting_date'   => $planting_date_fmt,
        ':latitude'        => $latitude,
        ':longitude'       => $longitude,
        ':gps_accuracy'    => $gps_accuracy,
        ':altitude'        => $altitude,
    ]);

    $case_id = $pdo->lastInsertId();

    echo json_encode([
        'success'      => true,
        'message'      => 'Disease case submitted successfully',
        'case_id'      => (int)$case_id,
        'reference_id' => $reference_id,
        'status'       => 'pending',
        'gps_attached' => ($latitude !== null && $longitude !== null),
        'coordinates'  => $latitude !== null ? [
            'lat'        => $latitude,
            'lng'        => $longitude,
            'accuracy_m' => $gps_accuracy,
            'altitude_m' => $altitude,
        ] : null,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $e->getMessage()]);
}
?>