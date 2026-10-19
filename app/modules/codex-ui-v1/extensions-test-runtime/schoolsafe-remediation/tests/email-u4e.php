<?php
// SchoolSafe — Recette finale : test cible U4e (isole, sans envoi reseau).
// Construit le courriel officiel pour l'adresse autorisee via le code isole,
// prouve le payload Brevo, puis prouve le BLOCAGE sans cle (jamais de faux succes).
// Sortie 0 ssi toutes les verifications passent. Aucun secret affiche.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeBrevo.fnc.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "ok   $label\n"; return; }
    $failures++;
    echo "FAIL $label : attendu " . var_export($expected, true) . ", obtenu " . var_export($actual, true) . "\n";
}

$to = 'lomsmedy@gmail.com'; // adresse de test explicitement autorisee (ordre de recette)
$event = ['event_type' => 'security.entry', 'school_id' => 1,
    'payload' => ['title' => 'Test recette isolee U4e', 'student_name' => 'Eleve Temoin',
        'time' => '08:00', 'message' => 'Arrivee enregistree (test isole, sans envoi).']];
$identity = ['school_name' => 'Ecole Temoin', 'locale' => 'fr', 'motto' => 'Savoir - Securite',
    'phone' => '+000 00 00 00', 'email' => 'contact@ecole-temoin.test'];
$mail = SchoolSafeMailBuild($event, $identity);
check('expediteur-officiel', $mail['from'], 'securite@mail.shoolsafe.tech');
check('sujet-non-vide', $mail['subject'] !== '', true);
check('html-non-vide', strlen($mail['html']) > 500, true);
check('texte-non-vide', strlen($mail['text']) > 50, true);
check('html-contient-ecole', strpos($mail['html'], 'Ecole Temoin') !== false, true);
echo 'INFO html-bytes=' . strlen($mail['html']) . ' sha=' . hash('sha256', $mail['html']) . "\n";

$p = SchoolSafeBrevoBuildPayload($to, $mail);
check('payload-destinataire', $p['to'][0]['email'], $to);
check('payload-expediteur', $p['sender']['email'], 'securite@mail.shoolsafe.tech');

check('cle-absente-isole', SchoolSafeBrevoConfig(), null);
try {
    SchoolSafeBrevoSend($to, $mail);
    check('envoi-bloque-sans-cle', 'ENVOYE', 'BLOQUE');
} catch (RuntimeException $e) {
    check('envoi-bloque-sans-cle', $e->getMessage(), 'NO_EMAIL_PROVIDER');
}

$fake = function ($url, $headers, $body) {
    return ['http_code' => 201, 'body' => '{"messageId":"<u4e-test@localhost>"}'];
};
$r = SchoolSafeBrevoSend($to, $mail,
    ['api_key' => 'CLE_TEST_NON_REELLE', 'base_url' => 'https://api.brevo.com/v3'], $fake);
check('accepte-simule', $r['accepted'], true);
check('msgid-simule', $r['provider_msg_id'], '<u4e-test@localhost>');

if ($failures > 0) { echo "U4e RED ($failures echecs)\n"; exit(1); }
echo "U4e CIBLE GREEN (construction + blocage sans cle ; envoi reel en attente de cle)\n";
