<?php
/** Diagnostic only; synthetic disposable PostgreSQL, no real account/session. */
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_STABILITY_FIXTURE')!=='isolated-account-state'
 ||getenv('POSTGRES_DB')!=='schoolcef_stability_fixture'
 ||!preg_match('/^prodeli-stability-db-[a-f0-9]{10}$/D',getenv('SCHOOLSAFE_DB_HOST')?:''))exit(2);
$root=__DIR__.'/../app/modules/codex-ui-v1/extensions-test-runtime';$DatabaseType='postgresql';
$db_connection=pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_stability_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'));
if(!$db_connection)exit(3);
function User($key){return $key==='USERNAME'?'fixture.operator':'teacher';}
function do_action(...$args){}
require $root.'/database.inc.php';require $root.'/functions/DBGet.fnc.php';require $root.'/ProgramFunctions/SchoolSafeAccountState.fnc.php';
DBQuery(file_get_contents($root.'/schoolsafe-remediation/014-account-state.sql'));
DBQuery("INSERT INTO schoolsafe_account_state (person_key,school_id,state,session_epoch) VALUES ('fixture.person',3,'active',5)");
$mode=$argv[1]??'';
if($mode!=='clean'){
 DBQuery('CREATE FUNCTION fixture_account_audit_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION \'SYNTHETIC_ACCOUNT_AUDIT_FAILURE\'; END $$');
 DBQuery('CREATE TRIGGER fixture_account_audit_fail BEFORE INSERT ON schoolsafe_account_audit FOR EACH ROW EXECUTE FUNCTION fixture_account_audit_fail()');
}
if($mode==='set')$result=SchoolSafeAccountSet('fixture.person',3,'suspended','synthetic',null);
elseif($mode==='revoke')$result=SchoolSafeAccountRevoke('fixture.person',3);
elseif($mode==='invalid'){$result=SchoolSafeAccountSet('fixture.person',3,'suspended','synthetic','2026-99-99');}
elseif($mode==='clean')$result=SchoolSafeAccountRevoke('fixture.person',3);
else exit(2);
// Native DBQuery may terminate before this line: the runner queries state separately.
echo json_encode(['returned'=>$result])."\n";
