<?php
// Papier de l'ecole (04/11). Usage : php test-papier.php /var/www/html [dossier ou deposer les PDF]
$root = $argv[1] ?? '/var/www/html';
$out = $argv[2] ?? '';
chdir($root);
require_once $root . '/ProgramFunctions/SchoolSafeLetterhead.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }

// Filtre du contenu : mise en page gardee, danger retire
$c = SchoolSafeLetterheadContent('<p style="text-align:center;color:#c00;position:fixed">Titre</p><p align="right">Droite</p>'
    . '<table border="1"><tr><td colspan="2" onclick="x()">A</td></tr></table><script>alert(1)</script><img src="https://evil.example/x.png"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">'
    . '<a href="javascript:alert(1)">lien</a><p style="background-color:url(x)">b</p><div style="page-break-before:always">Page 2</div>');
check('alignement et couleur gardes', strpos($c, 'style="text-align:center;color:#c00"') !== false, true);
check('position retiree', strpos($c, 'position') === false, true);
check('align converti', strpos($c, 'style="text-align:right"') !== false, true);
check('fusion de cellules gardee', strpos($c, 'colspan="2"') !== false && strpos($c, 'onclick') === false, true);
check('script retire', strpos($c, '<script') === false && strpos($c, 'alert(1)</script') === false, true);
check('image exterieure retiree', strpos($c, 'evil.example') === false, true);
check('image GIF integree gardee', strpos($c, 'data:image/gif;base64') !== false, true);
check('lien javascript neutralise', strpos($c, 'javascript:') === false, true);
check('url() refusee dans le style', strpos($c, 'url(') === false, true);
check('saut de page garde', strpos($c, 'page-break-before:always') !== false, true);
check('image locale integree par le rappel', strpos(SchoolSafeLetterheadContent('<img src="/assets/x.png">', fn($s) => $s === '/assets/x.png' ? 'data:image/png;base64,AAAA' : ''), 'data:image/png;base64,AAAA') !== false, true);
check('style : valeurs refusees', SchoolSafeLetterheadStyle('color:expression(alert(1));font-size:12pt;width:100vw'), 'font-size:12pt');

// Reglages et dates
check('5 dispositions', array_keys(SchoolSafeLetterheadLayouts()), ['classique', 'officielle', 'moderne', 'devoir', 'compacte']);
check('reglages par defaut', SchoolSafeLetterheadSettings(3, fn($q) => [])['layout'], 'classique');
check('reglages lus', SchoolSafeLetterheadSettings(3, fn($q) => [1 => ['TITLE' => 'SCHOOLSAFE_DOC_LAYOUT', 'CONFIG_VALUE' => 'officielle'], 2 => ['TITLE' => 'SCHOOLSAFE_DOC_PROVINCE', 'CONFIG_VALUE' => 'KINSHASA']]), ['layout' => 'officielle', 'ministry' => SchoolSafeLetterheadDefaults()['ministry'], 'province' => 'KINSHASA']);
check('disposition inconnue ignoree', SchoolSafeLetterheadSettings(3, fn($q) => [1 => ['TITLE' => 'SCHOOLSAFE_DOC_LAYOUT', 'CONFIG_VALUE' => 'x']])['layout'], 'classique');
check('validation : disposition obligatoire', fails(fn() => SchoolSafeLetterheadValidate(['layout' => 'x'])), 'Choisissez une disposition.');
check('validation : province trop longue', fails(fn() => SchoolSafeLetterheadValidate(['layout' => 'moderne', 'province' => str_repeat('a', 161)])) !== '', true);
check('date en francais', SchoolSafeLetterheadDate('2026-11-05'), 'jeudi 5 novembre 2026');
check('date invalide vide', SchoolSafeLetterheadDate('2026-13-40'), '');
check('logo : fichier natif integre', strpos(SchoolSafeLetterheadLogo('', $root . '/assets/themes/SchoolSafeCodex/logo.png'), 'data:image/') === 0 || !is_file($root . '/assets/themes/SchoolSafeCodex/logo.png'), true);

// PDF reel, 5 dispositions
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
$identity = ['school' => 'Complexe scolaire le sage', 'slogan' => 'Former • Réformer • Exceller', 'address' => '12, avenue de l’École', 'city' => 'Kinshasa',
    'phone' => '+243 000 000 000', 'email' => 'contact@ecole.example', 'website' => 'ecole.example', 'logo' => 'data:image/png;base64,' . $png, 'accent' => '#176b52',
    'ministry' => SchoolSafeLetterheadDefaults()['ministry'], 'province' => 'PROVINCE ÉDUCATIONNELLE KINSHASA'];
foreach (array_keys(SchoolSafeLetterheadLayouts()) as $layout) {
    $html = SchoolSafeLetterheadPage($identity, $layout, ['title' => 'Interrogation — Les fractions', 'meta' => [['Matière', 'Mathématiques'], ['À rendre le', 'jeudi 5 novembre 2026']],
        'body' => $c . str_repeat('<p>Exercice : calculez 3/4 + 1/8 et expliquez votre démarche.</p>', 40), 'student_box' => true, 'points' => '20', 'answer_lines' => 8]);
    check("$layout : pied avec nom de l ecole", strpos($html, '<footer class="ss-foot"><div class="ss-foot-school">Complexe scolaire le sage</div>') !== false, true);
    try { $pdf = SchoolSafeDocumentPdf($html); } catch (Throwable $e) { $pdf = ''; echo 'PDF : ' . $e->getMessage() . "\n"; }
    check("$layout : PDF produit", substr($pdf, 0, 5) === '%PDF-' && strlen($pdf) > 5000, true);
    if ($out !== '' && $pdf !== '') file_put_contents($out . '/papier-' . $layout . '.pdf', $pdf);
}
check('pied : logo et slogan SchoolSafe', strpos(SchoolSafeLetterheadFooter($identity), 'Un enfant protégé, parent informé') !== false && strpos(SchoolSafeLetterheadFooter($identity), 'class="ss-brand-logo" alt="SchoolSafe" src="data:image/png;base64,') !== false, true);
check('officielle : en-tete RDC', strpos(SchoolSafeLetterheadPage($identity, 'officielle', ['title' => 't']), 'RÉPUBLIQUE DÉMOCRATIQUE DU CONGO') !== false, true);

// Pages
$hw = file_get_contents($root . '/schoolsafe-homework.php');
check('fiche : parametres limites', strpos($hw, "array_diff(array_keys(\$_GET), ['assignment_id','download','layout','lines','box'])") !== false, true);
check('fiche : direction limitee a son ecole', strpos($hw, '" WHERE ga.ASSIGNMENT_ID=".(int)$id." AND c.SCHOOL_ID=".$school." AND c.SYEAR=".$year') !== false, true);
check('fiche : images locales sous assets seulement', strpos($hw, "strpos(\$real,__DIR__.'/assets/')!==0") !== false, true);
$pe = file_get_contents($root . '/papier-ecole.php');
check('reglages : droit configuration et jeton', strpos($pe, "AllowEdit('School_Setup/Configuration.php')") !== false && strpos($pe, 'hash_equals($csrf') !== false, true);
echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
