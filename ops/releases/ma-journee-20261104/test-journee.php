<?php
// « Ma journée de classe » (04/11). Usage : php test-journee.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
require_once $root . '/ProgramFunctions/SchoolSafeClassDay.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

check('jeudi = H', SchoolSafeClassDayLetter('2026-11-05'), 'H');
check('lundi = M', SchoolSafeClassDayLetter('2026-11-02'), 'M');
check('dimanche = U', SchoolSafeClassDayLetter('2026-11-08'), 'U');
// Codes reels de l'ecole 3
$codes = SchoolSafeClassDayCodes([
    1 => ['ID' => 9, 'TITLE' => 'Absent', 'SHORT_NAME' => 'A', 'TYPE' => 'teacher', 'STATE_CODE' => 'A', 'DEFAULT_CODE' => ''],
    2 => ['ID' => 10, 'TITLE' => 'Present', 'SHORT_NAME' => 'P', 'TYPE' => 'teacher', 'STATE_CODE' => 'P', 'DEFAULT_CODE' => 'Y'],
    3 => ['ID' => 11, 'TITLE' => 'Tardy', 'SHORT_NAME' => 'T', 'TYPE' => 'teacher', 'STATE_CODE' => 'P', 'DEFAULT_CODE' => ''],
    4 => ['ID' => 12, 'TITLE' => 'Excused Absence', 'SHORT_NAME' => 'E', 'TYPE' => 'official', 'STATE_CODE' => 'A', 'DEFAULT_CODE' => '']]);
check('codes de l ecole reconnus', $codes, ['absent' => 9, 'present' => 10, 'late' => 11, 'excused' => 12]);
check('arrivee : non scanne', SchoolSafeClassDayArrival(null, '07:45:00'), ['non_scanne', '']);
check('arrivee : a l heure', SchoolSafeClassDayArrival('2026-11-05 07:31:10', '07:45:00'), ['a_l_heure', '07:31']);
check('arrivee : en retard', SchoolSafeClassDayArrival('2026-11-05 07:52:00', '07:45:00'), ['retard', '07:52']);
check('arrivee : sans horaire', SchoolSafeClassDayArrival('2026-11-05 09:00:00', null), ['a_l_heure', '09:00']);
check('defaut : present', SchoolSafeClassDayDefault($codes, null, 'non_scanne'), 'present');
check('defaut : retard si scanne en retard', SchoolSafeClassDayDefault($codes, null, 'retard'), 'late');
check('defaut : appel deja fait garde', SchoolSafeClassDayDefault($codes, 9, 'a_l_heure'), 'absent');
check('appel : choix gardes', SchoolSafeClassDayValidate(['a_3' => 'absent', 'a_4' => 'late'], [3, 4, 5], $codes), [3 => 9, 4 => 11, 5 => 10]);
check('appel : eleve hors classe ignore', SchoolSafeClassDayValidate(['a_3' => 'present', 'a_99' => 'absent'], [3], $codes), [3 => 10]);
check('appel : choix inconnu refuse', fails(fn() => SchoolSafeClassDayValidate(['a_3' => 'parti'], [3], $codes)), 'Choix de présence invalide.');
check('date : aujourd hui par defaut', SchoolSafeClassDayDate(null, '2026-11-05'), '2026-11-05');
check('date : 3 jours avant permise', SchoolSafeClassDayDate('2026-11-02', '2026-11-05'), '2026-11-02');
check('date : avenir refuse', SchoolSafeClassDayDate('2026-11-06', '2026-11-05'), '2026-11-05');
check('date : trop ancienne refusee', SchoolSafeClassDayDate('2026-10-20', '2026-11-05'), '2026-11-05');
check('date : invalide', SchoolSafeClassDayDate('2026-13-01', '2026-11-05'), '2026-11-05');
$q = '';
SchoolSafeClassDayClasses(12, 3, 2026, '2026-11-05', function ($s) use (&$q) { $q = $s; return []; });
check('classes : seulement celles de l enseignant', strpos($q, '(cp.TEACHER_ID=12 OR cp.SECONDARY_TEACHER_ID=12)') !== false, true);
check('classes : jour de la semaine', strpos($q, "position('H' in cpsp.DAYS)>0") !== false, true);
SchoolSafeClassDayRoster(4, 23, 3, 2026, '2026-11-05', function ($s) use (&$q) { $q = $s; return []; });
check('liste : inscrits ce jour', strpos($q, "ss.START_DATE<='2026-11-05' AND (ss.END_DATE IS NULL OR ss.END_DATE>='2026-11-05')") !== false, true);
check('liste : premier scan d entree du jour', strpos($q, "x.TYPE='in' AND x.RECORDED_AT::date='2026-11-05'") !== false, true);

$page = file_get_contents($root . '/ma-journee.php');
check('page : enseignants seulement', strpos($page, "User('PROFILE')==='teacher'") !== false, true);
check('page : classe de l enseignant ce jour-la', strpos($page, "if(!isset(\$byId[\$cp]))throw new RuntimeException('Cette classe n’est pas la vôtre ce jour-là.');") !== false, true);
check('page : droit natif de l appel', strpos($page, "AllowUse('Attendance/TakeAttendance.php')") !== false, true);
check('page : jeton', strpos($page, 'hash_equals($csrf') !== false, true);
check('page : ecriture native et presence du jour', strpos($page, 'INSERT INTO attendance_period(') !== false && strpos($page, 'UpdateAttendanceDaily($student,$date);') !== false && strpos($page, 'INSERT INTO attendance_completed(') !== false, true);
check('page : code officiel de la direction respecte', strpos($page, "(\$current[1]['ADMIN']??'')!=='Y'") !== false, true);
check('page : transaction annulee en cas d erreur', strpos($page, "if(\$inTx)DBQuery('ROLLBACK');") !== false, true);
check('page : jour de classe exige', strpos($page, "if(!\$schoolDay)throw new RuntimeException('Ce jour n’est pas un jour de classe.');") !== false, true);
check('migration 058', strpos(file_get_contents($root . '/schoolsafe-remediation/058-class-diary.sql'), 'UNIQUE (course_period_id, school_date)') !== false, true);
check('menu : lien enseignant', strpos(file_get_contents($root . '/central-navigation.php'), "'Ma journée de classe', 'ma-journee.php'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
