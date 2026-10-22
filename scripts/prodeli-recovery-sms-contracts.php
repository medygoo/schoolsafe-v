<?php
/** Real PostgreSQL, disposable schema only. Never run on production. */
if (PHP_SAPI!=='cli' || getenv('POSTGRES_DB')!=='schoolcef_fixture' || getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg') exit(2);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/candidate';
$GLOBALS['db_connection']=pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'));
$GLOBALS['DatabaseType']='postgresql';
function do_action($tag,$args=[]) {}
function Config($key){return $GLOBALS['fixture_config'][$key]??($key==='SYEAR'?'2026':'');}
require $root.'/database.inc.php';require $root.'/functions/DBGet.fnc.php';require $root.'/functions/DBUpsert.php';
set_error_handler(static function($level){if($level===E_WARNING&&(error_reporting()&$level))throw new RuntimeException('Unhandled fixture warning');return false;});
require $root.'/ProgramFunctions/SchoolSafeRecovery.fnc.php';require $root.'/ProgramFunctions/SchoolSafeSms.fnc.php';
if (($argv[1]??'')==='verify') {
 $schema=$argv[2]??'';if(!preg_match('/^qa_recovery_[a-f0-9]{12}$/D',$schema))exit(2);
 DBQuery('SET search_path TO '.$schema);echo "READY\n";flush();echo SchoolSafeRecoveryOtpVerify((int)$argv[3],$argv[4])?'1':'0';exit;
}
$checks=0;$failures=0;
function check($ok,$name){global $checks,$failures;$checks++;if(!$ok)$failures++;echo ($ok?'PASS ':'FAIL ').$name."\n";}
$schema='qa_recovery_'.bin2hex(random_bytes(6));
try {
 DBQuery('CREATE SCHEMA '.$schema);DBQuery('SET search_path TO '.$schema);
 DBQuery("CREATE TABLE staff(staff_id serial,syear integer,username text,custom_200000001 text);INSERT INTO staff(syear,username,custom_200000001) VALUES(2026,'qa.recovery','+243810000001');CREATE TABLE schoolsafe_identity_links(staff_id integer);CREATE TABLE schoolsafe_native_onboarding(staff_id integer,syear integer,completed_at timestamp);");
 foreach(['035-recovery-epoch-otp.sql','036-sms-lines.sql'] as $file)DBQuery(file_get_contents($root.'/schoolsafe-remediation/'.$file));
 $id=SchoolSafeRecoveryOtpIssue('qa.recovery','+243810000001',static fn()=>'123456','127.0.0.1');
 check($id>0,'OTP issued for matching registered phone');
 check(DBGetOne('SELECT code_plain FROM schoolsafe_recovery_otp WHERE id='.(int)$id)===null,'production OTP not stored in plaintext');
 check(SchoolSafeRecoveryOtpVerify($id,'123456')&&!SchoolSafeRecoveryOtpVerify($id,'123456'),'OTP single use sequential');
 $id=SchoolSafeRecoveryOtpIssue('qa.recovery','+243810000001',static fn()=>'123456');
 for($i=0;$i<5;$i++)check(!SchoolSafeRecoveryOtpVerify($id,'999999'),'wrong code refused #'.($i+1));
 check((int)DBGetOne('SELECT attempts FROM schoolsafe_recovery_otp WHERE id='.$id)===5,'attempt bound exactly five');
 check(DBGetOne('SELECT consumed_at FROM schoolsafe_recovery_otp WHERE id='.$id)!==null,'fifth incorrect attempt consumes immediately');
 $id=SchoolSafeRecoveryOtpIssue('qa.recovery','+243810000001',static fn()=>'123456');DBQuery("UPDATE schoolsafe_recovery_otp SET expires_at=CURRENT_TIMESTAMP-interval '1 second' WHERE id=".$id);
 check(!SchoolSafeRecoveryOtpVerify($id,'123456'),'expired code refused');
 $id=SchoolSafeRecoveryOtpIssue('qa.recovery','+243810000001',static fn()=>'123456');DBQuery('BEGIN');DBQuery('SELECT id FROM schoolsafe_recovery_otp WHERE id='.$id.' FOR UPDATE');$workers=[];
 for($i=0;$i<2;$i++){$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'verify',$schema,(string)$id,'123456'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);if(trim(fgets($pipes[1]))!=='READY')throw new RuntimeException('Worker not ready');$workers[]=[$p,$pipes];}
 usleep(250000);DBQuery('COMMIT');$wins=0;
 foreach($workers as [$p,$pipes]){$wins+=(int)trim(stream_get_contents($pipes[1]));fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Worker failed');}
 check($wins===1,'concurrent valid OTP consumes once across independent processes');
 if(function_exists('SchoolSafeRecoveryOtpRequest')){
  $calls=[];$sender=static function($phone,$text,$opts)use(&$calls){$calls[]=[$phone,$text,$opts];return ['sent'=>true];};
  $request=SchoolSafeRecoveryOtpRequest('qa.recovery','0810000001','127.0.0.1',$sender,static fn()=>'654321');
  check($request>0&&count($calls)===1&&str_contains($calls[0][1],'654321'),'registered recipient receives the actual newly issued code');
  check($calls[0][0]==='+243810000001'&&!empty($calls[0][2]['dedup_key']),'canonical recipient and challenge-specific dedup key');
  check(SchoolSafeRecoveryOtpVerify($request,'654321'),'sent code matches stored hash');
  $before=count($calls);check(!SchoolSafeRecoveryOtpRequest('unknown','+243810000001','127.0.0.1',$sender),'unknown account produces no challenge');
  check(!SchoolSafeRecoveryOtpRequest('qa.recovery','+243820000001','127.0.0.1',$sender)&&count($calls)===$before,'wrong phone never sent an SMS');
  check(!SchoolSafeRecoveryOtpRequest('qa.recovery','+243810000001','127.0.0.1',static fn()=>['sent'=>false]),'provider rejection fails closed');
  check((int)DBGetOne('SELECT count(*) FROM schoolsafe_recovery_otp WHERE consumed_at IS NULL')===0,'failed delivery leaves no usable challenge');
  check(!SchoolSafeRecoveryOtpRequest('qa.recovery','+243810000001','127.0.0.1',static function(){throw new RuntimeException('synthetic unavailable');}),'provider exception fails closed');
  DBQuery('INSERT INTO schoolsafe_identity_links VALUES(1)');check(!SchoolSafeRecoveryOtpRequest('qa.recovery','+243810000001','127.0.0.1',$sender)&&count($calls)===$before,'linked identity is not converted to native recovery');DBQuery('DELETE FROM schoolsafe_identity_links');
  DBQuery('INSERT INTO schoolsafe_native_onboarding VALUES(1,2026,NULL)');check(!SchoolSafeRecoveryOtpRequest('qa.recovery','+243810000001','127.0.0.1',$sender),'pending first access remains separate from completed-account recovery');DBQuery('DELETE FROM schoolsafe_native_onboarding');
 }else check(false,'request orchestration must issue before transport');
 $controller=file_get_contents($root.'/plateforme-prodeli-sms.php');
 check((bool)preg_match('/SchoolSafeSmsSend\\(.*?\x27line_id\x27\\s*=>\\s*\\$id/s',$controller),'SMS controller passes the explicitly confirmed line');
 DBQuery("UPDATE schoolsafe_sms_lines SET status='active',conn_state='online',quota_total=100,sim_number=id WHERE id IN(1,2)");
 foreach([1,2] as $line){putenv('SMS_LINE_'.$line.'_BASE=http://127.0.0.1:9/line'.$line);putenv('SMS_LINE_'.$line.'_USER=fixture');putenv('SMS_LINE_'.$line.'_PASS=fixture');}
 check((int)SchoolSafeSmsPickLine('vodacom',1)['line']['ID']===1,'ordinary automatic routing preserved');
 check((int)SchoolSafeSmsPickLine('vodacom',1,2)['line']['ID']===2,'explicit test selects requested line, not recipient operator');
 $httpCalls=[];$http=static function($method,$url,$user,$pass,$payload)use(&$httpCalls){$httpCalls[]=[$url,json_decode($payload,true)];return ['http_code'=>202,'body'=>'{"id":"synthetic"}'];};
 $out=SchoolSafeSmsSend('+243810000001','synthetic test',['purpose'=>'test','actor'=>'qa','line_id'=>2,'dedup_key'=>'qa-line-2','http'=>$http]);
 check($out['status']==='accepted'&&(int)DBGetOne('SELECT line_id FROM schoolsafe_sms_outbox WHERE id='.(int)$out['outbox_id'])===2,'outbox and transport use selected line');
 check(str_contains($httpCalls[0][0],'line2')&&$httpCalls[0][1]['simNumber']===2,'selected gateway and SIM used');
 check((int)DBGetOne('SELECT quota_used FROM schoolsafe_sms_lines WHERE id=1')===0,'other network quota unchanged');
 $calls=count($httpCalls);
 $replay=SchoolSafeSmsSend('+243810000001','synthetic test',['purpose'=>'test','actor'=>'qa','line_id'=>2,'dedup_key'=>'qa-line-2','http'=>$http]);
 check($replay['via']==='dedup'&&count($httpCalls)===$calls,'selected-line replay does not send twice');
 $mismatch=SchoolSafeSmsSend('+243810000001','synthetic test',['purpose'=>'test','actor'=>'qa','line_id'=>1,'dedup_key'=>'qa-line-2','http'=>$http]);
 check($mismatch['status']==='blocked'&&count($httpCalls)===$calls,'dedup cannot switch the confirmed line');
 foreach([0,-1,'invalid'] as $bad){$blocked=SchoolSafeSmsSend('+243810000001','synthetic test',['purpose'=>'test','actor'=>'qa','line_id'=>$bad,'http'=>$http]);check($blocked['status']==='blocked'&&count($httpCalls)===$calls,'invalid selected line rejected: '.(string)$bad);}
 $blocked=SchoolSafeSmsSend('+243810000001','synthetic alert',['purpose'=>'alert','actor'=>'qa','line_id'=>2,'http'=>$http]);
 check($blocked['status']==='blocked'&&count($httpCalls)===$calls,'ordinary notifications cannot force a test line');
 DBQuery('UPDATE schoolsafe_sms_lines SET quota_total=quota_used WHERE id=2');
 check(SchoolSafeSmsPickLine('vodacom',1,2)['line']===false,'exhausted requested line never falls back');
 DBQuery('UPDATE schoolsafe_sms_lines SET quota_total=100 WHERE id=2');
 DBQuery("UPDATE schoolsafe_sms_lines SET status='disabled' WHERE id=2");$calls=count($httpCalls);
 $blocked=SchoolSafeSmsSend('+243810000001','test unavailable',['purpose'=>'test','actor'=>'qa','line_id'=>2,'dedup_key'=>'qa-disabled-2','http'=>$http]);
 check($blocked['status']==='blocked'&&count($httpCalls)===$calls,'disabled requested line never falls back to another line');
 SchoolSafeSmsSend('+243810000001','code 987654',['purpose'=>'otp','actor'=>'qa','dedup_key'=>'qa-private-otp','http'=>$http]);
 check(!str_contains((string)DBGetOne("SELECT message_text FROM schoolsafe_sms_outbox WHERE idempotency_key='qa-private-otp'"),'987654'),'OTP is not persisted in SMS outbox plaintext');
}finally{if(pg_transaction_status($GLOBALS['db_connection'])!==PGSQL_TRANSACTION_IDLE)pg_query($GLOBALS['db_connection'],'ROLLBACK');pg_query($GLOBALS['db_connection'],'SET search_path TO public');pg_query($GLOBALS['db_connection'],'DROP SCHEMA IF EXISTS '.$schema.' CASCADE');}
echo "$checks checks, $failures failures\n";exit($failures?1:0);
