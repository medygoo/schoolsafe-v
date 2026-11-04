<?php
// Slogan SchoolSafe partout (04/11). Usage : php test-slogan.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
chdir($root);
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$s = 'Un enfant protégé, un parent informé';
foreach ([
    'ProgramFunctions/SchoolSafeAttendance.fnc.php' => 1, 'ProgramFunctions/SchoolSafeFeeTypes.fnc.php' => 2, 'ProgramFunctions/SchoolSafePickupScan.fnc.php' => 1,
    'ProgramFunctions/SchoolSafeScanNotify.fnc.php' => 1, 'signaler-absence.php' => 1, 'ProgramFunctions/SchoolSafeRemediationRelay.fnc.php' => 2,
    'ProgramFunctions/SchoolSafeSchoolDocument.fnc.php' => 1, 'ProgramFunctions/SchoolSafeLetterhead.fnc.php' => 1, 'ProgramFunctions/SchoolSafeMailTemplate.fnc.php' => 2,
    'schoolsafe-about.php' => 1, 'schoolsafe-card-bridge/studio-validated-20261008/studio-card-options.js' => 1, 'modules/SchoolSafe/login-reference.inc.php' => 1,
] as $file => $count) check("$file : slogan x$count", substr_count(mb_strtolower(file_get_contents($root . '/' . $file)), mb_strtolower($s)), $count);
check('ancienne formulation retiree', strpos(file_get_contents($root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php'), 'protégé, parent informé'), false);
require_once $root . '/ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
$mail = SchoolSafeMailBuild(['type' => 'school.announcement', 'payload' => ['title' => 'Réunion', 'message' => 'Réunion des parents jeudi.']],
    ['school_name' => 'École Témoin', 'locale' => 'fr', 'motto' => 'Savoir', 'phone' => '+243 1']);
check('e-mail texte : slogan', strpos($mail['text'] ?? '', 'SchoolSafe · ' . $s) !== false, true);
check('e-mail texte : ecole et devise gardees', strpos($mail['text'] ?? '', 'École Témoin — Savoir') !== false, true);
check('e-mail HTML : slogan', strpos($mail['html'] ?? '', $s) !== false, true);
check('e-mail HTML : corps garde', strpos($mail['html'] ?? '', 'Réunion des parents jeudi.') !== false, true);
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
check('documents : pied avec slogan', strpos(SchoolSafeLetterheadFooter(['school' => 'X']), $s) !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
