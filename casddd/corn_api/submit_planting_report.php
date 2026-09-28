<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {

    // ============================================================
    // 1. GET INPUT
    // ============================================================

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    /*
     * Pagtatanim / Pag-aani
     * Flutter sends JSON
     */
    if (stripos($contentType, 'application/json') !== false) {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!$data) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data.'
            ]);
            exit;
        }

        $reference_id = trim(
            $data['reference_id'] ?? ''
        );

        $farmer_id = intval(
            $data['farmer_id'] ?? 0
        );

        $report_type = trim(
            $data['report_type'] ?? ''
        );

        $crop_type = trim(
            $data['crop_type'] ?? ''
        );

        $variety = isset($data['variety'])
            ? trim($data['variety'])
            : null;

        $area_hectares = isset($data['area_hectares'])
            ? floatval($data['area_hectares'])
            : null;

        $privacy_consent = isset($data['privacy_consent'])
            ? (bool)$data['privacy_consent']
            : false;

        $description = null;
        $latitude = null;
        $longitude = null;
        $gps_accuracy = null;
        $photo_path = null;

    /*
     * Damage / Growth
     * Flutter sends multipart/form-data
     */
    } else {

        $reference_id = trim(
            $_POST['reference_id'] ?? ''
        );

        $farmer_id = intval(
            $_POST['farmer_id'] ?? 0
        );

        $report_type = trim(
            $_POST['report_type'] ?? ''
        );

        $crop_type = null;
        $variety = null;
        $area_hectares = null;
        $privacy_consent = true;

        $description = trim(
            $_POST['description'] ?? ''
        );

        $latitude = (
            isset($_POST['latitude']) &&
            $_POST['latitude'] !== ''
        )
            ? floatval($_POST['latitude'])
            : null;

        $longitude = (
            isset($_POST['longitude']) &&
            $_POST['longitude'] !== ''
        )
            ? floatval($_POST['longitude'])
            : null;

        $gps_accuracy = (
            isset($_POST['gps_accuracy']) &&
            $_POST['gps_accuracy'] !== ''
        )
            ? floatval($_POST['gps_accuracy'])
            : null;

        $photo_path = null;
    }


    // ============================================================
    // 2. VALIDATE BASIC FIELDS
    // ============================================================

    if ($reference_id === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'reference_id is required.'
        ]);

        exit;
    }


    if ($farmer_id <= 0) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'farmer_id is required.'
        ]);

        exit;
    }


    // ============================================================
    // 3. VALIDATE REPORT TYPE
    // ============================================================

    $allowedReportTypes = [
        'planting',
        'harvesting',
        'damage',
        'growth'
    ];

    if (!in_array($report_type, $allowedReportTypes)) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid report type.'
        ]);

        exit;
    }


    // ============================================================
    // 4. VALIDATION FOR PLANTING / HARVESTING
    // ============================================================

    if (
        $report_type === 'planting' ||
        $report_type === 'harvesting'
    ) {

        if (
            $crop_type !== 'yellow_corn' &&
            $crop_type !== 'white_corn'
        ) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid crop type.'
            ]);

            exit;
        }


        if (
            $area_hectares === null ||
            $area_hectares <= 0
        ) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Area must be greater than 0.'
            ]);

            exit;
        }


        if (!$privacy_consent) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Privacy consent is required.'
            ]);

            exit;
        }
    }


    // ============================================================
    // 5. HANDLE PHOTO FOR DAMAGE / GROWTH
    // ============================================================

    if (
        $report_type === 'damage' ||
        $report_type === 'growth'
    ) {

        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] === UPLOAD_ERR_OK
        ) {

            $upload_dir = __DIR__ . '/uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }


            $allowed = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];


            $ext = strtolower(
                pathinfo(
                    $_FILES['photo']['name'],
                    PATHINFO_EXTENSION
                )
            );


            if (!in_array($ext, $allowed)) {

                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid photo type.'
                ]);

                exit;
            }


            $filename =
                'report_' .
                time() .
                '_' .
                uniqid() .
                '.' .
                $ext;


            $destination =
                $upload_dir . $filename;


            if (
                !move_uploaded_file(
                    $_FILES['photo']['tmp_name'],
                    $destination
                )
            ) {

                http_response_code(500);

                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save uploaded photo.'
                ]);

                exit;
            }


            $photo_path =
                'uploads/' . $filename;
        }
    }


    // ============================================================
    // 6. CHECK DUPLICATE REFERENCE ID
    // ============================================================

    $check = $conn->prepare("
        SELECT
            report_id,
            report_type,
            status
        FROM planting_harvesting_reports
        WHERE reference_id = ?
        LIMIT 1
    ");

    if (!$check) {

        throw new Exception(
            'Failed to prepare duplicate check: ' .
            $conn->error
        );
    }


    $check->bind_param(
        "s",
        $reference_id
    );

    $check->execute();

    $result = $check->get_result();


    if ($result->num_rows > 0) {

        $existing = $result->fetch_assoc();

        $check->close();

        echo json_encode([
            'success' => true,
            'message' => 'Report already exists.',
            'already_exists' => true,
            'report_id' => (int)$existing['report_id'],
            'reference_id' => $reference_id,
            'report_type' => $existing['report_type'],
            'status' => $existing['status']
        ]);

        exit;
    }


    $check->close();

   
    
    // ============================================================
    // 7. INSERT REPORT
    // ============================================================

    $status = 'received';

// ============================================================
// 7. INSERT REPORT
// ============================================================

$status = 'received';

$privacyValue = $privacy_consent ? 1 : 0;

$stmt = $conn->prepare("
    INSERT INTO planting_harvesting_reports
    (
        reference_id,
        farmer_id,
        report_type,
        crop_type,
        variety,
        area_hectares,
        privacy_consent,
        description,
        photo,
        status,
        latitude,
        longitude,
        gps_accuracy
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    throw new Exception(
        'Failed to prepare insert: ' .
        $conn->error
    );
}

$stmt->bind_param(
    "sisssidsssddd",
    $reference_id,
    $farmer_id,
    $report_type,
    $crop_type,
    $variety,
    $area_hectares,
    $privacyValue,
    $description,
    $photo_path,
    $status,
    $latitude,
    $longitude,
    $gps_accuracy
);

if (!$stmt->execute()) {
    throw new Exception(
        'Failed to insert report: ' .
        $stmt->error
    );
}

$report_id = $conn->insert_id;

$stmt->close();


// ============================================================
// 8. SUCCESS RESPONSE
// ============================================================
echo json_encode([
    'success' => true,
    'message' => 'Report submitted successfully.',
    'report_id' => (int)$report_id,
    'reference_id' => $reference_id,
    'report_type' => $report_type,
    'status' => $status,
    'gps_attached' => (
        $latitude !== null &&
        $longitude !== null
    )
]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit report: ' . $e->getMessage()
    ]);
}

?>