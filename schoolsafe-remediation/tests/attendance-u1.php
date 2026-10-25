<?php
// SchoolSafe — Lot U1 : 15 tests obligatoires + mesure perf (donnees fictives, isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/attendance-u1.php
// Horloge simulee : les heures 07:59/08:00/09:00/09:30 sont passees en
// parametres, jamais attendues. Sortie 0 ssi tous les cas passent.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeAttendance.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeNotify.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true) . ", obtenu " . var_export($actual, true) . "\n";
}

$host = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$port = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$user = getenv('POSTGRES_USER') ?: 'schoolsafe';
$pass = getenv('POSTGRES_PASSWORD') ?: '';
$dbname = getenv('POSTGRES_DB') ?: 'schoolsafe';
$conn = @pg_connect("host=$host port=$port dbname=$dbname user=$user password=$pass connect_timeout=5");
if ($conn === false) { fwrite(STDERR, "FAIL connexion DB isolee\n"); exit(1); }
$q = function ($sql, $params = []) use ($conn) {
    $res = $params ? @pg_query_params($conn, $sql, $params) : @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 140) . ' :: ' . pg_last_error($conn));
    return $res;
};
$get = function ($sql) use ($conn) {
    $res = @pg_query($conn, $sql);
    if ($res === false) throw new RuntimeException('DB_QUERY_FAILED: ' . substr($sql, 0, 140));
    $out = []; $i = 1;
    while ($r = pg_fetch_assoc($res)) { $out[$i++] = array_change_key_case($r, CASE_UPPER); }
    return $out;
};
$db = ['get' => $get, 'exec' => function ($sql) use ($conn) {
    if (@pg_query($conn, $sql) === false) throw new RuntimeException('DB_EXEC_FAILED: ' . pg_last_error($conn));
}];
$year = (int) (getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: 2026);
$schoolRow = $get('SELECT ID FROM schools WHERE SYEAR=' . $year . ' ORDER BY ID LIMIT 2');
$schoolA = (int) $schoolRow[1]['ID'];
$schoolB = isset($schoolRow[2]) ? (int) $schoolRow[2]['ID'] : $schoolA;
$T0 = date('Y-m-d H:i:s');
$D0 = date('Y-m-d', strtotime('+7 days'));      // jour d'ecole principal
$Dhol = date('Y-m-d', strtotime('+8 days'));    // ferie / vacances
$Dpast = date('Y-m-d', strtotime('-3 days'));   // rattrapage
$Dperf = date('Y-m-d', strtotime('+9 days'));   // perf 250 eleves
$uniq = 'u1' . substr(md5(uniqid('', true)), 0, 8);

// --- Fixtures ----------------------------------------------------------------
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U1','ParentA','parent',$2,$3,$4)",
    [$year, 'u1parentA_' . $uniq, $uniq . 'a@example.invalid', ",$schoolA,"]);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email) VALUES ($1,'U1','Prof','teacher',$2,$3)",
    [$year, 'u1teacher_' . $uniq, $uniq . 't@example.invalid']);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U1','Direct','admin',$2,$3,$4)",
    [$year, 'u1admin_' . $uniq, $uniq . 'd@example.invalid', ",$schoolA,"]);
