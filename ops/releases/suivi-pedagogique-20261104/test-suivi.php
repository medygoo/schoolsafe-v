<?php
// Lot 9 (04/11) : suivi pedagogique + PDF natif par Chromium. Usage : php test-suivi.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafePedagogyFollowup.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

check('periode par defaut : 7 jours', SchoolSafeFollowupRange(null, null, '2026-11-04'), ['2026-10-29', '2026-11-04']);
check('fin dans le futur ramenee a aujourd hui', SchoolSafeFollowupRange('2026-11-01', '2027-01-01', '2026-11-04'), ['2026-11-01', '2026-11-04']);
check('date invalide ignoree', SchoolSafeFollowupRange("2026-02-31'", '2026-11-04', '2026-11-04'), ['2026-10-29', '2026-11-04']);
check('debut apres fin', SchoolSafeFollowupRange('2026-11-04', '2026-11-01', '2026-11-04'), ['2026-11-01', '2026-11-01']);
check('92 jours au plus', SchoolSafeFollowupRange('2025-01-01', '2026-11-04', '2026-11-04'), ['2026-08-04', '2026-11-04']);

$row = ['TAKES_ROLL' => 't', 'EXPECTED_ROLLS' => '5', 'DONE_ROLLS' => '3', 'STUDENTS' => '12', 'EXPECTED_DAYS' => '5', 'DIARY_DAYS' => '4', 'MISSING_GRADES' => '7'];
check('trois relances', SchoolSafeFollowupAlerts($row), ['2 appel(s) non fait(s)', '1 jour(s) sans cahier de textes', '7 copie(s) non notée(s)']);
check('classe sans appel : pas de relance d appel', SchoolSafeFollowupAlerts(['TAKES_ROLL' => 'f'] + $row)[0], '1 jour(s) sans cahier de textes');
check('tout a jour', SchoolSafeFollowupAlerts(['DONE_ROLLS' => '5', 'DIARY_DAYS' => '5', 'MISSING_GRADES' => '0'] + $row), []);
check('classe vide : pas de relance cahier', SchoolSafeFollowupAlerts(['STUDENTS' => '0', 'TAKES_ROLL' => 'f', 'MISSING_GRADES' => '0'] + $row), []);
check('niveaux', [SchoolSafeFollowupLevel('42.5', 50), SchoolSafeFollowupLevel('55', 50), SchoolSafeFollowupLevel('75', 50), SchoolSafeFollowupLevel(null, 50)], ['ko', 'mid', 'ok', '']);

$sql = '';
SchoolSafeFollowupRows(3, 2026, '2026-10-29', '2026-11-04', '2026-11-04', function ($q) use (&$sql) { $sql = $q; return []; });
check('suivi : ecole et annee', strpos($sql, 'cp.SCHOOL_ID=3 AND cp.SYEAR=2026') !== false, true);
check('suivi : periode', substr_count($sql, "BETWEEN '2026-10-29' AND '2026-11-04'") >= 4, true);
check('suivi : absences par code officiel', strpos($sql, "aco.STATE_CODE='A'") !== false, true);
check('suivi : ecole invalide sans requete', SchoolSafeFollowupRows(0, 2026, 'a', 'b', 'c', function () { throw new Exception('x'); }), []);
$jsql = '';
SchoolSafeFollowupJournal(3, 2026, function ($q) use (&$jsql) { $jsql = $q; return []; }, 9999);
check('journal : ecole et limite', strpos($jsql, 'cp.SCHOOL_ID=3') !== false && substr($jsql, -9) === 'LIMIT 200', true);

// PDF natif : PDFStop en mode enregistrement, moteur Chromium, sans base ni reseau.
$GLOBALS['wkhtmltopdfPath'] = '/usr/bin/chromium';
$_SESSION['locale'] = 'fr_FR.utf8'; $_REQUEST['modname'] = 'Grades/ReportCards.php';
function do_action(...$a) {} function Preferences($k) { return $k === 'THEME' ? 'SchoolSafeCodex' : 'A4'; } function RosarioURL() { return 'https://app.shoolsafe.tech/'; }
function ProgramTitle() { return 'Bulletins'; } function ErrorMessage($e) { return implode(' ', $e); }
require_once $root . '/functions/PDF.php';
$h = PDFStart(['mode' => 3]);
echo '<h1>Bulletin de test</h1><table class="widefat" style="width:100%"><tr><th>Matière</th><th>Note</th></tr><tr><td>Français</td><td>14/20</td></tr></table>' . str_repeat('<p>Ligne du bulletin assez longue pour remplir la largeur de la page imprimée.</p>', 20);
$file = PDFStop($h);
$bytes = is_file($file) ? file_get_contents($file) : '';
check('PDF natif produit', substr($bytes, 0, 5), '%PDF-');
check('PDF natif : texte present', strlen($bytes) > 5000, true);
check('PDF natif : une seule page, sans page blanche', preg_match_all('#/Type\s*/Page[^s]#', $bytes), 1);
if (getenv('KEEP_PDF') && is_dir('/out')) copy($file, '/out/natif.pdf');
if (is_file($file)) unlink($file);
$h = PDFStart(['mode' => 3, 'orientation' => 'landscape', 'margins' => ['top' => 5, 'bottom' => 5, 'left' => 5, 'right' => 5]]);
echo '<p>Paysage</p>';
$file = PDFStop($h);
check('PDF natif paysage', is_file($file) && substr(file_get_contents($file), 0, 5) === '%PDF-', true);
if (getenv('KEEP_PDF') && is_dir('/out') && is_file($file)) copy($file, '/out/paysage.pdf');
if (is_file($file)) unlink($file);

echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
