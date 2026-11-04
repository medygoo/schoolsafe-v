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
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
