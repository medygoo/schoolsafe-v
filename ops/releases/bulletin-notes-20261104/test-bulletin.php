<?php
// Notes de bulletin et synthese (04/11). Partie 1 sans base ; partie 2 (--db) dans une transaction ANNULEE :
// branches de la 1ere Primaire, professeur d'anglais fictif, notes FR/EN, suggestion depuis un devoir,
// calcul, statut, journal ; puis verification que rien ne reste.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
$dbMode = in_array('--db', $argv, true);
if ($dbMode) {
    require_once $root . '/schoolsafe-notify-cli.php';
    $conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
    SchoolSafeNotifyCliInit($conn);
} else { function DBEscapeString($v) { return str_replace("'", "''", (string) $v); } }
require_once $root . '/ProgramFunctions/SchoolSafeBulletin.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

check('moyenne FR/EN', SchoolSafeBulletinCombine(8, 6, 10, true, 'moyenne'), [7.0, 10.0]);
check('somme FR/EN', SchoolSafeBulletinCombine(8, 6, 10, true, 'somme'), [14.0, 20.0]);
check('une seule note (moyenne)', SchoolSafeBulletinCombine(8, null, 10, true, 'moyenne'), [8.0, 10.0]);
check('une seule note (somme, meme echelle)', SchoolSafeBulletinCombine(null, 6, 10, true, 'somme'), [12.0, 20.0]);
check('branche sans anglais en somme', SchoolSafeBulletinCombine(8, null, 10, false, 'somme'), [8.0, 10.0]);
check('aucune note', SchoolSafeBulletinCombine(null, null, 10, true, 'moyenne'), [null, 10.0]);
check('note valide', SchoolSafeBulletinParse('7,5', 10), '7.5');
check('note vide', SchoolSafeBulletinParse(' ', 10), null);
check('note au-dessus du maxima', fails(fn() => SchoolSafeBulletinParse('12', 10)), 'Note invalide : 12 (entre 0 et 10).');

$terms = [16 => ['id' => 16, 'title' => '1er trimestre', 'periods' => [18 => '1re période', 19 => '2e période']]];
check('colonnes RDC', array_keys(SchoolSafeBulletinSlots($terms)), ['P:18', 'P:19', 'E:16', 'T:16', 'TG']);
$branches = [['COURSE_PERIOD_ID' => '100', 'TEACHER_EN' => '50', 'MAX_PERIOD' => '10', 'MAX_EXAM' => null], ['COURSE_PERIOD_ID' => '101', 'TEACHER_EN' => null, 'MAX_PERIOD' => '20', 'MAX_EXAM' => null]];
$marks = [100 => [1 => ['P:18' => ['fr' => 8, 'en' => 6], 'P:19' => ['fr' => 10], 'E:16' => ['fr' => 16, 'en' => 14]], 2 => ['P:18' => ['fr' => 5]]],
    101 => [1 => ['P:18' => ['fr' => 15], 'P:19' => ['fr' => 18], 'E:16' => ['fr' => 30]], 2 => ['P:18' => ['fr' => 20], 'P:19' => ['fr' => 20], 'E:16' => ['fr' => 40]]]];
