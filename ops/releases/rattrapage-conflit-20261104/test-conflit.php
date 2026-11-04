<?php
// Rattrapage, fin du conflit d'interet (04/11). Usage : php test-conflit.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
function DBGet($q) { return []; } function DBQuery($q) { return null; } // fonctions natives absentes hors application
require_once $root . '/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeRemediationOverview.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

$d = SchoolSafeRemediationDefaults();
check('par defaut : enseignant libre', $d['other_teacher'], false);
$in = ['threshold_percent' => '50', 'failures' => '3', 'min_kinds' => '2', 'vigilance' => '1', 'school_share_percent' => '40', 'pass_percent' => '50', 'pass_gain_points' => ''];
check('validation : case cochee', SchoolSafeRemediationSettingsValidate(['other_teacher' => '1'] + $in)['other_teacher'], true);
check('validation : case vide', SchoolSafeRemediationSettingsValidate($in)['other_teacher'], false);
$saved = '';
SchoolSafeRemediationSettingsSave(3, 5, SchoolSafeRemediationSettingsValidate(['other_teacher' => '1'] + $in), function ($q) use (&$saved) { $saved = $q; });
check('enregistrement : colonne', strpos($saved, 'pass_gain_points,other_teacher,updated_by') !== false, true);
check('enregistrement : valeur', strpos($saved, 'VALUES(3,50.00,3,2,true,40.00,50.00,NULL,true,5,now())') !== false, true);
check('enregistrement : mise a jour', strpos($saved, 'other_teacher=EXCLUDED.other_teacher') !== false, true);
$r = SchoolSafeRemediationSettings(3, fn($q) => [1 => ['THRESHOLD_PERCENT' => '50', 'FAILURES' => '3', 'MIN_KINDS' => '2', 'VIGILANCE' => 't', 'SCHOOL_SHARE_PERCENT' => '40', 'PASS_PERCENT' => '50', 'PASS_GAIN_POINTS' => null, 'OTHER_TEACHER' => 't']]);
check('lecture : autre enseignant exige', $r['other_teacher'], true);
check('texte : libre', SchoolSafeRemediationRuleText($d)['teacher'], 'Le rattrapage peut être donné par l’enseignant de la matière ou par un autre enseignant de l’école.');
check('texte : exige', SchoolSafeRemediationRuleText(['other_teacher' => true] + $d)['teacher'], 'Le rattrapage est donné par un autre enseignant que celui qui enseigne la matière.');
check('autorise : libre + enseignant de la matiere', SchoolSafeRemediationTeacherAllowed($d, true), true);
check('refuse : exige + enseignant de la matiere', SchoolSafeRemediationTeacherAllowed(['other_teacher' => true] + $d, true), false);
check('autorise : exige + autre enseignant', SchoolSafeRemediationTeacherAllowed(['other_teacher' => true] + $d, false), true);

$c = fn($t, $tid, $f, $s, $cases, $watch, $passed) => ['TITLE' => $t, 'TEACHER_ID' => $tid, 'FIRST_NAME' => $f, 'LAST_NAME' => 'X', 'STUDENTS' => (string) $s, 'CASES' => (string) $cases, 'WATCH' => (string) $watch, 'PASSED' => (string) $passed];
$v = SchoolSafeRemediationOverview([$c('6A Maths', 12, 'Medy', 20, 1, 1, 1), $c('6B Maths', 12, 'Medy', 10, 2, 2, 0), $c('5A Fr', 9, 'Ana', 0, 0, 0, 0), $c('4A', 7, 'Joe', 4, 1, 1, 0)],
    [['TEACHER_ID' => 9, 'FIRST_NAME' => 'Ana', 'LAST_NAME' => 'X', 'CASES' => '2', 'SESSIONS' => '3', 'HELD' => '2', 'OWN' => '0'], ['TEACHER_ID' => 12, 'FIRST_NAME' => 'Medy', 'LAST_NAME' => 'X', 'CASES' => '1', 'SESSIONS' => '1', 'HELD' => '0', 'OWN' => '1']]);
