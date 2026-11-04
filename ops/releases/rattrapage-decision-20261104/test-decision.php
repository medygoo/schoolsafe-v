<?php
// Rattrapage, lot 2 (04/11) : decision par tache attribuable. Usage : php test-decision.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$ws = file_get_contents($root . '/schoolsafe-remediation/Workspace.inc.php');
check('module actif : decision par tache, plus par titre', strpos($ws, "\$ssDirector = \$ssManage && (!empty(\$GLOBALS['RosarioModules']['SchoolSafe'])") !== false, true);
check('sans module : titre garde en repli', strpos($ws, "AND title='\" . \$ssDirectorTitle . \"'\"));") !== false, true);
check('POST refuse par la passerelle sans la tache', strpos($ws, "if (SchoolSafeEndpointGateway('rattrapage.php', 'POST', '', \$_POST) === 'deny') {") !== false, true);
foreach (['decide' => 'decide', 'reevaluate' => 'reevaluate', 'billing' => 'billing'] as $action => $task)
    check("formulaire $action affiche seulement avec la tache", strpos($ws, "\$ssDirector && \$ssActions['$task']") !== false, true);
$sql = file_get_contents($root . '/schoolsafe-remediation/056-remediation-decision-task.sql');
check('notification : tache Valider ou refuser', substr_count($sql, "modname='SchoolSafe/RemediationDecide.php' AND"), 2);
check('notification : plus de titre', strpos($sql, "p.title='Directeur") === false, true);
check('notification : administrateurs de l ecole seulement', strpos($sql, "s.profile='admin' AND s.syear=r.syear") !== false && strpos($sql, "s.schools LIKE '%,'||r.school_id||',%'") !== false, true);
$cat = file_get_contents($root . '/modules/SchoolSafe/Catalog.php');
foreach (['RemediationDecide' => 'decide', 'RemediationReassess' => 'reevaluate', 'RemediationBilling' => 'billing'] as $key => $action)
    check("tache $key attribuable aux administrateurs", (bool) preg_match("~'SchoolSafe/$key\\.php' => \\['title' => '[^']+', 'profiles' => \\['admin'\\], 'entry' => 'rattrapage.php', 'mutates' => true, 'routes' => \\[\\['rattrapage.php', '', '$action'\\]\\]\\]~", $cat), true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
