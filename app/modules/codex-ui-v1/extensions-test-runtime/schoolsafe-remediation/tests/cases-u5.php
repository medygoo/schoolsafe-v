<?php
// SchoolSafe — Lot U5 : ponctualite, tableaux de bord, recette generale (isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/cases-u5.php
// Sortie 0 ssi tous les cas passent. Nettoyage verifie (0 residu).
// Inclut les non-regressions U0-U4 + FEES/ENTRY/PICKUP + carte A1 (secret test).
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafePunctuality.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeDashboards.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeAttendance.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeCases.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeStudio.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeP1.fnc.php';

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
$dispatch = function ($args, $env = '') {
    $out = [];
    exec($env . PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-dispatch.php') . ' ' . $args . ' 2>&1', $out, $code);
    return [implode("\n", $out), $code];
};
$year = (int) (getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: 2026);
$schoolRow = $get('SELECT ID FROM schools WHERE SYEAR=' . $year . ' ORDER BY ID LIMIT 2');
$schoolA = (int) $schoolRow[1]['ID'];
$schoolB = isset($schoolRow[2]) ? (int) $schoolRow[2]['ID'] : $schoolA;
check('ecoles-temoins', $schoolB !== $schoolA, true);
$T0 = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$D0 = date('Y-m-d', strtotime('+10 days'));
$D1 = date('Y-m-d', strtotime('+11 days'));
$D2 = date('Y-m-d', strtotime('+12 days'));
$P1d = date('Y-m-d', strtotime('-5 days'));
$P2d = date('Y-m-d', strtotime('-4 days'));
$P3d = date('Y-m-d', strtotime('-3 days'));
$uniq = 'u5' . substr(md5(uniqid('', true)), 0, 8);

// --- Fixtures : direction, enseignant (+cours), parent, eleve B isole.
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U5', 'Direct', 'admin', 'u5admin_' . $uniq, $uniq . 'd@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U5', 'Prof', 'teacher', 'u5prof_' . $uniq, $uniq . 't@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U5', 'Mom', 'parent', 'u5mom_' . $uniq, $uniq . 'm@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U5', 'Etrange', 'teacher', 'u5etrang_' . $uniq, $uniq . 'e@example.invalid', ",$schoolB,"]);
$st = function ($uname) use ($get) {
    $r = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='$uname'");
    return (int) $r[1]['STAFF_ID'];
};
$adminFx = $st('u5admin_' . $uniq);
$teacher = $st('u5prof_' . $uniq);
$mom = $st('u5mom_' . $uniq);
$stranger = $st('u5etrang_' . $uniq);
$mkStud = function ($tag) use ($q, $get, $uniq) {
    $q('INSERT INTO students(first_name, last_name, username) VALUES ($1,$2,$3)', ['U5' . $tag, 'Kid', 'u5eleve_' . $tag . '_' . $uniq]);
    $r = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u5eleve_" . $tag . '_' . $uniq . "'");
    return (int) $r[1]['STUDENT_ID'];
};
$s1 = $mkStud('Un'); $s2 = $mkStud('Deux'); $s3 = $mkStud('Trois'); $s4 = $mkStud('Quatre');
$q('INSERT INTO students(first_name, last_name, username) VALUES ($1,$2,$3)', ['U5B', 'Kid', 'u5eleveB_' . $uniq]);
$rB = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u5eleveB_$uniq'");
$sB = (int) $rB[1]['STUDENT_ID'];
foreach ([$s1, $s2, $s3, $s4] as $sid) {
    $q('INSERT INTO student_enrollment(syear, school_id, student_id, grade_id) VALUES ($1,$2,$3,7)', [$year, $schoolA, $sid]);
    $q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sid, $mom]);
}
$q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolB, $sB]);
// Cours + classe du professeur (s1, s2, s3).
$q('INSERT INTO course_subjects(syear, school_id, title) VALUES ($1,$2,$3)', [$year, $schoolA, 'U5 Matiere']);
$subjRow = $get("SELECT SUBJECT_ID FROM course_subjects WHERE SCHOOL_ID=$schoolA AND SYEAR=$year AND TITLE='U5 Matiere' LIMIT 1");
$subj = (int) $subjRow[1]['SUBJECT_ID'];
$q('INSERT INTO courses(syear, school_id, subject_id, title, short_name) VALUES ($1,$2,$3,$4,$5)',
    [$year, $schoolA, $subj, 'U5 Cours', 'U5C-' . $uniq]);
