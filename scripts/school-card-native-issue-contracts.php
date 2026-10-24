<?php
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime';
$file=$root.'/ProgramFunctions/SchoolSafeCardIssue.fnc.php';
if(!is_file($file)){fwrite(STDERR,"FAIL Native card issuance still has no registry writer\n");exit(1);}
require_once $file;
$n=0;
function check($value,$label){global $n;if(!$value)throw new RuntimeException('FAIL '.$label);++$n;echo 'PASS '.$label."\n";}
$ctx=['school'=>1,'year'=>2026,'user'=>9];$secret=str_repeat('0',64);
$cards=[];$commands=[];$enrolled=true;$snapshot=[];
$db=['get'=>function($sql)use(&$cards,&$enrolled){
 if(strpos($sql,'to_regclass')!==false)return [1=>['T'=>'schoolsafe_student_cards']];
 if(strpos($sql,'FROM students s')!==false)return $enrolled?[1=>['STUDENT_ID'=>5]]:[];
 if(strpos($sql,'FROM schoolsafe_student_cards')!==false)return $cards;
 if(preg_match("/VALUES\(1,5,'([a-f0-9]+)','([A-Za-z0-9_-]+)'\)/",$sql,$m)){$cards[1]=['CARD_NUMBER'=>$m[1],'SIGNATURE'=>$m[2],'STATUS'=>'active'];return [1=>$cards[1]];}
 throw new RuntimeException('Unexpected SQL');
},'exec'=>function($sql)use(&$commands,&$cards,&$snapshot){$commands[]=$sql;if($sql==='BEGIN')$snapshot=$cards;if($sql==='ROLLBACK')$cards=$snapshot;}];
$yes=function(){return true;};
$r=SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,$yes);
check($r['operationPerformed']===true&&count($cards)===1,'Actual issuance writes the native registry');
check(preg_match('~^schoolsafe://card/[a-f0-9]{32}/[A-Za-z0-9_-]{32}$~D',$r['data']['credential']['qr_payload'])===1,'QR follows the existing verifier format');
$qr=$r['data']['credential']['qr_payload'];
$r=SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,$yes);
check($r['operationPerformed']===false&&$r['result']==='ALREADY_ISSUED'&&$r['data']['credential']['qr_payload']===$qr,'Replay preserves the existing card without a new write');
require_once $root.'/ProgramFunctions/SchoolSafeCardVerify.fnc.php';
$v=SchoolSafeCardVerifyQr($qr,$secret,1,2026,function($number,$school)use(&$cards){return ['school_id'=>$school,'student_id'=>5,'card_number'=>$number,'signature'=>$cards[1]['SIGNATURE'],'status'=>'active','enrolled'=>true,'first_name'=>'TEST','last_name'=>'SYNTHETIC','class_name'=>''];});
check($v['result']==='VALID','Native-issued QR is accepted by the actual scanner verifier');
foreach(['lost','revoked','replaced']as$status){$cards[1]['STATUS']=$status;try{SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,$yes);check(false,'inactive');}catch(RuntimeException$e){check($e->getMessage()==='CARD_NOT_ACTIVE'&&$cards[1]['STATUS']===$status,'Never reactivate '.$status);}}
$cards[1]['STATUS']='active';$cards[2]=$cards[1];
try{SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,$yes);check(false,'ambiguous');}catch(RuntimeException$e){check($e->getMessage()==='CARD_REGISTRY_AMBIGUOUS','Ambiguous existing cards fail closed');}unset($cards[2]);
try{SchoolSafeNativeCardRegister(5,$ctx,str_repeat('1',64),$db,$yes);check(false,'key');}catch(RuntimeException$e){check($e->getMessage()==='CARD_KEY_MISMATCH','Do not overwrite an existing signature after a key mismatch');}
$cards=[];$enrolled=false;
try{SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,$yes);check(false,'scope');}catch(RuntimeException$e){check($e->getMessage()==='NATIVE_STUDENT_NOT_ACCESSIBLE'&&!$cards,'Wrong school or year cannot issue a card');}$enrolled=true;
$calls=0;
try{SchoolSafeNativeCardRegister(5,$ctx,$secret,$db,function()use(&$calls){return ++$calls===1;});check(false,'authority');}catch(RuntimeException$e){check($e->getMessage()==='NATIVE_ACCESS_DENIED'&&!$cards,'Authority rechecked immediately before insert');}
try{SchoolSafeNativeCardRegister(5,$ctx,'',$db,$yes);check(false,'missing key');}catch(RuntimeException$e){check($e->getMessage()==='CARD_KEY_UNAVAILABLE'&&!$cards,'Missing signing key does not issue anything');}
$bad=$db;$bad['get']=function($sql)use($db){if(strpos($sql,'INSERT INTO')===0)throw new RuntimeException('Injected failure');return $db['get']($sql);};
try{SchoolSafeNativeCardRegister(5,$ctx,$secret,$bad,$yes);check(false,'failure');}catch(RuntimeException$e){check(end($commands)==='ROLLBACK'&&!$cards,'Failed persistence rolls back without an issuance acknowledgment');}
check(strpos(json_encode($r),$secret)===false,'Signing secret never enters the response');
function SchoolSafeReadNativeCardInput($id){return ['authorized'=>true,'student'=>['STUDENT_ID'=>$id,'LAST_NAME'=>'SYNTHETIC','FIRST_NAME'=>'TEST'],
 'school'=>['ID'=>1,'SYEAR'=>2026,'TITLE'=>'SYNTHETIC'],'enrollment'=>['STUDENT_ID'=>$id,'SCHOOL_ID'=>1,'SYEAR'=>2026],
 'verified'=>['photoUrl'=>'/assets/synthetic-avatar.svg','academicYearLabel'=>'2026'],'schoolingSource'=>null];}
require_once $root.'/schoolsafe-card-bridge/NativeCardRegistryRead.fnc.php';
putenv('SCHOOLSAFE_CARD_HMAC_SECRET='.$secret);
$read=SchoolSafePrepareRegistryCard(5,'studio-input',$db)['data'];
check($read['backend']==='native-registry'&&$read['nativeReference']===$read['targetReference'],'Preparation keeps native identities, never invents UUID identities');
check($read['input']['record']['photo_path']==='/assets/synthetic-avatar.svg','Preparation uses the verified native photo');
check($read['operationPerformed']===false&&$read['issuanceAllowed']===false&&!$cards,'Preparation never issues a card');
check(in_array('class.tutor',$read['missing'],true)&&!$read['input']['assignments'],'No invented tutor or printing readiness');
check($read['nativeCredentialIssuanceAllowed']===false,'Read-only preparation does not imply issuance authority');
$bad=$db;$bad['get']=function($sql){return [];};
try{SchoolSafeNativeCardRegistryPresent($bad);check(false,'metadata');}catch(RuntimeException$e){check($e->getMessage()==='CARD_REGISTRY_UNAVAILABLE','Failed schema probe is not a legacy-bridge fallback');}
$relay=file_get_contents($root.'/scanner-relay.php');
check(strpos($relay,"\$portrait=SchoolSafeReadNativeCardInput(\$data['nativeStudentId'])")!==false,'Scanner portrait uses the provider that actually returns verified photoUrl');
putenv('SCHOOLSAFE_CARD_HMAC_SECRET');
echo "$n native issuance checks, 0 failures\n";
