<?php
// Rattrapage : reglages par ecole, vigilance, partage, reussite (04/11). Usage : php test-reglages.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
function DBGet($q) { return []; } function DBQuery($q) { return null; } // fonctions natives absentes hors application
require_once $root . '/ProgramFunctions/SchoolSafeRemediationRelay.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeRemediationSettings.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

$d = SchoolSafeRemediationDefaults();
check('sans reglage : 3 evaluations', SchoolSafeRemediationSettings(3, fn($q) => [])['failures'], 3);
check('sans reglage : partage 40', SchoolSafeRemediationSettings(3, fn($q) => [])['school_share_percent'], 40.0);
check('sans reglage : non enregistre', SchoolSafeRemediationSettings(3, fn($q) => [])['saved'], false);
$row = [1 => ['THRESHOLD_PERCENT' => '45.00', 'FAILURES' => '4', 'MIN_KINDS' => '1', 'VIGILANCE' => 'f', 'SCHOOL_SHARE_PERCENT' => '30.00', 'PASS_PERCENT' => '60.00', 'PASS_GAIN_POINTS' => '15.00']];
$sql = '';
$r = SchoolSafeRemediationSettings(7, function ($q) use ($row, &$sql) { $sql = $q; return $row; });
check('lecture : ecole ciblee', strpos($sql, 'SCHOOL_ID=7') !== false, true);
check('lecture : valeurs', [$r['threshold_percent'], $r['failures'], $r['min_kinds'], $r['vigilance'], $r['school_share_percent'], $r['pass_gain_points']], [45.0, 4, 1, false, 30.0, 15.0]);

$in = ['threshold_percent' => '50', 'failures' => '3', 'min_kinds' => '2', 'vigilance' => '1', 'school_share_percent' => '40', 'pass_percent' => '50', 'pass_gain_points' => ''];
$v = SchoolSafeRemediationSettingsValidate($in);
check('validation : virgule acceptee', SchoolSafeRemediationSettingsValidate(['threshold_percent' => '47,5'] + $in)['threshold_percent'], 47.5);
check('validation : progression vide = null', $v['pass_gain_points'], null);
check('validation : vigilance decochee', SchoolSafeRemediationSettingsValidate(['vigilance' => ''] + $in)['vigilance'], false);
check('refus : 1 evaluation', fails(fn() => SchoolSafeRemediationSettingsValidate(['failures' => '1'] + $in)) !== '', true);
check('refus : 7 evaluations', fails(fn() => SchoolSafeRemediationSettingsValidate(['failures' => '7'] + $in)) !== '', true);
check('refus : sortes > evaluations', fails(fn() => SchoolSafeRemediationSettingsValidate(['failures' => '2', 'min_kinds' => '3'] + $in)) !== '', true);
check('refus : part > 100', fails(fn() => SchoolSafeRemediationSettingsValidate(['school_share_percent' => '120'] + $in)) !== '', true);
check('refus : texte', fails(fn() => SchoolSafeRemediationSettingsValidate(['threshold_percent' => '50; DROP'] + $in)) !== '', true);
check('refus : message en francais', fails(fn() => SchoolSafeRemediationSettingsValidate(['failures' => '9'] + $in)), 'Nombre d’évaluations : valeur attendue entre 2 et 6.');
check('partage 0 % accepte (tout a l enseignant)', SchoolSafeRemediationSettingsValidate(['school_share_percent' => '0'] + $in)['school_share_percent'], 0.0);

$saved = '';
SchoolSafeRemediationSettingsSave(3, 5, SchoolSafeRemediationSettingsValidate(['pass_gain_points' => '15'] + $in), function ($q) use (&$saved) { $saved = $q; });
check('enregistrement : une ligne par ecole', strpos($saved, 'ON CONFLICT(school_id) DO UPDATE') !== false, true);
check('enregistrement : valeurs', strpos($saved, 'VALUES(3,50.00,3,2,true,40.00,50.00,15.00,5,now())') !== false, true);
check('enregistrement : sans ecole refuse', fails(fn() => SchoolSafeRemediationSettingsSave(0, 5, $v, fn($q) => null)) !== '', true);

