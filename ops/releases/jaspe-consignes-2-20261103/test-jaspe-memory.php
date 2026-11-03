<?php
// J1 (03/11) : memoire de JASPE. Usage (image candidate, sans reseau) : php test-jaspe-memory.php /var/www/html
require_once ($argv[1] ?? '/var/www/html') . '/ProgramFunctions/SchoolSafeJaspeCloudflare.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

// Memoire : chaque echange s'ajoute, 8 entrees au plus, 500 caracteres au plus.
$m = SchoolSafeJaspeRemember([], 'Bonjour JASPE', 'Bonjour, je suis JASPE.');
check('premier echange memorise', $m, [['role'=>'user','content'=>'Bonjour JASPE'],['role'=>'assistant','content'=>'Bonjour, je suis JASPE.']]);
for ($i = 1; $i <= 6; $i++) $m = SchoolSafeJaspeRemember($m, "Question $i", "Reponse $i");
check('8 entrees au plus', count($m), 8);
check('les plus recentes gardees', [$m[0]['content'], $m[7]['content']], ['Question 3', 'Reponse 6']);
check('texte coupe a 500', mb_strlen(SchoolSafeJaspeRemember([], str_repeat('é', 900), 'ok')[0]['content']), 500);
check('entrees invalides ecartees', SchoolSafeJaspeCleanHistory([['role'=>'system','content'=>'x'], 'texte', ['role'=>'user','content'=>'  ']]), []);

// Message envoye au modele.
check('sans memoire : message inchange', SchoolSafeJaspeContextMessage('Salut', []), 'Salut');
$ctx = SchoolSafeJaspeContextMessage('Quelle question je viens de poser ?', [['role'=>'user','content'=>'Bonjour JASPE, qui es-tu ?'],['role'=>'assistant','content'=>'Je suis JASPE.']]);
check('contexte : question precedente presente', strpos($ctx, 'Utilisateur : Bonjour JASPE, qui es-tu ?') !== false, true);
check('contexte : reponse precedente presente', strpos($ctx, 'JASPE : Je suis JASPE.') !== false, true);
check('contexte : nouveau message en dernier', substr($ctx, -strlen('Quelle question je viens de poser ?')), 'Quelle question je viens de poser ?');

// Appel Worker : protocole inchange (cles message + session_key, JSON canonique, signature HMAC).
$seen = null;
$transport = function ($url, $headers, $body) use (&$seen) { $seen = [$headers, $body]; return json_encode(['reply' => 'Vous m avez demande qui je suis.']); };
$secret = str_repeat('s', 40);
$r = SchoolSafeJaspeCloudflare('Quelle question je viens de poser ?', 'cle', 'https://exemple.workers.dev', $secret, $transport,
    [['role'=>'user','content'=>'Bonjour JASPE, qui es-tu ?'],['role'=>'assistant','content'=>'Je suis JASPE.']]);
$sent = json_decode($seen[1], true);
check('Worker : seules les cles message et session_key', array_keys($sent), ['message', 'session_key']);
check('Worker : la question precedente voyage dans le message', strpos($sent['message'], 'Bonjour JASPE, qui es-tu ?') !== false, true);
$ts = substr($seen[0][1], strlen('x-jaspe-timestamp: '));
check('Worker : signature HMAC valide', substr($seen[0][2], strlen('x-jaspe-signature: ')), hash_hmac('sha256', $ts . "\n" . $seen[1], $secret));
check('Worker : reponse rendue', [$r['reply'], $r['mode']], ['Vous m avez demande qui je suis.', 'cloudflare']);

echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
