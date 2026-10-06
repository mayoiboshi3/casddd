<?php
// CLI only. --install creates tables and baselines existing statuses without alerts.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/push_common.php';
function request_json(string $url, string $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) throw new RuntimeException(curl_error($ch));
    curl_close($ch);
    return [$code, json_decode($raw,true) ?? []];
}
function base64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
try {
    if (!(int)$conn->query("SELECT GET_LOCK('corn_doctor_push',0)")->fetch_row()[0]) exit;
    if (in_array('--install', $argv, true)) {
        push_schema($conn);
        $conn->query("INSERT IGNORE INTO push_case_state(case_id,status) SELECT case_id,LOWER(TRIM(COALESCE(status,'pending'))) FROM disease_cases WHERE source IS NULL OR source <> 'scan'");
        echo "Push tables installed; existing statuses baselined.\n";
        exit;
    }
    $keyPath = getenv('CORN_FIREBASE_KEY') ?: 'C:/xampp/project/corn-doctor-23730-firebase-adminsdk-fbsvc-a29efd6025.json';
    if (!is_file($keyPath)) throw new RuntimeException('Firebase service-account file missing: '.$keyPath);
    $key = json_decode(file_get_contents($keyPath),true,512,JSON_THROW_ON_ERROR);
    $now = time();
    $jwt = base64url(json_encode(['alg'=>'RS256','typ'=>'JWT'])).'.'.base64url(json_encode(['iss'=>$key['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]));
    if (!openssl_sign($jwt,$signature,$key['private_key'],OPENSSL_ALGO_SHA256)) throw new RuntimeException('JWT signing failed');
    [$code,$oauth] = request_json('https://oauth2.googleapis.com/token',http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt.'.'.base64url($signature)]),['Content-Type: application/x-www-form-urlencoded']);
    if ($code!==200 || empty($oauth['access_token'])) throw new RuntimeException('Firebase authentication failed; HTTP '.$code);
    if (in_array('--check', $argv, true)) {
        $devices = (int)$conn->query('SELECT COUNT(*) FROM push_devices WHERE enabled=1')->fetch_row()[0];
        echo "Firebase authentication OK. Enabled devices: $devices\n";
        exit;
    }
    $cases = $conn->query("SELECT case_id,farmer_id,reference_id,LOWER(TRIM(COALESCE(status,'pending'))) AS status FROM disease_cases WHERE source IS NULL OR source <> 'scan'");
    foreach ($cases as $case) {
        $id=(int)$case['case_id']; $farmer=(int)$case['farmer_id']; $status=$case['status']; $ref=$case['reference_id'] ?? '';
        $conn->begin_transaction();
        try {
            $old=$conn->query("SELECT status,revision FROM push_case_state WHERE case_id=$id FOR UPDATE")->fetch_assoc();
            $changed=$old && $old['status']!==$status;
            $revision=$old ? (int)$old['revision']+($changed?1:0) : 0;
            if ($changed && in_array($status,['verified','rejected','resolved'],true)) {
                $stmt=$conn->prepare('INSERT IGNORE INTO push_queue(case_id,revision,device_id,farmer_id,status,reference_id,next_attempt) SELECT ?,?,id,?,?,?,UTC_TIMESTAMP() FROM push_devices WHERE farmer_id=? AND enabled=1');
                $stmt->bind_param('iiissi',$id,$revision,$farmer,$status,$ref,$farmer); $stmt->execute();
            }
            $stmt=$conn->prepare('INSERT INTO push_case_state VALUES(?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),revision=VALUES(revision)');
            $stmt->bind_param('isi',$id,$status,$revision); $stmt->execute(); $conn->commit();
        } catch(Throwable $e) { $conn->rollback(); throw $e; }
    }
    $jobs=$conn->query('SELECT q.*,d.token FROM push_queue q JOIN push_devices d ON d.id=q.device_id AND d.farmer_id=q.farmer_id AND d.enabled=1 WHERE q.sent_at IS NULL AND q.next_attempt<=UTC_TIMESTAMP() ORDER BY q.id LIMIT 50');
    foreach($jobs as $job) {
        $labels=['verified'=>'Na-verify','rejected'=>'Tinanggihan','resolved'=>'Nalutas'];
        $body=$labels[$job['status']].' ang ulat '.$job['reference_id'].'. Buksan ang app para sa detalye.';
        $payload=['message'=>['token'=>$job['token'],'notification'=>['title'=>'Update sa iyong ulat','body'=>$body],'data'=>['event_id'=>(string)$job['id'],'case_id'=>(string)$job['case_id'],'farmer_id'=>(string)$job['farmer_id'],'reference_id'=>$job['reference_id'],'status'=>$job['status']],'android'=>['priority'=>'HIGH','notification'=>['channel_id'=>'corn_doctor_notifications','tag'=>'case-'.$job['case_id'].'-'.$job['revision']]]]];
        [$code,$result]=request_json('https://fcm.googleapis.com/v1/projects/'.rawurlencode($key['project_id']).'/messages:send',json_encode($payload),['Content-Type: application/json','Authorization: Bearer '.$oauth['access_token']]);
        $id=(int)$job['id'];
        if($code===200) $conn->query("UPDATE push_queue SET sent_at=UTC_TIMESTAMP(),attempts=attempts+1 WHERE id=$id");
        else {
            foreach($result['error']['details'] ?? [] as $detail) if(($detail['errorCode'] ?? '')==='UNREGISTERED') $conn->query('UPDATE push_devices SET enabled=0 WHERE id='.(int)$job['device_id']);
            $conn->query("UPDATE push_queue SET attempts=attempts+1,next_attempt=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE id=$id");
            error_log('FCM send failed for queue '.$id.' HTTP '.$code);
        }
    }
    echo "Push check complete.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
