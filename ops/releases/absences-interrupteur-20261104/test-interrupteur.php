<?php
// Absences automatiques : interrupteur de l'ecole (04/11). Usage : php test-interrupteur.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
$s = file_get_contents($root . '/schoolsafe-attendance-scheduler.php');
check('planificateur : lit l interrupteur de chaque ecole', strpos($s, "TITLE='SCHOOLSAFE_ABSENCE_AUTO' LIMIT 1") !== false, true);
check('planificateur : sans activation, ecole ignoree', strpos($s, "if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\$/D', \$since)) { \$skipped++; continue; }") !== false, true);
check('planificateur : jour courant seulement apres activation', strpos($s, 'if (strcmp($today, $since) >= 0) foreach ($scopes') !== false, true);
check('planificateur : jamais de rattrapage avant activation', strpos($s, 'if (strcmp($day, $since) < 0) continue;') !== false, true);
check('planificateur : interrupteur lu avant les horaires', strpos($s, 'SCHOOLSAFE_ABSENCE_AUTO') < strpos($s, 'FROM schoolsafe_attendance_policy'), true);
$h = file_get_contents($root . '/horaires-arrivee.php');
check('page : activation a partir de demain', strpos($h, "\$value=\$on?date('Y-m-d',strtotime('+1 day')):'';") !== false, true);
check('page : jeton verifie avant l interrupteur', strpos($h, 'hash_equals($csrf') < strpos($h, "(\$_POST['action']??'')==='absences'"), true);
check('page : etat affiche', strpos($h, "activées depuis le ") !== false && strpos($h, "'coupées'") !== false, true);
check('page : horaires toujours personnalisables', strpos($h, 'SchoolSafeArrivalSave(') !== false, true);
$out = []; exec('php -l ' . escapeshellarg($root . '/schoolsafe-attendance-scheduler.php') . ' 2>&1', $out, $code);
check('planificateur : syntaxe', $code, 0);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
