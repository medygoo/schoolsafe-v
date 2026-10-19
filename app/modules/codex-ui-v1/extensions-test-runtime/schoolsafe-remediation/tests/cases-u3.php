<?php
// SchoolSafe — Lot U3 : Studio central + messagerie native (donnees fictives, isole).
// Usage (conteneur L1) : php schoolsafe-remediation/tests/cases-u3.php
// Sortie 0 ssi tous les cas passent. Nettoyage verifie (0 residu).
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeStudio.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeCases.fnc.php';

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
check('ecoles-temoins', $schoolB !== $schoolA, true);
$uniq = 'u3' . substr(md5(uniqid('', true)), 0, 8);

// Direction : admin existant sans perimetre (toutes ecoles).
$adminRow = $get("SELECT STAFF_ID FROM staff WHERE STAFF_ID=1 AND SYEAR=$year LIMIT 1");
$admin = isset($adminRow[1]) ? (int) $adminRow[1]['STAFF_ID'] : 0;
check('admin-temoin', $admin > 0, true);

// Fixtures ecole A : enseignant (avec cours), parent, 2 eleves.
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U3', 'Prof', 'teacher', 'u3prof_' . $uniq, $uniq . 't@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U3', 'Mom', 'parent', 'u3mom_' . $uniq, $uniq . 'm@example.invalid', ",$schoolA,"]);
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U3', 'Gard', 'none', 'u3gard_' . $uniq, $uniq . 'g@example.invalid', ",$schoolA,"]);
// Enseignant ecole B : perimetre B, aucun cours en A.
$q('INSERT INTO staff(syear, first_name, last_name, profile, username, email, schools) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, 'U3', 'Etrange', 'teacher', 'u3etrang_' . $uniq, $uniq . 'e@example.invalid', ",$schoolB,"]);
$st = function ($uname) use ($get) {
    $r = $get("SELECT STAFF_ID FROM staff WHERE USERNAME='$uname'");
    return (int) $r[1]['STAFF_ID'];
};
$teacher = $st('u3prof_' . $uniq);
$mom = $st('u3mom_' . $uniq);
$guard = $st('u3gard_' . $uniq);
$stranger = $st('u3etrang_' . $uniq);
// Matiere + cours + periode de cours en A (MP existante de l'ecole A).
$mpRow = $get("SELECT MARKING_PERIOD_ID FROM marking_periods WHERE SCHOOL_ID=$schoolA AND SYEAR=$year LIMIT 1");
$mp = (int) $mpRow[1]['MARKING_PERIOD_ID'];
$q('INSERT INTO course_subjects(syear, school_id, title) VALUES ($1,$2,$3)', [$year, $schoolA, 'U3 Matiere']);
$subjRow = $get("SELECT SUBJECT_ID FROM course_subjects WHERE SCHOOL_ID=$schoolA AND SYEAR=$year AND TITLE='U3 Matiere' LIMIT 1");
$subj = (int) $subjRow[1]['SUBJECT_ID'];
$q('INSERT INTO courses(syear, school_id, subject_id, title, short_name) VALUES ($1,$2,$3,$4,$5)',
    [$year, $schoolA, $subj, 'U3 Cours', 'U3C-' . $uniq]);
$courseRow = $get("SELECT COURSE_ID FROM courses WHERE SHORT_NAME='U3C-$uniq' LIMIT 1");
$course = (int) $courseRow[1]['COURSE_ID'];
$q('INSERT INTO course_periods(syear, school_id, course_id, short_name, marking_period_id, teacher_id, title) VALUES ($1,$2,$3,$4,$5,$6,$7)',
    [$year, $schoolA, $course, 'U3CP-' . $uniq, $mp, $teacher, 'U3 CP']);
$cpRow = $get("SELECT COURSE_PERIOD_ID FROM course_periods WHERE SHORT_NAME='U3CP-$uniq' LIMIT 1");
$cp = (int) $cpRow[1]['COURSE_PERIOD_ID'];
// Eleves A + liens parent + emplois du temps.
$sids = [];
foreach (['Fille', 'Garcon'] as $tag) {
    $q('INSERT INTO students(first_name, last_name, username) VALUES ($1,$2,$3)', ['U3' . $tag, 'Kid', 'u3eleve_' . $tag . '_' . $uniq]);
    $r = $get("SELECT STUDENT_ID FROM students WHERE USERNAME='u3eleve_" . $tag . '_' . $uniq . "'");
    $sid = (int) $r[1]['STUDENT_ID'];
    $sids[] = $sid;
    $q('INSERT INTO student_enrollment(syear, school_id, student_id) VALUES ($1,$2,$3)', [$year, $schoolA, $sid]);
    $q('INSERT INTO students_join_users(student_id, staff_id) VALUES ($1,$2)', [$sid, $mom]);
    $q('INSERT INTO schedule(syear, school_id, student_id, course_id, course_period_id, start_date) VALUES ($1,$2,$3,$4,$5,$6)',
        [$year, $schoolA, $sid, $course, $cp, date('Y-m-d')]);
}

// 1. Profils.
check('profil-parent', SchoolSafeStudioProfile($mom, $year, $db, $schoolA)['role'], 'parent');
check('profil-enseignant', SchoolSafeStudioProfile($teacher, $year, $db, $schoolA)['role'], 'teacher');
check('profil-direction', SchoolSafeStudioProfile($admin, $year, $db, $schoolA)['role'], 'direction');
check('profil-gardien', SchoolSafeStudioProfile($guard, $year, $db, $schoolA)['role'], 'gardien');
check('profil-central', SchoolSafeStudioProfile($admin, $year, $db, 0)['role'], 'central');

// 2. Parent -> direction (admin temoin).
$recips = SchoolSafeStudioAllowedRecipients($mom, $schoolA, $year, $db);
$ids = array_column($recips, 'id');
check('parent-voit-direction', in_array($admin, $ids, true), true);
$msgParent = SchoolSafeStudioSend($mom, $schoolA, $year, "U3 parent $uniq", "Bonjour, message parent $uniq.", [$admin], $db);
check('parent-envoi', is_int($msgParent) && $msgParent > 0, true);

// 3. Enseignant -> parent de sa classe.
$recipsT = SchoolSafeStudioAllowedRecipients($teacher, $schoolA, $year, $db);
check('enseignant-voit-parent', in_array($mom, array_column($recipsT, 'id'), true), true);
$msgTeacher = SchoolSafeStudioSend($teacher, $schoolA, $year, "U3 enseignant $uniq", "Devoir $uniq.", [$mom], $db);
check('enseignant-envoi', is_int($msgTeacher) && $msgTeacher > 0, true);

// 4. Inter-ecoles refuse : parent vers enseignant B.
try {
    SchoolSafeStudioSend($mom, $schoolA, $year, 'X', 'X', [$stranger], $db);
    check('inter-ecoles-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('inter-ecoles-refuse', $e->getMessage(), 'RECIPIENT_DENIED');
}

// 5. Direction : pas d'enseignant de l'autre ecole.
$recipsD = SchoolSafeStudioAllowedRecipients($admin, $schoolA, $year, $db);
check('direction-sans-fuite', in_array($stranger, array_column($recipsD, 'id'), true), false);
check('direction-voit-enseignant-A', in_array($teacher, array_column($recipsD, 'id'), true), true);

// 6. Gardien : aucun destinataire, aucun envoi.
check('gardien-destinataires', SchoolSafeStudioAllowedRecipients($guard, $schoolA, $year, $db), []);
try {
    SchoolSafeStudioSend($guard, $schoolA, $year, 'X', 'X', [$admin], $db);
    check('gardien-envoi-refuse', 'accepte', 'refuse');
} catch (RuntimeException $e) {
    check('gardien-envoi-refuse', $e->getMessage(), 'RECIPIENT_DENIED');
}

// 7. Convocation direction -> parents des eleves.
$conv = SchoolSafeStudioConvocation($admin, $schoolA, $year, $sids, "Convocation $uniq", 'Merci de vous presenter.', date('Y-m-d'), $db);
check('convocation-parents', $conv['parents'], 1);
check('convocation-eleves', $conv['students'], 2);
check('convocation-message', $conv['message_id'] > 0, true);

// 8. Devoir publie -> notification aux parents.
$hw = SchoolSafeStudioHomework($teacher, $schoolA, $year, "Devoir $uniq", 'Exercices de test.', $db);
check('devoir-notifie', $hw['notified'], 1);

// 9. Inbox parent : messages natifs recus.
$inbox = SchoolSafeStudioInbox($mom, $schoolA, $year, $db);
$subjects = [];
foreach ($inbox as $item) {
    if (($item['kind'] ?? '') === 'message') $subjects[] = (string) ($item['subject'] ?? '');
}
check('inbox-parent-enseignant', in_array("U3 enseignant $uniq", $subjects, true), true);
check('inbox-parent-convocation', in_array("[Convocation] Convocation $uniq", $subjects, true), true);

// 10. Lecture = lu natif, sans doublon.
check('lu-natif', SchoolSafeStudioMarkRead($mom, $msgTeacher, $db), true);
check('lu-sans-doublon', SchoolSafeStudioMarkRead($mom, $msgTeacher, $db), false);
$stRow = $get("SELECT STATUS FROM messagexuser WHERE MESSAGE_ID=$msgTeacher AND USER_ID=$mom AND KEY='staff_id' LIMIT 1");
check('statut-lu', $stRow[1]['STATUS'], 'read');

// 11. Reponse : reservee au destinataire, vers l'expediteur.
$replyId = SchoolSafeStudioReply($mom, $msgTeacher, "Recu, merci $uniq.", $db);
check('reponse-creee', $replyId > $msgTeacher, true);
try {
    SchoolSafeStudioReply($teacher, $msgTeacher, 'ne doit pas passer', $db);
    check('reponse-expediteur-refusee', 'acceptee', 'refusee');
} catch (RuntimeException $e) {
    check('reponse-expediteur-refusee', $e->getMessage(), 'REPLY_DENIED');
}

// 12. Central : aucun destinataire.
check('central-verrouille', SchoolSafeStudioAllowedRecipients($admin, 0, $year, $db), []);

// 13. Matrice des boutons par role.
$mp = SchoolSafeStudioButtons('parent');
$mg = SchoolSafeStudioButtons('gardien');
$mc = SchoolSafeStudioButtons('central');
$md = SchoolSafeStudioButtons('direction');
$keys = function ($m) { return array_column($m, 'key'); };
check('matrice-parent', in_array('report', $keys($mp), true) && in_array('compose', $keys($mp), true), true);
check('matrice-parent-sans-decide', in_array('decide', $keys($mp), true), false);
check('matrice-gardien', $keys($mg), ['security', 'mark-read']);
check('matrice-central', $keys($mc), ['health']);
check('matrice-direction-decide', in_array('decide', $keys($md), true) && in_array('convocations', $keys($md), true), true);

// 14. Pas de doublon : deux envois = deux messages, notifs dedup par message.
$dup1 = SchoolSafeStudioSend($teacher, $schoolA, $year, "U3 doublon $uniq", 'corps identique', [$mom], $db);
$dup2 = SchoolSafeStudioSend($teacher, $schoolA, $year, "U3 doublon $uniq", 'corps identique', [$mom], $db);
check('deux-messages-distincts', $dup1 !== $dup2, true);
$cnt = $get("SELECT COUNT(*) AS C FROM messages WHERE SUBJECT='U3 doublon $uniq'");
check('compte-messages', (int) $cnt[1]['C'], 2);

// Nettoyage isole.
$msgs = $get("SELECT MESSAGE_ID FROM messages WHERE SUBJECT LIKE '%$uniq%'");
foreach ($msgs as $row) {
    if (!isset($row['MESSAGE_ID'])) continue;
    $mid = (int) $row['MESSAGE_ID'];
    $q("DELETE FROM messagexuser WHERE MESSAGE_ID=$mid");
    $q("DELETE FROM messages WHERE MESSAGE_ID=$mid");
}
$dedups = ["msg:$msgParent:$admin", "msg:$msgTeacher:$mom",
    'msg:' . $conv['message_id'] . ":$mom", "msg:$replyId:$teacher",
    "msg:$dup1:$mom", "msg:$dup2:$mom", $hw['event']];
foreach ($dedups as $dd) {
    $q('DELETE FROM schoolsafe_notify_deliveries WHERE EVENT_ID IN (SELECT ID FROM schoolsafe_notify_events WHERE DEDUP_KEY=$1)', [$dd]);
    $q('DELETE FROM schoolsafe_notify_events WHERE DEDUP_KEY=$1', [$dd]);
}
$allSids = implode(',', $sids);
$q("DELETE FROM students_join_users WHERE STUDENT_ID IN ($allSids)");
$q("DELETE FROM schedule WHERE STUDENT_ID IN ($allSids)");
$q("DELETE FROM student_enrollment WHERE STUDENT_ID IN ($allSids)");
$q("DELETE FROM students WHERE USERNAME LIKE 'u3eleve\\_%$uniq' ESCAPE '\\'");
$q("DELETE FROM course_periods WHERE SHORT_NAME='U3CP-$uniq'");
$q("DELETE FROM courses WHERE SHORT_NAME='U3C-$uniq'");
$q("DELETE FROM course_subjects WHERE TITLE='U3 Matiere' AND SCHOOL_ID=$schoolA AND SYEAR=$year");
$q("DELETE FROM staff WHERE USERNAME IN ('u3prof_$uniq','u3mom_$uniq','u3gard_$uniq','u3etrang_$uniq')");
echo ($failures === 0 ? "U3 GREEN" : "U3 RED") . "\n";
exit($failures ? 1 : 0);
