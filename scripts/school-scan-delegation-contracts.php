<?php
/** Pure contracts: native owner handoff, never a grant writer. */
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:__DIR__.'/../app/modules/codex-ui-v1/extensions-test-runtime';
$file=$root.'/ProgramFunctions/SchoolSafeScanDelegation.fnc.php';
if(!is_file($file)){fwrite(STDERR,"FAIL: native delegation owner handoff missing\n");exit(1);}
require_once $file;
$n=0;
function gate($name,$ok){global $n;if(!$ok)throw new RuntimeException($name);$n++;}
$ctx=['school'=>1,'year'=>2026,'profile'=>'admin','can_read'=>true,'can_manage'=>true];
$calls=0;$db=['get'=>function($sql)use(&$calls){$calls++;gate('school-owned profiles only',str_contains($sql,'p.SCHOOL_ID=1'));gate('known native flags only',str_contains($sql,"e.CAN_USE='Y'"));return [1=>['ID'=>'8','TITLE'=>'Synthetic gate profile','PROFILE'=>'teacher','MODNAME'=>'SchoolSafe/ScanArrival.php','CAN_USE'=>'Y','CAN_EDIT'=>'']];}];
$read=SchoolSafeScanDelegation(['action'=>'grants'],$ctx,$db);
gate('reads native school profile rights',$read['profiles'][0]['profile_id']==='8'&&$read['profiles'][0]['capability']==='SCAN_ENTRY');
gate('read does not pretend one operator assignment',$read['scope']==='school_profiles');
gate('native edit flag retained',$read['profiles'][0]['can_edit']===false);
gate('owner URL uses server school',$read['editor_url']==='/ecoles/1/profils');
$prepared=SchoolSafeScanDelegation(['action'=>'delegate'],$ctx,$db);
gate('handoff is not mutation success',$prepared['status']==='native_editor_required'&&!isset($prepared['id']));
gate('handoff needs no grant query',$calls===1);
foreach([['can_read'=>false,'action'=>'grants'],['can_manage'=>false,'action'=>'delegate'],['profile'=>'teacher','action'=>'grants'],['school'=>0,'action'=>'delegate']]as$case){$action=$case['action'];unset($case['action']);try{SchoolSafeScanDelegation(['action'=>$action],array_replace($ctx,$case),$db);throw new LogicException('accepted forbidden');}catch(RuntimeException $e){gate('authority fails closed',$e->getMessage()==='FORBIDDEN');}}
foreach([['action'=>'delegate','profileId'=>'8'],['action'=>'delegate','decision'=>'assign'],['action'=>'grants','school'=>2],['action'=>'unknown']]as$input){try{SchoolSafeScanDelegation($input,$ctx,$db);throw new LogicException('accepted invalid');}catch(RuntimeException $e){gate('old bridge payload never mutates native profiles',$e->getMessage()==='INVALID_INPUT');}}
$empty=SchoolSafeScanDelegation(['action'=>'grants'],$ctx,['get'=>fn($sql)=>[]]);
gate('empty remains truthful',$empty['profiles']===[]);
try{SchoolSafeScanDelegation(['action'=>'grants'],$ctx,['get'=>fn($sql)=>false]);throw new LogicException('false read accepted');}catch(RuntimeException $e){gate('schema or read error never means no assignments',$e->getMessage()==='DELEGATION_UNAVAILABLE');}
$relay=file_get_contents($root.'/scanner-relay.php');
gate('real relay routes native handoff',str_contains($relay,'SchoolSafeScanDelegation('));
echo json_encode(['status'=>'PASS','checks'=>$n,'passed'=>$n,'failed'=>0,'scope'=>'Pure contracts; no database or real attribution'])."\n";
