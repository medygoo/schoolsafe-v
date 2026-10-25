<?php
// SchoolSafe — Lot U2 : dossiers, signalements, justifications (donnees fictives, isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/cases-u2.php
// Sortie 0 ssi tous les cas passent. Nettoyage verifie (0 residu).
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeCases.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeAttendance.fnc.php';

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
$D0 = date('Y-m-d', strtotime('+10 days'));
$D1 = date('Y-m-d', strtotime('+11 days'));
$D2 = date('Y-m-d', strtotime('+12 days'));
$D3 = date('Y-m-d', strtotime('+13 days'));
$uniq = 'u2' . substr(md5(uniqid('', true)), 0, 8);

$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U2','Parent','parent',$2,$3,$4)",
    [$year, 'u2parent_' . $uniq, $uniq . '@example.invalid', ",$schoolA,"]);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U2','Autre','parent',$2,$3,$4)",
    [$year, 'u2other_' . $uniq, $uniq . 'o@example.invalid', ",$schoolA,"]);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U2','Prof','teacher',$2,$3,$4)",
    [$year, 'u2teacher_' . $uniq, $uniq . 't@example.invalid', ",$schoolA,"]);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U2','Direct','admin',$2,$3,$4)",
    [$year, 'u2admin_' . $uniq, $uniq . 'd@example.invalid', ",$schoolA,"]);
$q("INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,'U2','Etrange','teacher',$2,$3,$4)",
    [$year, 'u2stranger_' . $uniq, $uniq . 's@example.invalid', ",$schoolB,"]);
$st = function ($uname) use ($get) {
    $r = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='$uname'");
    return (int) $r[1]['STAFF_ID'];
};
$parent = $st('u2parent_' . $uniq);
$parentOther = $st('u2other_' . $uniq);
$teacher = $st('u2teacher_' . $uniq);
$admin = $st('u2admin_' . $uniq);
$stranger = $st('u2stranger_' . $uniq);
$mkStud = function ($tag) use ($q, $get, $uniq) {
    $q("INSERT INTO students(first_name, last_name, username) VALUES ('U2',$1,$2)", [$tag, 'u2eleve_' . $tag . '_' . $uniq]);
    $r = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u2eleve_" . $tag . '_' . $uniq . "'");
    return (int) $r[1]['STUDENT_ID'];
};
$sLate = $mkStud('Tard'); $sMed = $mkStud('Medic'); $sAnt = $mkStud('Ant'); $sConf = $mkStud('Conf'); $sOther = $mkStud('Autre');
foreach ([$sLate, $sMed, $sAnt, $sConf] as $sid) {
    $q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sid]);
    $q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sid, $parent]);
}
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sOther]);
$q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sOther, $parentOther]);
foreach ([$D0, $D1, $D2, $D3] as $d) {
    $q('INSERT INTO attendance_calendar(syear, school_id, school_date, minutes, calendar_id) VALUES ($1,$2,$3,480,1)
        ON CONFLICT DO NOTHING', [$year, $schoolA, $d]);
}
$createdCal = [$D0, $D1, $D2, $D3];
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,'07:30','08:00','09:00','Africa/Kinshasa',$3)", [$schoolA, $year, date('Y-m-d', strtotime('-30 days'))]);
$q("INSERT INTO entry_exit_checkpoints(title, type) VALUES ('U2-Portail','in')");
$cp = $get("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='U2-Portail' LIMIT 1");
$cpId = (int) $cp[1]['ID'];

// CAS 1. Arrivee tardive 08h00 -> 09h00 -> 09h30 (avec U1).
$c = SchoolSafeAttendanceClosure($schoolA, $year, $D0, 'school', null, '08:00', $db, false);
check('C1 cloture 08h00', $c['status'], 'CLOSED');
$d = SchoolSafeAttendanceDetection($schoolA, $year, $D0, 'school', null, '09:00', $db, false);
check('C1 absence provisoire 09h00', in_array($sLate, $d['absent'], true), true);
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at)
    VALUES ($1,$2,'in','U2 tard',$3)", [$sLate, $cpId, $D0 . ' 09:30:00']);