$mkStud = function ($tag) use ($q, $get, $uniq) {
    $q("INSERT INTO students(first_name, last_name, username) VALUES ('U1',$1,$2)", [$tag, 'u1eleve_' . $tag . '_' . $uniq]);
    $r = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u1eleve_" . $tag . '_' . $uniq . "'");
    return (int) $r[1]['STUDENT_ID'];
};
$st = function ($uname) use ($get) {
    $r = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='$uname'");
    return (int) $r[1]['STAFF_ID'];
};
$parentA = $st('u1parentA_' . $uniq);
$teacher = $st('u1teacher_' . $uniq);
$sArrived = $mkStud('Arrive'); $sAbsent = $mkStud('Absent'); $sTeach = $mkStud('Prof'); $sExc = $mkStud('Excuse');
$sLate = $mkStud('Tard'); $sOther = $mkStud('Ailleurs');
foreach ([$sArrived, $sAbsent, $sTeach, $sExc, $sLate] as $sid) {
    $q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sid]);
    $q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sid, $parentA]);
}
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolB, $sOther]);
// Ne jamais supprimer un jour de calendrier preexistant : on note ce qu'on cree.
$createdCal = [];
foreach ([$D0, $Dhol, $Dpast, $Dperf] as $d) {
    $pre = $get("SELECT SCHOOL_DATE FROM attendance_calendar WHERE SCHOOL_ID=$schoolA AND SYEAR=$year AND SCHOOL_DATE='$d' LIMIT 1");
    if (!isset($pre[1])) $createdCal[] = "($schoolA,'$d')";
    $q('INSERT INTO attendance_calendar(syear, school_id, school_date, minutes, calendar_id) VALUES ($1,$2,$3,480,1)
        ON CONFLICT DO NOTHING', [$year, $schoolA, $d]);
}
$preB = $get("SELECT SCHOOL_DATE FROM attendance_calendar WHERE SCHOOL_ID=$schoolB AND SYEAR=$year AND SCHOOL_DATE='$D0' LIMIT 1");
if (!isset($preB[1])) $createdCal[] = "($schoolB,'$D0')";
$q('INSERT INTO attendance_calendar(syear, school_id, school_date, minutes, calendar_id) VALUES ($1,$2,$3,480,1)
    ON CONFLICT DO NOTHING', [$year, $schoolB, $D0]);
$Dtoday = date('Y-m-d');
$q('INSERT INTO calendar_events(syear, school_id, school_date, title) VALUES ($1,$2,$3,$4)',
    [$year, $schoolA, $Dhol, 'U1 Ferié test']);
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,'07:30','08:00','09:00','Africa/Kinshasa',$3)", [$schoolA, $year, date('Y-m-d', strtotime('-30 days'))]);
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,'08:00','08:30','09:30','Africa/Kinshasa',$3)", [$schoolB, $year, date('Y-m-d', strtotime('-30 days'))]);
$q("INSERT INTO entry_exit_checkpoints(title, type) VALUES ('U1-Portail','in')");
$cp = $get("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='U1-Portail' LIMIT 1");
$cpId = (int) $cp[1]['ID'];
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at)
    VALUES ($1,$2,'in','U1 arrive',$3)", [$sArrived, $cpId, $D0 . ' 07:45:00']);
$q("INSERT INTO attendance_day(student_id, school_date, syear, state_value) VALUES ($1,$2,$3,'1.0')",
    [$sTeach, $D0, $year]);
$q('INSERT INTO schoolsafe_attendance_exceptions(school_id, syear, student_id, school_date, reason)
    VALUES ($1,$2,$3,$4,$5)', [$schoolA, $year, $sExc, $D0, 'U1 dispense test']);
// Cours + classe (notifications enseignants + heritage classe).
$q('INSERT INTO course_subjects(syear, school_id, title) VALUES ($1,$2,$3)', [$year, $schoolA, 'U1 Matiere']);
$sub = $get("SELECT SUBJECT_ID FROM course_subjects WHERE SYEAR=$year AND SCHOOL_ID=$schoolA AND TITLE='U1 Matiere' LIMIT 1");
$q("INSERT INTO school_marking_periods(syear, mp, school_id, title, short_name, start_date, end_date)
    VALUES ($1,'FY',$2,'U1 Annee','U1',$3,$4)", [$year, $schoolA, date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+300 days'))]);
$mp = $get("SELECT MARKING_PERIOD_ID FROM school_marking_periods WHERE SYEAR=$year AND SCHOOL_ID=$schoolA AND TITLE='U1 Annee' LIMIT 1");
$mpId = (int) $mp[1]['MARKING_PERIOD_ID'];
$q('INSERT INTO courses(syear, subject_id, school_id, title) VALUES ($1,$2,$3,$4)',
    [$year, (int) $sub[1]['SUBJECT_ID'], $schoolA, 'U1 Cours']);
$co = $get("SELECT COURSE_ID FROM courses WHERE SYEAR=$year AND SCHOOL_ID=$schoolA AND TITLE='U1 Cours' LIMIT 1");
$q('INSERT INTO course_periods(syear, school_id, course_id, short_name, marking_period_id, teacher_id) VALUES ($1,$2,$3,$4,$5,$6)',
    [$year, $schoolA, (int) $co[1]['COURSE_ID'], 'U1-CP', $mpId, $teacher]);
$cpd = $get("SELECT COURSE_PERIOD_ID FROM course_periods WHERE SYEAR=$year AND SCHOOL_ID=$schoolA AND SHORT_NAME='U1-CP' LIMIT 1");
$cpdId = (int) $cpd[1]['COURSE_PERIOD_ID'];
$q("INSERT INTO school_gradelevels(school_id, title) VALUES ($1,$2)", [$schoolA, 'U1 Section']);
$gl = $get("SELECT ID FROM school_gradelevels WHERE SCHOOL_ID=$schoolA AND TITLE='U1 Section' LIMIT 1");
$glId = (int) $gl[1]['ID'];
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, course_period_id, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,$3,'07:30','08:15','09:00','Africa/Kinshasa',$4)", [$schoolA, $year, $cpdId, date('Y-m-d', strtotime('-30 days'))]);
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, gradelevel_id, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,$3,'07:30','08:10','09:00','Africa/Kinshasa',$4)", [$schoolA, $year, $glId, date('Y-m-d', strtotime('-30 days'))]);

