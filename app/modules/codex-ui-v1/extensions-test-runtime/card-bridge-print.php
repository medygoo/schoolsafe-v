<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafePrintReply($status, $body) {
    http_response_code($status); echo json_encode($body, JSON_THROW_ON_ERROR); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); SchoolSafePrintReply(405, ['error'=>'METHOD_NOT_ALLOWED','operationPerformed'=>false]);
}
if ($_GET || array_diff(array_keys($_POST), ['student_id','csrf','format','qr_payload'])) {
    SchoolSafePrintReply(400, ['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
}
foreach ($_POST as $value) {
    if (!is_string($value)) SchoolSafePrintReply(400, ['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
}
$student = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$format = $_POST['format'] ?? '';
$qr = $_POST['qr_payload'] ?? '';
if ($student === false || !in_array($format, ['badge','carte'], true) || strlen($qr)>2048
    || !preg_match('~^schoolsafe://card/[^/\s]+/[^/\s]+$~D', $qr)) {
    SchoolSafePrintReply(400, ['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
}
chdir(__DIR__);
$_REQUEST = ['modname'=>'Student_ID_Card/StudentIDCard.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
try {
    require_once __DIR__.'/Warehouse.php';
    if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) throw new RuntimeException('ACCESS_DENIED');
    if (!is_string($_POST['csrf'] ?? null) || !is_string($_SESSION['schoolsafe_card_issue_csrf'] ?? null)
        || !hash_equals($_SESSION['schoolsafe_card_issue_csrf'], $_POST['csrf'])) throw new RuntimeException('ACCESS_DENIED');
    require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
    $input=['action'=>'print-request','nativeStudentId'=>(string)$student,'format'=>$format,'qrPayload'=>$qr];
    // Reuse the catalog-owned print command without changing the permission module.
    if (SchoolSafeEndpointGateway('scanner-relay.php','POST','management',$input)==='deny') throw new RuntimeException('ACCESS_DENIED');
    require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    SchoolSafeReadNativeCardStudent($student);
    $user=(int)User('STAFF_ID'); $school=(int)UserSchool(); $year=(int)UserSyear();
    if ($user<1 || $school<1 || $year<1) throw new RuntimeException('ACCESS_DENIED');
    $config=json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'),true,16,JSON_THROW_ON_ERROR);
    if (($config['environment'] ?? '')!=='local-isolated-test-only' || ($config['sourceInstance'] ?? '')!=='codex-addons-local'
        || !preg_match('/^[a-f0-9]{64}$/D', $config['secretHex'] ?? '')) throw new RuntimeException('CONFIG_REQUIRED');
    $now=time();
    $body=json_encode(['version'=>1,'audience'=>'schoolsafe-scan-bridge','sourceInstance'=>'codex-addons-local',
        'nativeUserId'=>(string)$user,'nativeSchoolId'=>(string)$school,'nativeYear'=>(string)$year,
        'operation'=>'management','input'=>$input,'issuedAt'=>$now,'expiresAt'=>$now+30,'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);
    $response='';
    $curl=curl_init('http://codex-card-bridge-http:8080/bridge/cards/print-request');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['body'=>$body,'signature'=>hash_hmac('sha256',$body,hex2bin($config['secretHex']))],JSON_THROW_ON_ERROR),
        CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$response){$response.=$chunk;return strlen($response)<=300000?strlen($chunk):0;}]);
    try {
        $ok=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        if ($ok===false || !in_array($status,[200,400,401,403,409,503],true)) throw new RuntimeException('RELAY_UNAVAILABLE');
        $result=json_decode($response,true,32,JSON_THROW_ON_ERROR);
        if ($status===200 && (($result['operationPerformed'] ?? false)!==true
            || ($result['data']['status'] ?? '')!=='pending' || empty($result['data']['id']))) throw new RuntimeException('RELAY_UNAVAILABLE');
    } finally { curl_close($curl); }
    while(ob_get_level()>$level) ob_end_clean();
    SchoolSafePrintReply($status,$result);
} catch (Throwable $error) {
    while(ob_get_level()>$level) ob_end_clean();
    $denied=in_array($error->getMessage(),['ACCESS_DENIED','NATIVE_ACCESS_DENIED','NATIVE_STUDENT_NOT_ACCESSIBLE','INVALID_NATIVE_CONTEXT'],true);
    SchoolSafePrintReply($denied?403:503,['error'=>'CARD_PRINT_REQUEST_REJECTED','operationPerformed'=>false]);
}
