<?php
// JASPE change la disposition sans retirer d'information (04/11). Usage : php test-disposition.php /var/www/html [dossier PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }

// Reglages
check('decodage', SchoolSafeLetterheadStyleDecode('logo-droite.taille-grande.couleur-bleu'), ['logo' => 'droite', 'taille' => 'grande', 'couleur' => 'bleu']);
check('valeurs inconnues ignorees', SchoolSafeLetterheadStyleDecode('logo-cache.display-none.taille-enorme.titre-centre'), ['titre' => 'centre']);
check('injection ignoree', SchoolSafeLetterheadStyleDecode('logo-droite;}</style><script>x</script>'), []);
check('encodage ordonne', SchoolSafeLetterheadStyleEncode(['taille' => 'grande', 'logo' => 'centre', 'x' => 'y']), 'logo-centre.taille-grande');
check('texte lisible', SchoolSafeLetterheadStyleText(['logo' => 'droite', 'titre' => 'centre']), 'logo à droite, titre centré');
check('sans reglage', SchoolSafeLetterheadStyleText([]), 'disposition d’origine');

// Aucune regle ne masque une information
$all = [];
foreach (SchoolSafeLetterheadStyleSpec() as $key => $values) foreach (array_keys($values) as $value) $all[] = SchoolSafeLetterheadCustomCss([$key => $value], '#176b52');
$css = implode('', $all);
foreach (['display:none', 'visibility', 'opacity', 'font-size:0', 'height:0', 'position:absolute', 'clip', '{content:', ';content:'] as $hidden) check("aucun « $hidden » dans les reglages", strpos($css, $hidden), false);

// JASPE : reponse lue, reglages non permis ecartes
check('JSON de JASPE lu', SchoolSafeLetterheadJaspeParse('Voici : {"logo":"droite","titre":"centré","display":"none","entete":"bandeau"} voilà'), ['logo' => 'droite', 'titre' => 'centre', 'entete' => 'bandeau']);
check('JASPE : disposition de base', SchoolSafeLetterheadJaspeParse('{"disposition":"officielle"}'), ['disposition' => 'officielle']);
check('JASPE : texte sans JSON', SchoolSafeLetterheadJaspeParse('Je ne sais pas'), []);
$r = SchoolSafeLetterheadAsk('mets le logo à droite', 'classique', [], fn($m) => '{"logo":"droite","couleur":"bleu"}');
check('demande par JASPE', [$r['layout'], $r['style'], $r['by']], ['classique', ['logo' => 'droite', 'couleur' => 'bleu'], 'jaspe']);
$r = SchoolSafeLetterheadAsk('Logo à droite, titre centré, texte plus grand, en bleu', 'classique', ['police' => 'serif'], function ($m) { throw new RuntimeException('panne'); });
check('secours sans JASPE', [$r['style'], $r['by']], [['police' => 'serif', 'logo' => 'droite', 'titre' => 'centre', 'taille' => 'grande', 'couleur' => 'bleu'], 'consignes']);
$r = SchoolSafeLetterheadAsk('fais une page officielle', 'classique', [], null);
check('secours : disposition officielle', $r['layout'], 'officielle');
$r = SchoolSafeLetterheadAsk('bonjour', 'moderne', ['logo' => 'centre'], fn($m) => 'rien');
check('demande non comprise : rien ne change', [$r['layout'], $r['style'], $r['by']], ['moderne', ['logo' => 'centre'], '']);
check('consigne a JASPE : pas de contenu modifie', strpos(SchoolSafeLetterheadJaspePrompt('x', []), 'Tu ne modifies ni le texte ni les informations') !== false, true);
check('reglages ecole : disposition personnalisee lue', SchoolSafeLetterheadSettings(3, fn($q) => [1 => ['TITLE' => 'SCHOOLSAFE_DOC_STYLE', 'CONFIG_VALUE' => 'logo-centre.x-y']])['style'], 'logo-centre');
check('validation : style filtre', SchoolSafeLetterheadValidate(['layout' => 'moderne', 'style' => 'titre-cadre.bad-x'])['style'], 'titre-cadre');

// Toutes les informations restent presentes, quelle que soit la disposition
$identity = ['school' => 'Complexe scolaire le sage', 'slogan' => 'Former • Réformer • Exceller', 'address' => '12, avenue de l’École', 'city' => 'Kinshasa',
    'phone' => '+243 000 000 000', 'email' => 'contact@ecole.example', 'website' => 'ecole.example', 'logo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    'accent' => '#176b52', 'ministry' => 'MINISTÈRE', 'province' => 'KINSHASA'];
$must = ['Complexe scolaire le sage', 'Former • Réformer • Exceller', '12, avenue de l’École', '+243 000 000 000', 'contact@ecole.example', 'ecole.example', 'class="ss-logo"', 'Un enfant protégé, parent informé', 'CONTENU-DU-DEVOIR'];
$missing = 0;
foreach (array_keys(SchoolSafeLetterheadLayouts()) as $layout) {
    foreach (['', 'logo-droite.entete-bandeau.couleur-rouge', 'logo-centre.entete-cadre.titre-cadre.police-serif.taille-grande.interligne-aere.pied-gauche', 'entete-double.alignement-droite.taille-petite.couleur-noir'] as $style) {
        $html = SchoolSafeLetterheadPage($identity, $layout, ['title' => 'Devoir', 'body' => '<p>CONTENU-DU-DEVOIR</p>', 'style' => $style]);
        foreach ($must as $needle) if (strpos($html, $needle) === false) { $missing++; echo "   manque « $needle » ($layout, $style)\n"; }
    }
}
check('20 combinaisons : toutes les informations presentes', $missing, 0);
check('couleur appliquee', strpos(SchoolSafeLetterheadPage($identity, 'classique', ['title' => 't', 'style' => 'couleur-bleu']), '#1f4e9c') !== false, true);

// PDF avec disposition personnalisee
$html = SchoolSafeLetterheadPage($identity, 'classique', ['title' => 'Interrogation — Les fractions', 'meta' => [['Matière', 'Mathématiques']], 'student_box' => true, 'points' => '20',
    'body' => '<p>Calculez 3/4 + 1/8.</p>', 'answer_lines' => 4, 'style' => 'logo-centre.entete-bandeau.couleur-bleu.titre-cadre.police-serif.taille-grande']);
try { $pdf = SchoolSafeDocumentPdf($html); } catch (Throwable $e) { $pdf = ''; }
check('PDF personnalise produit', substr($pdf, 0, 5) === '%PDF-', true);
if ($out !== '' && $pdf !== '') file_put_contents($out . '/disposition-jaspe.pdf', $pdf);

// Pages
$hw = file_get_contents($root . '/schoolsafe-homework.php');
check('fiche : demande JASPE limitee en frequence et noms masques', strpos($hw, 'count($hits)<10?SchoolSafeLetterheadJaspeTransport():null') !== false && strpos($hw, 'SchoolSafeJaspeMask(mb_substr($_GET[\'demande\'],0,300)') !== false, true);
$pe = file_get_contents($root . '/papier-ecole.php');
check('papier : proposition avant enregistrement', strpos($pe, "if((\$post['action']??'')==='jaspe'){") !== false && strpos($pe, "'SCHOOLSAFE_DOC_STYLE'=>\$valid['style']") !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
