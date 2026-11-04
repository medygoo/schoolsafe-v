<?php
// Branches et maxima (04/11). Partie 1 sans base ; partie 2 (--db) : migration 064 + liste primaire +
// application a la 1ere Primaire (cours 4) dans une transaction ANNULEE, puis verification que rien ne reste.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeBranches.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }
$db_mode = in_array('--db', $argv, true);
if ($db_mode) {
    require_once $root . '/schoolsafe-notify-cli.php';
    $conn = pg_connect('host=' . (getenv('SCHOOLSAFE_DB_HOST') ?: 'db') . ' port=' . (getenv('SCHOOLSAFE_DB_PORT') ?: '5432') . ' dbname=' . getenv('POSTGRES_DB') . ' user=' . getenv('POSTGRES_USER') . ' password=' . getenv('POSTGRES_PASSWORD') . ' connect_timeout=5');
    SchoolSafeNotifyCliInit($conn);
} elseif (!function_exists('DBEscapeString')) { function DBEscapeString($v) { return str_replace("'", "''", (string) $v); } }

check('liste primaire : 20 branches', count(SchoolSafeBranchDefaults('primaire')), 20);
check('liste humanites : 13 branches', count(SchoolSafeBranchDefaults('humanites')), 13);
check('examen = double par defaut', SchoolSafeBranchExamMax('10', null), 20.0);
check('examen choisi', SchoolSafeBranchExamMax('10', '30'), 30.0);
$post = ['b_id' => ['', '5', ''], 'b_domain' => ['Langues', 'Maths', ''], 'b_title' => [' Lecture ', 'Calcul', ''], 'b_short' => ['', 'CALC', ''], 'b_max' => ['10', '20,5', ''], 'b_exam' => ['', '40', ''], 'b_off' => ['1']];
$rows = SchoolSafeBranchValidateRows($post);
check('lignes valides', [count($rows), $rows[0]['title'], $rows[0]['short'], $rows[1]['max'], $rows[1]['exam'], $rows[0]['active'], $rows[1]['active'], $rows[1]['id']], [2, 'Lecture', 'LECTUR', '20.5', '40', true, false, 5]);
check('maxima obligatoire', fails(fn() => SchoolSafeBranchValidateRows(['b_title' => ['Lecture'], 'b_max' => ['']])), 'Maxima de période invalide pour Lecture.');
check('branche en double', fails(fn() => SchoolSafeBranchValidateRows(['b_title' => ['Lecture', 'lecture'], 'b_max' => ['10', '10']])), 'Branche en double : lecture.');
check('examen invalide', fails(fn() => SchoolSafeBranchValidateRows(['b_title' => ['Lecture'], 'b_max' => ['10'], 'b_exam' => ['abc']])), 'Maxima d’examen invalide pour Lecture.');
$plan = SchoolSafeBranchPlan([['ID' => '1', 'ACTIVE' => 't', 'TITLE' => 'A'], ['ID' => '2', 'ACTIVE' => 'f', 'TITLE' => 'B'], ['ID' => '3', 'ACTIVE' => 't', 'TITLE' => 'C']], [3 => true]);
check('apercu : creer, garder, ignorer desactivee', [count($plan['create']), $plan['create'][0]['TITLE'], count($plan['keep'])], [1, 'A', 1]);
foreach (['branches-classe.php', 'ma-journee.php', 'suivi-pedagogique.php', 'central-navigation.php', 'ProgramFunctions/SchoolSafeBranches.fnc.php', 'ProgramFunctions/SchoolSafeClassDay.fnc.php', 'ProgramFunctions/SchoolSafePedagogyFollowup.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}

if ($db_mode) {
    require_once $root . '/ProgramFunctions/SchoolSafeClassDay.fnc.php';
    require_once $root . '/ProgramFunctions/SchoolSafePedagogyFollowup.fnc.php';
    $get = function ($q) { return DBGet($q); };
    $db = ['get' => $get, 'exec' => function ($q) { DBQuery($q); }];
    $before = (int) DBGet("SELECT count(*) AS N FROM courses")[1]['N'];
    DBQuery('BEGIN');
    try {
        if (!DBGet("SELECT to_regclass('public.schoolsafe_class_branches') AS T")[1]['T']) {
            $sql = file_get_contents($root . '/schoolsafe-remediation/064-class-branches.sql');
            pg_query($conn, preg_replace('/^(BEGIN|COMMIT);\s*$/m', '', $sql)) or throw new RuntimeException('migration');
        }
        $defaults = SchoolSafeBranchDefaults('primaire');
        $post = ['b_domain' => array_column($defaults, 0), 'b_title' => array_column($defaults, 1), 'b_short' => array_column($defaults, 2), 'b_max' => array_map('strval', array_column($defaults, 3))];
        check('liste enregistree', SchoolSafeBranchSave(3, 20, SchoolSafeBranchValidateRows($post), 6, $db), 20);
        check('enseignant anglais = titulaire refuse', fails(fn() => SchoolSafeBranchApply(3, 2026, 4, 20, 12, 12, 6, $db)), 'Le professeur d’anglais doit être un autre enseignant de l’école.');
        $out = SchoolSafeBranchApply(3, 2026, 4, 20, 12, 0, 6, $db);
        check('20 branches creees pour la 1ere Primaire, 1 eleve inscrit dans chacune', $out, ['created' => 20, 'kept' => 0, 'enrolled' => 20]);
        check('rejouer : aucun doublon', SchoolSafeBranchApply(3, 2026, 4, 20, 12, 0, 6, $db), ['created' => 0, 'kept' => 20, 'enrolled' => 0]);
        $types = (int) DBGet("SELECT count(*) AS N FROM gradebook_assignment_types t JOIN schoolsafe_class_branches b ON b.COURSE_ID=t.COURSE_ID WHERE b.CLASS_COURSE_PERIOD_ID=4 AND t.STAFF_ID=12")[1]['N'];
        check('types de devoirs du titulaire sur chaque branche', $types >= 20 && $types % 20 === 0, true);
        check('aucune branche ne fait l appel', (int) DBGet("SELECT count(*) AS N FROM course_periods cp JOIN schoolsafe_class_branches b USING(COURSE_PERIOD_ID) WHERE b.CLASS_COURSE_PERIOD_ID=4 AND COALESCE(cp.DOES_ATTENDANCE,'')<>''")[1]['N'], 0);
        $day = SchoolSafeClassDayClasses(12, 3, 2026, '2026-11-05', $get);
        check('Ma journee : seulement les classes (pas les branches)', count($day) <= 3, true);
        $rows = SchoolSafeFollowupRows(3, 2026, '2026-11-01', '2026-11-05', '2026-11-05', $get);
        $branchRows = array_values(array_filter($rows, static function ($r) { return in_array($r['IS_BRANCH'], ['t', true, '1'], true); }));
        check('suivi : branches visibles', count($branchRows), 20);
        check('suivi : pas de relance cahier pour une branche', SchoolSafeFollowupAlerts($branchRows[0]), []);
        check('classe principale non listee comme branche', count(SchoolSafeBranchClasses(3, 2026, $get)), 3);
    } catch (Throwable $x) { $ko++; echo 'KO   exception : ' . $x->getMessage() . "\n"; }
    finally { DBQuery('ROLLBACK'); }
    check('rien n est reste en base', (int) DBGet("SELECT count(*) AS N FROM courses")[1]['N'], $before);
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
