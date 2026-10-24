<?php
// Inject ONLY into a nonce-owned exact-image fixture. No production endpoint.
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_SCAN_HTTP_FIXTURE')!=='schoolcef-synthetic-image-20261023'
    ||getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg'||getenv('POSTGRES_DB')!=='schoolcef_fixture')exit(2);
$pdo=new PDO('pgsql:host=schoolcef-harmonization-pg;dbname=schoolcef_fixture',getenv('POSTGRES_USER'),getenv('POSTGRES_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require '/var/www/html/functions/Password.php';
require '/var/www/html/ProgramFunctions/SchoolSafeAccountState.fnc.php';
$prefix='qa.scan.'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(20));$cookie=tempnam('/tmp','scan-http-');
$uid=null;$students=[];$results=[];$failure=null;$person=SchoolSafeAccountPerson($prefix);$moduleOriginal=null;$moduleRow=null;
$q=fn($s)=>$pdo->quote($s);
function gate($ok,$name){global$results;if(!$ok)throw new RuntimeException($name);$results[]=$name;}
function requestNative($path,$fields=null,$headers=[]){global$cookie;
    if(!str_starts_with($path,'/')||str_starts_with($path,'//'))throw new RuntimeException('Only fixture loopback');
    $c=curl_init('http://127.0.0.1'.$path);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_HTTPHEADER=>$headers];
    if($fields!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=is_array($fields)?http_build_query($fields):$fields;}
    curl_setopt_array($c,$options);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if(!is_string($body))throw new RuntimeException('Fixture HTTP unavailable');
    return ['status'=>$code,'body'=>$body,'json'=>json_decode($body,true)];
}
try{
    $school=(int)$pdo->query('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1')->fetchColumn();gate($school>0,'Synthetic fixture school exists');
    $moduleRow=$pdo->query("SELECT school_id,title,config_value,created_at,updated_at FROM config WHERE school_id=0 AND title='MODULES'")->fetch(PDO::FETCH_ASSOC);
    $moduleOriginal=$moduleRow['config_value']??null;
    $modules=unserialize($moduleOriginal,['allowed_classes'=>false]);gate(is_array($modules),'Native module configuration is readable');
    $modules['SchoolSafe']=true;
    $pdo->exec("UPDATE config SET config_value=".$q(serialize($modules))." WHERE school_id=0 AND title='MODULES'");
    $uid=(int)$pdo->query('SELECT COALESCE(max(staff_id),0)+100 FROM staff')->fetchColumn();
    $pdo->exec("INSERT INTO staff(staff_id,syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(".$uid.",2026,'Synthetic','HTTP',".$q($prefix).','.$q(encrypt_password($password)).",'admin',NULL,".$q(','.$school.',').','.$school.',CURRENT_TIMESTAMP)');
    foreach(['misc/Portal.php','Students/Student.php','Student_ID_Card/StudentIDCard.php','SchoolSafe/Studio.php','SchoolSafe/IssueCard.php','SchoolSafe/ScanVerify.php']as$key)$pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$uid.','.$q($key).",'Y','Y')");
    $grade=(int)$pdo->query('SELECT id FROM school_gradelevels WHERE school_id='.$school.' ORDER BY id LIMIT 1')->fetchColumn();
    $next=(int)$pdo->query('SELECT COALESCE(max(student_id),0)+100 FROM students')->fetchColumn();
    foreach([$next,$next+1]as$id){$pdo->exec("INSERT INTO students(student_id,first_name,last_name) VALUES(".$id.",'Synthetic','HTTP fixture')");$students[]=$id;
        $pdo->exec('INSERT INTO student_enrollment(student_id,school_id,syear,grade_id,start_date) VALUES('.$id.','.$school.',2026,'.($grade?:'NULL').",CURRENT_DATE-interval '1 day')");}
    $r=requestNative('/index.php');$doc=new DOMDocument();@$doc->loadHTML($r['body']);$xp=new DOMXPath($doc);$form=['USERNAME'=>$prefix,'PASSWORD'=>$password];
    foreach($xp->query('//form[@id="loginform"]//input[@name]')as$input){$name=$input->getAttribute('name');if(!isset($form[$name]))$form[$name]=$input->getAttribute('value');}
    requestNative('/index.php',$form);
    $r=requestNative('/studio-native.php');$doc=new DOMDocument();@$doc->loadHTML($r['body']);$xp=new DOMXPath($doc);$nodes=$xp->query('//*[@id="native-issue-csrf"]');
    gate($r['status']===200&&$nodes->length===1,'Real native login reaches authorized Studio');$csrf=$nodes->item(0)->getAttribute('value');
    $r=requestNative('/card-bridge-prepare.php?resource=studio-input&student_id='.$next);
    gate($r['status']===200&&($r['json']['data']['backend']??null)==='native-registry','Authenticated preparation uses native provider without lab bridge');
    gate(($r['json']['operationPerformed']??null)===false,'Authenticated preparation performs no issuance');
    gate(($r['json']['data']['nativeCredentialIssuanceAllowed']??null)===true,'Configured native module exposes only actual issuance capability');
    $count=fn($id)=>(int)$pdo->query('SELECT count(*) FROM schoolsafe_student_cards WHERE student_id='.(int)$id)->fetchColumn();gate($count($next)===0,'Native preparation leaves registry empty');
    $body=['student_id'=>(string)$next,'csrf'=>$csrf,'expected_school'=>(string)$school,'expected_year'=>'2026'];
    $r=requestNative('/card-bridge-issue.php',array_replace($body,['csrf'=>'synthetic-invalid']));gate($r['status']===403&&$count($next)===0,'Invalid CSRF cannot issue through real HTTP');
    $r=requestNative('/card-bridge-issue.php',array_replace($body,['expected_school'=>(string)($school+1)]));gate($r['status']===409&&$count($next)===0,'Stale school intent cannot write through real HTTP');
    $r=requestNative('/card-bridge-issue.php',array_replace($body,['expected_year'=>'2025']));gate($r['status']===409&&$count($next)===0,'Stale academic year cannot write through real HTTP');
    $pdo->exec("UPDATE staff_exceptions SET can_edit='N' WHERE user_id=".$uid." AND modname='SchoolSafe/IssueCard.php'");
    $r=requestNative('/card-bridge-issue.php',$body);gate($r['status']===403&&$count($next)===0,'Live native edit permission revoked before real HTTP issuance');
    $pdo->exec("UPDATE staff_exceptions SET can_edit='Y' WHERE user_id=".$uid." AND modname='SchoolSafe/IssueCard.php'");
    $r=requestNative('/card-bridge-issue.php',$body);gate($r['status']===200&&($r['json']['result']??null)==='ISSUED'&&$count($next)===1,'Authorized HTTP action writes one native card [status='.$r['status'].']');$qr=$r['json']['data']['credential']['qr_payload'];
    $r=requestNative('/card-bridge-prepare.php?resource=studio-input&student_id='.$next);gate(($r['json']['data']['input']['credential']['qr_payload']??null)===$qr,'Actual Studio rereads the issued QR');
    $r=requestNative('/card-bridge-issue.php',$body);gate(($r['json']['result']??null)==='ALREADY_ISSUED'&&($r['json']['operationPerformed']??null)===false&&$count($next)===1,'HTTP replay is stable and not another issuance');
    $r=requestNative('/scanner-relay.php?operation=capabilities');$scanToken=$r['json']['csrfToken']??'';gate($scanToken!=='','Native scanner supplies its separate CSRF token');
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode(['mode'=>'verify','qr_payload'=>$qr]),['Content-Type: application/json','X-SchoolSafe-CSRF: '.$scanToken]);
    gate($r['status']===200&&($r['json']['data']['result']??null)==='VALID','Actual authenticated scanner accepts the actual HTTP-issued QR');
    $pdo->exec("INSERT INTO schoolsafe_account_state(person_key,school_id,state) VALUES(".$q($person).','.$school.",'readonly')");
    $r=requestNative('/card-bridge-issue.php',array_replace($body,['student_id'=>(string)($next+1)]));gate(($r['json']['result']??null)!=='ISSUED'&&$count($next+1)===0,'Readonly account cannot mint another child card');
}catch(Throwable$e){$failure=$e->getMessage();}
finally{
    if($students){$ids=implode(',',array_map('intval',$students));$pdo->exec('DELETE FROM schoolsafe_student_cards WHERE student_id IN('.$ids.');DELETE FROM student_enrollment WHERE student_id IN('.$ids.');DELETE FROM students WHERE student_id IN('.$ids.')');}
    $pdo->exec('DELETE FROM schoolsafe_account_state WHERE person_key='.$q($person));
    if($uid!==null){$pdo->exec('DELETE FROM staff_exceptions WHERE user_id='.$uid);$pdo->exec('DELETE FROM program_user_config WHERE user_id='.$uid);$pdo->exec('DELETE FROM staff WHERE staff_id='.$uid.' AND username='.$q($prefix));}
    if($moduleRow){
        // Native UPDATE changes updated_at. Restore the exact fixture-owned row,
        // including timestamps, rather than masking drift by recapturing baseline.
        $pdo->beginTransaction();$pdo->exec("DELETE FROM config WHERE school_id=0 AND title='MODULES'");
        $restore=$pdo->prepare('INSERT INTO config(school_id,title,config_value,created_at,updated_at) VALUES(?,?,?,?,?)');
        $restore->execute(array_values($moduleRow));$pdo->commit();
    }
    if(is_file($cookie))unlink($cookie);
}
echo json_encode(['status'=>$failure?'FAIL':'PASS','checks'=>count($results),'results'=>$results,'error'=>$failure,'scope'=>'authenticated HTTP in nonce-owned exact image; only synthetic fixture identities; no provider or physical camera'])."\n";
exit($failure?1:0);
