<?php
// Inject ONLY into a nonce-owned exact-image fixture. No production endpoint.
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_SCAN_HTTP_FIXTURE')!=='schoolcef-synthetic-image-20261023'
    ||getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg'||getenv('POSTGRES_DB')!=='schoolcef_fixture')exit(2);
$pdo=new PDO('pgsql:host=schoolcef-harmonization-pg;dbname=schoolcef_fixture',getenv('POSTGRES_USER'),getenv('POSTGRES_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require '/var/www/html/functions/Password.php';
require '/var/www/html/ProgramFunctions/SchoolSafeAccountState.fnc.php';
$prefix='qa.scan.'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(20));$cookie=tempnam('/tmp','scan-http-');
$uid=null;$students=[];$parents=[];$cookies=[$cookie];$operatorCookie=$cookie;$results=[];$failure=null;$person=SchoolSafeAccountPerson($prefix);$moduleOriginal=null;$moduleRow=null;$checkpoint=null;$foodAccount=null;$policyRows=null;
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
    $modules['SchoolSafe']=true;$modules['Food_Service']=true;
    $pdo->exec("UPDATE config SET config_value=".$q(serialize($modules))." WHERE school_id=0 AND title='MODULES'");
    $uid=(int)$pdo->query('SELECT COALESCE(max(staff_id),0)+100 FROM staff')->fetchColumn();
    $pdo->exec("INSERT INTO staff(staff_id,syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(".$uid.",2026,'Synthetic','HTTP',".$q($prefix).','.$q(encrypt_password($password)).",'admin',NULL,".$q(','.$school.',').','.$school.',CURRENT_TIMESTAMP)');
    foreach(['misc/Portal.php','Students/Student.php','Student_ID_Card/StudentIDCard.php','SchoolSafe/Studio.php','SchoolSafe/IssueCard.php','SchoolSafe/ScanVerify.php','SchoolSafe/ScanArrival.php','SchoolSafe/ScanFees.php','SchoolSafe/ScanCanteen.php','SchoolSafe/ScanPickup.php','SchoolSafe/FamilyRead.php','SchoolSafe/FamilyRegister.php','SchoolSafe/FeeModelsRead.php','SchoolSafe/FeeModelsManage.php']as$key)$pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$uid.','.$q($key).",'Y','Y')");
    $grade=(int)$pdo->query('SELECT id FROM school_gradelevels WHERE school_id='.$school.' ORDER BY id LIMIT 1')->fetchColumn();
    $next=(int)$pdo->query('SELECT COALESCE(max(student_id),0)+100 FROM students')->fetchColumn();
    foreach([$next,$next+1]as$id){$pdo->exec("INSERT INTO students(student_id,first_name,last_name) VALUES(".$id.",'Synthetic','HTTP fixture')");$students[]=$id;
        $pdo->exec('INSERT INTO student_enrollment(student_id,school_id,syear,grade_id,start_date) VALUES('.$id.','.$school.',2026,'.($grade?:'NULL').",CURRENT_DATE-interval '1 day')");}
    $parentId=$uid+1;$parentName=$prefix.'.parent';
    $pdo->exec("INSERT INTO staff(staff_id,syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(".$parentId.",2026,'Synthetic','Parent',".$q($parentName).','.$q(encrypt_password($password)).",'parent',NULL,".$q(','.$school.',').','.$school.',CURRENT_TIMESTAMP)');$parents[$parentId]=$parentName;
    foreach(['misc/Portal.php','SchoolSafe/Notifications.php']as$key)$pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$parentId.','.$q($key).",'Y','N')");
    $pdo->exec('INSERT INTO students_join_users(student_id,staff_id) VALUES('.$next.','.$parentId.')');
    $r=requestNative('/index.php');$doc=new DOMDocument();@$doc->loadHTML($r['body']);$xp=new DOMXPath($doc);$form=['USERNAME'=>$prefix,'PASSWORD'=>$password];
    foreach($xp->query('//form[@id="loginform"]//input[@name]')as$input){$name=$input->getAttribute('name');if(!isset($form[$name]))$form[$name]=$input->getAttribute('value');}
    requestNative('/index.php',$form);
    $r=requestNative('/studio-native.php');$doc=new DOMDocument();@$doc->loadHTML($r['body']);$xp=new DOMXPath($doc);$nodes=$xp->query('//*[@id="native-issue-csrf"]');
    gate($r['status']===200&&$nodes->length===1,'Real native login reaches authorized Studio');$csrf=$nodes->item(0)->getAttribute('value');
    foreach(['studio-native-client.js','restored-studio-native.js']as$asset){
        $version=substr(hash_file('sha256','/var/www/html/schoolsafe-card-bridge/'.$asset),0,16);
        gate(str_contains($r['body'],'/schoolsafe-card-bridge/'.$asset.'?v='.$version),'Fresh Studio HTML versions changed native asset '.$asset);
    }
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
    $r=requestNative('/scanner-native.php');
    gate(str_contains($r['body'],'scanner-native.js?v=native-entry-operation-4'),'Fresh scanner HTML invalidates the pre-cancellation client cache');
    $r=requestNative('/scanner-relay.php?operation=capabilities');$scanToken=$r['json']['csrfToken']??'';gate($scanToken!=='','Native scanner supplies its separate CSRF token');
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode(['mode'=>'verify','qr_payload'=>$qr]),['Content-Type: application/json','X-SchoolSafe-CSRF: '.$scanToken]);
    gate($r['status']===200&&($r['json']['data']['result']??null)==='VALID','Actual authenticated scanner accepts the actual HTTP-issued QR');
    // Native business HTTP modes: only new synthetic rows, no teacher attendance,
    // meal debit, receipt or external send. Preserve the original policy exactly.
    $policyWhere="syear=2026 AND school_id=".$school." AND program='schoolsafe_scan' AND title='FEE_MODELS'";
    $policyRows=$pdo->query('SELECT * FROM program_config WHERE '.$policyWhere)->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec('DELETE FROM program_config WHERE '.$policyWhere);
    $emptyPolicy=json_encode(['version'=>1,'school_id'=>$school,'syear'=>2026,'models'=>[]]);
    $pdo->exec("INSERT INTO program_config(syear,school_id,program,title,value) VALUES(2026,".$school.",'schoolsafe_scan','FEE_MODELS',".$q($emptyPolicy).')');
    $checkpoint=(int)$pdo->query('SELECT COALESCE(max(id),0)+100 FROM entry_exit_checkpoints')->fetchColumn();
    $pdo->exec("INSERT INTO entry_exit_checkpoints(id,title,type,schoolsafe_scan_school_id,schoolsafe_scan_syear,schoolsafe_scan_enabled) VALUES(".$checkpoint.",'Synthetic HTTP checkpoint',NULL,".$school.',2026,true)');
    $entryBody=['mode'=>'entry','qr_payload'=>$qr,'location_id'=>(string)$checkpoint,'request_key'=>'11111111-2222-4333-8444-555555555555'];
    $scanHeaders=['Content-Type: application/json','X-SchoolSafe-CSRF: '.$scanToken];
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($entryBody),$scanHeaders);
    gate($r['status']===200&&($r['json']['data']['result']??null)==='PRESENT','Actual arrival HTTP records the native passage');
    $arrival=(int)$pdo->query('SELECT count(*) FROM entry_exit_student_records WHERE student_id='.$next)->fetchColumn();
    gate($arrival===1,'Arrival creates exactly one passage');
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($entryBody),$scanHeaders);
    gate(($r['json']['data']['idempotent']??null)===true&&(int)$pdo->query('SELECT count(*) FROM entry_exit_student_records WHERE student_id='.$next)->fetchColumn()===1,'Actual arrival intention replay creates no duplicate passage');
    $pdo->exec("INSERT INTO billing_fees(student_id,title,amount,school_id,syear) VALUES(".$next.",'Synthetic obligation',100,".$school.',2026)');
    $pdo->exec('INSERT INTO billing_payments(student_id,amount,school_id,syear) VALUES('.$next.',50,'.$school.',2026)');
    $feeBody=['mode'=>'fees','qr_payload'=>$qr,'request_key'=>'66666666-7777-4888-8999-000000000000'];
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($feeBody),$scanHeaders);$fee=$r['json']['data']??[];
    gate($r['status']===200&&($fee['result']??null)==='FEE_STATUS','Actual native financial consultation HTTP works without bridge');
    gate(!array_intersect(['fee_total','paid_total','balance','threshold','tolerance'],array_keys($fee)),'Financial scan delegation without billing access reveals no amounts');
    gate((int)$pdo->query('SELECT count(*) FROM entry_exit_student_records WHERE student_id='.$next)->fetchColumn()===1,'Financial observation is not another arrival');
    gate((int)$pdo->query("SELECT count(*) FROM schoolsafe_notify_events WHERE student_id=".$next." AND event_type='payment.receipt'")->fetchColumn()===0,'Financial observation creates no payment receipt');
    $foodAccount=(int)$pdo->query('SELECT COALESCE(max(account_id),0)+100 FROM food_service_accounts')->fetchColumn();
    $pdo->exec('INSERT INTO food_service_accounts(account_id,balance) VALUES('.$foodAccount.',12.50)');
    $pdo->exec('INSERT INTO food_service_student_accounts(student_id,account_id) VALUES('.$next.','.$foodAccount.')');
    $canteenBody=['mode'=>'canteen','qr_payload'=>$qr];
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($canteenBody),$scanHeaders);$canteen=$r['json']['data']??[];
    gate($r['status']===200&&($canteen['status']??null)==='credit_available','Actual native canteen HTTP reads the existing account');
    gate(!array_key_exists('balance',$canteen),'Canteen scan delegation does not reveal monetary account detail');
    $pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$uid.",'Food_Service/Accounts.php','Y','N')");
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($canteenBody),$scanHeaders);$canteen=$r['json']['data']??[];
    gate(($canteen['balance']??null)==='12.50','Explicit native account read right enables actual canteen detail');
    gate((string)$pdo->query('SELECT balance FROM food_service_accounts WHERE account_id='.$foodAccount)->fetchColumn()==='12.50','Canteen verification performs no debit');
    $staffBefore=(int)$pdo->query('SELECT count(*) FROM staff')->fetchColumn();
    $familyBody=['action'=>'register-family','nativeStudentId'=>(string)$next,'guardianType'=>'mere','fullName'=>'Synthetic collector','phone'=>'','isPrimary'=>true];
    $r=requestNative('/scanner-relay.php?operation=management',json_encode($familyBody),$scanHeaders);$guardian=$r['json']['data']['guardian_id']??null;
    if($r['json']===null)throw new RuntimeException('Native family HTTP non-JSON: '.substr(preg_replace('/\s+/',' ',strip_tags($r['body'])),0,1400));
    gate($r['status']===200&&is_string($guardian),'Actual family registration uses native identity without creating an account [status='.$r['status'].',error='.(string)($r['json']['error']??($r['json']===null?'non-json':'none')).']');
    gate($pdo->query('SELECT phone FROM schoolsafe_pickup_guardians WHERE id='.(int)$guardian)->fetchColumn()==='','Missing phone retains exact native empty default, not NULL or invented number');
    gate((int)$pdo->query('SELECT count(*) FROM staff')->fetchColumn()===$staffBefore,'Family registration creates no forced account');
    $r=requestNative('/scanner-relay.php?operation=management',json_encode(['action'=>'school-list','nativeStudentId'=>(string)$next]),$scanHeaders);
    gate($r['status']===200&&is_array($r['json']['data']??null),'Actual scoped family list is available without bridge');
    $pickupBody=['mode'=>'pickup','qr_payload'=>$qr,'location_id'=>(string)$checkpoint];
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($pickupBody),$scanHeaders);
    gate(($r['json']['data']['result']??null)!=='PICKUP_RECORDED'&&(int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='out'")->fetchColumn()===0,'Unapproved collector cannot cause departure');
    // Synthetic readiness only. NOT a real photo/identity approval or human proof.
    $pdo->exec("UPDATE schoolsafe_pickup_guardians SET is_authorized_pickup=true,photo_accepted=true,photo_path='/synthetic-only/abstract.jpg' WHERE id=".(int)$guardian);
    $pickupBody['authorized_person_id']='g:'.$guardian;
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($pickupBody),$scanHeaders);$prepared=$r['json']['data']??[];
    gate(is_string($prepared['proof_token']??null)&&$prepared['proof_token']!=='','Actual pickup preparation issues a durable scoped proof');
    $confirm=$pickupBody+['authorized_person_id'=>'g:'.$guardian,'proof_token'=>$prepared['proof_token'],'human_confirmed'=>false];
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($confirm),$scanHeaders);
    gate((int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='out'")->fetchColumn()===0,'Missing human confirmation does not create a departure');
    $confirm['human_confirmed']=true;
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($confirm),$scanHeaders);
    gate((int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='out'")->fetchColumn()===1,'Explicit synthetic confirmation creates one native departure');
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($confirm),$scanHeaders);
    gate((int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='out'")->fetchColumn()===1,'Pickup proof replay creates no second departure');
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($entryBody),$scanHeaders);
    gate(($r['json']['data']['entry_allowed']??null)===false&&(int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='in'")->fetchColumn()===1,'Old arrival intention after pickup is not a new admission');
    $pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$uid.",'Student_Billing/StudentFees.php','Y','Y')");
    $eventsBefore=(int)$pdo->query('SELECT count(*) FROM schoolsafe_notify_events WHERE student_id='.$next)->fetchColumn();
    $r=requestNative('/scanner-relay.php?operation=fees',json_encode(['action'=>'save','label'=>'Synthetic HTTP policy','threshold'=>'80.00','tolerance'=>10]),$scanHeaders);$models=$r['json']['data']??[];$model=$models[0]??[];
    gate($r['status']===200&&is_string($model['id']??null)&&($model['active']??null)===false,'Actual native financial model HTTP save is inactive initially');
    $r=requestNative('/scanner-relay.php?operation=fees',json_encode(['action'=>'activate','id'=>$model['id']]),$scanHeaders);
    gate($r['status']===200&&($r['json']['data'][0]['active']??null)===true,'Actual native financial model HTTP activation works');
    gate((int)$pdo->query('SELECT count(*) FROM schoolsafe_notify_events WHERE student_id='.$next)->fetchColumn()===$eventsBefore,'Policy preparation/activation creates no business notification');
    $redEntry=$entryBody;$redEntry['request_key']='aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    $r=requestNative('/scanner-relay.php?operation=scan',json_encode($redEntry),$scanHeaders);
    gate(($r['json']['data']['reason']??null)==='FEE_CONTROL_DENIED'&&(int)$pdo->query("SELECT count(*) FROM entry_exit_student_records WHERE student_id=".$next." AND type='in'")->fetchColumn()===1,'Real native financial red blocks a new admission without creating absence or arrival');
    // Parent one was a legal native recipient at business-write time.
    // Parent two is linked only afterward and cannot inherit the frozen money.
    $lateParent=$uid+2;$lateName=$prefix.'.late';
    $pdo->exec("INSERT INTO staff(staff_id,syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(".$lateParent.",2026,'Synthetic','Late parent',".$q($lateName).','.$q(encrypt_password($password)).",'parent',NULL,".$q(','.$school.',').','.$school.',CURRENT_TIMESTAMP)');$parents[$lateParent]=$lateName;
    foreach(['misc/Portal.php','SchoolSafe/Notifications.php']as$key)$pdo->exec("INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(".$lateParent.','.$q($key).",'Y','N')");
    $pdo->exec('INSERT INTO students_join_users(student_id,staff_id) VALUES('.$next.','.$lateParent.')');
    foreach($parents as$parent=>$name){
        $cookie=tempnam('/tmp','scan-http-parent-');$cookies[]=$cookie;
        $r=requestNative('/index.php');$doc=new DOMDocument();@$doc->loadHTML($r['body']);$xp=new DOMXPath($doc);$form=['USERNAME'=>$name,'PASSWORD'=>$password];
        foreach($xp->query('//form[@id="loginform"]//input[@name]')as$input){$field=$input->getAttribute('name');if(!isset($form[$field]))$form[$field]=$input->getAttribute('value');}
        requestNative('/index.php',$form);
        $r=requestNative('/parent-notifications.php');$notices=$r['json']['data']['notifications']??null;
        gate($r['status']===200&&is_array($notices),'Actual parent inbox HTTP is available for synthetic role '.($parent===$parentId?'original':'late'));
        $financial=array_values(array_filter($notices,fn($n)=>($n['event_type']??null)==='financial.control'));
        gate(count($financial)===($parent===$parentId?2:0),$parent===$parentId?'Original recipient reads its actual frozen financial observation':'New parent link does not inherit historical financial recipients');
        if($parent===$parentId){
            $pdo->exec('DELETE FROM students_join_users WHERE student_id='.$next.' AND staff_id='.$parent);
            $r=requestNative('/parent-notifications.php');$after=$r['json']['data']['notifications']??[];
            gate(!array_filter($after,fn($n)=>($n['student_id']??null)===(string)$next),'Existing parent session loses private messages after live link revocation');
        }
    }
    $cookie=$operatorCookie;
    $pdo->exec("INSERT INTO schoolsafe_account_state(person_key,school_id,state) VALUES(".$q($person).','.$school.",'readonly')");
    $r=requestNative('/card-bridge-issue.php',array_replace($body,['student_id'=>(string)($next+1)]));gate(($r['json']['result']??null)!=='ISSUED'&&$count($next+1)===0,'Readonly account cannot mint another child card');
}catch(Throwable$e){$failure=$e->getMessage();}
finally{
    if($students){$ids=implode(',',array_map('intval',$students));
        foreach(['schoolsafe_notify_events','schoolsafe_entry_requests','schoolsafe_fee_consults','schoolsafe_pickup_proofs','schoolsafe_pickup_authorizations','schoolsafe_pickup_guardians','entry_exit_student_records','food_service_student_accounts','billing_fees','billing_payments','schoolsafe_student_cards','students_join_users','student_enrollment','students']as$table)$pdo->exec('DELETE FROM '.$table.' WHERE student_id IN('.$ids.')');
    }
    if($checkpoint!==null)$pdo->exec('DELETE FROM entry_exit_checkpoints WHERE id='.$checkpoint);
    if($foodAccount!==null)$pdo->exec('DELETE FROM food_service_accounts WHERE account_id='.$foodAccount);
    if($policyRows!==null){
        $pdo->exec('DELETE FROM program_config WHERE '.$policyWhere);
        foreach($policyRows as$row){$columns=array_keys($row);$restore=$pdo->prepare('INSERT INTO program_config('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($columns),'?')).')');$restore->execute(array_values($row));}
    }
    $pdo->exec('DELETE FROM schoolsafe_account_state WHERE person_key='.$q($person));
    foreach($parents as$parent=>$name)$pdo->exec('DELETE FROM staff_exceptions WHERE user_id='.$parent.';DELETE FROM program_user_config WHERE user_id='.$parent.';DELETE FROM staff WHERE staff_id='.$parent.' AND username='.$q($name));
    if($uid!==null){$pdo->exec('DELETE FROM staff_exceptions WHERE user_id='.$uid);$pdo->exec('DELETE FROM program_user_config WHERE user_id='.$uid);$pdo->exec('DELETE FROM staff WHERE staff_id='.$uid.' AND username='.$q($prefix));}
    if($moduleRow){
        // Native UPDATE changes updated_at. Restore the exact fixture-owned row,
        // including timestamps, rather than masking drift by recapturing baseline.
        $pdo->beginTransaction();$pdo->exec("DELETE FROM config WHERE school_id=0 AND title='MODULES'");
        $restore=$pdo->prepare('INSERT INTO config(school_id,title,config_value,created_at,updated_at) VALUES(?,?,?,?,?)');
        $restore->execute(array_values($moduleRow));$pdo->commit();
    }
    foreach($cookies as$path)if(is_file($path))unlink($path);
}
echo json_encode(['status'=>$failure?'FAIL':'PASS','checks'=>count($results),'results'=>$results,'error'=>$failure,'scope'=>'authenticated HTTP in nonce-owned exact image; only synthetic fixture identities; no provider or physical camera'])."\n";
exit($failure?1:0);
