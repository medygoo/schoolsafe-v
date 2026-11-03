<?php
// JASPE (03/11) : consignes, contexte de l'utilisateur, masquage des noms d'eleves.
// Usage (image candidate, sans reseau) : php test-jaspe-consignes.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
require_once $root . '/ProgramFunctions/SchoolSafeJaspeCloudflare.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeJaspePrompt.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$g = SchoolSafeJaspeGuidelines(['name' => 'Medy Lomela', 'profile' => 'parent', 'school' => 'Complexe scolaire le sage']);
check('consignes : vouvoiement', strpos($g, 'vouvoyant') !== false, true);
check('consignes : pas de repetition de la question', strpos($g, 'ne répète pas la question') !== false, true);
check('contexte : prenom, role, ecole', strpos($g, "l'utilisateur s'appelle Medy Lomela ; son rôle : parent d'élève ; école : « Complexe scolaire le sage »") !== false, true);
check('contexte : role inconnu ignore', strpos(SchoolSafeJaspeGuidelines(['profile' => 'pirate']), 'rôle') === false, true);
$rows = [['FIRST_NAME' => 'Josué', 'LAST_NAME' => 'KABEYA'], ['FIRST_NAME' => 'Grâce', 'LAST_NAME' => 'MBALA'], ['FIRST_NAME' => 'Robot', 'LAST_NAME' => 'Fictif']];
$names = SchoolSafeJaspeStudentNames(3, 2026, fn($sql) => $rows);
check('noms : nom complet masque', SchoolSafeJaspeMask('Mon fils Josué Kabeya a eu 18.', $names), 'Mon fils [élève] a eu 18.');
check('noms : ordre inverse masque', SchoolSafeJaspeMask('KABEYA  Josué est absent', $names), '[élève] est absent');
check('noms : nom de famille seul masque', SchoolSafeJaspeMask('La famille Mbala arrive', $names), 'La famille [élève] arrive');
check('noms : mot courant « grâce à » garde', SchoolSafeJaspeMask('grâce à vous', $names), 'grâce à vous');
check('noms : mot plus long non touche', SchoolSafeJaspeMask('Kabeyas', $names), 'Kabeyas');
check('historique masque', SchoolSafeJaspeMaskHistory([['role' => 'user', 'content' => 'Robot Fictif est malade']], $names)[0]['content'], '[élève] est malade');
check('requete ecole/annee absente : aucun nom', SchoolSafeJaspeStudentNames(0, 2026, fn($sql) => $rows), []);
$m = SchoolSafeJaspeContextMessage('Bonjour', [], 'CONSIGNES');
check('message : consignes en tete', strpos($m, 'CONSIGNES') === 0 && substr($m, -7) === 'Bonjour', true);
check('message sans consignes ni memoire inchange', SchoolSafeJaspeContextMessage('Bonjour', []), 'Bonjour');
$seen = null;
SchoolSafeJaspeCloudflare('Question', 'cle', 'https://exemple.workers.dev', str_repeat('s', 40),
    function ($u, $h, $b) use (&$seen) { $seen = json_decode($b, true); return json_encode(['reply' => 'ok']); }, [], $g);
check('Worker : protocole inchange', array_keys($seen), ['message', 'session_key']);
check('Worker : consignes envoyees', strpos($seen['message'], 'Consignes pour JASPE') === 0, true);
$found = SchoolSafeJaspeMaskedNames('Josué Kabeya a eu 18 sur 20.', $names);
check('nom retrouve tel qu ecrit', $found, ['Josué Kabeya']);
check('reponse affichee : nom remis', SchoolSafeJaspeUnmaskReply('Félicitez [élève] pour ce 18.', $found), 'Félicitez Josué Kabeya pour ce 18.');
check('deux eleves : pas de remplacement', SchoolSafeJaspeUnmaskReply('[élève] et [élève]', SchoolSafeJaspeMaskedNames('Josué Kabeya et Grâce Mbala', $names)), '[élève] et [élève]');
$raw = json_encode(['reply' => '{"id":"chatcmpl-a5c9","object":"chat.completion","choices":[{"index":0,"message":{"role":"assistant","content":null}}]}']);
$err = null; try { SchoolSafeJaspeCloudflare('Question', 'cle', 'https://exemple.workers.dev', str_repeat('s', 40), fn($u, $h, $b) => $raw); } catch (RuntimeException $e) { $err = $e->getMessage(); }
check('reponse technique brute du Worker -> secours', $err, 'INVALID_PROVIDER_REPLY');
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