$courseRow = $get("SELECT COURSE_ID FROM courses WHERE SHORT_NAME='U5C-$uniq' LIMIT 1");
$course = (int) $courseRow[1]['COURSE_ID'];
$mpRow = $get("SELECT MARKING_PERIOD_ID FROM marking_periods WHERE SCHOOL_ID=$schoolA AND SYEAR=$year LIMIT 1");
$mp = (int) $mpRow[1]['MARKING_PERIOD_ID'];
$q('INSERT INTO course_periods(syear, school_id, course_id, short_name, marking_period_id, teacher_id, title) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, $schoolA, $course, 'U5CP-' . $uniq, $mp, $teacher, 'U5 CP']);
$cpRow = $get("SELECT COURSE_PERIOD_ID FROM course_periods WHERE SHORT_NAME='U5CP-$uniq' LIMIT 1");
$cp = (int) $cpRow[1]['COURSE_PERIOD_ID'];
foreach ([$s1, $s2, $s3] as $sid) {
    $q('INSERT INTO schedule(syear, school_id, student_id, course_id, course_period_id, start_date) VALUES ($1,$2,$3,$4,$5,$6)',
        [$year, $schoolA, $sid, $course, $cp, date('Y-m-d', strtotime('-60 days'))]);
}
// Calendrier + politique + portail (retires avant les non-regressions).
foreach ([$today, $D0, $D1, $D2] as $d) {
    $q('INSERT INTO attendance_calendar(syear, school_id, school_date, minutes, calendar_id) VALUES ($1,$2,$3,480,1) ON CONFLICT DO NOTHING',
        [$year, $schoolA, $d]);
}
$q("INSERT INTO schoolsafe_attendance_policy(school_id, syear, arrival_time, closure_time, detection_time, timezone, effective_on)
    VALUES ($1,$2,'07:30','08:00','09:00','Africa/Kinshasa',$3)", [$schoolA, $year, date('Y-m-d', strtotime('-30 days'))]);
$q("INSERT INTO entry_exit_checkpoints(title, type) VALUES ('U5-Portail','in')");
$cpq = $get("SELECT ID FROM entry_exit_checkpoints WHERE TITLE='U5-Portail' LIMIT 1");
$cpId = (int) $cpq[1]['ID'];

// === A. PONCTUALITE ===
// s1 : 2 retards justifies. s2 : 3 retards injustifies (repetes).
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status, late_minutes) VALUES ($1,$2,$3,$4,$5,$6)',
    [$schoolA, $year, $s1, $P1d, 'resolved_arrived', 20]);
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status, late_minutes) VALUES ($1,$2,$3,$4,$5,$6)',
    [$schoolA, $year, $s1, $P3d, 'resolved_arrived', 25]);
foreach ([$P1d, $P2d, $P3d] as $d) {
    $q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status, late_minutes) VALUES ($1,$2,$3,$4,$5,$6)',
        [$schoolA, $year, $s2, $d, 'resolved_arrived', 30]);
}
foreach ([$P1d, $P3d] as $d) {
    $q('INSERT INTO schoolsafe_case_files(school_id, syear, student_id, school_date, status) VALUES ($1,$2,$3,$4,$5)',
        [$schoolA, $year, $s1, $d, 'justified']);
    $fr = $get("SELECT ID FROM schoolsafe_case_files WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$s1 AND SCHOOL_DATE='$d' LIMIT 1");
    $q('INSERT INTO schoolsafe_justifications(file_id, event_kind, version, submitted_by, motive, motive_text, status, decided_by) VALUES ($1,$2,1,$3,$4,$5,$6,$7)',
        [(int) $fr[1]['ID'], 'late_arrival', $mom, 'transport', 'Bus en retard.', 'accepted', $adminFx]);
}
$th = SchoolSafePunctualitySetThresholds($schoolA, $year,
    ['max_unexcused_lates' => 2, 'max_late_minutes' => 60, 'window_days' => 90, 'notify_direction' => true], $db);
