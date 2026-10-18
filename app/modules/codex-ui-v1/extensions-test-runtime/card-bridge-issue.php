<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeIssueReply($status, $data) {
    http_response_code($status); echo json_encode($data, JSON_THROW_ON_ERROR); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); SchoolSafeIssueReply(405, ['error'=>'METHOD_NOT_ALLOWED']);
}
if (!empty($_GET) || array_diff(array_keys($_POST), ['student_id','csrf'])) {
    SchoolSafeIssueReply(400, ['error'=>'INVALID_REQUEST']);
}
chdir(__DIR__);
$_REQUEST = ['modname'=>'Student_ID_Card/StudentIDCard.php','modfunc'=>''];
$level = ob_get_level(); ob_start();
try {
    require_once __DIR__.'/Warehouse.php';
    while (ob_get_level()>$level) ob_end_clean();
    if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) {
        SchoolSafeIssueReply(403, ['error'=>'NATIVE_ACCESS_DENIED']);
    }
    if (!is_string($_POST['csrf']??null) || empty($_SESSION['schoolsafe_card_issue_csrf'])
        || !hash_equals($_SESSION['schoolsafe_card_issue_csrf'], $_POST['csrf'])) {
        SchoolSafeIssueReply(403, ['error'=>'INVALID_CSRF']);
    }
    $id = filter_var($_POST['student_id']??null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id===false) SchoolSafeIssueReply(400, ['error'=>'INVALID_STUDENT_ID']);
    require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
    if (SchoolSafeEndpointGateway('card-bridge-issue.php', 'POST', '', $_POST) === 'deny') {
        SchoolSafeIssueReply(403, ['error'=>'NATIVE_ACCESS_DENIED']);
    }
    require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    require_once __DIR__.'/schoolsafe-card-bridge/NativeCardEnvelope.fnc.php';
    $config = json_decode(file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'), true, 16, JSON_THROW_ON_ERROR);
    if (($config['environment']??'')!=='local-isolated-test-only'
        || ($config['sourceInstance']??'')!=='codex-addons-local'
        || !preg_match('/^[a-f0-9]{64}$/D', $config['secretHex']??'')) throw new RuntimeException('CONFIG_REQUIRED');
    $secret = hex2bin($config['secretHex']);
    $envelope = SchoolSafeNativeCardEnvelope($id, 'codex-addons-local', $secret);
    $claim = json_decode($envelope['body'], true, 32, JSON_THROW_ON_ERROR);
    $claim['operation'] = 'issue-card';
    $envelope['body'] = json_encode($claim, JSON_THROW_ON_ERROR);
    $envelope['signature'] = hash_hmac('sha256', $envelope['body'], $secret);
    $curl = curl_init('http://codex-card-bridge-http:8080/bridge/cards/issue');
    curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_POSTFIELDS=>json_encode($envelope, JSON_THROW_ON_ERROR)]);
    try {
        $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body===false || strlen($body)>300000) throw new RuntimeException('ISSUER_UNAVAILABLE');
        $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!in_array($status, [200,400,401,403,409,503], true)) throw new RuntimeException('ISSUER_UNAVAILABLE');
        SchoolSafeIssueReply($status, $result);
    } finally { curl_close($curl); }
} catch (Throwable $error) {
    while (ob_get_level()>$level) ob_end_clean();
    SchoolSafeIssueReply(503, ['error'=>'CARD_ISSUER_UNAVAILABLE','operationPerformed'=>false]);
}
