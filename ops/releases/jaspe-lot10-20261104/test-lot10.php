<?php
// Lot 10 JASPE (04/11). Partie 1 sans reseau ; partie 2 (--ia) : vrais appels a Groq, sans aucune donnee d'eleve.
$root = $argv[1] ?? '/var/www/html';
chdir($root);
function DBEscapeString($v) { return str_replace("'", "''", (string) $v); }
function User($k) { return $k === 'PROFILE' ? 'admin' : ($k === 'STAFF_ID' ? 1 : 'Test'); }
function UserSchool() { return 3; }
function SchoolInfo($k) { return 'Complexe scolaire le sage'; }
function Config($k) { return ''; }
require_once $root . '/ProgramFunctions/SchoolSafeJaspeAssist.fnc.php';
$ok = 0; $ko = 0;
function check($label, $got, $want) { global $ok, $ko; if ($got === $want) { $ok++; echo "OK   $label\n"; } else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; } }
function fails(callable $f): string { try { $f(); return ''; } catch (RuntimeException $e) { return $e->getMessage(); } }
foreach (['courriers.php', 'synthese-bulletin.php', 'suivi-pedagogique.php', 'note-devoir.php', 'bulletin.php', 'ProgramFunctions/SchoolSafeJaspeAssist.fnc.php'] as $f) {
    $out = []; exec('php -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $out, $code); check('syntaxe ' . $f, $code, 0);
}
$p = SchoolSafeJaspeLetterPrompt('Convocation d’un parent', 'retards répétés le matin');
check('courrier : balises demandees', strpos($p, '{eleve}') !== false && strpos($p, "N'invente aucun fait") !== false, true);
check('courrier : demande trop courte', fails(fn() => SchoolSafeJaspeLetterPrompt('X', 'ok')), 'Dites en quelques mots ce que le courrier doit dire.');
check('nettoyage : gras retire, [élève] -> {eleve}', SchoolSafeJaspeLetterClean("Voici :\nMadame, Monsieur,\n\n**Votre enfant** [élève] est attendu."), "Madame, Monsieur,\n\nVotre enfant {eleve} est attendu.");
check('masquage des noms', SchoolSafeJaspeMask('Josué Kabeya est souvent en retard', ['Josué Kabeya', 'Kabeya']), '[élève] est souvent en retard');
check('mots : lecture JSON', SchoolSafeJaspeAppreciationParse('ok {"mots":["Bon travail.","À renforcer."]}', 2), [0 => 'Bon travail.', 1 => 'À renforcer.']);
check('mots : reponse illisible', SchoolSafeJaspeAppreciationParse('pas de json', 2), []);
check('mot de secours (bonne annee)', strpos(SchoolSafeJaspeAppreciationFallback(80.0, 50, ['Lecture'], []), 'Très bonne année') === 0, true);
check('mot de secours (annee difficile)', strpos(SchoolSafeJaspeAppreciationFallback(40.0, 50, [], ['Calcul']), 'Année difficile') === 0, true);
check('faits sans nom', SchoolSafeJaspeAppreciationFacts(68.5, 3, 25, ['Lecture'], ['Calcul'], 2), 'résultat 68,5 %, place 3 sur 25, points forts : Lecture, à travailler : Calcul, absences : 2');
check('langues', array_keys(SchoolSafeJaspeLanguages()), ['simple', 'ln', 'sw']);
check('langue inconnue refusee', fails(fn() => SchoolSafeJaspeExplainPrompt('xx', 'faits')), 'Langue inconnue.');
$sig = SchoolSafeDropoutSignals(['ABSENCES' => '4', 'LATES' => '1', 'LOW' => '3', 'AVG_RECENT' => '40', 'AVG_BEFORE' => '62', 'REMEDIATION' => '0']);
check('decrochage : 3 signaux', $sig, ['4 absences en 30 jours', '3 notes sous le seuil', 'moyenne en baisse (62 % → 40 %)']);
check('decrochage : rien a signaler', SchoolSafeDropoutSignals(['ABSENCES' => '1', 'LATES' => '0', 'LOW' => '0', 'AVG_RECENT' => null, 'AVG_BEFORE' => null, 'REMEDIATION' => '0']), []);
check('limite de 10 demandes par minute', (function () { $_SESSION['jaspe_ai_hits'] = array_fill(0, 10, time()); return SchoolSafeJaspeAssistAllowed(); })(), false);

if (in_array('--ia', $argv, true)) {
    $t = microtime(true);
    $letter = SchoolSafeJaspeAssistAsk(SchoolSafeJaspeLetterPrompt('Convocation d’un parent', 'retards répétés le matin depuis deux semaines ; rencontrer les parents'), 'courrier', 1500, null, 'Pour cette demande : écris le courrier complet demandé (120 à 220 mots), sans te limiter à quatre phrases.');
    if ($letter === null) echo "(reponse brute vide)
";
    $clean = $letter === null ? '' : SchoolSafeJaspeLetterClean($letter);
    check('JASPE redige un courrier avec {eleve}', $clean !== '' && strpos($clean, '{eleve}') !== false, true);
    echo '---- courrier (' . round(microtime(true) - $t, 1) . " s) ----\n" . mb_substr($clean, 0, 700) . "\n";
    $words = SchoolSafeJaspeAppreciationParse((string) SchoolSafeJaspeAssistAsk(SchoolSafeJaspeAppreciationPrompt([SchoolSafeJaspeAppreciationFacts(78, 2, 20, ['Lecture'], [], 1), SchoolSafeJaspeAppreciationFacts(42, 18, 20, [], ['Calcul', 'Écriture'], 6)]), 'appreciations', 1500, null, 'Pour cette demande : réponds uniquement par le JSON demandé.'), 2);
    check('JASPE propose 2 mots du titulaire', count($words), 2);
    echo "---- mots ----\n" . implode("\n", $words) . "\n";
    foreach (['ln', 'sw'] as $l) {
        $x = SchoolSafeJaspeAssistAsk(SchoolSafeJaspeExplainPrompt($l, 'Matière : Mathématiques. Travail : devoir « Les fractions ». Note : 4 sur 10 (40 %). Seuil de réussite : 50 %.'), 'explication', 900, null, SchoolSafeJaspeLanguageRule($l));
        check('explication en ' . $l, $x !== null && mb_strlen($x) > 40, true);
        echo '---- ' . $l . " ----\n" . mb_substr((string) $x, 0, 400) . "\n";
    }
}
echo "\n$ok OK, $ko KO\n";
exit($ko ? 1 : 0);
