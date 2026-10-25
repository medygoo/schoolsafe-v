<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
function SchoolSafePrintReadReply($status,$body) {
    http_response_code($status); echo json_encode($body,JSON_THROW_ON_ERROR); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    header('Allow: POST'); SchoolSafePrintReadReply(405,['error'=>'METHOD_NOT_ALLOWED','operationPerformed'=>false]);
}
if ($_GET || array_diff(array_keys($_POST),['student_id','qr_payload','csrf'])) {
    SchoolSafePrintReadReply(400,['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
}
foreach ($_POST as $value) if (!is_string($value)) SchoolSafePrintReadReply(400,['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
$id=filter_var($_POST['student_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$qr=$_POST['qr_payload'] ?? '';
if ($id===false || strlen($qr)>2048 || !preg_match('~^schoolsafe://card/[^/\s]+/[^/\s]+$~D',$qr)) {
    SchoolSafePrintReadReply(400,['error'=>'INVALID_REQUEST','operationPerformed'=>false]);
}
chdir(__DIR__); $_REQUEST=['modname'=>'Student_ID_Card/StudentIDCard.php','modfunc'=>''];
$level=ob_get_level(); ob_start();
try {
    require_once __DIR__.'/Warehouse.php';
    if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')
        || !is_string($_SESSION['schoolsafe_card_issue_csrf'] ?? null)
        || !hash_equals($_SESSION['schoolsafe_card_issue_csrf'],$_POST['csrf'] ?? '')) throw new RuntimeException('ACCESS_DENIED');
    require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
    if (SchoolSafeEndpointGateway('scanner-relay.php','POST','management',['action'=>'print-request'])==='deny'
        || SchoolSafeEndpointGateway('studio-native.php','GET')==='deny') throw new RuntimeException('ACCESS_DENIED');
    require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    require_once __DIR__.'/schoolsafe-card-bridge/NativeCardRelay.fnc.php';
    require_once __DIR__.'/schoolsafe-card-bridge/OfficialPrintRead.fnc.php';
    $result=SchoolSafePrepareNativeCard($id,'studio-input');
    $data=SchoolSafeValidateOfficialPrintRead($result,(string)$id,(string)UserSchool(),(string)UserSyear(),$qr);
    while(ob_get_level()>$level) ob_end_clean();
    SchoolSafePrintReadReply(200,['data'=>$data,'operationPerformed'=>false]);
} catch(Throwable $error) {
    while(ob_get_level()>$level) ob_end_clean();
    $code=$error->getMessage();
    $status=$code==='OFFICIAL_PRINT_NOT_READY'?409
        :(in_array($code,['ACCESS_DENIED','NATIVE_ACCESS_DENIED','NATIVE_STUDENT_NOT_ACCESSIBLE','CARD_BRIDGE_ACCESS_DENIED'],true)?403:503);
    SchoolSafePrintReadReply($status,['error'=>'OFFICIAL_PRINT_NOT_READY','operationPerformed'=>false]);
}