// T1. Cloture a 08h00, pas avant.
$r = SchoolSafeAttendanceClosure($schoolA, $year, $D0, 'school', null, '07:59', $db, false);
check('T1 cloture pas avant 08h00', $r['status'], 'TOO_EARLY');
$n = $get("SELECT COUNT(*) AS C FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$D0' AND PHASE='closure'");
check('T1 aucune ligne avant heure', (int) $n[1]['C'], 0);

// T2. Detection a 09h00, pas avant.
$r = SchoolSafeAttendanceDetection($schoolA, $year, $D0, 'school', null, '08:59', $db, false);
check('T2 detection pas avant 09h00', $r['status'], 'TOO_EARLY');

// Scenario 08h00 -> 09h00 -> 09h30 (cloture reelle, detection reelle).
$c = SchoolSafeAttendanceClosure($schoolA, $year, $D0, 'school', null, '08:00', $db, true);
check('T0 cloture 08h00', $c['status'], 'CLOSED');
check('T0 listes attendus/arrives/pending', [$c['expected_count'] >= 5, in_array($sArrived, $c['arrived'], true), in_array($sAbsent, $c['pending'], true)], [true, true, true]);
check('T0 pas d absence definitive', $c['status'] === 'CLOSED' && strpos(json_encode($c), 'absent') === false, true);
$c2 = SchoolSafeAttendanceClosure($schoolA, $year, $D0, 'school', null, '08:05', $db, true);
check('T0 cloture unique (rejeu)', $c2['status'], 'ALREADY_CLOSED');
$d = SchoolSafeAttendanceDetection($schoolA, $year, $D0, 'school', null, '09:00', $db, true);
check('T0 detection 09h00', $d['status'], 'DETECTED');
check('T0 absent provisoire, sans preuve', [in_array($sAbsent, $d['absent'], true), !in_array($sArrived, $d['absent'], true)], [true, true]);

// T3. Arrivee 09h30 apres detection : provisoire conserve, 90 min vs 08h00.
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at)
    VALUES ($1,$2,'in','U1 tard',$3)", [$sLate, $cpId, $D0 . ' 09:30:00']);
$late = SchoolSafeAttendanceLateArrival($schoolA, $year, $sLate, $D0, $db, strtotime($D0 . ' 09:30:00'));
check('T3 tardive resolue, historique garde', $late['status'], 'RESOLVED_ARRIVED');
check('T3 retard reel 90 min vs 08h00', $late['late_minutes'], 90);
$h = $get("SELECT STATUS FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$sLate AND SCHOOL_DATE='$D0'");
check('T3 evenement provisoire conserve', $h[1]['STATUS'], 'resolved_arrived');

// T4. Presence enseignante deja confirmee.
check('T4 appel enseignant respecte', in_array($sTeach, $d['absent'], true), false);
$ap = $get("SELECT STATE_VALUE FROM attendance_day WHERE STUDENT_ID=$sTeach AND SCHOOL_DATE='$D0'");
check('T4 registre natif intact', isset($ap[1]), true);

