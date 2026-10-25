<?php
// SchoolSafe — U4e REEL : envoi via le code isole vers l'adresse autorisee.
// Cle lue depuis env BREVO_API_KEY uniquement. Sortie EXPURGEE : aucun secret,
// aucune cle, aucun token n'est jamais affiche. Code HTTP + message-id seuls.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(2); }
chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeMailTemplate.fnc.php';
require_once __DIR__ . '/../../ProgramFunctions/SchoolSafeBrevo.fnc.php';

$to = 'lomsmedy@gmail.com'; // adresse de test explicitement autorisee
$event = ['event_type' => 'security.entry', 'school_id' => 1,
    'payload' => ['title' => 'Test recette reelle U4e', 'student_name' => 'Eleve Temoin',
        'time' => '08:00', 'message' => 'Arrivee enregistree (test isole autorise).']];
$identity = ['school_name' => 'Ecole Temoin', 'locale' => 'fr', 'motto' => 'Savoir - Securite',
    'phone' => '+000 00 00 00', 'email' => 'contact@ecole-temoin.test'];
$mail = SchoolSafeMailBuild($event, $identity);
echo 'FROM=' . $mail['from'] . "\n";
echo 'SUBJECT=' . $mail['subject'] . "\n";
echo 'DESTINATAIRE=' . $to . "\n";

try {
    $r = SchoolSafeBrevoSend($to, $mail);
    echo 'ACCEPTED=oui' . "\n";
    echo 'MSGID=' . $r['provider_msg_id'] . "\n";
} catch (RuntimeException $e) {
    $m = $e->getMessage();
    $m = preg_replace('/[A-Za-z0-9_\\-\\.]{20,}/', '***', $m);
    if (strpos($m, 'BREVO_ERROR:') === 0) {
        $parts = explode(':', $m, 3);
        echo 'ACCEPTED=non' . "\n" . 'HTTP_CODE=' . ($parts[1] ?? '?') . "\n";
    } else {
        echo 'ACCEPTED=non' . "\n" . 'BLOCK=' . substr($m, 0, 60) . "\n";
    }
    exit(1);
}