$late = SchoolSafeAttendanceLateArrival($schoolA, $year, $sLate, $D0, $db, strtotime($D0 . ' 09:30:00'));
check('C1 present retard 90 min', [$late['status'], $late['late_minutes']], ['RESOLVED_ARRIVED', 90]);
$absRow = $get("SELECT ID FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$sLate AND SCHOOL_DATE='$D0'");
$fileLate = SchoolSafeCasesOpen($schoolA, $year, $sLate, $D0, 'detection', $db, (int) $absRow[1]['ID']);
SchoolSafeCasesAddEvent($fileLate, 'late_arrival', null, ['late_minutes' => 90, 'record_id' => $late['record_id']], $db);
check('C1 provisoire 09h00 conserve', isset($absRow[1]), true);
$evLate = $get("SELECT COUNT(*) AS C FROM schoolsafe_case_events WHERE FILE_ID=$fileLate");
check('C1 historique dossier', ((int) $evLate[1]['C']) >= 1, true);

// CAS 2. Rendez-vous medical (confidentiel, decision, arrivee verifiee).
$filesMed = SchoolSafeCasesReport($parent, $sMed, $schoolA, $year, $D1, $D1,
    "Mon enfant doit passer a l'hopital avant son arrivee.", 'medical', $db);
check('C2 signalement enregistre', count($filesMed), 1);
$fileMed = $filesMed[0];
$notifMed = $get("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='case:$fileMed:report' LIMIT 1");
check('C2 ecole informee', isset($notifMed[1]), true);
$viewT = SchoolSafeCasesView($teacher, $fileMed, $db);
$redacted = true;
foreach ($viewT['events'] as $ev) {
    if (($ev['payload']['motive_text'] ?? '') === "Mon enfant doit passer a l'hopital avant son arrivee.") $redacted = false;
}
check('C2 motif cache a l enseignant', $redacted, true);
$viewD = SchoolSafeCasesView($admin, $fileMed, $db);
$full = false;
foreach ($viewD['events'] as $ev) {
    if (($ev['payload']['message'] ?? '') === "Mon enfant doit passer a l'hopital avant son arrivee.") $full = true;
}
check('C2 direction voit le motif', $full, true);
$justMed = SchoolSafeCasesJustify($parent, $fileMed, 'absence_detected', 'medical',
    'Certificat hopital joint.', true, 'r2://schoolsafe/cert-' . $uniq . '.pdf',
    'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $db);
check('C2 justification deposee', $justMed > 0, true);
$decMed = SchoolSafeCasesDecide($admin, $justMed, true, $db, 'Recu, bon retablissement.');
check('C2 decision acceptee', $decMed, 'accepted');
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at)
    VALUES ($1,$2,'in','U2 hopital',$3)", [$sMed, $cpId, $D1 . ' 10:15:00']);
check('C2 arrivee reelle verifiee', SchoolSafeCasesVerifyArrival($schoolA, $year, $sMed, $D1, $db), true);
$fMedSt = $get("SELECT STATUS FROM schoolsafe_case_files WHERE ID=$fileMed");
check('C2 dossier justifie, pieces gardees', $fMedSt[1]['STATUS'], 'justified');

// CAS 3. Absence anticipee (aujourd hui, demain, plusieurs jours).
$filesAnt = SchoolSafeCasesReport($parent, $sAnt, $schoolA, $year, $D1, $D3, 'Absence familiale annoncee.', 'family', $db);
check('C3 3 jours = 3 dossiers', count($filesAnt), 3);
$justAnt = SchoolSafeCasesJustify($parent, $filesAnt[0], 'absence_detected', 'family', 'Deplacement familial.', false, '', '', $db);
$decAnt = SchoolSafeCasesDecide($admin, $justAnt, false, $db, 'Periode d examens, refus.');
check('C3 ecole peut refuser', $decAnt, 'refused');
$fAntSt = $get("SELECT STATUS FROM schoolsafe_case_files WHERE ID=" . $filesAnt[0]);
check('C3 dossier non justifie', $fAntSt[1]['STATUS'], 'unjustified');
$fake = $get("SELECT COUNT(*) AS C FROM attendance_day WHERE STUDENT_ID=$sAnt AND SCHOOL_DATE IN ('$D1','$D2','$D3')");
check('C3 jamais de fausse presence', (int) $fake[1]['C'], 0);

