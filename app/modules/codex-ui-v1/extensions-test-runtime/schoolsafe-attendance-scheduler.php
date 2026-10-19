<?php
// SchoolSafe — Lot U1 : planificateur autonome des passages 08h00/09h00 (CLI uniquement).
//
// Usage (conteneur L1) : php schoolsafe-attendance-scheduler.php --once [--school=N] [--date=YYYY-MM-DD] [--now=HH:MM] [--catchup-days=7]
// - Fonctionne sans utilisateur connecte (tache cron / superviseur).
// - Un verrou consultatif PostgreSQL interdit deux executions concurrentes.
// - Rattrapage explicite : les jours de cours passes sans passage, dans la
//   fenetre --catchup-days, sont traites avec le statut 'catchup'.
// - Jamais de detection les jours sans cours (calendrier Rosario).
// - Rejouable sans doublon : un passage existant n'est jamais recree.
// - Fuseau : l'heure et la date sont evaluees dans le fuseau de chaque ecole.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(__DIR__);
require_once __DIR__ . '/ProgramFunctions/SchoolSafeAttendance.fnc.php';

$once = in_array('--once', $argv, true);
$schoolOnly = 0;
$dateOverride = '';
$nowOverride = '';
$catchupDays = 7;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--school=')) $schoolOnly = (int) substr($arg, 9);
    if (str_starts_with($arg, '--date=')) $dateOverride = substr($arg, 7);
    if (str_starts_with($arg, '--now=')) $nowOverride = substr($arg, 6);
    if (str_starts_with($arg, '--catchup-days=')) $catchupDays = max(0, min(31, (int) substr($arg, 15)));
}
if (!$once) { fwrite(STDERR, "Usage: php schoolsafe-attendance-scheduler.php --once [--school=N] [--date=YYYY-MM-DD] [--now=HH:MM] [--catchup-days=7]\n"); exit(2); }
if ($dateOverride !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dateOverride)) { fwrite(STDERR, "INVALID_DATE\n"); exit(2); }
if ($nowOverride !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/D', $nowOverride)) { fwrite(STDERR, "INVALID_TIME\n"); exit(2); }

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "DB_UNAVAILABLE\n"); exit(1); }

// Verrou anti-double-execution (non bloquant) : un seul planificateur a la fois.
$lock = @pg_query($conn, "SELECT pg_try_advisory_lock(hashtext('schoolsafe-attendance-scheduler')) AS locked");
$lockedRow = $lock !== false ? pg_fetch_assoc($lock) : null;
if (($lockedRow['locked'] ?? '') !== 't') { echo "ALREADY_RUNNING\n"; exit(0); }
register_shutdown_function(function () use ($conn) {
    @pg_query($conn, "SELECT pg_advisory_unlock(hashtext('schoolsafe-attendance-scheduler'))");
});

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
        if (@pg_query($conn, $sql) === false) throw new RuntimeException('DB_EXEC_FAILED');
    },
];

$year = (int) (getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: 2026);
$schoolSql = $schoolOnly > 0 ? ' AND ID=' . $schoolOnly : '';
$schools = $db['get']('SELECT ID, SYEAR FROM schools WHERE SYEAR=' . $year . $schoolSql . ' ORDER BY ID');
$closed = 0; $detected = 0; $skipped = 0; $caughtUp = 0;
if (is_array($schools)) foreach ($schools as $s) {
    if (!isset($s['ID'])) continue;
    $school = (int) $s['ID'];
    try {
        $policy = SchoolSafeAttendanceResolvePolicy($school, $year, null, null,
            $dateOverride !== '' ? $dateOverride : date('Y-m-d'), $db);
        $tz = SchoolSafeAttendanceValidateTz((string) ($policy['TIMEZONE'] ?? 'Africa/Kinshasa'));
    } catch (Throwable $e) { $skipped++; continue; }
    try {
        $today = $dateOverride !== '' ? $dateOverride : (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
        $nowTime = $nowOverride !== '' ? $nowOverride : (new DateTime('now', new DateTimeZone($tz)))->format('H:i');
    } catch (Throwable $e) { $skipped++; continue; }
    // Jour courant : cloture puis detection si dues.
    try {
        $c = SchoolSafeAttendanceClosure($school, $year, $today, 'school', null, $nowTime, $db, true);
        if (($c['status'] ?? '') === 'CLOSED') $closed++;
    } catch (Throwable $e) { $skipped++; continue; }
    try {
        $d = SchoolSafeAttendanceDetection($school, $year, $today, 'school', null, $nowTime, $db, true);
        if (($d['status'] ?? '') === 'DETECTED') $detected++;
    } catch (Throwable $e) { $skipped++; continue; }
    // Rattrapage explicite : jours de cours passes sans passage.
    for ($back = 1; $back <= $catchupDays; $back++) {
        try {
            $day = (new DateTime($today))->modify("-$back day")->format('Y-m-d');
        } catch (Throwable $e) { break; }
        try {
            if (!SchoolSafeAttendanceIsSchoolDay($school, $year, $day, $db)) continue;
            $have = $db['get']("SELECT ID FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$school"
                . " AND SCHOOL_DATE='$day' AND SCOPE_KIND='school' AND SCOPE_REF IS NULL LIMIT 1");
            if (is_array($have) && isset($have[1])) continue;
            $cc = SchoolSafeAttendanceClosure($school, $year, $day, 'school', null, '23:59', $db, true);
            $dd = SchoolSafeAttendanceDetection($school, $year, $day, 'school', null, '23:59', $db, true);
            if ((($cc['status'] ?? '') === 'CLOSED') || (($dd['status'] ?? '') === 'DETECTED')) {
                @pg_query($conn, "UPDATE schoolsafe_attendance_runs SET STATUS='catchup'"
                    . " WHERE SCHOOL_ID=$school AND SCHOOL_DATE='$day' AND SCOPE_KIND='school'"
                    . " AND SCOPE_REF IS NULL AND STATUS='done'");
                $caughtUp++;
            }
        } catch (Throwable $e) { continue; }
    }
}
echo "SCHEDULER closed=$closed detected=$detected caught_up=$caughtUp skipped=$skipped\n";
exit(0);