check('seuils-configures', [$th['max_unexcused_lates'], $th['max_late_minutes']], [2, 60]);
try {
    SchoolSafePunctualitySetThresholds($schoolA, $year, ['max_unexcused_lates' => -1], $db);
    check('seuil-negatif-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('seuil-negatif-refuse', $e->getMessage(), 'INVALID_THRESHOLD');
}
$from = date('Y-m-d', strtotime('-6 days'));
$st1 = SchoolSafePunctualityStudent($schoolA, $year, $s1, $from, $today, $db);
check('eleve-justifie', [$st1['total_lates'], $st1['justified_lates'], $st1['unjustified_lates'], $st1['total_minutes'],
    $st1['repeated_unexcused'], $st1['repeated_minutes']], [2, 2, 0, 45, false, false]);
$st2 = SchoolSafePunctualityStudent($schoolA, $year, $s2, $from, $today, $db);
check('eleve-repetes', [$st2['total_lates'], $st2['unjustified_lates'], $st2['total_minutes'],
    $st2['repeated_unexcused'], $st2['repeated_minutes']], [3, 3, 90, true, true]);
$repSchool = SchoolSafePunctualityReport($schoolA, $year, $from, $today, ['kind' => 'school'], $db);
check('rapport-ecole', count($repSchool) >= 4, true);
$repClass = SchoolSafePunctualityReport($schoolA, $year, $from, $today, ['kind' => 'class', 'ref' => $cp], $db);
check('rapport-classe', count($repClass), 3);
$repSection = SchoolSafePunctualityReport($schoolA, $year, $from, $today, ['kind' => 'section', 'ref' => 7], $db);
check('rapport-section', count($repSection) >= 4, true);
try {
    SchoolSafePunctualityReport($schoolA, $year, $from, $today, ['kind' => 'classe'], $db);
    check('portee-invalide-refusee', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('portee-invalide-refusee', $e->getMessage(), 'INVALID_SCOPE');
}
$beforeAbs = $get("SELECT COUNT(*) AS C, COALESCE(SUM(LATE_MINUTES),0) AS M FROM schoolsafe_absence_events WHERE STUDENT_ID IN ($s1,$s2)");
$fol = SchoolSafePunctualityFollowup($schoolA, $year, 'u5w1', $db);
check('suivi-direction', $fol['followed'], 1);
$folEv = $get("SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY='punctuality:$schoolA:$year:$s2:u5w1' LIMIT 1");
check('suivi-notifie', isset($folEv[1]), true);
$fol2 = SchoolSafePunctualityFollowup($schoolA, $year, 'u5w1', $db);
check('suivi-idempotent', $fol2['followed'], 0);
$afterAbs = $get("SELECT COUNT(*) AS C, COALESCE(SUM(LATE_MINUTES),0) AS M FROM schoolsafe_absence_events WHERE STUDENT_ID IN ($s1,$s2)");
check('aucune-sanction-auto', [(int) $afterAbs[1]['C'], (int) $afterAbs[1]['M']],
    [(int) $beforeAbs[1]['C'], (int) $beforeAbs[1]['M']]);

// === B. TABLEAUX DE BORD (fixtures du jour) ===
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status) VALUES ($1,$2,$3,$4,$5)',
    [$schoolA, $year, $s1, $today, 'provisional']);
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status, late_minutes) VALUES ($1,$2,$3,$4,$5,$6)',
    [$schoolA, $year, $s2, $today, 'resolved_arrived', 25]);
$q('INSERT INTO schoolsafe_absence_events(school_id, syear, student_id, school_date, status) VALUES ($1,$2,$3,$4,$5)',
    [$schoolB, $year, $sB, $today, 'provisional']);
