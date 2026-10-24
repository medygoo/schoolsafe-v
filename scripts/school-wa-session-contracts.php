<?php
// Provider GET responses are all injected; no provider/network/identity fixtures.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:__DIR__.'/../app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';
putenv('WAHA_BASE=https://waha.invalid');putenv('WAHA_KEY=fixture-not-a-real-key');
putenv('EVOLUTION_BASE=https://evolution.invalid');putenv('EVOLUTION_KEY=fixture-not-a-real-key');
$n=0;$failed=0;
function check($name,$ok){global $n,$failed;$n++;if(!$ok)$failed++;echo ($ok?'PASS ':'FAIL ').$name."\n";}
$waha=['ENGINE'=>'waha','SESSION_NAME'=>'fixture-waha','PHONE_E164'=>'+243000000000'];
$valid=['name'=>'fixture-waha','status'=>'WORKING','me'=>['id'=>'243000000000@c.us']];
function probe($row,$body,$code=200){return SchoolSafeWaSessionProbe($row,function($method,$url,$key,$payload)use($body,$code){
    if($method!=='GET'||$payload!==null)throw new RuntimeException('PROVIDER_MUTATION_FORBIDDEN');
    return ['http_code'=>$code,'body'=>is_string($body)?$body:json_encode($body)];
});}
check('matching actual working account verifies only minimal state',probe($waha,$valid)===['verified'=>true,'state'=>'online','reason'=>null]);
foreach(['SCAN_QR_CODE','STARTING','STOPPED','FAILED','unknown']as$state){$body=$valid;$body['status']=$state;check('nonoperational provider state '.$state.' denied',probe($waha,$body)['verified']===false);}
$body=$valid;$body['name']='another-session';check('different session cannot verify configured account',probe($waha,$body)['verified']===false);
$body=$valid;$body['me']['id']='243111111111@c.us';check('different sender phone cannot verify',probe($waha,$body)['reason']==='SESSION_ACCOUNT_MISMATCH');
$body=$valid;unset($body['me']);check('missing actual sender is not guessed from configuration',probe($waha,$body)['verified']===false);
check('malformed provider JSON is unavailable not online',probe($waha,'{')['state']==='error');
check('failed provider read is not active permission',probe($waha,$valid,500)['verified']===false);
$body=$valid;$body['me']['reachoutTimelock']=['active'=>true];check('provider outreach restriction prevents operational promise',probe($waha,$body)['reason']==='SESSION_LIMITED');
$body=$valid;$body['me']['messageCapping']=['limited'=>true];check('provider message cap prevents operational promise',probe($waha,$body)['reason']==='SESSION_LIMITED');
$body=$valid;$body['token']='DO_NOT_RETURN_FIXTURE';check('provider token and raw payload never returned',strpos(json_encode(probe($waha,$body)),'DO_NOT_RETURN_FIXTURE')===false);
$evo=['ENGINE'=>'evolution','SESSION_NAME'=>'fixture-evo','PHONE_E164'=>'+243000000000'];
function evo_probe($row,$instances){return SchoolSafeWaSessionProbe($row,function($method,$url)use($instances){
    if($method!=='GET')throw new RuntimeException('PROVIDER_MUTATION_FORBIDDEN');
    return ['http_code'=>200,'body'=>json_encode(strpos($url,'connectionState')!==false?['instance'=>['instanceName'=>'fixture-evo','state'=>'open']]:$instances)];
});}
$instances=[['name'=>'fixture-evo','connectionStatus'=>'open','ownerJid'=>'243000000000@s.whatsapp.net','token'=>'DO_NOT_RETURN_FIXTURE']];
check('Evolution requires both live state and exact owner',evo_probe($evo,$instances)['verified']===true);
$instances[0]['ownerJid']='243111111111@s.whatsapp.net';check('Evolution open alone cannot authorize wrong account',evo_probe($evo,$instances)['verified']===false);
check('ambiguous multiple Evolution instances rejected',evo_probe($evo,[$instances[0],$instances[0]])['verified']===false);
check('unsupported legacy provider shape is not inferred operational',evo_probe($evo,['instance'=>['instanceName'=>'fixture-evo']])['verified']===false);
check('device suffix resolves only actual canonical sender',SchoolSafeWaSessionPhone('243000000000:12@s.whatsapp.net')==='+243000000000');
check('opaque linked-device alias is not invented as phone',SchoolSafeWaSessionPhone('12345@lid')===null);
echo "checks=$n failures=$failed\n";exit($failed?1:0);
