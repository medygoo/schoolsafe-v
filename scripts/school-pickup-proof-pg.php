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
    $pdo->exec('CREATE TABLE students(student_id integer PRIMARY KEY); CREATE TABLE staff(staff_id integer PRIMARY KEY); CREATE TABLE students_join_users(student_id integer,staff_id integer); CREATE TABLE student_enrollment(student_id integer,school_id integer,syear integer);');
    foreach (['021-entry-records.sql','022-pickup-guardians.sql'] as $file) $pdo->exec(file_get_contents($root.'/schoolsafe-remediation/'.$file));
    $pdo->exec("CREATE TABLE qa_barrier(hold boolean NOT NULL, arrived boolean NOT NULL DEFAULT false); INSERT INTO qa_barrier(hold) VALUES(true);
        INSERT INTO students VALUES(11),(12); INSERT INTO staff VALUES(9),(18); INSERT INTO student_enrollment VALUES(11,1,2026),(12,2,2026);
        INSERT INTO entry_exit_checkpoints(id,title) VALUES(7,'Fixture checkpoint');
        INSERT INTO schoolsafe_pickup_guardians(id,school_id,student_id,guardian_type,full_name,is_primary,is_authorized_pickup,photo_path,photo_accepted)
            VALUES(21,1,11,'mere','Synthetic fixture guardian',true,true,'/synthetic-only/portrait.jpg',true);");
    echo json_encode(['status'=>'SETUP','synthetic_only'=>true])."\n";exit;
}
if (!in_array($action,['worker','activate','family'],true)) exit(2);
require_once $root.'/ProgramFunctions/SchoolSafePickupScan.fnc.php';
require_once $root.'/ProgramFunctions/SchoolSafePickupManage.fnc.php';
$barrier=getenv('SCHOOLSAFE_QA_PROOF_BARRIER')==='1';$barrierUsed=false;
$db=['get'=>static function($sql) use ($pdo,$connect,$barrier,&$barrierUsed) {
    $result=[];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $i=>$row) $result[$i+1]=array_change_key_case($row,CASE_UPPER);
    if ($barrier&&!$barrierUsed&&((strpos($sql,'FROM schoolsafe_pickup_proofs')!==false&&strpos($sql,'FOR UPDATE')!==false&&$result)
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
$token=$argv[2]??'';$user=(int)($argv[3]??9);$school=(int)($argv[4]??1);$student=(int)($argv[5]??11);
$result=SchoolSafePickupConfirm($token,$school,$student,7,$user,'2026-10-23','16:30:00',$db,
    ['result'=>'VALID','signature_valid'=>true,'student_id'=>$student],['id'=>'7','label'=>'Fixture checkpoint']);
if ($pdo->inTransaction()) throw new RuntimeException('TRANSACTION_LEFT_OPEN');
echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