$filesJust = SchoolSafeCasesReport($mom, $s3, $schoolA, $year, $today, $today, 'Controle dentaire.', 'medical', $db);
$fileJust = $filesJust[0];
$justId = SchoolSafeCasesJustify($mom, $fileJust, 'absence_detected', 'medical', 'Attente decision.', false, '', '', $db);
check('justificatif-pending', $justId > 0, true);
$evPend = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u5-pend-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'feed']], $db);
$evFail = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u5-fail-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'email']], $db);
$dispatch('--once --limit=500 --channel=email');
$p1dash = SchoolSafeP1Raise($schoolA, $year, 'security', 'P2', "Alerte tableau $uniq", 'Corps.',
    $adminFx, [$adminFx, $teacher], $db, 0, "u5-p1dash-$uniq");
$cloToday = SchoolSafeAttendanceClosure($schoolA, $year, $today, 'school', null, '08:00', $db, false);
check('appel-jour', $cloToday['status'], 'CLOSED');
$dd = SchoolSafeDashboardDirection($adminFx, $schoolA, $year, $db);
check('direction-absences-jour', $dd['absences_provisoires_jour'], 1);
check('direction-retards-jour', $dd['retards_resolus_jour'], 1);
check('direction-p1-ouvertes', $dd['alertes_p1_ouvertes'] >= 1, true);
check('direction-justificatifs', $dd['justificatifs_a_examiner'] >= 1, true);
check('direction-attente', $dd['notifications_en_attente'] >= 1, true);
check('direction-erreurs', $dd['notifications_en_erreur'] >= 1, true);
$dt = SchoolSafeDashboardTeacher($teacher, $schoolA, $year, $db);
check('enseignant-classes-eleves', [$dt['classes'], $dt['eleves']], [1, 3]);
check('enseignant-absences-jour', $dt['absences_jour'], 1);
check('enseignant-retards-jour', $dt['retards_jour'], 1);
check('enseignant-appels-jour', $dt['appels_jour'] >= 1, true);
$dp = SchoolSafeDashboardParent($mom, $schoolA, $year, $db);
$kidIds = array_column($dp, 'student_id');
check('parent-enfants', count($dp), 4);
check('parent-pas-de-B', in_array($sB, $kidIds, true), false);
$hasHist = false;
foreach ($dp as $k) { if ($k['student_id'] === $s1 && count($k['absences']) >= 1) $hasHist = true; }
check('parent-historique', $hasHist, true);
$dtech = SchoolSafeDashboardTech($schoolA, $year, $db);
check('tech-statuts', isset($dtech['par_statut']['pending'], $dtech['par_statut']['failed']), true);
$leak = false;
foreach ([$dtech['par_canal_statut'], $dtech['erreurs']] as $grp) {
    foreach ((array) $grp as $row) {
        foreach (array_keys((array) $row) as $k) {
            if (stripos($k, 'FIRST_NAME') !== false || stripos($k, 'LAST_NAME') !== false) $leak = true;
        }
    }
}
check('tech-sans-donnees-privees', $leak, false);
check('tech-p1-ouvertes', $dtech['alertes_p1_ouvertes'] >= 1, true);
$dpB = SchoolSafeDashboardParent($mom, $schoolB, $year, $db);
check('parent-isolation-ecole', $dpB, []);

// === C. RECETTE : chaine complete 08h00 -> 09h00 -> 09h30 ===
$c = SchoolSafeAttendanceClosure($schoolA, $year, $D0, 'school', null, '08:00', $db, false);
check('R1 cloture 08h00', $c['status'], 'CLOSED');
$d = SchoolSafeAttendanceDetection($schoolA, $year, $D0, 'school', null, '09:00', $db, true);
check('R1 absence provisoire 09h00', in_array($s1, $d['absent'], true), true);
$notAbs = $get("SELECT ID FROM schoolsafe_notify_events WHERE SCHOOL_ID=$schoolA AND DEDUP_KEY='absence:$schoolA:$D0:$s1' LIMIT 1");
check('R1 notification absence', isset($notAbs[1]), true);
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at) VALUES ($1,$2,'in',$3,$4)",
    [$s1, $cpId, 'U5 scan ' . $uniq, $D0 . ' 09:30:00']);
