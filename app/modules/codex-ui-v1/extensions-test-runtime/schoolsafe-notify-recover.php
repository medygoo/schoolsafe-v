<?php
// SchoolSafe — Lot U0 : recuperation apres interruption (CLI uniquement).
//
// Cas critique : ecriture officielle confirmee mais arret brutal AVANT la
// creation de l'evenement de notification. Ce script relit les sources
// officielles (LECTURE SEULE, jamais modifiees) et emet les evenements
// manquants via SchoolSafeNotifyEmit (idempotent, ON CONFLICT) :
//   entry   : entry_exit_student_records 'in' (7 j) -> security.entry
//   absence : schoolsafe_absence_events sans NOTIFY_EVENT_ID -> absence.detected
//   cases   : schoolsafe_case_events decision/justification/arrivee sans
//             evenement 'case.updated' -> case.updated (reconciliation U2)
// Le relay existant n'est ni modifie ni appele.
// Usage : php schoolsafe-notify-recover.php [--source=entry|pickup|fees|scan|absence|cases|all]
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
$source = 'all';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) $source = substr($arg, 9);
}
if (!in_array($source, ['entry', 'pickup', 'fees', 'scan', 'absence', 'cases', 'all'], true)) {
    fwrite(STDERR, "Usage: php schoolsafe-notify-recover.php [--source=entry|pickup|fees|scan|absence|cases|all]\n");
    exit(2);
}
chdir(__DIR__);
require_once __DIR__ . '/ProgramFunctions/SchoolSafeNotify.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeAttendance.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeCases.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeScanNotify.fnc.php';

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "DB_UNAVAILABLE\n"); exit(1); }

$db = [
    'get' => function ($sql) use ($conn) {
        $res = @pg_query($conn, $sql);
        if ($res === false) throw new RuntimeException('DB_QUERY_FAILED');
        $out = [];
        $i = 1;
        while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
        return $out;
    },
    'exec' => function ($sql) use ($conn) {
        $res = @pg_query($conn, $sql);
        if ($res === false) throw new RuntimeException('DB_EXEC_FAILED');
    },
];

$recoveredAbsence = 0; $skippedAbsence = 0; $recoveredCases = 0; $skippedCases = 0;

if ($source === 'absence' || $source === 'all') {
    // Absences provisoires ecrites sans evenement (crash entre les deux).
    $missing = $db['get']('SELECT SCHOOL_ID, SYEAR, STUDENT_ID, SCHOOL_DATE'
        . ' FROM schoolsafe_absence_events WHERE NOTIFY_EVENT_ID IS NULL'
        . " AND DETECTED_AT>=CURRENT_TIMESTAMP - INTERVAL '7 days' ORDER BY ID LIMIT 200");
    if (is_array($missing)) foreach ($missing as $m) {
        if (!isset($m['SCHOOL_ID'], $m['STUDENT_ID'], $m['SCHOOL_DATE'])) { $skippedAbsence++; continue; }
        $mschool = (int) $m['SCHOOL_ID'];
        $msyear = (int) $m['SYEAR'];
        $msid = (int) $m['STUDENT_ID'];
        $mdate = substr((string) $m['SCHOOL_DATE'], 0, 10);
        try {
            $parents = SchoolSafeNotifyResolveRecipients('absence.detected', $mschool, $msyear, $msid, $db);
        } catch (Throwable $ignored) { $skippedAbsence++; continue; }
        $staff = SchoolSafeAttendanceStaffRecipients($mschool, $msyear, $db);
        $out = SchoolSafeNotifyEmit(['school_id' => $mschool, 'syear' => $msyear,
            'event_type' => 'absence.detected', 'student_id' => $msid,
            'dedup_key' => "absence:$mschool:$mdate:$msid",
            'payload' => ['school_date' => $mdate, 'provisional' => true, 'recovered' => true]],
            array_merge($parents, $staff), $db);
        if (is_array($out) && $out['event_id'] > 0) {
            $eid = (int) $out['event_id'];
            try {
                $db['exec']('BEGIN');
                $db['get']('UPDATE schoolsafe_absence_events SET NOTIFY_EVENT_ID=' . $eid
                    . " WHERE SCHOOL_ID=$mschool AND STUDENT_ID=$msid AND SCHOOL_DATE='$mdate'");
                $db['exec']('COMMIT');
            } catch (Throwable $e) { try { $db['exec']('ROLLBACK'); } catch (Throwable $i) { } }
            if (!empty($out['created'])) $recoveredAbsence++; else $skippedAbsence++;
        } else $skippedAbsence++;
    }
}

