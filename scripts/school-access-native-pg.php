<?php
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_ACCESS_FIXTURE')!=='isolated-native-access'||getenv('POSTGRES_DB')!=='schoolcef_fixture'||!preg_match('/^access-proof-db-[a-f0-9]{10}$/D',getenv('SCHOOLSAFE_DB_HOST')?:''))exit(2);
$root='/repo/app/modules/codex-ui-v1/extensions-test-runtime';
$GLOBALS['db_connection']=pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'));
$GLOBALS['DatabaseType']='postgresql';
function do_action($tag,$args=[]){}
require $root.'/database.inc.php';require $root.'/functions/DBGet.fnc.php';
function User($key){return $GLOBALS['actor'][$key]??null;}
function UserSyear(){return 2026;}
function SchoolSafeSchoolBlocked($school){return !empty($GLOBALS['blocked']);}
function SchoolSafeAccountBlocksWrite(){return !empty($GLOBALS['readonly']);}
function SchoolSafeAccountPerson($name){return mb_strtolower(trim($name));}
function SchoolSafeAccountReady(){return $GLOBALS['account_schema_ready']??true;}
function SchoolSafeSchoolStateReady(){return $GLOBALS['school_schema_ready']??true;}
function SchoolSafeAccountStateForSchool($username,$school){return $GLOBALS['states'][$username]??'active';}
require $root.'/ProgramFunctions/SchoolSafeSchoolScope.fnc.php';
require $root.'/modules/SchoolSafe/Permissions.php';
require $root.'/ProgramFunctions/SchoolSafeSchoolAccessView.fnc.php';
$GLOBALS['RosarioModules']=['SchoolSafe'=>true];
function q($sql){$r=pg_query($GLOBALS['db_connection'],$sql);if($r===false)throw new RuntimeException('Fixture SQL failed');return $r;}
function one($sql){$rows=DBGet(q($sql));return $rows[1]?reset($rows[1]):null;}
function actor($id){$GLOBALS['actor']=SchoolSafeAccessRows('SELECT * FROM staff WHERE staff_id=$1',[$id])[1];}
function snap($id){$row=SchoolSafeAccessRows('SELECT * FROM staff WHERE staff_id=$1',[$id])[1];return SchoolSafeAccessVersion($row,SchoolSafeAccessRights($row));}
function assign($id,$selection,$bootstrap=false,$version=null,$school=1){return SchoolSafeAccessAssign($school,$id,$selection,$version??snap($id),$bootstrap);}
$n=0;$failed=0;function check($ok,$name){global $n,$failed;$n++;if(!$ok)$failed++;echo ($ok?'PASS ':'FAIL ').$name."\n";}
if(($argv[1]??'')==='worker'){actor((int)$argv[2]);echo json_encode(assign((int)$argv[3],json_decode($argv[4],true),false,$argv[5]));exit;}
function competeAccess($jobs){
 $lock=pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'),PGSQL_CONNECT_FORCE_NEW);
 pg_query($lock,'BEGIN');pg_query($lock,'SELECT id FROM schools WHERE id=1 AND syear=2026 FOR UPDATE');$workers=[];
 try{
  foreach($jobs as$j){$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'worker',(string)$j[0],(string)$j[1],json_encode($j[2]),$j[3]],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$p,$pipes];}
  $deadline=microtime(true)+8;
  while((int)one("SELECT count(*) FROM pg_stat_activity WHERE wait_event_type='Lock' AND datname='schoolcef_fixture'")<count($jobs)){
   if(microtime(true)>$deadline)throw new RuntimeException('Concurrent workers did not reach native school lock');usleep(20000);
  }
  pg_query($lock,'COMMIT');$answers=[];
  foreach($workers as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Fixture worker failed');$answers[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
  return $answers;
 }finally{@pg_query($lock,'ROLLBACK');pg_close($lock);}
}
q("CREATE TABLE schools(id integer,syear integer,PRIMARY KEY(id,syear));INSERT INTO schools VALUES(1,2026),(2,2026);
CREATE TABLE user_profiles(id serial PRIMARY KEY,title text,profile text,school_id integer);
INSERT INTO user_profiles VALUES(1,'Platform','admin',NULL),(7,'School admin template','admin',NULL),(30,'Shared teacher','teacher',NULL);
SELECT setval(pg_get_serial_sequence('user_profiles','id'),100);
CREATE TABLE profile_exceptions(profile_id integer,modname text,can_use text,can_edit text,PRIMARY KEY(profile_id,modname));
CREATE TABLE staff(staff_id integer,syear integer,username text,profile text,profile_id integer,schools text,PRIMARY KEY(staff_id,syear));
CREATE TABLE staff_exceptions(user_id integer,modname text,can_use text,can_edit text);
CREATE TABLE schoolsafe_account_audit(id serial,person_key text,school_id integer,action varchar(32),reason text,actor text,created_at timestamp DEFAULT CURRENT_TIMESTAMP);
INSERT INTO staff VALUES(1,2026,'platform','admin',1,NULL),(2,2026,'director','admin',7,',1,'),(3,2026,'a','teacher',30,',1,'),(4,2026,'b','teacher',30,',1,'),(5,2026,'c','teacher',30,',1,'),(6,2026,'d','teacher',30,',1,'),(7,2026,'foreign','teacher',30,',2,'),(8,2026,'other.director','admin',7,',1,');
INSERT INTO profile_exceptions VALUES(7,'Students/Student.php','Y','Y'),(30,'Students/Student.php','Y','');");
$all=[];foreach(SchoolSafeAccessCatalog() as$key=>$def){if(in_array('admin',$def['profiles'],true))$all[$key]=['use'=>'Y','edit'=>'Y'];}
$scans=[];foreach(['ScanArrival','ScanPickup','ScanFees','ScanCanteen','ScanVerify'] as$k)$scans['SchoolSafe/'.$k.'.php']=['use'=>'Y','edit'=>'Y'];
actor(1);
check(assign(2,$all)['status']==='refused','platform cannot silently use school delegation mode');
$r=assign(2,$all,true);check($r['status']==='saved','platform explicitly bootstraps existing school administrator');
check(one('SELECT count(*) FROM staff')==='8','bootstrap creates no identity');
check(one('SELECT count(*) FROM profile_exceptions WHERE profile_id=7')==='1','shared global template untouched');
check(one('SELECT profile_id FROM staff WHERE staff_id=8')==='7','other template holder untouched');
check(assign(7,$scans,true)['status']==='refused','bootstrap requires school administrator, not teacher');
check(assign(2,$all,true)['status']==='unchanged','authorized identical bootstrap is a no-op');
actor(2);
$directorVersion=snap(2);
for($i=3;$i<=6;$i++)check(assign($i,$scans)['status']==='saved','all five Scan tasks given independently to recipient '.$i);
check(one("SELECT count(*) FROM staff s JOIN profile_exceptions e ON e.profile_id=s.profile_id WHERE s.staff_id IN(3,4,5,6) AND e.modname='SchoolSafe/ScanArrival.php' AND can_use='Y'")==='4','no business recipient quota or transfer');
check(one('SELECT count(*) FROM profile_exceptions WHERE profile_id=30')==='1','shared teacher profile not modified');
$r=assign(3,[]);check($r['status']==='saved','native individual revocation via separate owned profile');
check(one("SELECT count(*) FROM staff s JOIN profile_exceptions e ON e.profile_id=s.profile_id WHERE s.staff_id IN(4,5,6) AND e.modname='SchoolSafe/ScanArrival.php'")==='3','revocation preserves all other recipients');
check(one("SELECT count(*) FROM staff s JOIN profile_exceptions e ON e.profile_id=s.profile_id WHERE s.staff_id=3 AND e.modname='Students/Student.php'")==='1','unrelated native right preserved on revocation');
check(assign(7,$scans)['status']==='refused','foreign school target refused');
check(assign(2,$all)['status']==='refused','self modification refused');
$GLOBALS['RosarioModules']['SchoolSafe']=false;check(assign(4,$scans)['status']==='refused','inactive module never creates school management authority');$GLOBALS['RosarioModules']['SchoolSafe']=true;
$p4=(int)one('SELECT profile_id FROM staff WHERE staff_id=4');$p4v=SchoolSafeAccessProfileVersion($p4);
$GLOBALS['account_schema_ready']=false;check(assign(4,$scans)['status']==='refused','missing native account-state schema cannot authorize a grant or replay');
try{SchoolSafeAccessProfileBegin(1,$p4,$p4v);q('ROLLBACK');$ok=false;}catch(Throwable $e){$ok=true;}check($ok,'shared editor refuses missing account-state schema');unset($GLOBALS['account_schema_ready']);
$GLOBALS['school_schema_ready']=false;check(assign(4,$scans)['status']==='refused','missing native school-state schema cannot authorize a grant or replay');
try{SchoolSafeAccessProfileBegin(1,$p4,$p4v);q('ROLLBACK');$ok=false;}catch(Throwable $e){$ok=true;}check($ok,'shared editor refuses missing school-state schema');unset($GLOBALS['school_schema_ready']);
$GLOBALS['states']['b']='unknown';check(assign(4,$scans)['status']==='refused','unknown target state cannot accept an assignment');unset($GLOBALS['states']['b']);
$GLOBALS['states']['director']='unknown';check(assign(4,$scans)['status']==='refused','unknown current actor state fails closed');unset($GLOBALS['states']['director']);
$oldScope=$GLOBALS['actor']['SCHOOLS'];$GLOBALS['actor']['SCHOOLS']=',1,2,';check(assign(4,$scans)['status']==='refused','stale session scope cannot override live staff ownership');$GLOBALS['actor']['SCHOOLS']=$oldScope;
q("INSERT INTO staff VALUES(9,2026,'zero','teacher',0,',1,'),(10,2026,'custom','teacher',NULL,',1,');INSERT INTO profile_exceptions VALUES(0,'Students/Student.php','Y','');INSERT INTO staff_exceptions VALUES(9,'Users/Profiles.php','Y','Y'),(10,'Students/Student.php','Y','');");
check(assign(9,['SchoolSafe/ScanVerify.php'=>['use'=>'Y']])['status']==='saved','named profile zero is resolved, never individual override');
check(one("SELECT count(*) FROM staff s JOIN profile_exceptions e ON e.profile_id=s.profile_id WHERE s.staff_id=9 AND e.modname='Users/Profiles.php'")==='0','ignored individual reserved exception never promoted into named profile');
check(assign(10,$scans)['status']==='saved','genuine native custom account may explicitly receive a named school version');
$old=snap(4);check(assign(4,[])['status']==='saved','first actor updates target');
check(assign(4,$scans,false,$old)['status']==='refused','stale form cannot restore removed rights');
$GLOBALS['readonly']=true;check(assign(4,$scans)['status']==='refused','readonly current actor cannot delegate');unset($GLOBALS['readonly']);
$GLOBALS['states']['director']='suspended';check(assign(4,$scans)['status']==='refused','suspension wins');unset($GLOBALS['states']['director']);
$GLOBALS['blocked']=true;check(assign(4,$scans)['status']==='refused','blocked school fails closed');unset($GLOBALS['blocked']);
check(assign(4,['Users/Profiles.php'=>['use'=>'Y']])['status']==='refused','reserved platform right refused');
$before=(int)one('SELECT count(*) FROM user_profiles');$v=snap(4);
q("CREATE FUNCTION deny_access_audit() RETURNS trigger LANGUAGE plpgsql AS \$\$BEGIN RAISE EXCEPTION 'synthetic audit failure';END\$\$;CREATE TRIGGER deny_audit BEFORE INSERT ON schoolsafe_account_audit FOR EACH ROW EXECUTE FUNCTION deny_access_audit();");
check(assign(4,$scans)['status']==='refused','audit failure refuses business change');
check(snap(4)===$v&&(int)one('SELECT count(*) FROM user_profiles')===$before,'audit failure rolls profile, flags and assignment back');
q('DROP TRIGGER deny_audit ON schoolsafe_account_audit');
$countBefore=(int)one('SELECT count(*) FROM schoolsafe_account_audit');$v=snap(4);
$race=competeAccess([[2,4,$scans,$v],[2,4,$scans,$v]]);$statuses=array_column($race,'status');sort($statuses);
check($statuses===['refused','saved'],'two concurrent stale forms yield one write and one refused stale version');
check((int)one('SELECT count(*) FROM schoolsafe_account_audit')===$countBefore+1,'concurrent submission commits one native audit only');
$profile5=(int)one('SELECT profile_id FROM staff WHERE staff_id=5');
$pv=SchoolSafeAccessProfileVersion($profile5);
try{SchoolSafeAccessProfileBegin(1,$profile5,str_repeat('0',64));$ok=false;}catch(Throwable $e){$ok=true;}
check($ok,'shared editor rejects stale rights or holder fingerprint');
$context=SchoolSafeAccessProfileBegin(1,$profile5,$pv);
q("UPDATE profile_exceptions SET can_edit='' WHERE profile_id=$profile5 AND modname='SchoolSafe/ScanArrival.php'");
SchoolSafeAccessProfileFinish($context,$profile5,'droits');
check(one("SELECT count(*) FROM schoolsafe_account_audit WHERE action='profile_droits'")==='1','existing profile controller now has mandatory transactional native audit');
try{SchoolSafeAccessProfileBegin(1,(int)one('SELECT profile_id FROM staff WHERE staff_id=2'),SchoolSafeAccessProfileVersion((int)one('SELECT profile_id FROM staff WHERE staff_id=2')));$ok=false;}catch(Throwable $e){$ok=true;}
check($ok,'shared editor protects own held profile and remaining administrator');
$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['token'=>'synthetic-token'];$_POST=['token'=>'wrong'];
check(SchoolSafeAccessSubmit(1,false,'token')['status']==='refused','controller rejects CSRF before any access write');
$_POST=['token'=>'synthetic-token','access_present'=>'1','access_staff'=>'4','access_version'=>snap(4)];
check(SchoolSafeAccessSubmit(1,false,'token')['status']==='refused','controller requires explicit confirmation');
$_SESSION=['token'=>''];$_POST['token']='';$_POST['access_confirm']='Y';
check(SchoolSafeAccessSubmit(1,false,'token')['status']==='refused','empty CSRF never accepted');
$_SESSION=['token'=>'synthetic-token'];
ob_start();SchoolSafeAccessRender(1,2026,4,false,'/ecoles/1/profils','token');$html=ob_get_clean();
echo 'RENDER:'.base64_encode($html)."\n";
check(str_contains($html,'data-access-all')&&str_contains($html,'access_confirm')&&str_contains($html,'access_version'),'native school view presents full selection, confirmation and stale-form guard');
check(SchoolSafeProfileDelegable((string)$profile5,'teacher'),'school-owned lower flag set remains natively delegable');
$own=(int)one('SELECT profile_id FROM staff WHERE staff_id=2');
check(SchoolSafeProfileDelegable((string)$own,'admin'),'complete school-owned equal profile is natively delegable without artificial missing right');
check(!SchoolSafeProfileStrictlyWithin($own,$own),'global strict comparison default is unchanged');
$profile=(int)one('SELECT profile_id FROM staff WHERE staff_id=2');
q("DELETE FROM profile_exceptions WHERE profile_id=$profile AND modname='SchoolSafe/DelegationManage.php'");
check(assign(4,$scans)['status']==='refused','withdrawn management authority enforced in existing session');
actor(1);check(assign(2,$all,true)['status']==='saved'&&assign(8,$all,true)['status']==='saved','central explicit choices may establish multiple school access managers');
$race=competeAccess([[2,8,[],snap(8)],[8,2,[],snap(2)]]);$statuses=array_column($race,'status');sort($statuses);
check($statuses===['refused','saved'],'mutual concurrent manager revocations preserve live authority ceiling');
check(one("SELECT count(*) FROM staff s JOIN profile_exceptions e ON e.profile_id=s.profile_id WHERE s.staff_id IN(2,8) AND e.modname='SchoolSafe/DelegationManage.php' AND can_use='Y' AND can_edit='Y'")==='1','last effective access manager survives concurrent mutual revocation');
check(one("SELECT count(*) FROM schoolsafe_account_audit WHERE reason::jsonb->'before' IS NOT NULL AND reason::jsonb->'after' IS NOT NULL")===one('SELECT count(*) FROM schoolsafe_account_audit'),'native audit includes before and after plus actor target school');
actor(1);check(!SchoolSafeCentralRequestAllowed('/scanner-native.php',[]),'platform never gets school scanner');
echo json_encode(['checks'=>$n,'failed'=>$failed])."\n";exit($failed?1:0);