$late = SchoolSafeAttendanceLateArrival($schoolA, $year, $s1, $D0, $db, strtotime($D0 . ' 09:30:00'));
check('R1 present retard 90', [$late['status'], $late['late_minutes']], ['RESOLVED_ARRIVED', 90]);
$absHist = $get("SELECT STATUS FROM schoolsafe_absence_events WHERE SCHOOL_ID=$schoolA AND STUDENT_ID=$s1 AND SCHOOL_DATE='$D0'");
check('R1 historique 09h00 conserve', $absHist[1]['STATUS'], 'resolved_arrived');
$fileLate = SchoolSafeCasesOpen($schoolA, $year, $s1, $D0, 'detection', $db, null);
SchoolSafeCasesAddEvent($fileLate, 'late_arrival', null, ['late_minutes' => 90], $db);
SchoolSafeCasesFlagConflict($teacher, $fileLate, 'Correction enseignante : present a 08h05 a mon appel.', $db);
$fConf = $get("SELECT STATUS FROM schoolsafe_case_files WHERE ID=$fileLate");
check('R1 correction enseignante', $fConf[1]['STATUS'], 'under_review');

// Rendez-vous medical : signalement -> ecole informee -> motif cache -> decision -> arrivee.
$filesMed = SchoolSafeCasesReport($mom, $s4, $schoolA, $year, $D1, $D1, "Mon enfant doit passer a l'hopital avant son arrivee.", 'medical', $db);
$fileMed = $filesMed[0];
check('R2 signalement', count($filesMed), 1);
$viewT = SchoolSafeCasesView($teacher, $fileMed, $db);
$redacted = true;
foreach ($viewT['events'] as $ev) {
    if (($ev['payload']['motive_text'] ?? '') === "Mon enfant doit passer a l'hopital avant son arrivee.") $redacted = false;
}
check('R2 motif cache enseignant', $redacted, true);
$justMed = SchoolSafeCasesJustify($mom, $fileMed, 'absence_detected', 'medical', 'Certificat joint.', true,
    'r2://schoolsafe/u5-' . $uniq . '.pdf', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $db);
$decMed = SchoolSafeCasesDecide($adminFx, $justMed, true, $db, 'Bon retablissement.');
check('R2 decision acceptee', $decMed, 'accepted');
$q("INSERT INTO entry_exit_student_records(student_id, checkpoint_id, type, comments, recorded_at) VALUES ($1,$2,'in',$3,$4)",
    [$s4, $cpId, 'U5 hopital ' . $uniq, $D1 . ' 10:15:00']);
check('R2 arrivee verifiee', SchoolSafeCasesVerifyArrival($schoolA, $year, $s4, $D1, $db), true);

// Absence refusee (non justifiee) : l'ecole peut refuser.
$filesRef = SchoolSafeCasesReport($mom, $s1, $schoolA, $year, $D2, $D2, 'Absence annoncee.', 'family', $db);
$justRef = SchoolSafeCasesJustify($mom, $filesRef[0], 'absence_detected', 'family', 'Deplacement.', false, '', '', $db);
$decRef = SchoolSafeCasesDecide($adminFx, $justRef, false, $db, 'Examens, refus.');
check('R3 ecole peut refuser', $decRef, 'refused');

// Studio : message -> lecture -> convocation -> devoir.
$msgId = SchoolSafeStudioSend($mom, $schoolA, $year, "U5 message $uniq", 'Bonjour direction.', [$adminFx], $db);
check('R4 message envoye', $msgId > 0, true);
check('R4 lecture', SchoolSafeStudioMarkRead($adminFx, $msgId, $db), true);
$inbox = SchoolSafeStudioInbox($adminFx, $schoolA, $year, $db);
$found = false;
foreach ($inbox as $it) { if (($it['subject'] ?? '') === "U5 message $uniq") $found = true; }
check('R4 historique lecture', $found, true);
$conv = SchoolSafeStudioConvocation($adminFx, $schoolA, $year, [$s1], "Convocation $uniq", 'Merci de venir.', $today, $db);
check('R4 convocation', $conv['parents'], 1);
$hw = SchoolSafeStudioHomework($teacher, $schoolA, $year, "Devoir $uniq", 'Exercices.', $db);
check('R4 devoir notifie', $hw['notified'] >= 1, true);

