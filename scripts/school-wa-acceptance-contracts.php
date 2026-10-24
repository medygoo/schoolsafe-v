<?php
// Pure native transport tests. Every HTTP call is intercepted; no live DB/provider.
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:__DIR__.'/../app/modules/codex-ui-v1/extensions-test-runtime';
require_once $root.'/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';
function Config($key){return 'N';}
function DBEscapeString($value){return str_replace("'","''",(string)$value);}
function DBGet($sql){
    if(strpos($sql,'INSERT INTO schoolsafe_wa_outbox')!==false)return empty($GLOBALS['reject_insert'])?[1=>['ID'=>1]]:[];
    if(strpos($sql,'FROM schoolsafe_wa_outbox')!==false)return $GLOBALS['known']??[];
    if(strpos($sql,'FROM schoolsafe_wa_consents')!==false)return [1=>['ID'=>1]];
    if(strpos($sql,'FROM schoolsafe_wa_sessions')!==false)return [1=>['ID'=>1,'SESSION_NAME'=>strpos($sql,"'evolution'")!==false?'fixture-evo':'fixture-waha']];
    throw new RuntimeException('UNEXPECTED_SQL');
}
function DBInsert($table,$row,$key){$GLOBALS['writes'][]=$row;return empty($GLOBALS['reject_insert'])?1:0;}
function DBQuery($sql){$GLOBALS['sql'][]=$sql;return $sql;}
putenv('WAHA_BASE=https://waha.invalid');putenv('WAHA_KEY=fixture-not-a-real-key');
putenv('EVOLUTION_BASE=https://evolution.invalid');putenv('EVOLUTION_KEY=fixture-not-a-real-key');
$checks=0;$errors=0;
function check($label,$ok){global $checks,$errors;$checks++;if(!$ok)$errors++;echo ($ok?'PASS ':'FAIL ').$label."\n";}
function attempt(array $primary,array $secondary=['http_code'=>202,'body'=>'{"key":{"id":"fixture-evo-id"}}']){
    $GLOBALS['known']=[];$GLOBALS['sql']=[];$GLOBALS['writes']=[];$GLOBALS['calls']=['waha'=>0,'evolution'=>0];
    return SchoolSafeWaSend('+243000000000','Observation synthétique — pas un reçu',[
        'school_id'=>1,'staff_id'=>27,'actor'=>'isolated-fixture','dedup_key'=>'fixture-acceptance',
        'http_waha'=>function()use($primary){$GLOBALS['calls']['waha']++;return $primary;},
        'http_evo'=>function()use($secondary){$GLOBALS['calls']['evolution']++;return $secondary;},
    ]);
}
foreach([['http_code'=>0,'network_error'=>'timeout'],['http_code'=>429,'body'=>''],['http_code'=>500,'body'=>''],['http_code'=>408,'body'=>'']]as$response){
    $out=attempt($response);
    check('ambiguous primary '.$response['http_code'].' never sends fallback',$GLOBALS['calls']['waha']===1&&$GLOBALS['calls']['evolution']===0);
    check('ambiguous primary '.$response['http_code'].' stays queued not accepted/failed',($out['status']??'')==='queued'&&($out['error']??'')==='ACCEPTANCE_UNKNOWN');
}
$out=attempt(['http_code'=>202,'body'=>'{"id":"fixture-primary-id"}']);
check('actual primary message identifier permits accepted',($out['status']??'')==='accepted'&&$GLOBALS['calls']['evolution']===0);
foreach(['','{}','{"id":{}}','{"id":false}']as$body){
    $out=attempt(['http_code'=>202,'body'=>$body]);
    check('2xx without valid provider identifier is not acceptance',($out['status']??'')==='queued'&&$GLOBALS['calls']['evolution']===0);
}
$out=attempt(['http_code'=>400,'body'=>'{"error":"invalid recipient"}']);
check('definite client rejection never blindly switches provider',($out['status']??'')==='failed'&&$GLOBALS['calls']['evolution']===0);
$notSent=['http_code'=>0,'curl_errno'=>6,'request_size'=>0,'request_not_sent'=>true,'network_error'=>'fixture DNS failure'];
$out=attempt($notSent);
check('transport proof request never sent permits exactly one fallback',($out['status']??'')==='accepted'&&$GLOBALS['calls']['waha']===1&&$GLOBALS['calls']['evolution']===1);
$out=attempt($notSent,['http_code'=>0,'network_error'=>'secondary timeout']);
check('ambiguous secondary is queued and never attempted a second time',($out['status']??'')==='queued'&&$GLOBALS['calls']['evolution']===1);
check('generic network failure is not definitive nonacceptance',SchoolSafeWaConfirmedFailure(['http_code'=>0,'network_error'=>'reset'])===false);
check('contradictory success cannot authorize fallback',SchoolSafeWaConfirmedFailure($notSent+['unused'=>true])===true&&SchoolSafeWaConfirmedFailure(array_replace($notSent,['http_code'=>202]))===false);
$GLOBALS['known']=[1=>['ID'=>9,'STATUS'=>'queued']];$GLOBALS['calls']=['waha'=>0,'evolution'=>0];
$out=SchoolSafeWaSend('+243000000000','Observation synthétique',['school_id'=>1,'staff_id'=>27,'dedup_key'=>'fixture-acceptance','http_waha'=>function(){throw new RuntimeException('REPLAY_SENT');}]);
check('queued uncertain intention is never resent on replay',($out['status']??'')==='queued'&&($out['via']??'')==='dedup');
$GLOBALS['reject_insert']=true;
$out=attempt(['http_code'=>202,'body'=>'{"id":"must-not-send"}']);
check('outbox insertion failure prevents all provider calls',($out['status']??'')==='failed'&&$GLOBALS['calls']['waha']===0&&$GLOBALS['calls']['evolution']===0);
$GLOBALS['reject_insert']=false;$GLOBALS['known']=[];$called=false;
$out=SchoolSafeWaSend('+243000000000',str_repeat('a',2001),['school_id'=>1,'staff_id'=>27,'http_waha'=>function()use(&$called){$called=true;return ['http_code'=>202,'body'=>'{"id":"fixture-id"}'];}]);
check('oversized observation is rejected not silently truncated',($out['error']??'')==='TEXT_TOO_LONG'&&!$called);
echo "checks=$checks failures=$errors\n";exit($errors?1:0);
