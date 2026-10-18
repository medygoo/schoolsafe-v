<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeNotificationReply($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET || $_POST) {
    SchoolSafeNotificationReply(400, ['error'=>'INVALID_REQUEST']);
}
chdir(__DIR__);
$_REQUEST = ['modname'=>'misc/Portal.php', 'modfunc'=>''];
$level = ob_get_level();
$bridge_status = 503;
ob_start();
try {
    require_once __DIR__ . '/Warehouse.php';
    if (!User('STAFF_ID')) {
        while (ob_get_level()>$level) { ob_end_clean(); }
        SchoolSafeNotificationReply(401, ['error'=>'NATIVE_SESSION_REQUIRED']);
    }
    if (User('PROFILE') !== 'parent') { throw new RuntimeException('PARENT_REQUIRED'); }
    $user = (int)User('STAFF_ID');
    $school = (int)UserSchool();
    $year = (int)UserSyear();
    if ($user<1 || $school<1 || $year<1) { throw new RuntimeException('PARENT_REQUIRED'); }
    $rows = DBGet("SELECT DISTINCT u.STUDENT_ID FROM students_join_users u
        JOIN student_enrollment e ON e.STUDENT_ID=u.STUDENT_ID
        WHERE u.STAFF_ID=" . $user . " AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year);
    $ids = [];
    foreach ($rows as $row) { $ids[] = (string)$row['STUDENT_ID']; }
    if (count($ids)>500) { throw new RuntimeException('NATIVE_SCOPE_TOO_LARGE'); }
    $config = json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'), true, 16, JSON_THROW_ON_ERROR);
    if (($config['environment']??'') !== 'local-isolated-test-only'
        || ($config['sourceInstance']??'') !== 'codex-addons-local'
        || !preg_match('/^[a-f0-9]{64}$/D',$config['secretHex']??'')) { throw new RuntimeException('CONFIG_REQUIRED'); }
    $now = time();
    $body = json_encode(['version'=>1,'audience'=>'schoolsafe-scan-bridge',
        'sourceInstance'=>'codex-addons-local','nativeUserId'=>(string)$user,
        'nativeSchoolId'=>(string)$school,'nativeYear'=>(string)$year,
        'operation'=>'notifications','input'=>['nativeStudentIds'=>$ids],
        'issuedAt'=>$now,'expiresAt'=>$now+30,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
    $curl = curl_init('http://codex-card-bridge-http:8080/bridge/scanner/notifications');
    $response = '';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_POSTFIELDS=>json_encode(['body'=>$body,'signature'=>hash_hmac('sha256',$body,hex2bin($config['secretHex']))],JSON_THROW_ON_ERROR),
        CURLOPT_WRITEFUNCTION=>function ($handle, $chunk) use (&$response) {
            if (strlen($response)+strlen($chunk)>300000) { return 0; }
            $response .= $chunk; return strlen($chunk);
        }]);
    try {
        $ok=curl_exec($curl);
        $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        if (in_array($status,[400,401,403],true)) { $bridge_status=$status; }
        if ($ok===false || $status!==200) { throw new RuntimeException('RELAY_UNAVAILABLE'); }
        $result=json_decode($response,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($result['data']['notifications']??null)
            || count($result['data']['notifications'])>100
            || !is_array($result['data']['principalStudentIds']??null)) { throw new RuntimeException('INVALID_RESULT'); }
        foreach ($result['data']['principalStudentIds'] as $id) {
            if (!is_string($id) || !in_array($id,$ids,true)) { throw new RuntimeException('INVALID_RESULT'); }
        }
    } finally { curl_close($curl); }
    while (ob_get_level()>$level) { ob_end_clean(); }
    SchoolSafeNotificationReply(200,$result);
} catch (Throwable $error) {
    while (ob_get_level()>$level) { ob_end_clean(); }
    SchoolSafeNotificationReply($error->getMessage()==='PARENT_REQUIRED'?403:$bridge_status,['error'=>'NATIVE_NOTIFICATIONS_UNAVAILABLE']);
}