// Urgence avec prise en charge humaine.
$p1b = SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', "Urgence $uniq", 'Incident cour.', $adminFx,
    [$adminFx, $teacher], $db, 0, "u5-p1b-$uniq");
check('R5 urgence levee', $p1b['created'], true);
check('R5 urgence ack', SchoolSafeP1Ack($p1b['alert_id'], $teacher, $db)['acked'], true);
check('R5 urgence close', SchoolSafeP1Close($p1b['alert_id'], $adminFx, 'Gere sur place.', $db)['closed'], true);

// Notifications : feed reel, email honnete, doublon, panne, reprise, recover.
$dispatch('--once --limit=500 --channel=feed');
$feed = SchoolSafeNotifyTestFeed($mom, $schoolA, $db, 50);
check('R6 fil parent', count($feed) > 0, true);
$dispatch('--once --limit=500 --channel=email');
// Le signalement previent la direction (staff) : l'email a la direction
// (adresse valide, sans fournisseur) doit echouer honnetement, jamais 'sent'.
$mailRow = $get("SELECT STATUS, LAST_ERROR FROM schoolsafe_notify_deliveries d JOIN schoolsafe_notify_events e ON e.ID=d.EVENT_ID WHERE e.DEDUP_KEY='case:$fileMed:report' AND d.CHANNEL='email' AND d.RECIPIENT_STAFF_ID=$adminFx LIMIT 1");
check('R6 email honnete', isset($mailRow[1]) && $mailRow[1]['STATUS'] === 'failed'
    && strpos($mailRow[1]['LAST_ERROR'], 'NO_EMAIL_PROVIDER') !== false, true);
$dbl1 = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u5-dbl-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'feed']], $db);
$dbl2 = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u5-dbl-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'feed']], $db);
check('R6 double evenement', [$dbl1['created'], $dbl2['created']], [true, false]);
$dispatch('--once --limit=500 --channel=feed --retry-failed');
$pane = SchoolSafeNotifyEmit(['school_id' => $schoolA, 'syear' => $year, 'event_type' => 'school.announcement',
    'dedup_key' => "u5-pane-$uniq", 'payload' => ['kind' => 'new_message']],
    [['audience' => 'parent', 'staff_id' => $mom, 'channel' => 'feed']], $db);
list($fout, $fcode) = $dispatch('--once --limit=500 --channel=feed', 'SCHOOLSAFE_NOTIFY_FORCE_FAIL=1 ');
$fRow = $get("SELECT STATUS, LAST_ERROR FROM schoolsafe_notify_deliveries WHERE EVENT_ID=" . (int) $pane['event_id'] . " LIMIT 1");
check('R6 panne conservee', [$fcode, $fRow[1]['STATUS'], $fRow[1]['LAST_ERROR']], [0, 'failed', 'FORCED_FAILURE']);
$dispatch('--once --limit=500 --channel=feed --retry-failed');
$fRow2 = $get("SELECT STATUS FROM schoolsafe_notify_deliveries WHERE EVENT_ID=" . (int) $pane['event_id'] . " LIMIT 1");
check('R6 reprise apres panne', $fRow2[1]['STATUS'], 'sent');
$rout = [];
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../schoolsafe-notify-recover.php') . ' --source=all 2>&1', $rout, $rcode);
check('R6 recover ok', $rcode, 0);

// Inter-ecoles refuse partout.
try {
    SchoolSafeStudioSend($mom, $schoolA, $year, 'X', 'X', [$stranger], $db);
    check('R7 studio inter-ecoles', 'envoye', 'refuse');
} catch (RuntimeException $e) {
    check('R7 studio inter-ecoles', $e->getMessage(), 'RECIPIENT_DENIED');
}
try {
    SchoolSafeP1Raise($schoolA, $year, 'security', 'P1', 'X', 'Y', $adminFx, [$stranger], $db, 0, "u5-str-$uniq");
    check('R7 p1 inter-ecoles', 'levee', 'refusee');
} catch (RuntimeException $e) {
    check('R7 p1 inter-ecoles', $e->getMessage(), 'RECIPIENT_DENIED');
}
try {
    SchoolSafeCasesView($stranger, $fileMed, $db);
    check('R7 dossier inter-ecoles', 'vu', 'refuse');
} catch (Throwable $e) {
    check('R7 dossier inter-ecoles', $e->getMessage(), 'VIEW_DENIED');
}

