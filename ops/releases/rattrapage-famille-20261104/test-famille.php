<?php
// Rattrapage, famille et paiement juste (04/11). Usage : php test-famille.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
function DBGet($q) { return []; } function DBQuery($q) { return null; } // fonctions natives absentes hors application
require_once $root . '/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeFeeScan.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeFeeTypes.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

// Tarif juste
check('gratuit', SchoolSafeRemediationTariff('free', '5000', '50', ''), ['tariff' => '0', 'base' => null, 'reduction' => null, 'reason' => null]);
check('payant sans reduction', SchoolSafeRemediationTariff('paid', '15000', '0', ''), ['tariff' => '15000.00', 'base' => '15000.00', 'reduction' => null, 'reason' => null]);
check('payant reduction vide = 0', SchoolSafeRemediationTariff('paid', '15000', '', '')['tariff'], '15000.00');
check('reduction 50 % motivee', SchoolSafeRemediationTariff('paid', '15000', '50', 'Famille en difficulté'), ['tariff' => '7500.00', 'base' => '15000.00', 'reduction' => '50.00', 'reason' => 'Famille en difficulté']);
check('reduction decimale avec virgule', SchoolSafeRemediationTariff('paid', '10000,50', '12,5', 'Fratrie')['tariff'], '8750.44');
check('motif obligatoire', fails(fn() => SchoolSafeRemediationTariff('paid', '15000', '20', '  ')), 'Indiquez le motif de la réduction.');
check('100 % = gratuit', fails(fn() => SchoolSafeRemediationTariff('paid', '15000', '100', 'x')), 'Une réduction de 100 % revient à la gratuité : choisissez « Gratuit ».');
check('reduction > 100 refusee', fails(fn() => SchoolSafeRemediationTariff('paid', '15000', '120', 'x')) !== '', true);
check('tarif nul refuse', fails(fn() => SchoolSafeRemediationTariff('paid', '0', '0', '')), 'Tarif invalide.');
check('tarif texte refuse', fails(fn() => SchoolSafeRemediationTariff('paid', '1e5', '0', '')), 'Tarif invalide.');
check('mode inconnu refuse', fails(fn() => SchoolSafeRemediationTariff('loan', '10', '0', '')) !== '', true);
check('motif trop long refuse', fails(fn() => SchoolSafeRemediationTariff('paid', '100', '10', str_repeat('a', 501))) !== '', true);
check('reponses du parent', array_keys(SchoolSafeRemediationParentResponses()), ['accepted', 'meeting', 'declined']);

// Jamais bloquant : le controle des frais a l'entree ignore le rattrapage
$q = [];
SchoolSafeFeeLedgerRead(16, 3, 2026, function ($s) use (&$q) { $q[] = $s; return []; }, '2026-11-04');
$sql = implode("\n", $q);
check('entree : frais de rattrapage exclus', strpos($sql, 'NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases rc WHERE rc.fee_id IN (billing_fees.id,billing_fees.waived_fee_id))') !== false, true);
check('entree : paiements de rattrapage exclus', strpos($sql, 'NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payment_purpose rp WHERE rp.payment_id IN (billing_payments.id,billing_payments.refunded_payment_id))') !== false, true);
check('entree : eleve, ecole, annee gardes', substr_count($sql, 'STUDENT_ID=16 AND SCHOOL_ID=3 AND SYEAR=2026'), 2);
$q = [];
SchoolSafeFeeTypesLedger(16, 3, 2026, function ($s) use (&$q) { $q[] = $s; return []; });
$sql = implode("\n", $q);
check('types de frais : rattrapage exclu (frais)', strpos($sql, 'rc.fee_id IN (billing_fees.id,billing_fees.waived_fee_id)') !== false, true);
check('types de frais : rattrapage exclu (paiements)', strpos($sql, 'rp.payment_id IN (billing_payments.id,billing_payments.refunded_payment_id)') !== false, true);

// Page
$ws = file_get_contents($root . '/schoolsafe-remediation/Workspace.inc.php');
check('page : reponse limitee au parent', strpos($ws, "\$action === 'parent_response' && User('PROFILE') === 'parent'") !== false, true);
check('page : reponse dans la portee du parent principal', strpos($ws, "' WHERE r.id=' . \$caseId . ' AND ' . \$ssScope . \" AND r.status IN ('approved','repeat') AND r.parent_response IS NULL RETURNING r.id\"") !== false, true);
check('page : une seule reponse', strpos($ws, 'r.parent_response IS NULL') !== false, true);
check('page : evenement journalise', strpos($ws, "\",'parent_\" . \$response . \"',\"") !== false, true);
check('page : plan d accompagnement parent', strpos($ws, 'Plan d’accompagnement') !== false, true);
check('page : jamais dependant du paiement', strpos($ws, 'L’accompagnement de votre enfant ne dépend jamais du paiement.') !== false, true);
check('page : motif de reduction cache au parent', strpos($ws, "User('PROFILE') === 'parent' ? 'tarif réduit accordé'") !== false, true);
check('page : tarif calcule par la fonction', strpos($ws, '$price = SchoolSafeRemediationTariff(') !== false && strpos($ws, '$amount') === false, true);
check('page : action declaree au portail', strpos($ws, "'billing', 'parent_response'] as \$ssAction") !== false, true);
$pay = file_get_contents($root . '/remediation-payments.php');
check('paiement : part attribuee a qui donne les seances', strpos($pay, "confirmed_by,teacher_id) SELECT") !== false && strpos($pay, "ORDER BY count(*) FILTER (WHERE se.status='held') DESC") !== false, true);
check('paiement : partage du reglage garde', strpos($pay, "round(amount*'.\$sharePct.'/100,2)") !== false, true);
$cat = file_get_contents($root . '/modules/SchoolSafe/Catalog.php');
check('droit : reponse du parent, profil parent seul', strpos($cat, "'SchoolSafe/RemediationParentReply.php' => ['title' => 'Répondre au plan de rattrapage de mon enfant', 'profiles' => ['parent'], 'entry' => 'rattrapage.php', 'mutates' => true, 'routes' => [['rattrapage.php', '', 'parent_response']]]") !== false, true);
check('menu : entree du parent', strpos(file_get_contents($root . '/modules/SchoolSafe/Menu.php'), "'RemediationParentReply'") !== false, true);
check('programme natif present', strpos(file_get_contents($root . '/modules/SchoolSafe/RemediationParentReply.php'), "SchoolSafeRenderPermissionProgram('SchoolSafe/RemediationParentReply.php')") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
