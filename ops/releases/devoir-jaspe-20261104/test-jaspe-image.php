<?php
// Image de JASPE sur le devoir (04/11). Usage : php test-jaspe-image.php /var/www/html [dossier PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

check('reglage jaspe permis', SchoolSafeLetterheadStyleDecode('jaspe-salut.logo-droite'), ['jaspe' => 'salut', 'logo' => 'droite']);
check('valeur inconnue refusee', SchoolSafeLetterheadStyleDecode('jaspe-geante'), []);
check('images legeres presentes', filesize($root . '/assets/documents/jaspe-salut.png') < 60000 && filesize($root . '/assets/documents/jaspe-debout.png') < 60000, true);
$f = SchoolSafeLetterheadJaspeFigure('salut', 'Courage <b>Josué</b> !');
check('figure : image integree', strpos($f, 'src="data:image/png;base64,') !== false, true);
check('figure : message libre echappe', strpos($f, 'Courage &lt;b&gt;Josué&lt;/b&gt; !') !== false, true);
check('figure : message par defaut', strpos(SchoolSafeLetterheadJaspeFigure('debout', ''), 'Bon travail, tu vas y arriver !') !== false, true);
check('figure : aucune', SchoolSafeLetterheadJaspeFigure('aucune', 'x'), '');
check('consigne : ajoute Jaspe', SchoolSafeLetterheadGuess('ajoute Jaspe sur le devoir')['jaspe'] ?? '', 'salut');
check('consigne : Jaspe en bas', SchoolSafeLetterheadGuess('mets Jaspe en bas de la page')['jaspe'] ?? '', 'debout');
check('consigne : enleve Jaspe', SchoolSafeLetterheadGuess('enlève Jaspe')['jaspe'] ?? '', 'aucune');
$id = ['school' => 'Complexe scolaire le sage', 'phone' => '+243 000', 'email' => 'contact@ecole.example', 'address' => 'Kinshasa'];
$base = ['title' => 'Interrogation — Les fractions', 'student_box' => true, 'points' => '20', 'student_name' => 'Josué Mbala', 'student_class' => '6e primaire',
    'body' => '<p>1. Calculez 3/4 + 1/8.</p><p>2. Simplifiez 6/8.</p><p>3. Comparez 2/3 et 3/5.</p>', 'answer_lines' => 6];
$none = SchoolSafeLetterheadPage($id, 'devoir', $base);
check('sans reglage : pas d image', strpos($none, 'class="ss-jaspe') === false, true);
$both = SchoolSafeLetterheadPage($id, 'devoir', $base + ['style' => 'jaspe-salut', 'jaspe_text' => 'Bonjour Josué, lis bien !']);
check('salut : image en haut, avant le contenu', strpos($both, 'class="ss-jaspe ss-jaspe-salut"') !== false && strpos($both, 'class="ss-jaspe ss-jaspe-salut"') < strpos($both, 'class="ss-body"'), true);
check('salut : informations gardees', strpos($both, 'Complexe scolaire le sage') !== false && strpos($both, 'Un enfant protégé, un parent informé') !== false && strpos($both, 'Josué Mbala') !== false, true);
$down = SchoolSafeLetterheadPage($id, 'devoir', $base + ['style' => 'jaspe-debout']);
check('debout : image apres les reponses', strpos($down, 'class="ss-jaspe ss-jaspe-debout"') > strpos($down, 'class="ss-lines"'), true);
foreach (['salut' => $both, 'debout' => $down] as $mode => $html) {
    try { $pdf = SchoolSafeDocumentPdf($html); } catch (Throwable $e) { $pdf = ''; }
    check("PDF avec JASPE ($mode)", substr($pdf, 0, 5) === '%PDF-', true);
    if ($out !== '' && $pdf !== '') file_put_contents($out . '/jaspe-' . $mode . '.pdf', $pdf);
}
$hw = file_get_contents($root . '/schoolsafe-homework.php');
check('fiche : choix image et message', strpos($hw, 'name="img"') !== false && strpos($hw, 'name="bulle" maxlength="90"') !== false, true);
check('fiche : valeurs image limitees', strpos($hw, "in_array(\$_GET['img'],['aucune','salut','debout'],true)") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