// Retard justifie vs non justifie.
$justLate = SchoolSafeCasesJustify($parent, $fileLate, 'late_arrival', 'transport', 'Bus en panne.', false, '', '', $db);
$decLate = SchoolSafeCasesDecide($admin, $justLate, true, $db, 'OK.');
check('C4 retard justifie', $decLate, 'accepted');

// Doublons.
$fileDup = SchoolSafeCasesOpen($schoolA, $year, $sLate, $D0, 'parent_report', $db);
check('C5 dossier unique (rejeu)', $fileDup, $fileLate);
$justDup = SchoolSafeCasesJustify($parent, $fileLate, 'late_arrival', 'transport', 'Precision : bus 12.', false, '', '', $db);
$ver = $get("SELECT MAX(VERSION) AS V FROM schoolsafe_justifications WHERE FILE_ID=$fileLate AND EVENT_KIND='late_arrival'");
check('C5 version conservee, pas de doublon', (int) $ver[1]['V'], 2);

// Conflit avec l'appel enseignant.
$q("INSERT INTO attendance_day(student_id, school_date, syear, state_value) VALUES ($1,$2,$3,'1.0')", [$sConf, $D0, $year]);
$fileConf = SchoolSafeCasesOpen($schoolA, $year, $sConf, $D0, 'teacher', $db);
SchoolSafeCasesFlagConflict($teacher, $fileConf, 'Present a mon appel de 08h05.', $db);
$fConfSt = $get("SELECT STATUS FROM schoolsafe_case_files WHERE ID=$fileConf");
check('C6 dossier de verification', $fConfSt[1]['STATUS'], 'under_review');
$notifConf = $get("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='case:$fileConf:conflict' LIMIT 1");
check('C6 enseignant notifie', isset($notifConf[1]), true);

// Restrictions medicales et droits.
try {
    SchoolSafeCasesView($stranger, $fileMed, $db);
    check('C7 enseignant autre ecole refuse', 'VU', 'VIEW_DENIED');
} catch (Throwable $e) { check('C7 enseignant autre ecole refuse', $e->getMessage(), 'VIEW_DENIED'); }
try {
    SchoolSafeCasesView($parentOther, $fileMed, $db);
    check('C7 parent autre enfant refuse', 'VU', 'VIEW_DENIED');
} catch (Throwable $e) { check('C7 parent autre enfant refuse', $e->getMessage(), 'VIEW_DENIED'); }
try {
    SchoolSafeCasesReport($parent, $sOther, $schoolA, $year, $D1, $D1, 'Intrusion.', 'other', $db);
    check('C8 parent limite a ses enfants', 'ECRIT', 'PARENT_SCOPE');
} catch (Throwable $e) { check('C8 parent limite a ses enfants', $e->getMessage(), 'PARENT_SCOPE'); }
$justFresh = SchoolSafeCasesJustify($parent, $fileMed, 'absence_detected', 'family',
    'Complement en attente de decision.', false, '', '', $db);
try {
    SchoolSafeCasesDecide($parent, $justFresh, true, $db, 'Auto-validation.');
    check('C8 parent ne decide pas', 'DECIDE', 'DECIDER_SCOPE');
} catch (Throwable $e) { check('C8 parent ne decide pas', $e->getMessage(), 'DECIDER_SCOPE'); }
SchoolSafeCasesDecide($admin, $justFresh, true, $db, 'Solde par la direction.');