// T5. Eleve non inscrit / non attendu.
$exp = SchoolSafeAttendanceExpectedStudents($schoolA, $year, $D0, $db);
check('T5 non-inscrit exclu des attendus', in_array($sOther, $exp, true), false);
try {
    SchoolSafeNotifyResolveRecipients('absence.detected', $schoolB, $year, $sAbsent, $db);
    check('T5 isolation inter-ecoles', 'ACCEPTE', 'SCHOOL_ISOLATION');
} catch (Throwable $e) { check('T5 isolation inter-ecoles', $e->getMessage(), 'SCHOOL_ISOLATION'); }

// T6. Jour ferie / vacances : jamais de detection.
check('T6 jour ferie reconnu', SchoolSafeAttendanceIsSchoolDay($schoolA, $year, $Dhol, $db), false);
$dhol = SchoolSafeAttendanceDetection($schoolA, $year, $Dhol, 'school', null, '09:00', $db, true);
check('T6 aucune detection ferie', $dhol['status'], 'NO_SCHOOL_DAY');
$ahol = $get("SELECT COUNT(*) AS C FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$Dhol'");
check('T6 zero absence ferie', (int) $ahol[1]['C'], 0);

// T7. Horaires differents ecoles + classe.
$cB = SchoolSafeAttendanceClosure($schoolB, $year, $D0, 'school', null, '08:15', $db, false);
check('T7 ecole B pas avant 08h30', $cB['status'], 'TOO_EARLY');
$cB2 = SchoolSafeAttendanceClosure($schoolB, $year, $D0, 'school', null, '08:30', $db, false);
check('T7 ecole B cloture 08h30', $cB2['status'], 'CLOSED');
$polClass = SchoolSafeAttendanceResolvePolicy($schoolA, $year, null, $cpdId, $D0, $db);
check('T7 heritage classe prime', [$polClass['_level'], substr((string) $polClass['CLOSURE_TIME'], 0, 5)], ['class', '08:15']);
$polSect = SchoolSafeAttendanceResolvePolicy($schoolA, $year, $glId, null, $D0, $db);
check('T7 heritage section prime sur ecole', [$polSect['_level'], substr((string) $polSect['CLOSURE_TIME'], 0, 5)], ['section', '08:10']);

// T8. Fuseaux + non-retroactivite.
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,'07:30','08:00','09:00','America/New_York',$3)", [$schoolA, $year, $D0]);
$polOld = SchoolSafeAttendanceResolvePolicy($schoolA, $year, null, null, date('Y-m-d', strtotime('-20 days')), $db);
check('T8 ancienne date garde ancien fuseau', (string) $polOld['TIMEZONE'], 'Africa/Kinshasa');
$polNew = SchoolSafeAttendanceResolvePolicy($schoolA, $year, null, null, $D0, $db);
check('T8 date effet applique nouveau fuseau', (string) $polNew['TIMEZONE'], 'America/New_York');
$tzA = new DateTime($D0 . ' 08:00', new DateTimeZone('Africa/Kinshasa'));
$tzN = new DateTime($D0 . ' 08:00', new DateTimeZone('America/New_York'));
check('T8 decalage fuseaux reel', ($tzN->getTimestamp() - $tzA->getTimestamp()) > 3000, true);

// T9. Redemarrage planificateur (rejeu sans doublon).
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-attendance-scheduler.php')
    . " --once --school=$schoolA --date=$D0 --now=09:30 --catchup-days=0 2>&1", $o1, $code1);
check('T9 planificateur exit 0', $code1, 0);
$before = $get("SELECT COUNT(*) AS C FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$D0'");
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-attendance-scheduler.php')
    . " --once --school=$schoolA --date=$D0 --now=09:30 --catchup-days=0 2>&1", $o2, $code2);
$after = $get("SELECT COUNT(*) AS C FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$D0'");
check('T9 redemarrage sans doublon', [(int) $before[1]['C'], (int) $after[1]['C'], $code2], [(int) $before[1]['C'], (int) $before[1]['C'], 0]);

// T10. Deux executions simultanees (verrou session : pg_advisory_lock survit
// aux transactions, seule la liberation explicite le rend, ROLLBACK seul
// ne suffirait pas et bloquerait T11 a tort).
$q("SELECT pg_advisory_lock(hashtext('schoolsafe-attendance-scheduler'))");
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-attendance-scheduler.php')
    . " --once --school=$schoolA --date=$D0 --now=09:30 --catchup-days=0 2>&1", $o3, $code3);
