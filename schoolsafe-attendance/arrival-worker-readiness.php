<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$stage = 'config';
try {
    $bridge = json_decode(file_get_contents('/var/lib/schoolsafe-attendance/http-config.json'), true, 32, JSON_THROW_ON_ERROR);
    $worker = json_decode(file_get_contents('/var/lib/schoolsafe-attendance/worker.json'), true, 32, JSON_THROW_ON_ERROR);
    $connect = static function (array $c): PDO {
        return new PDO('pgsql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['database'], $c['user'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    };
    $ensure = static function (bool $value): void { if (!$value) throw new RuntimeException('READINESS_ASSERTION'); };
    $target = $connect($bridge['target']); $mapping = $connect($bridge['mapping']); $native = $connect($worker['native']);
    $stage = 'role-flags';
    foreach ([$target,$mapping,$native] as $pdo) {
        $flags = $pdo->query('SELECT rolsuper OR rolcreatedb OR rolcreaterole OR rolreplication OR rolbypassrls AS elevated FROM pg_roles WHERE rolname=current_user')->fetchColumn();
        $ensure($flags === false);
    }
    $stage = 'effective-privileges';
    $ensure($target->query("SELECT has_table_privilege(current_user,c.oid,'SELECT') FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='app' AND c.relname='security_events'")->fetchColumn() === false);
    $ensure($mapping->query("SELECT has_table_privilege(current_user,c.oid,'SELECT') FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='card_bridge' AND c.relname='entity_map'")->fetchColumn() === false);
    $ensure($native->query("SELECT has_table_privilege(current_user,'public.student_enrollment','UPDATE')")->fetchColumn() === false);
    $ensure($native->query("SELECT has_table_privilege(current_user,'public.students','UPDATE')")->fetchColumn() === false);
    $ensure($native->query("SELECT has_table_privilege(current_user,'schoolsafe_attendance.arrival_ledger','UPDATE,DELETE')")->fetchColumn() === false);
    $stage = 'filtered-reads';
    $sourceCount = (int) $target->query('SELECT count(*) FROM schoolsafe_arrival_source.arrivals')->fetchColumn();
    $mapCount = (int) $mapping->query('SELECT count(*) FROM schoolsafe_arrival_mapping.identities')->fetchColumn();
    $stage = 'lock-function';
    $native->beginTransaction();
    // Exercise the definer lock function as the actual worker, without creating any event or evidence.
    $native->query('SELECT id FROM schoolsafe_attendance.lock_enrollment_at(1,0,2026,CURRENT_DATE)')->fetchAll();
    $native->rollBack();
    $stage = 'ledger-read';
    $ledgerCount = (int) $native->query('SELECT count(*) FROM schoolsafe_attendance.arrival_ledger')->fetchColumn();
    $stage = 'private-file-modes';
    $filesPrivate = true;
    foreach (['worker.json','http-config.json','worker-state.json'] as $file) {
        $path = '/var/lib/schoolsafe-attendance/' . $file;
        $filesPrivate = $filesPrivate && is_file($path) && (fileperms($path) & 0777) === 0600 && fileowner($path) === 33;
    }
    $ensure($filesPrivate);
    echo json_encode(['nonElevatedRoles'=>true,'rawSourceDenied'=>true,'rawMappingDenied'=>true,
        'nativeUpdateDenied'=>true,'ledgerUpdateDeleteDenied'=>true,'lockFunctionExecutedThenRolledBack'=>true,
        'sourceArrivalCount'=>$sourceCount,'reviewedStudentMappings'=>$mapCount,'nativeLedgerCount'=>$ledgerCount,
        'privateFiles0600Owner33'=>true], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR,"ARRIVAL_READINESS_FAILED stage=" . $stage . ": sensitive error details withheld\n"); exit(1);
}
