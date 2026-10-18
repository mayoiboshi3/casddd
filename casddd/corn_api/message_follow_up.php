<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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

    echo json_encode([
        'success' => false,
        'message' => 'DB connection failed: ' . $e->getMessage(),
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| POST — FARMER SENDS MESSAGE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $caseId = intval($_POST['case_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($caseId <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'case_id is required',
        ]);

        exit;
    }

    if ($message === '') {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Message cannot be empty',
        ]);

        exit;
    }

    try {

        // Make sure the case actually exists.
        $checkStmt = $pdo->prepare("
            SELECT case_id
            FROM disease_cases
            WHERE case_id = :case_id
            LIMIT 1
        ");

        $checkStmt->execute([
            ':case_id' => $caseId,
        ]);

        if (!$checkStmt->fetch()) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'Case not found',
            ]);

            exit;
        }


        // Get the existing farmer replies.
        $getReplyStmt = $pdo->prepare("
            SELECT farmer_reply_text
            FROM disease_cases
            WHERE case_id = :case_id
            LIMIT 1
        ");

        $getReplyStmt->execute([
            ':case_id' => $caseId,
        ]);

        $case = $getReplyStmt->fetch(PDO::FETCH_ASSOC);

        $replies = [];

        if ($case && !empty($case['farmer_reply_text'])) {

            $decoded = json_decode(
                $case['farmer_reply_text'],
                true
            );

            if (is_array($decoded)) {
                $replies = $decoded;
            }
        }


        // Add the new farmer message.
        $replies[] = [
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];


        // Save the updated JSON array.
        $updatedReplies = json_encode(
            $replies,
            JSON_UNESCAPED_UNICODE
        );

        $updateStmt = $pdo->prepare("
            UPDATE disease_cases
            SET
                farmer_reply_text = :farmer_reply_text,
                farmer_reply_at = NOW()
            WHERE case_id = :case_id
        ");

        $updateStmt->execute([
            ':farmer_reply_text' => $updatedReplies,
            ':case_id' => $caseId,
        ]);


        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'case_id' => $caseId,
        ]);

        exit;

    } catch (PDOException $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Failed to send message: ' . $e->getMessage(),
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| GET — LOAD MESSAGE THREAD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);

    exit;
}

$caseId = intval($_GET['case_id'] ?? 0);

if ($caseId <= 0) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'case_id is required',
    ]);

    exit;
}

try {

    $messages = [];


    // =========================================================
    // STAFF MESSAGES
    // =========================================================

    $staffStmt = $pdo->prepare("
        SELECT
            sender,
            message_text,
            created_at
        FROM case_messages
        WHERE case_id = :case_id
        ORDER BY created_at ASC
    ");

    $staffStmt->execute([
        ':case_id' => $caseId,
    ]);

    $staffMessages = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($staffMessages as $row) {

        $messages[] = [
            'sender' => 'staff',
            'message' => $row['message_text'],
            'created_at' => $row['created_at'],
        ];
    }


    // =========================================================
    // FARMER MESSAGES
    // =========================================================

    $farmerStmt = $pdo->prepare("
        SELECT
            farmer_reply_text
        FROM disease_cases
        WHERE case_id = :case_id
        LIMIT 1
    ");

    $farmerStmt->execute([
        ':case_id' => $caseId,
    ]);

    $case = $farmerStmt->fetch(PDO::FETCH_ASSOC);

    if ($case && !empty($case['farmer_reply_text'])) {

        $replies = json_decode(
            $case['farmer_reply_text'],
            true
        );

        if (is_array($replies)) {

            foreach ($replies as $reply) {

                if (empty($reply['message'])) {
                    continue;
                }

                $messages[] = [
                    'sender' => 'farmer',
                    'message' => $reply['message'],
                    'created_at' => $reply['created_at'] ?? null,
                ];
            }
        }
    }


    // =========================================================
    // MERGE + SORT BY TIME
    // =========================================================

    usort($messages, function ($a, $b) {

        $timeA = !empty($a['created_at'])
            ? strtotime($a['created_at'])
            : 0;

        $timeB = !empty($b['created_at'])
            ? strtotime($b['created_at'])
            : 0;

        return $timeA <=> $timeB;
    });


    // =========================================================
    // RESPONSE
    // =========================================================

    echo json_encode([
        'success' => true,
        'case_id' => $caseId,
        'count' => count($messages),
        'data' => $messages,
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Query failed: ' . $e->getMessage(),
    ]);
}