$q("SELECT pg_advisory_unlock(hashtext('schoolsafe-attendance-scheduler'))");
$already = implode("\n", $o3);
check('T10 concurrence refusee proprement', [$code3, strpos($already, 'ALREADY_RUNNING') !== false], [0, true]);

// T11. Execution manquee puis rattrapage explicite.
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-attendance-scheduler.php')
    . " --once --school=$schoolA --date=$Dtoday --now=09:30 --catchup-days=7 2>&1", $o4, $code4);
$catch = $get("SELECT COUNT(*) AS C FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$Dpast' AND STATUS='catchup'");
check('T11 rattrapage jour manque', [(int) $catch[1]['C'] > 0, $code4], [true, 0]);

// T12. Refus acces autre ecole (fil test + detection).
$feedOther = SchoolSafeNotifyTestFeed($parentA, $schoolB, $db, 50);
check('T12 fil test inter-ecoles vide', $feedOther, []);
$dB = SchoolSafeAttendanceDetection($schoolB, $year, $D0, 'school', null, '09:30', $db, false);
check('T12 detection B ignore eleve A', in_array($sAbsent, $dB['absent'] ?? [], true), false);

// T13. Notifications provisoires via U0.
$ev = $get("SELECT ID, PAYLOAD FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='absence:$schoolA:$D0:$sAbsent' LIMIT 1");
check('T13 evenement absence.detected', isset($ev[1]), true);
$pl = isset($ev[1]) ? json_decode((string) $ev[1]['PAYLOAD'], true) : [];
check('T13 provisoire marque', ($pl['provisional'] ?? false), true);
$dl = isset($ev[1]) ? $get('SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE EVENT_ID=' . (int) $ev[1]['ID']) : ['1' => ['C' => 0]];
check('T13 parents+staff notifies', ((int) $dl[1]['C']) >= 3, true);

// T14. Historique conserve apres correction.
$evLate = $get("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='absence:$schoolA:$D0:$sLate' LIMIT 1");
check('T14 evenement tardif conserve', isset($evLate[1]), true);
$absLate = $get("SELECT STATUS, LATE_MINUTES FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$sLate AND SCHOOL_DATE='$D0'");
check('T14 statut resolu + 90 min', [$absLate[1]['STATUS'], (int) $absLate[1]['LATE_MINUTES']], ['resolved_arrived', 90]);

// PERF. 250 eleves.
$perfIds = [];
for ($i = 0; $i < 250; $i++) {
    $q("INSERT INTO students(first_name, last_name, username) VALUES ('U1Perf',$1,$2)", ["P$i", 'u1perf_' . $i . '_' . $uniq]);
}
$pr = $get("SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u1perf\\_%\\_$uniq' ESCAPE '\\'");
if (is_array($pr)) foreach ($pr as $row) { if (isset($row['STUDENT_ID'])) $perfIds[] = (int) $row['STUDENT_ID']; }
foreach ($perfIds as $sid) {
    $q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sid]);
}
$t = microtime(true);
$dperf = SchoolSafeAttendanceDetection($schoolA, $year, $Dperf, 'school', null, '09:00', $db, false);
$ms = (int) round((microtime(true) - $t) * 1000);
check('PERF 250 detectes', $dperf['absent_count'] >= 250, true);
echo "PERF detection 250 eleves : {$ms} ms (attendus=" . count($perfIds) . ")\n";

