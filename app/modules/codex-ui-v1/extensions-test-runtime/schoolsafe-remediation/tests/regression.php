<?php
require dirname(__DIR__) . '/Detection.fnc.php';
require dirname(__DIR__) . '/PaymentScope.fnc.php';
require dirname(__DIR__) . '/ParentInbox.fnc.php';
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function grade(int $id, $points, string $type = 'homework', $maximum = 100): array {
    return ['assignment_id'=>$id,'points'=>$points,'maximum'=>$maximum,'type'=>$type];
}
$cases = [
    ['two failures', [grade(1,20),grade(2,30,'quiz')],false],
    ['three mixed failures',[grade(1,35),grade(2,45,'quiz'),grade(3,49)],true],
    ['one type only',[grade(1,20),grade(2,30),grade(3,40)],false],
    ['exam mixed',[grade(1,20,'exam'),grade(2,30),grade(3,40,'exam')],true],
    ['exactly fifty',[grade(1,20),grade(2,30,'quiz'),grade(3,50)],false],
    ['duplicate assignment',[grade(1,20),grade(1,20,'quiz'),grade(2,30)],false],
    ['missing grade',[grade(1,20),grade(2,30,'quiz'),grade(3,null)],false],
    ['exemption',[grade(1,20),grade(2,30,'quiz'),grade(3,-1)],false],
    ['zero maximum',[grade(1,20),grade(2,30,'quiz'),grade(3,0,'exam',0)],false],
    ['native score normalization',[grade(1,4,'homework',10),grade(2,8,'quiz',20),grade(3,9,'exam',20)],true],
    ['success does not reset failures',[grade(1,20),grade(2,90),grade(3,30,'quiz'),grade(4,40)],true],
    ['unmapped category',[grade(1,20),grade(2,30),grade(3,40,'unmapped')],false],
    ['second type arrives fourth',[grade(1,20),grade(2,30),grade(3,40),grade(4,20,'quiz')],true],
    ['excluded grade',[grade(1,20),grade(2,30,'quiz'),grade(3,40)+['excluded'=>true]],false],
    ['nonfinite grade',[grade(1,20),grade(2,30,'quiz'),grade(3,INF)],false]
];
foreach ($cases as [$label,$grades,$eligible]) {
    $result = SchoolSafeRemediationDetection($grades);
    check($result['eligible'] === $eligible, $label);
    if ($eligible) {
        $types=[];
        foreach ($grades as $g) if (in_array($g['assignment_id'],$result['evidence'],true)) $types[]=$g['type'];
        check(count(array_unique($result['evidence']))===3 && count(array_unique($types))>=2, $label . ': distinct mixed evidence');
    }
}
function request(array $payload): array {
    $proc = proc_open([PHP_BINARY,__DIR__.'/endpoint-worker.php',base64_encode(json_encode($payload,JSON_THROW_ON_ERROR))],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);
    $html=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code=proc_close($proc);
    if ($code!==0 || !preg_match('/RESULT:(\{.*\})$/s',$error,$match)) throw new RuntimeException('Worker failed: '.$error);
    return json_decode($match[1],true,32,JSON_THROW_ON_ERROR)+['html'=>$html];
}
function writes(array $result): array {
    return array_values(array_filter($result['queries'],static fn($sql)=>preg_match('/^(INSERT|UPDATE|DELETE|BEGIN|COMMIT)/',$sql)));
}
foreach (['rattrapage.php','remediation-payments.php'] as $endpoint) {
    foreach (['PUT','DELETE','PATCH'] as $method) {
        $r=request(compact('endpoint','method'));
        check($r['status']===405 && !$r['queries'], $endpoint.': '.$method.' refused before bootstrap');
    }
    foreach (['nativeDenied','gatewayDenied'] as $denial) {
        $r=request(['endpoint'=>$endpoint,$denial=>true]);
        check($r['status']===403 && !writes($r),$endpoint.': '.$denial.' refused without writes');
    }
    foreach ([['csrf'=>['test-token'],'action'=>'record'],['csrf'=>'test-token','action'=>['record']],['csrf'=>'wrong','action'=>'record']] as $post) {
        $r=request(['endpoint'=>$endpoint,'method'=>'POST','post'=>$post]);
        check(in_array($r['status'],[400,403],true) && !writes($r),$endpoint.': malformed/expired form refused');
    }
}
$r=request(['endpoint'=>'rattrapage.php','method'=>'POST','post'=>['csrf'=>'test-token','action'=>'create','case_id'=>'7']]);
check($r['status']===403 && !writes($r),'manual creation command refused');
$r=request(['method'=>'POST','post'=>['csrf'=>'test-token','action'=>'unknown']]);
check($r['status']===400 && !writes($r),'unknown finance action refused');
foreach (['0','-1','1e3','0.001','1;SELECT 1'] as $amount) {
    $r=request(['method'=>'POST','post'=>['csrf'=>'test-token','action'=>'record','case_id'=>'7','amount'=>$amount,'receipt_reference'=>'SYNTHETIC','payment_date'=>'2026-10-12']]);
    check($r['status']===400 && !writes($r),'invalid monetary input refused: '.$amount);
}
$r=request(['method'=>'POST','post'=>['csrf'=>'test-token','action'=>'record','case_id'=>'7','amount'=>'100.01','receipt_reference'=>'SYNTHETIC','payment_date'=>'2026-02-30']]);
check($r['status']===400 && !writes($r),'invalid calendar date refused');
$r=request(['method'=>'POST','post'=>['csrf'=>'test-token','action'=>'record','case_id'=>'7','amount'=>'100.01','receipt_reference'=>'SYNTHETIC','payment_date'=>'2026-10-12']]);
$sql=implode("\n",$r['queries']);
check($r['status']===200 && str_contains($sql,'INSERT INTO billing_payments') && str_contains($sql,'INSERT INTO schoolsafe_remediation_payment_purpose') && !str_contains($sql,'INSERT INTO schoolsafe_remediation_payments('),'record uses native payment and receipt, no premature split');
check(str_contains($sql,SchoolSafeRemediationFeeScopeSql()),'record checks native fee student, school, year and amount');
$r=request(['method'=>'POST','post'=>['csrf'=>'test-token','action'=>'confirm','case_id'=>'7','payment_id'=>'10']]);
$sql=implode("\n",$r['queries']);
check(str_contains($sql,'round(amount*0.4,2),amount-round(amount*0.4,2)') && str_contains($sql,"'payment_confirmed'"),'confirmation calls 40/60 native SQL and journals event');
check(str_contains($sql,SchoolSafeRemediationFeeScopeSql()) && str_contains($sql,'FOR UPDATE'),'confirmation locks dossier and checks native fee');
check(strpos($sql,'SELECT fee.id')<strpos($sql,'SELECT r.id FROM schoolsafe_remediation_cases'),'confirmation locks fee before dossier');
check(str_contains($sql,'used.invalidated_at IS NULL OR used.case_id<>r.id'),'reconfirmation excludes active or foreign-case allocations');
$r=request(['method'=>'POST','reconfirm'=>true,'post'=>['csrf'=>'test-token','action'=>'confirm','case_id'=>'7','payment_id'=>'10']]);
check(str_contains(implode("\n",$r['queries']),"'payment_reconfirmed'") && !str_contains(implode("\n",$r['queries']),'UPDATE schoolsafe_remediation_payments SET'),'reconfirmation appends allocation and journals separate event');
$r=request(['method'=>'POST','legacyVersions'=>true,'post'=>['csrf'=>'test-token','action'=>'confirm','case_id'=>'7','payment_id'=>'10']]);
check(!str_contains(implode("\n",$r['queries']),'used.invalidated_at IS NULL OR used.case_id<>r.id'),'pre-migration schema keeps reconfirmation disabled');
$r=request(['method'=>'POST','empty'=>true,'post'=>['csrf'=>'test-token','action'=>'confirm','case_id'=>'999','payment_id'=>'10']]);
check(!str_contains(implode("\n",$r['queries']),'INSERT INTO schoolsafe_remediation_payments('),'unavailable dossier creates no allocation');
$r=request([]);
check(str_contains($r['html'],'name="action" value="confirm"'),'confirmation form sends explicit action');
check(str_contains($r['html'],'rattrapage.php#remediation-case-7'),'finance links to pedagogical dossier');
$r=request(['endpoint'=>'rattrapage.php']);
check(str_contains($r['html'],'Synthetic &lt;quiz&gt;') && str_contains($r['html'],'49 / 100'),'dossier renders escaped individual native evidence');
check(str_contains($r['html'],'remediation-payments.php#remediation-case-7'),'pedagogical dossier links to finance');
$r=request(['endpoint'=>'rattrapage.php','profile'=>'teacher']);
check(str_contains(implode("\n",$r['queries']),'cp.teacher_id=2') && !str_contains($r['html'],'Paiements de ce rattrapage'),'teacher query restricted to own courses without finance link');
$queries=[];
function DBGet($sql) { global $queries; $queries[]=$sql; return []; }
function ParseMLField($value) { return $value; }
SchoolSafeRemediationParentInbox(['1'],['1'],3,1,2026);
check(count($queries)===1 && str_contains($queries[0],"n.audience='principal_parent' AND n.recipient_id=3"),'parent inbox requires explicit historical recipient');
SchoolSafeRemediationParentInbox([],['1'],3,1,2026);
check(count($queries)===1,'empty principal scope performs no query');
try {
    SchoolSafeRemediationParentInbox(['2'],['1'],3,1,2026);
    throw new RuntimeException('Foreign principal scope accepted');
} catch (RuntimeException $e) {
    check($e->getMessage()==='INVALID_PRINCIPAL_SCOPE','foreign principal scope refused');
}
echo "PHP assertions passed: $checks\n";