// === E. NETTOYAGE (fictif seul, AVANT non-regressions pour ne pas les polluer) ===
$testSids = $get("SELECT STUDENT_ID FROM students WHERE USERNAME LIKE 'u5%\\_$uniq' ESCAPE '\\' OR USERNAME='u5eleveB_$uniq'");
$sidList = '0';
if (is_array($testSids)) foreach ($testSids as $sr) { if (isset($sr['STUDENT_ID'])) $sidList .= ',' . ((int) $sr['STUDENT_ID']); }
$alertRows = $get("SELECT ID FROM schoolsafe_p1_alerts WHERE DEDUP_KEY LIKE 'u5%'");
$alertList = '0';
if (is_array($alertRows)) foreach ($alertRows as $ar) { if (isset($ar['ID'])) $alertList .= ',' . ((int) $ar['ID']); }
$q("DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE CREATED_AT>='$T0')");
$q("DELETE FROM schoolsafe_notify_events WHERE CREATED_AT>='$T0'");
$q("DELETE FROM schoolsafe_p1_recipients WHERE ALERT_ID IN ($alertList)");
$q("DELETE FROM schoolsafe_p1_alerts WHERE ID IN ($alertList)");
$q('BEGIN');
$q("SET LOCAL schoolsafe.allow_case_purge = '1'");
$q("DELETE FROM schoolsafe_justifications WHERE FILE_ID IN (SELECT ID FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList))");
$q("DELETE FROM schoolsafe_case_events WHERE FILE_ID IN (SELECT ID FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList))");
$q("DELETE FROM schoolsafe_case_files WHERE STUDENT_ID IN ($sidList)");
$q('COMMIT');
$q("DELETE FROM schoolsafe_absence_events WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM schoolsafe_attendance_runs WHERE SCHOOL_ID=$schoolA AND SCHOOL_DATE IN ('$today','$D0','$D1','$D2')");
$msgRows = $get("SELECT MESSAGE_ID FROM messages WHERE SUBJECT LIKE '%$uniq%'");
foreach ((array) $msgRows as $mr) {
    if (!isset($mr['MESSAGE_ID'])) continue;
    $mid = (int) $mr['MESSAGE_ID'];
    $q("DELETE FROM messagexuser WHERE MESSAGE_ID=$mid");
    $q("DELETE FROM messages WHERE MESSAGE_ID=$mid");
}
$q("DELETE FROM entry_exit_student_records WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM entry_exit_checkpoints WHERE TITLE='U5-Portail'");
$q("DELETE FROM attendance_day WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM schedule WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM student_enrollment WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM students_join_users WHERE STUDENT_ID IN ($sidList)");
$q("DELETE FROM students WHERE USERNAME LIKE 'u5%\\_$uniq' ESCAPE '\\' OR USERNAME='u5eleveB_$uniq'");
$q("DELETE FROM course_periods WHERE SHORT_NAME='U5CP-$uniq'");
$q("DELETE FROM courses WHERE SHORT_NAME='U5C-$uniq'");
$q("DELETE FROM course_subjects WHERE TITLE='U5 Matiere' AND SCHOOL_ID=$schoolA AND SYEAR=$year");
$q("DELETE FROM staff WHERE USERNAME LIKE 'u5%\\_$uniq' ESCAPE '\\'");
$q("DELETE FROM schoolsafe_punctuality_thresholds WHERE SCHOOL_ID=$schoolA AND SYEAR=$year");
$q("DELETE FROM schoolsafe_attendance_policy WHERE SCHOOL_ID=$schoolA");
foreach ([$today, $D0, $D1, $D2] as $d) {
    $q("DELETE FROM attendance_calendar WHERE SCHOOL_ID=$schoolA AND SYEAR=$year AND SCHOOL_DATE='$d' AND CALENDAR_ID=1");
}