check('classe : 40 % et 4 eleves = a examiner, en tete', [$v['classes'][0]['title'], $v['classes'][0]['rate'], $v['classes'][0]['alert']], ['6B Maths', 40, true]);
check('classe : 10 % non signalee', $v['classes'][array_search('6A Maths', array_column($v['classes'], 'title'))]['alert'], false);
check('classe : 2 eleves sur 4 (50 %) non signalee (moins de 3)', $v['classes'][array_search('4A', array_column($v['classes'], 'title'))]['alert'], false);
check('classe sans eleve : taux inconnu', $v['classes'][array_search('5A Fr', array_column($v['classes'], 'title'))]['rate'], null);
$medy = $v['teachers'][array_search('Medy X', array_column($v['teachers'], 'teacher'))];
check('enseignant : cumul des classes', [$medy['classes'], $medy['students'], $medy['cases'], $medy['watch'], $medy['alerts']], [2, 30, 3, 3, 1]);
check('enseignant : taux 20 %', $medy['rate'], 20);
check('enseignant : reussite 1 sur 3', $medy['success'], 33);
check('rattrapage : seances dans sa propre matiere', $v['givers'][1]['own'], 1);
check('rattrapage : autre enseignant', [$v['givers'][0]['teacher'], $v['givers'][0]['own']], ['Ana X', 0]);
$q = '';
SchoolSafeRemediationClassRows(3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('classes : ecole et annee', strpos($q, 'cp.school_id=3 AND cp.syear=2026') !== false, true);
check('classes : vigilance sans dossier', strpos($q, 'NOT w.cleared AND NOT EXISTS') !== false, true);
check('classes : sans ecole aucune requete', SchoolSafeRemediationClassRows(0, 2026, function ($s) { throw new Exception('requete'); }), []);
SchoolSafeRemediationGiverRows(3, 2026, function ($s) use (&$q) { $q = $s; return []; });
check('rattrapage : ecole et annee', strpos($q, 'r.school_id=3 AND r.syear=2026') !== false, true);

$ws = file_get_contents($root . '/schoolsafe-remediation/Workspace.inc.php');
check('page : enseignant confie voit le dossier', strpos($ws, 'OR EXISTS(SELECT 1 FROM schoolsafe_remediation_sessions se WHERE se.case_id=r.id AND se.teacher_id=') !== false, true);
check('page : vigilance limitee a la matiere de l enseignant', strpos($ws, "\$ssWatchScope = User('PROFILE') === 'teacher' ? 'w.school_id='") !== false, true);
check('page : enseignant de l ecole uniquement', strpos($ws, "st.profile='teacher' AND st.syear=r.syear AND (st.schools IS NULL OR st.schools LIKE '%,'||r.school_id||',%')") !== false, true);
check('page : regle autre enseignant appliquee au serveur', strpos($ws, "(empty(\$ssRule['other_teacher']) ? '' : ' AND NOT EXISTS(SELECT 1 FROM course_periods cp WHERE cp.course_id=r.course_id AND cp.teacher_id='") !== false, true);
check('page : liste sans l enseignant de la matiere si exige', strpos($ws, "if (\$ssTeaches && !empty(\$ssRule['other_teacher'])) continue;") !== false, true);
check('page : suivi reserve a la direction', preg_match("/<\\?php if \\(\\\$ssManage\\): \\?>\\r?\\n<\\?php require_once __DIR__ \\. '\\/\\.\\.\\/ProgramFunctions\\/SchoolSafeRemediationOverview/", $ws), 1);
check('page : reglages charges avant les actions', strpos($ws, '$ssRule = SchoolSafeRemediationSettings(') < strpos($ws, "\$action === 'session'"), true);
$rg = file_get_contents($root . '/reglages-rattrapage.php');
check('reglages : case autre enseignant', strpos($rg, 'name="other_teacher"') !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
