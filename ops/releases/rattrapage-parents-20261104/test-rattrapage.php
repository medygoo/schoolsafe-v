<?php
// Rattrapage -> parents (04/11) : messages envoyes aux parents. Usage : php test-rattrapage.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
function DBGet($q) { return []; } function DBQuery($q) { return null; } // fonctions natives absentes hors application
require_once $root . '/ProgramFunctions/SchoolSafeRemediationRelay.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$m = fn($k, $case = []) => SchoolSafeRemediationMessage($k, 'Josué', 'Mathématiques', $case);
check('detection : titre', $m('detected')[0], 'Rattrapage proposé');
check('detection : prenom et matiere', strpos($m('detected')[1], 'Josué en Mathématiques') !== false, true);
check('decision validee', $m('approved')[0], 'Rattrapage confirmé');
check('seance reportee', $m('session_postponed')[0], 'Séance reportée');
check('reussite : felicitations', strpos($m('reassessment_passed')[1], 'Félicitations') !== false, true);
check('payant : rappel que l accompagnement ne depend pas du paiement', strpos($m('billing_configured', ['BILLING_MODE' => 'paid', 'TARIFF' => '15000'])[1], 'ne dépend pas du paiement') !== false, true);
check('payant : montant', strpos($m('billing_configured', ['BILLING_MODE' => 'paid', 'TARIFF' => '15000'])[1], '15 000') !== false, true);
check('gratuit', strpos($m('billing_configured', ['BILLING_MODE' => 'free'])[1], 'gratuit') !== false, true);
check('evenement interne non envoye', $m('payment_review_required'), null);
check('essai interne non envoye', $m('test_parent_snapshot'), null);
check('sans prenom : votre enfant', strpos(SchoolSafeRemediationMessage('detected', '', '', [])[1], 'votre enfant') !== false, true);
check('titre multilingue', SchoolSafeRemediationTitle('|fr_FR.utf8:Classe maternelle|en_US.utf8:Kinder'), 'Classe maternelle');
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