$t = SchoolSafeRemediationRuleText($d);
check('texte : regle d origine', $t['detection'], 'Un dossier est proposé après 3 évaluations sous 50 % dans une même matière, d’au moins 2 sortes (devoir, interrogation, examen).');
check('texte : partage', $t['share'], 'Partage des paiements : 40 % pour l’école, 60 % pour l’enseignant.');
check('texte : reussite avec progression', SchoolSafeRemediationRuleText(['pass_gain_points' => 15.0] + $d)['pass'], 'Réussite conseillée : 50 % ou plus à la nouvelle évaluation, ou une progression d’au moins 15 points.');
check('texte : seuil decimal', SchoolSafeRemediationPct(47.5), '47,5 %');

check('reussite : 10/20 = 50 %', SchoolSafeRemediationPassVerdict($d, 10, 20, 30.0), true);
check('reussite : 9/20 sans progression', SchoolSafeRemediationPassVerdict($d, 9, 20, 30.0), false);
check('reussite : 9/20 avec +15 depuis 25 %', SchoolSafeRemediationPassVerdict(['pass_gain_points' => 15.0] + $d, 9, 20, 25.0), true);
check('reussite : progression inconnue', SchoolSafeRemediationPassVerdict(['pass_gain_points' => 15.0] + $d, 9, 20, null), false);
check('reussite : maximum nul', SchoolSafeRemediationPassVerdict($d, 5, 0, null), false);

$w = SchoolSafeRemediationWatchMessage('Josué', 'Mathématiques', 2, 50.0);
check('vigilance : titre', $w[0], 'Vigilance');
check('vigilance : message', strpos($w[1], 'Josué a 2 évaluations récentes sous 50 % en Mathématiques') !== false, true);
check('vigilance : sans dossier', strpos($w[1], 'Aucun dossier') !== false, true);
check('vigilance : sans prenom', strpos(SchoolSafeRemediationWatchMessage('', '', 2, 50.0)[1], 'votre enfant a 2') !== false, true);

$q = []; $db = ['get' => function ($s) use (&$q) { $q[] = $s; return []; }];
check('relais : sans evenement = 0', SchoolSafeRemediationRelay($db), 0);
check('relais : lit aussi la vigilance', count(array_filter($q, fn($s) => strpos($s, 'schoolsafe_remediation_watch') !== false)) === 1, true);
check('relais vigilance : une seule fois', count(array_filter($q, fn($s) => strpos($s, "'remediation:watch:'||w.ID") !== false)) === 1, true);
check('relais vigilance : pas si dossier ouvert', count(array_filter($q, fn($s) => strpos($s, 'NOT EXISTS (SELECT 1 FROM schoolsafe_remediation_cases') !== false)) === 1, true);

$pay = file_get_contents($root . '/remediation-payments.php');
check('paiement : plus de 0.4 fixe', strpos($pay, 'amount*0.4') === false, true);
check('paiement : partage du reglage', strpos($pay, "round(amount*'.\$sharePct.'/100,2)") !== false, true);
$ws = file_get_contents($root . '/schoolsafe-remediation/Workspace.inc.php');
check('page : bloc vigilance', strpos($ws, 'id="vigilance-title"') !== false, true);
check('page : portee de la vigilance', strpos($ws, "preg_replace('/\\br\\./', 'w.', \$ssScope)") !== false, true);
check('page : indication de reussite', strpos($ws, 'SchoolSafeRemediationPassVerdict(') !== false, true);
$rt = file_get_contents($root . '/rattrapage.php');
check('regle : plus de 40 % fixe', strpos($rt, '40 % pour l') === false, true);
check('regle : lien reglages reserve', strpos($rt, "\$ssRuleEdit = User('PROFILE') === 'admin' && AllowEdit('School_Setup/Configuration.php')") !== false, true);
$rg = file_get_contents($root . '/reglages-rattrapage.php');
check('reglages : jeton', strpos($rg, 'hash_equals($csrf') !== false, true);
check('reglages : droit configuration', strpos($rg, "AllowEdit('School_Setup/Configuration.php')") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
