<?php
// Routage local de JASPE (03/11) : une page seulement sur demande explicite, sinon l'IA.
// Usage (image candidate, sans reseau) : php test-jaspe-routage.php /var/www/html
function SchoolSafeJaspeActions($canUse, $canEdit) {
    $ids = ['dashboard','school_fees','school_payments','student_assignments','student_grades','report_cards','attendance_summary','student_schedule','student_register','school_calendar'];
    return array_map(fn($id) => ['id'=>$id,'title'=>'Page '.$id,'url'=>'Modules.php?modname=X/'.$id.'.php','kind'=>'navigation'], $ids);
}
require_once ($argv[1] ?? '/var/www/html') . '/ProgramFunctions/SchoolSafeJaspeConversation.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$c = fn($m) => SchoolSafeJaspeConversation($m, fn() => true, fn() => true);
$ai = fn($r) => $r['state'] === 'IDLE' && strpos($r['reply'], 'conversation IA libre') !== false;
check('aide-moi a organiser ma journee -> IA', $ai($c('Aide-moi à organiser ma journée')), true);
check('motiver un eleve avant un examen -> IA', $ai($c('Motiver un élève avant un examen')), true);
check('question avec le mot notes -> IA', $ai($c('Comment aider mon fils à améliorer ses notes en maths ?')), true);
check('ouvre les bulletins -> bouton', $c('Ouvre les bulletins')['navigate'] ?? null, 'report_cards');
check('ou sont mes bulletins -> bouton', $c('Où sont mes bulletins ?')['navigate'] ?? null, 'report_cards');
check('message court bulletins -> bouton', $c('bulletins')['navigate'] ?? null, 'report_cards');
check('inscrire un eleve -> bouton', $c('Je veux inscrire un élève')['navigate'] ?? null, 'student_register');
$multi = $c('Affiche les absences et l emploi du temps');
check('plusieurs pages -> choix (max 3), pas d ouverture', [isset($multi['navigate']), count($multi['choices'] ?? [])], [false, 2]);
$hello = $c('Bonjour');
check('bonjour -> salut sans la liste des pages', [$hello['mode'], $hello['actions']], ['local', []]);
check('bonjour suivi d une vraie question -> IA', $ai($c('Bonjour, peux-tu m expliquer les fractions simplement ?')), true);
check('ne pas ouvrir respecte', $c('Ne l ouvre pas, explique-moi les bulletins')['reply'], 'Je n\'ouvre aucune page.');
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