// Append-only.
$evRow = $get("SELECT ID FROM schoolsafe_case_events WHERE FILE_ID=$fileLate LIMIT 1");
$eid = (int) $evRow[1]['ID'];
$upErr = '';
try { $q("UPDATE schoolsafe_case_events SET PAYLOAD='{}' WHERE ID=$eid"); } catch (Throwable $e) { $upErr = $e->getMessage(); }
check('C9 UPDATE historique interdit', strpos($upErr, 'APPEND_ONLY') !== false, true);
$delErr = '';
try { $q("DELETE FROM schoolsafe_case_events WHERE ID=$eid"); } catch (Throwable $e) { $delErr = $e->getMessage(); }
check('C9 DELETE historique interdit', strpos($delErr, 'APPEND_ONLY') !== false, true);

// La politique U2 a servi pour C1 : on la retire AVANT les non-regressions pour
// rendre a U1 son creneau (school,effective) sans conflit (U1 insere le sien).
$q("DELETE FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=$schoolA");
// Non-regressions.
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/notify-u0.php') . ' 2>&1', $ou0, $cu0);
if ($cu0 !== 0) echo 'U0-OUT: ' . implode(' | ', $ou0) . "\n";
check('T U0 11/11 intact', $cu0, 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/attendance-u1.php') . ' 2>&1', $ou1, $cu1);
if ($cu1 !== 0) echo 'U1-OUT: ' . implode(' | ', array_slice($ou1, -8)) . "\n";
check('T U1 15/15 intact', $cu1, 0);
foreach (['fee-scan.php' => 'FEES', 'scan-entry.php' => 'ENTRY', 'scan-pickup.php' => 'PICKUP'] as $f => $lot) {
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1', $o, $c);
    check("T $lot intact", $c, 0);
}

// --- Nettoyage (fictif seul) ---------------------------------------------------
$testSids = $get("SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u2%\\_$uniq' ESCAPE '\\'");
$sidList = '0';
if (is_array($testSids)) foreach ($testSids as $sr) { if (isset($sr['STUDENT_ID'])) $sidList .= ',' . ((int) $sr['STUDENT_ID']); }
// Purge fictive autorisee (trigger append-only levee pour cette transaction
// seulement ; les tests C9 prouvent le refus hors purge).
$q('BEGIN');
$q("SET LOCAL schoolsafe.allow_case_purge = '1'");
$q("DELETE FROM schoolsafe_justifications WHERE FILE_ID IN (SELECT ID FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList))");
$q("DELETE FROM schoolsafe_case_events WHERE FILE_ID IN (SELECT ID FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList))");
$q("DELETE FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList)");
$q('COMMIT');
$q("DELETE FROM schoolsafe_absence_events WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE IN ('$D0','$D1','$D2','$D3')");
$q("DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND CREATED_AT>='$T0' AND DEDUP_KEY LIKE 'case:%')");
$q("DELETE FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND CREATED_AT>='$T0' AND DEDUP_KEY LIKE 'case:%'");
$q("DELETE FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=$schoolA");
$q("DELETE FROM entry_exit_student_records WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM entry_exit_checkpoints WHERE TITLE='U2-Portail'");
$q("DELETE FROM attendance_day WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM student_enrollment WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM students_join_users WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM students WHERE USERNAME LIKE 'u2%\\_$uniq' ESCAPE '\\'");
$q("DELETE FROM staff WHERE USERNAME LIKE 'u2%\\_$uniq' ESCAPE '\\'");
// Les jours U2 sont futurs (+10..+13 j) : aucune ligne preexistante possible.
foreach ($createdCal as $d) {
    $q("DELETE FROM attendance_calendar WHERE SCHOOL_ID=$schoolA AND SYEAR=$year AND SCHOOL_DATE='$d' AND CALENDAR_ID=1");
}
$left = $get("SELECT (SELECT COUNT(*) FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList)) AS F, (SELECT COUNT(*) FROM staff WHERE USERNAME LIKE 'u2%\\_$uniq' ESCAPE '\\') AS S");
check('NETTOYAGE 0 residu', [(int) $left[1]['F'], (int) $left[1]['S']], [0, 0]);

echo $failures === 0 ? "GREEN : U2 dossiers.\n" : "STOP : $failures echec(s).\n";
exit($failures === 0 ? 0 : 1);