// === D. NON-REGRESSIONS U0-U5 + FEES/ENTRY/PICKUP + carte A1 ===
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/notify-u0.php') . ' 2>&1', $ou0, $cu0);
if ($cu0 !== 0) echo 'U0-OUT: ' . implode(' | ', array_slice($ou0, -5)) . "\n";
check('T U0 intact', $cu0, 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/attendance-u1.php') . ' 2>&1', $ou1, $cu1);
if ($cu1 !== 0) echo 'U1-OUT: ' . implode(' | ', array_slice($ou1, -5)) . "\n";
check('T U1 intact', $cu1, 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/cases-u2.php') . ' 2>&1', $ou2, $cu2);
if ($cu2 !== 0) echo 'U2-OUT: ' . implode(' | ', array_slice($ou2, -5)) . "\n";
check('T U2 intact', $cu2, 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/cases-u3.php') . ' 2>&1', $ou3, $cu3);
if ($cu3 !== 0) echo 'U3-OUT: ' . implode(' | ', array_slice($ou3, -5)) . "\n";
check('T U3 intact', $cu3, 0);
exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/cases-u4.php') . ' 2>&1', $ou4, $cu4);
if ($cu4 !== 0) echo 'U4-OUT: ' . implode(' | ', array_slice($ou4, -5)) . "\n";
check('T U4 intact', $cu4, 0);
foreach (['fee-scan.php' => 'FEES', 'scan-entry.php' => 'ENTRY', 'scan-pickup.php' => 'PICKUP'] as $f => $lot) {
    $o = [];
    exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/' . $f) . ' 2>&1', $o, $c);
    if ($c !== 0) echo "$lot-OUT: " . implode(' | ', array_slice($o, -5)) . "\n";
    check("T $lot intact", $c, 0);
}
$testSecret = bin2hex(random_bytes(32));
$a1o = [];
exec('SCHOOLSAFE_CARD_HMAC_SECRET=' . $testSecret . ' ' . PHP_BINARY . ' '
    . escapeshellarg(__DIR__ . '/card-verify-a1.php') . ' 2>&1', $a1o, $a1c);
if ($a1c !== 0) echo 'A1-OUT: ' . implode(' | ', array_slice($a1o, -5)) . "\n";
check('T carte A1 (secret test)', $a1c, 0);

// === F. BALAYAGE POST-SUITES + VERIFICATION 0 residu global ===
// Les suites imbriquees (U1/U2) detectent TOUS les inscrits : leurs passages
// peuvent laisser des absences/notifications sur des eleves temoins. La base
// etait vide au demarrage (verifie) : tout ce qui porte l'horodatage du run
// est balaye, sans toucher aux donnees Rosario natives.
$q("DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE CREATED_AT>='$T0')");
$q("DELETE FROM schoolsafe_notify_events WHERE CREATED_AT>='$T0'");
$q("DELETE FROM schoolsafe_absence_events WHERE DETECTED_AT>='$T0'");
$q("DELETE FROM schoolsafe_attendance_runs WHERE STARTED_AT>='$T0'");
$left = $get("SELECT (SELECT COUNT(*) FROM schoolsafe_notify_events) AS E, (SELECT COUNT(*) FROM schoolsafe_notify_deliveries) AS D, (SELECT COUNT(*) FROM schoolsafe_absence_events) AS A, (SELECT COUNT(*) FROM schoolsafe_attendance_runs) AS R, (SELECT COUNT(*) FROM schoolsafe_case_files) AS F, (SELECT COUNT(*) FROM schoolsafe_p1_alerts) AS P, (SELECT COUNT(*) FROM staff WHERE USERNAME LIKE 'u5%\\_$uniq' ESCAPE '\\') AS S");
check('NETTOYAGE 0 residu', [(int) $left[1]['E'], (int) $left[1]['D'], (int) $left[1]['A'], (int) $left[1]['R'], (int) $left[1]['F'], (int) $left[1]['P'], (int) $left[1]['S']], [0, 0, 0, 0, 0, 0, 0]);

echo $failures === 0 ? "U5 GREEN\n" : "U5 RED : $failures echec(s)\n";
exit($failures === 0 ? 0 : 1);
