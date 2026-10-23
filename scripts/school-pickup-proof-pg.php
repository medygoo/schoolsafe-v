<?php
/** Disposable PostgreSQL fixture only. No HTTP, camera, real identities or external sends. */
if (PHP_SAPI !== 'cli' || getenv('SCHOOLSAFE_SCAN_FIXTURE') !== 'schoolcef-isolated-scan-20261023'
    || getenv('POSTGRES_DB') !== 'schoolcef_fixture'
    || !preg_match('/^scan-proof-db-[a-f0-9]{10}$/D', getenv('SCHOOLSAFE_DB_HOST') ?: '')) exit(2);
$root = getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT') ?: '/repo/app/modules/codex-ui-v1/extensions-test-runtime';
$dsn = 'pgsql:host='.getenv('SCHOOLSAFE_DB_HOST').';dbname=schoolcef_fixture';
$connect = static function() use ($dsn) {
    return new PDO($dsn, 'schoolcef_fixture', getenv('POSTGRES_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
};
$pdo=$connect();$pdo->exec("SET TIME ZONE 'UTC'; SET statement_timeout='20s'");
$action=$argv[1]??'';
if ($action==='setup') {
    if ((int)$pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")->fetchColumn()!==0) throw new RuntimeException('EMPTY_DISPOSABLE_DATABASE_REQUIRED');
    $pdo->exec('CREATE TABLE students(student_id integer PRIMARY KEY,first_name text DEFAULT $$Synthetic$$,last_name text DEFAULT $$Fixture$$); CREATE TABLE staff(staff_id integer PRIMARY KEY,profile text DEFAULT $$admin$$); CREATE TABLE students_join_users(student_id integer,staff_id integer); CREATE TABLE student_enrollment(id serial PRIMARY KEY,student_id integer,school_id integer,syear integer,grade_id integer DEFAULT 1,start_date date DEFAULT $$2020-01-01$$,end_date date); CREATE TABLE school_gradelevels(id integer,title text); CREATE TABLE school_periods(school_id integer,syear integer,start_time text,sort_order integer);');
    foreach (['020-school-cards.sql','021-entry-records.sql','022-pickup-guardians.sql','023-fee-consults.sql'] as $file) $pdo->exec(file_get_contents($root.'/schoolsafe-remediation/'.$file));
    $pdo->exec('CREATE TABLE schools(id integer,syear numeric(4,0),PRIMARY KEY(id,syear)); INSERT INTO schools VALUES(1,2026),(1,2025),(2,2026)');
    $nativeDdl=file_get_contents($root.'/rosariosis.sql');
    foreach(['billing_fees','billing_payments','program_config'] as $table) {
        if(preg_match('/CREATE TABLE '.$table.' \\([\\s\\S]*?\\n\\);/',$nativeDdl,$match)!==1)throw new RuntimeException('NATIVE_BILLING_DDL_MISSING');
        $pdo->exec($match[0]);
    }
    $pdo->exec("CREATE TABLE qa_barrier(hold boolean NOT NULL, arrived boolean NOT NULL DEFAULT false); INSERT INTO qa_barrier(hold) VALUES(true);
        INSERT INTO students(student_id) VALUES(11),(12); INSERT INTO staff VALUES(9),(18); INSERT INTO student_enrollment(student_id,school_id,syear) VALUES(11,1,2026),(12,2,2026); INSERT INTO school_gradelevels VALUES(1,'Synthetic class');
        INSERT INTO entry_exit_checkpoints(id,title) VALUES(7,'Fixture checkpoint');
        INSERT INTO schoolsafe_pickup_guardians(id,school_id,student_id,guardian_type,full_name,is_primary,is_authorized_pickup,photo_path,photo_accepted)
            VALUES(21,1,11,'mere','Synthetic fixture guardian',true,true,'/synthetic-only/portrait.jpg',true);");
    $pdo->exec("INSERT INTO entry_exit_student_records(student_id,checkpoint_id,type,comments,recorded_at) VALUES(11,7,'in','Synthetic legacy fixture',CURRENT_TIMESTAMP)");
    $before=$pdo->query('SELECT * FROM entry_exit_student_records')->fetch(PDO::FETCH_ASSOC);
    for ($i=0;$i<2;$i++) $pdo->exec(file_get_contents($root.'/schoolsafe-remediation/039-scan-passage-scope.sql'));
    $after=$pdo->query('SELECT * FROM entry_exit_student_records')->fetch(PDO::FETCH_ASSOC);
    if (array_intersect_key($after,$before)!==$before || $after['schoolsafe_scan_school_id']!==null || $after['schoolsafe_scan_syear']!==null) throw new RuntimeException('LEGACY_RECORD_CHANGED');
    $pdo->exec('UPDATE entry_exit_checkpoints SET schoolsafe_scan_school_id=1,schoolsafe_scan_syear=2026,schoolsafe_scan_enabled=true');
    $signature=substr(strtr(base64_encode(hash_hmac('sha256','QA-ENTRY',hex2bin(str_repeat('0',64)),true)),'+/','-_'),0,32);
    $statement=$pdo->prepare('INSERT INTO schoolsafe_student_cards(school_id,student_id,card_number,signature) VALUES(1,11,?,?)');$statement->execute(['QA-ENTRY',$signature]);
    $pdo->exec("INSERT INTO entry_exit_checkpoints(id,title,type,schoolsafe_scan_school_id,schoolsafe_scan_syear,schoolsafe_scan_enabled) VALUES
        (8,'Synthetic foreign point',NULL,2,2026,true),(9,'Synthetic prior year point',NULL,1,2025,true),
        (10,'Synthetic inactive point',NULL,1,2026,false),(11,'Synthetic entrance','in',1,2026,true),(12,'Synthetic exit','out',1,2026,true),
        (13,'Synthetic unassigned legacy point',NULL,NULL,NULL,false)");
    $pdo->query("SELECT setval(pg_get_serial_sequence('entry_exit_checkpoints','id'),(SELECT max(id) FROM entry_exit_checkpoints))");
    echo json_encode(['status'=>'SETUP','synthetic_only'=>true,'legacy_unchanged'=>true])."\n";exit;
}
if (!in_array($action,['worker','activate','family','entry','pickup','locations','location-manage','arrival-read','feed','parent-management','unlink-parent','fee-scan','fee-policy-manage'],true)) exit(2);
require_once $root.'/ProgramFunctions/SchoolSafePickupScan.fnc.php';
require_once $root.'/ProgramFunctions/SchoolSafePickupManage.fnc.php';
$barrier=getenv('SCHOOLSAFE_QA_PROOF_BARRIER')==='1';$barrierUsed=false;
$db=['get'=>static function($sql) use ($pdo,$connect,$barrier,&$barrierUsed) {
    $result=[];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $i=>$row) $result[$i+1]=array_change_key_case($row,CASE_UPPER);
    if ($barrier&&!$barrierUsed&&((strpos($sql,'FROM schoolsafe_pickup_proofs')!==false&&strpos($sql,'FOR UPDATE')!==false&&$result)
        ||(strpos($sql,'FROM program_config')!==false&&strpos($sql,'FOR SHARE')!==false)
        ||(strpos($sql,'FROM students_join_users')!==false&&strpos($sql,'FOR SHARE')!==false)
        ||strpos($sql,'SELECT SLOT_NO FROM schoolsafe_pickup_authorizations')!==false
        ||strpos($sql,'AND IS_PRIMARY AND IS_ACTIVE LIMIT 1')!==false)) {
        $barrierUsed=true;$watch=$connect();$watch->exec('UPDATE qa_barrier SET arrived=true');$deadline=microtime(true)+15;
        while (in_array($watch->query('SELECT hold FROM qa_barrier')->fetchColumn(),[true,'t',1,'1'],true)) {
            if (microtime(true)>$deadline) throw new RuntimeException('QA_BARRIER_TIMEOUT');
            usleep(20000);
        }
    }
    return $result;
}, 'exec'=>static function($sql) use ($pdo) {$pdo->exec($sql);}];
if ($action==='fee-policy-manage') {
    require_once $root.'/ProgramFunctions/SchoolSafeFeePolicy.fnc.php';
    $db['quote']=static function($value) use($pdo){return $pdo->quote($value);};
    $ctx=array_replace(['user'=>9,'school'=>1,'year'=>2026,'profile'=>'admin','can_edit_billing'=>true,'can_read'=>true,'can_manage'=>true],json_decode($argv[3]??'{}',true,16,JSON_THROW_ON_ERROR));
    try{$result=SchoolSafeFeePolicyManage(json_decode($argv[2],true,16,JSON_THROW_ON_ERROR),$ctx,$db);}
    catch(RuntimeException $error){$result=['error'=>$error->getMessage()];}
    if($pdo->inTransaction())throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='fee-scan') {
    require_once $root.'/ProgramFunctions/SchoolSafeFeeScan.fnc.php';
    $secret=str_repeat('0',64);$signature=substr(strtr(base64_encode(hash_hmac('sha256','QA-ENTRY',hex2bin($secret),true)),'+/','-_'),0,32);
    try{$result=SchoolSafeFeeScan('schoolsafe://card/QA-ENTRY/'.$signature,$secret,['user'=>9,'school'=>1,'year'=>2026,'detail'=>($argv[2]??'1')==='1'],$db,'2026-10-23');}
    catch(RuntimeException $error){$result=['error'=>$error->getMessage()];}
    if($pdo->inTransaction())throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='unlink-parent') {
    $pdo->exec('DELETE FROM students_join_users WHERE student_id=11 AND staff_id=27');
    echo json_encode(['unlinked'=>true])."\n";exit;
}
if ($action==='parent-management') {
    $input=json_decode($argv[2],true,16,JSON_THROW_ON_ERROR);
    $context=array_replace(['user'=>9,'school'=>1,'year'=>2026,'profile'=>'admin','parent'=>false],json_decode($argv[3]??'{}',true,16,JSON_THROW_ON_ERROR));
    $db['quote']=static function($value) use ($pdo) {return $pdo->quote($value);};
    try {
        if($input['action']==='bind')$result=SchoolSafePickupApproveFamily(11,21,'Synthetic reason','Synthetic consent',$context,$db,$input['parentStaffId']??null);
        elseif($input['action']==='external')$result=SchoolSafePickupRegisterExternal(11,$input,$context,$db);
        elseif($input['action']==='activate-staff')$result=SchoolSafePickupValidateAuthorization(11,$input['authorizationId'],$context,$db,'2026-10-23');
        elseif($input['action']==='suspend')$result=SchoolSafePickupParentSuspend(11,$input['authorizationId'],'Synthetic reason',$context,$db);
        else $result=SchoolSafePickupParentActivate(11,$input['authorizationId'],'Synthetic reason',$context,$db,'2026-10-23');
    } catch(RuntimeException $error) {$result=['error'=>$error->getMessage()];}
    if($pdo->inTransaction())throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='arrival-read') {
    require_once $root.'/schoolsafe-attendance/ArrivalRead.php';
    try {$result=SchoolSafeArrivalRead(['profile'=>'admin','staff'=>'9','school'=>'1','year'=>'2026'],['date'=>gmdate('Y-m-d')],$db['get']);}
    catch(RuntimeException $error) {$result=['error'=>$error->getMessage()];}
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='feed') {
    chdir($root);
    function DBGet($sql) {global $db;return $db['get']($sql);}
    function Config($key) {return 'N';} // unrelated optional sources disabled in this fixture
    require_once $root.'/ProgramFunctions/SchoolSafeNotificationFeed.fnc.php';
    $degraded=[];
    $items=SchoolSafeNotificationFeed([],[],explode(',',$argv[2]??'11'),(int)($argv[3]??27),1,2026,$degraded);
    echo json_encode(['items'=>$items,'degraded'=>$degraded],JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='location-manage') {
    require_once $root.'/ProgramFunctions/SchoolSafeScanLocationManage.fnc.php';
    $db['quote']=static function($value) use ($pdo) {return $pdo->quote($value);};
    $ctx=array_replace(['user'=>9,'school'=>1,'year'=>2026,'profile'=>'admin','can_configure'=>true,'can_read'=>true,'can_save'=>true],json_decode($argv[3]??'{}',true,16,JSON_THROW_ON_ERROR));
    try {$result=SchoolSafeScanLocationManage(json_decode($argv[2],true,16,JSON_THROW_ON_ERROR),$ctx,$db);}
    catch(RuntimeException $error) {$result=['error'=>$error->getMessage()];}
    if($pdo->inTransaction())throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if ($action==='locations') {
    require_once $root.'/ProgramFunctions/SchoolSafeScanPassage.fnc.php';
    $modes=($argv[2]??'')==='' ? [] : explode(',',$argv[2]);
    echo json_encode(['locations'=>SchoolSafeScanLocations(1,2026,$modes,$db)],JSON_THROW_ON_ERROR)."\n";exit;
}
if (in_array($action,['activate','family'],true)) {
    $context=['user'=>9,'school'=>(int)($argv[4]??1),'year'=>(int)($argv[3]??2026),'profile'=>'admin','parent'=>false];
    try {
        $result=$action==='activate'
            ? SchoolSafePickupValidateAuthorization(11,(int)($argv[2]??0),$context,$db,'2026-10-23')
            : SchoolSafePickupRegisterFamily(11,['guardianType'=>'pere','fullName'=>'Synthetic family fixture','isPrimary'=>false],$context,$db);
    } catch (RuntimeException $error) { $result=['error'=>$error->getMessage()]; }
    if ($pdo->inTransaction()) throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
if (in_array($action,['entry','pickup'],true)) {
    require_once $root.'/ProgramFunctions/SchoolSafeEntryScan.fnc.php';
    $secret=str_repeat('0',64);$signature=substr(strtr(base64_encode(hash_hmac('sha256','QA-ENTRY',hex2bin($secret),true)),'+/','-_'),0,32);
    $qr='schoolsafe://card/QA-ENTRY/'.$signature;$context=['user'=>9,'school'=>1,'year'=>2026];$now=strtotime('2026-10-23 16:30:00 UTC');
    try{$result=$action==='entry' ? SchoolSafeEntryScan($qr,'7',$secret,$context,$db,$now)
        : SchoolSafePickupScan($qr,'7',$secret,$context,$db,($argv[2]??'prepare')==='prepare'
            ? ['authorized_person_id'=>'g:21'] : ['proof_token'=>$argv[2],'human_confirmed'=>true],$now);}
    catch(RuntimeException $error){$result=['error'=>$error->getMessage()];}
    if ($pdo->inTransaction()) throw new RuntimeException('TRANSACTION_LEFT_OPEN');
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";exit;
}
$token=$argv[2]??'';$user=(int)($argv[3]??9);$school=(int)($argv[4]??1);$student=(int)($argv[5]??11);
try {
    $result=SchoolSafePickupConfirm($token,$school,$student,7,$user,'2026-10-23','16:30:00',$db,
        ['result'=>'VALID','signature_valid'=>true,'student_id'=>$student],['id'=>'7','label'=>'Fixture checkpoint'],2026);
} catch (RuntimeException $error) { $result=['error'=>$error->getMessage()]; }
if ($pdo->inTransaction()) throw new RuntimeException('TRANSACTION_LEFT_OPEN');
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
