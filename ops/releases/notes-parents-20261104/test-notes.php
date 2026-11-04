<?php
// Notes visibles chaque jour par le parent (04/11). Usage : php test-notes.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeGradeNotice.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

check('grade.published est un type connu', in_array('grade.published', SchoolSafeNotifyEventTypes(), true), true);
require_once $root . '/ProgramFunctions/SchoolSafeNotifyRecipient.fnc.php';
check('grade.published part aussi sur WhatsApp', in_array('grade.published', SchoolSafeNotifyWaEventTypes(), true), true);
check('note lisible', SchoolSafeGradeNoticeMark('4.00', '10.00'), '4/10 (40 %)');
check('demi-point', SchoolSafeGradeNoticeMark('7.5', '20'), '7,5/20 (38 %)');
check('dispense', SchoolSafeGradeNoticeMark('-1.00', '10'), 'dispensé');
check('sans note', SchoolSafeGradeNoticeMark(null, '10'), '');

$row = ['ASSIGNMENT_ID' => '7', 'STUDENT_ID' => '4', 'POINTS' => '4.00', 'MAXIMUM' => '10.00', 'COMMENT' => 'Revois les fractions', 'TITLE' => 'Les fractions',
    'DUE_DATE' => '2026-11-06', 'ASSIGNED_DATE' => '2026-11-04', 'COURSE' => '|fr_FR.utf8:Mathématiques|en_US.utf8:Maths', 'TYPE_TITLE' => 'Devoir',
    'FIRST_NAME' => 'Josué', 'SCHOOL_TITLE' => 'Complexe scolaire le sage', 'SCHOOL_ID' => '3', 'SYEAR' => '2026', 'CHANGED_LOCAL' => '2026-11-06 10:00:00'];
$m = SchoolSafeGradeNoticeMessage($row, 'https://x/note-devoir.php?devoir=7&eleve=4');
check('titre du message', $m[0], 'Nouvelle note : Mathématiques');
check('message : prenom, matiere, devoir, note', strpos($m[1], 'Note de Josué — Mathématiques — Devoir « Les fractions » du ') !== false && strpos($m[1], ' : 4/10 (40 %)') !== false, true);
check('message : appreciation', strpos($m[1], 'Appréciation de l’enseignant : Revois les fractions') !== false, true);
check('message : lien et slogan', strpos($m[1], 'note-devoir.php?devoir=7&eleve=4') !== false && strpos($m[1], 'Un enfant protégé, un parent informé') !== false, true);

$row2 = ['ASSIGNMENT_ID' => '8', 'TITLE' => 'Lecture', 'POINTS' => '9', 'MAXIMUM' => '10', 'COMMENT' => '', 'COURSE' => 'Français', 'TYPE_TITLE' => 'Interrogation', 'DUE_DATE' => '2026-11-06'] + $row;
$d = SchoolSafeGradeNoticeDigest([$row, $row2], 'https://x/travail-enfant.php?enfant=4');
check('resume du soir : titre', $d[0], 'Notes du jour de Josué');
check('resume du soir : deux lignes', substr_count($d[1], "\n• "), 2);

$plan = SchoolSafeGradeNoticePlan([$row, $row2], [3 => 'immediat'], '2026-11-06 10:30:00');
check('immediat : une annonce par note', array_column($plan, 'key'), ['grade:7:4', 'grade:8:4']);
check('soir : rien avant 18 h', SchoolSafeGradeNoticePlan([$row, $row2], [3 => 'soir'], '2026-11-06 17:59:00'), []);
$evening = SchoolSafeGradeNoticePlan([$row, $row2], [3 => 'soir'], '2026-11-06 18:01:00');
check('soir : un seul resume pour l eleve', [count($evening), count($evening[0]['rows']), $evening[0]['digest']], [1, 2, true]);
check('soir : cle stable', strpos($evening[0]['key'], 'grades:4:20261106:') === 0, true);
check('soir : note saisie apres 18 h attend le lendemain', SchoolSafeGradeNoticePlan([['CHANGED_LOCAL' => '2026-11-06 18:20:00'] + $row], [3 => 'soir'], '2026-11-06 19:00:00'), []);
check('pas d envoi', SchoolSafeGradeNoticePlan([$row], [3 => 'non'], '2026-11-06 19:00:00'), []);
check('cle de dedoublonnage valide', (bool) preg_match('/^[A-Za-z0-9:_-]{1,200}$/D', $evening[0]['key']), true);

check('corrige cache avant la remise', SchoolSafeGradeNoticeAnswerVisible('2026-11-06', '2026-11-06', false), false);
check('corrige visible apres la remise', SchoolSafeGradeNoticeAnswerVisible('2026-11-06', '2026-11-07', false), true);
check('corrige toujours visible par l enseignant', SchoolSafeGradeNoticeAnswerVisible('2026-11-09', '2026-11-07', true), true);
check('progression en hausse', SchoolSafeGradeNoticeDirection([['PCT' => '40'], ['PCT' => '70']]), 'hausse');
check('progression en baisse', SchoolSafeGradeNoticeDirection([['PCT' => '70'], ['PCT' => '40']]), 'baisse');
check('progression : une seule note', SchoolSafeGradeNoticeDirection([['PCT' => '70']]), '');
check('moyenne de classe cachee sous 5 notes', SchoolSafeGradeNoticeClassAverage(7, function () { return [1 => ['N' => '3', 'AVG' => '55']]; }), null);
check('moyenne de classe a partir de 5 notes', SchoolSafeGradeNoticeClassAverage(7, function () { return [1 => ['N' => '6', 'AVG' => '55.04']]; }), 55.0);
check('mode par defaut : tout de suite', SchoolSafeGradeNoticeMode(3, function () { return []; }), 'immediat');
check('mode inconnu ignore', SchoolSafeGradeNoticeMode(3, function () { return [1 => ['CONFIG_VALUE' => 'x']]; }), 'immediat');
check('mode soir', SchoolSafeGradeNoticeMode(3, function () { return [1 => ['CONFIG_VALUE' => 'soir']]; }), 'soir');

foreach (['note-devoir.php', 'devoirs-travaux.php', 'travail-enfant.php', 'papier-ecole.php', 'schoolsafe-notify-dispatch.php', 'ProgramFunctions/SchoolSafeGradeNotice.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code);
    check('syntaxe ' . $f, $code, 0);
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
