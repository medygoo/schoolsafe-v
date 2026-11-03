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
if (!empty($_GET) || array_diff(array_keys($_POST), ['student_id','csrf','expected_school','expected_year','scope'])
    || (isset($_POST['scope']) && ($_POST['scope'] !== 'school' || isset($_POST['student_id'])))) {
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
    $school_scope = ($_POST['scope'] ?? '') === 'school';
    $id = $school_scope ? 1 : filter_var($_POST['student_id']??null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id===false) SchoolSafeIssueReply(400, ['error'=>'INVALID_STUDENT_ID']);
    if (!is_string($_POST['expected_school']??null)||!is_string($_POST['expected_year']??null)
        ||$_POST['expected_school']!==(string)UserSchool()||$_POST['expected_year']!==(string)UserSyear()) {
        SchoolSafeIssueReply(409,['error'=>'NATIVE_CONTEXT_CHANGED','operationPerformed'=>false]);
    }
    require_once __DIR__.'/modules/SchoolSafe/Gateway.php';
    if (SchoolSafeEndpointGateway('card-bridge-issue.php', 'POST', '', $_POST) === 'deny') {
        SchoolSafeIssueReply(403, ['error'=>'NATIVE_ACCESS_DENIED']);
    }
    require_once __DIR__.'/schoolsafe-card-bridge/RosarioCardRead.fnc.php';
    require_once __DIR__.'/ProgramFunctions/SchoolSafeCardIssue.fnc.php';
    $db=['get'=>function($sql){return DBGet(DBQuery($sql));},'exec'=>function($sql){DBQuery($sql);}];
    if ($school_scope) {
        // QR officiel relie automatiquement a chaque eleve dont la fiche est complete (premiere carte seulement).
        require_once __DIR__.'/schoolsafe-card-bridge/NativeCardRelay.fnc.php';
        $issued = 0; $pending = 0;
        foreach (SchoolSafeCardRosterIds() as $student) {
            try {
                $check = SchoolSafePrepareNativeCard($student, 'studio-input')['data'];
                if (($check['missing'] ?? null) !== ['card.official_qr'] || ($check['nativeCredentialIssuanceAllowed'] ?? false) !== true) { $pending++; continue; }
                $done = SchoolSafeNativeCardRegister($student,
                    ['user'=>(int)User('STAFF_ID'),'school'=>(int)UserSchool(),'year'=>(int)UserSyear()],
                    getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'',$db,
                    function()use($db){return SchoolSafeNativeCardMayIssue($db);});
                if (($done['operationPerformed'] ?? false) === true) $issued++;
            } catch (Throwable $one) { $pending++; }
        }
        SchoolSafeIssueReply(200, ['operationPerformed'=>$issued > 0, 'issued'=>$issued, 'notReady'=>$pending]);
    }
    // Existing native visibility and command authority, not a laboratory relay.
    SchoolSafeReadNativeCardStudent($id);
    $result=SchoolSafeNativeCardRegister($id,
        ['user'=>(int)User('STAFF_ID'),'school'=>(int)UserSchool(),'year'=>(int)UserSyear()],
        getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'',$db,
        function()use($db){return SchoolSafeNativeCardMayIssue($db);});
    SchoolSafeIssueReply(200,$result);
} catch (Throwable $error) {
    while (ob_get_level()>$level) ob_end_clean();
    $code=$error->getMessage();
    $known=['NATIVE_ACCESS_DENIED'=>403,'NATIVE_STUDENT_NOT_ACCESSIBLE'=>403,
        'CARD_NOT_ACTIVE'=>409,'CARD_REGISTRY_AMBIGUOUS'=>409,'CARD_KEY_MISMATCH'=>409];
    SchoolSafeIssueReply($known[$code]??503, ['error'=>isset($known[$code])?$code:'CARD_ISSUER_UNAVAILABLE','operationPerformed'=>isset($known[$code])?false:null]);
}
