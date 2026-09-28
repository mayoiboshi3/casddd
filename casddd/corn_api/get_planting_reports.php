<?php

header('Content-Type: application/json');

require_once 'config.php';

try {

    if (!isset($_GET['farmer_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing farmer_id.'
        ]);
        exit;
    }

    $farmerId = (int)$_GET['farmer_id'];

    $stmt = $conn->prepare("
        SELECT
            report_id,
            reference_id,
            farmer_id,
            report_type,
            crop_type,
            variety,
            area_hectares,
            status,
            submitted_at
        FROM planting_harvesting_reports
        WHERE farmer_id = ?
        ORDER BY submitted_at DESC
    ");

    if (!$stmt) {
        throw new Exception(
            'Failed to prepare query: ' . $conn->error
        );
    }

    $stmt->bind_param("i", $farmerId);
    $stmt->execute();

    $result = $stmt->get_result();

    $reports = [];

    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'report_id' => (int)$row['report_id'],
            'reference_id' => $row['reference_id'],
            'farmer_id' => (int)$row['farmer_id'],
            'report_type' => $row['report_type'],
            'crop_type' => $row['crop_type'],
            'variety' => $row['variety'],
            'area_hectares' => (float)$row['area_hectares'],
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}