// T15. Non-regressions U0 / ENTRY / PICKUP / FEES.
// U0 epuise l'outbox par tranches de 50 (FIFO) : on purge d'abord nos lignes
// 'pending' de test pour lui rendre un outbox vide, sans toucher U0.
for ($flush = 0; $flush < 5; $flush++) {
    $pend = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE STATUS='pending'");
    if ((int) $pend[1]['C'] === 0) break;
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-dispatch.php')
        . ' --once --limit=500 --channel=all 2>&1', $ofl, $cfl);
}
$pend = $get("SELECT COUNT(*) AS C FROM schoolsafe_notify_deliveries WHERE STATUS='pending'");
check('T15 outbox purge avant U0', (int) $pend[1]['C'], 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/notify-u0.php') . ' 2>&1', $ou0, $cu0);
if ($cu0 !== 0) echo 'U0-OUT: ' . implode(' | ', $ou0) . "\n";
check('T15 U0 11/11 intact', $cu0, 0);
foreach (['fee-scan.php' => 'FEES', 'scan-entry.php' => 'ENTRY', 'scan-pickup.php' => 'PICKUP'] as $f => $lot) {
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1', $o, $c);
    check("T15 $lot intact", $c, 0);
}

// --- Nettoyage (fictif seul) ---------------------------------------------------
$allDates = "'$D0','$Dhol','$Dpast','$Dperf','$Dtoday'";
$testSids = $get("SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\' OR USERNAME LIKE 'u1perf\\_%\\_$uniq' ESCAPE '\\'");
$sidList = '0';
if (is_array($testSids)) foreach ($testSids as $sr) { if (isset($sr['STUDENT_ID'])) $sidList .= ',' . ((int) $sr['STUDENT_ID']); }
$q("DELETE FROM schoolsafe_absence_events WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE IN ($allDates)");
$q("DELETE FROM schoolsafe_attendance_runs WHERE SCHOOL_ID IN ($schoolA,$schoolB) AND SCHOOL_DATE IN ($allDates)");
$q("DELETE FROM schoolsafe_attendance_exceptions WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE='$D0'");
$q("DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID IN ($schoolA,$schoolB) AND CREATED_AT>='$T0' AND (DEDUP_KEY LIKE 'att-closure:%' OR DEDUP_KEY LIKE 'absence:%'))");
$q("DELETE FROM schoolsafe_notify_events WHERE SCHOOL_ID IN ($schoolA,$schoolB) AND CREATED_AT>='$T0' AND (DEDUP_KEY LIKE 'att-closure:%' OR DEDUP_KEY LIKE 'absence:%')");
$q("DELETE FROM schoolsafe_attendance_policy WHERE SCHOOL_ID IN ($schoolA,$schoolB)");
$q("DELETE FROM entry_exit_student_records WHERE CHECKPOINT_ID=$cpId");
$q("DELETE FROM entry_exit_checkpoints WHERE ID=$cpId");
$q("DELETE FROM attendance_day WHERE SCHOOL_DATE IN ('$D0','$Dhol','$Dpast','$Dperf') AND STUDENT_ID IN (SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\' OR USERNAME LIKE 'u1perf\\_%\\_$uniq' ESCAPE '\\')");
$q("DELETE FROM student_enrollment WHERE STUDENT_ID IN (SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\' OR USERNAME LIKE 'u1perf\\_%\\_$uniq' ESCAPE '\\')");
$q("DELETE FROM students_join_users WHERE STUDENT_ID IN (SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\')");
$q("DELETE FROM course_periods WHERE SHORT_NAME='U1-CP'");
$q("DELETE FROM students WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\' OR USERNAME LIKE 'u1perf\\_%\\_$uniq' ESCAPE '\\'");
$q("DELETE FROM staff WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\'");
foreach ($createdCal as $pair) {
    [$cs, $cd] = explode(',', trim($pair, '()'));
    $q("DELETE FROM attendance_calendar WHERE SCHOOL_ID=" . (int) $cs . " AND SYEAR=$year AND SCHOOL_DATE=$cd AND CALENDAR_ID=1");
}
$q("DELETE FROM calendar_events WHERE TITLE='U1 Ferié test'");
$q("DELETE FROM courses WHERE TITLE='U1 Cours'");
$q("DELETE FROM course_subjects WHERE TITLE='U1 Matiere'");
$q("DELETE FROM school_marking_periods WHERE TITLE='U1 Annee'");
$q("DELETE FROM school_gradelevels WHERE TITLE='U1 Section'");
$left = $get("SELECT (SELECT COUNT(*) FROM schoolsafe_absence_events WHERE SCHOOL_DATE IN ($allDates) AND SCHOOL_ID IN ($schoolA,$schoolB)) AS A, (SELECT COUNT(*) FROM staff WHERE USERNAME LIKE 'u1%\\_$uniq' ESCAPE '\\') AS S");
check('NETTOYAGE 0 residu', [(int) $left[1]['A'], (int) $left[1]['S']], [0, 0]);

echo $failures === 0 ? "GREEN : U1 15/15 + perf.\n" : "STOP : $failures echec(s).\n";
exit($failures === 0 ? 0 : 1);
