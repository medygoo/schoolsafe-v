<?php
// Emojis de JASPE (03/11) : quand les envoyer. Usage : php test-jaspe-emojis.php /var/www/html
require_once ($argv[1] ?? '/var/www/html') . '/ProgramFunctions/SchoolSafeJaspeMotion.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$r = fn($reply, $state = 'SPEAKING') => ['reply' => $reply, 'state' => $state, 'actions' => []];
check('felicitations -> emojis', SchoolSafeJaspeMotionRules($r('Bravo à toute la classe, félicitations !')), ['proud', 'sendStanding']);
check('merci de l utilisateur -> emojis', SchoolSafeJaspeMotionRules($r('Avec plaisir, je reste disponible.'), 'Merci JASPE'), ['grateful', 'sendStanding']);
check('anniversaire -> emojis', SchoolSafeJaspeMotionRules($r('Joyeux anniversaire à votre fille !')), [null, 'sendStanding']);
check('bonne journee -> emojis', SchoolSafeJaspeMotionRules($r('Je vous souhaite une bonne journée.')), [null, 'sendStanding']);
check('enregistrement -> pouce leve', SchoolSafeJaspeMotionRules($r('Le dossier est enregistré.')), ['proud', 'thumbsUp']);
check('erreur -> inquiete, pas d emojis', SchoolSafeJaspeMotionRules($r('Bravo', 'OFFLINE')), ['worried', null]);
check('salut -> signe de la main', SchoolSafeJaspeMotionRules($r('Bonjour, je suis JASPE.'), 'Salut'), [null, 'wave']);
check('mercredi n est pas merci', SchoolSafeJaspeMotionRules($r('Le cours a lieu mercredi.'), 'Quel jour mercredi ?'), [null, null]);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