$c = SchoolSafeBulletinCompute($branches, [1 => 'Ange', 2 => 'Béni'], $marks, $terms, 'moyenne');
$a = $c['students'][1];
check('branche 100 eleve 1 : periodes, examen, total', [$a['cells'][100]['P:18'], $a['cells'][100]['P:19'], $a['cells'][100]['E:16'], $a['cells'][100]['T:16']], [[7.0, 10.0], [10.0, 10.0], [15.0, 20.0], [32.0, 40.0]]);
check('total du trimestre (2 branches)', $a['totals']['T:16'], [95.0, 120.0, true]);
check('pourcentage', $a['pct']['T:16'], 79.2);
check('notes manquantes eleve 2', $c['students'][2]['missing'][16], 2);
check('places (79,2 % premier, 70,8 % deuxieme ; une note manquante compte 0)', [$c['students'][2]['rank']['T:16'], $a['rank']['T:16'], $c['students'][2]['pct']['T:16']], [2, 1, 70.8]);
check('total general = trimestre', $a['totals']['TG'], [95.0, 120.0, true]);
check('decisions RDC', array_keys(SchoolSafeBulletinDecisions()), ['passe', 'double', 'repechage', 'echoue', 'oriente']);
foreach (['notes-bulletin.php', 'synthese-bulletin.php', 'branches-classe.php', 'central-navigation.php', 'ProgramFunctions/SchoolSafeBulletin.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}

if ($dbMode) {
    $get = function ($q) { return DBGet($q); };
    $db = ['get' => $get, 'exec' => function ($q) { DBQuery($q); }];
    $before = (int) DBGet("SELECT count(*) AS N FROM schoolsafe_grade_journal")[1]['N'];
    DBQuery('BEGIN');
    try {
        foreach (['064-class-branches', '065-bulletin-marks'] as $mig) {
            $table = $mig === '064-class-branches' ? 'schoolsafe_class_branches' : 'schoolsafe_bulletin_marks';
            if (!DBGet("SELECT to_regclass('public." . $table . "') AS T")[1]['T'])
                pg_query($conn, preg_replace('/^(BEGIN|COMMIT);\s*$/m', '', file_get_contents($root . '/schoolsafe-remediation/' . $mig . '.sql'))) or throw new RuntimeException('migration ' . $mig);
        }
        $en = (int) DBGet("INSERT INTO staff(SYEAR,FIRST_NAME,LAST_NAME,PROFILE,SCHOOLS) VALUES(2026,'Essai','Anglais','teacher',',3,') RETURNING STAFF_ID")[1]['STAFF_ID'];
        SchoolSafeBranchSave(3, 20, SchoolSafeBranchValidateRows(['b_domain' => ['Langues', 'Mathématiques'], 'b_title' => ['Lecture', 'Calcul'], 'b_short' => ['LECT', 'CALC'], 'b_max' => ['10', '20']]), 6, $db);
        SchoolSafeBranchApply(3, 2026, 4, 20, 12, $en, 6, $db);
        $br = SchoolSafeBulletinBranches(4, $get);
        check('branches de la classe avec professeur d anglais', [count($br), (int) $br[1]['TEACHER_EN']], [2, $en]);
        $tc = SchoolSafeBulletinTeacherClasses($en, 3, 2026, $get);
        check('le professeur d anglais note en anglais', [count($tc), $tc[1]['LANG']], [1, 'en']);
        $lect = (int) $br[1]['COURSE_PERIOD_ID'];
        $type = (int) DBGet("SELECT ASSIGNMENT_TYPE_ID FROM gradebook_assignment_types WHERE COURSE_ID=" . (int) DBGet("SELECT COURSE_ID FROM course_periods WHERE COURSE_PERIOD_ID=" . $lect)[1]['COURSE_ID'] . " AND STAFF_ID=12 AND TITLE='Devoir'")[1]['ASSIGNMENT_TYPE_ID'];
        $as = (int) DBGet("INSERT INTO gradebook_assignments(ASSIGNMENT_TYPE_ID,STAFF_ID,MARKING_PERIOD_ID,COURSE_PERIOD_ID,TITLE,ASSIGNED_DATE,DUE_DATE,POINTS) VALUES(" . $type . ",12,19," . $lect . ",'Essai lecture','2026-11-03','2026-11-04',20) RETURNING ASSIGNMENT_ID")[1]['ASSIGNMENT_ID'];
        DBQuery("INSERT INTO gradebook_grades(STUDENT_ID,COURSE_PERIOD_ID,ASSIGNMENT_ID,POINTS) VALUES(4," . $lect . "," . $as . ",15)");
        check('suggestion depuis les devoirs : 15/20 -> 7,5/10', SchoolSafeBulletinSuggest($lect, 12, 'periode', 19, 10, $get), [4 => 7.5]);
        foreach ([['fr', 12, 8], ['en', $en, 6]] as [$lang, $who, $pts])
            DBQuery("INSERT INTO schoolsafe_bulletin_marks(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,BRANCH_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,KIND,LANG,POINTS,ENTERED_BY) VALUES(3,2026,4," . $lect . ",4,19,'periode','" . $lang . "'," . $pts . "," . $who . ")");
        DBQuery("UPDATE schoolsafe_bulletin_marks SET POINTS=9 WHERE BRANCH_COURSE_PERIOD_ID=" . $lect . " AND LANG='fr'");
        $terms = SchoolSafeBulletinTerms(3, 2026, $get);
        check('3 trimestres de 2 periodes', [count($terms), count($terms[16]['periods'] ?? [])], [3, 2]);
        $calc = SchoolSafeBulletinCompute($br, SchoolSafeBulletinStudents(4, $get), SchoolSafeBulletinMarks(4, $get), $terms, SchoolSafeBulletinLangMode(3, $get));
        check('Josue : lecture 2e periode = moyenne (9 + 6) / 2', $calc['students'][4]['cells'][$lect]['P:19'], [7.5, 10.0]);
        check('journal : 2 ajouts + 1 modification', (int) DBGet("SELECT count(*) AS N FROM schoolsafe_grade_journal")[1]['N'] - $before >= 3, true);
        DBQuery("INSERT INTO schoolsafe_bulletin_status(SCHOOL_ID,SYEAR,CLASS_COURSE_PERIOD_ID,STUDENT_ID,MARKING_PERIOD_ID,APPLICATION,CONDUITE,STATUS,UPDATED_BY) VALUES(3,2026,4,4,16,'Bien','Très bien','valide',6)");
        check('statut valide lu', SchoolSafeBulletinStatus(4, $get)[4][16]['STATUS'], 'valide');
    } catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
    finally { DBQuery('ROLLBACK'); }
    check('rien n est reste en base', [(int) DBGet("SELECT count(*) AS N FROM schoolsafe_grade_journal")[1]['N'], (int) DBGet("SELECT count(*) AS N FROM staff WHERE LAST_NAME='Anglais' AND FIRST_NAME='Essai'")[1]['N']], [$before, 0]);
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