if ($source === 'cases' || $source === 'all') {
    // Decisions/justifications/arrivees U2 sans 'case.updated' (7 j).
    $events = $db['get']("SELECT e.ID, e.KIND, e.CREATED_AT, f.ID AS FILE_ID, f.SCHOOL_ID, f.SYEAR, f.STUDENT_ID"
        . ' FROM schoolsafe_case_events e JOIN schoolsafe_case_files f ON f.ID=e.FILE_ID'
        . " WHERE e.KIND IN ('decision','justification','arrival_verified','late_arrival')"
        . " AND e.CREATED_AT>=CURRENT_TIMESTAMP - INTERVAL '7 days' ORDER BY e.ID LIMIT 200");
    if (is_array($events)) foreach ($events as $ev) {
        if (!isset($ev['ID'], $ev['FILE_ID'])) { $skippedCases++; continue; }
        $cschool = (int) $ev['SCHOOL_ID'];
        $csyear = (int) $ev['SYEAR'];
        $csid = (int) $ev['STUDENT_ID'];
        $dedup = 'case:' . (int) $ev['FILE_ID'] . ':' . (string) $ev['KIND'] . ':' . (int) $ev['ID'];
        $exists = $db['get']("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$cschool"
            . " AND DEDUP_KEY='$dedup' LIMIT 1");
        if (is_array($exists) && isset($exists[1])) { $skippedCases++; continue; }
        $prows = $db['get']('SELECT su.STAFF_ID FROM students_join_users su JOIN staff st ON st.STAFF_ID=su.STAFF_ID'
            . ' WHERE su.STUDENT_ID=' . $csid . " AND st.PROFILE='parent'");
        $recips = [];
        if (is_array($prows)) foreach ($prows as $pr) {
            if (!isset($pr['STAFF_ID'])) continue;
            $recips[] = ['audience' => 'parent', 'staff_id' => (int) $pr['STAFF_ID'], 'channel' => 'feed'];
            $recips[] = ['audience' => 'parent', 'staff_id' => (int) $pr['STAFF_ID'], 'channel' => 'email'];
        }
        $admins = $db['get']("SELECT STAFF_ID FROM staff WHERE PROFILE='admin' AND SYEAR=$csyear");
        if (is_array($admins)) foreach ($admins as $ar) {
            if (isset($ar['STAFF_ID'])) $recips[] = ['audience' => 'direction', 'staff_id' => (int) $ar['STAFF_ID'], 'channel' => 'feed'];
        }
        if (!$recips) { $skippedCases++; continue; }
        $out = SchoolSafeNotifyEmit(['school_id' => $cschool, 'syear' => $csyear,
            'event_type' => 'case.updated', 'student_id' => $csid, 'dedup_key' => $dedup,
            'payload' => ['kind' => (string) $ev['KIND'], 'file_id' => (int) $ev['FILE_ID'],
                'recovered' => true]], $recips, $db);
        if (is_array($out) && !empty($out['created'])) $recoveredCases++; else $skippedCases++;
    }
}
// Native persisted scope is authoritative, never current enrollment/default year.
// Source filtering applies to scan recovery too; unrelated sources do not run it.
$scan = SchoolSafeScanNotifyRecover($source, $db);
$recovered = $scan['entry']; $skipped = $scan['skipped'];
echo "RECOVER recovered=$recovered skipped=$skipped"
    . " absence_recovered=$recoveredAbsence absence_skipped=$skippedAbsence"
    . " cases_recovered=$recoveredCases cases_skipped=$skippedCases"
    . ' pickup_recovered=' . $scan['pickup'] . ' fees_recovered=' . $scan['fees'] . " source=$source\n";
exit(0);
