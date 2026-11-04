<?php
// Rattrapage, seances utiles (04/11). Usage : php test-seances.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
function DBGet($q) { return []; } function DBQuery($q) { return null; } // fonctions natives absentes hors application
require_once $root . '/ProgramFunctions/SchoolSafeRemediationSessions.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeCardVerify.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

// Groupe
check('groupe : cases cochees, sans le dossier principal', SchoolSafeRemediationGroupIds(['group_8' => '1', 'group_9' => '1', 'group_7' => '1', 'case_id' => '7', 'group_10' => '0', 'group_x' => '1'], 7), [8, 9]);
$many = []; for ($i = 1; $i <= 15; $i++) $many['group_' . (100 + $i)] = '1';
check('groupe : 15 autres refuses', fails(fn() => SchoolSafeRemediationGroupIds($many, 7)), 'Quinze élèves au plus par séance de groupe.');
check('groupe : cle seulement a plusieurs', SchoolSafeRemediationGroupKey(1), null);
check('groupe : cle de 32 caracteres', (bool) preg_match('/^[a-f0-9]{32}$/D', (string) SchoolSafeRemediationGroupKey(3)), true);

// Carte : secret de test, cartes signees comme le moteur natif
$secret = str_repeat('ab', 32);
$sign = fn($n) => 'schoolsafe://card/' . $n . '/' . substr(strtr(base64_encode(hash_hmac('sha256', $n, hex2bin($secret), true)), '+/', '-_'), 0, 32);
$cards = ['C16' => ['student_id' => 16, 'status' => 'active', 'enrolled' => true], 'C17' => ['student_id' => 17, 'status' => 'active', 'enrolled' => true],
    'LOST' => ['student_id' => 16, 'status' => 'lost', 'enrolled' => true], 'OUT' => ['student_id' => 16, 'status' => 'active', 'enrolled' => false]];
$query = function ($number, $school) use ($cards) {
    if (!isset($cards[$number])) return null;
    return $cards[$number] + ['school_id' => $school, 'card_number' => $number, 'signature' => '', 'first_name' => 'A', 'last_name' => 'B', 'class_name' => ''];
};
check('carte : bonne carte de l eleve', SchoolSafeRemediationCardCheck($sign('C16'), 16, 3, 2026, $secret, $query), 'ok');
check('carte : espaces du lecteur ignores', SchoolSafeRemediationCardCheck(' ' . $sign('C16') . "\n", 16, 3, 2026, $secret, $query), 'ok');
check('carte : autre eleve', SchoolSafeRemediationCardCheck($sign('C17'), 16, 3, 2026, $secret, $query), 'other_student');
check('carte : perdue', SchoolSafeRemediationCardCheck($sign('LOST'), 16, 3, 2026, $secret, $query), 'invalid');
check('carte : eleve non inscrit', SchoolSafeRemediationCardCheck($sign('OUT'), 16, 3, 2026, $secret, $query), 'invalid');
check('carte : signature fausse', SchoolSafeRemediationCardCheck('schoolsafe://card/C16/' . str_repeat('A', 32), 16, 3, 2026, $secret, $query), 'invalid');
check('carte : simple numero tape refuse', SchoolSafeRemediationCardCheck('C16', 16, 3, 2026, $secret, $query), 'invalid');
check('carte : sans secret = verification indisponible', SchoolSafeRemediationCardCheck($sign('C16'), 16, 3, 2026, '', $query), 'unavailable');
check('carte : message autre eleve', SchoolSafeRemediationCardMessage('other_student'), 'Cette carte appartient à un autre élève.');

// JASPE
$p = SchoolSafeRemediationJaspePrompt('Mathématiques', '6e année', "  additionner   des fractions\n");
check('JASPE : matiere, niveau, objectif', strpos($p, 'Matière : Mathématiques. Niveau : 6e année. Objectif : additionner des fractions.') !== false, true);
check('JASPE : rappel sans nom ni note', strpos($p, 'Pas de nom d’élève, pas de note.') !== false, true);
check('JASPE : objectif obligatoire', fails(fn() => SchoolSafeRemediationJaspePrompt('M', 'N', '   ')), 'Indiquez l’objectif de la séance.');
check('JASPE : consigne sous la limite du secours (1000)', mb_strlen(SchoolSafeRemediationJaspePrompt(str_repeat('m', 200), str_repeat('n', 200), str_repeat('x', 900))) <= 1000, true);
check('JASPE : brouillon rendu', SchoolSafeRemediationJaspeExercises('q', fn($m) => '  Exercices  '), 'Exercices');
check('JASPE : panne = null', SchoolSafeRemediationJaspeExercises('q', function ($m) { throw new RuntimeException('down'); }), null);
check('JASPE : reponse vide = null', SchoolSafeRemediationJaspeExercises('q', fn($m) => ''), null);

// Page
$ws = file_get_contents($root . '/schoolsafe-remediation/Workspace.inc.php');
check('page : jour de classe verifie si calendrier', strpos($ws, 'SchoolSafeAttendanceIsSchoolDay($ssSchool, $ssYear, $date->format(\'Y-m-d\')') !== false, true);
check('page : groupe limite a la meme matiere et a la portee', strpos($ws, "' AND r.course_id=' . (int) \$ssMain[1]['COURSE_ID'] . ' AND ' . \$ssScope") !== false, true);
check('page : cle de groupe enregistree', strpos($ws, 'objective,group_key) SELECT r.id,') !== false, true);
check('page : carte = presence verifiee, refus sinon', strpos($ws, "if (\$ssCheck !== 'ok') { DBQuery('ROLLBACK');") !== false && strpos($ws, ",attendance_card=' . (\$ssByCard ? 'true' : 'false')") !== false, true);
check('page : JASPE noms masques', strpos($ws, '$ssPrompt = SchoolSafeJaspeMask($ssPrompt, SchoolSafeJaspeStudentNames(') !== false, true);
check('page : JASPE limite de frequence', strpos($ws, "if (count(\$ssHits) >= 10)") !== false, true);
check('page : JASPE dans la portee du dossier', strpos($ws, "WHERE r.id=' . \$caseId . ' AND ' . \$ssScope . \" AND r.status IN ('approved','repeat')\");" . "\r\n") !== false
    || strpos($ws, "WHERE r.id=' . \$caseId . ' AND ' . \$ssScope . \" AND r.status IN ('approved','repeat')\");\n") !== false, true);
check('JASPE : texte simple demande', strpos($p, 'pas de tableau, pas de formule en code') !== false, true);
$fb = file_get_contents($root . '/ProgramFunctions/SchoolSafeJaspeFallbackLLM.fnc.php');
check('JASPE : longueur reglable, conversation a 400', strpos($fb, "string \$preamble = '', int \$maxTokens = 400): ?array") !== false && strpos($fb, "'max_tokens' => \$maxTokens") !== false, true);
check('page : brouillon long, service rapide puis Worker', strpos($ws, 'SchoolSafeJaspeFallbackLLM($message, [], $preamble, 1200)') !== false, true);
$cat = file_get_contents($root . '/modules/SchoolSafe/Catalog.php');
check('droit : JASPE rattache au suivi de seance', strpos($cat, "'routes' => [['rattrapage.php', '', 'update_session'], ['rattrapage.php', '', 'jaspe_exercises']]]") !== false, true);
check('page : script carte charge', strpos(file_get_contents($root . '/rattrapage.php'), 'schoolsafe-remediation/card-scan.js?v=1') !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
