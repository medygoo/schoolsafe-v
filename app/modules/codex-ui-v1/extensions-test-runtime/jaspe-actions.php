<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET || $_POST) {
    http_response_code(400); exit('{"error":"INVALID_REQUEST"}');
}
chdir(__DIR__);
$_REQUEST = ['modname'=>'misc/Portal.php', 'modfunc'=>''];
$level = ob_get_level(); ob_start(); require_once __DIR__ . '/Warehouse.php';
while (ob_get_level() > $level) ob_end_clean();
if (!User('STAFF_ID') && !UserStudentID()) {
    http_response_code(401); exit('{"error":"AUTH_REQUIRED"}');
}
require_once __DIR__ . '/ProgramFunctions/SchoolSafeJaspeActions.fnc.php';
if (empty($RosarioModules['SchoolSafe_Jaspe']) || !AllowUse('SchoolSafe_Jaspe/Assistant.php')) {
    http_response_code(403); exit('{"error":"JASPE_ACCESS_DENIED"}');
}
// Preparation only: no assistant activation, business data or write operation.
echo json_encode(['actions'=>SchoolSafeJaspeActions('AllowUse', 'SchoolSafeJaspeCanEditDestination'),
    'writes_enabled'=>false], JSON_UNESCAPED_SLASHES);
