<?php
// Pure policy contracts. No DB, identity change, camera, send or live endpoint.
if(PHP_SAPI!=='cli')exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/ProgramFunctions/SchoolSafeFeePolicy.fnc.php';
$pass=0;$fail=0;
function feePolicyCheck($name,$ok){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function feePolicyFailure(callable $call){try{$call();return 'NO_REFUSAL';}catch(RuntimeException $error){return $error->getMessage();}}
$model=['id'=>'11111111-1111-4111-8111-111111111111','label'=>'Synthetic annual control','threshold'=>'80.00','tolerance'=>10,'active'=>true];
foreach([[7500,7000,'yellow'],[7500,6749,'red'],[7500,6750,'yellow'],[7500,7500,'blue'],[10000,8000,'blue'],[10000,7200,'yellow'],[10000,7199,'red'],[7500,0,'red'],[7500,-100,'red'],[0,0,'blue'],[7500,8000,'blue'],[-2500,-1000,'blue']] as [$net,$paid,$expected]){
    $decision=SchoolSafeFeeClassify($model,['has_data'=>true,'fee_cents'=>$net,'paid_cents'=>$paid,'balance_cents'=>$net-$paid]);
    feePolicyCheck('net '.$net.' paid '.$paid.' yields '.$expected,$decision['color']===$expected&&$decision['entry_allowed']===($expected!=='red'));
    feePolicyCheck('operator result contains no financial amounts',!array_intersect(['fee_total','paid_total','balance','threshold','tolerance'],array_keys($decision)));
}
$missing=SchoolSafeFeeClassify($model,['has_data'=>false]);
feePolicyCheck('missing data is neither tolerated nor blue nor a claimed debt',$missing['entry_allowed']===false&&$missing['color']===null&&$missing['state']==='unavailable');
feePolicyCheck('inconsistent ledger is refused',feePolicyFailure(function()use($model){SchoolSafeFeeClassify($model,['has_data'=>true,'fee_cents'=>100,'paid_cents'=>0,'balance_cents'=>0]);})==='FEE_READ_UNAVAILABLE');
$ctx=['school'=>1,'year'=>2026];$state=['version'=>1,'school_id'=>1,'syear'=>2026,'models'=>[$model]];$rows=[];
$db=['get'=>function($sql)use(&$rows){return $rows;}];
feePolicyCheck('known absent configuration is inactive, not blue',SchoolSafeFeePolicyActive($ctx,$db)===null);
$rows=false;feePolicyCheck('failed configuration read is unavailable',feePolicyFailure(function()use($ctx,$db){SchoolSafeFeePolicyActive($ctx,$db);})==='FEE_POLICY_UNAVAILABLE');
foreach(['foreign_school','old_year','duplicate_active','false_string','invalid_json'] as $case){
    $bad=$state;
    if($case==='foreign_school')$bad['school_id']=2;
    if($case==='old_year')$bad['syear']=2025;
    if($case==='duplicate_active')$bad['models'][]=array_replace($model,['id'=>'22222222-2222-4222-8222-222222222222']);
    if($case==='false_string')$bad['models'][0]['active']='f';
    $rows=[1=>['VALUE'=>$case==='invalid_json'?'not-json':json_encode($bad)]];
    feePolicyCheck('configuration rejects '.$case,feePolicyFailure(function()use($ctx,$db){SchoolSafeFeePolicyActive($ctx,$db);})==='FEE_POLICY_UNAVAILABLE');
}
$rows=[1=>['VALUE'=>json_encode($state)]];
feePolicyCheck('valid current model selected',SchoolSafeFeePolicyActive($ctx,$db)['id']===$model['id']);
$rows[2]=$rows[1];feePolicyCheck('ambiguous native config rows refused',feePolicyFailure(function()use($ctx,$db){SchoolSafeFeePolicyActive($ctx,$db);})==='FEE_POLICY_UNAVAILABLE');
$entry=file_get_contents($root.'/ProgramFunctions/SchoolSafeEntryScan.fnc.php');$relay=file_get_contents($root.'/scanner-relay.php');
feePolicyCheck('entry gate precedes official arrival insert',strpos($entry,'SchoolSafeFeeClassify(')<strpos($entry,'$inserted = $get('));
feePolicyCheck('native model management wired to existing relay',strpos($relay,'SchoolSafeFeePolicyManage(')!==false);
feePolicyCheck('capabilities report active policy separately from consultation right',strpos($relay,"'fee_policy_active'=>\$activePolicy!==null")!==false&&strpos($relay,"'fee_consult_available'")!==false);
echo json_encode(['pass'=>$pass,'fail'=>$fail])."\n";exit($fail?1:0);
