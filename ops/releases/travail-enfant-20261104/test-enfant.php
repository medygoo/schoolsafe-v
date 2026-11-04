<?php
// « Travail de mon enfant » (04/11). Usage : php test-enfant.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
require_once $root . '/ProgramFunctions/SchoolSafeChildWork.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

$q = '';
SchoolSafeChildWorkChildren('parent', 27, 0, 3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('parent : seulement ses enfants', strpos($q, 'JOIN students_join_users su ON su.STUDENT_ID=s.STUDENT_ID AND su.STAFF_ID=27') !== false, true);
check('parent : inscrits dans l ecole et l annee', strpos($q, 'e.SCHOOL_ID=3 AND e.SYEAR=2026 AND (e.END_DATE IS NULL OR e.END_DATE>=CURRENT_DATE)') !== false, true);
SchoolSafeChildWorkChildren('student', 0, 16, 3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('eleve : lui-meme', strpos($q, 'WHERE s.STUDENT_ID=16') !== false, true);
check('enseignant : rien', SchoolSafeChildWorkChildren('teacher', 12, 0, 3, 2026, function ($s) { throw new Exception('requete'); }), []);
$kids = [1 => ['STUDENT_ID' => 3, 'FIRST_NAME' => 'Grâce'], 2 => ['STUDENT_ID' => 4, 'FIRST_NAME' => 'Josué']];
check('choix : enfant demande', SchoolSafeChildWorkPick('4', $kids)['FIRST_NAME'], 'Josué');
check('choix : enfant d un autre parent refuse', SchoolSafeChildWorkPick('99', $kids)['FIRST_NAME'], 'Grâce');
check('choix : aucun enfant', SchoolSafeChildWorkPick('3', []), null);
SchoolSafeChildWorkHomework(4, [['COURSE_PERIOD_ID' => 4]], function ($s) use (&$q) { $q = $s; return []; });
check('devoirs : deja donnes seulement', strpos($q, '(ga.ASSIGNED_DATE IS NULL OR ga.ASSIGNED_DATE<=CURRENT_DATE)') !== false, true);
check('devoirs : classes de l enfant', strpos($q, 'cp.COURSE_PERIOD_ID IN (4)') !== false, true);
check('devoirs : sans classe, aucune requete', SchoolSafeChildWorkHomework(4, [], function ($s) { throw new Exception('requete'); }), []);
SchoolSafeChildWorkGrades(4, 3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('notes : de l enfant, 30 jours', strpos($q, 'gg.STUDENT_ID=4') !== false && strpos($q, "interval '30 days'") !== false, true);
SchoolSafeChildWorkAttendance(4, 3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('presence : sans les presents', strpos($q, "COALESCE(ac.DEFAULT_CODE,'')<>'Y'") !== false, true);
check('code : absent', SchoolSafeChildWorkCode('Absent', 'A', 'teacher'), 'Absent');
check('code : retard', SchoolSafeChildWorkCode('Tardy', 'P', 'teacher'), 'Retard');
check('code : excuse', SchoolSafeChildWorkCode('Excused Absence', 'A', 'official'), 'Absence excusée');
check('note : 15 / 20', SchoolSafeChildWorkMark('15.00', '20.00'), '15 / 20');
check('note : decimale', SchoolSafeChildWorkMark('12.5', '20'), '12,5 / 20');
check('note : dispense', SchoolSafeChildWorkMark('-1', '20'), 'dispensé');
check('note : aucune', SchoolSafeChildWorkMark(null, '20'), '');

$page = file_get_contents($root . '/travail-enfant.php');
check('page : parents et eleves seulement', strpos($page, "in_array(\$profile,['parent','student'],true)") !== false, true);
check('page : lecture seule (GET)', strpos($page, "if((\$_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}") !== false, true);
check('page : fiche au nom de l enfant', strpos($page, '&amp;student=<?=$sid?>&amp;download=1') !== false, true);
check('page : choix d enfant valide avant changement', strpos($page, "\$child=SchoolSafeChildWorkPick(") < strpos($page, 'SetUserStudentID('), true);
check('menu : lien parent et eleve', strpos(file_get_contents($root . '/central-navigation.php'), "'Travail de mon enfant', 'travail-enfant.php'") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
