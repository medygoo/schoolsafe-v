<?php
/** Run only in nonce-owned exact-image QA web; all accounts and mutations synthetic. */
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_SCAN_HTTP_FIXTURE')!=='schoolcef-synthetic-image-20261023'
 ||getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg'||getenv('POSTGRES_DB')!=='schoolcef_fixture')exit(2);
$pdo=new PDO('pgsql:host=schoolcef-harmonization-pg;dbname=schoolcef_fixture',getenv('POSTGRES_USER'),getenv('POSTGRES_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require '/var/www/html/functions/Password.php';
$prefix='qa.state.'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(24));$cookie=tempnam('/tmp','state-qa-');$checks=[];$error=null;$staff=null;$profile=null;$trigger=false;
$q=fn($v)=>$pdo->quote((string)$v);
function gate($ok,$message){global$checks;if(!$ok)throw new RuntimeException($message);$checks[]=$message;}
function request($path,$fields=null){global$cookie;if(!str_starts_with($path,'/')||str_starts_with($path,'//'))throw new RuntimeException('loopback only');
 $c=curl_init('http://127.0.0.1'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
 if($fields!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);if(!is_string($body))throw new RuntimeException('HTTP failed');return [$status,$body];}
function snapshot($pdo,$school){return [$pdo->query('SELECT row_to_json(t)::text FROM (SELECT * FROM schoolsafe_school_state WHERE school_id='.$school.') t')->fetchColumn(),
 (int)$pdo->query('SELECT count(*) FROM schoolsafe_school_audit WHERE school_id='.$school)->fetchColumn()];}
try{
 $school=(int)$pdo->query('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1')->fetchColumn();gate($school>0,'synthetic school exists');
 $before=$pdo->query('SELECT * FROM schoolsafe_school_state WHERE school_id='.$school)->fetch(PDO::FETCH_ASSOC);
 $beforeSnapshot=snapshot($pdo,$school);
 $profile=(int)$pdo->query('INSERT INTO user_profiles(title,profile) VALUES('.$q($prefix).",'admin') RETURNING id")->fetchColumn();
 $pdo->exec('INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit) VALUES('.$profile.", 'Users/User.php','Y','')");
 $staff=(int)$pdo->query('SELECT coalesce(max(staff_id),0)+200 FROM staff')->fetchColumn();
 $pdo->exec('INSERT INTO staff(staff_id,syear,username,password,first_name,last_name,profile,profile_id,schools,current_school_id,last_login) VALUES('.$staff.',2026,'.$q($prefix).','.$q(encrypt_password($password)).",'Synthetic','State QA','admin',".$profile.',NULL,'.$school.',CURRENT_TIMESTAMP)');
 $login=request('/index.php');gate($login[0]===200,'native login form responds');
 $form=['USERNAME'=>$prefix,'PASSWORD'=>$password];$doc=new DOMDocument();@$doc->loadHTML($login[1]);$xp=new DOMXPath($doc);foreach($xp->query('//form[@id="loginform"]//input[@name]')as$n){$key=$n->getAttribute('name');if(!isset($form[$key]))$form[$key]=$n->getAttribute('value');}
 request('/index.php',$form);
 [$status,$html]=request('/admin-console.php');gate($status===200,'native central console authenticated with synthetic global actor');
 $doc=new DOMDocument();@$doc->loadHTML($html);$xp=new DOMXPath($doc);$tokens=$xp->query('//input[@name="console_token"]');gate($tokens->length>0,'native console exposes session CSRF token');$csrf=$tokens->item(0)->getAttribute('value');
 $post=['console_action'=>'school_state','school_id'=>(string)$school,'school_state'=>'suspended','school_reason'=>$prefix,'school_until'=>'2026-99-99','console_token'=>'bad'];
 request('/admin-console.php',$post);gate(snapshot($pdo,$school)===$beforeSnapshot,'invalid CSRF does not change school or audit');
 $post['console_token']=$csrf;[$status,$body]=request('/admin-console.php',$post);gate($status===200&&snapshot($pdo,$school)===$beforeSnapshot,'invalid calendar date refused by actual central POST');
 $post['school_until']='';[$status,$body]=request('/admin-console.php',$post);$after=snapshot($pdo,$school);gate(in_array($status,[302,303],true)&&$after[1]===$beforeSnapshot[1]+1,'valid suspension and audit committed by central POST');
 $state=$pdo->query('SELECT state FROM schoolsafe_school_state WHERE school_id='.$school)->fetchColumn();gate($state==='suspended','valid central POST stores suspended state');
 // A trigger in the fixture only: failure occurs on the new synthetic reason, after the state UPDATE.
 $pdo->exec('CREATE FUNCTION fixture_state_audit_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.reason LIKE '.$q($prefix.'.fail%')." THEN RAISE EXCEPTION 'SYNTHETIC_AUDIT_FAILURE'; END IF; RETURN NEW; END $$");
 $pdo->exec('CREATE TRIGGER fixture_state_audit_fail BEFORE INSERT ON schoolsafe_school_audit FOR EACH ROW EXECUTE FUNCTION fixture_state_audit_fail()');$trigger=true;
 $stable=snapshot($pdo,$school);$post['school_state']='inactive';$post['school_reason']=$prefix.'.fail';
 [$status,$body]=request('/admin-console.php',$post);gate($status===200&&snapshot($pdo,$school)===$stable,'audit failure rolls back central POST state and audit');
 $state=$pdo->query('SELECT state FROM schoolsafe_school_state WHERE school_id='.$school)->fetchColumn();gate($state==='suspended','audit failure preserves previous effective suspension');
}catch(Throwable$e){$error=$e->getMessage();}
finally{
 try{
  if($trigger)$pdo->exec('DROP TRIGGER fixture_state_audit_fail ON schoolsafe_school_audit; DROP FUNCTION fixture_state_audit_fail()');
  if(isset($school)&&$school>0&&isset($beforeSnapshot)){
   $pdo->exec('DELETE FROM schoolsafe_school_audit WHERE school_id='.$school.' AND actor='.$q($prefix));
   $pdo->exec('DELETE FROM schoolsafe_school_state WHERE school_id='.$school);
   if($before){$keys=array_keys($before);$values=array_map(fn($v)=>$v===null?'NULL':$q($v),array_values($before));$pdo->exec('INSERT INTO schoolsafe_school_state('.implode(',',$keys).') VALUES('.implode(',',$values).')');}
   if(snapshot($pdo,$school)!==$beforeSnapshot)throw new RuntimeException('fixture not restored');
  }
  if($staff!==null)$pdo->exec('DELETE FROM staff WHERE staff_id='.$staff.' AND username='.$q($prefix));
  if($profile!==null){$pdo->exec('DELETE FROM profile_exceptions WHERE profile_id='.$profile);$pdo->exec('DELETE FROM user_profiles WHERE id='.$profile.' AND title='.$q($prefix));}
  if(is_file($cookie))unlink($cookie);
 }catch(Throwable$cleanup){$error='synthetic cleanup failed';}
}
echo json_encode(['status'=>$error?'FAIL':'PASS','checks'=>count($checks),'results'=>$checks,'error'=>$error],JSON_UNESCAPED_UNICODE)."\n";exit($error?1:0);
