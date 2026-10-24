<?php
/** Diagnostic worker. Only a NEW, explicitly labelled local synthetic DB. No HTTP or providers. */
if (getenv('SCHOOLSAFE_STABILITY_FIXTURE') !== 'isolated-school-state'
    || getenv('POSTGRES_DB') !== 'schoolcef_stability_fixture'
    || !preg_match('/^prodeli-stability-db-[a-f0-9]{10}$/D', getenv('SCHOOLSAFE_DB_HOST') ?: '')) {
    fwrite(STDERR, "Refused: isolated fixture marker/name/host required.\n"); exit(2);
}
$mode = $argv[1] ?? '';
if (!in_array($mode, ['setup', 'clean', 'audit-failure', 'invalid-date'], true)) exit(2);
$root = __DIR__ . '/../app/modules/codex-ui-v1/extensions-test-runtime';
date_default_timezone_set('UTC'); // Fixture determinism, not a production setting.
$DatabaseType = 'postgresql';
$db_connection = pg_connect('host=' . getenv('SCHOOLSAFE_DB_HOST')
    . ' dbname=schoolcef_stability_fixture user=schoolcef_fixture password=' . getenv('POSTGRES_PASSWORD'));
if (!$db_connection) exit(3);
// Identity/hooks only are substituted; SQL execution, filtering, error handling and reads are native.
function User($key) { return $key === 'USERNAME' ? 'synthetic.audit.actor' : 'teacher'; }
function do_action(...$args) {}
require $root . '/database.inc.php';
require $root . '/functions/DBGet.fnc.php';
require $root . '/ProgramFunctions/SchoolSafeSchoolState.fnc.php';
if ($mode === 'setup') {
    if (DBGetOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public'") !== '0') exit(4);
    DBQuery(file_get_contents($root . '/schoolsafe-remediation/016-school-state.sql'));
    DBQuery("INSERT INTO schoolsafe_school_state (school_id,state) VALUES (999001,'active'),(999002,'active'),(999003,'active')");
    DBQuery('CREATE FUNCTION fixture_fail_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN '
        . "IF NEW.school_id=999002 THEN RAISE EXCEPTION 'SYNTHETIC_AUDIT_FAILURE'; END IF; RETURN NEW; END $$");
    DBQuery('CREATE TRIGGER fixture_fail_audit BEFORE INSERT ON schoolsafe_school_audit FOR EACH ROW EXECUTE FUNCTION fixture_fail_audit()');
    echo json_encode(['setup'=>'READY','canonical_migration'=>'016-school-state.sql']) . "\n";
    exit;
}
$id = ['clean'=>999001,'audit-failure'=>999002,'invalid-date'=>999003][$mode];
$until = $mode === 'invalid-date' ? '2026-99-99' : null;
$returned = SchoolSafeSchoolStateSet($id, 'suspended', 'SYNTHETIC_ONLY', $until);
echo json_encode(['mode'=>$mode,'returned'=>$returned,'effective'=>SchoolSafeSchoolStateEffective($id),
    'invalid_date_passes_console_regex'=>$until === null ? null : (bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $until)]) . "\n";
