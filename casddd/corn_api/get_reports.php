<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $user,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$farmer_id = intval($_GET['farmer_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
if ($farmer_id === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'farmer_id is required']);
    exit;
}

try {

    $sql = "
        SELECT 
            dc.case_id, 
            dc.reference_id, 
            dc.report_date, 
            dc.growth_stage, 
            dc.plants_affected, 
            dc.total_plants, 
            dc.infection_percentage, 
            dc.severity, 
            dc.description, 
            dc.photo_evidence, 
            dc.status, 
            dc.verified_date, 
            dc.treatment_recommendation, 
            dc.follow_up_date, 
            dc.remarks, 
            dc.created_at, 
            d.disease_name

        FROM disease_cases dc

        LEFT JOIN diseases d 
            ON dc.disease_id = d.disease_id

        WHERE dc.farmer_id = :farmer_id
          AND (dc.source IS NULL OR dc.source <> 'scan')
    ";

    $params = [
        ':farmer_id' => $farmer_id
    ];

    // Apply status filter only when a status was selected
    if (!empty($status)) {
        $sql .= " AND dc.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY dc.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($cases),
        'data'    => $cases,
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Query failed: ' . $e->getMessage()
    ]);
}
?>