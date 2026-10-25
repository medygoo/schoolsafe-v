<?php
// Run only as a child of regression.php. Native bootstrap/SQL are substituted, no connection.
$payload = json_decode(base64_decode($argv[1]), true, 32, JSON_THROW_ON_ERROR);
$GLOBALS['fixture'] = $payload;
$GLOBALS['queries'] = [];
function UserSchool() { return 1; }
function UserSyear() { return 2026; }
function User($key) {
    return ['STAFF_ID'=>2,'PROFILE'=>$GLOBALS['fixture']['profile'] ?? 'admin','PROFILE_ID'=>1,'NAME'=>'Synthetic Director'][$key] ?? '';
}
function AllowUse($path) { return empty($GLOBALS['fixture']['nativeDenied']); }
function AllowEdit($path) { return empty($GLOBALS['fixture']['nativeDenied']); }
function SchoolSafeEndpointGateway($endpoint, $method, $operation = '', $input = []) {
    return empty($GLOBALS['fixture']['gatewayDenied']) ? 'legacy' : 'deny';
}
function SchoolSafeNativeCommandPermission($path) { return true; }
function DBEscapeString($value) { return str_replace("'", "''", (string) $value); }
function ParseMLField($value) { return $value; }
function DBQuery($sql) { $GLOBALS['queries'][] = $sql; return $sql; }
function DBGet($sql) {
    $GLOBALS['queries'][] = $sql;
    if (!empty($GLOBALS['fixture']['empty'])) return [];
    if (str_contains($sql, 'FROM user_profiles')) return [1=>['ID'=>1]];
    if (str_contains($sql, 'FROM information_schema.columns')) return [1=>['READY'=>empty($GLOBALS['fixture']['legacyVersions'])?'t':'f']];
    if (str_contains($sql, 'SELECT id FROM schoolsafe_remediation_payments') && !empty($GLOBALS['fixture']['reconfirm'])) return [1=>['ID'=>5]];
    if (str_contains($sql, 'SELECT fee.id FROM billing_fees')) return [1=>['ID'=>9]];
    if (str_contains($sql, 'SELECT p.id FROM billing_payments') || str_contains($sql, 'SELECT id FROM schoolsafe_remediation_payment_purpose')) return [1=>['ID'=>10]];
    $case = ['ID'=>7,'STUDENT_ID'=>1,'COURSE_ID'=>1,'FEE_ID'=>9,'TARIFF'=>'200.02',
        'FIRST_NAME'=>'Synthetic','LAST_NAME'=>'Student','COURSE_TITLE'=>'Mathematics',
        'BILLING_MODE'=>'paid','STATUS'=>'pending','EVIDENCE_VALID'=>'t','CREATED_AT'=>'2026-01-01'];
    if (str_contains($sql, 'SELECT r.* FROM schoolsafe_remediation_cases')
        || str_contains($sql, 'SELECT r.id FROM schoolsafe_remediation_cases')
        || str_contains($sql, 'SELECT r.id,r.student_id')
        || str_contains($sql, 'SELECT r.*,s.first_name')) return [1=>$case];
    if (str_contains($sql, 'INSERT INTO billing_payments')) return [1=>['ID'=>10]];
    if (str_contains($sql, 'SELECT p.id,p.amount')) return [1=>['ID'=>10,'AMOUNT'=>'100.01','PAYMENT_DATE'=>'2026-10-12']];
    if (str_contains($sql, 'Notes justificatives')) throw new RuntimeException('Unexpected UI text in SQL');
    if (str_contains($sql, 'gg.assignment_id=ANY(r.evidence)')) return [1=>['TITLE'=>'Synthetic <quiz>', 'KIND'=>'quiz','POINTS'=>'49','MAXIMUM'=>'100','MARKING_PERIOD_ID'=>12]];
    return [];
}
$_SESSION = ['ss_remediation_finance_csrf'=>'test-token','ss_remediation_csrf'=>'test-token'];
$_SERVER['REQUEST_METHOD'] = $payload['method'] ?? 'GET';
$_POST = $payload['post'] ?? [];
$_GET = [];
register_shutdown_function(static function () {
    fwrite(STDERR, "\nRESULT:" . json_encode(['status'=>http_response_code() ?: 200, 'queries'=>$GLOBALS['queries']], JSON_THROW_ON_ERROR));
});
$fixturePath = sys_get_temp_dir() . '/ss-remediation-' . bin2hex(random_bytes(8));
mkdir($fixturePath . '/schoolsafe-remediation', 0700, true);
mkdir($fixturePath . '/modules/SchoolSafe', 0700, true);
file_put_contents($fixturePath . '/Warehouse.php', '<?php /* bootstrap supplied by test */');
file_put_contents($fixturePath . '/modules/SchoolSafe/Gateway.php', '<?php /* gateway supplied by test */');
$runtime = dirname(__DIR__, 2);
foreach (['rattrapage.php','remediation-payments.php'] as $file) copy($runtime . '/' . $file, $fixturePath . '/' . $file);
foreach (['Workspace.inc.php','PaymentScope.fnc.php'] as $file) copy(dirname(__DIR__) . '/' . $file, $fixturePath . '/schoolsafe-remediation/' . $file);
register_shutdown_function(static function () use ($fixturePath) {
    foreach (glob($fixturePath . '/schoolsafe-remediation/*') as $file) unlink($file);
    unlink($fixturePath . '/modules/SchoolSafe/Gateway.php');
    unlink($fixturePath . '/Warehouse.php');
    unlink($fixturePath . '/rattrapage.php');
    unlink($fixturePath . '/remediation-payments.php');
    rmdir($fixturePath . '/schoolsafe-remediation');
    rmdir($fixturePath . '/modules/SchoolSafe');
    rmdir($fixturePath . '/modules');
    chdir(sys_get_temp_dir());
    rmdir($fixturePath);
});
require $fixturePath . '/' . ($payload['endpoint'] ?? 'remediation-payments.php');
