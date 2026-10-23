<?php
// Synthetic protocol callbacks only. Native physical outcomes/concurrency tested separately in PostgreSQL.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT');if(!$root)exit(2);
require_once $root.'/ProgramFunctions/SchoolSafeEntryRequest.fnc.php';
$pass=0;$fail=0;
function entryRequestCheck($name,$ok){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name\n";}}
function entryRequestError(callable $call){try{$call();return 'NO_REFUSAL';}catch(RuntimeException $error){return $error->getMessage();}}
$ctx=['school'=>3,'year'=>2026,'user'=>9,'request_key'=>'11111111-1111-4111-8111-111111111111'];
$calls=0;$queries=[];$rows=[];$writeOk=true;
$db=['quote'=>fn($value)=>"'".str_replace("'","''",$value)."'",'get'=>function($sql)use(&$queries,&$rows,&$writeOk){$queries[]=$sql;return strpos($sql,'INSERT INTO')===0?($writeOk?[1=>['REQUEST_KEY'=>'11111111-1111-4111-8111-111111111111']]:false):$rows;}];
$operation=function()use(&$calls){$calls++;return ['result'=>'PRESENT','decision'=>'allowed','record_id'=>20,'financial'=>['state'=>'inactive','color'=>null,'reason'=>'no_active_policy']];};
$legacy=$ctx;unset($legacy['request_key']);
entryRequestCheck('legacy internal operation needs no new protocol query',SchoolSafeEntryRequest(42,7,$legacy,$db,$operation)['result']==='PRESENT'&&$queries===[]&&$calls===1);
$calls=0;$result=SchoolSafeEntryRequest(42,7,$ctx,$db,$operation);
entryRequestCheck('fresh operation invokes native callback exactly once',$result['record_id']===20&&$calls===1);
entryRequestCheck('protocol lookup binds school year operator and request',strpos($queries[0],'SCHOOL_ID=3 AND SYEAR=2026 AND ACTOR_STAFF_ID=9')!==false&&strpos($queries[0],'FOR SHARE')!==false);
entryRequestCheck('receipt stores native result reference not child portrait or money',strpos($queries[1],'entry_record_id')!==false&&strpos($queries[1],'fee_total')===false&&strpos($queries[1],'photo')===false);
$saved=['STUDENT_ID'=>42,'CHECKPOINT_ID'=>7,'RESULT'=>'PRESENT','ENTRY_RECORD_ID'=>20,'REASON'=>null,'FINANCIAL_CONSULT_ID'=>91,'FINANCIAL_STATE'=>'active'];
$rows=[1=>$saved];$calls=0;$replay=SchoolSafeEntryRequest(42,7,$ctx,$db,$operation);
entryRequestCheck('historical success replay never executes another native callback',$calls===0&&$replay['result']==='ALREADY_RECORDED'&&$replay['idempotent']===true);
entryRequestCheck('historical operation is not a claim of current presence or authorization',$replay['decision']==='recorded'&&$replay['entry_allowed']===false&&!array_intersect(['financial','late','balance','fee_total','photo_url'],array_keys($replay)));
$rows[1]['RESULT']='ALREADY_PRESENT';$replay=SchoolSafeEntryRequest(42,7,$ctx,$db,$operation);
entryRequestCheck('original no-op remains a no-op on later replay',$replay['original_result']==='ALREADY_PRESENT'&&$calls===0);
$rows[1]['STUDENT_ID']=43;
entryRequestCheck('another child cannot reuse protocol receipt',entryRequestError(fn()=>SchoolSafeEntryRequest(42,7,$ctx,$db,$operation))==='OPERATION_KEY_CONFLICT'&&$calls===0);
$rows[1]=$saved;$rows[1]['CHECKPOINT_ID']=8;
entryRequestCheck('another checkpoint cannot reuse protocol receipt',entryRequestError(fn()=>SchoolSafeEntryRequest(42,7,$ctx,$db,$operation))==='OPERATION_KEY_CONFLICT');
$rows=false;
entryRequestCheck('unavailable protocol read is not a new physical operation',entryRequestError(fn()=>SchoolSafeEntryRequest(42,7,$ctx,$db,$operation))==='SCAN_OPERATION_UNAVAILABLE'&&$calls===0);
$rows=[];$writeOk=false;
entryRequestCheck('unconfirmed protocol insert fails enclosing business transaction',entryRequestError(fn()=>SchoolSafeEntryRequest(42,7,$ctx,$db,$operation))==='SCAN_OPERATION_WRITE_FAILED');
entryRequestCheck('malformed request UUID rejected before business callback',entryRequestError(fn()=>SchoolSafeEntryRequest(42,7,array_replace($ctx,['request_key'=>true]),$db,$operation))==='INVALID_INPUT');
$entry=file_get_contents($root.'/ProgramFunctions/SchoolSafeEntryScan.fnc.php');
entryRequestCheck('native child transaction remains owner of receipt and business writes',strpos($entry,'return SchoolSafeEntryRequest(')>strpos($entry,'return SchoolSafePickupStudentTransaction('));
entryRequestCheck('native physical reader never counts HTTP receipts',strpos(file_get_contents($root.'/schoolsafe-attendance/ArrivalRead.php'),'schoolsafe_entry_requests')===false);
echo json_encode(['pass'=>$pass,'fail'=>$fail,'scope'=>'Pure synthetic protocol callbacks, not HTTP/camera/production'])."\n";exit($fail?1:0);
