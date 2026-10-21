<?php
/** CLI only. Isolated schema on the explicitly labelled local fixture database.
 * Run in schoolsafe-prodeli-presentation-qa; never in production.
 * Uses real PostgreSQL and Rosario DBInsertSQL; auth/controller are NOT tested.
 */
if (PHP_SAPI !== 'cli' || getenv('POSTGRES_DB') !== 'schoolcef_fixture'
    || getenv('SCHOOLSAFE_DB_HOST') !== 'schoolcef-harmonization-pg') exit(2);
$root = '/candidate';
$conn = pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'));
if (!$conn) exit(2);
$GLOBALS['db_connection'] = $conn;
$GLOBALS['DatabaseType'] = 'postgresql';
// Only application hooks/auth are substituted; use actual Rosario DB functions.
function do_action($tag,$args=[]) {}
require $root.'/database.inc.php';
require $root.'/functions/DBGet.fnc.php';
// An unhandled legacy pg_exec warning must fail the test without printing SQL
// (or skipping finally/schema cleanup through Rosario's die() error page).
set_error_handler(static function ($severity) {
    if ($severity === E_WARNING && (error_reporting() & $severity)) {
        throw new RuntimeException('Unhandled PostgreSQL warning in fixture');
    }
    return false;
});
function encrypt_password($p) {return password_hash($p, PASSWORD_BCRYPT);}
function User($key) {return $key==='STAFF_ID' ? 7 : '';}
require $root.'/functions/DBUpsert.php';
require (getenv('PRODELI_SETUP_UNDER_TEST') ?: $root.'/ProgramFunctions/SchoolSafeSchoolSetup.fnc.php');
$schema='qa_director_'.bin2hex(random_bytes(6));
// Two independent processes exercise the same SQL boundary, not mocked locks.
if (($argv[1]??'')==='worker') {
    $schema=$argv[2]??'';
    if (!preg_match('/^qa_director_[a-f0-9]{12}$/D',$schema)) exit(2);
    DBQuery('SET search_path TO '.$schema);
    echo "READY\n";flush();
    $result=createDirector($argv[4]??'qa.concurrent',['school_id'=>(int)$argv[3]]);
    echo json_encode(['ok'=>$result['ok'],'error'=>$result['error']]);
    exit;
}
$failures=0;$checks=0;
function check($ok,$name) {global $failures,$checks; $checks++;if(!$ok)$failures++;echo ($ok?'PASS ':'FAIL ').$name."\n";}
function createDirector($username,$extra=[]) {
    return SchoolSafeCreateDirector(array_replace(['first_name'=>'Anne','last_name'=>"D'Essai",'username'=>$username,
        'email'=>'qa@example.invalid','school_id'=>1,'principal'=>true],$extra),2026,'qa');
}
try {
    DBQuery('CREATE SCHEMA '.$schema);DBQuery('SET search_path TO '.$schema);
    DBQuery("CREATE TABLE schools(id integer,syear integer,PRIMARY KEY(id,syear)); INSERT INTO schools VALUES(1,2026),(2,2026),(3,2026),(4,2026),(5,2026);
        CREATE TABLE user_profiles(id integer, title text,profile text); INSERT INTO user_profiles VALUES(2,'Directeur','admin');
        CREATE TABLE staff(staff_id serial,syear integer,first_name varchar(50),last_name varchar(50),username varchar(100),password text,email text,profile text,profile_id integer,schools text,current_school_id integer,UNIQUE(username,syear));
        CREATE TABLE students(username text);
        CREATE TABLE schoolsafe_identity_audit(actor_staff_id integer,target_staff_id integer,action text,result text);");
    // Use the actual additive migration twice; never touch public tables.
    $migration=file_get_contents($root.'/schoolsafe-remediation/018-school-leadership.sql');
    DBQuery($migration);DBQuery($migration);
    check((int)DBGetOne('SELECT count(*) FROM schools')===5,'018 replay preserves schools');
    $first=createDirector('qa.first');
    check(!empty($first['ok']) && $first['role']==='principal','native principal created');
    $id=(int)($first['staff_id']??0);
    check($id>0 && (int)DBGetOne('SELECT staff_id FROM schoolsafe_school_leadership WHERE school_id=1 AND role=\'principal\'')===$id,'principal attached');
    check(DBGetOne('SELECT last_name FROM staff WHERE staff_id='.$id)==="D'Essai",'apostrophe stored verbatim');
    check(password_verify($first['temp_password']??'',(string)DBGetOne('SELECT password FROM staff WHERE staff_id='.$id)),'temporary password hashed');
    check((int)DBGetOne("SELECT count(*) FROM schoolsafe_identity_audit WHERE action='create' AND result='success'")===1,'creation audit committed');
    $second=createDirector('qa.second');
    check(empty($second['ok']) && ($second['error']??'')==='PRINCIPAL_EXISTS','existing principal not replaced');
    check((int)DBGetOne("SELECT count(*) FROM staff WHERE username='qa.second'")===0,'refusal leaves no account');
    check($id>0 && (int)DBGetOne("SELECT staff_id FROM schoolsafe_school_leadership WHERE school_id=1 AND role='principal'")===$id,'original principal unchanged');
    $duplicate=createDirector('QA.FIRST',['school_id'=>2]);
    check(empty($duplicate['ok']) && ($duplicate['error']??'')==='USERNAME_TAKEN','case-insensitive duplicate refused');
    $adjoint=createDirector('qa.adjoint',['principal'=>false]);
    check(!empty($adjoint['ok']) && $adjoint['role']==='adjoint','explicit deputy preserves principal');
    DBQuery("ALTER TABLE schoolsafe_identity_audit ADD CONSTRAINT reject_create CHECK(action<>'create') NOT VALID");
    $failed=createDirector('qa.audit.failure',['school_id'=>2]);
    check(empty($failed['ok']) && !isset($failed['temp_password']),'audit failure releases no secret');
    check((int)DBGetOne("SELECT count(*) FROM staff WHERE username='qa.audit.failure'")===0,'audit failure rolls account back');
    check((int)DBGetOne('SELECT count(*) FROM schoolsafe_school_leadership WHERE school_id=2')===0,'audit failure rolls leadership back');
    DBQuery('ALTER TABLE schoolsafe_identity_audit DROP CONSTRAINT reject_create');
    DBQuery("ALTER TABLE schoolsafe_school_leadership ADD CONSTRAINT reject_school CHECK(school_id<>2) NOT VALID");
    $failed=createDirector('qa.leadership.failure',['school_id'=>2]);
    check(empty($failed['ok']) && (int)DBGetOne("SELECT count(*) FROM staff WHERE username='qa.leadership.failure'")===0,'leadership failure rolls account back');
    DBQuery('ALTER TABLE schoolsafe_school_leadership DROP CONSTRAINT reject_school');
    $recovery=createDirector('qa.retry',['school_id'=>2]);
    check(!empty($recovery['ok']) && $recovery['role']==='principal','retry after database failure succeeds');
    DBQuery('BEGIN');
    $nested=createDirector('qa.nested',['principal'=>false]);
    check(empty($nested['ok']) && pg_transaction_status($conn)===PGSQL_TRANSACTION_INTRANS,'caller transaction never committed implicitly');
    DBQuery('ROLLBACK');
    DBQuery("INSERT INTO students(username) VALUES('qa.student')");
    $student=createDirector('QA.STUDENT',['school_id'=>3]);
    check(empty($student['ok']) && $student['error']==='USERNAME_TAKEN','student username collision refused');
    $unknown=createDirector('qa.unknown',['school_id'=>999]);
    check(empty($unknown['ok']) && $unknown['error']==='SCHOOL_NOT_FOUND','unknown school refused');
    DBQuery("UPDATE user_profiles SET profile='teacher'");
    $profile=createDirector('qa.profile',['school_id'=>3]);
    check(empty($profile['ok']) && $profile['error']==='DIRECTOR_PROFILE_MISSING','non-admin Director profile refused');
    DBQuery("UPDATE user_profiles SET profile='admin'");
    DBQuery('ALTER TABLE schoolsafe_school_leadership RENAME TO leadership_unavailable');
    $missing=createDirector('qa.missing',['school_id'=>3]);
    check(empty($missing['ok']) && (int)DBGetOne("SELECT count(*) FROM staff WHERE username='qa.missing'")===0,'missing migration fails closed');
    DBQuery('ALTER TABLE leadership_unavailable RENAME TO schoolsafe_school_leadership');
    foreach ([ [[3,'qa.concurrent'],[4,'qa.concurrent'],'USERNAME_TAKEN'],
        [[5,'qa.principal.a'],[5,'qa.principal.b'],'PRINCIPAL_EXISTS'] ] as [$left,$right,$expected]) {
        DBQuery('BEGIN');
        DBQuery('LOCK TABLE staff IN SHARE ROW EXCLUSIVE MODE');
        $workers=[];
        foreach ([$left,$right] as [$school,$username]) {
            $pipes=[];
            $process=proc_open([PHP_BINARY,__FILE__,'worker',$schema,(string)$school,$username],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency worker');
            fclose($pipes[0]);
            if (trim(fgets($pipes[1]))!=='READY') throw new RuntimeException('Worker not ready');
            $workers[]=[$process,$pipes];
        }
        DBQuery('COMMIT');
        $outcomes=[];
        foreach ($workers as [$process,$pipes]) {
            $outcomes[]=json_decode(stream_get_contents($pipes[1]),true,512,JSON_THROW_ON_ERROR);
            fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
            check(proc_close($process)===0 && $stderr==='','concurrency worker completes cleanly');
        }
        check(count(array_filter($outcomes,static fn($r)=>$r['ok']))===1
            && count(array_filter($outcomes,static fn($r)=>$r['error']===$expected))===1,
            'concurrent conflict: one creation, one '.$expected);
    }
    check((int)DBGetOne("SELECT count(*) FROM staff WHERE LOWER(username)='qa.concurrent'")===1,
        'concurrent duplicate has one account');
    check((int)DBGetOne("SELECT count(*) FROM staff WHERE username IN ('qa.principal.a','qa.principal.b')")===1
        && (int)DBGetOne("SELECT count(*) FROM schoolsafe_school_leadership WHERE school_id=5 AND role='principal'")===1,
        'concurrent principals have one account and one principal');
} finally {
    @pg_query($conn,'ROLLBACK');
    DBQuery('SET search_path TO public');DBQuery('DROP SCHEMA '.$schema.' CASCADE');
}
echo "$checks checks, $failures failures; isolated schema removed\n";
exit($failures ? 1 : 0);
