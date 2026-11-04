<?php
// « Preparer l'annee » (03/11) : etat des etapes sur des donnees simulees. Usage : php test-preparer-annee.php /var/www/html
require_once ($argv[1] ?? '/var/www/html') . '/ProgramFunctions/SchoolSafeYearSetup.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fake(array $data) { uksort($data, fn($a, $b) => strlen($b) - strlen($a)); return function ($sql) use ($data) { foreach ($data as $needle => $rows) if (strpos($sql, $needle) !== false) return $rows; return []; }; }
$state = fn($steps) => array_column($steps, 'state', 'id');
// Ecole vide : tout est a faire.
$s = $state(SchoolSafeYearSetupSteps(3, 2026, fake([])));
check('ecole vide : 10 etapes', count($s), 10);
check('ecole vide : rien de fait', array_unique(array_values($s)), ['todo']);
// Ecole prete.
$ready = fake([
  'FROM schools' => [1 => ['TITLE' => 'Le Sage', 'ADDRESS' => 'Kinshasa', 'PHONE' => '+243', 'PRINCIPAL' => 'Direction']],
  'FROM school_marking_periods' => [1 => ['MP' => 'FY', 'TITLE' => 'Année scolaire', 'START_DATE' => '2026-09-01', 'END_DATE' => '2027-07-02'], 2 => ['MP' => 'QTR', 'TITLE' => '1er trimestre', 'START_DATE' => '2026-09-01', 'END_DATE' => '2026-12-20']],
  'FROM attendance_calendar' => [1 => ['N' => 200]],
  'FROM school_periods' => [1 => ['TITLE' => 'Journée']],
  'FROM school_gradelevels' => [1 => ['TITLE' => '1ère', 'NEXT_GRADE_ID' => '2'], 2 => ['TITLE' => '2ème', 'NEXT_GRADE_ID' => '']],
  'FROM course_subjects' => [1 => ['TITLE' => '|fr_FR.utf8:Français']],
  'FROM courses' => [1 => ['N' => 3]],
  'FROM course_periods WHERE' => [1 => ['N' => 3, 'SANS' => 0]],
  'SYEAR=2026 AND GRADE_SCALE_ID IS NULL' => [1 => ['N' => 0]],
  'FROM report_card_grade_scales' => [1 => ['TITLE' => 'Sur 20']],
  'FROM gradebook_assignment_types' => [1 => ['TITLE' => 'Devoir'], 2 => ['TITLE' => 'Examen']],
  'FROM schoolsafe_attendance_policy' => [1 => ['N' => 9]],
  'FROM student_enrollment' => [1 => ['N' => 10]],
  'FROM schedule' => [1 => ['N' => 10]],
]);
$steps = SchoolSafeYearSetupSteps(3, 2026, $ready);
check('ecole prete : tout fait', array_unique(array_values($state($steps))), ['ok']);
check('ecole prete : 10 / 10', SchoolSafeYearSetupProgress($steps), [10, 10]);
check('titre multilingue lisible', SchoolSafeYearSetupTitle('|fr_FR.utf8:Botanique'), 'Botanique');
// Corrections detectees.
$warn = fake([
  'FROM school_marking_periods' => [1 => ['MP' => 'FY', 'TITLE' => 'Full Year', 'START_DATE' => '2026-06-13', 'END_DATE' => '2027-06-12'], 2 => ['MP' => 'FY', 'TITLE' => 'Année', 'START_DATE' => '2026-09-01', 'END_DATE' => '2027-07-31']],
  'FROM school_periods' => [1 => ['TITLE' => 'Full Day']],
  'FROM report_card_grade_scales' => [1 => ['TITLE' => 'Main']],
  'FROM student_enrollment' => [1 => ['N' => 10]], 'FROM schedule' => [1 => ['N' => 1]],
  'FROM gradebook_assignment_types' => [],
  'FROM course_subjects' => [1 => ['TITLE' => 'Maths']], 'FROM courses' => [1 => ['N' => 1]], 'FROM course_periods WHERE' => [1 => ['N' => 2, 'SANS' => 1]],
]);
$w = SchoolSafeYearSetupSteps(3, 2026, $warn); $ws = $state($w);
check('deux annees + anglais -> a corriger', $ws['periodes'], 'warn');
check('detail deux annees', strpos($w[1]['detail'], '2 années complètes') !== false, true);
check('tranche anglaise -> a corriger', $ws['tranches'], 'warn');
check('bareme Main -> a corriger', $ws['baremes'], 'warn');
check('1 eleve place sur 10 -> a corriger', $ws['programmation'], 'warn');
check('classe sans enseignant -> a corriger', $ws['cours'], 'warn');
check('aucun type de devoir -> a faire', $ws['types'], 'todo');
check('bouton types -> creation en masse', $w[7]['url'], 'Modules.php?modname=Grades/MassCreateAssignments.php&assignment_type=new');
// Decoupage de l'annee : modeles, dates, erreurs.
$count = function ($plan) { $q = 0; foreach ($plan['sems'] as $sem) $q += count($sem['qtrs']); return [count($plan['sems']), $q]; };
check('3 trimestres : 1 bloc, 3 periodes', $count(SchoolSafeYearPlan('trimestres', '2026-09-01', '2027-07-02')), [1, 3]);
check('primaire RDC : 3 trimestres, 6 periodes', $count(SchoolSafeYearPlan('trimestres_periodes', '2026-09-01', '2027-07-02')), [3, 6]);
check('secondaire RDC : 2 semestres, 4 periodes', $count(SchoolSafeYearPlan('semestres_periodes', '2026-09-01', '2027-07-02')), [2, 4]);
check('2 semestres : 2 blocs, 2 periodes', $count(SchoolSafeYearPlan('semestres', '2026-09-01', '2027-07-02')), [2, 2]);
$p = SchoolSafeYearPlan('trimestres_periodes', '2026-09-01', '2027-07-02');
check('premiere periode commence le premier jour', $p['sems'][0]['qtrs'][0]['start'], '2026-09-01');
check('derniere periode finit le dernier jour', $p['sems'][2]['qtrs'][1]['end'], '2027-07-02');
check('periodes contigues', SchoolSafeYearDate($p['sems'][0]['qtrs'][0]['end'])->modify('+1 day')->format('Y-m-d'), $p['sems'][0]['qtrs'][1]['start']);
check('trimestre englobe ses periodes', [$p['sems'][1]['start'], $p['sems'][1]['end']], [$p['sems'][1]['qtrs'][0]['start'], $p['sems'][1]['qtrs'][1]['end']]);
check('nom de l annee', $p['fy']['title'], 'Année scolaire 2026-2027');
$c = SchoolSafeYearPlan('trimestres', '2026-09-01', '2027-07-02', [0 => ['2026-09-01', '2026-12-18'], 1 => ['2027-01-04', '2027-03-26'], 2 => ['2027-04-06', '2027-07-02']]);
check('dates choisies par l ecole gardees', $c['sems'][0]['qtrs'][1]['start'], '2027-01-04');
$err = function ($f) { try { $f(); return null; } catch (RuntimeException $e) { return $e->getMessage(); } };
check('fin avant debut refusee', is_string($err(fn() => SchoolSafeYearPlan('trimestres', '2027-07-02', '2026-09-01'))), true);
check('chevauchement refuse', strpos((string) $err(fn() => SchoolSafeYearPlan('trimestres', '2026-09-01', '2027-07-02', [0 => ['2026-09-01', '2027-01-10'], 1 => ['2027-01-04', '2027-03-26'], 2 => ['2027-04-06', '2027-07-02']])), 'chevauche') !== false, true);
check('periode hors annee refusee', strpos((string) $err(fn() => SchoolSafeYearPlan('trimestres', '2026-09-01', '2027-07-02', [2 => ['2027-04-06', '2027-08-30']])), 'sort de') !== false, true);
check('modele inconnu refuse', is_string($err(fn() => SchoolSafeYearPlan('libre', '2026-09-01', '2027-07-02'))), true);
check('date impossible refusee', is_string($err(fn() => SchoolSafeYearPlan('trimestres', '2026-02-30', '2027-07-02'))), true);
$us = SchoolSafeYearSetupSteps(3, 2026, fake(['FROM report_card_grade_scales' => [1 => ['TITLE' => 'Barème principal']], 'FROM report_card_grades' => [1 => ['TITLE' => 'A+'], 2 => ['TITLE' => 'F']]]));
check('bareme americain A+ a F -> a corriger', $state($us)['baremes'], 'warn');
$ns = SchoolSafeYearSetupSteps(3, 2026, fake(['FROM report_card_grade_scales' => [1 => ['TITLE' => 'Sur 20']], 'SYEAR=2026 AND GRADE_SCALE_ID IS NULL' => [1 => ['N' => 1]]]));
check('classe sans bareme -> a corriger', $state($ns)['baremes'], 'warn');
check('bouton choisir le bareme', $ns[6]['tool'][0], 'bareme-annee.php');
foreach (SchoolSafeScaleModels() as $id => $m) {
    $th = array_column($m['grades'], 2);
    $sorted = $th; rsort($sorted);
    check("bareme $id : seuils decroissants", $th, $sorted);
    check("bareme $id : derniere mention a 0 %", end($th), 0);
    check("bareme $id : reussite a 50 %", in_array(50, $th, true), true);
}
// Bareme RDC et mentions modifiables par l'ecole.
$rdc = SchoolSafeScaleModels()['rdc']['grades'];
check('RDC : Elite a 80 %', [$rdc[0][0], $rdc[0][2]], ['E', 80]);
check('RDC : seuils 80/70/60/50/0', array_column($rdc, 2), [80, 70, 60, 50, 0]);
$v = SchoolSafeScaleValidate([['E', 'Élite', '85'], ['GD', 'Grande distinction', '70,5'], ['', '', ''], ['ECH', 'Échec', '0']], 100);
check('mentions modifiees gardees, ligne vide retiree', array_column($v, 0), ['E', 'GD', 'ECH']);
check('seuil avec virgule accepte', $v[1][2], 70.5);
check('moyenne au milieu de la tranche', $v[0][3], 92.5);
check('moyenne a l echelle sur 20', SchoolSafeScaleValidate([['TB', 'Très bien', '80'], ['I', 'Insuffisant', '0']], 20)[0][3], 18.0);
$verr = function ($rows) { try { SchoolSafeScaleValidate($rows, 100); return null; } catch (RuntimeException $e) { return $e->getMessage(); } };
check('seuils dans le desordre refuses', is_string($verr([['A', 'a', '50'], ['B', 'b', '70'], ['C', 'c', '0']])), true);
check('code en double refuse', is_string($verr([['A', 'a', '50'], ['A', 'b', '0']])), true);
check('code NE reserve', is_string($verr([['NE', 'x', '50'], ['A', 'b', '0']])), true);
check('derniere mention pas a 0 % refusee', is_string($verr([['A', 'a', '70'], ['B', 'b', '40']])), true);
check('une seule mention refusee', is_string($verr([['A', 'a', '0']])), true);
check('seuil hors 0-100 refuse', is_string($verr([['A', 'a', '120'], ['B', 'b', '0']])), true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
